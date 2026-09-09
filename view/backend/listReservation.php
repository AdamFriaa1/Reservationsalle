<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin', 'gestionnaire']);

$reservationC = new ReservationC();
$salleC       = new SalleC();
$batimentC    = new BatimentC();
$utilisateurC = new UtilisateurC();

try { $reservationC->cloturerReservationsEchues(); } catch (Throwable $e) { /* silencieux */ }

$filtres = [
    'recherche'   => trim($_GET['recherche'] ?? ''),
    'statut'      => $_GET['statut'] ?? 'tous',
    'salle'       => $_GET['salle'] ?? 'toutes',
    'batiment'    => $_GET['batiment'] ?? 'tous',
    'utilisateur' => $_GET['utilisateur'] ?? 'tous',
    'date_debut'  => trim($_GET['date_debut'] ?? ''),
    'date_fin'    => trim($_GET['date_fin'] ?? ''),
    'orderBy'     => $_GET['orderBy'] ?? 'date',
    'orderDir'    => $_GET['orderDir'] ?? 'DESC',
];

$erreurFiltre = '';
if ($filtres['date_debut'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtres['date_debut'])) {
    $erreurFiltre = 'La date de début du filtre est invalide.';
    $filtres['date_debut'] = '';
}
if ($filtres['date_fin'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtres['date_fin'])) {
    $erreurFiltre = 'La date de fin du filtre est invalide.';
    $filtres['date_fin'] = '';
}
if ($filtres['date_debut'] !== '' && $filtres['date_fin'] !== ''
    && $filtres['date_fin'] < $filtres['date_debut']) {
    $erreurFiltre = 'La date de fin doit être postérieure à la date de début.';
    $filtres['date_fin'] = '';
}

// Le formulaire utilise date_debut/date_fin, le contrôleur attend date_min/date_max.
$filtresBd = $filtres;
$filtresBd['date_min'] = $filtres['date_debut'];
$filtresBd['date_max'] = $filtres['date_fin'];

$erreur = '';
try {
    $reservations = $reservationC->filterReservations($filtresBd);
    $salles       = $salleC->showSalles();
    $batiments    = $batimentC->showBatiments();
    $utilisateurs = $utilisateurC->getUtilisateursActifs();
    $compteurs    = $reservationC->compteurs();
} catch (Throwable $e) {
    $erreur = 'Erreur lors du chargement : ' . $e->getMessage();
    $reservations = []; $salles = []; $batiments = []; $utilisateurs = [];
    $compteurs = ['total' => 0, 'en_attente' => 0, 'validees' => 0, 'refusees' => 0, 'annulees' => 0, 'terminees' => 0];
}

$titrePage  = 'Réservations';
$pageActive = 'reservations';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <div class="fil-ariane"><a href="index.php">Tableau de bord</a> / Réservations</div>
        <h2><i class="fas fa-calendar-check"></i> Gestion des réservations</h2>
        <p>Validez, refusez ou déplacez les demandes de réservation.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="conflits.php" class="btn btn-light"><i class="fas fa-triangle-exclamation"></i> Conflits</a>
        <a href="addReservation.php" class="btn btn-primary"><i class="fas fa-plus"></i> Réservation manuelle</a>
    </div>
</div>

<?php flash_afficher(); ?>
<?php if ($erreur !== ''): ?>
    <div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i><span><?= e($erreur) ?></span></div>
<?php endif; ?>
<?php if ($erreurFiltre !== ''): ?>
    <div class="alert alert-warning"><i class="fas fa-triangle-exclamation"></i><span><?= e($erreurFiltre) ?></span></div>
<?php endif; ?>

