<?php
/**
 * Statistiques d'utilisation des salles sur une période choisie :
 * taux d'occupation, volume par jour, répartition par bâtiment, classement.
 */
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin']);

$salleC       = new SalleC();
$reservationC = new ReservationC();

// ---- Période analysée : fenêtre passé + à venir par défaut,
//      pour une répartition par statut complète (tous les statuts visibles) ----
$dateDebut = trim($_GET['date_debut'] ?? date('Y-m-d', strtotime('-25 days')));
$dateFin   = trim($_GET['date_fin']   ?? date('Y-m-d', strtotime('+25 days')));

$erreurFiltre = '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut)) {
    $erreurFiltre = 'La date de début est invalide.';
    $dateDebut = date('Y-m-d', strtotime('-25 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin)) {
    $erreurFiltre = 'La date de fin est invalide.';
    $dateFin = date('Y-m-d', strtotime('+25 days'));
}
if ($dateFin < $dateDebut) {
    $erreurFiltre = 'La date de fin doit être postérieure à la date de début.';
    [$dateDebut, $dateFin] = [$dateFin, $dateDebut];
}

$nbJours = max(1, (int)ceil((strtotime($dateFin) - strtotime($dateDebut)) / 86400) + 1);

$erreur = '';
try {
    $stats        = $salleC->statistiquesUtilisation($dateDebut . ' 00:00:00', $dateFin . ' 23:59:59');
    $parJour      = $reservationC->reservationsParJour($dateDebut, $dateFin);
    $parBatiment  = $reservationC->repartitionParBatiment();
    $topSalles    = $reservationC->topSalles(8);
    $reservations = $reservationC->rapportPeriode($dateDebut, $dateFin);
} catch (Throwable $e) {
    $erreur = 'Erreur lors du calcul : ' . $e->getMessage();
    $stats = []; $parJour = []; $parBatiment = []; $topSalles = []; $reservations = [];
}

// ---- Agrégats sur la période ----
$totalReservations = count($reservations);
$parStatut = ['en_attente' => 0, 'validee' => 0, 'refusee' => 0, 'annulee' => 0, 'terminee' => 0];
$totalParticipants = 0;
foreach ($reservations as $r) {
    if (isset($parStatut[$r['statut']])) $parStatut[$r['statut']]++;
    $totalParticipants += (int)$r['nb_participants'];
}

$heuresTotales = array_sum(array_map(fn($s) => (float)$s['heures_occupees'], $stats));
$tauxMoyen     = $stats ? round(array_sum(array_map(fn($s) => (float)$s['taux_occupation'], $stats)) / count($stats), 1) : 0;
$moyenneParticipants = $totalReservations > 0 ? round($totalParticipants / $totalReservations, 1) : 0;

// ---- Série journalière complète ----
$serie = [];
for ($t = strtotime($dateDebut); $t <= strtotime($dateFin); $t += 86400) {
    $serie[date('Y-m-d', $t)] = 0;
}
foreach ($parJour as $p) {
    if (isset($serie[$p['jour']])) $serie[$p['jour']] = (int)$p['nb'];
}
// Au-delà de 40 jours, l'histogramme devient illisible : on n'affiche que les 40 derniers
if (count($serie) > 40) $serie = array_slice($serie, -40, null, true);
$maxSerie = max(1, max($serie));

$maxBatiment = max(1, max(array_map(fn($b) => (int)$b['nb'], $parBatiment ?: [['nb' => 1]])));
$maxTop      = max(1, max(array_map(fn($s) => (int)$s['nb'], $topSalles ?: [['nb' => 1]])));

$titrePage  = 'Statistiques';
$pageActive = 'statistiques';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <div class="fil-ariane"><a href="index.php">Tableau de bord</a> / Statistiques</div>
        <h2><i class="fas fa-chart-pie"></i> Statistiques d'utilisation</h2>
        <p>Du <?= e(fmt_date($dateDebut)) ?> au <?= e(fmt_date($dateFin)) ?> — <?= (int)$nbJours ?> jours.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="rapport.php?date_debut=<?= e($dateDebut) ?>&date_fin=<?= e($dateFin) ?>" class="btn btn-light">
            <i class="fas fa-file-lines"></i> Rapport détaillé
        </a>
        <button type="button" class="btn btn-primary" onclick="window.print();">
            <i class="fas fa-print"></i> Imprimer
        </button>
    </div>
</div>

<?php if ($erreur !== ''): ?>
    <div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i><span><?= e($erreur) ?></span></div>
<?php endif; ?>
<?php if ($erreurFiltre !== ''): ?>
    <div class="alert alert-warning"><i class="fas fa-triangle-exclamation"></i><span><?= e($erreurFiltre) ?></span></div>
<?php endif; ?>

<div class="card no-print">
    <div class="card-header"><h3><i class="fas fa-calendar-week"></i> Période analysée</h3></div>
    <div class="card-body">
        <form method="get" id="formPeriode" class="filtres-grid" novalidate>
            <div class="form-group">
                <label for="date_debut">Du <span class="req">*</span></label>
                <input type="date" id="date_debut" name="date_debut" value="<?= e($dateDebut) ?>">
                <span class="erreur-champ" id="err-date_debut"></span>
            </div>
            <div class="form-group">
                <label for="date_fin">Au <span class="req">*</span></label>
                <input type="date" id="date_fin" name="date_fin" value="<?= e($dateFin) ?>">
                <span class="erreur-champ" id="err-date_fin"></span>
            </div>
            <div class="filtres-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-chart-line"></i> Analyser</button>
                <a href="statistiques.php" class="btn btn-light"><i class="fas fa-rotate-left"></i></a>
            </div>
        </form>

        <div class="chips mt-3">
            <a class="chip chip-action" href="?date_debut=<?= date('Y-m-d', strtotime('-6 days')) ?>&date_fin=<?= date('Y-m-d') ?>">7 derniers jours</a>
            <a class="chip chip-action" href="?date_debut=<?= date('Y-m-d', strtotime('-29 days')) ?>&date_fin=<?= date('Y-m-d') ?>">30 derniers jours</a>
            <a class="chip chip-action" href="?date_debut=<?= date('Y-m-01') ?>&date_fin=<?= date('Y-m-t') ?>">Ce mois</a>
            <a class="chip chip-action" href="?date_debut=<?= date('Y-01-01') ?>&date_fin=<?= date('Y-12-31') ?>">Cette année</a>
        </div>
    </div>
</div>

<div class="grid grid-4 mb-3">
    <div class="stat-card">
        <div class="stat-icon bleu"><i class="fas fa-calendar-check"></i></div>
        <div><div class="stat-value"><?= (int)$totalReservations ?></div><div class="stat-label">Réservations</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon violet"><i class="fas fa-gauge-high"></i></div>
        <div><div class="stat-value"><?= e((string)$tauxMoyen) ?> %</div><div class="stat-label">Taux d'occupation moyen</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon vert"><i class="fas fa-hourglass-half"></i></div>
        <div><div class="stat-value"><?= e((string)round($heuresTotales, 1)) ?> h</div><div class="stat-label">Heures occupées</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-users"></i></div>
        <div><div class="stat-value"><?= e((string)$moyenneParticipants) ?></div><div class="stat-label">Participants en moyenne</div></div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-chart-column"></i> Volume par jour</h3></div>
        <div class="card-body">
            <?php if (array_sum($serie) === 0): ?>
                <p class="text-muted">Aucune réservation sur cette période.</p>
            <?php else: ?>
                <?php $pasLabel = max(1, (int)ceil(count($serie) / 12)); $iCol = 0; ?>
                <div class="histo">
                    <?php foreach ($serie as $jourIso => $nb): ?>
                        <div class="histo-col" title="<?= e(date('d/m/Y', strtotime($jourIso))) ?> : <?= $nb ?> réservation(s)">
                            <div class="histo-val"><?= $nb > 0 ? (int)$nb : '' ?></div>
                            <div class="histo-barre<?= $nb === 0 ? ' vide' : '' ?>" style="height: <?= $nb > 0 ? max(6, (int)round(($nb / $maxSerie) * 128)) : 3 ?>px;"></div>
                            <div class="histo-label"><?= $iCol % $pasLabel === 0 ? e(date('d/m', strtotime($jourIso))) : '' ?></div>
                        </div>
                    <?php $iCol++; endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3><i class="fas fa-chart-pie"></i> Répartition par statut</h3></div>
        <div class="card-body">
            <?php if ($totalReservations === 0): ?>
                <p class="text-muted">Aucune donnée sur cette période.</p>
            <?php else: ?>
                <?php
                $couleursStatut = [
                    'validee'    => '#16a34a',
                    'en_attente' => '#f59e0b',
                    'refusee'    => '#dc2626',
                    'terminee'   => '#2563eb',
                    'annulee'    => '#94a3b8',
                ];
                // Construction du dégradé conique (donut) à partir des parts
                $segments = []; $accPct = 0.0;
                foreach ($couleursStatut as $st => $couleur) {
                    $nb = $parStatut[$st] ?? 0;
                    if ($nb <= 0) continue;
                    $pct = ($nb / $totalReservations) * 100;
                    $segments[] = "$couleur " . round($accPct, 2) . '% ' . round($accPct + $pct, 2) . '%';
                    $accPct += $pct;
                }
                $gradient = 'conic-gradient(' . implode(', ', $segments) . ')';
                ?>
                <div class="donut-wrap">
                    <div class="donut" style="background: <?= $gradient ?>;">
                        <div class="donut-centre">
                            <div class="donut-total"><?= (int)$totalReservations ?></div>
                            <div class="donut-sous">demandes</div>
                        </div>
                    </div>
                    <div class="donut-legende">
                        <?php foreach ($couleursStatut as $st => $couleur): $nb = $parStatut[$st] ?? 0; ?>
                            <div class="donut-item">
                                <span class="donut-puce" style="background: <?= $couleur ?>;"></span>
                                <span class="donut-nom"><?= e(libelle_statut($st)) ?></span>
                                <span class="donut-val"><?= (int)$nb ?> <span class="text-muted">· <?= (int)round(($nb / max(1, $totalReservations)) * 100) ?> %</span></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-building"></i> Réservations par bâtiment</h3></div>
        <div class="card-body">
            <?php if (!$parBatiment): ?>
                <p class="text-muted">Aucun bâtiment enregistré.</p>
            <?php else: ?>
                <div class="barres">
                    <?php foreach ($parBatiment as $b): ?>
                        <div class="barre-ligne">
                            <div class="barre-label" title="<?= e($b['nom_batiment']) ?>"><?= e($b['nom_batiment']) ?></div>
                            <div class="barre-piste">
                                <div class="barre-remplie" style="width:0"
                                     data-largeur="<?= (int)round(((int)$b['nb'] / $maxBatiment) * 100) ?>"></div>
                            </div>
                            <div class="barre-valeur"><?= (int)$b['nb'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-muted mt-3" style="font-size:.82rem;">
                    Ce classement porte sur l'ensemble de l'historique, pas seulement sur la période filtrée.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3><i class="fas fa-ranking-star"></i> Salles les plus réservées</h3></div>
        <div class="card-body">
            <?php if (!$topSalles): ?>
                <p class="text-muted">Aucune salle enregistrée.</p>
            <?php else: ?>
                <div class="barres">
                    <?php foreach ($topSalles as $s): ?>
                        <div class="barre-ligne">
                            <div class="barre-label" title="<?= e($s['nom'] . ' — ' . $s['nom_batiment']) ?>"><?= e($s['nom']) ?></div>
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
        <h3><i class="fas fa-table"></i> Taux d'occupation détaillé par salle</h3>
        <span class="text-muted" style="font-size:.86rem;"><?= count($stats) ?> salle(s)</span>
    </div>

    <?php if (!$stats): ?>
        <div class="vide"><i class="fas fa-chart-simple"></i><h3>Aucune donnée</h3></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Salle</th><th>Bâtiment</th><th>Capacité</th><th>Réservations</th>
                        <th>Heures occupées</th><th>Participants moy.</th><th>Taux d'occupation</th><th>État</th></tr>
                </thead>
                <tbody>
                <?php foreach ($stats as $s): ?>
                    <tr>
                        <td>
                            <div class="cell-titre"><?= e($s['nom']) ?></div>
                            <div class="cell-sub"><?= e($s['code_salle']) ?></div>
                        </td>
                        <td><?= e($s['nom_batiment']) ?></td>
                        <td><?= (int)$s['capacite'] ?></td>
                        <td><?= (int)$s['nb_reservations'] ?></td>
                        <td><?= e((string)$s['heures_occupees']) ?> h</td>
                        <td><?= e((string)round((float)$s['moyenne_participants'], 1)) ?></td>
                        <td>
                            <div class="taux-cellule">
                                <div class="barre-piste mini">
                                    <div class="barre-remplie" style="width:0"
                                         data-largeur="<?= min(100, (int)round((float)$s['taux_occupation'])) ?>"></div>
                                </div>
                                <span class="mono"><?= e((string)$s['taux_occupation']) ?> %</span>
                            </div>
                        </td>
                        <td>
                            <?php $cl = ['disponible' => 'badge-success', 'maintenance' => 'badge-warning', 'indisponible' => 'badge-danger']; ?>
                            <span class="badge <?= $cl[$s['etat']] ?? 'badge-muted' ?>"><?= e(libelle_etat_salle($s['etat'])) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-body">
            <p class="text-muted" style="font-size:.82rem;">
                Le taux d'occupation rapporte les minutes réservées (statuts validée et terminée)
                à l'amplitude d'ouverture de la salle sur la période, week-ends compris.
            </p>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>

<script>
Valider.attacher('formPeriode', {
    date_debut: [{ test: v => Valider.requis(v), message: 'La date de début est obligatoire.' }],
    date_fin: [
        { test: v => Valider.requis(v), message: 'La date de fin est obligatoire.' },
        { test: (v, f) => v >= f.querySelector('#date_debut').value,
          message: 'La date de fin doit être postérieure à la date de début.' }
    ]
});
</script>
