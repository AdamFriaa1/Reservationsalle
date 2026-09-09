<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin']);

$utilisateurC = new UtilisateurC();

$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
if ($id === 0) { flash_set('error', 'Utilisateur introuvable.'); redirect('listUtilisateur.php'); }

$existant = $utilisateurC->getUtilisateur($id);
if ($existant === null) { flash_set('error', 'Utilisateur introuvable.'); redirect('listUtilisateur.php'); }

$erreurs = [];
$donnees = [
    'nom'         => $existant['nom'],
    'prenom'      => $existant['prenom'],
    'email'       => $existant['email'],
    'telephone'   => (string)$existant['telephone'],
    'departement' => (string)$existant['departement'],
    'role'        => $existant['role'],
    'statut'      => $existant['statut'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_valide()) {
        $erreurs[] = 'Session expirée, merci de renvoyer le formulaire.';
    } else {
        foreach ($donnees as $cle => $_) {
            $donnees[$cle] = trim($_POST[$cle] ?? '');
        }
        $motDePasse = $_POST['mot_de_passe'] ?? '';
        $confirmMdp = $_POST['confirmation'] ?? '';

        if (!v_requis($donnees['nom']))              $erreurs[] = 'Le nom est obligatoire.';
        elseif (!v_longueur($donnees['nom'], 2, 60)) $erreurs[] = 'Le nom doit contenir entre 2 et 60 caractères.';

        if (!v_requis($donnees['prenom']))              $erreurs[] = 'Le prénom est obligatoire.';
        elseif (!v_longueur($donnees['prenom'], 2, 60)) $erreurs[] = 'Le prénom doit contenir entre 2 et 60 caractères.';

        if (!v_requis($donnees['email']))    $erreurs[] = "L'adresse email est obligatoire.";
        elseif (!v_email($donnees['email'])) $erreurs[] = "L'adresse email n'est pas valide.";
        elseif ($utilisateurC->emailExiste($donnees['email'], $id)) $erreurs[] = 'Cette adresse email est déjà utilisée par un autre compte.';

        if ($donnees['telephone'] !== '' && !v_telephone($donnees['telephone'])) {
            $erreurs[] = 'Le numéro de téléphone est invalide (8 à 15 chiffres).';
        }
        if ($donnees['departement'] !== '' && !v_longueur($donnees['departement'], 2, 80)) {
            $erreurs[] = 'Le département doit contenir entre 2 et 80 caractères.';
        }
        if (!v_dans(['admin', 'gestionnaire', 'utilisateur'], $donnees['role'])) $erreurs[] = 'Rôle invalide.';
        if (!v_dans(['actif', 'inactif'], $donnees['statut']))                   $erreurs[] = 'Statut invalide.';

        // Un administrateur ne doit pas se retirer ses propres droits ni se désactiver
        if ($id === id_courant()) {
            if ($donnees['role'] !== 'admin')   $erreurs[] = 'Vous ne pouvez pas modifier votre propre rôle.';
            if ($donnees['statut'] !== 'actif') $erreurs[] = 'Vous ne pouvez pas désactiver votre propre compte.';
        }

        // Le mot de passe n'est changé que s'il est renseigné
        if ($motDePasse !== '') {
            if (!v_mot_de_passe($motDePasse))    $erreurs[] = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
            elseif ($motDePasse !== $confirmMdp) $erreurs[] = 'La confirmation ne correspond pas au nouveau mot de passe.';
        }

        if (!$erreurs) {
            try {
                $u = new Utilisateur();
                $u->setNom($donnees['nom']);
                $u->setPrenom($donnees['prenom']);
                $u->setEmail($donnees['email']);
                $u->setTelephone($donnees['telephone'] !== '' ? $donnees['telephone'] : null);
                $u->setDepartement($donnees['departement'] !== '' ? $donnees['departement'] : null);
                $u->setRole($donnees['role']);
                $u->setStatut($donnees['statut']);

                $utilisateurC->updateUtilisateur($u, $id);

                if ($motDePasse !== '') {
                    $utilisateurC->updateMotDePasse($id, $motDePasse);
                }

                // Si l'admin modifie son propre compte, la session est rafraîchie
                if ($id === id_courant()) {
                    $_SESSION['utilisateur'] = $utilisateurC->getUtilisateur($id);
                }

                flash_set('success', 'Compte de ' . $donnees['prenom'] . ' ' . $donnees['nom'] . ' mis à jour'
                    . ($motDePasse !== '' ? ', mot de passe réinitialisé.' : '.'));
                redirect('listUtilisateur.php');
            } catch (Throwable $e) {
                $erreurs[] = 'Erreur lors de la mise à jour : ' . $e->getMessage();
            }
        }
    }
}

$titrePage  = 'Modifier le compte';
$pageActive = 'utilisateurs';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <div class="fil-ariane"><a href="index.php">Tableau de bord</a> / <a href="listUtilisateur.php">Utilisateurs</a> / Modifier</div>
        <h2><i class="fas fa-user-pen"></i> Modifier « <?= e($existant['prenom'] . ' ' . $existant['nom']) ?> »</h2>
        <p>Inscrit le <?= e(fmt_date($existant['date_creation'])) ?> · <?= e(libelle_role($existant['role'])) ?></p>
    </div>
    <a href="listUtilisateur.php" class="btn btn-light"><i class="fas fa-arrow-left"></i> Retour</a>
</div>

