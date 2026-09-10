/* =====================================================================
   ReservaSalles 2 — Interactions générales
   ---------------------------------------------------------------------
   JavaScript natif, aucune bibliothèque externe.
     1. Thème clair / sombre
     2. Apparition au défilement
     3. Compteurs et jauges animés
     4. Onde au clic + lueur suivant le curseur
     5. Notifications flottantes
     6. Coque back-office (rail latéral)
     7. Palette de commandes (Ctrl+K)
     8. Aperçu de fichier, copie, confirmations
   ===================================================================== */
'use strict';

const RS = (() => {

    const doc  = document;
    const html = doc.documentElement;
    const sobre = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* =============================================== 1. THÈME */
    const CLE_THEME = 'rs2-theme';

    function themeEffectif() {
        const choix = localStorage.getItem(CLE_THEME);
        if (choix === 'dark' || choix === 'light') return choix;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function appliquerTheme(mode, memoriser) {
        if (mode) html.setAttribute('data-theme', mode);
        const eff = mode || themeEffectif();
        html.classList.toggle('theme-dark', eff === 'dark');
        if (memoriser && mode) {
            try { localStorage.setItem(CLE_THEME, mode); } catch (e) { /* stockage refusé */ }
        }
        doc.querySelectorAll('.theme-btn').forEach(b => {
            b.setAttribute('aria-label', eff === 'dark' ? 'Passer en thème clair' : 'Passer en thème sombre');
            b.setAttribute('aria-pressed', String(eff === 'dark'));
        });
    }

    function initTheme() {
        const choix = localStorage.getItem(CLE_THEME);
        appliquerTheme(choix === 'dark' || choix === 'light' ? choix : null, false);

        doc.addEventListener('click', e => {
            const b = e.target.closest('.theme-btn');
            if (!b) return;
            appliquerTheme(themeEffectif() === 'dark' ? 'light' : 'dark', true);
        });

        // Suit le système tant que l'utilisateur n'a rien choisi
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            if (!localStorage.getItem(CLE_THEME)) appliquerTheme(null, false);
        });
    }

    /* =============================================== 2. APPARITION AU DÉFILEMENT */
    /**
     * Un IntersectionObserver ne se déclenche pas dans un onglet en arrière-plan.
     * Sans filet, le contenu resterait invisible (opacité 0). On double donc
     * l'observateur d'un contrôle manuel : au retour de l'onglet, au défilement,
     * et une fois peu après le chargement.
     */
    function surVue(cibles, montrer, marge) {
        const verifier = () => {
            const h = window.innerHeight || doc.documentElement.clientHeight || 0;
            cibles.forEach(el => {
                if (el.dataset.vu) return;
                const r = el.getBoundingClientRect();
                if (r.top < h * marge && r.bottom > 0) { el.dataset.vu = '1'; montrer(el); }
            });
        };

        if (sobre || !('IntersectionObserver' in window)) {
            cibles.forEach(el => { el.dataset.vu = '1'; montrer(el); });
            return;
        }

        const obs = new IntersectionObserver(entrees => {
            entrees.forEach(en => {
                if (!en.isIntersecting || en.target.dataset.vu) return;
                en.target.dataset.vu = '1';
                montrer(en.target);
                obs.unobserve(en.target);
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

        cibles.forEach(el => obs.observe(el));

        doc.addEventListener('visibilitychange', () => { if (!doc.hidden) verifier(); });
        window.addEventListener('scroll', verifier, { passive: true });
        window.addEventListener('resize', verifier, { passive: true });
        setTimeout(verifier, 1200);
    }

    function initReveal() {
        const cibles = [...doc.querySelectorAll('.reveal')];
        if (!cibles.length) return;
        surVue(cibles, el => el.classList.add('in'), 0.95);
    }

    /* =============================================== 3. COMPTEURS ET JAUGES */
    function compter(el) {
        const cible = parseFloat(el.dataset.count);
        if (isNaN(cible)) return;
        const dec   = (el.dataset.count.split('.')[1] || '').length;
        const final = () => { el.textContent = cible.toFixed(dec); };

        // requestAnimationFrame ne tourne pas dans un onglet en arrière-plan :
        // un chiffre figé à zéro serait une donnée fausse, on l'écrit d'emblée.
        if (sobre || doc.hidden) { final(); return; }

        const duree = 1100, depart = performance.now();
        const pas = (t) => {
            const p = Math.min(1, (t - depart) / duree);
            const adouci = 1 - Math.pow(1 - p, 3);          // easeOutCubic
            el.textContent = (cible * adouci).toFixed(dec);
            if (p < 1) requestAnimationFrame(pas);
            else final();
        };
        requestAnimationFrame(pas);
        setTimeout(final, duree + 400);                     // filet de sécurité
    }

    function initCompteurs() {
        // data-meter (v2) et data-largeur (vues héritées) remplissent une barre.
        const cibles = [...doc.querySelectorAll('[data-count], [data-meter], [data-largeur]')];
        if (!cibles.length) return;

        // Un chiffre resté à zéro serait une donnée fausse : le même filet de
        // sécurité que pour l'apparition s'applique ici.
        surVue(cibles, el => {
            if (el.hasAttribute('data-count')) { compter(el); return; }
            const pct = parseFloat(el.dataset.meter ?? el.dataset.largeur) || 0;
            el.style.width = Math.max(0, Math.min(100, pct)) + '%';
        }, 1);
    }

    /* =============================================== 4. ONDE ET LUEUR */
    function initBoutons() {
        doc.addEventListener('pointerdown', e => {
            const b = e.target.closest('.btn');
            if (!b || sobre || b.disabled) return;
            const r = b.getBoundingClientRect();
            const taille = Math.max(r.width, r.height);
            const onde = doc.createElement('span');
            onde.className = 'ripple';
            onde.style.width = onde.style.height = taille + 'px';
            onde.style.left = (e.clientX - r.left - taille / 2) + 'px';
            onde.style.top  = (e.clientY - r.top  - taille / 2) + 'px';
            b.appendChild(onde);
            setTimeout(() => onde.remove(), 600);
        });

        doc.addEventListener('pointermove', e => {
            const b = e.target.closest('.btn-primary');
            if (!b) return;
            const r = b.getBoundingClientRect();
            b.style.setProperty('--mx', (e.clientX - r.left) + 'px');
            b.style.setProperty('--my', (e.clientY - r.top) + 'px');
        });
    }

    /* =============================================== 5. NOTIFICATIONS */
    function bac() {
        let z = doc.querySelector('.toasts');
        if (!z) {
            z = doc.createElement('div');
            z.className = 'toasts';
            z.setAttribute('role', 'status');
            z.setAttribute('aria-live', 'polite');
            doc.body.appendChild(z);
        }
        return z;
    }

    function toast(message, type = 'info', duree = 4200) {
        const icones = { ok: 'fa-circle-check', bad: 'fa-circle-exclamation', info: 'fa-circle-info' };
        const t = doc.createElement('div');
        t.className = 'toast toast-' + type;
        t.innerHTML = `<i class="fas ${icones[type] || icones.info}"></i>`
                    + `<div>${message}</div>`
                    + `<button class="x" type="button" aria-label="Fermer">&times;</button>`;
        bac().appendChild(t);
        const fermer = () => {
            t.classList.add('out');
            t.addEventListener('animationend', () => t.remove(), { once: true });
        };
        t.querySelector('.x').addEventListener('click', fermer);
        if (duree) setTimeout(fermer, duree);
        return t;
    }

    /* =============================================== 6. COQUE BACK-OFFICE */
    const CLE_MINI = 'rs2-rail-mini';

    function initRail() {
        const coque = doc.querySelector('.admin');
        if (!coque) return;

        if (localStorage.getItem(CLE_MINI) === '1' && window.innerWidth > 940) {
            coque.classList.add('mini');
        }

        doc.addEventListener('click', e => {
            const b = e.target.closest('[data-rail]');
            if (b) {
                if (window.innerWidth <= 940) {
                    coque.classList.toggle('open');
                } else {
                    coque.classList.toggle('mini');
                    try { localStorage.setItem(CLE_MINI, coque.classList.contains('mini') ? '1' : '0'); }
                    catch (err) { /* ignoré */ }
                }
                return;
            }
            // Clic sur le voile : referme le rail
            if (coque.classList.contains('open') && !e.target.closest('.rail')) {
                coque.classList.remove('open');
            }
        });

        doc.addEventListener('keydown', e => {
            if (e.key === 'Escape') coque.classList.remove('open');
        });
    }

    /* Ombre de la barre du front-office au défilement */
    function initNavCollante() {
        const nav = doc.querySelector('.site-nav');
        if (!nav) return;
        const maj = () => nav.classList.toggle('stuck', window.scrollY > 8);
        maj();
        window.addEventListener('scroll', maj, { passive: true });
    }

    /* =============================================== 7. PALETTE DE COMMANDES */
    function initPalette() {
        const entrees = window.RS_COMMANDES;
        if (!Array.isArray(entrees) || !entrees.length) return;

        const back = doc.createElement('div');
        back.className = 'cmdk-back';
        back.hidden = true;
        back.innerHTML =
            `<div class="cmdk" role="dialog" aria-modal="true" aria-label="Recherche rapide">
               <div class="cmdk-in">
                 <i class="fas fa-magnifying-glass"></i>
                 <input type="text" placeholder="Aller à une page, une salle…" aria-label="Rechercher">
               </div>
               <div class="cmdk-list"></div>
               <div class="cmdk-foot">
                 <span><kbd>&uarr;</kbd><kbd>&darr;</kbd> naviguer</span>
                 <span><kbd>Entrée</kbd> ouvrir</span>
                 <span><kbd>Échap</kbd> fermer</span>
               </div>
             </div>`;
        doc.body.appendChild(back);

        const champ  = back.querySelector('input');
        const liste  = back.querySelector('.cmdk-list');
        let filtres = [], curseur = 0;

        // Recherche insensible aux accents : « etage » trouve « Étage »
        const DIACRITIQUES = new RegExp('[\\u0300-\\u036f]', 'g');
        const sansAccent = (s) => s.normalize('NFD').replace(DIACRITIQUES, '').toLowerCase();

        function dessiner(q) {
            const req = sansAccent(q.trim());
            filtres = req === ''
                ? entrees.slice(0, 12)
                : entrees.filter(c => sansAccent(c.label + ' ' + (c.groupe || '')).includes(req)).slice(0, 12);
            curseur = 0;

            if (!filtres.length) {
                liste.innerHTML = '<div class="cmdk-none">Aucun résultat</div>';
                return;
            }
            let html = '', groupe = null;
            filtres.forEach((c, i) => {
                if (c.groupe && c.groupe !== groupe) {
                    groupe = c.groupe;
                    html += `<div class="grp">${groupe}</div>`;
                }
                html += `<div class="cmdk-item${i === 0 ? ' sel' : ''}" data-i="${i}">`
                      + `<i class="fas ${c.icone || 'fa-arrow-right'}"></i><span>${c.label}</span>`
                      + (c.tail ? `<span class="tail">${c.tail}</span>` : '')
                      + `</div>`;
            });
            liste.innerHTML = html;
        }

        function surligner() {
            liste.querySelectorAll('.cmdk-item').forEach(el => {
                const on = Number(el.dataset.i) === curseur;
                el.classList.toggle('sel', on);
                if (on) el.scrollIntoView({ block: 'nearest' });
            });
        }

        function ouvrir() {
            back.hidden = false;
            champ.value = '';
            dessiner('');
            champ.focus();
            doc.body.style.overflow = 'hidden';
        }
        function fermer() {
            back.hidden = true;
            doc.body.style.overflow = '';
        }
        function valider() {
            const c = filtres[curseur];
            if (c && c.url) window.location.href = c.url;
        }

        champ.addEventListener('input', () => dessiner(champ.value));
        liste.addEventListener('click', e => {
            const it = e.target.closest('.cmdk-item');
            if (!it) return;
            curseur = Number(it.dataset.i);
            valider();
        });
        liste.addEventListener('pointermove', e => {
            const it = e.target.closest('.cmdk-item');
            if (it && Number(it.dataset.i) !== curseur) { curseur = Number(it.dataset.i); surligner(); }
        });
        back.addEventListener('click', e => { if (e.target === back) fermer(); });

        doc.addEventListener('keydown', e => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                back.hidden ? ouvrir() : fermer();
                return;
            }
            if (back.hidden) return;
            if (e.key === 'Escape')      { e.preventDefault(); fermer(); }
            else if (e.key === 'ArrowDown') { e.preventDefault(); curseur = (curseur + 1) % filtres.length; surligner(); }
            else if (e.key === 'ArrowUp')   { e.preventDefault(); curseur = (curseur - 1 + filtres.length) % filtres.length; surligner(); }
            else if (e.key === 'Enter')     { e.preventDefault(); valider(); }
        });

        doc.addEventListener('click', e => { if (e.target.closest('[data-cmdk]')) ouvrir(); });
    }

    /* =============================================== 8. DIVERS */
    /* Aperçu immédiat d'une image choisie : <input data-preview="#cible"> */
    function initApercus() {
        doc.querySelectorAll('input[type=file][data-preview]').forEach(champ => {
            const cible = doc.querySelector(champ.dataset.preview);
            if (!cible) return;
            champ.addEventListener('change', () => {
                const f = champ.files && champ.files[0];
                if (!f) { cible.hidden = true; cible.removeAttribute('src'); return; }
                if (cible.dataset.url) URL.revokeObjectURL(cible.dataset.url);
                const url = URL.createObjectURL(f);
                cible.dataset.url = url;
                cible.src = url;
                cible.hidden = false;
            });
        });
    }

    /* Copie dans le presse-papiers : <button data-copy="texte"> */
    function initCopie() {
        doc.addEventListener('click', async e => {
            const b = e.target.closest('[data-copy]');
            if (!b) return;
            try {
                await navigator.clipboard.writeText(b.dataset.copy);
                toast('Copié dans le presse-papiers.', 'ok', 2200);
            } catch (err) {
                toast('La copie a échoué.', 'bad');
            }
        });
    }

    /* ---------------------------------------------------------------------
       Fenêtres modales des vues héritées.
       Déclencheur : <button data-modale="idDeLaModale" data-id="…" data-titre="…">
       Les valeurs sont recopiées dans les [data-champ="id"], [data-champ="titre"]…
       de la modale : un <input> reçoit sa valeur, tout autre élément son texte.
       --------------------------------------------------------------------- */
    function initModales() {
        let derniereSource = null;

        const ouvrir = (modale, source) => {
            modale.querySelectorAll('[data-champ]').forEach(cible => {
                const valeur = source.dataset[cible.dataset.champ];
                if (valeur === undefined) return;
                if ('value' in cible && cible.tagName !== 'DIV') cible.value = valeur;
                else cible.textContent = valeur;
            });
            modale.classList.add('on');
            doc.body.style.overflow = 'hidden';
            derniereSource = source;
            const premier = modale.querySelector('textarea, input:not([type=hidden]), select');
            if (premier) setTimeout(() => premier.focus(), 60);
        };

        const fermer = (modale) => {
            modale.classList.remove('on');
            doc.body.style.overflow = '';
            if (derniereSource) { derniereSource.focus(); derniereSource = null; }
        };

        doc.addEventListener('click', e => {
            const source = e.target.closest('[data-modale]');
            if (source) {
                const modale = doc.getElementById(source.dataset.modale);
                if (modale) { e.preventDefault(); ouvrir(modale, source); }
                return;
            }
            const fermeture = e.target.closest('.fermer-modale, [data-fermer]');
            if (fermeture) {
                const modale = fermeture.closest('.modale');
                if (modale) { e.preventDefault(); fermer(modale); }
                return;
            }
            // Clic sur le voile
            if (e.target.classList && e.target.classList.contains('modale')) fermer(e.target);
        });

        doc.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            const ouverte = doc.querySelector('.modale.on');
            if (ouverte) fermer(ouverte);
        });
    }

    /* Confirmation avant une action destructrice : <a data-confirm="message"> */
    function initConfirmations() {
        doc.addEventListener('click', e => {
            const el = e.target.closest('[data-confirm]');
            if (!el) return;
            if (!window.confirm(el.dataset.confirm)) e.preventDefault();
        });
    }

    /* =============================================== DÉMARRAGE */
    function init() {
        initTheme();
        initReveal();
        initCompteurs();
        initBoutons();
        initRail();
        initNavCollante();
        initPalette();
        initApercus();
        initModales();
        initCopie();
        initConfirmations();
    }

    if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', init);
    else init();

    return { toast, theme: appliquerTheme };
})();

/* ---------------------------------------------------------------------
   Confirmations appelées en ligne depuis les vues (onclick="return …").
   Conservées comme fonctions globales pour rester compatibles avec les
   pages existantes du back-office.
   --------------------------------------------------------------------- */
function confirmerSuppression(message) {
    return window.confirm(message || 'Confirmer la suppression ? Cette action est définitive.');
}
function confirmerAction(message) {
    return window.confirm(message || 'Confirmer cette action ?');
}
