<?php
require_once dirname(__DIR__, 2) . '/init.php';
exiger_role(['admin']);

$utilisateurC = new UtilisateurC();

$erreurs = [];
$donnees = [
    'nom' => '', 'prenom' => '', 'email' => '', 'telephone' => '',
    'departement' => '', 'role' => 'utilisateur', 'statut' => 'actif',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_valide()) {
        $erreurs[] = 'Session expirée, merci de renvoyer le formulaire.';
    } else {
        foreach ($donnees as $cle => $_) {
            $donnees[$cle] = trim($_POST[$cle] ?? '');
        }
        $motDePasse  = $_POST['mot_de_passe'] ?? '';
        $confirmMdp  = $_POST['confirmation'] ?? '';

        // ---- Contrôles de saisie côté serveur ----
        if (!v_requis($donnees['nom']))              $erreurs[] = 'Le nom est obligatoire.';
        elseif (!v_longueur($donnees['nom'], 2, 60)) $erreurs[] = 'Le nom doit contenir entre 2 et 60 caractères.';

        if (!v_requis($donnees['prenom']))              $erreurs[] = 'Le prénom est obligatoire.';
        elseif (!v_longueur($donnees['prenom'], 2, 60)) $erreurs[] = 'Le prénom doit contenir entre 2 et 60 caractères.';

        if (!v_requis($donnees['email']))            $erreurs[] = "L'adresse email est obligatoire.";
        elseif (!v_email($donnees['email']))         $erreurs[] = "L'adresse email n'est pas valide.";
        elseif ($utilisateurC->emailExiste($donnees['email'])) $erreurs[] = 'Cette adresse email est déjà utilisée.';

        if ($donnees['telephone'] !== '' && !v_telephone($donnees['telephone'])) {
            $erreurs[] = 'Le numéro de téléphone est invalide (8 à 15 chiffres).';
        }
        if ($donnees['departement'] !== '' && !v_longueur($donnees['departement'], 2, 80)) {
            $erreurs[] = 'Le département doit contenir entre 2 et 80 caractères.';
        }
        if (!v_dans(['admin', 'gestionnaire', 'utilisateur'], $donnees['role'])) $erreurs[] = 'Rôle invalide.';
        if (!v_dans(['actif', 'inactif'], $donnees['statut']))                   $erreurs[] = 'Statut invalide.';

        if (!v_requis($motDePasse)) {
            $erreurs[] = 'Le mot de passe est obligatoire.';
        } elseif (!v_mot_de_passe($motDePasse)) {
            $erreurs[] = 'Le mot de passe doit contenir au moins 6 caractères.';
        } elseif ($motDePasse !== $confirmMdp) {
            $erreurs[] = 'La confirmation ne correspond pas au mot de passe.';
        }

        if (!$erreurs) {
            try {
                $u = new Utilisateur();
                $u->setNom($donnees['nom']);
                $u->setPrenom($donnees['prenom']);
                $u->setEmail($donnees['email']);
                $u->hasherMotDePasse($motDePasse);
                $u->setTelephone($donnees['telephone'] !== '' ? $donnees['telephone'] : null);
                $u->setDepartement($donnees['departement'] !== '' ? $donnees['departement'] : null);
                $u->setRole($donnees['role']);
                $u->setStatut($donnees['statut']);

                $utilisateurC->addUtilisateur($u);
                flash_set('success', 'Compte de ' . $donnees['prenom'] . ' ' . $donnees['nom']
                    . ' créé (' . libelle_role($donnees['role']) . ').');
                redirect('listUtilisateur.php');
            } catch (Throwable $e) {
                $erreurs[] = 'Erreur lors de la création : ' . $e->getMessage();
            }
        }
    }
}

$titrePage  = 'Nouveau compte';
$pageActive = 'utilisateurs';
require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <div class="fil-ariane"><a href="index.php">Tableau de bord</a> / <a href="listUtilisateur.php">Utilisateurs</a> / Nouveau</div>
        <h2><i class="fas fa-user-plus"></i> Nouveau compte</h2>
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

<div class="card">
    <div class="card-header"><h3><i class="fas fa-id-card"></i> Informations du compte</h3></div>
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
                    <input type="email" id="email" name="email" value="<?= e($donnees['email']) ?>"
                           placeholder="prenom.nom@reserva.tn">
                    <span class="aide">Sert d'identifiant de connexion.</span>
                    <span class="erreur-champ" id="err-email"></span>
                </div>
                <div class="form-group">
                    <label for="telephone">Téléphone</label>
                    <input type="tel" id="telephone" name="telephone" value="<?= e($donnees['telephone']) ?>"
                           placeholder="+216 71 000 000">
                    <span class="erreur-champ" id="err-telephone"></span>
                </div>
                <div class="form-group">
                    <label for="departement">Département</label>
                    <input type="text" id="departement" name="departement" value="<?= e($donnees['departement']) ?>"
                           placeholder="Ressources Humaines">
                    <span class="erreur-champ" id="err-departement"></span>
                </div>
                <div class="form-group">
                    <label for="role">Rôle <span class="req">*</span></label>
                    <select id="role" name="role">
                        <option value="utilisateur"  <?= $donnees['role'] === 'utilisateur' ? 'selected' : '' ?>>Utilisateur</option>
                        <option value="gestionnaire" <?= $donnees['role'] === 'gestionnaire' ? 'selected' : '' ?>>Gestionnaire de réservations</option>
                        <option value="admin"        <?= $donnees['role'] === 'admin' ? 'selected' : '' ?>>Administrateur bâtiments</option>
                    </select>
                    <span class="aide">Détermine les pages accessibles dans le back-office.</span>
                </div>
                <div class="form-group">
                    <label for="mot_de_passe">Mot de passe <span class="req">*</span></label>
                    <input type="password" id="mot_de_passe" name="mot_de_passe">
                    <span class="aide">6 caractères minimum.</span>
                    <span class="erreur-champ" id="err-mot_de_passe"></span>
                </div>
                <div class="form-group">
                    <label for="confirmation">Confirmation <span class="req">*</span></label>
                    <input type="password" id="confirmation" name="confirmation">
                    <span class="erreur-champ" id="err-confirmation"></span>
                </div>
                <div class="form-group">
                    <label for="statut">Statut <span class="req">*</span></label>
                    <select id="statut" name="statut">
                        <option value="actif"   <?= $donnees['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                        <option value="inactif" <?= $donnees['statut'] === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Créer le compte</button>
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
        { test: v => Valider.requis(v),     message: 'Le mot de passe est obligatoire.' },
        { test: v => Valider.motDePasse(v), message: '6 caractères minimum.' }
    ],
    confirmation: [
        { test: v => Valider.requis(v), message: 'Merci de confirmer le mot de passe.' },
        { test: (v, f) => v === f.querySelector('#mot_de_passe').value,
          message: 'Les deux mots de passe ne correspondent pas.' }
    ]
});
</script>
