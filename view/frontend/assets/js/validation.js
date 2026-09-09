/* =====================================================================
   ReservaSalles — Contrôles de saisie côté client (JavaScript)
   Aucune validation n'est déléguée au HTML (pas de "required" natif) :
   tout est vérifié ici, puis à nouveau côté PHP.
   ===================================================================== */

const Valider = {

    // ------------------------------------------------------------------
    //  Règles élémentaires
    // ------------------------------------------------------------------
    requis(v) {
        return v.trim() !== '';
    },

    longueur(v, min, max) {
        const n = v.trim().length;
        return n >= min && n <= max;
    },

    email(v) {
        return /^[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}$/.test(v.trim());
    },

    telephone(v) {
        return /^[0-9 +\-]{8,20}$/.test(v.trim());
    },

    entier(v, min, max) {
        if (!/^-?\d+$/.test(v.trim())) return false;
        const n = parseInt(v, 10);
        return n >= min && n <= max;
    },

    motDePasse(v) {
        return v.length >= 6;
    },

    /* Le code d'une salle ou d'un bâtiment : lettres, chiffres et tirets. */
    code(v) {
        return /^[A-Za-z0-9\-_]{2,20}$/.test(v.trim());
    },

    /* Le texte ne doit pas être uniquement composé de chiffres. */
    texte(v, min, max) {
        const t = v.trim();
        return t.length >= min && t.length <= max && !/^\d+$/.test(t);
    },

    dateFuture(v) {
        const d = new Date(v);
        return !isNaN(d.getTime()) && d.getTime() > Date.now();
    },

    /* La fin doit être après le début. */
    apres(finValeur, debutValeur) {
        const d = new Date(debutValeur);
        const f = new Date(finValeur);
        return !isNaN(d.getTime()) && !isNaN(f.getTime()) && f.getTime() > d.getTime();
    },

    /* Les deux dates doivent tomber le même jour. */
    memeJour(a, b) {
        const d1 = new Date(a), d2 = new Date(b);
        if (isNaN(d1.getTime()) || isNaN(d2.getTime())) return false;
        return d1.toDateString() === d2.toDateString();
    },

    /* Durée en minutes entre deux heures « HH:MM » d'une même journée. */
    minutesEntreHeures(debutHHMM, finHHMM) {
        const re = /^([01]\d|2[0-3]):([0-5]\d)$/;
        const a = re.exec((debutHHMM || '').trim());
        const b = re.exec((finHHMM   || '').trim());
        if (!a || !b) return NaN;
        return (parseInt(b[1], 10) * 60 + parseInt(b[2], 10))
             - (parseInt(a[1], 10) * 60 + parseInt(a[2], 10));
    },

    /* Vrai si la durée entre deux heures « HH:MM » tient dans les bornes (minutes). */
    dureeCreneau(debutHHMM, finHHMM, min, max) {
        const d = this.minutesEntreHeures(debutHHMM, finHHMM);
        if (isNaN(d)) return false;
        return d >= min && d <= max;
    },

    /* Durée en minutes entre deux dates. */
    dureeMinutes(debut, fin) {
        return (new Date(fin) - new Date(debut)) / 60000;
    },

    // ------------------------------------------------------------------
    //  Affichage des messages
    // ------------------------------------------------------------------
    marquer(champ, message) {
        champ.classList.add('invalide');
        const zone = document.getElementById('err-' + champ.id);
        if (zone) {
            zone.innerHTML = '<i class="fas fa-circle-exclamation"></i> ' + message;
            zone.classList.add('visible');
        }
    },

    nettoyer(champ) {
        champ.classList.remove('invalide');
        const zone = document.getElementById('err-' + champ.id);
        if (zone) {
            zone.textContent = '';
            zone.classList.remove('visible');
        }
    },

    nettoyerTout(formulaire) {
        formulaire.querySelectorAll('.invalide').forEach(c => c.classList.remove('invalide'));
        formulaire.querySelectorAll('.erreur-champ').forEach(z => {
            z.textContent = '';
            z.classList.remove('visible');
        });
    },

    // ------------------------------------------------------------------
    //  Moteur générique
    //
    //  regles = { idDuChamp: [ {test: fn(valeur, formulaire), message: '...'} ] }
    // ------------------------------------------------------------------
    attacher(idFormulaire, regles) {
        const formulaire = document.getElementById(idFormulaire);
        if (!formulaire) return;

        const controlerChamp = (id) => {
            const champ = document.getElementById(id);
            if (!champ) return true;
            this.nettoyer(champ);
            for (const regle of regles[id]) {
                if (!regle.test(champ.value, formulaire)) {
                    this.marquer(champ, regle.message);
                    return false;
                }
            }
            return true;
        };

        // Contrôle à la sortie du champ, puis en direct une fois en erreur
        Object.keys(regles).forEach(id => {
            const champ = document.getElementById(id);
            if (!champ) return;
            champ.addEventListener('blur', () => controlerChamp(id));
            champ.addEventListener('input', () => {
                if (champ.classList.contains('invalide')) controlerChamp(id);
            });
            champ.addEventListener('change', () => {
                if (champ.classList.contains('invalide')) controlerChamp(id);
            });
        });

        formulaire.addEventListener('submit', (evt) => {
            let premierEnErreur = null;
            Object.keys(regles).forEach(id => {
                if (!controlerChamp(id) && premierEnErreur === null) {
                    premierEnErreur = document.getElementById(id);
                }
            });
            if (premierEnErreur !== null) {
                evt.preventDefault();
                premierEnErreur.scrollIntoView({ behavior: 'smooth', block: 'center' });
                premierEnErreur.focus();
            }
        });
    }
};

// ---------------------------------------------------------------------
//  Confirmation avant suppression
// ---------------------------------------------------------------------
function confirmerSuppression(message) {
    return confirm(message || 'Confirmez-vous la suppression ? Cette action est irréversible.');
}

// ---------------------------------------------------------------------
//  Menu mobile
// ---------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    const bouton  = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlayMobile');
    if (bouton && sidebar) {
        const basculer = () => {
            sidebar.classList.toggle('ouverte');
            if (overlay) overlay.classList.toggle('actif');
        };
        bouton.addEventListener('click', basculer);
        if (overlay) overlay.addEventListener('click', basculer);
    }

    // Masquer automatiquement les alertes après 6 secondes
    document.querySelectorAll('.alert-success').forEach(a => {
        setTimeout(() => {
            a.style.transition = 'opacity .4s ease';
            a.style.opacity = '0';
            setTimeout(() => a.remove(), 400);
        }, 6000);
    });
});
