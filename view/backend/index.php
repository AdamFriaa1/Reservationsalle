<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin', 'gestionnaire']);

$salleC       = new SalleC();
$batimentC    = new BatimentC();
$reservationC = new ReservationC();
$utilisateurC = new UtilisateurC();

try { $reservationC->cloturerReservationsEchues(); } catch (Throwable $e) { /* silencieux */ }

$erreur = '';
try {
    $cSalles      = $salleC->compteurs();
    $cReserv      = $reservationC->compteurs();
    $batiments    = $batimentC->showBatimentsAvecDetails();
    $topSalles    = $reservationC->topSalles(6);
    $prochaines   = $reservationC->prochaines(6);
    $enAttente    = $reservationC->filterReservations(['statut' => 'en_attente', 'orderBy' => 'date', 'orderDir' => 'ASC']);
    $utilisateurs = $utilisateurC->showUtilisateurs();

    // Volume des 14 derniers jours
    $debut14 = date('Y-m-d', strtotime('-13 days'));
    $parJour = $reservationC->reservationsParJour($debut14, date('Y-m-d'));
} catch (Throwable $e) {
    $erreur = "Impossible de charger le tableau de bord : " . $e->getMessage()
            . " — vérifiez que la base « reserva_salles » est importée dans phpMyAdmin.";
    $cSalles = ['total' => 0, 'disponibles' => 0, 'maintenance' => 0, 'capacite_totale' => 0];
    $cReserv = ['total' => 0, 'en_attente' => 0, 'validees' => 0, 'refusees' => 0, 'annulees' => 0, 'terminees' => 0];
    $batiments = []; $topSalles = []; $prochaines = []; $enAttente = []; $utilisateurs = []; $parJour = [];
}

// Série complète sur 14 jours (les jours sans réservation valent zéro)
$serie = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $serie[$d] = 0;
}
foreach ($parJour as $p) {
    if (isset($serie[$p['jour']])) $serie[$p['jour']] = (int)$p['nb'];
}
$maxSerie = max(1, max($serie));
$maxTop   = max(1, max(array_map(fn($s) => (int)$s['nb'], $topSalles ?: [['nb' => 1]])));

$titrePage  = 'Tableau de bord';
$pageActive = 'dashboard';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="fas fa-gauge-high"></i> Tableau de bord</h2>
        <p>Vue d'ensemble du parc de salles et de l'activité de réservation.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="listReservation.php?statut=en_attente" class="btn btn-warning">
            <i class="fas fa-hourglass-half"></i> Demandes en attente (<?= (int)$cReserv['en_attente'] ?>)
        </a>
        <a href="addReservation.php" class="btn btn-primary"><i class="fas fa-plus"></i> Réservation manuelle</a>
    </div>
</div>

<?php flash_afficher(); ?>
<?php if ($erreur !== ''): ?>
    <div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i><span><?= e($erreur) ?></span></div>
<?php endif; ?>

<div class="grid grid-4 mb-3">
    <div class="stat-card">
        <div class="stat-icon bleu"><i class="fas fa-building"></i></div>
        <div><div class="stat-value"><?= count($batiments) ?></div><div class="stat-label">Bâtiments</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon violet"><i class="fas fa-door-open"></i></div>
        <div>
            <div class="stat-value"><?= (int)$cSalles['total'] ?></div>
            <div class="stat-label"><?= (int)$cSalles['disponibles'] ?> réservables · <?= (int)$cSalles['maintenance'] ?> en maintenance</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-hourglass-half"></i></div>
        <div><div class="stat-value"><?= (int)$cReserv['en_attente'] ?></div><div class="stat-label">Demandes à traiter</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon vert"><i class="fas fa-circle-check"></i></div>
        <div><div class="stat-value"><?= (int)$cReserv['validees'] ?></div><div class="stat-label">Réservations validées</div></div>
    </div>
</div>

<div class="grid grid-4 mb-3">
    <div class="stat-card">
        <div class="stat-icon gris"><i class="fas fa-list"></i></div>
        <div><div class="stat-value"><?= (int)$cReserv['total'] ?></div><div class="stat-label">Réservations au total</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon rouge"><i class="fas fa-circle-xmark"></i></div>
        <div><div class="stat-value"><?= (int)$cReserv['refusees'] + (int)$cReserv['annulees'] ?></div><div class="stat-label">Refusées / annulées</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bleu"><i class="fas fa-users"></i></div>
        <div><div class="stat-value"><?= count($utilisateurs) ?></div><div class="stat-label">Comptes utilisateurs</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon vert"><i class="fas fa-chair"></i></div>
        <div><div class="stat-value"><?= (int)$cSalles['capacite_totale'] ?></div><div class="stat-label">Places au total</div></div>
    </div>
</div>

