<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin']);

$salleC = new SalleC();
$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    flash_set('error', 'Identifiant de salle invalide.');
    redirect('listSalle.php');
}

try {
    $salle = $salleC->getSalle($id);
    if ($salle === null) {
        flash_set('error', 'Salle introuvable.');
    } else {
        $salleC->deleteSalle($id);
        flash_set('success', 'Salle « ' . $salle['nom'] . ' » supprimée, avec ses réservations associées.');
    }
} catch (Throwable $e) {
    flash_set('error', 'Suppression impossible : ' . $e->getMessage());
}

redirect('listSalle.php');
