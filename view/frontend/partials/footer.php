<?php
/**
 * Pied de page du FrontOffice — ReservaSalles 2
 * Ferme la coque ouverte par partials/header.php et charge les scripts.
 */
$pleineLargeur = $pleineLargeur ?? false;
$connecte      = $connecte ?? est_connecte();
?>
<?php if (!$pleineLargeur): ?>
</div><!-- /.wrap -->
<?php endif; ?>
</main>

<footer class="site-foot">
    <div class="wrap">
        <div class="foot-base" style="margin-top:0;padding-top:0;border-top:0">
            <a href="index.php" class="brand">
                <span class="brand-mark"><i class="fas fa-calendar-check"></i></span>
                <span>ReservaSalles<small>Espaces &amp; réunions</small></span>
            </a>
            <nav class="row g-5 wrapf t-sm">
                <a href="salles.php">Les salles</a>
                <a href="calendrier.php">Disponibilités</a>
                <?php if ($connecte): ?>
                    <a href="reserver.php">Réserver</a>
                    <a href="mesReservations.php">Mes réservations</a>
                <?php else: ?>
                    <a href="login.php">Connexion</a>
                    <a href="register.php">Créer un compte</a>
                <?php endif; ?>
            </nav>
        </div>
    </div>
</footer>

<script>
/* Entrées de la palette de commandes (Ctrl+K) pour le front-office. */
window.RS_COMMANDES = [
    { groupe: 'Navigation', label: 'Accueil',            url: 'index.php',           icone: 'fa-house' },
    { groupe: 'Navigation', label: 'Les salles',         url: 'salles.php',          icone: 'fa-door-open' },
    { groupe: 'Navigation', label: 'Disponibilités',     url: 'calendrier.php',      icone: 'fa-calendar-days' },
<?php if ($connecte): ?>
    { groupe: 'Réservation', label: 'Nouvelle réservation', url: 'reserver.php',     icone: 'fa-calendar-plus' },
    { groupe: 'Réservation', label: 'Mes réservations',     url: 'mesReservations.php', icone: 'fa-clock-rotate-left' },
    { groupe: 'Compte', label: 'Se déconnecter',            url: 'logout.php',       icone: 'fa-right-from-bracket' },
<?php else: ?>
    { groupe: 'Compte', label: 'Connexion',              url: 'login.php',           icone: 'fa-right-to-bracket' },
    { groupe: 'Compte', label: 'Créer un compte',        url: 'register.php',        icone: 'fa-user-plus' },
<?php endif; ?>
<?php
// Accès direct aux salles disponibles, s'il y en a.
try {
    foreach ((new SalleC())->getSallesDisponibles() as $s) {
        printf("    { groupe: 'Salles', label: %s, url: %s, icone: 'fa-door-closed', tail: %s },\n",
            json_encode($s['nom'] . ' — ' . $s['code_salle'], JSON_UNESCAPED_UNICODE),
            json_encode('calendrier.php?salle=' . (int)$s['id_salle']),
            json_encode((int)$s['capacite'] . ' places', JSON_UNESCAPED_UNICODE));
    }
} catch (Throwable $e) { /* la palette reste utilisable sans les salles */ }
?>
];
</script>
<script src="../../assets/js/valider.js?v=10"></script>
<script src="../../assets/js/app.js?v=10"></script>
</body>
</html>
