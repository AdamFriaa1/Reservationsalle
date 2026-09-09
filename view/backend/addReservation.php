<?php
/**
 * Réservation manuelle : le gestionnaire réserve une salle au nom d'un utilisateur.
 * La réservation est directement validée (le gestionnaire fait autorité).
 */
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin', 'gestionnaire']);

$reservationC = new ReservationC();
$salleC       = new SalleC();
$utilisateurC = new UtilisateurC();
$mailC        = new MailC();

$erreurs   = [];
$conflits  = [];
$donnees   = [
    'id_utilisateur'  => '',
    'id_salle'        => $_GET['salle'] ?? '',
    'titre'           => '',
    'description'     => '',
    'date'            => $_GET['date'] ?? date('Y-m-d'),
    'heure_debut'     => '09:00',
    'heure_fin'       => '10:00',
    'nb_participants' => '',
    'statut'          => 'validee',
];

try {
    $salles       = $salleC->getSallesDisponibles();
    $utilisateurs = $utilisateurC->getUtilisateursActifs();
} catch (Throwable $e) {
    $erreurs[] = 'Erreur de chargement : ' . $e->getMessage();
    $salles = []; $utilisateurs = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_valide()) {
        $erreurs[] = 'Session expirée, merci de renvoyer le formulaire.';
    } else {
        foreach ($donnees as $cle => $_) {
            $donnees[$cle] = trim($_POST[$cle] ?? '');
        }

        // ---- Contrôles de saisie côté serveur ----
        if (!ctype_digit((string)$donnees['id_utilisateur'])) $erreurs[] = 'Merci de choisir le demandeur.';
        if (!ctype_digit((string)$donnees['id_salle']))       $erreurs[] = 'Merci de choisir une salle.';

        if (!v_requis($donnees['titre']))                $erreurs[] = "L'objet de la réunion est obligatoire.";
        elseif (!v_longueur($donnees['titre'], 3, 150))  $erreurs[] = "L'objet doit contenir entre 3 et 150 caractères.";

        if ($donnees['description'] !== '' && mb_strlen($donnees['description']) > 1000) {
            $erreurs[] = 'La description ne doit pas dépasser 1000 caractères.';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $donnees['date'])) {
            $erreurs[] = 'La date est invalide.';
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $donnees['heure_debut'])) {
            $erreurs[] = "L'heure de début est invalide.";
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $donnees['heure_fin'])) {
            $erreurs[] = "L'heure de fin est invalide.";
        }
        if (!v_entier($donnees['nb_participants'], 1, 1000)) {
            $erreurs[] = 'Le nombre de participants doit être un entier entre 1 et 1000.';
        }
        if (!v_dans(['validee', 'en_attente'], $donnees['statut'])) {
            $erreurs[] = 'Statut initial invalide.';
        }

        if (!$erreurs) {
            $debut = $donnees['date'] . ' ' . $donnees['heure_debut'] . ':00';
            $fin   = $donnees['date'] . ' ' . $donnees['heure_fin'] . ':00';

            $salle = $salleC->getSalle((int)$donnees['id_salle']);

            if ($salle === null) {
                $erreurs[] = 'La salle sélectionnée est introuvable.';
            } else {
                // Validation métier complète (horaires, capacité, chevauchements…)
                $erreursMetier = $reservationC->validerCreneau(
                    $salle, $debut, $fin, (int)$donnees['nb_participants']);
                $erreurs = array_merge($erreurs, $erreursMetier);

                if ($erreursMetier) {
                    $conflits = $reservationC->detecterConflits((int)$salle['id_salle'], $debut, $fin);
                }
            }

            if (!$erreurs) {
                try {
                    $r = new Reservation();
                    $r->setIdSalle((int)$donnees['id_salle']);
                    $r->setIdUtilisateur((int)$donnees['id_utilisateur']);
                    $r->setTitre($donnees['titre']);
                    $r->setDescription($donnees['description'] !== '' ? $donnees['description'] : null);
                    $r->setDateDebut($debut);
                    $r->setDateFin($fin);
                    $r->setNbParticipants((int)$donnees['nb_participants']);
                    $r->setStatut($donnees['statut']);
                    if ($donnees['statut'] === 'validee') {
                        $r->setIdValidateur(id_courant());
                    }

                    $id = $reservationC->addReservation($r);

                    // Notification au bénéficiaire de la réservation
                    $creee = $reservationC->getReservation($id);
                    if ($creee !== null) {
                        if ($donnees['statut'] === 'validee') {
                            $mailC->notifierValidation($creee);
                        } else {
                            $mailC->notifierDemande($creee);
                        }
                    }

                    flash_set('success', 'Réservation « ' . $donnees['titre'] . ' » créée'
                        . ($donnees['statut'] === 'validee' ? ' et validée.' : ' en attente de validation.'));
                    redirect('listReservation.php');
                } catch (Throwable $e) {
                    $erreurs[] = 'Erreur lors de la création : ' . $e->getMessage();
                }
            }
        }
    }
}

