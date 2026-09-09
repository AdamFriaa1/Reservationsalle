<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin']);

$batimentC = new BatimentC();
$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    flash_set('error', 'Identifiant de bâtiment invalide.');
    redirect('listBatiment.php');
}

try {
    $b = $batimentC->getBatiment($id);
    if ($b === null) {
        flash_set('error', 'Bâtiment introuvable.');
    } else {
        $nbSalles = $batimentC->compterSalles($id);
        $batimentC->deleteBatiment($id);
        flash_set('success', 'Bâtiment « ' . $b['nom'] . ' » supprimé'
            . ($nbSalles > 0 ? ', ainsi que ses ' . $nbSalles . ' salle(s).' : '.'));
    }
} catch (Throwable $e) {
    flash_set('error', 'Suppression impossible : ' . $e->getMessage());
}

redirect('listBatiment.php');
