<?php
require_once dirname(__DIR__, 2) . '/init.php';

$salleC    = new SalleC();
$batimentC = new BatimentC();

$filtres = [
    'recherche'    => trim($_GET['recherche'] ?? ''),
    'batiment'     => $_GET['batiment'] ?? 'tous',
    'type'         => $_GET['type'] ?? 'tous',
    'capacite_min' => $_GET['capacite_min'] ?? '',
    'equipement'   => trim($_GET['equipement'] ?? ''),
    'pmr'          => $_GET['pmr'] ?? 'non',
    'orderBy'      => $_GET['orderBy'] ?? 'batiment',
    'orderDir'     => $_GET['orderDir'] ?? 'ASC',
    'etat'         => 'disponible',   // le front n'affiche que les salles réservables
];

// Recherche par créneau : on n'affiche que les salles réellement libres
$creneauDebut = trim($_GET['debut'] ?? '');
$creneauFin   = trim($_GET['fin'] ?? '');
$erreurs      = [];
$parCreneau   = false;

$erreur = '';
try {
    $batiments = $batimentC->showBatiments();

    if ($creneauDebut !== '' && $creneauFin !== '') {
        $debutSql = str_replace('T', ' ', $creneauDebut) . ':00';
        $finSql   = str_replace('T', ' ', $creneauFin) . ':00';
        if (strtotime($finSql) <= strtotime($debutSql)) {
            $erreurs[] = "L'heure de fin doit être postérieure à l'heure de début.";
            $salles = $salleC->filterSalles($filtres);
        } else {
            $salles = $salleC->getSallesLibres($debutSql, $finSql, (int)($filtres['capacite_min'] ?: 0));
            $parCreneau = true;
        }
    } else {
        $salles = $salleC->filterSalles($filtres);
    }
} catch (Throwable $e) {
    $erreur    = 'Erreur lors du chargement des salles : ' . $e->getMessage();
    $salles    = [];
    $batiments = [];
}

$nbFiltresActifs = 0;
foreach (['recherche', 'equipement'] as $k) { if ($filtres[$k] !== '') $nbFiltresActifs++; }
foreach (['batiment', 'type'] as $k) { if ($filtres[$k] !== 'tous') $nbFiltresActifs++; }
if ($filtres['capacite_min'] !== '') $nbFiltresActifs++;
if ($filtres['pmr'] === 'oui')       $nbFiltresActifs++;
if ($parCreneau)                     $nbFiltresActifs++;

$titrePage  = 'Les salles';
$pageActive = 'salles';
require __DIR__ . '/partials/header.php';
?>