$titrePage  = 'Réservation manuelle';
$pageActive = 'reservation-manuelle';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <div class="fil-ariane"><a href="index.php">Tableau de bord</a> / <a href="listReservation.php">Réservations</a> / Manuelle</div>
        <h2><i class="fas fa-calendar-plus"></i> Réservation manuelle</h2>
        <p>Réservez une salle au nom d'un utilisateur (demande téléphonique, réunion imposée…).</p>
    </div>
    <a href="listReservation.php" class="btn btn-light"><i class="fas fa-arrow-left"></i> Retour</a>
</div>

<?php flash_afficher(); ?>

<?php if ($erreurs): ?>
    <div class="alert alert-danger">
        <i class="fas fa-circle-exclamation"></i>
        <div>
            <strong>Merci de corriger :</strong>
            <ul><?php foreach ($erreurs as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    </div>
<?php endif; ?>

<?php if ($conflits): ?>
    <div class="card mb-3" style="border-left:4px solid var(--rouge);">
        <div class="card-header"><h3><i class="fas fa-triangle-exclamation"></i> Créneaux déjà occupés</h3></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Objet</th><th>Demandeur</th><th>Créneau</th><th>Statut</th></tr></thead>
                <tbody>
                <?php foreach ($conflits as $c): ?>
                    <tr>
                        <td class="cell-titre"><?= e($c['titre']) ?></td>
                        <td><?= e($c['prenom_utilisateur'] . ' ' . $c['nom_utilisateur']) ?></td>
                        <td class="mono"><?= e(fmt_heure($c['date_debut'])) ?> – <?= e(fmt_heure($c['date_fin'])) ?></td>
                        <td><span class="badge <?= classe_statut($c['statut']) ?>"><?= e(libelle_statut($c['statut'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if (!$salles): ?>
    <div class="alert alert-warning">
        <i class="fas fa-triangle-exclamation"></i>
        <span>Aucune salle disponible. <a href="addSalle.php">Créez une salle</a> ou vérifiez les états de maintenance.</span>
    </div>
<?php else: ?>

<div class="grid" style="grid-template-columns: 2fr 1fr; gap: 1.2rem; align-items: start;">

    <form method="post" id="formReservation" novalidate>
        <?= csrf_field() ?>

        <div class="card">
            <div class="card-header"><h3><i class="fas fa-user"></i> Demandeur et salle</h3></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="id_utilisateur">Réserver au nom de <span class="req">*</span></label>
                        <select id="id_utilisateur" name="id_utilisateur">
                            <option value="">— Choisir un utilisateur —</option>
                            <?php foreach ($utilisateurs as $u): ?>
                                <option value="<?= (int)$u['id_utilisateur'] ?>"
                                    <?= (string)$donnees['id_utilisateur'] === (string)$u['id_utilisateur'] ? 'selected' : '' ?>>
                                    <?= e($u['prenom'] . ' ' . $u['nom']) ?><?= !empty($u['departement']) ? ' — ' . e($u['departement']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="aide">Cette personne recevra les notifications par email.</span>
                        <span class="erreur-champ" id="err-id_utilisateur"></span>
                    </div>

                    <div class="form-group">
                        <label for="id_salle">Salle <span class="req">*</span></label>
                        <select id="id_salle" name="id_salle">
                            <option value="">— Choisir une salle —</option>
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
                        <span class="erreur-champ" id="err-id_salle"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3><i class="fas fa-circle-info"></i> Détails de la réunion</h3></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group full">
                        <label for="titre">Objet de la réunion <span class="req">*</span></label>
                        <input type="text" id="titre" name="titre" value="<?= e($donnees['titre']) ?>"
                               placeholder="Comité de direction mensuel">
                        <span class="erreur-champ" id="err-titre"></span>
                    </div>
                    <div class="form-group full">
                        <label for="description">Description</label>
                        <textarea id="description" name="description"
                                  placeholder="Ordre du jour, matériel nécessaire…"><?= e($donnees['description']) ?></textarea>
                        <span class="erreur-champ" id="err-description"></span>
                    </div>
                    <div class="form-group">
                        <label for="date">Date <span class="req">*</span></label>
                        <input type="date" id="date" name="date" value="<?= e($donnees['date']) ?>"
                               min="<?= date('Y-m-d') ?>">
                        <span class="erreur-champ" id="err-date"></span>
                    </div>
                    <div class="form-group">
                        <label for="nb_participants">Participants <span class="req">*</span></label>
                        <input type="number" id="nb_participants" name="nb_participants" min="1" max="1000"
                               value="<?= e($donnees['nb_participants']) ?>">
                        <span class="aide" id="aide-capacite">Choisissez d'abord une salle.</span>
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
                    <div class="form-group full">
                        <label for="statut">Statut initial <span class="req">*</span></label>
                        <select id="statut" name="statut">
                            <option value="validee"    <?= $donnees['statut'] === 'validee' ? 'selected' : '' ?>>Validée immédiatement</option>
                            <option value="en_attente" <?= $donnees['statut'] === 'en_attente' ? 'selected' : '' ?>>En attente de validation</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-calendar-check"></i> Créer la réservation</button>
                    <a href="listReservation.php" class="btn btn-light">Annuler</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-header"><h3><i class="fas fa-circle-question"></i> Rappels</h3></div>
        <div class="card-body">
            <ul class="liste-info">
                <li><i class="fas fa-clock"></i> Le créneau doit tenir dans les horaires d'ouverture de la salle.</li>
                <li><i class="fas fa-hourglass"></i> Durée entre 15 minutes et 12 heures, sur une même journée.</li>
                <li><i class="fas fa-users"></i> Le nombre de participants ne peut pas dépasser la capacité.</li>
                <li><i class="fas fa-triangle-exclamation"></i> Tout chevauchement avec une réservation en attente ou validée est bloqué.</li>
                <li><i class="fas fa-envelope"></i> Le demandeur reçoit un email de confirmation.</li>
            </ul>
            <a href="calendrier.php" class="btn btn-light btn-block mt-3">
                <i class="fas fa-calendar"></i> Consulter le planning global
            </a>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>

<script>
// Affiche la capacité de la salle choisie et borne le nombre de participants
(function () {
    const selSalle = document.getElementById('id_salle');
    const champNb  = document.getElementById('nb_participants');
    const aide     = document.getElementById('aide-capacite');
    if (!selSalle || !champNb || !aide) return;

    function majCapacite() {
        const opt = selSalle.options[selSalle.selectedIndex];
        const cap = opt ? opt.getAttribute('data-capacite') : null;
        if (cap) {
            champNb.max = cap;
            aide.textContent = 'Capacité de la salle : ' + cap + ' personnes. Ouverture '
                + opt.getAttribute('data-ouverture') + ' – ' + opt.getAttribute('data-fermeture') + '.';
        } else {
            aide.textContent = "Choisissez d'abord une salle.";
        }
    }
    selSalle.addEventListener('change', majCapacite);
    majCapacite();
})();

Valider.attacher('formReservation', {
    id_utilisateur: [{ test: v => Valider.requis(v), message: 'Merci de choisir le demandeur.' }],
    id_salle:       [{ test: v => Valider.requis(v), message: 'Merci de choisir une salle.' }],
    titre: [
        { test: v => Valider.requis(v),           message: "L'objet est obligatoire." },
        { test: v => Valider.longueur(v, 3, 150), message: 'Entre 3 et 150 caractères.' }
    ],
    description: [{ test: v => v.trim().length <= 1000, message: '1000 caractères maximum.' }],
    date: [
        { test: v => Valider.requis(v),      message: 'La date est obligatoire.' },
        { test: v => Valider.dateFuture(v),  message: 'La date ne peut pas être dans le passé.' }
    ],
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
        { test: (v, f) => v > f.querySelector('#heure_debut').value,
          message: "La fin doit être après le début." },
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
