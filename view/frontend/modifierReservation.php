<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_connexion();

$reservationC = new ReservationC();
$salleC       = new SalleC();
$mailC        = new MailC();

$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
if ($id === 0) {
    flash_set('error', 'Réservation introuvable.');
    redirect('mesReservations.php');
}

$reservation = $reservationC->getReservation($id);
if ($reservation === null) {
    flash_set('error', 'Réservation introuvable.');
    redirect('mesReservations.php');
}

// L'utilisateur ne peut modifier que ses propres réservations
if ((int)$reservation['id_utilisateur'] !== id_courant()) {
    flash_set('error', "Vous ne pouvez modifier que vos propres réservations.");
    redirect('mesReservations.php');
}
if (!$reservationC->peutEtreModifiee($reservation)) {
    flash_set('error', 'Le délai de modification est dépassé (limite : '
        . (int)$reservation['delai_annulation'] . ' h avant le début).');
    redirect('mesReservations.php');
}

$erreurs = [];
$donnees = [
    'id_salle'        => (int)$reservation['id_salle'],
    'titre'           => $reservation['titre'],
    'description'     => (string)$reservation['description'],
    'date_debut'      => date('Y-m-d\TH:i', strtotime($reservation['date_debut'])),
    'date_fin'        => date('Y-m-d\TH:i', strtotime($reservation['date_fin'])),
    'nb_participants' => (int)$reservation['nb_participants'],
];

