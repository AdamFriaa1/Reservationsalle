<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin']);

$etageC    = new EtageC();
$batimentC = new BatimentC();

$recherche = trim($_GET['recherche'] ?? '');
$batiment  = $_GET['batiment'] ?? 'tous';
$pmr       = $_GET['pmr'] ?? 'tous';
$orderBy   = $_GET['orderBy'] ?? 'batiment';
$orderDir  = $_GET['orderDir'] ?? 'ASC';

$erreur = '';
try {
    $etages    = $etageC->filterEtages($recherche, $batiment, $pmr, $orderBy, $orderDir);
    $batiments = $batimentC->showBatiments();
} catch (Throwable $e) {
    $erreur = 'Erreur lors du chargement : ' . $e->getMessage();
    $etages = []; $batiments = [];
}

$titrePage  = 'Étages';
$pageActive = 'etages';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <div class="fil-ariane"><a href="index.php">Tableau de bord</a> / Étages</div>
        <h2><i class="fas fa-layer-group"></i> Gestion des étages</h2>
        <p>Chaque étage appartient à un bâtiment et regroupe des salles.</p>
    </div>
    <a href="addEtage.php<?= $batiment !== 'tous' ? '?batiment=' . (int)$batiment : '' ?>" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nouvel étage
    </a>
</div>

<?php flash_afficher(); ?>
<?php if ($erreur !== ''): ?>
    <div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i><span><?= e($erreur) ?></span></div>
<?php endif; ?>

<?php if (!$batiments): ?>
    <div class="alert alert-warning">
        <i class="fas fa-triangle-exclamation"></i>
        <span>Aucun bâtiment n'existe encore. <a href="addBatiment.php">Créez d'abord un bâtiment</a>.</span>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-sliders"></i> Recherche et filtres</h3></div>
    <div class="card-body">
        <form method="get" class="filtres-grid">
            <div class="form-group">
                <label for="recherche">Recherche</label>
                <input type="search" id="recherche" name="recherche" value="<?= e($recherche) ?>"
                       placeholder="Nom d'étage, bâtiment…">
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
            <div class="form-group">
                <label for="pmr">Accessibilité PMR</label>
                <select id="pmr" name="pmr">
                    <option value="tous" <?= $pmr === 'tous' ? 'selected' : '' ?>>Indifférent</option>
                    <option value="oui"  <?= $pmr === 'oui' ? 'selected' : '' ?>>Accessible</option>
                    <option value="non"  <?= $pmr === 'non' ? 'selected' : '' ?>>Non accessible</option>
                </select>
            </div>
            <div class="filtres-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-magnifying-glass"></i> Filtrer</button>
                <a href="listEtage.php" class="btn btn-light"><i class="fas fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Liste des étages</h3>
        <span class="text-muted" style="font-size:.86rem;"><?= count($etages) ?> résultat(s)</span>
    </div>

    <?php if (!$etages): ?>
        <div class="vide">
            <i class="fas fa-layer-group"></i>
            <h3>Aucun étage</h3>
            <p>Ajoutez un étage pour pouvoir y créer des salles.</p>
            <?php if ($batiments): ?>
                <a href="addEtage.php" class="btn btn-primary mt-3"><i class="fas fa-plus"></i> Créer un étage</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Étage</th><th>Bâtiment</th><th>Niveau</th>
                        <th>PMR</th><th>Salles</th><th>Capacité</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($etages as $et): ?>
                    <tr>
                        <td class="cell-titre"><?= e($et['nom_etage']) ?></td>
                        <td>
                            <?= e($et['nom_batiment']) ?>
                            <div class="cell-sub"><?= e($et['code_batiment']) ?> · <?= e($et['ville']) ?></div>
                        </td>
                        <td><span class="badge badge-info">Niveau <?= (int)$et['numero_etage'] ?></span></td>
                        <td>
                            <?php if ((int)$et['accessible_pmr'] === 1): ?>
                                <span class="badge badge-success"><i class="fas fa-wheelchair"></i> Oui</span>
                            <?php else: ?>
                                <span class="badge badge-muted">Non</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="listSalle.php?etage=<?= (int)$et['id_etage'] ?>"><?= (int)$et['nb_salles'] ?></a>
                        </td>
                        <td><?= (int)$et['capacite_totale'] ?> places</td>
                        <td>
                            <div class="td-actions">
                                <a href="addSalle.php?etage=<?= (int)$et['id_etage'] ?>" class="btn btn-light btn-sm"
                                   title="Ajouter une salle"><i class="fas fa-door-open"></i></a>
                                <a href="updateEtage.php?id=<?= (int)$et['id_etage'] ?>" class="btn btn-light btn-sm"
                                   title="Modifier"><i class="fas fa-pen"></i></a>
                                <a href="deleteEtage.php?id=<?= (int)$et['id_etage'] ?>&batiment=<?= (int)$et['id_batiment'] ?>"
                                   class="btn btn-danger btn-sm" title="Supprimer"
                                   onclick="return confirmerSuppression('Supprimer l\'étage « <?= e(addslashes($et['nom_etage'])) ?> » et ses <?= (int)$et['nb_salles'] ?> salle(s) ?');">
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
