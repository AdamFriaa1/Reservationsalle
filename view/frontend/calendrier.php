<?php
require_once dirname(__DIR__, 2) . '/init.php';

$salleC       = new SalleC();
$batimentC    = new BatimentC();
$reservationC = new ReservationC();

// ---- Paramètres du calendrier ----
$mois   = (int)($_GET['mois']  ?? date('n'));
$annee  = (int)($_GET['annee'] ?? date('Y'));
if ($mois < 1)  { $mois = 12; $annee--; }
if ($mois > 12) { $mois = 1;  $annee++; }
if ($annee < 2020 || $annee > 2100) { $annee = (int)date('Y'); }

$idSalle    = isset($_GET['salle']) && ctype_digit((string)$_GET['salle']) ? (int)$_GET['salle'] : null;
$idBatiment = isset($_GET['batiment']) && ctype_digit((string)$_GET['batiment']) ? (int)$_GET['batiment'] : null;
$jour       = $_GET['jour'] ?? null;

$erreur = '';
try {
    $salles       = $salleC->getSallesDisponibles();
    $batiments    = $batimentC->showBatiments();
    $reservations = $reservationC->getCalendrierMois($annee, $mois, $idSalle, $idBatiment);
    $salleActive  = $idSalle !== null ? $salleC->getSalle($idSalle) : null;
    $creneaux     = ($salleActive !== null && $jour !== null)
        ? $reservationC->getCreneauxJour($salleActive, $jour) : [];
} catch (Throwable $e) {
    $erreur = 'Erreur lors du chargement du calendrier : ' . $e->getMessage();
    $salles = []; $batiments = []; $reservations = []; $salleActive = null; $creneaux = [];
}

// ---- Construction de la grille ----
$premierJour   = mktime(0, 0, 0, $mois, 1, $annee);
$nbJours       = (int)date('t', $premierJour);
$decalage      = ((int)date('N', $premierJour)) - 1;   // lundi = 0
$aujourdhui    = date('Y-m-d');

$moisPrecedent = ['mois' => $mois === 1 ? 12 : $mois - 1, 'annee' => $mois === 1 ? $annee - 1 : $annee];
$moisSuivant   = ['mois' => $mois === 12 ? 1 : $mois + 1, 'annee' => $mois === 12 ? $annee + 1 : $annee];

/** Conserve les filtres actifs dans les liens de navigation. */
function lienCal(array $params, ?int $idSalle, ?int $idBatiment): string
{
    if ($idSalle !== null)    { $params['salle']    = $idSalle; }
    if ($idBatiment !== null) { $params['batiment'] = $idBatiment; }
    return 'calendrier.php?' . http_build_query($params);
}

$titrePage  = 'Calendrier';
$pageActive = 'calendrier';
require __DIR__ . '/partials/header.php';
?>

