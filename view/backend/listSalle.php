<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin']);

$salleC    = new SalleC();
$batimentC = new BatimentC();
$etageC    = new EtageC();

// ---- Changement rapide d'état (maintenance / disponibilité) ----
if (isset($_GET['etat'], $_GET['salle']) && ctype_digit((string)$_GET['salle'])) {
    try {
        $salleC->changerEtat((int)$_GET['salle'], $_GET['etat']);
        flash_set('success', 'État de la salle mis à jour : ' . libelle_etat_salle($_GET['etat']) . '.');
    } catch (Throwable $e) {
        flash_set('error', 'Changement d\'état impossible : ' . $e->getMessage());
    }
    redirect('listSalle.php');
}

$filtres = [
    'recherche'    => trim($_GET['recherche'] ?? ''),
    'batiment'     => $_GET['batiment'] ?? 'tous',
    'etage'        => $_GET['etage'] ?? 'tous',
    'type'         => $_GET['type'] ?? 'tous',
    'etat'         => $_GET['etat_filtre'] ?? 'tous',
    'capacite_min' => $_GET['capacite_min'] ?? '',
    'orderBy'      => $_GET['orderBy'] ?? 'batiment',
    'orderDir'     => $_GET['orderDir'] ?? 'ASC',
];

$erreur = '';
try {
    $salles    = $salleC->filterSalles($filtres);
    $batiments = $batimentC->showBatiments();
    $etages    = $etageC->getEtagesPourSelect();
    $compteurs = $salleC->compteurs();
} catch (Throwable $e) {
    $erreur = 'Erreur lors du chargement : ' . $e->getMessage();
    $salles = []; $batiments = []; $etages = [];
    $compteurs = ['total' => 0, 'disponibles' => 0, 'maintenance' => 0, 'indisponibles' => 0, 'capacite_totale' => 0];
}

$titrePage  = 'Salles';
$pageActive = 'salles';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <div class="fil-ariane"><a href="index.php">Tableau de bord</a> / Salles</div>
        <h2><i class="fas fa-door-open"></i> Gestion des salles</h2>
        <p>Caractéristiques, équipements, horaires et état de maintenance.</p>
    </div>
    <a href="addSalle.php<?= $filtres['etage'] !== 'tous' ? '?etage=' . (int)$filtres['etage'] : '' ?>"
       class="btn btn-primary"><i class="fas fa-plus"></i> Nouvelle salle</a>
</div>

<?php flash_afficher(); ?>
<?php if ($erreur !== ''): ?>
    <div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i><span><?= e($erreur) ?></span></div>
<?php endif; ?>

<?php if (!$etages): ?>
    <div class="alert alert-warning">
        <i class="fas fa-triangle-exclamation"></i>
        <span>Aucun étage n'existe encore. <a href="addEtage.php">Créez d'abord un étage</a>.</span>
    </div>
<?php endif; ?>

