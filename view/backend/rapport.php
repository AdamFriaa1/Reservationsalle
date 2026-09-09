<?php
/**
 * Rapport d'activité sur une période, filtrable par bâtiment,
 * conçu pour être imprimé ou enregistré en PDF depuis le navigateur.
 */
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin']);

$reservationC = new ReservationC();
$batimentC    = new BatimentC();
$salleC       = new SalleC();

$dateDebut = trim($_GET['date_debut'] ?? date('Y-m-01'));
$dateFin   = trim($_GET['date_fin']   ?? date('Y-m-t'));
$batiment  = $_GET['batiment'] ?? 'tous';

$erreurFiltre = '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut)) {
    $erreurFiltre = 'La date de début est invalide.';
    $dateDebut = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin)) {
    $erreurFiltre = 'La date de fin est invalide.';
    $dateFin = date('Y-m-t');
}
if ($dateFin < $dateDebut) {
    $erreurFiltre = 'La date de fin doit être postérieure à la date de début.';
    [$dateDebut, $dateFin] = [$dateFin, $dateDebut];
}

$idBatiment = ctype_digit((string)$batiment) ? (int)$batiment : null;

$erreur = '';
try {
    $reservations = $reservationC->rapportPeriode($dateDebut, $dateFin, $idBatiment);
    $batiments    = $batimentC->showBatiments();
    $stats        = $salleC->statistiquesUtilisation($dateDebut . ' 00:00:00', $dateFin . ' 23:59:59');
} catch (Throwable $e) {
    $erreur = 'Erreur lors de la génération : ' . $e->getMessage();
    $reservations = []; $batiments = []; $stats = [];
}

// ---- Agrégats ----
$parStatut = ['en_attente' => 0, 'validee' => 0, 'refusee' => 0, 'annulee' => 0, 'terminee' => 0];
$minutesTotales = 0;
$participants   = 0;
$parSalle       = [];
$parDemandeur   = [];

foreach ($reservations as $r) {
    if (isset($parStatut[$r['statut']])) $parStatut[$r['statut']]++;
    $participants += (int)$r['nb_participants'];

    if (in_array($r['statut'], ['validee', 'terminee'], true)) {
        $minutesTotales += (strtotime($r['date_fin']) - strtotime($r['date_debut'])) / 60;
    }

    $cleSalle = $r['nom_salle'] . ' — ' . $r['nom_batiment'];
    $parSalle[$cleSalle] = ($parSalle[$cleSalle] ?? 0) + 1;

    $cleUser = $r['prenom_utilisateur'] . ' ' . $r['nom_utilisateur'];
    $parDemandeur[$cleUser] = ($parDemandeur[$cleUser] ?? 0) + 1;
}

arsort($parSalle);
arsort($parDemandeur);

$total       = count($reservations);
$tauxAccord  = $total > 0 ? round((($parStatut['validee'] + $parStatut['terminee']) / $total) * 100, 1) : 0;
$nomBatiment = 'Tous les bâtiments';
foreach ($batiments as $b) {
    if ((string)$b['id_batiment'] === (string)$batiment) $nomBatiment = $b['nom'];
}

$titrePage  = 'Rapport';
$pageActive = 'rapport';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header no-print">
    <div>
        <div class="fil-ariane"><a href="index.php">Tableau de bord</a> / Rapport</div>
        <h2><i class="fas fa-file-lines"></i> Rapport d'activité</h2>
        <p>Générez un rapport par période, imprimable ou exportable en PDF.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="statistiques.php?date_debut=<?= e($dateDebut) ?>&date_fin=<?= e($dateFin) ?>" class="btn btn-light">
            <i class="fas fa-chart-pie"></i> Statistiques
        </a>
        <button type="button" class="btn btn-primary" onclick="window.print();">
            <i class="fas fa-print"></i> Imprimer / PDF
        </button>
    </div>
</div>

<?php if ($erreur !== ''): ?>
    <div class="alert alert-danger no-print"><i class="fas fa-circle-exclamation"></i><span><?= e($erreur) ?></span></div>
<?php endif; ?>
<?php if ($erreurFiltre !== ''): ?>
    <div class="alert alert-warning no-print"><i class="fas fa-triangle-exclamation"></i><span><?= e($erreurFiltre) ?></span></div>
<?php endif; ?>