try { $salles = $salleC->getSallesDisponibles(); } catch (Throwable $e) { $salles = []; }

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

        if (!ctype_digit((string)$donnees['id_salle'])) $erreurs[] = 'Merci de choisir une salle.';
        if (!v_requis($donnees['titre']))               $erreurs[] = "L'objet est obligatoire.";
        elseif (!v_longueur($donnees['titre'], 3, 150)) $erreurs[] = "L'objet doit contenir entre 3 et 150 caractères.";
        if (!v_requis($donnees['date_debut']) || !v_requis($donnees['date_fin'])) {
            $erreurs[] = 'Les dates sont obligatoires.';
        }
        if (!v_entier($donnees['nb_participants'], 1, 1000)) {
            $erreurs[] = 'Le nombre de participants doit être un entier entre 1 et 1000.';
        }

        if (!$erreurs) {
            $salle = $salleC->getSalle((int)$donnees['id_salle']);
            if ($salle === null) {
                $erreurs[] = 'Salle introuvable.';
            } else {
                $debutSql = str_replace('T', ' ', $donnees['date_debut']) . ':00';
                $finSql   = str_replace('T', ' ', $donnees['date_fin']) . ':00';

                // On exclut la réservation courante de la détection de conflits
                $erreurs = array_merge($erreurs, $reservationC->validerCreneau(
                    $salle, $debutSql, $finSql, (int)$donnees['nb_participants'], $id
                ));

                if (!$erreurs) {
                    try {
                        $r = new Reservation();
                        $r->setIdSalle((int)$donnees['id_salle']);
                        $r->setTitre($donnees['titre']);
                        $r->setDescription($donnees['description'] !== '' ? $donnees['description'] : null);
                        $r->setDateDebut($debutSql);
                        $r->setDateFin($finSql);
                        $r->setNbParticipants((int)$donnees['nb_participants']);

                        $reservationC->updateReservation($r, $id);

                        // Une réservation modifiée repasse en attente de validation
                        if ($reservation['statut'] === 'validee') {
                            $reservationC->changerStatut($id, 'en_attente', null, null);
                        }

                        $maj = $reservationC->getReservation($id);
                        if ($maj !== null) {
                            $mailC->notifierDemande($maj);
                        }

                        flash_set('success', 'Votre réservation a été mise à jour et repasse en attente de validation.');
                        redirect('mesReservations.php');
                    } catch (Throwable $e) {
                        $erreurs[] = 'Erreur lors de la mise à jour : ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

$titrePage  = 'Modifier la réservation';
$pageActive = 'mes-reservations';
require __DIR__ . '/partials/header.php';
?>

<div class="page container">

    <div class="page-header">
        <h1><i class="fas fa-pen-to-square"></i> Modifier la réservation</h1>
        <p>Réservation n° <?= (int)$id ?> — créée le <?= e(fmt_datetime($reservation['date_creation'])) ?></p>
    </div>

    <?php if ($erreurs): ?>
        <div class="alert alert-danger">
            <i class="fas fa-circle-exclamation"></i>
            <div>
                <strong>Modification impossible :</strong>
                <ul><?php foreach ($erreurs as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </div>
        </div>
    <?php endif; ?>

    <div class="alert alert-info">
        <i class="fas fa-circle-info"></i>
        <span>Toute modification remet la réservation en attente de validation par le gestionnaire.</span>
    </div>

    <div class="card">
        <div class="card-header"><h2><i class="fas fa-sliders"></i> Détails</h2></div>
        <div class="card-body">
            <form method="post" id="formModif" novalidate>
                <?= csrf_field() ?>

                <div class="form-grid">
                    <div class="form-group full">
                        <label for="id_salle">Salle <span class="req">*</span></label>
                        <select id="id_salle" name="id_salle">
                            <?php foreach ($salles as $s): ?>
                                <option value="<?= (int)$s['id_salle'] ?>" data-capacite="<?= (int)$s['capacite'] ?>"
                                    <?= (string)$donnees['id_salle'] === (string)$s['id_salle'] ? 'selected' : '' ?>>
                                    <?= e($s['nom']) ?> — <?= e($s['code_salle']) ?>
                                    (<?= (int)$s['capacite'] ?> pl., <?= e($s['nom_batiment']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="erreur-champ" id="err-id_salle"></span>
                    </div>

                    <div class="form-group full">
                        <label for="titre">Objet <span class="req">*</span></label>
                        <input type="text" id="titre" name="titre" value="<?= e($donnees['titre']) ?>">
                        <span class="erreur-champ" id="err-titre"></span>
                    </div>

                    <div class="form-group">
                        <label for="date_debut">Début <span class="req">*</span></label>
                        <input type="datetime-local" id="date_debut" name="date_debut" value="<?= e($donnees['date_debut']) ?>">
                        <span class="erreur-champ" id="err-date_debut"></span>
                    </div>

                    <div class="form-group">
                        <label for="date_fin">Fin <span class="req">*</span></label>
                        <input type="datetime-local" id="date_fin" name="date_fin" value="<?= e($donnees['date_fin']) ?>">
                        <span class="erreur-champ" id="err-date_fin"></span>
                    </div>

                    <div class="form-group">
                        <label for="nb_participants">Participants <span class="req">*</span></label>
                        <input type="number" id="nb_participants" name="nb_participants" min="1" max="1000"
                               value="<?= e($donnees['nb_participants']) ?>">
                        <span class="erreur-champ" id="err-nb_participants"></span>
                    </div>

                    <div class="form-group full">
                        <label for="description">Description</label>
                        <textarea id="description" name="description"><?= e($donnees['description']) ?></textarea>
                        <span class="erreur-champ" id="err-description"></span>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Enregistrer</button>
                    <a href="mesReservations.php" class="btn btn-light">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>

<script>
const selectSalle = document.getElementById('id_salle');

Valider.attacher('formModif', {
    id_salle: [{ test: v => Valider.requis(v), message: 'Merci de choisir une salle.' }],
    titre: [
        { test: v => Valider.requis(v),        message: "L'objet est obligatoire." },
        { test: v => Valider.texte(v, 3, 150), message: 'Entre 3 et 150 caractères.' }
    ],
    date_debut: [
        { test: v => Valider.requis(v),     message: 'La date de début est obligatoire.' },
        { test: v => Valider.dateFuture(v), message: 'Impossible de choisir un créneau passé.' }
    ],
    date_fin: [
        { test: v => Valider.requis(v), message: 'La date de fin est obligatoire.' },
        { test: (v, f) => Valider.apres(v, f.querySelector('#date_debut').value),
          message: 'La fin doit être après le début.' },
        { test: (v, f) => Valider.memeJour(f.querySelector('#date_debut').value, v),
          message: 'Le début et la fin doivent tomber le même jour.' },
        { test: (v, f) => Valider.dureeMinutes(f.querySelector('#date_debut').value, v) >= 15,
          message: 'Durée minimale : 15 minutes.' }
    ],
    nb_participants: [
        { test: v => Valider.entier(v, 1, 1000), message: 'Entier entre 1 et 1000.' },
        { test: (v) => {
            const opt = selectSalle.options[selectSalle.selectedIndex];
            const cap = opt ? parseInt(opt.dataset.capacite || '0', 10) : 0;
            return cap === 0 || parseInt(v, 10) <= cap;
          }, message: 'Ce nombre dépasse la capacité de la salle.' }
    ],
    description: [{ test: v => v.trim().length <= 1000, message: '1000 caractères maximum.' }]
});
</script>