<div class="grid grid-4 mb-3">
    <div class="stat-card">
        <div class="stat-icon bleu"><i class="fas fa-door-open"></i></div>
        <div><div class="stat-value"><?= (int)$compteurs['total'] ?></div><div class="stat-label">Salles au total</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon vert"><i class="fas fa-circle-check"></i></div>
        <div><div class="stat-value"><?= (int)$compteurs['disponibles'] ?></div><div class="stat-label">Disponibles</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-screwdriver-wrench"></i></div>
        <div><div class="stat-value"><?= (int)$compteurs['maintenance'] ?></div><div class="stat-label">En maintenance</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gris"><i class="fas fa-chair"></i></div>
        <div><div class="stat-value"><?= (int)$compteurs['capacite_totale'] ?></div><div class="stat-label">Places</div></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-sliders"></i> Recherche multicritère</h3></div>
    <div class="card-body">
        <form method="get" class="filtres-grid">
            <div class="form-group">
                <label for="recherche">Recherche</label>
                <input type="search" id="recherche" name="recherche" value="<?= e($filtres['recherche']) ?>"
                       placeholder="Nom, code, équipement…">
            </div>
            <div class="form-group">
                <label for="batiment">Bâtiment</label>
                <select id="batiment" name="batiment">
                    <option value="tous">Tous</option>
                    <?php foreach ($batiments as $b): ?>
                        <option value="<?= (int)$b['id_batiment'] ?>" <?= (string)$filtres['batiment'] === (string)$b['id_batiment'] ? 'selected' : '' ?>>
                            <?= e($b['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="type">Type</label>
                <select id="type" name="type">
                    <option value="tous">Tous</option>
                    <?php foreach (Salle::TYPES as $t): ?>
                        <option value="<?= e($t) ?>" <?= $filtres['type'] === $t ? 'selected' : '' ?>>
                            <?= e(libelle_type_salle($t)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="etat_filtre">État</label>
                <select id="etat_filtre" name="etat_filtre">
                    <option value="tous">Tous</option>
                    <?php foreach (Salle::ETATS as $et): ?>
                        <option value="<?= e($et) ?>" <?= $filtres['etat'] === $et ? 'selected' : '' ?>>
                            <?= e(libelle_etat_salle($et)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="capacite_min">Capacité min.</label>
                <input type="number" id="capacite_min" name="capacite_min" min="1" max="500"
                       value="<?= e($filtres['capacite_min']) ?>">
                <span class="erreur-champ" id="err-capacite_min"></span>
            </div>
            <div class="filtres-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-magnifying-glass"></i> Filtrer</button>
                <a href="listSalle.php" class="btn btn-light"><i class="fas fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Liste des salles</h3>
        <span class="text-muted" style="font-size:.86rem;"><?= count($salles) ?> résultat(s)</span>
    </div>

    <?php if (!$salles): ?>
        <div class="vide">
            <i class="fas fa-door-closed"></i>
            <h3>Aucune salle</h3>
            <p>Ajustez vos filtres ou créez une nouvelle salle.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Salle</th><th>Code</th><th>Localisation</th><th>Capacité</th>
                        <th>Type</th><th>Horaires</th><th>État</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($salles as $s): ?>
                    <tr>
                        <td>
                            <div class="cell-titre"><?= e($s['nom']) ?></div>
                            <?php $eq = liste_equipements($s['equipements']); ?>
                            <?php if ($eq): ?>
                                <div class="cell-sub"><?= e(implode(' · ', array_slice($eq, 0, 3))) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-info mono"><?= e($s['code_salle']) ?></span></td>
                        <td>
                            <?= e($s['nom_batiment']) ?>
                            <div class="cell-sub"><?= e($s['nom_etage']) ?><?= !empty($s['localisation']) ? ' · ' . e($s['localisation']) : '' ?></div>
                        </td>
                        <td><i class="fas fa-users text-muted"></i> <?= (int)$s['capacite'] ?></td>
                        <td><span class="badge badge-violet"><?= e(libelle_type_salle($s['type_salle'])) ?></span></td>
                        <td class="mono"><?= e(substr($s['heure_ouverture'], 0, 5)) ?>–<?= e(substr($s['heure_fermeture'], 0, 5)) ?></td>
                        <td>
                            <?php
                            $classeEtat = ['disponible' => 'badge-success', 'maintenance' => 'badge-warning', 'indisponible' => 'badge-danger'];
                            ?>
                            <span class="badge <?= $classeEtat[$s['etat']] ?? 'badge-muted' ?>">
                                <?= e(libelle_etat_salle($s['etat'])) ?>
                            </span>
                        </td>
                        <td>
                            <div class="td-actions">
                                <?php if ($s['etat'] === 'disponible'): ?>
                                    <a href="?etat=maintenance&salle=<?= (int)$s['id_salle'] ?>"
                                       class="btn btn-warning btn-sm" title="Mettre en maintenance"
                                       onclick="return confirmerAction('Passer cette salle en maintenance ?');">
                                        <i class="fas fa-screwdriver-wrench"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="?etat=disponible&salle=<?= (int)$s['id_salle'] ?>"
                                       class="btn btn-success btn-sm" title="Remettre en service"
                                       onclick="return confirmerAction('Remettre cette salle en service ?');">
                                        <i class="fas fa-circle-check"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="calendrier.php?salle=<?= (int)$s['id_salle'] ?>"
                                   class="btn btn-light btn-sm" title="Planning"><i class="fas fa-calendar"></i></a>
                                <a href="updateSalle.php?id=<?= (int)$s['id_salle'] ?>"
                                   class="btn btn-light btn-sm" title="Modifier"><i class="fas fa-pen"></i></a>
                                <a href="deleteSalle.php?id=<?= (int)$s['id_salle'] ?>"
                                   class="btn btn-danger btn-sm" title="Supprimer"
                                   onclick="return confirmerSuppression('Supprimer la salle « <?= e(addslashes($s['nom'])) ?> » ? Ses réservations seront supprimées aussi.');">
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