<div class="page container">

    <div class="page-header">
        <h1><i class="fas fa-door-open"></i> Les salles disponibles</h1>
        <p>Filtrez par bâtiment, capacité, équipement ou disponibilité sur un créneau précis.</p>
    </div>

    <?php flash_afficher(); ?>
    <?php if ($erreur !== ''): ?>
        <div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i><span><?= e($erreur) ?></span></div>
    <?php endif; ?>
    <?php foreach ($erreurs as $err): ?>
        <div class="alert alert-warning"><i class="fas fa-triangle-exclamation"></i><span><?= e($err) ?></span></div>
    <?php endforeach; ?>

    <form method="get" class="filtres" id="formFiltres" novalidate>
        <div class="d-flex justify-between align-center mb-3 flex-wrap gap-2">
            <h3 style="font-size:1rem;font-weight:650;">
                <i class="fas fa-sliders"></i> Filtres
                <?php if ($nbFiltresActifs > 0): ?>
                    <span class="badge badge-info"><?= $nbFiltresActifs ?> actif<?= $nbFiltresActifs > 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </h3>
            <span class="text-muted" style="font-size:.87rem;">
                <?= count($salles) ?> salle<?= count($salles) > 1 ? 's' : '' ?> trouvée<?= count($salles) > 1 ? 's' : '' ?>
            </span>
        </div>

        <div class="filtres-grid">
            <div class="form-group">
                <label for="recherche">Recherche</label>
                <input type="search" id="recherche" name="recherche" value="<?= e($filtres['recherche']) ?>"
                       placeholder="Nom, code, lieu…">
            </div>

            <div class="form-group">
                <label for="batiment">Bâtiment</label>
                <select id="batiment" name="batiment">
                    <option value="tous">Tous les bâtiments</option>
                    <?php foreach ($batiments as $b): ?>
                        <option value="<?= (int)$b['id_batiment'] ?>"
                            <?= (string)$filtres['batiment'] === (string)$b['id_batiment'] ? 'selected' : '' ?>>
                            <?= e($b['nom']) ?> (<?= e($b['ville']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="type">Type de salle</label>
                <select id="type" name="type">
                    <option value="tous">Tous les types</option>
                    <?php foreach (Salle::TYPES as $t): ?>
                        <option value="<?= e($t) ?>" <?= $filtres['type'] === $t ? 'selected' : '' ?>>
                            <?= e(libelle_type_salle($t)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="capacite_min">Capacité minimale</label>
                <input type="number" id="capacite_min" name="capacite_min" min="1" max="500"
                       value="<?= e($filtres['capacite_min']) ?>" placeholder="ex. 10">
                <span class="erreur-champ" id="err-capacite_min"></span>
            </div>

            <div class="form-group">
                <label for="equipement">Équipement</label>
                <input type="text" id="equipement" name="equipement" value="<?= e($filtres['equipement']) ?>"
                       placeholder="Vidéoprojecteur, Wifi…">
            </div>

            <div class="form-group">
                <label for="debut">Libre à partir de</label>
                <input type="datetime-local" id="debut" name="debut" value="<?= e($creneauDebut) ?>">
                <span class="erreur-champ" id="err-debut"></span>
            </div>

            <div class="form-group">
                <label for="fin">Jusqu'à</label>
                <input type="datetime-local" id="fin" name="fin" value="<?= e($creneauFin) ?>">
                <span class="erreur-champ" id="err-fin"></span>
            </div>

            <div class="form-group">
                <label for="orderBy">Trier par</label>
                <select id="orderBy" name="orderBy">
                    <option value="batiment" <?= $filtres['orderBy'] === 'batiment' ? 'selected' : '' ?>>Bâtiment</option>
                    <option value="nom"      <?= $filtres['orderBy'] === 'nom' ? 'selected' : '' ?>>Nom</option>
                    <option value="capacite" <?= $filtres['orderBy'] === 'capacite' ? 'selected' : '' ?>>Capacité</option>
                    <option value="type"     <?= $filtres['orderBy'] === 'type' ? 'selected' : '' ?>>Type</option>
                </select>
            </div>

            <div class="form-group">
                <label for="orderDir">Ordre</label>
                <select id="orderDir" name="orderDir">
                    <option value="ASC"  <?= strtoupper($filtres['orderDir']) === 'ASC' ? 'selected' : '' ?>>Croissant</option>
                    <option value="DESC" <?= strtoupper($filtres['orderDir']) === 'DESC' ? 'selected' : '' ?>>Décroissant</option>
                </select>
            </div>

            <div class="form-group">
                <label class="checkbox-line" style="margin-top:22px;">
                    <input type="checkbox" name="pmr" value="oui" <?= $filtres['pmr'] === 'oui' ? 'checked' : '' ?>>
                    Accessible PMR
                </label>
            </div>
        </div>

        <div class="filtres-actions mt-3">
            <button type="submit" class="btn btn-primary"><i class="fas fa-magnifying-glass"></i> Rechercher</button>
            <a href="salles.php" class="btn btn-light"><i class="fas fa-rotate-left"></i> Réinitialiser</a>
        </div>
    </form>

    <?php if ($parCreneau): ?>
        <div class="alert alert-info">
            <i class="fas fa-circle-info"></i>
            <span>Salles réellement libres du <strong><?= e(date('d/m/Y H:i', strtotime($creneauDebut))) ?></strong>
                  au <strong><?= e(date('d/m/Y H:i', strtotime($creneauFin))) ?></strong>.</span>
        </div>
    <?php endif; ?>

    <?php if (!$salles): ?>
        <div class="card"><div class="vide">
            <i class="fas fa-magnifying-glass"></i>
            <h3>Aucune salle ne correspond</h3>
            <p>Élargissez vos critères ou essayez un autre créneau.</p>
        </div></div>
    <?php else: ?>
        <div class="grid grid-3">
            <?php foreach ($salles as $s): ?>
                <div class="salle-card">
                    <div class="salle-visuel">
                        <span class="code"><?= e($s['code_salle']) ?></span>
                        <span class="etat">
                            <span class="badge <?= $s['etat'] === 'disponible' ? 'badge-success' : 'badge-warning' ?>">
                                <?= e(libelle_etat_salle($s['etat'])) ?>
                            </span>
                        </span>
                        <i class="fas fa-<?= $s['type_salle'] === 'conference' ? 'chalkboard-user'
                            : ($s['type_salle'] === 'visio' ? 'video'
                            : ($s['type_salle'] === 'formation' ? 'graduation-cap'
                            : ($s['type_salle'] === 'coworking' ? 'laptop-code' : 'users'))) ?>"></i>
                    </div>
                    <div class="salle-body">
                        <h3><?= e($s['nom']) ?></h3>
                        <div class="salle-lieu">
                            <i class="fas fa-location-dot"></i>
                            <?= e($s['nom_batiment']) ?> · <?= e($s['nom_etage']) ?>
                            <?php if ((int)$s['accessible_pmr'] === 1): ?>
                                · <i class="fas fa-wheelchair" title="Accessible PMR"></i>
                            <?php endif; ?>
                        </div>
                        <div class="salle-meta">
                            <span><i class="fas fa-users"></i> <?= (int)$s['capacite'] ?> places</span>
                            <span><i class="fas fa-tag"></i> <?= e(libelle_type_salle($s['type_salle'])) ?></span>
                            <span><i class="fas fa-clock"></i> <?= e(substr($s['heure_ouverture'], 0, 5)) ?>–<?= e(substr($s['heure_fermeture'], 0, 5)) ?></span>
                        </div>
                        <?php $equipements = liste_equipements($s['equipements']); ?>
                        <?php if ($equipements): ?>
                            <div class="equip-list">
                                <?php foreach ($equipements as $eq): ?>
                                    <span class="equip"><?= e($eq) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="salle-actions">
                            <a href="calendrier.php?salle=<?= (int)$s['id_salle'] ?>" class="btn btn-light btn-sm">
                                <i class="fas fa-calendar"></i> Disponibilités
                            </a>
                            <a href="reserver.php?salle=<?= (int)$s['id_salle'] ?><?= $parCreneau ? '&debut=' . urlencode($creneauDebut) . '&fin=' . urlencode($creneauFin) : '' ?>"
                               class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Réserver
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>

<script>
Valider.attacher('formFiltres', {
    capacite_min: [
        { test: v => v.trim() === '' || Valider.entier(v, 1, 500), message: 'Entier entre 1 et 500.' }
    ],
    fin: [
        { test: (v, f) => {
            const d = f.querySelector('#debut').value;
            if (v.trim() === '' || d.trim() === '') return true;
            return Valider.apres(v, d);
          }, message: "La fin doit être après le début." }
    ],
    debut: [
        { test: (v, f) => {
            const fin = f.querySelector('#fin').value;
            if (v.trim() === '' && fin.trim() !== '') return false;
            return true;
          }, message: "Indiquez aussi l'heure de début." }
    ]
});
</script>
