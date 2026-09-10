<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_connexion();

$salleC       = new SalleC();
$reservationC = new ReservationC();
$utilisateurC = new UtilisateurC();
$mailC        = new MailC();

$erreurs      = [];
$conflits     = [];
$alternatives = [];
$salles       = [];

$idSalle = isset($_GET['salle']) && ctype_digit((string)$_GET['salle']) ? (int)$_GET['salle'] : 0;
$jour    = (isset($_GET['jour']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['jour'])) ? $_GET['jour'] : '';
$hd      = (isset($_GET['hd']) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $_GET['hd'])) ? $_GET['hd'] : '';
$hf      = (isset($_GET['hf']) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $_GET['hf'])) ? $_GET['hf'] : '';

$donnees = [
    'id_salle'        => $idSalle,
    'titre'           => '',
    'description'     => '',
    'date_debut'      => $_GET['debut'] ?? ($jour !== '' ? $jour . 'T' . ($hd !== '' ? $hd : '09:00') : ''),
    'date_fin'        => $_GET['fin']   ?? ($jour !== '' ? $jour . 'T' . ($hf !== '' ? $hf : '10:00') : ''),
    'nb_participants' => 1,
];

try {
    $salles = $salleC->getSallesDisponibles();
} catch (Throwable $e) {
    $erreurs[] = 'Impossible de charger la liste des salles : ' . $e->getMessage();
}

