<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin', 'gestionnaire']);

$reservationC = new ReservationC();
$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    flash_set('error', 'Identifiant de réservation invalide.');
    redirect('listReservation.php');
}

try {
    $r = $reservationC->getReservation($id);
    if ($r === null) {
        flash_set('error', 'Réservation introuvable.');
    } else {
        $reservationC->deleteReservation($id);
        flash_set('success', 'Réservation « ' . $r['titre'] . ' » supprimée définitivement.');
    }
} catch (Throwable $e) {
    flash_set('error', 'Suppression impossible : ' . $e->getMessage());
}

redirect('listReservation.php');
