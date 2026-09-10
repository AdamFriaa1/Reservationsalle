<?php
/**
 * Pied de page du BackOffice — ReservaSalles 2
 * Ferme la coque ouverte par partials/header.php et charge les scripts.
 */
$estAdmin = $estAdmin ?? (role_courant() === 'admin');
?>
        </main>

        <footer style="padding: var(--s-5) var(--s-7) var(--s-8)">
            <div class="rule mb-4"></div>
            <div class="row-between wrapf t-sm muted">
                <span>ReservaSalles — Back-office</span>
                <span><?= e(fmt_date(date('Y-m-d'))) ?></span>
            </div>
        </footer>
    </div><!-- /.main -->
</div><!-- /.admin -->

<script>
/* Entrées de la palette de commandes (Ctrl+K) pour le back-office. */
window.RS_COMMANDES = [
    { groupe: 'Général', label: 'Tableau de bord',              url: 'index.php',            icone: 'fa-gauge-high' },
<?php if ($estAdmin): ?>
    { groupe: 'Bâtiments', label: 'Liste des bâtiments',        url: 'listBatiment.php',     icone: 'fa-building' },
    { groupe: 'Bâtiments', label: 'Nouveau bâtiment',           url: 'addBatiment.php',      icone: 'fa-plus' },
    { groupe: 'Bâtiments', label: 'Liste des étages',           url: 'listEtage.php',        icone: 'fa-layer-group' },
    { groupe: 'Bâtiments', label: 'Nouvel étage',               url: 'addEtage.php',         icone: 'fa-plus' },
    { groupe: 'Salles', label: 'Liste des salles',              url: 'listSalle.php',        icone: 'fa-door-open' },
    { groupe: 'Salles', label: 'Nouvelle salle',                url: 'addSalle.php',         icone: 'fa-plus' },
    { groupe: 'Analyse', label: 'Statistiques d\'utilisation',  url: 'statistiques.php',     icone: 'fa-chart-column' },
    { groupe: 'Analyse', label: 'Rapports par période',         url: 'rapport.php',          icone: 'fa-file-lines' },
    { groupe: 'Comptes', label: 'Liste des utilisateurs',       url: 'listUtilisateur.php',  icone: 'fa-users' },
    { groupe: 'Comptes', label: 'Nouvel utilisateur',           url: 'addUtilisateur.php',   icone: 'fa-user-plus' },
<?php endif; ?>
    { groupe: 'Réservations', label: 'Toutes les réservations',   url: 'listReservation.php',                   icone: 'fa-calendar-check' },
    { groupe: 'Réservations', label: 'En attente de validation',  url: 'listReservation.php?statut=en_attente', icone: 'fa-hourglass-half' },
    { groupe: 'Réservations', label: 'Réservation manuelle',      url: 'addReservation.php',                    icone: 'fa-square-plus' },
    { groupe: 'Réservations', label: 'Planning global',           url: 'calendrier.php',                        icone: 'fa-calendar-days' },
    { groupe: 'Réservations', label: 'Conflits de réservation',   url: 'conflits.php',                          icone: 'fa-triangle-exclamation' },
    { groupe: 'Suivi', label: 'Notifications envoyées',           url: 'notifications.php',                     icone: 'fa-envelope' },
    { groupe: 'Autre', label: 'Voir le site public',             url: '../frontend/index.php',                 icone: 'fa-globe' },
    { groupe: 'Autre', label: 'Se déconnecter',                   url: '../frontend/logout.php',                icone: 'fa-right-from-bracket' }
];
</script>
<script src="../../assets/js/valider.js?v=10"></script>
<script src="../../assets/js/app.js?v=10"></script>
</body>
</html>
