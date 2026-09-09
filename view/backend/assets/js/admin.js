/* =====================================================================
   ReservaSalles — BackOffice : sidebar, modales, aides de saisie
   ===================================================================== */

document.addEventListener('DOMContentLoaded', () => {

    // ------------------------------ Sidebar ---------------------------
    const bouton  = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlayMobile');

    if (bouton && sidebar) {
        bouton.addEventListener('click', () => {
            sidebar.classList.toggle('ouverte');
            if (overlay) overlay.classList.toggle('actif');
        });
    }
    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('ouverte');
            overlay.classList.remove('actif');
        });
    }

    // ------------------------------ Modales ---------------------------
    document.querySelectorAll('[data-modale]').forEach(declencheur => {
        declencheur.addEventListener('click', () => {
            const modale = document.getElementById(declencheur.dataset.modale);
            if (!modale) return;
            // Recopie les valeurs data-* du bouton dans les champs de la modale
            Object.keys(declencheur.dataset).forEach(cle => {
                if (cle === 'modale') return;
                const cible = modale.querySelector('[data-champ="' + cle + '"]');
                if (cible) {
                    if ('value' in cible) cible.value = declencheur.dataset[cle];
                    else cible.textContent = declencheur.dataset[cle];
                }
            });
            modale.classList.add('ouverte');
        });
    });

    document.querySelectorAll('.fermer-modale, [data-fermer]').forEach(b => {
        b.addEventListener('click', () => {
            const modale = b.closest('.modale');
            if (modale) modale.classList.remove('ouverte');
        });
    });

    document.querySelectorAll('.modale').forEach(m => {
        m.addEventListener('click', evt => {
            if (evt.target === m) m.classList.remove('ouverte');
        });
    });

    document.addEventListener('keydown', evt => {
        if (evt.key === 'Escape') {
            document.querySelectorAll('.modale.ouverte').forEach(m => m.classList.remove('ouverte'));
        }
    });

    // --------------------- Cases à cocher « chips » -------------------
    document.querySelectorAll('.chip input[type=checkbox]').forEach(c => {
        const majSurbrillance = () => c.closest('.chip').classList.toggle('coche', c.checked);
        majSurbrillance();
        c.addEventListener('change', majSurbrillance);
    });

    // ------------------ Disparition des alertes de succès -------------
    document.querySelectorAll('.alert-success').forEach(a => {
        setTimeout(() => {
            a.style.transition = 'opacity .4s ease';
            a.style.opacity = '0';
            setTimeout(() => a.remove(), 400);
        }, 6000);
    });

    // ------------------ Animation des barres de progression -----------
    document.querySelectorAll('.barre-remplie[data-largeur]').forEach(b => {
        setTimeout(() => { b.style.width = b.dataset.largeur + '%'; }, 60);
    });
});

/* Confirmation avant une action destructive. */
function confirmerAction(message) {
    return confirm(message || 'Confirmez-vous cette action ?');
}
