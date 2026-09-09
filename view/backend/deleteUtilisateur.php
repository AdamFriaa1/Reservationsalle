<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin']);

$utilisateurC = new UtilisateurC();
$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    flash_set('error', "Identifiant d'utilisateur invalide.");
    redirect('listUtilisateur.php');
}
if ($id === id_courant()) {
    flash_set('error', 'Vous ne pouvez pas supprimer votre propre compte.');
    redirect('listUtilisateur.php');
}

try {
    $u = $utilisateurC->getUtilisateur($id);
    if ($u === null) {
        flash_set('error', 'Utilisateur introuvable.');
    } else {
        $utilisateurC->deleteUtilisateur($id);
        flash_set('success', 'Compte de ' . $u['prenom'] . ' ' . $u['nom'] . ' supprimé.');
    }
} catch (Throwable $e) {
    flash_set('error', 'Suppression impossible : ' . $e->getMessage());
}

redirect('listUtilisateur.php');
