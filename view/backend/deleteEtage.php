<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin']);

$etageC = new EtageC();
$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
$retour = isset($_GET['batiment']) && ctype_digit((string)$_GET['batiment'])
    ? 'listEtage.php?batiment=' . (int)$_GET['batiment'] : 'listEtage.php';

if ($id === 0) {
    flash_set('error', "Identifiant d'étage invalide.");
    redirect($retour);
}

try {
    $etage = $etageC->getEtage($id);
    if ($etage === null) {
        flash_set('error', 'Étage introuvable.');
    } else {
        $nbSalles = $etageC->compterSalles($id);
        $etageC->deleteEtage($id);
        flash_set('success', 'Étage « ' . $etage['nom_etage'] . ' » supprimé'
            . ($nbSalles > 0 ? ', ainsi que ses ' . $nbSalles . ' salle(s).' : '.'));
    }
} catch (Throwable $e) {
    flash_set('error', 'Suppression impossible : ' . $e->getMessage());
}

redirect($retour);
