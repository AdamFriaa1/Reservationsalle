<?php
/**
 * Modification / déplacement d'une réunion par un gestionnaire.
 * Permet de changer la salle et/ou le créneau pour résoudre un conflit,
 * puis notifie le demandeur du déplacement.
 */
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin', 'gestionnaire']);

$reservationC = new ReservationC();
$salleC       = new SalleC();
$mailC        = new MailC();

$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
if ($id === 0) { flash_set('error', 'Réservation introuvable.'); redirect('listReservation.php'); }

$existante = $reservationC->getReservation($id);
if ($existante === null) { flash_set('error', 'Réservation introuvable.'); redirect('listReservation.php'); }

$erreurs      = [];
$conflits     = [];
$alternatives = [];

$donnees = [
    'id_salle'        => (int)$existante['id_salle'],
    'titre'           => $existante['titre'],
    'description'     => (string)$existante['description'],
    'date'            => date('Y-m-d', strtotime($existante['date_debut'])),
    'heure_debut'     => date('H:i',   strtotime($existante['date_debut'])),
    'heure_fin'       => date('H:i',   strtotime($existante['date_fin'])),
    'nb_participants' => (int)$existante['nb_participants'],
    'statut'          => $existante['statut'],
];

try { $salles = $salleC->getSallesDisponibles(); } catch (Throwable $e) { $salles = []; }

// Conflits actuels de la réservation (utile avant modification)
try {
    if (in_array($existante['statut'], ['en_attente', 'validee'], true)) {
        $conflitsActuels = $reservationC->detecterConflits(
            (int)$existante['id_salle'], $existante['date_debut'], $existante['date_fin'], $id);
    } else {
        $conflitsActuels = [];
    }
} catch (Throwable $e) { $conflitsActuels = []; }

/* Réservation qui bloque, transmise par la page « Conflits » : on l'affiche
   en vis-à-vis pour que le gestionnaire voie les deux créneaux à la fois. */
$bloquante = null;
if (isset($_GET['conflit']) && ctype_digit((string)$_GET['conflit'])) {
    try {
        $c = $reservationC->getReservation((int)$_GET['conflit']);
        if ($c !== null && (int)$c['id_reservation'] !== $id) { $bloquante = $c; }
    } catch (Throwable $e) { $bloquante = null; }
}
// À défaut, on prend le premier chevauchement détecté automatiquement.
if ($bloquante === null && $conflitsActuels) { $bloquante = $conflitsActuels[0]; }

