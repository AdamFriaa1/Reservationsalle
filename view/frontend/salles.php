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

/* --- Puces des filtres actifs, chacune retirable d'un clic --- */
$base    = $_GET;
$sansCle = function (array $cles) use ($base): string {
    $q = $base;
    foreach ($cles as $c) { unset($q[$c]); }
    return 'salles.php' . ($q ? '?' . http_build_query($q) : '');
};
$actifs = [];
if ($filtres['recherche'] !== '') {
    $actifs[] = ['« ' . $filtres['recherche'] . ' »', $sansCle(['recherche'])];
}
if ($filtres['batiment'] !== 'tous') {
    $nomBat = 'Bâtiment';
    foreach ($batiments as $b) {
        if ((string)$b['id_batiment'] === (string)$filtres['batiment']) { $nomBat = $b['nom']; break; }
    }
    $actifs[] = [$nomBat, $sansCle(['batiment'])];
}
if ($filtres['type'] !== 'tous')     $actifs[] = [libelle_type_salle($filtres['type']), $sansCle(['type'])];
if ($filtres['capacite_min'] !== '') $actifs[] = ['≥ ' . (int)$filtres['capacite_min'] . ' places', $sansCle(['capacite_min'])];
if ($filtres['equipement'] !== '')   $actifs[] = [$filtres['equipement'], $sansCle(['equipement'])];
if ($filtres['pmr'] === 'oui')       $actifs[] = ['Accessible PMR', $sansCle(['pmr'])];
if ($parCreneau)                     $actifs[] = ['Libre sur un créneau', $sansCle(['debut', 'fin'])];

$titrePage  = 'Les salles';
$pageActive = 'salles';
require __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <span class="eyebrow">Catalogue</span>
        <h1 class="mt-2">Les salles disponibles</h1>
        <p>Filtrez par bâtiment, capacité, équipement — ou cherchez ce qui est libre sur un créneau précis.</p>
    </div>
    <div class="row g-2 wrapf">
        <a href="calendrier.php" class="btn"><i class="fas fa-calendar-days"></i> Vue calendrier</a>
        <?php if (est_connecte()): ?>
            <a href="reserver.php" class="btn btn-primary"><i class="fas fa-plus"></i> Réserver</a>
        <?php endif; ?>
    </div>
</div>

<?php flash_afficher(); ?>
<?php if ($erreur !== ''): ?>
    <div class="alert alert-bad"><i class="fas fa-circle-exclamation"></i><span><?= e($erreur) ?></span></div>
<?php endif; ?>
<?php foreach ($erreurs as $err): ?>
    <div class="alert alert-warn"><i class="fas fa-triangle-exclamation"></i><span><?= e($err) ?></span></div>
<?php endforeach; ?>

<!-- ============================================== FILTRES -->
<form method="get" class="filters" id="formFiltres" novalidate>
    <div class="row-between wrapf mb-4">
        <h2 style="font-size:var(--t-md)">
            <i class="fas fa-sliders" style="color:var(--brand-500)"></i> Filtres
        </h2>
        <span class="muted t-sm">
            <strong class="strong"><?= count($salles) ?></strong>
            salle<?= count($salles) > 1 ? 's' : '' ?> trouvée<?= count($salles) > 1 ? 's' : '' ?>
        </span>
    </div>

    <div class="filters-grid">
        <div class="field">
            <label for="recherche">Recherche</label>
            <input type="search" id="recherche" name="recherche" value="<?= e($filtres['recherche']) ?>"
                   placeholder="Nom, code, lieu…">
        </div>

        <div class="field">
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

        <div class="field">
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

        <div class="field">
            <label for="capacite_min">Capacité minimale</label>
            <input type="number" id="capacite_min" name="capacite_min" min="1" max="500"
                   value="<?= e($filtres['capacite_min']) ?>" placeholder="ex. 10">
            <span class="err" id="err-capacite_min"></span>
        </div>

        <div class="field">
            <label for="equipement">Équipement</label>
            <input type="text" id="equipement" name="equipement" value="<?= e($filtres['equipement']) ?>"
                   placeholder="Vidéoprojecteur, Wifi…">
        </div>

        <div class="field">
            <label for="debut">Libre à partir de</label>
            <input type="datetime-local" id="debut" name="debut" value="<?= e($creneauDebut) ?>">
            <span class="err" id="err-debut"></span>
        </div>

        <div class="field">
            <label for="fin">Jusqu'à</label>
            <input type="datetime-local" id="fin" name="fin" value="<?= e($creneauFin) ?>">
            <span class="err" id="err-fin"></span>
        </div>

        <div class="field">
            <label for="orderBy">Trier par</label>
            <select id="orderBy" name="orderBy">
                <option value="batiment" <?= $filtres['orderBy'] === 'batiment' ? 'selected' : '' ?>>Bâtiment</option>
                <option value="nom"      <?= $filtres['orderBy'] === 'nom' ? 'selected' : '' ?>>Nom</option>
                <option value="capacite" <?= $filtres['orderBy'] === 'capacite' ? 'selected' : '' ?>>Capacité</option>
                <option value="type"     <?= $filtres['orderBy'] === 'type' ? 'selected' : '' ?>>Type</option>
            </select>
        </div>

        <div class="field">
            <label for="orderDir">Ordre</label>
            <select id="orderDir" name="orderDir">
                <option value="ASC"  <?= strtoupper($filtres['orderDir']) === 'ASC' ? 'selected' : '' ?>>Croissant</option>
                <option value="DESC" <?= strtoupper($filtres['orderDir']) === 'DESC' ? 'selected' : '' ?>>Décroissant</option>
            </select>
        </div>

        <div class="field">
            <label class="check" style="margin-top:26px">
                <input type="checkbox" name="pmr" value="oui" <?= $filtres['pmr'] === 'oui' ? 'checked' : '' ?>>
                Accessible PMR
            </label>
        </div>
    </div>

    <div class="row g-3 wrapf mt-5">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-magnifying-glass"></i> Rechercher
        </button>
        <a href="salles.php" class="btn btn-ghost"><i class="fas fa-rotate-left"></i> Réinitialiser</a>
    </div>