<?php if ($erreurs): ?>
    <div class="alert alert-danger">
        <i class="fas fa-circle-exclamation"></i>
        <div>
            <strong>Merci de corriger :</strong>
            <ul><?php foreach ($erreurs as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    </div>
<?php endif; ?>

<?php if ($id === id_courant()): ?>
    <div class="alert alert-info">
        <i class="fas fa-circle-info"></i>
        <span>Il s'agit de votre propre compte : le rôle et le statut ne sont pas modifiables ici.</span>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-id-card"></i> Informations</h3></div>
    <div class="card-body">
        <form method="post" id="formUtilisateur" novalidate>
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="prenom">Prénom <span class="req">*</span></label>
                    <input type="text" id="prenom" name="prenom" value="<?= e($donnees['prenom']) ?>">
                    <span class="erreur-champ" id="err-prenom"></span>
                </div>
                <div class="form-group">
                    <label for="nom">Nom <span class="req">*</span></label>
                    <input type="text" id="nom" name="nom" value="<?= e($donnees['nom']) ?>">
                    <span class="erreur-champ" id="err-nom"></span>
                </div>
                <div class="form-group">
                    <label for="email">Adresse email <span class="req">*</span></label>
                    <input type="email" id="email" name="email" value="<?= e($donnees['email']) ?>">
                    <span class="erreur-champ" id="err-email"></span>
                </div>
                <div class="form-group">
                    <label for="telephone">Téléphone</label>
                    <input type="tel" id="telephone" name="telephone" value="<?= e($donnees['telephone']) ?>">
                    <span class="erreur-champ" id="err-telephone"></span>
                </div>
                <div class="form-group">
                    <label for="departement">Département</label>
                    <input type="text" id="departement" name="departement" value="<?= e($donnees['departement']) ?>">
                    <span class="erreur-champ" id="err-departement"></span>
                </div>
                <div class="form-group">
                    <label for="role">Rôle <span class="req">*</span></label>
                    <select id="role" name="role" <?= $id === id_courant() ? 'disabled' : '' ?>>
                        <option value="utilisateur"  <?= $donnees['role'] === 'utilisateur' ? 'selected' : '' ?>>Utilisateur</option>
                        <option value="gestionnaire" <?= $donnees['role'] === 'gestionnaire' ? 'selected' : '' ?>>Gestionnaire de réservations</option>
                        <option value="admin"        <?= $donnees['role'] === 'admin' ? 'selected' : '' ?>>Administrateur bâtiments</option>
                    </select>
                    <?php if ($id === id_courant()): ?>
                        <input type="hidden" name="role" value="<?= e($donnees['role']) ?>">
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="statut">Statut <span class="req">*</span></label>
                    <select id="statut" name="statut" <?= $id === id_courant() ? 'disabled' : '' ?>>
                        <option value="actif"   <?= $donnees['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                        <option value="inactif" <?= $donnees['statut'] === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                    </select>
                    <?php if ($id === id_courant()): ?>
                        <input type="hidden" name="statut" value="<?= e($donnees['statut']) ?>">
                    <?php endif; ?>
                </div>
            </div>

            <h4 class="section-titre"><i class="fas fa-key"></i> Réinitialiser le mot de passe</h4>
            <p class="text-muted mb-2" style="font-size:.88rem;">
                Laissez ces deux champs vides pour conserver le mot de passe actuel.
            </p>

            <div class="form-grid">
                <div class="form-group">
                    <label for="mot_de_passe">Nouveau mot de passe</label>
                    <input type="password" id="mot_de_passe" name="mot_de_passe" autocomplete="new-password">
                    <span class="aide">6 caractères minimum.</span>
                    <span class="erreur-champ" id="err-mot_de_passe"></span>
                </div>
                <div class="form-group">
                    <label for="confirmation">Confirmation</label>
                    <input type="password" id="confirmation" name="confirmation" autocomplete="new-password">
                    <span class="erreur-champ" id="err-confirmation"></span>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Enregistrer</button>
                <a href="listReservation.php?utilisateur=<?= (int)$id ?>" class="btn btn-light">
                    <i class="fas fa-calendar-check"></i> Ses réservations
                </a>
                <a href="listUtilisateur.php" class="btn btn-light">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>

<script>
Valider.attacher('formUtilisateur', {
    prenom: [
        { test: v => Valider.requis(v),       message: 'Le prénom est obligatoire.' },
        { test: v => Valider.texte(v, 2, 60), message: 'Entre 2 et 60 caractères, sans chiffres.' }
    ],
    nom: [
        { test: v => Valider.requis(v),       message: 'Le nom est obligatoire.' },
        { test: v => Valider.texte(v, 2, 60), message: 'Entre 2 et 60 caractères, sans chiffres.' }
    ],
    email: [
        { test: v => Valider.requis(v), message: "L'adresse email est obligatoire." },
        { test: v => Valider.email(v),  message: "Format d'email invalide." }
    ],
    telephone: [
        { test: v => v.trim() === '' || Valider.telephone(v), message: 'Numéro invalide (8 à 15 chiffres).' }
    ],
    departement: [
        { test: v => v.trim() === '' || Valider.longueur(v, 2, 80), message: 'Entre 2 et 80 caractères.' }
    ],
    mot_de_passe: [
        { test: v => v === '' || Valider.motDePasse(v), message: '6 caractères minimum.' }
    ],
    confirmation: [
        { test: (v, f) => f.querySelector('#mot_de_passe').value === '' || v === f.querySelector('#mot_de_passe').value,
          message: 'Les deux mots de passe ne correspondent pas.' }
    ]
});
</script>
