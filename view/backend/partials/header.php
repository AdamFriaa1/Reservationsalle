<?php
/**
 * En-tête du BackOffice — ReservaSalles 2
 * Coque « console d'administration » : rail latéral repliable + barre supérieure.
 * Variables attendues : $titrePage, $pageActive
 */
$titrePage  = $titrePage  ?? 'Administration';
$pageActive = $pageActive ?? '';
$moi        = utilisateur_courant();
$estAdmin   = role_courant() === 'admin';
$init       = mb_strtoupper(mb_substr($moi['prenom'], 0, 1) . mb_substr($moi['nom'], 0, 1));
$on         = fn(string $p): string => $pageActive === $p ? ' on' : '';

$nbEnAttente = 0;
try {
    $nbEnAttente = (int)config::getConnexion()
        ->query("SELECT COUNT(*) FROM reservation WHERE statut = 'en_attente'")
        ->fetchColumn();
} catch (Throwable $e) { $nbEnAttente = 0; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= e($titrePage) ?> · Administration ReservaSalles</title>

    <script>
        (function () {
            document.documentElement.classList.add('js');
            try {
                var t = localStorage.getItem('rs2-theme');
                if (t === 'dark' || t === 'light') document.documentElement.setAttribute('data-theme', t);
                var sombre = t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (sombre) document.documentElement.classList.add('theme-dark');
            } catch (e) {}
        })();
    </script>

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/app.css?v=10">
    <link rel="stylesheet" href="../../assets/css/shell.css?v=10">
    <link rel="stylesheet" href="../../assets/css/compat.css?v=10">
</head>
<body>

<a class="skip" href="#main">Aller au contenu</a>

<div class="admin">

    <aside class="rail" aria-label="Navigation de l'administration">
        <div class="rail-head">
            <a href="index.php" class="brand">
                <span class="brand-mark"><i class="fas fa-calendar-check"></i></span>
                <span>ReservaSalles<small>Back-office</small></span>
            </a>
        </div>

        <div class="rail-scroll">
            <div class="rail-group">
                <h5>Général</h5>
                <a class="<?= trim($on('dashboard')) ?>" href="index.php">
                    <i class="fas fa-gauge-high"></i><span>Tableau de bord</span>
                </a>
            </div>

            <?php if ($estAdmin): ?>
            <div class="rail-group">
                <h5>Administrateur bâtiments</h5>
                <a class="<?= trim($on('batiments')) ?>" href="listBatiment.php"><i class="fas fa-building"></i><span>Bâtiments</span></a>
                <a class="<?= trim($on('etages')) ?>" href="listEtage.php"><i class="fas fa-layer-group"></i><span>Étages</span></a>
                <a class="<?= trim($on('salles')) ?>" href="listSalle.php"><i class="fas fa-door-open"></i><span>Salles</span></a>
                <a class="<?= trim($on('statistiques')) ?>" href="statistiques.php"><i class="fas fa-chart-column"></i><span>Statistiques</span></a>
                <a class="<?= trim($on('rapport')) ?>" href="rapport.php"><i class="fas fa-file-lines"></i><span>Rapports</span></a>
            </div>
            <?php endif; ?>

            <div class="rail-group">
                <h5>Gestionnaire de réservations</h5>
                <a class="<?= trim($on('reservations')) ?>" href="listReservation.php">
                    <i class="fas fa-calendar-check"></i><span>Réservations</span>
                    <?php if ($nbEnAttente > 0): ?><span class="count"><?= $nbEnAttente ?></span><?php endif; ?>
                </a>
                <a class="<?= trim($on('reservation-manuelle')) ?>" href="addReservation.php"><i class="fas fa-square-plus"></i><span>Réservation manuelle</span></a>
                <a class="<?= trim($on('calendrier')) ?>" href="calendrier.php"><i class="fas fa-calendar-days"></i><span>Planning global</span></a>
                <a class="<?= trim($on('conflits')) ?>" href="conflits.php"><i class="fas fa-triangle-exclamation"></i><span>Conflits</span></a>
            </div>

            <?php if ($estAdmin): ?>
            <div class="rail-group">
                <h5>Comptes</h5>
                <a class="<?= trim($on('utilisateurs')) ?>" href="listUtilisateur.php"><i class="fas fa-users"></i><span>Utilisateurs</span></a>
            </div>
            <?php endif; ?>

            <div class="rail-group">
                <h5>Suivi</h5>
                <a class="<?= trim($on('notifications')) ?>" href="notifications.php"><i class="fas fa-envelope"></i><span>Notifications</span></a>
            </div>
        </div>

        <div class="rail-foot">
            <span class="avatar"><?= e($init) ?></span>
            <span class="who">
                <b><?= e($moi['prenom'] . ' ' . $moi['nom']) ?></b>
                <span><?= e(libelle_role($moi['role'])) ?></span>
            </span>
            <a class="out" href="../frontend/logout.php" title="Se déconnecter" aria-label="Se déconnecter">
                <i class="fas fa-right-from-bracket"></i>
            </a>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <button class="btn btn-ghost btn-icon rail-toggle" type="button" data-rail
                    aria-label="Afficher ou masquer le menu"><i class="fas fa-bars"></i></button>
            <button class="btn btn-ghost btn-icon hide-mobile" type="button" data-rail
                    aria-label="Replier le menu" title="Replier le menu"><i class="fas fa-bars-staggered"></i></button>

            <h1><?= e($titrePage) ?></h1>

            <div class="push">
                <button class="searchbox" type="button" data-cmdk>
                    <i class="fas fa-magnifying-glass"></i>
                    <span>Rechercher…</span>
                    <kbd>Ctrl</kbd><kbd>K</kbd>
                </button>
                <button class="theme-btn" type="button" aria-label="Changer de thème">
                    <i class="fas fa-sun i-sun"></i><i class="fas fa-moon i-moon"></i>
                </button>
                <a class="btn btn-sm" href="../frontend/index.php" title="Voir le site public">
                    <i class="fas fa-globe"></i> <span class="hide-mobile">Voir le site</span>
                </a>
            </div>
        </header>

        <main id="main" class="content" tabindex="-1">