</form>

<?php if ($actifs): ?>
    <div class="row g-2 wrapf mb-5">
        <span class="muted t-sm">Filtres actifs :</span>
        <?php foreach ($actifs as [$libelle, $lien]): ?>
            <a class="chip-action" href="<?= e($lien) ?>" title="Retirer ce filtre">
                <?= e($libelle) ?> <i class="fas fa-xmark"></i>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($parCreneau): ?>
    <div class="alert alert-info">
        <i class="fas fa-circle-info"></i>
        <span>Salles réellement libres du
            <strong><?= e(date('d/m/Y à H:i', strtotime($creneauDebut))) ?></strong>
            au <strong><?= e(date('H:i', strtotime($creneauFin))) ?></strong>.</span>
    </div>
<?php endif; ?>

<!-- ============================================== RÉSULTATS -->
<?php if (!$salles): ?>
    <div class="card"><div class="empty">
        <span class="empty-icon"><i class="fas fa-magnifying-glass"></i></span>
        <h3>Aucune salle ne correspond</h3>
        <p>Élargissez vos critères, ou essayez un autre créneau.</p>
        <a href="salles.php" class="btn mt-3"><i class="fas fa-rotate-left"></i> Tout réafficher</a>
    </div></div>
<?php else: ?>
    <div class="grid g-auto">
        <?php foreach ($salles as $i => $s): ?>
            <?php
            $photo = photo_salle_url($s['image'] ?? null);
            $ico = match ($s['type_salle']) {
                'conference' => 'fa-chalkboard-user',
                'visio'      => 'fa-video',
                'formation'  => 'fa-graduation-cap',
                'coworking'  => 'fa-laptop-code',
                default      => 'fa-users',
            };
            ?>
            <article class="room reveal" data-d="<?= min(5, ($i % 3) + 1) ?>">
                <div class="room-media">
                    <?php if ($photo): ?>
                        <img src="<?= e($photo) ?>" alt="Photo de <?= e($s['nom']) ?>" loading="lazy">
                    <?php else: ?>
                        <i class="fas <?= $ico ?>"></i>
                    <?php endif; ?>
                    <span class="room-code"><?= e($s['code_salle']) ?></span>
                    <span class="room-state">
                        <span class="badge <?= $s['etat'] === 'disponible' ? 'badge-ok' : 'badge-warn' ?>">
                            <?= e(libelle_etat_salle($s['etat'])) ?>
                        </span>
                    </span>
                </div>
                <div class="room-body">
                    <h3><?= e($s['nom']) ?></h3>
                    <div class="room-where">
                        <i class="fas fa-location-dot"></i>
                        <?= e($s['nom_batiment']) ?> · <?= e($s['nom_etage']) ?>
                        <?php if ((int)$s['accessible_pmr'] === 1): ?>
                            <i class="fas fa-wheelchair" title="Accessible PMR"></i>
                        <?php endif; ?>
                    </div>
                    <div class="room-meta">
                        <span><i class="fas fa-users"></i> <?= (int)$s['capacite'] ?> places</span>
                        <span><i class="fas fa-tag"></i> <?= e(libelle_type_salle($s['type_salle'])) ?></span>
                        <span><i class="fas fa-clock"></i>
                            <?= e(substr($s['heure_ouverture'], 0, 5)) ?>–<?= e(substr($s['heure_fermeture'], 0, 5)) ?>
                        </span>
                    </div>
                    <?php $equipements = liste_equipements($s['equipements']); ?>
                    <?php if ($equipements): ?>
                        <div class="tags">
                            <?php foreach (array_slice($equipements, 0, 4) as $eq): ?>
                                <span class="tag"><?= e($eq) ?></span>
                            <?php endforeach; ?>
                            <?php if (count($equipements) > 4): ?>
                                <span class="tag">+<?= count($equipements) - 4 ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="room-actions">
                        <a href="calendrier.php?salle=<?= (int)$s['id_salle'] ?>" class="btn btn-sm">
                            <i class="fas fa-calendar"></i> Disponibilités
                        </a>
                        <a href="reserver.php?salle=<?= (int)$s['id_salle'] ?><?= $parCreneau ? '&debut=' . urlencode($creneauDebut) . '&fin=' . urlencode($creneauFin) : '' ?>"
                           class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Réserver
                        </a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

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
        { test: (v, f) => !(v.trim() === '' && f.querySelector('#fin').value.trim() !== ''),
          message: "Indiquez aussi l'heure de début." }
    ]
});
</script>
