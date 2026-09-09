/* =====================================================================
   ReservaSalles — Calendrier interactif
   Charge les disponibilités d'une salle en AJAX et permet de choisir
   un créneau de début et de fin en cliquant.
   ===================================================================== */

const Calendrier = {

    salleId: null,
    dateChoisie: null,
    creneauDebut: null,
    creneauFin: null,

    /* ------------------------------------------------------------------
       Initialisation : appelée par la page qui utilise le calendrier.
       ------------------------------------------------------------------ */
    init(options) {
        this.salleId = options.salleId || null;
        this.urlAjax = options.urlAjax || 'ajax/disponibilites.php';
        this.zoneCreneaux = document.getElementById(options.zoneCreneaux || 'zoneCreneaux');
        this.champDebut   = document.getElementById(options.champDebut || 'date_debut');
        this.champFin     = document.getElementById(options.champFin || 'date_fin');
        this.champDate    = document.getElementById(options.champDate || 'date_jour');

        this.brancherJours();
        this.brancherSelecteurSalle(options.selecteurSalle);
    },

    /* Clic sur une case du calendrier mensuel. */
    brancherJours() {
        document.querySelectorAll('.cal-jour[data-date]').forEach(jour => {
            // Les journées vides ou déjà passées ne sont pas sélectionnables.
            if (jour.classList.contains('vide') || jour.classList.contains('passe')) {
                jour.setAttribute('aria-disabled', 'true');
                return;
            }
            jour.addEventListener('click', () => {
                document.querySelectorAll('.cal-jour.selection').forEach(j => j.classList.remove('selection'));
                jour.classList.add('selection');
                this.dateChoisie = jour.dataset.date;
                if (this.champDate) this.champDate.value = this.dateChoisie;
                this.chargerCreneaux();
            });
        });
    },

    brancherSelecteurSalle(idSelecteur) {
        if (!idSelecteur) return;
        const select = document.getElementById(idSelecteur);
        if (!select) return;
        select.addEventListener('change', () => {
            this.salleId = select.value;
            this.creneauDebut = null;
            this.creneauFin = null;
            if (this.dateChoisie) this.chargerCreneaux();
        });
    },

    /* ------------------------------------------------------------------
       Chargement AJAX des créneaux d'une journée.
       ------------------------------------------------------------------ */
    async chargerCreneaux() {
        if (!this.zoneCreneaux) return;

        if (!this.salleId || !this.dateChoisie) {
            this.zoneCreneaux.innerHTML =
                '<p class="text-muted">Choisissez une salle et une date pour voir les créneaux.</p>';
            return;
        }

        this.zoneCreneaux.innerHTML =
            '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement des créneaux…</p>';

        try {
            const url = `${this.urlAjax}?salle=${encodeURIComponent(this.salleId)}&date=${encodeURIComponent(this.dateChoisie)}`;
            const reponse = await fetch(url);
            if (!reponse.ok) throw new Error('Réponse HTTP ' + reponse.status);
            const donnees = await reponse.json();

            if (donnees.erreur) {
                this.zoneCreneaux.innerHTML =
                    '<div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i><span>'
                    + donnees.erreur + '</span></div>';
                return;
            }
            this.afficherCreneaux(donnees.creneaux || []);
        } catch (e) {
            this.zoneCreneaux.innerHTML =
                '<div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i>'
                + '<span>Impossible de charger les créneaux : ' + e.message + '</span></div>';
        }
    },

    afficherCreneaux(creneaux) {
        if (creneaux.length === 0) {
            this.zoneCreneaux.innerHTML =
                '<p class="text-muted">Aucun créneau disponible pour cette journée.</p>';
            return;
        }

        let html = '<div class="creneaux">';
        creneaux.forEach(c => {
            let classe = 'creneau ';
            let info   = '';
            if (c.passe)        { classe += 'passe'; }
            else if (c.occupe)  { classe += 'occupe'; info = '<small>Occupé</small>'; }
            else                { classe += 'libre'; info = '<small>Libre</small>'; }

            const attributs = (!c.passe && !c.occupe)
                ? ` data-debut="${c.debut}" data-fin="${c.fin}"` : '';
            html += `<div class="${classe}"${attributs}>${c.debut}${info}</div>`;
        });
        html += '</div>';
        html += '<p class="aide mt-3"><i class="fas fa-circle-info"></i> '
             + 'Cliquez sur un créneau de début, puis sur un créneau de fin.</p>';

        this.zoneCreneaux.innerHTML = html;
        this.brancherCreneaux();
    },

    brancherCreneaux() {
        this.zoneCreneaux.querySelectorAll('.creneau.libre').forEach(c => {
            c.addEventListener('click', () => this.choisirCreneau(c));
        });
    },

    /* Premier clic = début, second clic = fin. */
    choisirCreneau(element) {
        const heure = element.dataset.debut;

        if (this.creneauDebut === null || this.creneauFin !== null) {
            this.zoneCreneaux.querySelectorAll('.choisi').forEach(c => c.classList.remove('choisi'));
            this.creneauDebut = heure;
            this.creneauFin   = null;
            element.classList.add('choisi');
        } else {
            const fin = element.dataset.fin;
            if (fin <= this.creneauDebut) {
                this.zoneCreneaux.querySelectorAll('.choisi').forEach(c => c.classList.remove('choisi'));
                this.creneauDebut = heure;
                element.classList.add('choisi');
                return;
            }
            this.creneauFin = fin;
            this.marquerPlage();
        }
        this.reporterDansFormulaire();
    },

    /* Colore tous les créneaux compris dans la plage sélectionnée. */
    marquerPlage() {
        this.zoneCreneaux.querySelectorAll('.creneau.libre').forEach(c => {
            const d = c.dataset.debut;
            if (d >= this.creneauDebut && d < this.creneauFin) c.classList.add('choisi');
            else c.classList.remove('choisi');
        });
    },

    reporterDansFormulaire() {
        if (!this.champDebut || !this.champFin || !this.dateChoisie) return;
        if (this.creneauDebut) {
            this.champDebut.value = this.dateChoisie + 'T' + this.creneauDebut;
        }
        if (this.creneauFin) {
            this.champFin.value = this.dateChoisie + 'T' + this.creneauFin;
        }
        // Prévenir les validateurs branchés sur ces champs
        this.champDebut.dispatchEvent(new Event('change'));
        this.champFin.dispatchEvent(new Event('change'));
    }
};
