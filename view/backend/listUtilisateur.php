<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin']);

$utilisateurC = new UtilisateurC();

// ---- Activation / désactivation rapide ----
if (isset($_GET['statut_id'], $_GET['nouveau_statut']) && ctype_digit((string)$_GET['statut_id'])) {
    $cible = (int)$_GET['statut_id'];
    if ($cible === id_courant()) {
        flash_set('error', 'Vous ne pouvez pas désactiver votre propre compte.');
    } else {
        try {
            $utilisateurC->changerStatut($cible, $_GET['nouveau_statut'] === 'actif' ? 'actif' : 'inactif');
            flash_set('success', 'Statut du compte mis à jour.');
        } catch (Throwable $e) {
            flash_set('error', 'Modification impossible : ' . $e->getMessage());
        }
    }
    redirect('listUtilisateur.php');
}

$recherche = trim($_GET['recherche'] ?? '');
$role      = $_GET['role'] ?? 'tous';
$statut    = $_GET['statut'] ?? 'tous';
$orderBy   = $_GET['orderBy'] ?? 'nom';
$orderDir  = $_GET['orderDir'] ?? 'ASC';

$erreur = '';
try {
    $utilisateurs = $utilisateurC->filterUtilisateurs($recherche, $role, $statut, $orderBy, $orderDir);
} catch (Throwable $e) {
    $erreur = 'Erreur lors du chargement : ' . $e->getMessage();
    $utilisateurs = [];
}

$parRole = ['admin' => 0, 'gestionnaire' => 0, 'utilisateur' => 0];
foreach ($utilisateurs as $u) {
    if (isset($parRole[$u['role']])) $parRole[$u['role']]++;
}

$titrePage  = 'Utilisateurs';
$pageActive = 'utilisateurs';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <div class="fil-ariane"><a href="index.php">Tableau de bord</a> / Utilisateurs</div>
        <h2><i class="fas fa-users"></i> Gestion des comptes</h2>
        <p>Administrateurs bâtiments, gestionnaires de réservations et utilisateurs.</p>
    </div>
    <a href="addUtilisateur.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Nouveau compte</a>
</div>

<?php flash_afficher(); ?>
<?php if ($erreur !== ''): ?>
    <div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i><span><?= e($erreur) ?></span></div>
<?php endif; ?>

