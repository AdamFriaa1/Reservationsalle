/* =====================================================================
   ReservaSalles 2 — Calendrier interactif
   ---------------------------------------------------------------------
   La grille du mois est produite par PHP ; ce script s'occupe de :
     - la sélection d'une journée (les jours passés sont inertes) ;
     - le chargement AJAX des créneaux de la journée ;
     - le choix d'une plage : un clic pour le début, un clic pour la fin ;
     - le récapitulatif et le report vers le formulaire de réservation.
   ===================================================================== */
'use strict';

const Calendrier = {

    salleId: null,
    jour: null,
    debut: null,        // index du créneau de début
    fin: null,          // index du créneau de fin (inclus)
    creneaux: [],
    pas: 30,            // minutes par créneau

    init(options) {
        this.url        = options.urlAjax || 'ajax/disponibilites.php';
        this.salleId    = options.salleId || null;
        this.grille     = document.getElementById(options.grille || 'calGrille');
        this.zone       = document.getElementById(options.zone || 'calSlots');
        this.titre      = document.getElementById(options.titre || 'calTitre');
        this.sousTitre  = document.getElementById(options.sousTitre || 'calSousTitre');
        this.urlReserver= options.urlReserver || 'reserver.php';
        this.connecte   = options.connecte !== false;

        this.brancherJours();
        this.brancherSelecteur(options.selecteurSalle);

        // Ouvre d'emblée la journée demandée (lien direct) ou aujourd'hui
        const depart = options.jour
            || (this.grille && this.grille.querySelector('.day.today:not(.past)')?.dataset.date);
        if (depart && this.salleId) {
            const cell = this.grille?.querySelector('.day[data-date="' + depart + '"]:not(.past)');
            if (cell) this.choisirJour(depart, cell);
        }
    },

    /* ---------------------------------------------------- Grille du mois */
    brancherJours() {
        if (!this.grille) return;
        this.grille.querySelectorAll('.day[data-date]').forEach(cell => {
            if (cell.classList.contains('past') || cell.classList.contains('void')) {
                cell.setAttribute('aria-disabled', 'true');
                cell.disabled = true;
                return;
            }
            cell.addEventListener('click', () => this.choisirJour(cell.dataset.date, cell));
        });
    },

    brancherSelecteur(id) {
        if (!id) return;
        const sel = document.getElementById(id);
        if (!sel) return;
        sel.addEventListener('change', () => {
            this.salleId = sel.value || null;
            this.debut = this.fin = null;
            if (this.jour) this.charger();
            else this.vide();
        });
    },

    choisirJour(date, cell) {
        this.jour  = date;
        this.debut = this.fin = null;

        this.grille.querySelectorAll('.day.pick').forEach(d => d.classList.remove('pick'));
        cell.classList.add('pick');

        if (this.titre) {
            const d = this.dateLocale(date);
            const jours = ['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'];
            const mois  = ['janvier','février','mars','avril','mai','juin','juillet','août',
                           'septembre','octobre','novembre','décembre'];
            this.titre.textContent = jours[d.getDay()] + ' ' + d.getDate() + ' ' + mois[d.getMonth()];
        }
        this.charger();
    },

    /* Parse « AAAA-MM-JJ » en date locale (évite le décalage UTC) */
    dateLocale(iso) {
        const [a, m, j] = iso.split('-').map(Number);
        return new Date(a, m - 1, j);
    },

    /* ---------------------------------------------------- Chargement AJAX */
    vide(message) {
        if (!this.zone) return;
        this.zone.innerHTML =
            '<div class="empty" style="padding:38px 12px">'
          + '<span class="empty-icon"><i class="fas fa-calendar-day"></i></span>'
          + '<p class="muted t-sm">' + (message || 'Choisissez une salle puis une journée pour voir les créneaux.') + '</p>'
          + '</div>';
    },

    squelette() {
        if (!this.zone) return;
        let html = '<div class="slots">';
        for (let i = 0; i < 12; i++) html += '<div class="skel" style="height:38px"></div>';
        this.zone.innerHTML = html + '</div>';
    },

    async charger() {
        if (!this.zone) return;
        if (!this.salleId || !this.jour) { this.vide(); return; }

        this.squelette();
        try {
            const url = this.url + '?salle=' + encodeURIComponent(this.salleId)
                      + '&date=' + encodeURIComponent(this.jour);
            const rep = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!rep.ok) throw new Error('Réponse HTTP ' + rep.status);
            const data = await rep.json();

            if (data.erreur) {
                this.zone.innerHTML = '<div class="alert alert-bad" style="margin:0">'
                    + '<i class="fas fa-circle-exclamation"></i><span>' + data.erreur + '</span></div>';
                return;
            }
            this.creneaux = data.creneaux || [];
            if (this.sousTitre && data.salle) {
                this.sousTitre.textContent = data.salle.nom + ' · ' + data.salle.capacite + ' places'
                    + (data.salle.horaires ? ' · ' + data.salle.horaires : '');
            }
            this.dessiner();
        } catch (e) {
            this.zone.innerHTML = '<div class="alert alert-bad" style="margin:0">'
                + '<i class="fas fa-circle-exclamation"></i><span>Impossible de charger les créneaux : '
                + e.message + '</span></div>';
        }
    },

    /* ---------------------------------------------------- Rendu des créneaux */
    dessiner() {
        if (!this.creneaux.length) {
            this.vide('Aucun créneau sur cette journée (la salle est peut-être fermée).');
            return;
        }
        const bas = this.debut === null ? null : Math.min(this.debut, this.fin ?? this.debut);
        const haut = this.debut === null ? null : Math.max(this.debut, this.fin ?? this.debut);

        let html = '<div class="slots" role="group" aria-label="Créneaux de la journée">';
        this.creneaux.forEach((c, i) => {
            let cls = 'slot';
            if (c.passe)       cls += ' gone';
            else if (c.occupe) cls += ' taken';
            else               cls += ' free';

            if (bas !== null) {
                if (i === bas || i === haut) cls += ' edge';
                else if (i > bas && i < haut) cls += ' mid';
            }
            const titre = c.occupe && c.titre ? ' title="Occupé : ' + this.echapper(c.titre) + '"' : '';
            html += '<div class="' + cls + '" data-i="' + i + '"' + titre + '>' + c.debut
                  + (c.occupe && !c.passe ? '<em>occupé</em>' : '')
                  + '</div>';
        });
        html += '</div>';
        html += this.recap();

        this.zone.innerHTML = html;
        this.zone.querySelectorAll('.slot.free').forEach(el => {
            el.addEventListener('click', () => this.cliquer(parseInt(el.dataset.i, 10)));
        });
    },

    cliquer(i) {
        if (this.debut === null || this.fin !== null) {
            this.debut = i; this.fin = null;
        } else if (i === this.debut) {
            this.debut = null;                        // même clic : on annule
        } else {
            // toute la plage doit être libre
            const [a, b] = [Math.min(i, this.debut), Math.max(i, this.debut)];
            for (let k = a; k <= b; k++) {
                if (this.creneaux[k].occupe || this.creneaux[k].passe) {
                    if (window.RS) RS.toast('Cette plage traverse un créneau occupé.', 'bad', 3200);
                    this.debut = i; this.fin = null;
                    this.dessiner();
                    return;
                }
            }
            this.fin = i;
        }
        this.dessiner();
    },

    recap() {
        if (this.debut === null) {
            return '<p class="hint mt-4"><i class="fas fa-hand-pointer"></i> '
                 + 'Cliquez un créneau de début, puis un créneau de fin.</p>';
        }
        const bas  = Math.min(this.debut, this.fin ?? this.debut);
        const haut = Math.max(this.debut, this.fin ?? this.debut);
        const hDebut = this.creneaux[bas].debut;
        const hFin   = this.creneaux[haut].fin;
        const mins   = Valider.minutesEntreHeures(hDebut, hFin);
        const h = Math.floor(mins / 60), m = mins % 60;
        const duree = h ? (m ? h + ' h ' + String(m).padStart(2, '0') : h + ' h') : m + ' min';

        const lien = this.urlReserver
                   + '?salle=' + encodeURIComponent(this.salleId)
                   + '&jour='  + encodeURIComponent(this.jour)
                   + '&hd='    + encodeURIComponent(hDebut)
                   + '&hf='    + encodeURIComponent(hFin);

        return '<div class="pickbox">'
             + '<span class="lab">Créneau retenu</span>'
             + '<span class="val">' + hDebut + '<i class="fas fa-arrow-right"></i>' + hFin
             + '<small>' + duree + '</small></span>'
             + (this.connecte
                 ? '<a class="btn btn-primary btn-block" href="' + lien + '">'
                   + '<i class="fas fa-calendar-plus"></i> Réserver ce créneau</a>'
                 : '<a class="btn btn-outline btn-block" href="login.php">'
                   + '<i class="fas fa-right-to-bracket"></i> Connectez-vous pour réserver</a>')
             + '</div>';
    },

    echapper(s) {
        return String(s).replace(/[&<>"']/g, c =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
};