<div class="page container">

    <div class="page-header">
        <h1><i class="fas fa-calendar-days"></i> Calendrier des disponibilités</h1>
        <p>Cliquez sur une journée pour voir les créneaux libres de la salle sélectionnée.</p>
    </div>

    <?php flash_afficher(); ?>
    <?php if ($erreur !== ''): ?>
        <div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i><span><?= e($erreur) ?></span></div>
    <?php endif; ?>

    <form method="get" class="filtres">
        <input type="hidden" name="mois" value="<?= $mois ?>">
        <input type="hidden" name="annee" value="<?= $annee ?>">
        <div class="filtres-grid">
            <div class="form-group">
                <label for="batiment">Bâtiment</label>
                <select id="batiment" name="batiment" onchange="this.form.submit()">
                    <option value="">Tous les bâtiments</option>
                    <?php foreach ($batiments as $b): ?>
                        <option value="<?= (int)$b['id_batiment'] ?>" <?= $idBatiment === (int)$b['id_batiment'] ? 'selected' : '' ?>>
                            <?= e($b['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="salle">Salle</label>
                <select id="salle" name="salle" onchange="this.form.submit()">
                    <option value="">Toutes les salles</option>
                    <?php foreach ($salles as $s): ?>
                        <?php if ($idBatiment !== null && (int)$s['id_batiment'] !== $idBatiment) continue; ?>
                        <option value="<?= (int)$s['id_salle'] ?>" <?= $idSalle === (int)$s['id_salle'] ? 'selected' : '' ?>>
                            <?= e($s['nom']) ?> — <?= e($s['code_salle']) ?> (<?= (int)$s['capacite'] ?> pl.)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <a href="calendrier.php" class="btn btn-light"><i class="fas fa-rotate-left"></i> Réinitialiser</a>
            </div>
        </div>
    </form>

    <div class="cal-nav">
        <div class="d-flex gap-2 align-center">
            <a href="<?= e(lienCal($moisPrecedent, $idSalle, $idBatiment)) ?>" class="btn btn-light btn-icon">
                <i class="fas fa-chevron-left"></i>
            </a>
            <span class="cal-titre"><?= e(MOIS_FR[$mois] . ' ' . $annee) ?></span>
            <a href="<?= e(lienCal($moisSuivant, $idSalle, $idBatiment)) ?>" class="btn btn-light btn-icon">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
        <a href="<?= e(lienCal(['mois' => (int)date('n'), 'annee' => (int)date('Y')], $idSalle, $idBatiment)) ?>"
           class="btn btn-light btn-sm"><i class="fas fa-calendar-day"></i> Ce mois-ci</a>
    </div>

    <div class="calendrier">
        <div class="cal-entete">
            <div>Lun</div><div>Mar</div><div>Mer</div><div>Jeu</div>
            <div>Ven</div><div>Sam</div><div>Dim</div>
        </div>
        <div class="cal-grille">
            <?php for ($i = 0; $i < $decalage; $i++): ?>
                <div class="cal-jour vide"></div>
            <?php endfor; ?>

            <?php for ($j = 1; $j <= $nbJours; $j++):
                $dateJour  = sprintf('%04d-%02d-%02d', $annee, $mois, $j);
                $duJour    = $reservations[$dateJour] ?? [];
                $classes   = 'cal-jour';
                if ($dateJour === $aujourdhui)  $classes .= ' aujourdhui';
                if ($dateJour < $aujourdhui)    $classes .= ' passe';
                if ($jour === $dateJour)        $classes .= ' selection';
            ?>
                <div class="<?= $classes ?>" data-date="<?= e($dateJour) ?>">
                    <div class="cal-num"><?= $j ?></div>
                    <?php foreach (array_slice($duJour, 0, 2) as $r): ?>
                        <div class="cal-event <?= e($r['statut']) ?>"
                             title="<?= e($r['titre'] . ' — ' . $r['nom_salle'] . ' (' . fmt_heure($r['date_debut']) . '–' . fmt_heure($r['date_fin']) . ')') ?>">
                            <?= e(fmt_heure($r['date_debut'])) ?> <?= e($r['nom_salle']) ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($duJour) > 2): ?>
                        <div class="cal-plus">+<?= count($duJour) - 2 ?> autre<?= count($duJour) - 2 > 1 ? 's' : '' ?></div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <div class="legende">
        <span><i style="background:#16a34a"></i> Réservation validée</span>
        <span><i style="background:#f59e0b"></i> En attente de validation</span>
        <span><i style="background:#cbd5e1"></i> Terminée</span>
    </div>

    <div class="card mt-3" style="margin-top:28px;">
        <div class="card-header">
            <h2><i class="fas fa-clock"></i> Créneaux de la journée</h2>
            <?php if ($salleActive !== null): ?>
                <span class="badge badge-info">
                    <?= e($salleActive['nom']) ?> · <?= (int)$salleActive['capacite'] ?> places ·
                    <?= e(substr($salleActive['heure_ouverture'], 0, 5)) ?>–<?= e(substr($salleActive['heure_fermeture'], 0, 5)) ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div id="zoneCreneaux">
                <?php if ($salleActive === null): ?>
                    <p class="text-muted">
                        <i class="fas fa-circle-info"></i>
                        Choisissez d'abord une salle dans les filtres ci-dessus, puis cliquez sur une journée.
                    </p>
                <?php elseif (!$creneaux): ?>
                    <p class="text-muted">
                        <i class="fas fa-circle-info"></i> Cliquez sur une journée du calendrier
                        pour afficher les créneaux de <strong><?= e($salleActive['nom']) ?></strong>.
                    </p>
                <?php else: ?>
                    <div class="creneaux">
                        <?php foreach ($creneaux as $c): ?>
                            <div class="creneau <?= $c['passe'] ? 'passe' : ($c['occupe'] ? 'occupe' : 'libre') ?>">
                                <?= e($c['debut']) ?>
                                <small><?= $c['passe'] ? 'Passé' : ($c['occupe'] ? 'Occupé' : 'Libre') ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($salleActive !== null && est_connecte()): ?>
                <div class="mt-3">
                    <a href="reserver.php?salle=<?= (int)$salleActive['id_salle'] ?><?= $jour ? '&jour=' . e($jour) : '' ?>"
                       class="btn btn-primary">
                        <i class="fas fa-plus"></i> Réserver cette salle
                    </a>
                </div>
            <?php elseif ($salleActive !== null): ?>
                <div class="alert alert-info mt-3" style="margin-top:20px;">
                    <i class="fas fa-circle-info"></i>
                    <span><a href="login.php">Connectez-vous</a> pour réserver cette salle.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>

<script src="assets/js/calendrier.js"></script>
<script>
Calendrier.init({
    salleId: <?= $idSalle !== null ? (int)$idSalle : 'null' ?>,
    urlAjax: 'ajax/disponibilites.php',
    zoneCreneaux: 'zoneCreneaux',
    selecteurSalle: 'salle'
});
</script>