<div class="grid grid-4 mb-3">
    <div class="stat-card">
        <div class="stat-icon bleu"><i class="fas fa-users"></i></div>
        <div><div class="stat-value"><?= count($utilisateurs) ?></div><div class="stat-label">Comptes affichés</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon violet"><i class="fas fa-user-shield"></i></div>
        <div><div class="stat-value"><?= $parRole['admin'] ?></div><div class="stat-label">Administrateurs</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-user-gear"></i></div>
        <div><div class="stat-value"><?= $parRole['gestionnaire'] ?></div><div class="stat-label">Gestionnaires</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon vert"><i class="fas fa-user"></i></div>
        <div><div class="stat-value"><?= $parRole['utilisateur'] ?></div><div class="stat-label">Utilisateurs</div></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-sliders"></i> Recherche et filtres</h3></div>
    <div class="card-body">
        <form method="get" class="filtres-grid">
            <div class="form-group">
                <label for="recherche">Recherche</label>
                <input type="search" id="recherche" name="recherche" value="<?= e($recherche) ?>"
                       placeholder="Nom, prénom, email, département…">
            </div>
            <div class="form-group">
                <label for="role">Rôle</label>
                <select id="role" name="role">
                    <option value="tous">Tous les rôles</option>
                    <option value="admin"        <?= $role === 'admin' ? 'selected' : '' ?>>Administrateur bâtiments</option>
                    <option value="gestionnaire" <?= $role === 'gestionnaire' ? 'selected' : '' ?>>Gestionnaire de réservations</option>
                    <option value="utilisateur"  <?= $role === 'utilisateur' ? 'selected' : '' ?>>Utilisateur</option>
                </select>
            </div>
            <div class="form-group">
                <label for="statut">Statut</label>
                <select id="statut" name="statut">
                    <option value="tous">Tous</option>
                    <option value="actif"   <?= $statut === 'actif' ? 'selected' : '' ?>>Actif</option>
                    <option value="inactif" <?= $statut === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                </select>
            </div>
            <div class="filtres-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-magnifying-glass"></i> Filtrer</button>
                <a href="listUtilisateur.php" class="btn btn-light"><i class="fas fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Liste des comptes</h3>
        <span class="text-muted" style="font-size:.86rem;"><?= count($utilisateurs) ?> résultat(s)</span>
    </div>

    <?php if (!$utilisateurs): ?>
        <div class="vide"><i class="fas fa-user-slash"></i><h3>Aucun compte trouvé</h3><p>Ajustez vos filtres.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Utilisateur</th><th>Contact</th><th>Département</th><th>Rôle</th>
                        <th>Réservations</th><th>Statut</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($utilisateurs as $u): ?>
                    <tr>
                        <td>
                            <div class="cell-user">
                                <div class="avatar-mini"><?= e(mb_strtoupper(mb_substr($u['prenom'], 0, 1) . mb_substr($u['nom'], 0, 1))) ?></div>
                                <div>
                                    <div class="cell-titre">
                                        <?= e($u['prenom'] . ' ' . $u['nom']) ?>
                                        <?php if ((int)$u['id_utilisateur'] === id_courant()): ?>
                                            <span class="badge badge-info">vous</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="cell-sub">Inscrit le <?= e(fmt_date($u['date_creation'])) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?= e($u['email']) ?>
                            <div class="cell-sub"><?= e((string)$u['telephone']) ?></div>
                        </td>
                        <td><?= e((string)$u['departement']) ?></td>
                        <td>
                            <?php $classeRole = ['admin' => 'badge-violet', 'gestionnaire' => 'badge-warning', 'utilisateur' => 'badge-info']; ?>
                            <span class="badge <?= $classeRole[$u['role']] ?? 'badge-muted' ?>">
                                <?= e(libelle_role($u['role'])) ?>
                            </span>
                        </td>
                        <td>
                            <a href="listReservation.php?utilisateur=<?= (int)$u['id_utilisateur'] ?>">
                                <?= (int)$u['nb_reservations'] ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($u['statut'] === 'actif'): ?>
                                <span class="badge badge-success">Actif</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="td-actions">
                                <?php if ((int)$u['id_utilisateur'] !== id_courant()): ?>
                                    <?php if ($u['statut'] === 'actif'): ?>
                                        <a href="?statut_id=<?= (int)$u['id_utilisateur'] ?>&nouveau_statut=inactif"
                                           class="btn btn-warning btn-sm" title="Désactiver"
                                           onclick="return confirmerAction('Désactiver ce compte ? L\'utilisateur ne pourra plus se connecter.');">
                                            <i class="fas fa-user-slash"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="?statut_id=<?= (int)$u['id_utilisateur'] ?>&nouveau_statut=actif"
                                           class="btn btn-success btn-sm" title="Activer"
                                           onclick="return confirmerAction('Réactiver ce compte ?');">
                                            <i class="fas fa-user-check"></i>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <a href="updateUtilisateur.php?id=<?= (int)$u['id_utilisateur'] ?>"
                                   class="btn btn-light btn-sm" title="Modifier"><i class="fas fa-pen"></i></a>
                                <?php if ((int)$u['id_utilisateur'] !== id_courant()): ?>
                                    <a href="deleteUtilisateur.php?id=<?= (int)$u['id_utilisateur'] ?>"
                                       class="btn btn-danger btn-sm" title="Supprimer"
                                       onclick="return confirmerSuppression('Supprimer le compte de <?= e(addslashes($u['prenom'] . ' ' . $u['nom'])) ?> ? Ses <?= (int)$u['nb_reservations'] ?> réservation(s) seront supprimées.');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php endif; ?>
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