// Durée d'origine, en minutes : un déplacement conserve la durée par défaut.
$dureeOrigine = max(15, (int)round(
    (strtotime($existante['date_fin']) - strtotime($existante['date_debut'])) / 60
));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_valide()) {
        $erreurs[] = 'Session expirée, merci de renvoyer le formulaire.';
    } else {
        foreach ($donnees as $cle => $_) {
            $donnees[$cle] = trim($_POST[$cle] ?? '');
        }

        // ---- Contrôles de saisie côté serveur ----
        if (!ctype_digit((string)$donnees['id_salle'])) $erreurs[] = 'Merci de choisir une salle.';

        if (!v_requis($donnees['titre']))               $erreurs[] = "L'objet de la réunion est obligatoire.";
        elseif (!v_longueur($donnees['titre'], 3, 150)) $erreurs[] = "L'objet doit contenir entre 3 et 150 caractères.";

        if ($donnees['description'] !== '' && mb_strlen($donnees['description']) > 1000) {
            $erreurs[] = 'La description ne doit pas dépasser 1000 caractères.';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $donnees['date']))                  $erreurs[] = 'La date est invalide.';
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $donnees['heure_debut']))     $erreurs[] = "L'heure de début est invalide.";
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $donnees['heure_fin']))       $erreurs[] = "L'heure de fin est invalide.";
        if (!v_entier($donnees['nb_participants'], 1, 1000))                         $erreurs[] = 'Le nombre de participants doit être un entier entre 1 et 1000.';
        if (!v_dans(Reservation::STATUTS, $donnees['statut']))                       $erreurs[] = 'Statut invalide.';

        if (!$erreurs) {
            $debut = $donnees['date'] . ' ' . $donnees['heure_debut'] . ':00';
            $fin   = $donnees['date'] . ' ' . $donnees['heure_fin'] . ':00';

            $salle = $salleC->getSalle((int)$donnees['id_salle']);

            if ($salle === null) {
                $erreurs[] = 'La salle sélectionnée est introuvable.';
            } elseif (in_array($donnees['statut'], ['en_attente', 'validee'], true)) {
                // La réservation courante est exclue du contrôle de chevauchement
                $erreursMetier = $reservationC->validerCreneau(
                    $salle, $debut, $fin, (int)$donnees['nb_participants'], $id);
                $erreurs = array_merge($erreurs, $erreursMetier);

                if ($erreursMetier) {
                    $conflits = $reservationC->detecterConflits(
                        (int)$salle['id_salle'], $debut, $fin, $id);
                    if ($conflits) {
                        try {
                            $alternatives = $salleC->getSallesLibres(
                                $debut, $fin, (int)$donnees['nb_participants']);
                        } catch (Throwable $e) { $alternatives = []; }
                    }
                }
            }

            if (!$erreurs) {
                try {
                    $salleChangee   = (int)$donnees['id_salle'] !== (int)$existante['id_salle'];
                    $creneauChange  = $debut !== $existante['date_debut'] || $fin !== $existante['date_fin'];

                    $r = new Reservation();
                    $r->setIdSalle((int)$donnees['id_salle']);
                    $r->setIdUtilisateur((int)$existante['id_utilisateur']);
                    $r->setTitre($donnees['titre']);
                    $r->setDescription($donnees['description'] !== '' ? $donnees['description'] : null);
                    $r->setDateDebut($debut);
                    $r->setDateFin($fin);
                    $r->setNbParticipants((int)$donnees['nb_participants']);
                    $r->setStatut($donnees['statut']);

                    $reservationC->updateReservation($r, $id);

                    // Le statut n'est pas géré par updateReservation : mise à jour dédiée
                    if ($donnees['statut'] !== $existante['statut']) {
                        $reservationC->changerStatut(
                            $id, $donnees['statut'], id_courant(), $existante['motif_refus']);
                    }

                    // Notification de déplacement si la salle ou le créneau a changé
                    if ($salleChangee || $creneauChange) {
                        $misAJour = $reservationC->getReservation($id);
                        if ($misAJour !== null) {
                            $ancienCreneau = fmt_date($existante['date_debut']) . ' de '
                                . fmt_heure($existante['date_debut']) . ' à '
                                . fmt_heure($existante['date_fin']);
                            $mailC->notifierDeplacement($misAJour, $ancienCreneau, $existante['nom_salle']);
                        }
                    }

                    flash_set('success', 'Réservation « ' . $donnees['titre'] . ' » mise à jour'
                        . (($salleChangee || $creneauChange) ? ', le demandeur a été notifié du déplacement.' : '.'));
                    redirect('listReservation.php');
                } catch (Throwable $e) {
                    $erreurs[] = 'Erreur lors de la mise à jour : ' . $e->getMessage();
                }
            }
        }
    }
}

$titrePage  = 'Modifier la réservation';
$pageActive = 'reservations';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <div class="fil-ariane"><a href="index.php">Tableau de bord</a> / <a href="listReservation.php">Réservations</a> / Modifier</div>
        <h2><i class="fas fa-arrows-up-down-left-right"></i> Modifier / déplacer la réunion</h2>
        <p>
            Demandée par <strong><?= e($existante['prenom_utilisateur'] . ' ' . $existante['nom_utilisateur']) ?></strong>
            le <?= e(fmt_datetime($existante['date_creation'])) ?> ·
            <span class="badge <?= classe_statut($existante['statut']) ?>"><?= e(libelle_statut($existante['statut'])) ?></span>
        </p>
    </div>
    <a href="listReservation.php" class="btn btn-light"><i class="fas fa-arrow-left"></i> Retour</a>
</div>