// =====================================================================
//  TRAITEMENT DU FORMULAIRE
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_valide()) {
        $erreurs[] = 'Session expirée, merci de renvoyer le formulaire.';
    } else {
        $donnees['id_salle']        = $_POST['id_salle'] ?? '';
        $donnees['titre']           = trim($_POST['titre'] ?? '');
        $donnees['description']     = trim($_POST['description'] ?? '');
        $donnees['date_debut']      = trim($_POST['date_debut'] ?? '');
        $donnees['date_fin']        = trim($_POST['date_fin'] ?? '');
        $donnees['nb_participants'] = $_POST['nb_participants'] ?? '';

        // Le formulaire saisit un jour unique + deux heures ; on recompose
        // les dates complètes ici (utile si JavaScript est indisponible).
        $jourPoste = trim($_POST['date_jour'] ?? '');
        $heureDeb  = trim($_POST['heure_debut'] ?? '');
        $heureFin  = trim($_POST['heure_fin'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $jourPoste)) {
            if ($donnees['date_debut'] === '' && preg_match('/^\d{2}:\d{2}$/', $heureDeb)) {
                $donnees['date_debut'] = $jourPoste . 'T' . $heureDeb;
            }
            if ($donnees['date_fin'] === '' && preg_match('/^\d{2}:\d{2}$/', $heureFin)) {
                $donnees['date_fin'] = $jourPoste . 'T' . $heureFin;
            }
        }

        // ---- Contrôles de saisie côté serveur ----
        if (!ctype_digit((string)$donnees['id_salle']) || (int)$donnees['id_salle'] < 1) {
            $erreurs[] = 'Merci de choisir une salle.';
        }
        if (!v_requis($donnees['titre'])) {
            $erreurs[] = "L'objet de la réunion est obligatoire.";
        } elseif (!v_longueur($donnees['titre'], 3, 150)) {
            $erreurs[] = "L'objet doit contenir entre 3 et 150 caractères.";
        }
        if ($donnees['description'] !== '' && !v_longueur($donnees['description'], 0, 1000)) {
            $erreurs[] = 'La description ne doit pas dépasser 1000 caractères.';
        }
        if (!v_requis($donnees['date_debut']) || !v_requis($donnees['date_fin'])) {
            $erreurs[] = 'Le jour et les heures de début et de fin sont obligatoires.';
        }
        if (!v_entier($donnees['nb_participants'], 1, 1000)) {
            $erreurs[] = 'Le nombre de participants doit être un entier entre 1 et 1000.';
        }

        // ---- Validation métier (horaires, capacité, chevauchement) ----
        if (!$erreurs) {
            $salle = $salleC->getSalle((int)$donnees['id_salle']);
            if ($salle === null) {
                $erreurs[] = 'Salle introuvable.';
            } else {
                $debutSql = str_replace('T', ' ', $donnees['date_debut']) . ':00';
                $finSql   = str_replace('T', ' ', $donnees['date_fin']) . ':00';

                $erreursMetier = $reservationC->validerCreneau(
                    $salle, $debutSql, $finSql, (int)$donnees['nb_participants']
                );
                $erreurs = array_merge($erreurs, $erreursMetier);

                // Propositions de rechange en cas de conflit
                if ($erreursMetier) {
                    $conflits = $reservationC->detecterConflits((int)$salle['id_salle'], $debutSql, $finSql);
                    if ($conflits) {
                        try {
                            $alternatives = $salleC->getSallesLibres($debutSql, $finSql, (int)$donnees['nb_participants']);
                        } catch (Throwable $e) {
                            $alternatives = [];
                        }
                    }
                }

                // ---- Enregistrement ----
                if (!$erreurs) {
                    try {
                        $r = new Reservation();
                        $r->setIdSalle((int)$donnees['id_salle']);
                        $r->setIdUtilisateur(id_courant());
                        $r->setTitre($donnees['titre']);
                        $r->setDescription($donnees['description'] !== '' ? $donnees['description'] : null);
                        $r->setDateDebut($debutSql);
                        $r->setDateFin($finSql);
                        $r->setNbParticipants((int)$donnees['nb_participants']);
                        $r->setStatut('en_attente');

                        $id = $reservationC->addReservation($r);

                        // ---- Notifications par email ----
                        $complete = $reservationC->getReservation($id);
                        if ($complete !== null) {
                            $mailC->notifierDemande($complete);
                            $mailC->alerterGestionnaires($utilisateurC->getEmailsGestionnaires(), $complete);
                        }

                        flash_set('success',
                            'Votre demande a été enregistrée. Vous recevrez un email dès qu\'un gestionnaire l\'aura traitée.');
                        redirect('mesReservations.php');

                    } catch (Throwable $e) {
                        $erreurs[] = 'Erreur lors de l\'enregistrement : ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

$salleChoisie = null;
if (ctype_digit((string)$donnees['id_salle']) && (int)$donnees['id_salle'] > 0) {
    try { $salleChoisie = $salleC->getSalle((int)$donnees['id_salle']); } catch (Throwable $e) { $salleChoisie = null; }
}

// Décompose les dates complètes en « jour + heures » pour les champs.
$vJour = $vHeureDebut = $vHeureFin = '';
if (preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/', (string)$donnees['date_debut'], $m)) {
    $vJour = $m[1]; $vHeureDebut = $m[2];
}
if (preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/', (string)$donnees['date_fin'], $m)) {
    if ($vJour === '') $vJour = $m[1];
    $vHeureFin = $m[2];
}
$minJour = date('Y-m-d');

// Caractéristiques de toutes les salles, exposées au JavaScript pour un
// affichage immédiat dès qu'une salle est choisie (sans recharger la page).
$sallesInfos = [];
foreach ($salles as $s) {
    $sallesInfos[(int)$s['id_salle']] = [
        'nom'          => $s['nom'],
        'code'         => $s['code_salle'],
        'batiment'     => $s['nom_batiment'],
        'etage'        => $s['nom_etage'],
        'capacite'     => (int)$s['capacite'],
        'type'         => libelle_type_salle($s['type_salle']),
        'ouverture'    => substr((string)$s['heure_ouverture'], 0, 5),
        'fermeture'    => substr((string)$s['heure_fermeture'], 0, 5),
        'annulation'   => (int)$s['delai_annulation'],
        'localisation' => (string)($s['localisation'] ?? ''),
        'equipements'  => liste_equipements($s['equipements'] ?? null),
        'photo'        => photo_salle_url($s['image'] ?? null),
        'pmr'          => (int)($s['accessible_pmr'] ?? 0) === 1,
    ];
}

$titrePage  = 'Nouvelle réservation';
$pageActive = 'reserver';
require __DIR__ . '/partials/header.php';
?>

<div class="page-head">
    <div>
        <span class="eyebrow">Demande</span>
        <h1 class="mt-2">Demander une réservation</h1>
        <p>Votre demande part au gestionnaire, qui la valide ou la refuse. Vous êtes prévenu par email dans les deux cas.</p>
    </div>
    <a href="calendrier.php<?= $salleChoisie ? '?salle=' . (int)$salleChoisie['id_salle'] : '' ?>" class="btn">
        <i class="fas fa-calendar-days"></i> Voir les disponibilités
    </a>
</div>

<?php flash_afficher(); ?>

<?php if ($erreurs): ?>
    <div class="alert alert-bad">
        <i class="fas fa-circle-exclamation"></i>
        <div>
            <strong>La demande n'a pas pu être enregistrée :</strong>
            <ul><?php foreach ($erreurs as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    </div>
<?php endif; ?>

<?php if ($conflits): ?>
    <div class="alert alert-warn">
        <i class="fas fa-triangle-exclamation"></i>
        <div>
            <strong>Créneau déjà occupé :</strong>
            <ul>
                <?php foreach ($conflits as $c): ?>
                    <li>
                        « <?= e($c['titre']) ?> » — <?= e(fmt_datetime($c['date_debut'])) ?>
                        à <?= e(fmt_heure($c['date_fin'])) ?>
                        (<span class="badge <?= e(classe_statut($c['statut'])) ?>"><?= e(libelle_statut($c['statut'])) ?></span>)
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <?php if ($alternatives): ?>
        <div class="card mb-5">
            <div class="card-head">
                <h2><i class="fas fa-lightbulb"></i> Salles libres sur ce même créneau</h2>
            </div>
            <div class="card-body">
                <div class="grid g-cols-3">
                    <?php foreach (array_slice($alternatives, 0, 3) as $alt): ?>
                        <div class="panel">
                            <h3 style="font-size:var(--t-base)"><?= e($alt['nom']) ?></h3>
                            <div class="muted t-sm mb-4">
                                <?= e($alt['nom_batiment']) ?> · <?= (int)$alt['capacite'] ?> places
                            </div>
                            <a href="reserver.php?salle=<?= (int)$alt['id_salle'] ?>&debut=<?= e($donnees['date_debut']) ?>&fin=<?= e($donnees['date_fin']) ?>"
                               class="btn btn-primary btn-sm btn-block">Choisir cette salle</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="grid" style="grid-template-columns: minmax(0,1fr) 348px; align-items:start">

    <!-- ====================================== FORMULAIRE -->
    <div class="card">
        <div class="card-head"><h2><i class="fas fa-pen-to-square"></i> Détails de la réunion</h2></div>
        <div class="card-body">
            <form method="post" id="formReservation" novalidate>
                <?= csrf_field() ?>

                <div class="form-grid">
                    <div class="field full">
                        <label for="id_salle">Salle <span class="req">*</span></label>
                        <select id="id_salle" name="id_salle">
                            <option value="">— Choisir une salle —</option>
                            <?php foreach ($salles as $s): ?>
                                <option value="<?= (int)$s['id_salle'] ?>"
                                    <?= (string)$donnees['id_salle'] === (string)$s['id_salle'] ? 'selected' : '' ?>>
                                    <?= e($s['nom']) ?> — <?= e($s['code_salle']) ?>
                                    (<?= (int)$s['capacite'] ?> pl., <?= e($s['nom_batiment']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="err" id="err-id_salle"></span>
                    </div>

                    <div class="field full">
                        <label for="titre">Objet de la réunion <span class="req">*</span></label>
                        <input type="text" id="titre" name="titre" value="<?= e($donnees['titre']) ?>"
                               placeholder="Comité de pilotage projet X">
                        <span class="err" id="err-titre"></span>
                    </div>

                    <div class="field full">
                        <label for="date_jour">Jour de la réunion <span class="req">*</span></label>
                        <input type="date" id="date_jour" name="date_jour"
                               value="<?= e($vJour) ?>" min="<?= e($minJour) ?>">
                        <span class="hint">Le jour se choisit une seule fois : les deux heures ci-dessous s'y appliquent.</span>
                        <span class="err" id="err-date_jour"></span>
                    </div>

                    <div class="field">
                        <label for="heure_debut">Heure de début <span class="req">*</span></label>
                        <input type="time" id="heure_debut" name="heure_debut" value="<?= e($vHeureDebut) ?>" step="900">
                        <span class="err" id="err-heure_debut"></span>
                    </div>

                    <div class="field">
                        <label for="heure_fin">Heure de fin <span class="req">*</span></label>
                        <input type="time" id="heure_fin" name="heure_fin" value="<?= e($vHeureFin) ?>" step="900">
                        <span class="err" id="err-heure_fin"></span>
                    </div>

                    <input type="hidden" id="date_debut" name="date_debut" value="<?= e($donnees['date_debut']) ?>">
                    <input type="hidden" id="date_fin"   name="date_fin"   value="<?= e($donnees['date_fin']) ?>">

                    <div class="field">
                        <label for="nb_participants">Participants <span class="req">*</span></label>
                        <input type="number" id="nb_participants" name="nb_participants" min="1" max="1000"
                               value="<?= e($donnees['nb_participants']) ?>">
                        <span class="hint" id="aideCapacite"></span>
                        <span class="err" id="err-nb_participants"></span>
                    </div>

                    <div class="field full">
                        <label for="description">Description</label>
                        <textarea id="description" name="description"
                                  placeholder="Ordre du jour, matériel particulier…"><?= e($donnees['description']) ?></textarea>
                        <span class="hint">Facultatif — 1000 caractères maximum.</span>
                        <span class="err" id="err-description"></span>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-paper-plane"></i> Envoyer la demande
                    </button>
                    <a href="salles.php" class="btn btn-ghost">Annuler</a>
                </div>
            </form>
        </div>
    </div>

    <!-- ====================================== PANNEAU LATÉRAL -->
    <div class="stack g-5" style="position:sticky; top:calc(var(--topbar-h) + var(--s-5))">

        <!-- Récapitulatif vivant du créneau -->
        <div class="card" id="carteRecap" hidden>
            <div class="card-body">
                <span class="eyebrow">Créneau demandé</span>
                <div class="mt-3" style="font-family:var(--f-display);font-size:var(--t-xl);font-weight:750;
                                         letter-spacing:-.025em;font-variant-numeric:tabular-nums">
                    <span id="recapHeures">—</span>
                    <small class="muted" style="font-family:var(--f-body);font-size:var(--t-sm);font-weight:600;margin-left:8px"
                           id="recapDuree"></small>
                </div>
                <div class="muted t-sm mt-2" id="recapJour"></div>
                <div id="recapDispo" class="mt-4"></div>
            </div>
        </div>

        <!-- Caractéristiques de la salle -->
        <div class="card">
            <div class="card-head"><h2><i class="fas fa-circle-info"></i> Salle sélectionnée</h2></div>
            <div class="card-body" id="panneauSalle">
                <div class="empty" style="padding:26px 0">
                    <span class="empty-icon"><i class="fas fa-door-closed"></i></span>
                    <p class="t-sm">Choisissez une salle pour voir ses caractéristiques.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>

<script>
const SALLES_INFOS = <?= json_encode($sallesInfos, JSON_UNESCAPED_UNICODE) ?>;

const selectSalle  = document.getElementById('id_salle');
const aideCapacite = document.getElementById('aideCapacite');
const panneauSalle = document.getElementById('panneauSalle');
const champJour    = document.getElementById('date_jour');
const champHDebut  = document.getElementById('heure_debut');
const champHFin    = document.getElementById('heure_fin');
const champDebut   = document.getElementById('date_debut');
const champFin     = document.getElementById('date_fin');
const carteRecap   = document.getElementById('carteRecap');

function ech(s) {
    return String(s).replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
function ligne(k, v) {
    return '<div class="kv-row"><span class="k">' + ech(k) + '</span><span class="v">' + ech(v) + '</span></div>';
}

/* ---- Panneau « Salle sélectionnée » : rendu immédiat, sans rechargement ---- */
function rendrePanneau() {
    const info = SALLES_INFOS[selectSalle.value];
    if (!info) {
        panneauSalle.innerHTML =
            '<div class="empty" style="padding:26px 0">'
          + '<span class="empty-icon"><i class="fas fa-door-closed"></i></span>'
          + '<p class="t-sm">Choisissez une salle pour voir ses caractéristiques.</p></div>';
        return;
    }
    let h = '';
    if (info.photo) {
        h += '<img src="' + ech(info.photo) + '" alt="Photo de ' + ech(info.nom) + '" class="salle-photo-panneau">';
    }
    h += '<h3>' + ech(info.nom) + '</h3>';
    h += '<p class="muted t-sm mb-4">' + ech(info.batiment) + ' · ' + ech(info.etage) + '</p>';
    h += '<div class="kv">'
       + ligne('Code', info.code)
       + ligne('Capacité', info.capacite + ' personnes')
       + ligne('Type', info.type)
       + ligne('Ouverture', info.ouverture + ' – ' + info.fermeture)
       + ligne('Annulation', "jusqu'à " + info.annulation + ' h avant')
       + (info.localisation ? ligne('Localisation', info.localisation) : '')
       + (info.pmr ? ligne('Accès', 'Accessible PMR') : '')
       + '</div>';
    if (info.equipements && info.equipements.length) {
        h += '<div class="tags mt-4">'
           + info.equipements.map(x => '<span class="tag">' + ech(x) + '</span>').join('')
           + '</div>';
    }
    h += '<a href="calendrier.php?salle=' + encodeURIComponent(selectSalle.value)
       + '" class="btn btn-block mt-5"><i class="fas fa-calendar"></i> Voir les disponibilités</a>';
    panneauSalle.innerHTML = h;
}

/* ---- Capacité et bornes horaires selon la salle ---- */
function majSalle() {
    const info = SALLES_INFOS[selectSalle.value];
    if (info) {
        aideCapacite.textContent = 'Maximum ' + info.capacite + ' personnes · salle ouverte de '
                                 + info.ouverture + ' à ' + info.fermeture + '.';
        document.getElementById('nb_participants').max = info.capacite;
        champHDebut.min = info.ouverture; champHDebut.max = info.fermeture;
        champHFin.min   = info.ouverture; champHFin.max   = info.fermeture;
    } else {
        aideCapacite.textContent = '';
    }
    rendrePanneau();
    majRecap();
}

/* ---- Jour unique + deux heures → champs cachés + récapitulatif ---- */
function composerDates() {
    const j = champJour.value;
    champDebut.value = (j && champHDebut.value) ? j + 'T' + champHDebut.value : '';
    champFin.value   = (j && champHFin.value)   ? j + 'T' + champHFin.value   : '';
}

function majRecap() {
    composerDates();
    const j = champJour.value, a = champHDebut.value, b = champHFin.value;
    if (!j || !a || !b) { carteRecap.hidden = true; return; }

    const mins = Valider.minutesEntreHeures(a, b);
    if (isNaN(mins) || mins <= 0) { carteRecap.hidden = true; return; }

    carteRecap.hidden = false;
    const hh = Math.floor(mins / 60), mm = mins % 60;
    document.getElementById('recapHeures').textContent = a + ' → ' + b;
    document.getElementById('recapDuree').textContent =
        hh ? (mm ? hh + ' h ' + String(mm).padStart(2, '0') : hh + ' h') : mm + ' min';

    const d = new Date(j + 'T00:00:00');
    const jours = ['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'];
    const mois  = ['janvier','février','mars','avril','mai','juin','juillet','août',
                   'septembre','octobre','novembre','décembre'];
    document.getElementById('recapJour').textContent =
        jours[d.getDay()] + ' ' + d.getDate() + ' ' + mois[d.getMonth()] + ' ' + d.getFullYear();

    verifierDispo();
}

/* ---- Vérification du chevauchement avant l'envoi (AJAX) ---- */
let jetonDispo = 0;
async function verifierDispo() {
    const zone = document.getElementById('recapDispo');
    const salle = selectSalle.value, j = champJour.value;
    const a = champHDebut.value, b = champHFin.value;
    if (!salle || !j || !a || !b) { zone.innerHTML = ''; return; }

    const monJeton = ++jetonDispo;
    zone.innerHTML = '<div class="skel" style="height:34px"></div>';
    try {
        const rep = await fetch('ajax/disponibilites.php?salle=' + encodeURIComponent(salle)
                              + '&date=' + encodeURIComponent(j));
        const data = await rep.json();
        if (monJeton !== jetonDispo) return;          // une saisie plus récente a pris le relais
        if (data.erreur) { zone.innerHTML = ''; return; }

        const pris = (data.creneaux || []).filter(c => c.occupe && c.debut >= a && c.debut < b);
        const horsPlage = (data.creneaux || []).length
            ? (a < data.creneaux[0].debut || b > data.creneaux[data.creneaux.length - 1].fin)
            : false;

        if (horsPlage) {
            zone.innerHTML = '<div class="alert alert-warn" style="margin:0">'
                + '<i class="fas fa-triangle-exclamation"></i>'
                + '<span>Ce créneau sort des horaires d\'ouverture de la salle.</span></div>';
        } else if (pris.length) {
            zone.innerHTML = '<div class="alert alert-bad" style="margin:0">'
                + '<i class="fas fa-circle-exclamation"></i>'
                + '<span>Déjà occupé sur ' + pris.length + ' créneau'
                + (pris.length > 1 ? 'x' : '') + ' (à partir de ' + pris[0].debut + ').</span></div>';
        } else {
            zone.innerHTML = '<div class="alert alert-ok" style="margin:0">'
                + '<i class="fas fa-circle-check"></i><span>Ce créneau est libre.</span></div>';
        }
    } catch (e) {
        if (monJeton === jetonDispo) zone.innerHTML = '';
    }
}

/* En choisissant l'heure de début, on propose une fin une heure plus tard. */
champHDebut.addEventListener('change', () => {
    if (champHDebut.value && !champHFin.value) {
        const [h, m] = champHDebut.value.split(':').map(Number);
        const fin = new Date(2000, 0, 1, h + 1, m);
        champHFin.value = String(fin.getHours()).padStart(2, '0') + ':'
                        + String(fin.getMinutes()).padStart(2, '0');
    }
    majRecap();
});
[champJour, champHFin].forEach(c => c.addEventListener('change', majRecap));
selectSalle.addEventListener('change', majSalle);
document.getElementById('formReservation').addEventListener('submit', composerDates);

majSalle();

Valider.attacher('formReservation', {
    id_salle: [
        { test: v => Valider.requis(v), message: 'Merci de choisir une salle.' }
    ],
    titre: [
        { test: v => Valider.requis(v),        message: "L'objet de la réunion est obligatoire." },
        { test: v => Valider.texte(v, 3, 150), message: 'Entre 3 et 150 caractères, pas uniquement des chiffres.' }
    ],
    date_jour: [
        { test: v => Valider.requis(v),      message: 'Choisissez le jour de la réunion.' },
        { test: v => Valider.jourNonPasse(v),message: 'Impossible de réserver un jour passé.' }
    ],
    heure_debut: [
        { test: v => Valider.requis(v), message: "Indiquez l'heure de début." },
        { test: (v) => {
            const info = SALLES_INFOS[selectSalle.value];
            return !info || Valider.dansPlage(v, info.ouverture, info.fermeture);
          }, message: "Cette heure est en dehors des horaires d'ouverture." }
    ],
    heure_fin: [
        { test: v => Valider.requis(v), message: "Indiquez l'heure de fin." },
        { test: (v, f) => Valider.minutesEntreHeures(f.querySelector('#heure_debut').value, v) > 0,
          message: "La fin doit être après le début." },
        { test: (v, f) => Valider.dureeCreneau(f.querySelector('#heure_debut').value, v, 15, 720),
          message: 'Durée comprise entre 15 minutes et 12 heures.' },
        { test: (v) => {
            const info = SALLES_INFOS[selectSalle.value];
            return !info || Valider.dansPlage(v, info.ouverture, info.fermeture);
          }, message: "Cette heure est en dehors des horaires d'ouverture." }
    ],
    nb_participants: [
        { test: v => Valider.requis(v),          message: 'Le nombre de participants est obligatoire.' },
        { test: v => Valider.entier(v, 1, 1000), message: 'Entier entre 1 et 1000.' },
        { test: (v) => {
            const info = SALLES_INFOS[selectSalle.value];
            return !info || parseInt(v, 10) <= info.capacite;
          }, message: 'Ce nombre dépasse la capacité de la salle choisie.' }
    ],
    description: [
        { test: v => v.trim().length <= 1000, message: '1000 caractères maximum.' }
    ]
});
</script>
