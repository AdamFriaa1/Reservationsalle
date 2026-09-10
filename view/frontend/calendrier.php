<?php
require_once dirname(__DIR__, 2) . '/init.php';

$salleC       = new SalleC();
$batimentC    = new BatimentC();
$reservationC = new ReservationC();

// ---- Paramètres du calendrier ----
$mois  = (int)($_GET['mois']  ?? date('n'));
$annee = (int)($_GET['annee'] ?? date('Y'));
if ($mois < 1)  { $mois = 12; $annee--; }
if ($mois > 12) { $mois = 1;  $annee++; }
if ($annee < 2020 || $annee > 2100) { $annee = (int)date('Y'); }

$idSalle    = isset($_GET['salle'])    && ctype_digit((string)$_GET['salle'])    ? (int)$_GET['salle']    : null;
$idBatiment = isset($_GET['batiment']) && ctype_digit((string)$_GET['batiment']) ? (int)$_GET['batiment'] : null;
$jour       = (isset($_GET['jour']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['jour'])) ? $_GET['jour'] : null;

$erreur = '';
try {
    $salles       = $salleC->getSallesDisponibles();
    $batiments    = $batimentC->showBatiments();
    $reservations = $reservationC->getCalendrierMois($annee, $mois, $idSalle, $idBatiment);
    $salleActive  = $idSalle !== null ? $salleC->getSalle($idSalle) : null;
} catch (Throwable $e) {
    $erreur = 'Erreur lors du chargement du calendrier : ' . $e->getMessage();
    $salles = []; $batiments = []; $reservations = []; $salleActive = null;
}

/* ---------------------------------------------------------------------
   Occupation d'une journée.

   La même mesure sert qu'une salle soit sélectionnée ou non : minutes
   réservées ÷ minutes d'ouverture disponibles. Sans salle choisie, le
   dénominateur additionne les heures d'ouverture de toutes les salles
   affichées — le voyant garde donc exactement le même sens dans les deux cas.

   L'étiquette porte toujours un nombre concret (« 2 réunions ») plutôt qu'un
   simple mot : même un jour peu chargé reste lisible d'un coup d'œil.
   --------------------------------------------------------------------- */
function minutesOuverture(array $salle): float
{
    $ouverture = strtotime('1970-01-01 ' . $salle['heure_ouverture']);
    $fermeture = strtotime('1970-01-01 ' . $salle['heure_fermeture']);
    return max(1, ($fermeture - $ouverture) / 60);
}

/** @return array{0:string,1:string,2:int,3:string} classe, étiquette, segments, détail */
function occupationJour(array $duJour, float $capaciteMinutes): array
{
    $minutes = 0;
    $nb      = 0;
    foreach ($duJour as $r) {
        if (!in_array($r['statut'], ['en_attente', 'validee', 'terminee'], true)) continue;
        $nb++;
        $minutes += max(0, (strtotime($r['date_fin']) - strtotime($r['date_debut'])) / 60);
    }

    if ($nb === 0) {
        return ['libre', 'Libre', 1, 'Aucune réservation'];
    }

    // Des réservations qui se chevauchent comptent leurs minutes deux fois :
    // on plafonne à 100 % pour ne pas afficher un taux absurde.
    $ratio = $capaciteMinutes > 0 ? min(1, $minutes / $capaciteMinutes) : 0;

    // Seuils réellement atteignables : une salle occupée à 35 % de sa journée
    // se remplit, à 85 % il ne reste que des miettes.
    if      ($ratio >= 0.85) { $cls = 'full'; $seg = 3; }
    elseif  ($ratio >= 0.35) { $cls = 'busy'; $seg = 2; }
    else                     { $cls = 'libre'; $seg = 1; }

    $mot = $cls === 'full' ? 'Complet' : $nb . ' réunion' . ($nb > 1 ? 's' : '');

    $detail = $nb . ' réunion' . ($nb > 1 ? 's' : '')
            . ' · ' . fmt_minutes((int)round($minutes)) . ' occupées sur '
            . fmt_minutes((int)round($capaciteMinutes))
            . ' (' . round($ratio * 100) . ' %)';

    return [$cls, $mot, $seg, $detail];
}

/* Capacité de référence : la salle choisie, sinon toutes les salles listées. */
$capaciteMinutes = 0;
if ($salleActive !== null) {
    $capaciteMinutes = minutesOuverture($salleActive);
} else {
    foreach ($salles as $s) {
        if ($idBatiment !== null && (int)$s['id_batiment'] !== $idBatiment) continue;
        $capaciteMinutes += minutesOuverture($s);
    }
    $capaciteMinutes = max(1, $capaciteMinutes);
}

