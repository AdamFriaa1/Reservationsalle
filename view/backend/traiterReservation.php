<?php
/**
 * Traitement d'une demande de réservation par un gestionnaire :
 *  - validation  (GET  : ?id=…&action=valider)
 *  - refus       (POST : action=refuser + motif_refus, via la modale)
 * Chaque décision déclenche une notification email au demandeur.
 */
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin', 'gestionnaire']);

$reservationC = new ReservationC();
$mailC        = new MailC();

$retour = $_SERVER['HTTP_REFERER'] ?? 'listReservation.php';
// On ne redirige que vers une page interne du back-office
if (!preg_match('#^[A-Za-z0-9_\-\.]+\.php(\?.*)?$#', basename($retour))) {
    $retour = 'listReservation.php';
} else {
    $retour = basename($retour);
}

// ---------------------------------------------------------------
//  REFUS (POST, avec motif obligatoire)
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'refuser') {

    if (!csrf_valide()) {
        flash_set('error', 'Session expirée, merci de recommencer.');
        redirect($retour);
    }

    $id    = isset($_POST['id']) && ctype_digit((string)$_POST['id']) ? (int)$_POST['id'] : 0;
    $motif = trim($_POST['motif_refus'] ?? '');

    if ($id === 0) {
        flash_set('error', 'Réservation introuvable.');
        redirect($retour);
    }
    if (!v_requis($motif)) {
        flash_set('error', 'Le motif du refus est obligatoire.');
        redirect($retour);
    }
    if (!v_longueur($motif, 10, 500)) {
        flash_set('error', 'Le motif doit contenir entre 10 et 500 caractères.');
        redirect($retour);
    }

    try {
        $r = $reservationC->getReservation($id);
        if ($r === null) {
            flash_set('error', 'Réservation introuvable.');
        } elseif (in_array($r['statut'], ['refusee', 'annulee'], true)) {
            flash_set('error', 'Cette réservation est déjà refusée ou annulée.');
        } else {
            // Une demande déjà validée est « annulée », une demande en attente est « refusée ».
            $nouveauStatut = $r['statut'] === 'validee' ? 'annulee' : 'refusee';
            $reservationC->changerStatut($id, $nouveauStatut, id_courant(), $motif);

            $r['motif_refus'] = $motif;
            if ($nouveauStatut === 'refusee') {
                $mailC->notifierRefus($r, $motif);
                flash_set('success', 'Demande « ' . $r['titre'] . ' » refusée, le demandeur a été notifié.');
            } else {
                $mailC->notifierAnnulation($r);
                flash_set('success', 'Réservation « ' . $r['titre'] . ' » annulée, le demandeur a été notifié.');
            }
        }
    } catch (Throwable $e) {
        flash_set('error', 'Traitement impossible : ' . $e->getMessage());
    }

    redirect($retour);
}

// ---------------------------------------------------------------
//  VALIDATION (GET)
// ---------------------------------------------------------------
$id     = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? '';

if ($id === 0 || $action !== 'valider') {
    flash_set('error', 'Action invalide.');
    redirect($retour);
}

try {
    $r = $reservationC->getReservation($id);

    if ($r === null) {
        flash_set('error', 'Réservation introuvable.');
    } elseif ($r['statut'] === 'validee') {
        flash_set('error', 'Cette réservation est déjà validée.');
    } elseif (in_array($r['statut'], ['annulee', 'terminee'], true)) {
        flash_set('error', 'Une réservation annulée ou terminée ne peut plus être validée.');
    } else {
        // Dernier contrôle de chevauchement avant validation
        $conflits = $reservationC->detecterConflits(
            (int)$r['id_salle'], $r['date_debut'], $r['date_fin'], $id, ['validee']);

        if ($conflits) {
            $premier = $conflits[0];
            flash_set('error', 'Validation refusée : la salle « ' . $r['nom_salle']
                . ' » est déjà occupée sur ce créneau par « ' . $premier['titre'] . ' » ('
                . fmt_heure($premier['date_debut']) . ' – ' . fmt_heure($premier['date_fin'])
                . '). Déplacez l\'une des deux réunions.');
        } elseif ($r['etat_salle'] !== 'disponible') {
            flash_set('error', 'La salle « ' . $r['nom_salle'] . ' » n\'est pas disponible ('
                . libelle_etat_salle($r['etat_salle']) . ').');
        } else {
            $reservationC->changerStatut($id, 'validee', id_courant(), null);
            $mailC->notifierValidation($r);
            flash_set('success', 'Réservation « ' . $r['titre'] . ' » validée, le demandeur a été notifié.');
        }
    }
} catch (Throwable $e) {
    flash_set('error', 'Validation impossible : ' . $e->getMessage());
}

redirect($retour);