<div class="grid grid-2">

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-column"></i> Réservations des 14 derniers jours</h3>
        </div>
        <div class="card-body">
            <div class="histo">
                <?php foreach ($serie as $jourIso => $nb): ?>
                    <div class="histo-col" title="<?= e(date('d/m/Y', strtotime($jourIso))) ?> : <?= $nb ?>">
                        <div class="histo-barre" style="height: <?= max(3, (int)round(($nb / $maxSerie) * 145)) ?>px;"></div>
                        <div class="histo-label"><?= e(date('d/m', strtotime($jourIso))) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-ranking-star"></i> Salles les plus demandées</h3>
            <a href="statistiques.php" class="btn btn-light btn-sm">Détail</a>
        </div>
        <div class="card-body">
            <?php if (!$topSalles): ?>
                <p class="text-muted">Aucune donnée pour l'instant.</p>
            <?php else: ?>
                <div class="barres">
                    <?php foreach ($topSalles as $s): ?>
                        <div class="barre-ligne">
                            <div class="barre-label" title="<?= e($s['nom'] . ' — ' . $s['nom_batiment']) ?>">
                                <?= e($s['nom']) ?>
                            </div>
                            <div class="barre-piste">
                                <div class="barre-remplie" style="width:0"
                                     data-largeur="<?= (int)round(((int)$s['nb'] / $maxTop) * 100) ?>"></div>
                            </div>
                            <div class="barre-valeur"><?= (int)$s['nb'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-hourglass-half"></i> Demandes en attente de validation</h3>
        <a href="listReservation.php?statut=en_attente" class="btn btn-light btn-sm">
            Tout voir <i class="fas fa-arrow-right"></i>
        </a>
    </div>
    <?php if (!$enAttente): ?>
        <div class="vide">
            <i class="fas fa-circle-check"></i>
            <h3>Aucune demande en attente</h3>
            <p>Toutes les demandes ont été traitées.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Objet</th><th>Demandeur</th><th>Salle</th><th>Créneau</th><th>Pers.</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($enAttente, 0, 6) as $r): ?>
                    <?php $conflits = $reservationC->detecterConflits(
                        (int)$r['id_salle'], $r['date_debut'], $r['date_fin'], (int)$r['id_reservation']); ?>
                    <tr>
                        <td>
                            <div class="cell-titre"><?= e($r['titre']) ?></div>
                            <?php if ($conflits): ?>
                                <div class="cell-sub" style="color:var(--rouge);">
                                    <i class="fas fa-triangle-exclamation"></i> <?= count($conflits) ?> conflit(s)
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e($r['prenom_utilisateur'] . ' ' . $r['nom_utilisateur']) ?>
                            <div class="cell-sub"><?= e((string)$r['departement']) ?></div>
                        </td>
                        <td>
                            <?= e($r['nom_salle']) ?>
                            <div class="cell-sub"><?= e($r['nom_batiment']) ?></div>
                        </td>
                        <td>
                            <?= e(date('d/m/Y', strtotime($r['date_debut']))) ?>
                            <div class="cell-sub"><?= e(fmt_heure($r['date_debut'])) ?> – <?= e(fmt_heure($r['date_fin'])) ?></div>
                        </td>
                        <td><?= (int)$r['nb_participants'] ?></td>
                        <td>
                            <div class="td-actions">
                                <a href="traiterReservation.php?id=<?= (int)$r['id_reservation'] ?>&action=valider"
                                   class="btn btn-success btn-sm" title="Valider"
                                   onclick="return confirmerAction('Valider cette réservation ?');">
                                    <i class="fas fa-check"></i>
                                </a>
                                <button type="button" class="btn btn-danger btn-sm" title="Refuser"
                                        data-modale="modaleRefus"
                                        data-id="<?= (int)$r['id_reservation'] ?>"
                                        data-titre="<?= e($r['titre']) ?>">
                                    <i class="fas fa-xmark"></i>
                                </button>
                                <a href="updateReservation.php?id=<?= (int)$r['id_reservation'] ?>"
                                   class="btn btn-light btn-sm" title="Déplacer"><i class="fas fa-arrows-up-down-left-right"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-calendar-day"></i> Prochaines réunions</h3>
        <a href="calendrier.php" class="btn btn-light btn-sm">Planning global</a>
    </div>
    <?php if (!$prochaines): ?>
        <div class="vide"><i class="fas fa-calendar-xmark"></i><h3>Rien de prévu</h3></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Créneau</th><th>Objet</th><th>Salle</th><th>Organisateur</th><th>Statut</th></tr></thead>
                <tbody>
                <?php foreach ($prochaines as $r): ?>
                    <tr>
                        <td>
                            <div class="cell-titre"><?= e(date('d/m/Y', strtotime($r['date_debut']))) ?></div>
                            <div class="cell-sub"><?= e(fmt_heure($r['date_debut'])) ?> – <?= e(fmt_heure($r['date_fin'])) ?></div>
                        </td>
                        <td><?= e($r['titre']) ?></td>
                        <td><?= e($r['nom_salle']) ?><div class="cell-sub"><?= e($r['nom_batiment']) ?></div></td>
                        <td><?= e($r['prenom_utilisateur'] . ' ' . $r['nom_utilisateur']) ?></td>
                        <td><span class="badge <?= classe_statut($r['statut']) ?>"><?= e(libelle_statut($r['statut'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modale de refus -->
<div class="modale" id="modaleRefus">
    <div class="modale-boite">
        <form method="post" action="traiterReservation.php" id="formRefus" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="refuser">
            <input type="hidden" name="id" data-champ="id">
            <div class="modale-entete">
                <h3><i class="fas fa-circle-xmark"></i> Refuser la demande</h3>
                <button type="button" class="fermer-modale"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="modale-corps">
                <p class="mb-2">Demande : <strong data-champ="titre"></strong></p>
                <div class="form-group">
                    <label for="motif_refus">Motif du refus <span class="req">*</span></label>
                    <textarea id="motif_refus" name="motif_refus"
                              placeholder="Salle déjà mobilisée pour un séminaire…"></textarea>
                    <span class="aide">Ce motif est envoyé au demandeur par email.</span>
                    <span class="erreur-champ" id="err-motif_refus"></span>
                </div>
            </div>
            <div class="modale-pied">
                <button type="button" class="btn btn-light" data-fermer>Annuler</button>
                <button type="submit" class="btn btn-danger"><i class="fas fa-paper-plane"></i> Refuser et notifier</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>

<script>
Valider.attacher('formRefus', {
    motif_refus: [
        { test: v => Valider.requis(v),        message: 'Le motif est obligatoire.' },
        { test: v => Valider.longueur(v, 10, 500), message: 'Entre 10 et 500 caractères.' }
    ]
});
</script>
