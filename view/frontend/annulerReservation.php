<?php
/**
 * Annulation d'une réservation par son propriétaire.
 * La ligne est conservée en base avec le statut « annulee » (historique),
 * et un email de notification est envoyé.
 */
require_once dirname(__DIR__, 2) . '/init.php';
exiger_connexion();

$reservationC = new ReservationC();
$mailC        = new MailC();

$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
if ($id === 0) {
    flash_set('error', 'Réservation introuvable.');
    redirect('mesReservations.php');
}

try {
    $reservation = $reservationC->getReservation($id);

    if ($reservation === null) {
        flash_set('error', 'Réservation introuvable.');
    } elseif ((int)$reservation['id_utilisateur'] !== id_courant()) {
        flash_set('error', "Vous ne pouvez annuler que vos propres réservations.");
    } elseif (!$reservationC->peutEtreModifiee($reservation)) {
        flash_set('error', 'Le délai d\'annulation est dépassé (limite : '
            . (int)$reservation['delai_annulation'] . ' h avant le début).');
    } else {
        $reservationC->changerStatut($id, 'annulee', id_courant(), null);
        $mailC->notifierAnnulation($reservation);
        flash_set('success', 'La réservation « ' . $reservation['titre'] . ' » a été annulée.');
    }
} catch (Throwable $e) {
    flash_set('error', "Erreur lors de l'annulation : " . $e->getMessage());
}

redirect('mesReservations.php');
