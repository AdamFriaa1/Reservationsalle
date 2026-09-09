<?php
/**
 * En-tête du FrontOffice — intègre la template réelle GENTELELLA (ColorlibHQ, MIT,
 * sans framework), vendorisée dans /assets/gentelella/. Shell Gentelella + composants
 * métier (front.css). Variables attendues : $titrePage, $pageActive
 */
$titrePage  = $titrePage  ?? 'Réservation de salles';
$pageActive = $pageActive ?? '';
$moi        = utilisateur_courant();
$connecte   = est_connecte();
$gtl        = '../../assets/gentelella';
$act = fn(string $p): string => $pageActive === $p ? ' active' : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titrePage) ?> — ReservaSalles</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <!-- Template réelle Gentelella (MIT) -->
    <link rel="stylesheet" href="<?= $gtl ?>/assets/main-v4-pXJJcGAu.css">
    <!-- Composants métier ReservaSalles -->
    <link rel="stylesheet" href="assets/css/front.css?v=7">
</head>
<body data-shell="admin">

<a class="skip-link" href="#main-content">Aller au contenu</a>

<aside class="sidebar" id="sidebar" aria-label="Navigation principale">
    <a href="index.php" class="sidebar-brand">
        <div class="brand-icon"><i class="fas fa-calendar-check"></i></div>
        <div class="brand-name">ReservaSalles <small>Réservation de salles</small></div>
    </a>

    <nav class="sidebar-nav">
        <div class="nav-group">
            <div class="nav-label">Navigation</div>
            <a class="nav-link<?= $act('accueil') ?>" href="index.php"><i class="fas fa-house"></i><span class="nav-text">Accueil</span></a>
            <a class="nav-link<?= $act('salles') ?>" href="salles.php"><i class="fas fa-door-open"></i><span class="nav-text">Les salles</span></a>
            <a class="nav-link<?= $act('calendrier') ?>" href="calendrier.php"><i class="fas fa-calendar-days"></i><span class="nav-text">Calendrier</span></a>
        </div>

        <?php if ($connecte): ?>
        <div class="nav-group">
            <div class="nav-label">Mes réservations</div>
            <a class="nav-link<?= $act('reserver') ?>" href="reserver.php"><i class="fas fa-plus"></i><span class="nav-text">Réserver</span></a>
            <a class="nav-link<?= $act('mes-reservations') ?>" href="mesReservations.php"><i class="fas fa-clock-rotate-left"></i><span class="nav-text">Mes réservations</span></a>
        </div>
            <?php if (in_array($moi['role'], ['admin', 'gestionnaire'], true)): ?>
            <div class="nav-group">
                <div class="nav-label">Administration</div>
                <a class="nav-link" href="../backend/index.php"><i class="fas fa-gauge-high"></i><span class="nav-text">Back-office</span></a>
            </div>
            <?php endif; ?>
        <?php else: ?>
        <div class="nav-group">
            <div class="nav-label">Compte</div>
            <a class="nav-link<?= $act('login') ?>" href="login.php"><i class="fas fa-right-to-bracket"></i><span class="nav-text">Connexion</span></a>
            <a class="nav-link<?= $act('register') ?>" href="register.php"><i class="fas fa-user-plus"></i><span class="nav-text">Inscription</span></a>
        </div>
        <?php endif; ?>
    </nav>

    <?php if ($connecte): ?>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="avatar"><?= e(mb_strtoupper(mb_substr($moi['prenom'], 0, 1) . mb_substr($moi['nom'], 0, 1))) ?><span class="online"></span></div>
            <div class="sidebar-user-info">
                <div class="name"><?= e($moi['prenom'] . ' ' . $moi['nom']) ?></div>
                <div class="role"><?= e(libelle_role($moi['role'])) ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</aside>

<header class="topbar">
    <div class="topbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Menu" aria-controls="sidebar"><i class="fas fa-bars"></i></button>
        <nav class="breadcrumb" aria-label="Fil d'Ariane">
            <a href="index.php">Accueil</a><span class="sep" aria-hidden="true">›</span><span class="current"><?= e($titrePage) ?></span>
        </nav>
    </div>
    <div class="topbar-right">
        <?php if ($connecte): ?>
            <a class="tb-btn tb-docs tb-logout" href="logout.php" title="Se déconnecter"><i class="fas fa-right-from-bracket"></i> <span>Déconnexion</span></a>
        <?php else: ?>
            <a class="tb-btn tb-docs" href="login.php"><i class="fas fa-right-to-bracket"></i> <span>Connexion</span></a>
            <a class="btn btn-primary btn-sm" href="register.php"><i class="fas fa-user-plus"></i> Inscription</a>
        <?php endif; ?>
    </div>
</header>

<main id="main-content" class="main" tabindex="-1">
<div class="page-wrapper">
