<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin']);

$batimentC = new BatimentC();

$recherche = trim($_GET['recherche'] ?? '');
$ville     = $_GET['ville'] ?? 'toutes';
$orderBy   = $_GET['orderBy'] ?? 'nom';
$orderDir  = $_GET['orderDir'] ?? 'ASC';

$erreur = '';
try {
    $batiments = $batimentC->filterBatiments($recherche, $ville, $orderBy, $orderDir);
    $villes    = $batimentC->getVilles();
} catch (Throwable $e) {
    $erreur = 'Erreur lors du chargement : ' . $e->getMessage();
    $batiments = []; $villes = [];
}

$totalSalles   = array_sum(array_column($batiments, 'nb_salles'));
$totalEtages   = array_sum(array_column($batiments, 'nb_etages'));
$totalCapacite = array_sum(array_column($batiments, 'capacite_totale'));

/** Construit un lien de tri en conservant les filtres. */
function lienTri(string $colonne, string $orderBy, string $orderDir, array $base): string
{
    $dir = ($orderBy === $colonne && strtoupper($orderDir) === 'ASC') ? 'DESC' : 'ASC';
    return '?' . http_build_query(array_merge($base, ['orderBy' => $colonne, 'orderDir' => $dir]));
}
$base = ['recherche' => $recherche, 'ville' => $ville];

$titrePage  = 'Bâtiments';
$pageActive = 'batiments';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <div class="fil-ariane"><a href="index.php">Tableau de bord</a> / Bâtiments</div>
        <h2><i class="fas fa-building"></i> Gestion des bâtiments</h2>
        <p>Créez et administrez les bâtiments de l'organisation.</p>
    </div>
    <a href="addBatiment.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau bâtiment</a>
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
        <div class="stat-icon violet"><i class="fas fa-layer-group"></i></div>
        <div><div class="stat-value"><?= (int)$totalEtages ?></div><div class="stat-label">Étages</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon vert"><i class="fas fa-door-open"></i></div>
        <div><div class="stat-value"><?= (int)$totalSalles ?></div><div class="stat-label">Salles</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-chair"></i></div>
        <div><div class="stat-value"><?= (int)$totalCapacite ?></div><div class="stat-label">Places</div></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-sliders"></i> Recherche et filtres</h3></div>
    <div class="card-body">
        <form method="get" class="filtres-grid">
            <div class="form-group">
                <label for="recherche">Recherche</label>
                <input type="search" id="recherche" name="recherche" value="<?= e($recherche) ?>"
                       placeholder="Nom, code, adresse…">
            </div>
            <div class="form-group">
                <label for="ville">Ville</label>
                <select id="ville" name="ville">
                    <option value="toutes">Toutes les villes</option>
                    <?php foreach ($villes as $v): ?>
                        <option value="<?= e($v) ?>" <?= $ville === $v ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtres-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-magnifying-glass"></i> Filtrer</button>
                <a href="listBatiment.php" class="btn btn-light"><i class="fas fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Liste des bâtiments</h3>
        <span class="text-muted" style="font-size:.86rem;"><?= count($batiments) ?> résultat(s)</span>
    </div>

    <?php if (!$batiments): ?>
        <div class="vide">
            <i class="fas fa-building-circle-xmark"></i>
            <h3>Aucun bâtiment</h3>
            <p>Commencez par créer un bâtiment, puis ajoutez-lui des étages et des salles.</p>
            <a href="addBatiment.php" class="btn btn-primary mt-3"><i class="fas fa-plus"></i> Créer un bâtiment</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th><a href="<?= e(lienTri('nom', $orderBy, $orderDir, $base)) ?>">Bâtiment <i class="fas fa-sort"></i></a></th>
                        <th>Code</th>
                        <th><a href="<?= e(lienTri('ville', $orderBy, $orderDir, $base)) ?>">Localisation <i class="fas fa-sort"></i></a></th>
                        <th>Étages</th>
                        <th><a href="<?= e(lienTri('salles', $orderBy, $orderDir, $base)) ?>">Salles <i class="fas fa-sort"></i></a></th>
                        <th><a href="<?= e(lienTri('capacite', $orderBy, $orderDir, $base)) ?>">Capacité <i class="fas fa-sort"></i></a></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($batiments as $b): ?>
                    <?php $bPhoto = photo_batiment_url($b['image'] ?? null); ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <?php if ($bPhoto): ?>
                                    <img src="<?= e($bPhoto) ?>" alt=""
                                         style="width:46px;height:46px;border-radius:8px;object-fit:cover;flex-shrink:0;border:1px solid var(--gris-200);">
                                <?php else: ?>
                                    <span style="width:46px;height:46px;border-radius:8px;flex-shrink:0;display:grid;place-items:center;background:var(--gris-100);color:var(--gris-500);">
                                        <i class="fas fa-building"></i>
                                    </span>
                                <?php endif; ?>
                                <div>
                                    <div class="cell-titre"><?= e($b['nom']) ?></div>
                                    <?php if (!empty($b['description'])): ?>
                                        <div class="cell-sub"><?= e(mb_substr($b['description'], 0, 55)) ?><?= mb_strlen($b['description']) > 55 ? '…' : '' ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge badge-info mono"><?= e($b['code_batiment']) ?></span></td>
                        <td>
                            <?= e($b['ville']) ?>
                            <div class="cell-sub"><?= e($b['adresse']) ?></div>
                        </td>
                        <td>
                            <a href="listEtage.php?batiment=<?= (int)$b['id_batiment'] ?>">
                                <?= (int)$b['nb_etages'] ?> étage<?= (int)$b['nb_etages'] > 1 ? 's' : '' ?>
                            </a>
                        </td>
                        <td>
                            <a href="listSalle.php?batiment=<?= (int)$b['id_batiment'] ?>">
                                <?= (int)$b['nb_salles'] ?>
                            </a>
                        </td>
                        <td><?= (int)$b['capacite_totale'] ?> places</td>
                        <td>
                            <div class="td-actions">
                                <a href="listEtage.php?batiment=<?= (int)$b['id_batiment'] ?>"
                                   class="btn btn-light btn-sm" title="Voir les étages"><i class="fas fa-layer-group"></i></a>
                                <a href="updateBatiment.php?id=<?= (int)$b['id_batiment'] ?>"
                                   class="btn btn-light btn-sm" title="Modifier"><i class="fas fa-pen"></i></a>
                                <a href="deleteBatiment.php?id=<?= (int)$b['id_batiment'] ?>"
                                   class="btn btn-danger btn-sm" title="Supprimer"
                                   onclick="return confirmerSuppression('Supprimer « <?= e(addslashes($b['nom'])) ?> » ? Les <?= (int)$b['nb_etages'] ?> étage(s) et <?= (int)$b['nb_salles'] ?> salle(s) rattaché(e)s seront supprimé(e)s.');">
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

<?php require __DIR__ . '/partials/footer.php'; ?>
