<?php
/**
 * Détection des conflits : liste toutes les paires de réservations
 * qui se chevauchent dans une même salle (statuts en attente ou validée).
 */
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin', 'gestionnaire']);

$reservationC = new ReservationC();
$salleC       = new SalleC();

$salleFiltre = $_GET['salle'] ?? 'toutes';

$erreur = '';
try {
    // On ne regarde que les réservations à venir, en attente ou validées
    $candidates = $reservationC->filterReservations([
        'salle'    => $salleFiltre,
        'date_min' => date('Y-m-d'),
        'orderBy'  => 'date',
        'orderDir' => 'ASC',
    ]);
    $salles = $salleC->showSalles();
} catch (Throwable $e) {
    $erreur = 'Erreur lors du chargement : ' . $e->getMessage();
    $candidates = []; $salles = [];
}

// ---- Recherche des chevauchements deux à deux ----
$candidates = array_values(array_filter(
    $candidates,
    fn($r) => in_array($r['statut'], ['en_attente', 'validee'], true)
));

$paires = [];
$nb     = count($candidates);
for ($i = 0; $i < $nb; $i++) {
    for ($j = $i + 1; $j < $nb; $j++) {
        $a = $candidates[$i];
        $b = $candidates[$j];

        if ((int)$a['id_salle'] !== (int)$b['id_salle']) continue;

        // Règle de chevauchement : debutA < finB ET finA > debutB
        if (strtotime($a['date_debut']) < strtotime($b['date_fin'])
            && strtotime($a['date_fin']) > strtotime($b['date_debut'])) {

            $debutChev = max(strtotime($a['date_debut']), strtotime($b['date_debut']));
            $finChev   = min(strtotime($a['date_fin']),   strtotime($b['date_fin']));

            $paires[] = [
                'a'         => $a,
                'b'         => $b,
                'minutes'   => (int)(($finChev - $debutChev) / 60),
                'debut'     => date('H:i', $debutChev),
                'fin'       => date('H:i', $finChev),
                'critique'  => $a['statut'] === 'validee' && $b['statut'] === 'validee',
            ];
        }
    }
}

// Les conflits entre deux réservations validées passent en tête
usort($paires, function ($x, $y) {
    if ($x['critique'] !== $y['critique']) return $x['critique'] ? -1 : 1;
    return strtotime($x['a']['date_debut']) <=> strtotime($y['a']['date_debut']);
});

$nbCritiques = count(array_filter($paires, fn($p) => $p['critique']));

$titrePage  = 'Conflits';
$pageActive = 'conflits';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <div class="fil-ariane"><a href="index.php">Tableau de bord</a> / Conflits</div>
        <h2><i class="fas fa-triangle-exclamation"></i> Conflits de réservation</h2>
        <p>Chevauchements détectés entre réservations à venir dans une même salle.</p>
    </div>
    <a href="calendrier.php" class="btn btn-light"><i class="fas fa-calendar"></i> Planning global</a>
</div>

<?php flash_afficher(); ?>
<?php if ($erreur !== ''): ?>
    <div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i><span><?= e($erreur) ?></span></div>
<?php endif; ?>

<div class="grid grid-4 mb-3">
    <div class="stat-card">
        <div class="stat-icon rouge"><i class="fas fa-triangle-exclamation"></i></div>
        <div><div class="stat-value"><?= count($paires) ?></div><div class="stat-label">Conflits détectés</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-circle-exclamation"></i></div>
        <div><div class="stat-value"><?= (int)$nbCritiques ?></div><div class="stat-label">Entre deux validées</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bleu"><i class="fas fa-calendar-check"></i></div>
        <div><div class="stat-value"><?= count($candidates) ?></div><div class="stat-label">Réservations à venir</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon vert"><i class="fas fa-door-open"></i></div>
        <div><div class="stat-value"><?= count($salles) ?></div><div class="stat-label">Salles suivies</div></div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="get" class="filtres-grid">
            <div class="form-group">
                <label for="salle">Salle</label>
                <select id="salle" name="salle">
                    <option value="toutes">Toutes les salles</option>
                    <?php foreach ($salles as $s): ?>
                        <option value="<?= (int)$s['id_salle'] ?>" <?= (string)$salleFiltre === (string)$s['id_salle'] ? 'selected' : '' ?>>
                            <?= e($s['nom']) ?> — <?= e($s['nom_batiment']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtres-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrer</button>
                <a href="conflits.php" class="btn btn-light"><i class="fas fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<?php if (!$paires): ?>
    <div class="card">
        <div class="vide">
            <i class="fas fa-circle-check" style="color:var(--vert);"></i>
            <h3>Aucun conflit détecté</h3>
            <p>Aucune réservation à venir ne se chevauche dans une même salle.</p>
        </div>
    </div>
<?php else: ?>

    <?php foreach ($paires as $p): ?>
        <div class="card conflit-carte <?= $p['critique'] ? 'critique' : '' ?>">
            <div class="card-header">
                <h3>
                    <i class="fas fa-triangle-exclamation"></i>
                    <?= e($p['a']['nom_salle']) ?> — <?= e($p['a']['nom_batiment']) ?>
                </h3>
                <span class="badge <?= $p['critique'] ? 'badge-danger' : 'badge-warning' ?>">
                    <?= $p['critique'] ? 'Conflit bloquant' : 'À arbitrer' ?>
                    · <?= e(fmt_minutes((int)$p['minutes'])) ?> de chevauchement
                </span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3" style="font-size:.9rem;">
                    <i class="fas fa-clock"></i>
                    Chevauchement le <?= e(fmt_date($p['a']['date_debut'])) ?>
                    de <?= e($p['debut']) ?> à <?= e($p['fin']) ?>.
                </p>

                <div class="grid grid-2">
                    <?php foreach (['a', 'b'] as $cle): ?>
                        <?php $r = $p[$cle]; ?>
                        <div class="conflit-bloc">
                            <div class="conflit-entete">
                                <strong><?= e($r['titre']) ?></strong>
                                <span class="badge <?= classe_statut($r['statut']) ?>"><?= e(libelle_statut($r['statut'])) ?></span>
                            </div>
                            <ul class="liste-info">
                                <li><i class="fas fa-user"></i> <?= e($r['prenom_utilisateur'] . ' ' . $r['nom_utilisateur']) ?></li>
                                <li><i class="fas fa-clock"></i> <?= e(fmt_heure($r['date_debut'])) ?> – <?= e(fmt_heure($r['date_fin'])) ?></li>
                                <li><i class="fas fa-users"></i> <?= (int)$r['nb_participants'] ?> participant(s)</li>
                                <li><i class="fas fa-calendar-plus"></i> Demandée le <?= e(fmt_date($r['date_creation'])) ?></li>
                            </ul>
                            <div class="d-flex gap-2 mt-3">
                                <a href="updateReservation.php?id=<?= (int)$r['id_reservation'] ?>"
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-arrows-up-down-left-right"></i> Déplacer
                                </a>
                                <?php if ($r['statut'] === 'en_attente'): ?>
                                    <button type="button" class="btn btn-danger btn-sm"
                                            data-modale="modaleRefus"
                                            data-id="<?= (int)$r['id_reservation'] ?>"
                                            data-titre="<?= e($r['titre']) ?>">
                                        <i class="fas fa-xmark"></i> Refuser
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

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
                    <label for="motif_refus">Motif <span class="req">*</span></label>
                    <textarea id="motif_refus" name="motif_refus"
                              placeholder="Créneau déjà attribué à une autre réunion…"></textarea>
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
</script>