// ---- Construction de la grille ----
$premierJour = mktime(0, 0, 0, $mois, 1, $annee);
$nbJours     = (int)date('t', $premierJour);
$decalage    = ((int)date('N', $premierJour)) - 1;   // lundi = 0
$aujourdhui  = date('Y-m-d');

$moisPrec = ['mois' => $mois === 1  ? 12 : $mois - 1, 'annee' => $mois === 1  ? $annee - 1 : $annee];
$moisSuiv = ['mois' => $mois === 12 ? 1  : $mois + 1, 'annee' => $mois === 12 ? $annee + 1 : $annee];

/** Conserve les filtres actifs dans les liens de navigation. */
function lienCal(array $params, ?int $idSalle, ?int $idBatiment): string
{
    if ($idSalle !== null)    { $params['salle']    = $idSalle; }
    if ($idBatiment !== null) { $params['batiment'] = $idBatiment; }
    return 'calendrier.php?' . http_build_query($params);
}

$titrePage  = 'Disponibilités';
$pageActive = 'calendrier';
require __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <span class="eyebrow">Planning</span>
        <h1 class="mt-2">Calendrier des disponibilités</h1>
        <p>
            <?= $salleActive !== null
                ? e($salleActive['nom'] . ' — cliquez sur une journée pour voir ses créneaux.')
                : 'Toutes salles confondues. Choisissez une salle pour son planning détaillé.' ?>
        </p>
    </div>
    <div class="row g-2 wrapf">
        <a href="salles.php" class="btn"><i class="fas fa-door-open"></i> Catalogue</a>
        <?php if (est_connecte() && $salleActive !== null): ?>
            <a href="reserver.php?salle=<?= (int)$salleActive['id_salle'] ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Réserver cette salle
            </a>
        <?php endif; ?>
    </div>
</div>

<?php flash_afficher(); ?>
<?php if ($erreur !== ''): ?>
    <div class="alert alert-bad"><i class="fas fa-circle-exclamation"></i><span><?= e($erreur) ?></span></div>
<?php endif; ?>

