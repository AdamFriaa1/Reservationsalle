<?php
/**
 * En-tête du FrontOffice — ReservaSalles 2
 * Coque « site produit » : barre supérieure glacée, pas de rail latéral.
 * Variables attendues : $titrePage, $pageActive
 * Optionnel : $pleineLargeur = true  (la page gère elle-même ses .wrap)
 */
$titrePage     = $titrePage  ?? 'Réservation de salles';
$pageActive    = $pageActive ?? '';
$pleineLargeur = $pleineLargeur ?? false;
$moi           = utilisateur_courant();
$connecte      = est_connecte();
$on            = fn(string $p): string => $pageActive === $p ? ' class="on"' : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ReservaSalles — réservez une salle de réunion en quelques secondes : disponibilités en temps réel, validation par un gestionnaire, notifications par email.">
    <meta name="theme-color" content="#2454ff">
    <title><?= e($titrePage) ?> · ReservaSalles</title>

    <script>
        /* Posé avant le rendu : évite tout clignotement de thème. */
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

<header class="site-nav" id="siteNav">
    <div class="wrap">
        <a href="index.php" class="brand">
            <span class="brand-mark"><i class="fas fa-calendar-check"></i></span>
            <span>ReservaSalles<small>Espaces &amp; réunions</small></span>
        </a>

        <nav class="site-links" aria-label="Navigation principale">
            <a href="index.php"<?= $on('accueil') ?>>Accueil</a>
            <a href="salles.php"<?= $on('salles') ?>>Les salles</a>
            <a href="calendrier.php"<?= $on('calendrier') ?>>Disponibilités</a>
            <?php if ($connecte): ?>
                <a href="mesReservations.php"<?= $on('mes-reservations') ?>>Mes réservations</a>
            <?php endif; ?>
        </nav>

        <div class="nav-tools">
            <button class="btn btn-ghost btn-icon" type="button" data-cmdk
                    aria-label="Recherche rapide (Ctrl+K)" title="Recherche rapide — Ctrl+K">
                <i class="fas fa-magnifying-glass"></i>
            </button>
            <button class="theme-btn" type="button" aria-label="Changer de thème">
                <i class="fas fa-sun i-sun"></i><i class="fas fa-moon i-moon"></i>
            </button>

            <?php if ($connecte): ?>
                <a href="reserver.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Réserver
                </a>
                <?php if (in_array($moi['role'], ['admin', 'gestionnaire'], true)): ?>
                    <a href="../backend/index.php" class="btn btn-sm" title="Back-office">
                        <i class="fas fa-gauge-high"></i>
                    </a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-ghost btn-icon" title="Se déconnecter"
                   aria-label="Se déconnecter"><i class="fas fa-right-from-bracket"></i></a>
                <span class="avatar" title="<?= e($moi['prenom'] . ' ' . $moi['nom'] . ' — ' . libelle_role($moi['role'])) ?>">
                    <?= e(mb_strtoupper(mb_substr($moi['prenom'], 0, 1) . mb_substr($moi['nom'], 0, 1))) ?>
                </span>
            <?php else: ?>
                <a href="login.php" class="btn btn-ghost btn-sm">Connexion</a>
                <a href="register.php" class="btn btn-primary btn-sm">Créer un compte</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main id="main" tabindex="-1">
<?php if (!$pleineLargeur): ?>
<div class="wrap" style="padding-block: var(--s-7) var(--s-8)">
<?php endif; ?>