<?php if ($erreurs): ?>
    <div class="alert alert-danger">
        <i class="fas fa-circle-exclamation"></i>
        <div>
            <strong>Merci de corriger :</strong>
            <ul><?php foreach ($erreurs as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    </div>
<?php endif; ?>

<!-- ================================================ COMPARATIF AVANT / APRÈS -->
<div class="card mb-4" id="carteComparatif">
    <div class="card-header">
        <h3><i class="fas fa-right-left"></i> Créneau actuel et créneau visé</h3>
        <span class="badge badge-plain" id="badgeChangement">Aucun changement</span>
    </div>
    <div class="card-body">
        <div class="deplacement">

            <!-- Avant -->
            <div class="dep-bloc">
                <span class="dep-label">Actuellement</span>
                <span class="dep-heures"><?= e(fmt_heure($existante['date_debut'])) ?> → <?= e(fmt_heure($existante['date_fin'])) ?></span>
                <span class="dep-sous"><?= e(fmt_date($existante['date_debut'])) ?></span>
                <span class="dep-salle"><i class="fas fa-door-open"></i> <?= e($existante['nom_salle']) ?> — <?= e($existante['nom_batiment']) ?></span>
            </div>

            <span class="dep-fleche" aria-hidden="true"><i class="fas fa-arrow-right"></i></span>

            <!-- Après (mis à jour en direct par le formulaire) -->
            <div class="dep-bloc cible" id="depApres">
                <span class="dep-label">Après déplacement</span>
                <span class="dep-heures" id="depHeures">—</span>
                <span class="dep-sous" id="depDate">—</span>
                <span class="dep-salle"><i class="fas fa-door-open"></i> <span id="depSalle">—</span></span>
            </div>

            <!-- Réservation qui bloque -->
            <?php if ($bloquante !== null): ?>
                <div class="dep-bloc bloquant">
                    <span class="dep-label">À ne pas croiser</span>
                    <span class="dep-heures"><?= e(fmt_heure($bloquante['date_debut'])) ?> → <?= e(fmt_heure($bloquante['date_fin'])) ?></span>
                    <span class="dep-sous">« <?= e($bloquante['titre']) ?> »</span>
                    <span class="dep-salle">
                        <span class="badge <?= e(classe_statut($bloquante['statut'])) ?>"><?= e(libelle_statut($bloquante['statut'])) ?></span>
                        <?= e($bloquante['prenom_utilisateur'] . ' ' . $bloquante['nom_utilisateur']) ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($conflitsActuels && !$erreurs): ?>
            <div class="alert alert-warning mt-4" style="margin-bottom:0">
                <i class="fas fa-triangle-exclamation"></i>
                <div>
                    <strong>Le créneau actuel chevauche <?= count($conflitsActuels) ?> autre(s) réservation(s) :</strong>
                    <ul>
                        <?php foreach ($conflitsActuels as $c): ?>
                            <li>
                                « <?= e($c['titre']) ?> » —
                                <?= e(fmt_heure($c['date_debut'])) ?> à <?= e(fmt_heure($c['date_fin'])) ?>
                                (<?= e(libelle_statut($c['statut'])) ?>)
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($alternatives): ?>
    <div class="card mb-3" style="border-left:4px solid var(--vert);">
        <div class="card-header"><h3><i class="fas fa-lightbulb"></i> Salles libres sur ce créneau</h3></div>
        <div class="card-body">
            <p class="text-muted mb-2" style="font-size:.88rem;">Cliquez pour affecter la réunion à l'une de ces salles.</p>
            <div class="chips">
                <?php foreach (array_slice($alternatives, 0, 8) as $alt): ?>
                    <button type="button" class="chip chip-action" data-salle-alt="<?= (int)$alt['id_salle'] ?>">
                        <i class="fas fa-door-open"></i>
                        <?= e($alt['nom']) ?> — <?= e($alt['nom_batiment']) ?> (<?= (int)$alt['capacite'] ?> pers.)
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="grid" style="grid-template-columns: 2fr 1fr; gap: 1.2rem; align-items: start;">

    <form method="post" id="formReservation" novalidate>
        <?= csrf_field() ?>

        <div class="card">
            <div class="card-header"><h3><i class="fas fa-door-open"></i> Salle et créneau</h3></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group full">
                        <label for="id_salle">Salle <span class="req">*</span></label>
                        <select id="id_salle" name="id_salle">
                            <?php foreach ($salles as $s): ?>
                                <option value="<?= (int)$s['id_salle'] ?>"
                                        data-capacite="<?= (int)$s['capacite'] ?>"
                                        data-ouverture="<?= e(substr($s['heure_ouverture'], 0, 5)) ?>"
                                        data-fermeture="<?= e(substr($s['heure_fermeture'], 0, 5)) ?>"
                                    <?= (string)$donnees['id_salle'] === (string)$s['id_salle'] ? 'selected' : '' ?>>
                                    <?= e($s['nom']) ?> — <?= e($s['nom_batiment']) ?> (<?= (int)$s['capacite'] ?> pers.)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="aide" id="aide-capacite"></span>
                        <span class="erreur-champ" id="err-id_salle"></span>
                    </div>
                    <div class="form-group">
                        <label for="date">Date <span class="req">*</span></label>
                        <input type="date" id="date" name="date" value="<?= e($donnees['date']) ?>">
                        <span class="erreur-champ" id="err-date"></span>
                    </div>
                    <div class="form-group">
                        <label for="nb_participants">Participants <span class="req">*</span></label>
                        <input type="number" id="nb_participants" name="nb_participants" min="1" max="1000"
                               value="<?= e($donnees['nb_participants']) ?>">
                        <span class="erreur-champ" id="err-nb_participants"></span>
                    </div>
                    <div class="form-group">
                        <label for="heure_debut">Heure de début <span class="req">*</span></label>
                        <input type="time" id="heure_debut" name="heure_debut" value="<?= e($donnees['heure_debut']) ?>">
                        <span class="erreur-champ" id="err-heure_debut"></span>
                    </div>
                    <div class="form-group">
                        <label for="heure_fin">Heure de fin <span class="req">*</span></label>
                        <input type="time" id="heure_fin" name="heure_fin" value="<?= e($donnees['heure_fin']) ?>">
                        <span class="erreur-champ" id="err-heure_fin"></span>
                    </div>
                </div>

                <!-- ============ DISPONIBILITÉS DE LA SALLE CE JOUR-LÀ ============ -->
                <div class="rule mt-4 mb-4"></div>
                <div class="d-flex justify-between align-center flex-wrap gap-2 mb-3">
                    <h4 style="font-size:var(--t-base)">
                        <i class="fas fa-table-cells" style="color:var(--brand-500)"></i>
                        Disponibilités de la salle ce jour-là
                    </h4>
                    <span class="aide" id="dispoResume"></span>
                </div>
                <div id="dispoStrip">
                    <p class="aide"><i class="fas fa-circle-info"></i> Chargement…</p>
                </div>
                <p class="aide mt-3">
                    <i class="fas fa-hand-pointer"></i>
                    Cliquez un créneau libre : la réunion y est déplacée en gardant sa durée
                    (<?= e(fmt_minutes($dureeOrigine)) ?>).
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3><i class="fas fa-circle-info"></i> Détails</h3></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group full">
                        <label for="titre">Objet de la réunion <span class="req">*</span></label>
                        <input type="text" id="titre" name="titre" value="<?= e($donnees['titre']) ?>">
                        <span class="erreur-champ" id="err-titre"></span>
                    </div>
                    <div class="form-group full">
                        <label for="description">Description</label>
                        <textarea id="description" name="description"><?= e($donnees['description']) ?></textarea>
                        <span class="erreur-champ" id="err-description"></span>
                    </div>
                    <div class="form-group full">
                        <label for="statut">Statut <span class="req">*</span></label>
                        <select id="statut" name="statut">
                            <?php foreach (Reservation::STATUTS as $st): ?>
                                <option value="<?= e($st) ?>" <?= $donnees['statut'] === $st ? 'selected' : '' ?>>
                                    <?= e(libelle_statut($st)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="aide">Le contrôle de chevauchement ne s'applique qu'aux statuts « en attente » et « validée ».</span>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Enregistrer et notifier</button>
                    <a href="listReservation.php" class="btn btn-light">Annuler</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header"><h3><i class="fas fa-clock-rotate-left"></i> Réservation d'origine</h3></div>
        <div class="card-body">
            <ul class="liste-info">
                <li><i class="fas fa-user"></i> <?= e($existante['prenom_utilisateur'] . ' ' . $existante['nom_utilisateur']) ?></li>
                <li><i class="fas fa-envelope"></i> <?= e($existante['email_utilisateur']) ?></li>
                <li><i class="fas fa-door-open"></i> <?= e($existante['nom_salle']) ?> — <?= e($existante['nom_batiment']) ?></li>
                <li><i class="fas fa-calendar"></i> <?= e(fmt_date($existante['date_debut'])) ?></li>
                <li><i class="fas fa-clock"></i> <?= e(fmt_heure($existante['date_debut'])) ?> – <?= e(fmt_heure($existante['date_fin'])) ?></li>
                <li><i class="fas fa-users"></i> <?= (int)$existante['nb_participants'] ?> participant(s)</li>
                <?php if (!empty($existante['motif_refus'])): ?>
                    <li><i class="fas fa-comment"></i> Motif : <?= e($existante['motif_refus']) ?></li>
                <?php endif; ?>
            </ul>
            <a href="calendrier.php?salle=<?= (int)$existante['id_salle'] ?>" class="btn btn-light btn-block mt-3">
                <i class="fas fa-calendar"></i> Planning de la salle
            </a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>

<script>
(function () {
    const selSalle = document.getElementById('id_salle');
    const aide     = document.getElementById('aide-capacite');
    const champNb  = document.getElementById('nb_participants');

    function majCapacite() {
        const opt = selSalle.options[selSalle.selectedIndex];
        if (!opt) return;
        champNb.max = opt.getAttribute('data-capacite');
        aide.textContent = 'Capacité : ' + opt.getAttribute('data-capacite') + ' personnes · Ouverture '
            + opt.getAttribute('data-ouverture') + ' – ' + opt.getAttribute('data-fermeture') + '.';
    }
    selSalle.addEventListener('change', majCapacite);
    majCapacite();

    // Boutons « salle libre » proposés en cas de conflit
    document.querySelectorAll('[data-salle-alt]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            selSalle.value = btn.getAttribute('data-salle-alt');
            majCapacite();
            chargerDispos();
            selSalle.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });

    /* =================================================================
       Comparatif « avant / après » et bande de disponibilités
       ================================================================= */
    const ID_RESERVATION = <?= (int)$id ?>;
    const DUREE_ORIGINE  = <?= (int)$dureeOrigine ?>;          // minutes
    const ORIGINE = {
        salle: '<?= e(addslashes($existante['nom_salle'])) ?>',
        date:  '<?= e(date('Y-m-d', strtotime($existante['date_debut']))) ?>',
        debut: '<?= e(date('H:i', strtotime($existante['date_debut']))) ?>',
        fin:   '<?= e(date('H:i', strtotime($existante['date_fin']))) ?>'
    };
    const BLOQUANTE = <?= $bloquante !== null ? json_encode([
        'titre' => $bloquante['titre'],
        'salle' => (int)$bloquante['id_salle'],
        'date'  => date('Y-m-d', strtotime($bloquante['date_debut'])),
        'debut' => date('H:i',   strtotime($bloquante['date_debut'])),
        'fin'   => date('H:i',   strtotime($bloquante['date_fin'])),
    ], JSON_UNESCAPED_UNICODE) : 'null' ?>;

    const champDate  = document.getElementById('date');
    const champDebut = document.getElementById('heure_debut');
    const champFin   = document.getElementById('heure_fin');
    const strip      = document.getElementById('dispoStrip');
    const resume     = document.getElementById('dispoResume');
    const badge      = document.getElementById('badgeChangement');

    let creneaux = [];

    const enMinutes = h => { const [a, b] = h.split(':').map(Number); return a * 60 + b; };
    const enHeure   = m => String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');

    /* ---- Le panneau « après déplacement » suit les champs en direct ---- */
    function majComparatif() {
        const opt = selSalle.selectedOptions[0];
        const nomSalle = opt ? opt.textContent.trim().split('—')[0].trim() : '—';
        document.getElementById('depSalle').textContent  = nomSalle;
        document.getElementById('depHeures').textContent =
            (champDebut.value && champFin.value) ? champDebut.value + ' → ' + champFin.value : '—';

        if (champDate.value) {
            const d = new Date(champDate.value + 'T00:00:00');
            const jours = ['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'];
            const mois  = ['janvier','février','mars','avril','mai','juin','juillet','août',
                           'septembre','octobre','novembre','décembre'];
            document.getElementById('depDate').textContent =
                jours[d.getDay()] + ' ' + d.getDate() + ' ' + mois[d.getMonth()] + ' ' + d.getFullYear();
        }

        const bouge = champDate.value !== ORIGINE.date
                   || champDebut.value !== ORIGINE.debut
                   || champFin.value   !== ORIGINE.fin
                   || nomSalle !== ORIGINE.salle;
        badge.textContent = bouge ? 'Déplacement en cours' : 'Aucun changement';
        badge.className   = 'badge badge-plain ' + (bouge ? 'badge-warn' : '');
        document.getElementById('depApres').classList.toggle('actif', bouge);
    }

    /* ---- Bande de créneaux de la salle sélectionnée ---- */
    let jeton = 0;
    async function chargerDispos() {
        const salle = selSalle.value, date = champDate.value;
        if (!salle || !date) { strip.innerHTML = ''; return; }

        const monJeton = ++jeton;
        strip.innerHTML = '<div class="slots">' + '<div class="skel" style="height:36px"></div>'.repeat(10) + '</div>';

        try {
            // La réunion déplacée est exclue : elle ne doit pas se bloquer elle-même.
            const url = '../frontend/ajax/disponibilites.php?salle=' + encodeURIComponent(salle)
                      + '&date=' + encodeURIComponent(date)
                      + '&exclure=' + ID_RESERVATION;
            const rep  = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await rep.json();
            if (monJeton !== jeton) return;

            if (data.erreur) {
                strip.innerHTML = '<div class="alert alert-warning" style="margin:0">'
                    + '<i class="fas fa-triangle-exclamation"></i><span>' + data.erreur + '</span></div>';
                resume.textContent = '';
                return;
            }
            creneaux = data.creneaux || [];
            dessinerDispos();
        } catch (e) {
            if (monJeton === jeton) {
                strip.innerHTML = '<p class="aide">Disponibilités indisponibles : ' + e.message + '</p>';
            }
        }
    }

    function dessinerDispos() {
        if (!creneaux.length) { strip.innerHTML = '<p class="aide">Salle fermée ce jour-là.</p>'; return; }

        const d = champDebut.value ? enMinutes(champDebut.value) : null;
        const f = champFin.value   ? enMinutes(champFin.value)   : null;
        const memeSalleQueBloquante = BLOQUANTE && String(BLOQUANTE.salle) === String(selSalle.value)
                                   && BLOQUANTE.date === champDate.value;

        let libres = 0, html = '<div class="slots">';
        creneaux.forEach((c, i) => {
            const deb = enMinutes(c.debut), fin = enMinutes(c.fin);
            let cls = 'slot';
            if (c.passe)       cls += ' gone';
            else if (c.occupe) cls += ' taken';
            else { cls += ' free'; libres++; }

            // Créneau retenu pour le déplacement
            if (d !== null && f !== null && deb >= d && fin <= f) {
                cls += (deb === d || fin === f) ? ' edge' : ' mid';
            }
            // Créneau de la réservation qui bloque : bien visible
            let titre = c.occupe && c.titre ? ' title="Occupé : ' + c.titre.replace(/"/g, '&quot;') + '"' : '';
            if (memeSalleQueBloquante && deb >= enMinutes(BLOQUANTE.debut) && fin <= enMinutes(BLOQUANTE.fin)) {
                cls += ' bloquant';
                titre = ' title="' + BLOQUANTE.titre.replace(/"/g, '&quot;') + ' — à ne pas croiser"';
            }
            html += '<div class="' + cls + '" data-i="' + i + '"' + titre + '>' + c.debut + '</div>';
        });
        html += '</div>';
        strip.innerHTML = html;
        resume.textContent = libres + ' créneau' + (libres > 1 ? 'x' : '') + ' libre' + (libres > 1 ? 's' : '')
                           + ' sur ' + creneaux.length;

        strip.querySelectorAll('.slot.free').forEach(el => {
            el.addEventListener('click', () => poser(parseInt(el.dataset.i, 10)));
        });
    }

    /* ---- Clic sur un créneau libre : on y pose la réunion, durée conservée ---- */
    function poser(i) {
        const debut = enMinutes(creneaux[i].debut);
        const fin   = debut + DUREE_ORIGINE;

        // Toute la plage doit être libre et tenir dans les horaires d'ouverture
        const dernier = enMinutes(creneaux[creneaux.length - 1].fin);
        if (fin > dernier) {
            RS.toast('La réunion (' + DUREE_ORIGINE + ' min) dépasserait l\'heure de fermeture.', 'bad');
            return;
        }
        const occupe = creneaux.some(c =>
            !c.passe && c.occupe && enMinutes(c.debut) < fin && enMinutes(c.fin) > debut);
        if (occupe) {
            RS.toast('Cette plage traverse un créneau déjà occupé.', 'bad');
            return;
        }

        champDebut.value = enHeure(debut);
        champFin.value   = enHeure(fin);
        [champDebut, champFin].forEach(c => c.dispatchEvent(new Event('change', { bubbles: true })));
        dessinerDispos();
        majComparatif();
        RS.toast('Créneau proposé : ' + enHeure(debut) + ' → ' + enHeure(fin)
               + '. Enregistrez pour confirmer.', 'ok');
    }

    selSalle.addEventListener('change', () => { chargerDispos(); majComparatif(); });
    champDate.addEventListener('change', () => { chargerDispos(); majComparatif(); });
    [champDebut, champFin].forEach(c =>
        c.addEventListener('change', () => { dessinerDispos(); majComparatif(); }));

    majComparatif();
    chargerDispos();
})();

Valider.attacher('formReservation', {
    id_salle: [{ test: v => Valider.requis(v), message: 'Merci de choisir une salle.' }],
    titre: [
        { test: v => Valider.requis(v),           message: "L'objet est obligatoire." },
        { test: v => Valider.longueur(v, 3, 150), message: 'Entre 3 et 150 caractères.' }
    ],
    description: [{ test: v => v.trim().length <= 1000, message: '1000 caractères maximum.' }],
    date: [{ test: v => Valider.requis(v), message: 'La date est obligatoire.' }],
    nb_participants: [
        { test: v => Valider.requis(v),          message: 'Le nombre de participants est obligatoire.' },
        { test: v => Valider.entier(v, 1, 1000), message: 'Entier entre 1 et 1000.' },
        { test: (v, f) => {
            const opt = f.querySelector('#id_salle').selectedOptions[0];
            const cap = opt ? parseInt(opt.getAttribute('data-capacite'), 10) : null;
            return !cap || parseInt(v, 10) <= cap;
          },
          message: 'Le nombre de participants dépasse la capacité de la salle.' }
    ],
    heure_debut: [
        { test: v => Valider.requis(v), message: "L'heure de début est obligatoire." },
        { test: (v, f) => {
            const opt = f.querySelector('#id_salle').selectedOptions[0];
            const ouv = opt ? opt.getAttribute('data-ouverture') : null;
            return !ouv || v >= ouv;
          },
          message: "Le début est avant l'heure d'ouverture de la salle." }
    ],
    heure_fin: [
        { test: v => Valider.requis(v), message: "L'heure de fin est obligatoire." },
        { test: (v, f) => v > f.querySelector('#heure_debut').value, message: 'La fin doit être après le début.' },
        { test: (v, f) => Valider.dureeCreneau(f.querySelector("#heure_debut").value, v, 15, 720),
          message: 'La durée doit être comprise entre 15 minutes et 12 heures.' },
        { test: (v, f) => {
            const opt = f.querySelector('#id_salle').selectedOptions[0];
            const fer = opt ? opt.getAttribute('data-fermeture') : null;
            return !fer || v <= fer;
          },
          message: "La fin dépasse l'heure de fermeture de la salle." }
    ]
});
</script>
