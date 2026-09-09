<?php
/**
 * En-tête du BackOffice — intègre la template réelle GENTELELLA (ColorlibHQ, MIT,
 * sans Bootstrap/jQuery/framework), vendorisée dans /assets/gentelella/.
 * Le CSS de Gentelella habille le shell (sidebar, topbar, main) ; admin.css
 * habille les composants métier. Variables attendues : $titrePage, $pageActive
 */
$titrePage  = $titrePage  ?? 'Administration';
$pageActive = $pageActive ?? '';
$moi        = utilisateur_courant();
$estAdmin   = role_courant() === 'admin';

$nbEnAttente = 0;
try {
    $nbEnAttente = (int)config::getConnexion()
        ->query("SELECT COUNT(*) FROM reservation WHERE statut = 'en_attente'")
        ->fetchColumn();
} catch (Throwable $e) { $nbEnAttente = 0; }

$gtl = '../../assets/gentelella';               // template vendorisée
$init = mb_strtoupper(mb_substr($moi['prenom'], 0, 1) . mb_substr($moi['nom'], 0, 1));
// petit utilitaire local pour l'état actif
$act = fn(string $p): string => $pageActive === $p ? ' active' : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titrePage) ?> — Administration ReservaSalles</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <!-- Template réelle Gentelella (MIT) -->
    <link rel="stylesheet" href="<?= $gtl ?>/assets/main-v4-pXJJcGAu.css">
    <!-- Composants métier ReservaSalles -->
    <link rel="stylesheet" href="assets/css/admin.css?v=8">
</head>
<body data-shell="admin">

<a class="skip-link" href="#main-content">Aller au contenu</a>

<aside class="sidebar" id="sidebar" aria-label="Navigation principale">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="fas fa-calendar-check"></i></div>
        <div class="brand-name">ReservaSalles <small>Back-office</small></div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-group">
            <div class="nav-label">Général</div>
            <a class="nav-link<?= $act('dashboard') ?>" href="index.php">
                <i class="fas fa-gauge-high"></i><span class="nav-text">Tableau de bord</span>
            </a>
        </div>

        <?php if ($estAdmin): ?>
        <div class="nav-group">
            <div class="nav-label">Administrateur bâtiments</div>
            <a class="nav-link<?= $act('batiments') ?>" href="listBatiment.php"><i class="fas fa-building"></i><span class="nav-text">Bâtiments</span></a>
            <a class="nav-link<?= $act('etages') ?>" href="listEtage.php"><i class="fas fa-layer-group"></i><span class="nav-text">Étages</span></a>
            <a class="nav-link<?= $act('salles') ?>" href="listSalle.php"><i class="fas fa-door-open"></i><span class="nav-text">Salles</span></a>
            <a class="nav-link<?= $act('statistiques') ?>" href="statistiques.php"><i class="fas fa-chart-column"></i><span class="nav-text">Statistiques</span></a>
            <a class="nav-link<?= $act('rapport') ?>" href="rapport.php"><i class="fas fa-file-lines"></i><span class="nav-text">Rapports par période</span></a>
        </div>
        <?php endif; ?>

        <div class="nav-group">
            <div class="nav-label">Gestionnaire de réservations</div>
            <a class="nav-link<?= $act('reservations') ?>" href="listReservation.php">
                <i class="fas fa-calendar-check"></i><span class="nav-text">Réservations</span>
                <?php if ($nbEnAttente > 0): ?><span class="badge badge-red"><?= $nbEnAttente ?></span><?php endif; ?>
            </a>
            <a class="nav-link<?= $act('reservation-manuelle') ?>" href="addReservation.php"><i class="fas fa-square-plus"></i><span class="nav-text">Réservation manuelle</span></a>
            <a class="nav-link<?= $act('calendrier') ?>" href="calendrier.php"><i class="fas fa-calendar-days"></i><span class="nav-text">Planning global</span></a>
            <a class="nav-link<?= $act('conflits') ?>" href="conflits.php"><i class="fas fa-triangle-exclamation"></i><span class="nav-text">Conflits</span></a>
        </div>

        <?php if ($estAdmin): ?>
        <div class="nav-group">
            <div class="nav-label">Comptes</div>
            <a class="nav-link<?= $act('utilisateurs') ?>" href="listUtilisateur.php"><i class="fas fa-users"></i><span class="nav-text">Utilisateurs</span></a>
        </div>
        <?php endif; ?>

        <div class="nav-group">
            <div class="nav-label">Suivi</div>
            <a class="nav-link<?= $act('notifications') ?>" href="notifications.php"><i class="fas fa-envelope"></i><span class="nav-text">Notifications envoyées</span></a>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="avatar"><?= e($init) ?><span class="online"></span></div>
            <div class="sidebar-user-info">
                <div class="name"><?= e($moi['prenom'] . ' ' . $moi['nom']) ?></div>
                <div class="role"><?= e(libelle_role($moi['role'])) ?></div>
            </div>
        </div>
    </div>
</aside>

<header class="topbar">
    <div class="topbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Menu" aria-controls="sidebar"><i class="fas fa-bars"></i></button>
        <nav class="breadcrumb" aria-label="Fil d'Ariane">
            <a href="index.php">Accueil</a><span class="sep" aria-hidden="true">›</span><span class="current"><?= e($titrePage) ?></span>
        </nav>
    </div>
    <div class="topbar-right">
        <a class="tb-btn tb-docs" href="../frontend/index.php" title="Voir le site"><i class="fas fa-globe"></i> <span>Voir le site</span></a>
        <a class="tb-btn tb-docs tb-logout" href="../frontend/logout.php" title="Se déconnecter"><i class="fas fa-right-from-bracket"></i> <span>Déconnexion</span></a>
    </div>
</header>

<main id="main-content" class="main" tabindex="-1">
<div class="page-wrapper">
