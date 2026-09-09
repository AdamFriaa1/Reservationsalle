    </div><!-- /.page-wrapper -->

    <footer class="footer">
        <span>ReservaSalles — Projet Technologies Web 2A · ESPRIT 2025-2026</span>
        <span>PHP 8 · MVC · PDO · Template <a href="https://github.com/ColorlibHQ/gentelella" target="_blank" rel="noopener">Gentelella</a> (MIT)</span>
    </footer>
</main>

<script src="assets/js/validation.js"></script>
<script>
/* Bascule de la sidebar Gentelella en mobile (sans dépendre du JS de la template). */
(function () {
    var t = document.getElementById('sidebarToggle');
    var s = document.getElementById('sidebar');
    if (!t || !s) return;
    function toggle() {
        s.classList.toggle('open');
        document.body.classList.toggle('sidebar-open');
    }
    t.addEventListener('click', function (e) { e.stopPropagation(); toggle(); });
    document.addEventListener('click', function (e) {
        if (document.body.classList.contains('sidebar-open') && !s.contains(e.target)) {
            s.classList.remove('open');
            document.body.classList.remove('sidebar-open');
        }
    });
})();
</script>
</body>
</html>
