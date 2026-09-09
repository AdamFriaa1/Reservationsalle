<?php
/**
 * Planning global : calendrier mensuel de toutes les réservations,
 * filtrable par bâtiment et par salle, avec le détail d'une journée.
 */
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin', 'gestionnaire']);

$reservationC = new ReservationC();
$salleC       = new SalleC();
$batimentC    = new BatimentC();

// ---- Mois affiché ----
$mois  = isset($_GET['mois'])  && ctype_digit((string)$_GET['mois'])  ? (int)$_GET['mois']  : (int)date('n');
$annee = isset($_GET['annee']) && ctype_digit((string)$_GET['annee']) ? (int)$_GET['annee'] : (int)date('Y');
if ($mois < 1 || $mois > 12)          $mois  = (int)date('n');
if ($annee < 2020 || $annee > 2100)   $annee = (int)date('Y');

$salleFiltre    = $_GET['salle'] ?? 'toutes';
$batimentFiltre = $_GET['batiment'] ?? 'tous';
$jourDetail     = $_GET['jour'] ?? '';
if ($jourDetail !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $jourDetail)) {
    $jourDetail = '';
}

// Les filtres deviennent des entiers ou null pour le contrôleur
$idSalle    = ctype_digit((string)$salleFiltre)    ? (int)$salleFiltre    : null;
$idBatiment = ctype_digit((string)$batimentFiltre) ? (int)$batimentFiltre : null;

$erreur = '';
try {
    // getCalendrierMois renvoie déjà les réservations regroupées par jour
    $parJour   = $reservationC->getCalendrierMois($annee, $mois, $idSalle, $idBatiment);
    $salles    = $salleC->showSalles();
    $batiments = $batimentC->showBatiments();

    // Détail d'une journée : toutes salles confondues, avec les filtres courants
    $duJour = [];
    if ($jourDetail !== '') {
        $duJour = $reservationC->filterReservations([
            'salle'    => $salleFiltre,
            'batiment' => $batimentFiltre,
            'date_min' => $jourDetail,
            'date_max' => $jourDetail,
            'orderBy'  => 'date',
            'orderDir' => 'ASC',
        ]);
    }
} catch (Throwable $e) {
    $erreur = 'Erreur lors du chargement : ' . $e->getMessage();
    $parJour = []; $salles = []; $batiments = []; $duJour = [];
}

$nbEvenements = array_sum(array_map('count', $parJour));

// ---- Construction de la grille (semaine commençant lundi) ----
$premier     = mktime(0, 0, 0, $mois, 1, $annee);
$nbJours     = (int)date('t', $premier);
$jourSemaine = (int)date('N', $premier);      // 1 = lundi
$caseAvant   = $jourSemaine - 1;
$moisPrec    = $mois === 1  ? 12 : $mois - 1;
$anneePrec   = $mois === 1  ? $annee - 1 : $annee;
$moisSuiv    = $mois === 12 ? 1  : $mois + 1;
$anneeSuiv   = $mois === 12 ? $annee + 1 : $annee;
$aujourdhui  = date('Y-m-d');

$paramsBase = ['salle' => $salleFiltre, 'batiment' => $batimentFiltre];

$titrePage  = 'Planning global';
$pageActive = 'calendrier';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <div class="fil-ariane"><a href="index.php">Tableau de bord</a> / Planning</div>
        <h2><i class="fas fa-calendar-days"></i> Planning global</h2>
        <p>Vue mensuelle de l'occupation des salles.</p>
    </div>
    <a href="addReservation.php" class="btn btn-primary"><i class="fas fa-plus"></i> Réservation manuelle</a>
</div>