<div class="card no-print">
    <div class="card-header"><h3><i class="fas fa-sliders"></i> Paramètres du rapport</h3></div>
    <div class="card-body">
        <form method="get" id="formRapport" class="filtres-grid" novalidate>
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
            <div class="form-group">
                <label for="batiment">Bâtiment</label>
                <select id="batiment" name="batiment">
                    <option value="tous">Tous les bâtiments</option>
                    <?php foreach ($batiments as $b): ?>
                        <option value="<?= (int)$b['id_batiment'] ?>" <?= (string)$batiment === (string)$b['id_batiment'] ? 'selected' : '' ?>>
                            <?= e($b['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtres-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-file-export"></i> Générer</button>
                <a href="rapport.php" class="btn btn-light"><i class="fas fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- ===================== Document imprimable ===================== -->
<div class="document">

    <div class="doc-entete">
        <div>
            <h1>Rapport d'utilisation des salles</h1>
            <p class="doc-sous-titre">
                <?= e($nomBatiment) ?> · du <?= e(fmt_date($dateDebut)) ?> au <?= e(fmt_date($dateFin)) ?>
            </p>
        </div>
        <div class="doc-meta">
            <div><strong><?= e(APP_NAME) ?></strong></div>
            <div>Édité le <?= e(fmt_datetime(date('Y-m-d H:i:s'))) ?></div>
            <div>Par <?= e(utilisateur_courant()['prenom'] . ' ' . utilisateur_courant()['nom']) ?></div>
        </div>
    </div>

    <h3 class="doc-section">1. Synthèse</h3>
    <div class="grid grid-4 mb-3">
        <div class="stat-card">
            <div class="stat-icon bleu"><i class="fas fa-calendar-check"></i></div>
            <div><div class="stat-value"><?= (int)$total ?></div><div class="stat-label">Demandes</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon vert"><i class="fas fa-circle-check"></i></div>
            <div><div class="stat-value"><?= (int)$tauxAccord ?> %</div><div class="stat-label">Taux d'acceptation</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon violet"><i class="fas fa-hourglass-half"></i></div>
            <div><div class="stat-value"><?= e((string)round($minutesTotales / 60, 1)) ?> h</div><div class="stat-label">Heures réservées</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-users"></i></div>
            <div><div class="stat-value"><?= (int)$participants ?></div><div class="stat-label">Participants cumulés</div></div>
        </div>
    </div>

    <table class="data doc-table">
        <thead><tr><th>Statut</th><th>Nombre</th><th>Part</th></tr></thead>
        <tbody>
        <?php foreach ($parStatut as $st => $nb): ?>
            <tr>
                <td><span class="badge <?= classe_statut($st) ?>"><?= e(libelle_statut($st)) ?></span></td>
                <td><?= (int)$nb ?></td>
                <td><?= $total > 0 ? (int)round(($nb / $total) * 100) : 0 ?> %</td>
            </tr>
        <?php endforeach; ?>
        <tr class="ligne-total">
            <td><strong>Total</strong></td><td><strong><?= (int)$total ?></strong></td><td><strong>100 %</strong></td>
        </tr>
        </tbody>
    </table>

    <h3 class="doc-section">2. Occupation par salle</h3>
    <?php if (!$stats): ?>
        <p class="text-muted">Aucune salle enregistrée.</p>
    <?php else: ?>
        <table class="data doc-table">
            <thead>
                <tr><th>Salle</th><th>Bâtiment</th><th>Capacité</th><th>Réservations</th>
                    <th>Heures</th><th>Taux d'occupation</th></tr>
            </thead>
            <tbody>
            <?php foreach ($stats as $s): ?>
                <tr>
                    <td><?= e($s['nom']) ?> <span class="text-muted">(<?= e($s['code_salle']) ?>)</span></td>
                    <td><?= e($s['nom_batiment']) ?></td>
                    <td><?= (int)$s['capacite'] ?></td>
                    <td><?= (int)$s['nb_reservations'] ?></td>
                    <td><?= e((string)$s['heures_occupees']) ?> h</td>
                    <td class="mono"><?= e((string)$s['taux_occupation']) ?> %</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h3 class="doc-section">3. Principaux demandeurs</h3>
    <?php if (!$parDemandeur): ?>
        <p class="text-muted">Aucune demande sur la période.</p>
    <?php else: ?>
        <table class="data doc-table">
            <thead><tr><th>Demandeur</th><th>Demandes</th><th>Part</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($parDemandeur, 0, 10, true) as $nomUser => $nb): ?>
                <tr>
                    <td><?= e($nomUser) ?></td>
                    <td><?= (int)$nb ?></td>
                    <td><?= $total > 0 ? (int)round(($nb / $total) * 100) : 0 ?> %</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h3 class="doc-section">4. Détail des réservations</h3>
    <?php if (!$reservations): ?>
        <p class="text-muted">Aucune réservation sur cette période.</p>
    <?php else: ?>
        <table class="data doc-table">
            <thead>
                <tr><th>Date</th><th>Créneau</th><th>Objet</th><th>Salle</th>
                    <th>Demandeur</th><th>Pers.</th><th>Statut</th></tr>
            </thead>
            <tbody>
            <?php foreach ($reservations as $r): ?>
                <tr>
                    <td><?= e(date('d/m/Y', strtotime($r['date_debut']))) ?></td>
                    <td class="mono"><?= e(fmt_heure($r['date_debut'])) ?>–<?= e(fmt_heure($r['date_fin'])) ?></td>
                    <td><?= e($r['titre']) ?></td>
                    <td><?= e($r['nom_salle']) ?><div class="cell-sub"><?= e($r['nom_batiment']) ?></div></td>
                    <td><?= e($r['prenom_utilisateur'] . ' ' . $r['nom_utilisateur']) ?></td>
                    <td><?= (int)$r['nb_participants'] ?></td>
                    <td><span class="badge <?= classe_statut($r['statut']) ?>"><?= e(libelle_statut($r['statut'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p class="doc-pied">
        Rapport généré automatiquement par <?= e(APP_NAME) ?> — document interne.
    </p>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>

<script>
Valider.attacher('formRapport', {
    date_debut: [{ test: v => Valider.requis(v), message: 'La date de début est obligatoire.' }],
    date_fin: [
        { test: v => Valider.requis(v), message: 'La date de fin est obligatoire.' },
        { test: (v, f) => v >= f.querySelector('#date_debut').value,
          message: 'La date de fin doit être postérieure à la date de début.' }
    ]
});
</script>