<div class="grid grid-4 mb-3">
    <a href="?statut=tous" class="stat-card lien-carte">
        <div class="stat-icon gris"><i class="fas fa-list"></i></div>
        <div><div class="stat-value"><?= (int)$compteurs['total'] ?></div><div class="stat-label">Toutes</div></div>
    </a>
    <a href="?statut=en_attente" class="stat-card lien-carte">
        <div class="stat-icon orange"><i class="fas fa-hourglass-half"></i></div>
        <div><div class="stat-value"><?= (int)$compteurs['en_attente'] ?></div><div class="stat-label">En attente</div></div>
    </a>
    <a href="?statut=validee" class="stat-card lien-carte">
        <div class="stat-icon vert"><i class="fas fa-circle-check"></i></div>
        <div><div class="stat-value"><?= (int)$compteurs['validees'] ?></div><div class="stat-label">Validées</div></div>
    </a>
    <a href="?statut=refusee" class="stat-card lien-carte">
        <div class="stat-icon rouge"><i class="fas fa-circle-xmark"></i></div>
        <div><div class="stat-value"><?= (int)$compteurs['refusees'] ?></div><div class="stat-label">Refusées</div></div>
    </a>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-sliders"></i> Recherche multicritère</h3></div>
    <div class="card-body">
        <form method="get" id="formFiltres" class="filtres-grid" novalidate>
            <div class="form-group">
                <label for="recherche">Recherche</label>
                <input type="search" id="recherche" name="recherche" value="<?= e($filtres['recherche']) ?>"
                       placeholder="Objet, demandeur, salle…">
            </div>
            <div class="form-group">
                <label for="statut">Statut</label>
                <select id="statut" name="statut">
                    <option value="tous">Tous</option>
                    <?php foreach (Reservation::STATUTS as $st): ?>
                        <option value="<?= e($st) ?>" <?= $filtres['statut'] === $st ? 'selected' : '' ?>>
                            <?= e(libelle_statut($st)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="batiment">Bâtiment</label>
                <select id="batiment" name="batiment">
                    <option value="tous">Tous</option>
                    <?php foreach ($batiments as $b): ?>
                        <option value="<?= (int)$b['id_batiment'] ?>" <?= (string)$filtres['batiment'] === (string)$b['id_batiment'] ? 'selected' : '' ?>>
                            <?= e($b['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="salle">Salle</label>
                <select id="salle" name="salle">
                    <option value="toutes">Toutes</option>
                    <?php foreach ($salles as $s): ?>
                        <option value="<?= (int)$s['id_salle'] ?>" <?= (string)$filtres['salle'] === (string)$s['id_salle'] ? 'selected' : '' ?>>
                            <?= e($s['nom']) ?> — <?= e($s['nom_batiment']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="utilisateur">Demandeur</label>
                <select id="utilisateur" name="utilisateur">
                    <option value="tous">Tous</option>
                    <?php foreach ($utilisateurs as $u): ?>
                        <option value="<?= (int)$u['id_utilisateur'] ?>" <?= (string)$filtres['utilisateur'] === (string)$u['id_utilisateur'] ? 'selected' : '' ?>>
                            <?= e($u['prenom'] . ' ' . $u['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="date_debut">Du</label>
                <input type="date" id="date_debut" name="date_debut" value="<?= e($filtres['date_debut']) ?>">
                <span class="erreur-champ" id="err-date_debut"></span>
            </div>
            <div class="form-group">
                <label for="date_fin">Au</label>
                <input type="date" id="date_fin" name="date_fin" value="<?= e($filtres['date_fin']) ?>">
                <span class="erreur-champ" id="err-date_fin"></span>
            </div>
            <div class="filtres-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-magnifying-glass"></i> Filtrer</button>
                <a href="listReservation.php" class="btn btn-light"><i class="fas fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Résultats</h3>
        <span class="text-muted" style="font-size:.86rem;"><?= count($reservations) ?> réservation(s)</span>
    </div>

    <?php if (!$reservations): ?>
        <div class="vide">
            <i class="fas fa-calendar-xmark"></i>
            <h3>Aucune réservation</h3>
            <p>Ajustez vos filtres ou créez une réservation manuelle.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Objet</th><th>Demandeur</th><th>Salle</th><th>Créneau</th>
                        <th>Durée</th><th>Pers.</th><th>Statut</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($reservations as $r): ?>
                    <?php
                    $conflits = [];
                    if (in_array($r['statut'], ['en_attente', 'validee'], true)) {
                        $conflits = $reservationC->detecterConflits(
                            (int)$r['id_salle'], $r['date_debut'], $r['date_fin'], (int)$r['id_reservation']);
                    }
                    $duree = (strtotime($r['date_fin']) - strtotime($r['date_debut'])) / 60;
                    ?>
                    <tr>
                        <td>
                            <div class="cell-titre"><?= e($r['titre']) ?></div>
                            <?php if ($conflits): ?>
                                <div class="cell-sub" style="color:var(--rouge);">
                                    <i class="fas fa-triangle-exclamation"></i> <?= count($conflits) ?> chevauchement(s)
                                </div>
                            <?php elseif (!empty($r['motif_refus'])): ?>
                                <div class="cell-sub" title="<?= e($r['motif_refus']) ?>">
                                    Motif : <?= e(mb_substr($r['motif_refus'], 0, 40)) ?><?= mb_strlen($r['motif_refus']) > 40 ? '…' : '' ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e($r['prenom_utilisateur'] . ' ' . $r['nom_utilisateur']) ?>
                            <div class="cell-sub"><?= e((string)$r['departement']) ?></div>
                        </td>
                        <td>
                            <?= e($r['nom_salle']) ?>
                            <div class="cell-sub"><?= e($r['nom_batiment']) ?> · <?= e($r['nom_etage']) ?></div>
                        </td>
                        <td>
                            <div class="cell-titre"><?= e(fmt_date($r['date_debut'])) ?></div>
                            <div class="cell-sub mono"><?= e(fmt_heure($r['date_debut'])) ?> – <?= e(fmt_heure($r['date_fin'])) ?></div>
                        </td>
                        <td><?= e(fmt_minutes((int)$duree)) ?></td>
                        <td><?= (int)$r['nb_participants'] ?>/<?= (int)$r['capacite'] ?></td>
                        <td>
                            <span class="badge <?= classe_statut($r['statut']) ?>"><?= e(libelle_statut($r['statut'])) ?></span>
                            <?php if (!empty($r['prenom_validateur'])): ?>
                                <div class="cell-sub">par <?= e($r['prenom_validateur']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="td-actions">
                                <?php if ($r['statut'] === 'en_attente'): ?>
                                    <a href="traiterReservation.php?id=<?= (int)$r['id_reservation'] ?>&action=valider"
                                       class="btn btn-success btn-sm" title="Valider"
                                       onclick="return confirmerAction('<?= $conflits ? 'Attention : cette réservation entre en conflit avec une autre. Valider quand même ?' : 'Valider cette réservation ?' ?>');">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <button type="button" class="btn btn-danger btn-sm" title="Refuser"
                                            data-modale="modaleRefus"
                                            data-id="<?= (int)$r['id_reservation'] ?>"
                                            data-titre="<?= e($r['titre']) ?>">
                                        <i class="fas fa-xmark"></i>
                                    </button>
                                <?php elseif ($r['statut'] === 'validee'): ?>
                                    <button type="button" class="btn btn-warning btn-sm" title="Annuler"
                                            data-modale="modaleRefus"
                                            data-id="<?= (int)$r['id_reservation'] ?>"
                                            data-titre="<?= e($r['titre']) ?>">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                <?php endif; ?>
                                <a href="updateReservation.php?id=<?= (int)$r['id_reservation'] ?>"
                                   class="btn btn-light btn-sm" title="Modifier / déplacer"><i class="fas fa-pen"></i></a>
                                <a href="deleteReservation.php?id=<?= (int)$r['id_reservation'] ?>"
                                   class="btn btn-danger btn-sm" title="Supprimer"
                                   onclick="return confirmerSuppression('Supprimer définitivement « <?= e(addslashes($r['titre'])) ?> » ?');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modale de refus / annulation -->
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
                    <label for="motif_refus">Motif <span class="req">*</span></label>
                    <textarea id="motif_refus" name="motif_refus"
                              placeholder="Salle mobilisée pour un séminaire à cette date…"></textarea>
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
        { test: v => Valider.requis(v),            message: 'Le motif est obligatoire.' },
        { test: v => Valider.longueur(v, 10, 500), message: 'Entre 10 et 500 caractères.' }
    ]
});

Valider.attacher('formFiltres', {
    date_fin: [
        { test: (v, f) => {
            const d = f.querySelector('#date_debut').value;
            return v === '' || d === '' || v >= d;
          },
          message: 'La date de fin doit être postérieure à la date de début.' }
    ]
});
</script>