<?php flash_afficher(); ?>
<?php if ($erreur !== ''): ?>
    <div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i><span><?= e($erreur) ?></span></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="get" class="filtres-grid">
            <input type="hidden" name="mois"  value="<?= (int)$mois ?>">
            <input type="hidden" name="annee" value="<?= (int)$annee ?>">
            <div class="form-group">
                <label for="batiment">Bâtiment</label>
                <select id="batiment" name="batiment">
                    <option value="tous">Tous les bâtiments</option>
                    <?php foreach ($batiments as $b): ?>
                        <option value="<?= (int)$b['id_batiment'] ?>" <?= (string)$batimentFiltre === (string)$b['id_batiment'] ? 'selected' : '' ?>>
                            <?= e($b['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
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
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Appliquer</button>
                <a href="calendrier.php" class="btn btn-light"><i class="fas fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>
            <i class="fas fa-calendar"></i>
            <?= e(MOIS_FR[$mois]) ?> <?= (int)$annee ?>
            <span class="text-muted" style="font-weight:400;font-size:.85rem;">
                — <?= (int)$nbEvenements ?> réservation(s)
            </span>
        </h3>
        <div class="d-flex gap-2">
            <a href="?<?= e(http_build_query($paramsBase + ['mois' => $moisPrec, 'annee' => $anneePrec])) ?>"
               class="btn btn-light btn-sm"><i class="fas fa-chevron-left"></i></a>
            <a href="?<?= e(http_build_query($paramsBase + ['mois' => (int)date('n'), 'annee' => (int)date('Y')])) ?>"
               class="btn btn-light btn-sm">Ce mois</a>
            <a href="?<?= e(http_build_query($paramsBase + ['mois' => $moisSuiv, 'annee' => $anneeSuiv])) ?>"
               class="btn btn-light btn-sm"><i class="fas fa-chevron-right"></i></a>
        </div>
    </div>

    <div class="card-body">
        <div class="calendrier">
        <div class="cal-entete">
            <?php foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $j): ?>
                <div><?= e($j) ?></div>
            <?php endforeach; ?>
        </div>

        <div class="cal-grille">
            <?php for ($i = 0; $i < $caseAvant; $i++): ?>
                <div class="cal-jour vide"></div>
            <?php endfor; ?>

            <?php for ($jour = 1; $jour <= $nbJours; $jour++): ?>
                <?php
                $dateIso   = sprintf('%04d-%02d-%02d', $annee, $mois, $jour);
                $duJourCal = $parJour[$dateIso] ?? [];
                $classes   = 'cal-jour cal-lien';
                if ($dateIso === $aujourdhui)  $classes .= ' aujourdhui';
                if ($dateIso === $jourDetail)  $classes .= ' selectionne';
                if ($duJourCal)                $classes .= ' occupe';
                ?>
                <a class="<?= $classes ?>"
                   href="?<?= e(http_build_query($paramsBase + ['mois' => $mois, 'annee' => $annee, 'jour' => $dateIso])) ?>">
                    <div class="cal-num"><?= $jour ?></div>
                    <div class="cal-liste">
                        <?php foreach (array_slice($duJourCal, 0, 3) as $ev): ?>
                            <div class="cal-event <?= e($ev['statut']) ?>"
                                 title="<?= e($ev['titre'] . ' — ' . $ev['nom_salle'] . ' — ' . fmt_heure($ev['date_debut'])) ?>">
                                <span class="cal-h"><?= e(fmt_heure($ev['date_debut'])) ?></span>
                                <?= e(mb_substr($ev['titre'], 0, 16)) ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($duJourCal) > 3): ?>
                            <div class="cal-plus">+<?= count($duJourCal) - 3 ?> autre(s)</div>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endfor; ?>
        </div>

        </div>

        <div class="cal-legende">
            <span><i class="pastille legende-en_attente"></i> En attente</span>
            <span><i class="pastille legende-validee"></i> Validée</span>
            <span><i class="pastille legende-terminee"></i> Terminée</span>
        </div>
    </div>
</div>

<?php if ($jourDetail !== ''): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-day"></i> <?= e(fmt_date($jourDetail)) ?></h3>
            <a href="addReservation.php?date=<?= e($jourDetail) ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Réserver ce jour
            </a>
        </div>

        <?php if (!$duJour): ?>
            <div class="vide">
                <i class="fas fa-calendar-xmark"></i>
                <h3>Aucune réservation ce jour</h3>
                <p>Toutes les salles sont libres.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Créneau</th><th>Objet</th><th>Salle</th><th>Organisateur</th>
                               <th>Pers.</th><th>Statut</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($duJour as $r): ?>
                        <tr>
                            <td class="mono"><?= e(fmt_heure($r['date_debut'])) ?> – <?= e(fmt_heure($r['date_fin'])) ?></td>
                            <td class="cell-titre"><?= e($r['titre']) ?></td>
                            <td><?= e($r['nom_salle']) ?><div class="cell-sub"><?= e($r['nom_batiment']) ?></div></td>
                            <td><?= e($r['prenom_utilisateur'] . ' ' . $r['nom_utilisateur']) ?></td>
                            <td><?= (int)$r['nb_participants'] ?></td>
                            <td><span class="badge <?= classe_statut($r['statut']) ?>"><?= e(libelle_statut($r['statut'])) ?></span></td>
                            <td>
                                <div class="td-actions">
                                    <?php if ($r['statut'] === 'en_attente'): ?>
                                        <a href="traiterReservation.php?id=<?= (int)$r['id_reservation'] ?>&action=valider"
                                           class="btn btn-success btn-sm" title="Valider"
                                           onclick="return confirmerAction('Valider cette réservation ?');">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="updateReservation.php?id=<?= (int)$r['id_reservation'] ?>"
                                       class="btn btn-light btn-sm" title="Modifier"><i class="fas fa-pen"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
