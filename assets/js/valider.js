/* =====================================================================
   ReservaSalles 2 — Contrôles de saisie côté client
   ---------------------------------------------------------------------
   Aucune validation n'est déléguée au HTML : pas d'attribut « required »,
   pas de « pattern ». Tout est vérifié ici, puis à nouveau côté PHP.

   Utilisation :
     Valider.attacher('idDuFormulaire', {
         nomDuChamp: [ { test: (v, form) => booleen, message: '…' }, … ]
     });

   Comportement : contrôle à la sortie du champ, correction en direct dès
   que l'utilisateur retape, et blocage de l'envoi avec défilement vers
   la première erreur.
   ===================================================================== */
'use strict';

const Valider = {

    /* ------------------------------------------------------------------
       Règles élémentaires
       ------------------------------------------------------------------ */
    requis(v) { return String(v).trim() !== ''; },

    longueur(v, min, max) {
        const n = String(v).trim().length;
        return n >= min && n <= max;
    },

    /* Texte : longueur correcte et pas uniquement des chiffres */
    texte(v, min, max) {
        const s = String(v).trim();
        return s.length >= min && s.length <= max && !/^\d+$/.test(s);
    },

    email(v) { return /^[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}$/.test(String(v).trim()); },

    telephone(v) { return /^[0-9 +\-]{8,20}$/.test(String(v).trim()); },

    entier(v, min = -Infinity, max = Infinity) {
        const s = String(v).trim();
        if (!/^-?\d+$/.test(s)) return false;
        const n = parseInt(s, 10);
        return n >= min && n <= max;
    },

    /* Code d'une salle ou d'un bâtiment : lettres, chiffres, tirets */
    code(v) { return /^[A-Za-z0-9\-_]{2,20}$/.test(String(v).trim()); },

    motDePasse(v) { return String(v).length >= 6; },

    /* Les deux mots de passe saisis concordent */
    identique(v, autre) { return String(v) === String(autre); },

    /* ------------------------------------------------------------------
       Règles de date et d'heure
       ------------------------------------------------------------------ */
    /* Une date « AAAA-MM-JJ » qui n'est pas dans le passé */
    jourNonPasse(v) {
        const s = String(v).trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) return false;
        const aujourdhui = new Date();
        const iso = aujourdhui.getFullYear() + '-'
                  + String(aujourdhui.getMonth() + 1).padStart(2, '0') + '-'
                  + String(aujourdhui.getDate()).padStart(2, '0');
        return s >= iso;
    },

    dateFuture(v) {
        const d = new Date(v);
        return !isNaN(d.getTime()) && d.getTime() > Date.now();
    },

    apres(fin, debut) {
        const d = new Date(debut), f = new Date(fin);
        return !isNaN(d.getTime()) && !isNaN(f.getTime()) && f > d;
    },

    memeJour(a, b) {
        const d1 = new Date(a), d2 = new Date(b);
        if (isNaN(d1.getTime()) || isNaN(d2.getTime())) return false;
        return d1.toDateString() === d2.toDateString();
    },

    /* Minutes écoulées entre deux heures « HH:MM » d'une même journée */
    minutesEntreHeures(debut, fin) {
        const re = /^([01]\d|2[0-3]):([0-5]\d)$/;
        const a = re.exec(String(debut).trim());
        const b = re.exec(String(fin).trim());
        if (!a || !b) return NaN;
        return (parseInt(b[1], 10) * 60 + parseInt(b[2], 10))
             - (parseInt(a[1], 10) * 60 + parseInt(a[2], 10));
    },

    /* La durée entre deux heures tient dans les bornes (en minutes) */
    dureeCreneau(debut, fin, min, max) {
        const d = this.minutesEntreHeures(debut, fin);
        return !isNaN(d) && d >= min && d <= max;
    },

    /* Une heure « HH:MM » comprise dans la plage d'ouverture */
    dansPlage(v, ouverture, fermeture) {
        const s = String(v).trim();
        if (!/^([01]\d|2[0-3]):([0-5]\d)$/.test(s)) return false;
        return s >= ouverture && s <= fermeture;
    },

    /* Minutes entre deux dates complètes (« AAAA-MM-JJTHH:MM ») */
    dureeMinutes(debut, fin) {
        return (new Date(fin) - new Date(debut)) / 60000;
    },

    /* ------------------------------------------------------------------
       Affichage des messages
       ------------------------------------------------------------------ */
    marquer(champ, message) {
        const bloc = champ.closest('.field') || champ.parentElement;
        bloc.classList.add('bad');
        const zone = document.getElementById('err-' + champ.name) || bloc.querySelector('.err');
        if (zone) {
            zone.innerHTML = '<i class="fas fa-circle-exclamation"></i>' + message;
            zone.classList.add('on');
        }
        champ.setAttribute('aria-invalid', 'true');
    },

    nettoyer(champ) {
        const bloc = champ.closest('.field') || champ.parentElement;
        bloc.classList.remove('bad');
        const zone = document.getElementById('err-' + champ.name) || bloc.querySelector('.err');
        if (zone) { zone.textContent = ''; zone.classList.remove('on'); }
        champ.removeAttribute('aria-invalid');
    },

    /* Efface tous les messages d'un formulaire */
    nettoyerTout(formulaire) {
        if (!formulaire) return;
        formulaire.querySelectorAll('[name]').forEach(c => this.nettoyer(c));
    },

    /* ------------------------------------------------------------------
       Contrôle d'un champ isolé
       ------------------------------------------------------------------ */
    controler(champ, regles, formulaire) {
        for (const regle of regles) {
            let ok;
            try { ok = regle.test(champ.value, formulaire); }
            catch (e) { ok = true; }              // une règle cassée ne bloque pas la saisie
            if (!ok) { this.marquer(champ, regle.message); return false; }
        }
        this.nettoyer(champ);
        return true;
    },

    /* ------------------------------------------------------------------
       Branchement sur un formulaire
       ------------------------------------------------------------------ */
    attacher(idFormulaire, regles) {
        const form = document.getElementById(idFormulaire);
        if (!form) return;

        const champs = {};
        Object.keys(regles).forEach(nom => {
            const champ = form.querySelector('[name="' + nom + '"]');
            if (!champ) return;
            champs[nom] = champ;

            // Contrôle à la sortie du champ
            champ.addEventListener('blur', () => {
                champ.dataset.touche = '1';
                this.controler(champ, regles[nom], form);
            });

            // Correction en direct, seulement après un premier contrôle
            const enDirect = () => {
                if (champ.dataset.touche) this.controler(champ, regles[nom], form);
            };
            champ.addEventListener('input', enDirect);
            champ.addEventListener('change', enDirect);
        });

        form.addEventListener('submit', (e) => {
            let premierFautif = null;

            Object.keys(champs).forEach(nom => {
                const champ = champs[nom];
                champ.dataset.touche = '1';
                if (!this.controler(champ, regles[nom], form) && !premierFautif) {
                    premierFautif = champ;
                }
            });

            if (premierFautif) {
                e.preventDefault();
                premierFautif.scrollIntoView({ block: 'center', behavior: 'smooth' });
                premierFautif.focus({ preventScroll: true });
                if (window.RS && RS.toast) {
                    RS.toast('Le formulaire contient des erreurs. Corrigez les champs signalés.', 'bad');
                }
                return;
            }

            // Évite le double envoi pendant le traitement serveur
            const envoi = form.querySelector('button[type=submit]');
            if (envoi) {
                envoi.disabled = true;
                envoi.dataset.libelle = envoi.innerHTML;
                envoi.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Envoi…';
                // Réactive si la navigation est annulée (retour arrière navigateur)
                setTimeout(() => {
                    if (envoi.dataset.libelle) { envoi.disabled = false; envoi.innerHTML = envoi.dataset.libelle; }
                }, 8000);
            }
        });
    }
};