<!-- ============================================== BARRE D'OUTILS -->
<form method="get" class="cal-tools" id="formCal">
    <input type="hidden" name="mois"  value="<?= $mois ?>">
    <input type="hidden" name="annee" value="<?= $annee ?>">

    <div class="cal-month">
        <a class="btn btn-ghost btn-icon" href="<?= e(lienCal($moisPrec, $idSalle, $idBatiment)) ?>"
           aria-label="Mois précédent"><i class="fas fa-chevron-left"></i></a>
        <span class="lbl"><?= e(mb_strtolower(MOIS_FR[$mois]) . ' ' . $annee) ?></span>
        <a class="btn btn-ghost btn-icon" href="<?= e(lienCal($moisSuiv, $idSalle, $idBatiment)) ?>"
           aria-label="Mois suivant"><i class="fas fa-chevron-right"></i></a>
    </div>

    <a class="btn btn-sm" href="<?= e(lienCal(['mois' => (int)date('n'), 'annee' => (int)date('Y')], $idSalle, $idBatiment)) ?>">
        <i class="fas fa-calendar-day"></i> Aujourd'hui
    </a>

    <label class="row g-2 t-sm push" style="font-weight:600;color:var(--ink-600)">
        Bâtiment
        <select name="batiment" onchange="this.form.submit()" style="width:auto;min-width:158px">
            <option value="">Tous</option>
            <?php foreach ($batiments as $b): ?>
                <option value="<?= (int)$b['id_batiment'] ?>" <?= $idBatiment === (int)$b['id_batiment'] ? 'selected' : '' ?>>
                    <?= e($b['nom']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label class="row g-2 t-sm" style="font-weight:600;color:var(--ink-600)">
        Salle
        <select name="salle" id="selSalle" onchange="this.form.submit()" style="width:auto;min-width:210px">
            <option value="">— Choisir une salle —</option>
            <?php foreach ($salles as $s): ?>
                <?php if ($idBatiment !== null && (int)$s['id_batiment'] !== $idBatiment) continue; ?>
                <option value="<?= (int)$s['id_salle'] ?>" <?= $idSalle === (int)$s['id_salle'] ? 'selected' : '' ?>>
                    <?= e($s['nom']) ?> — <?= e($s['code_salle']) ?> (<?= (int)$s['capacite'] ?> pl.)
                </option>
            <?php endforeach; ?>
        </select>
    </label>
</form>

<?php if ($salleActive === null): ?>
    <div class="alert alert-info">
        <i class="fas fa-circle-info"></i>
        <span>Sélectionnez une salle ci-dessus : le calendrier affichera alors son taux
              d'occupation jour par jour, et vous pourrez choisir un créneau.</span>
    </div>
<?php endif; ?>

<!-- ============================================== CALENDRIER + CRÉNEAUX -->
<div class="cal-split">

    <div>
        <div class="cal">
            <div class="cal-dow" aria-hidden="true">
                <div>Lun</div><div>Mar</div><div>Mer</div><div>Jeu</div><div>Ven</div><div>Sam</div><div>Dim</div>
            </div>
            <div class="cal-days" id="calGrille">
                <?php for ($i = 0; $i < $decalage; $i++): ?>
                    <div class="day void" aria-hidden="true"></div>
                <?php endfor; ?>

                <?php for ($j = 1; $j <= $nbJours; $j++):
                    $dateJour = sprintf('%04d-%02d-%02d', $annee, $mois, $j);
                    $duJour   = $reservations[$dateJour] ?? [];
                    $passe    = $dateJour < $aujourdhui;
                    [$cls, $mot, $segments, $detail] = occupationJour($duJour, $capaciteMinutes);

                    $classes = 'day';
                    if ($passe)                     $classes .= ' past';
                    if ($dateJour === $aujourdhui)  $classes .= ' today';
                    if ($jour === $dateJour)        $classes .= ' pick';
                ?>
                    <button type="button" class="<?= $classes ?>" data-date="<?= e($dateJour) ?>"
                            <?= $passe ? 'disabled' : '' ?>
                            title="<?= e(fmt_date($dateJour) . ' — ' . $detail) ?>"
                            aria-label="<?= e(fmt_date($dateJour) . ($passe ? ' (passé)' : ' — ' . $detail)) ?>">
                        <span class="n"><?= $j ?></span>
                        <?php if (!$passe): ?>
                            <span class="lvl"><?= e($mot) ?></span>
                            <span class="gauge" aria-hidden="true">
                                <?php for ($k = 0; $k < 3; $k++): ?>
                                    <i class="<?= $k < $segments ? e($cls) : '' ?>"></i>
                                <?php endfor; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                <?php endfor; ?>
            </div>
        </div>

        <div class="legend">
            <span><i style="background:var(--ok-500)"></i> Moins d'un tiers occupé</span>
            <span><i style="background:var(--warn-500)"></i> Se remplit</span>
            <span><i style="background:var(--bad-500)"></i> Complet</span>
            <span><i style="background:var(--line)"></i> Journée passée</span>
            <span class="dim">Survolez une journée pour le détail.</span>
        </div>
    </div>

    <div class="card slots-panel">
        <div class="slots-head">
            <h3 id="calTitre">Aucune journée choisie</h3>
            <div class="sub" id="calSousTitre">
                <?= $salleActive !== null
                    ? e($salleActive['nom'] . ' · ' . (int)$salleActive['capacite'] . ' places · '
                        . substr($salleActive['heure_ouverture'], 0, 5) . '–' . substr($salleActive['heure_fermeture'], 0, 5))
                    : 'Aucune salle sélectionnée' ?>
            </div>
        </div>
        <div class="slots-body" id="calSlots">
            <div class="empty" style="padding:38px 12px">
                <span class="empty-icon"><i class="fas fa-calendar-day"></i></span>
                <p class="muted t-sm">
                    <?= $salleActive !== null
                        ? 'Cliquez sur une journée du calendrier.'
                        : 'Choisissez une salle puis une journée.' ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>

<script src="../../assets/js/calendrier.js?v=10"></script>
<script>
Calendrier.init({
    salleId:        <?= $idSalle !== null ? (int)$idSalle : 'null' ?>,
    jour:           <?= $jour !== null ? json_encode($jour) : 'null' ?>,
    urlAjax:        'ajax/disponibilites.php',
    grille:         'calGrille',
    zone:           'calSlots',
    titre:          'calTitre',
    sousTitre:      'calSousTitre',
    selecteurSalle: 'selSalle',
    urlReserver:    'reserver.php',
    connecte:       <?= est_connecte() ? 'true' : 'false' ?>
});
</script>
