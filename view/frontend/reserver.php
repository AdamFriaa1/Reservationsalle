<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_connexion();

$salleC       = new SalleC();
$reservationC = new ReservationC();
$utilisateurC = new UtilisateurC();
$mailC        = new MailC();

$erreurs  = [];
$conflits = [];
$salles   = [];

$idSalle = isset($_GET['salle']) && ctype_digit((string)$_GET['salle']) ? (int)$_GET['salle'] : 0;
$jour    = $_GET['jour'] ?? '';

$donnees = [
    'id_salle'        => $idSalle,
    'titre'           => '',
    'description'     => '',
    'date_debut'      => $_GET['debut'] ?? ($jour !== '' ? $jour . 'T09:00' : ''),
    'date_fin'        => $_GET['fin']   ?? ($jour !== '' ? $jour . 'T10:00' : ''),
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
            $erreurs[] = 'Les dates de début et de fin sont obligatoires.';
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

// Décompose les dates complètes en « jour + heure » pour les champs du formulaire.
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

<div class="page container">

    <div class="page-header">
        <h1><i class="fas fa-calendar-plus"></i> Demander une réservation</h1>
        <p>Votre demande sera transmise au gestionnaire pour validation.</p>
    </div>

    <?php flash_afficher(); ?>

    <?php if ($erreurs): ?>
        <div class="alert alert-danger">
            <i class="fas fa-circle-exclamation"></i>
            <div>
                <strong>La demande n'a pas pu être enregistrée :</strong>
                <ul><?php foreach ($erreurs as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($conflits): ?>
        <div class="alert alert-warning">
            <i class="fas fa-triangle-exclamation"></i>
            <div>
                <strong>Créneau déjà occupé :</strong>
                <ul>
                    <?php foreach ($conflits as $c): ?>
                        <li>
                            « <?= e($c['titre']) ?> » — <?= e(fmt_datetime($c['date_debut'])) ?>
                            à <?= e(fmt_heure($c['date_fin'])) ?>
                            (<span class="badge <?= classe_statut($c['statut']) ?>"><?= e(libelle_statut($c['statut'])) ?></span>)
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <?php if (!empty($alternatives)): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <h2><i class="fas fa-lightbulb"></i> Salles libres sur ce même créneau</h2>
                </div>
                <div class="card-body">
                    <div class="grid grid-3">
                        <?php foreach (array_slice($alternatives, 0, 3) as $alt): ?>
                            <div class="card"><div class="card-body">
                                <h3 style="font-size:1rem;"><?= e($alt['nom']) ?></h3>
                                <div class="cell-sub mb-2"><?= e($alt['nom_batiment']) ?> · <?= (int)$alt['capacite'] ?> places</div>
                                <a href="reserver.php?salle=<?= (int)$alt['id_salle'] ?>&debut=<?= e($donnees['date_debut']) ?>&fin=<?= e($donnees['date_fin']) ?>"
                                   class="btn btn-primary btn-sm btn-block">Choisir cette salle</a>
                            </div></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="grid" style="grid-template-columns: 1fr 340px; align-items:start;">

        <div class="card">
            <div class="card-header"><h2><i class="fas fa-pen-to-square"></i> Détails de la réunion</h2></div>
            <div class="card-body">
                <form method="post" id="formReservation" novalidate>
                    <?= csrf_field() ?>

                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="id_salle">Salle <span class="req">*</span></label>
                            <select id="id_salle" name="id_salle">
                                <option value="">— Choisir une salle —</option>
                                <?php foreach ($salles as $s): ?>
                                    <option value="<?= (int)$s['id_salle'] ?>"
                                            data-capacite="<?= (int)$s['capacite'] ?>"
                                            data-ouverture="<?= e(substr($s['heure_ouverture'], 0, 5)) ?>"
                                            data-fermeture="<?= e(substr($s['heure_fermeture'], 0, 5)) ?>"
                                        <?= (string)$donnees['id_salle'] === (string)$s['id_salle'] ? 'selected' : '' ?>>
                                        <?= e($s['nom']) ?> — <?= e($s['code_salle']) ?>
                                        (<?= (int)$s['capacite'] ?> pl., <?= e($s['nom_batiment']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="erreur-champ" id="err-id_salle"></span>
                        </div>

                        <div class="form-group full">
                            <label for="titre">Objet de la réunion <span class="req">*</span></label>
                            <input type="text" id="titre" name="titre" value="<?= e($donnees['titre']) ?>"
                                   placeholder="Comité de pilotage projet X">
                            <span class="erreur-champ" id="err-titre"></span>
                        </div>

                        <div class="form-group full">
                            <label for="date_jour">Jour de la réunion <span class="req">*</span></label>
                            <input type="date" id="date_jour" name="date_jour"
                                   value="<?= e($vJour) ?>" min="<?= e($minJour) ?>">
                            <span class="aide">Choisissez d'abord la date : les heures ci-dessous s'appliquent à ce jour-là.</span>
                            <span class="erreur-champ" id="err-date_jour"></span>
                        </div>

                        <div class="form-group">
                            <label for="heure_debut">Heure de début <span class="req">*</span></label>
                            <input type="time" id="heure_debut" name="heure_debut" value="<?= e($vHeureDebut) ?>" step="300">
                            <span class="erreur-champ" id="err-heure_debut"></span>
                        </div>

                        <div class="form-group">
                            <label for="heure_fin">Heure de fin <span class="req">*</span></label>
                            <input type="time" id="heure_fin" name="heure_fin" value="<?= e($vHeureFin) ?>" step="300">
                            <span class="erreur-champ" id="err-heure_fin"></span>
                        </div>

                        <input type="hidden" id="date_debut" name="date_debut" value="<?= e($donnees['date_debut']) ?>">
                        <input type="hidden" id="date_fin"   name="date_fin"   value="<?= e($donnees['date_fin']) ?>">

                        <div class="form-group">
                            <label for="nb_participants">Participants <span class="req">*</span></label>
                            <input type="number" id="nb_participants" name="nb_participants" min="1" max="1000"
                                   value="<?= e($donnees['nb_participants']) ?>">
                            <span class="aide" id="aideCapacite"></span>
                            <span class="erreur-champ" id="err-nb_participants"></span>
                        </div>

                        <div class="form-group full">
                            <label for="description">Description</label>
                            <textarea id="description" name="description"
                                      placeholder="Ordre du jour, matériel particulier…"><?= e($donnees['description']) ?></textarea>
                            <span class="aide">Facultatif — 1000 caractères maximum.</span>
                            <span class="erreur-champ" id="err-description"></span>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Envoyer la demande
                        </button>
                        <a href="salles.php" class="btn btn-light">Annuler</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2><i class="fas fa-circle-info"></i> Salle sélectionnée</h2></div>
            <div class="card-body" id="panneauSalle">
                <?php if ($salleChoisie === null): ?>
                    <p class="text-muted">Choisissez une salle pour voir ses caractéristiques.</p>
                <?php else: ?>
                    <?php $photoChoisie = photo_salle_url($salleChoisie['image'] ?? null); ?>
                    <?php if ($photoChoisie): ?>
                        <img src="<?= e($photoChoisie) ?>" alt="Photo de <?= e($salleChoisie['nom']) ?>" class="salle-photo-panneau">
                    <?php endif; ?>
                    <h3 style="font-size:1.05rem;margin-bottom:4px;"><?= e($salleChoisie['nom']) ?></h3>
                    <p class="cell-sub mb-3"><?= e($salleChoisie['nom_batiment']) ?> · <?= e($salleChoisie['nom_etage']) ?></p>
                    <div class="detail-liste">
                        <div class="detail-ligne"><span class="k">Code</span><span class="v"><?= e($salleChoisie['code_salle']) ?></span></div>
                        <div class="detail-ligne"><span class="k">Capacité</span><span class="v"><?= (int)$salleChoisie['capacite'] ?> personnes</span></div>
                        <div class="detail-ligne"><span class="k">Type</span><span class="v"><?= e(libelle_type_salle($salleChoisie['type_salle'])) ?></span></div>
                        <div class="detail-ligne"><span class="k">Ouverture</span><span class="v"><?= e(substr($salleChoisie['heure_ouverture'], 0, 5)) ?> – <?= e(substr($salleChoisie['heure_fermeture'], 0, 5)) ?></span></div>
                        <div class="detail-ligne"><span class="k">Annulation</span><span class="v">jusqu'à <?= (int)$salleChoisie['delai_annulation'] ?> h avant</span></div>
                        <?php if (!empty($salleChoisie['localisation'])): ?>
                            <div class="detail-ligne"><span class="k">Localisation</span><span class="v"><?= e($salleChoisie['localisation']) ?></span></div>
                        <?php endif; ?>
                    </div>
                    <?php $eq = liste_equipements($salleChoisie['equipements']); ?>
                    <?php if ($eq): ?>
                        <div class="equip-list mt-3"><?php foreach ($eq as $x): ?><span class="equip"><?= e($x) ?></span><?php endforeach; ?></div>
                    <?php endif; ?>
                    <a href="calendrier.php?salle=<?= (int)$salleChoisie['id_salle'] ?>" class="btn btn-light btn-sm btn-block mt-3">
                        <i class="fas fa-calendar"></i> Voir les disponibilités
                    </a>
                <?php endif; ?>
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

function echapper(s) {
    return String(s).replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

/* ---- Panneau « Salle sélectionnée » : rendu immédiat, sans recharger ---- */
function rendrePanneau() {
    const info = SALLES_INFOS[selectSalle.value];
    if (!info) {
        panneauSalle.innerHTML =
            '<p class="text-muted">Choisissez une salle pour voir ses caractéristiques.</p>';
        return;
    }
    let html = '';
    if (info.photo) {
        html += '<img src="' + echapper(info.photo) + '" alt="Photo de ' + echapper(info.nom)
             + '" class="salle-photo-panneau">';
    }
    html += '<h3 style="font-size:1.05rem;margin-bottom:4px;">' + echapper(info.nom) + '</h3>';
    html += '<p class="cell-sub mb-3">' + echapper(info.batiment) + ' · ' + echapper(info.etage) + '</p>';
    html += '<div class="detail-liste">'
         +  ligne('Code', info.code)
         +  ligne('Capacité', info.capacite + ' personnes')
         +  ligne('Type', info.type)
         +  ligne('Ouverture', info.ouverture + ' – ' + info.fermeture)
         +  ligne('Annulation', "jusqu'à " + info.annulation + ' h avant')
         + (info.localisation ? ligne('Localisation', info.localisation) : '')
         + (info.pmr ? ligne('Accès', 'Accessible PMR') : '')
         +  '</div>';
    if (info.equipements && info.equipements.length) {
        html += '<div class="equip-list mt-3">'
             +  info.equipements.map(x => '<span class="equip">' + echapper(x) + '</span>').join('')
             +  '</div>';
    }
    html += '<a href="calendrier.php?salle=' + encodeURIComponent(selectSalle.value)
         +  '" class="btn btn-light btn-sm btn-block mt-3">'
         +  '<i class="fas fa-calendar"></i> Voir les disponibilités</a>';
    panneauSalle.innerHTML = html;
}
function ligne(k, v) {
    return '<div class="detail-ligne"><span class="k">' + echapper(k)
         + '</span><span class="v">' + echapper(v) + '</span></div>';
}

/* ---- Capacité + bornes horaires selon la salle ---- */
function majSalle() {
    const info = SALLES_INFOS[selectSalle.value];
    if (info) {
        aideCapacite.textContent = 'Capacité maximale : ' + info.capacite + ' personnes ('
            + info.ouverture + '–' + info.fermeture + ').';
        document.getElementById('nb_participants').max = info.capacite;
        champHDebut.min = info.ouverture; champHDebut.max = info.fermeture;
        champHFin.min   = info.ouverture; champHFin.max   = info.fermeture;
    } else {
        aideCapacite.textContent = '';
    }
    rendrePanneau();
}
selectSalle.addEventListener('change', majSalle);
majSalle();

/* ---- Jour unique + deux heures → champs cachés date_debut / date_fin ---- */
function composerDates() {
    const j = champJour.value;
    champDebut.value = (j && champHDebut.value) ? j + 'T' + champHDebut.value : '';
    champFin.value   = (j && champHFin.value)   ? j + 'T' + champHFin.value   : '';
}
/* En choisissant l'heure de début, on propose une fin une heure plus tard. */
champHDebut.addEventListener('change', () => {
    if (champHDebut.value && !champHFin.value) {
        const [h, m] = champHDebut.value.split(':').map(Number);
        const fin = new Date(2000, 0, 1, h + 1, m);
        champHFin.value = String(fin.getHours()).padStart(2, '0') + ':'
                        + String(fin.getMinutes()).padStart(2, '0');
    }
    composerDates();
});
[champJour, champHFin].forEach(c => c.addEventListener('change', composerDates));
document.getElementById('formReservation').addEventListener('submit', composerDates);
composerDates();

Valider.attacher('formReservation', {
    id_salle: [
        { test: v => Valider.requis(v), message: 'Merci de choisir une salle.' }
    ],
    titre: [
        { test: v => Valider.requis(v),       message: "L'objet de la réunion est obligatoire." },
        { test: v => Valider.texte(v, 3, 150),message: 'Entre 3 et 150 caractères, pas uniquement des chiffres.' }
    ],
    date_jour: [
        { test: v => Valider.requis(v), message: 'Choisissez le jour de la réunion.' },
        { test: v => v >= '<?= e($minJour) ?>', message: 'Impossible de réserver un jour passé.' }
    ],
    heure_debut: [
        { test: v => Valider.requis(v), message: "Indiquez l'heure de début." }
    ],
    heure_fin: [
        { test: v => Valider.requis(v), message: "Indiquez l'heure de fin." },
        { test: (v, f) => Valider.minutesEntreHeures(f.querySelector('#heure_debut').value, v) > 0,
          message: "La fin doit être après le début." },
        { test: (v, f) => Valider.dureeCreneau(f.querySelector('#heure_debut').value, v, 15, 720),
          message: 'Durée comprise entre 15 minutes et 12 heures.' }
    ],
    nb_participants: [
        { test: v => Valider.requis(v),        message: 'Le nombre de participants est obligatoire.' },
        { test: v => Valider.entier(v, 1, 1000), message: 'Entier entre 1 et 1000.' },
        { test: (v) => {
            const info = SALLES_INFOS[selectSalle.value];
            const cap = info ? info.capacite : 0;
            return cap === 0 || parseInt(v, 10) <= cap;
          }, message: 'Ce nombre dépasse la capacité de la salle choisie.' }
    ],
    description: [
        { test: v => v.trim().length <= 1000, message: '1000 caractères maximum.' }
    ]
});
</script>
