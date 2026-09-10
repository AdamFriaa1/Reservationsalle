<?php
require_once dirname(__DIR__, 2) . '/init.php';

if (est_connecte()) {
    redirect('index.php');
}

$utilisateurC = new UtilisateurC();
$erreurs      = [];
$donnees      = ['nom' => '', 'prenom' => '', 'email' => '', 'telephone' => '', 'departement' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_valide()) {
        $erreurs[] = 'Session expirée, merci de recommencer.';
    } else {
        foreach ($donnees as $cle => $_) {
            $donnees[$cle] = trim($_POST[$cle] ?? '');
        }
        $motDePasse   = $_POST['mot_de_passe'] ?? '';
        $confirmation = $_POST['confirmation'] ?? '';

        // ---- Contrôles de saisie côté serveur ----
        if (!v_requis($donnees['nom']))               $erreurs[] = 'Le nom est obligatoire.';
        elseif (!v_longueur($donnees['nom'], 2, 60))  $erreurs[] = 'Le nom doit contenir entre 2 et 60 caractères.';

        if (!v_requis($donnees['prenom']))              $erreurs[] = 'Le prénom est obligatoire.';
        elseif (!v_longueur($donnees['prenom'], 2, 60)) $erreurs[] = 'Le prénom doit contenir entre 2 et 60 caractères.';

        if (!v_requis($donnees['email']))       $erreurs[] = "L'adresse email est obligatoire.";
        elseif (!v_email($donnees['email']))    $erreurs[] = "L'adresse email n'est pas valide.";
        elseif ($utilisateurC->emailExiste($donnees['email'])) $erreurs[] = 'Cette adresse email est déjà utilisée.';

        if ($donnees['telephone'] !== '' && !v_telephone($donnees['telephone'])) {
            $erreurs[] = 'Le téléphone doit contenir de 8 à 20 chiffres.';
        }
        if ($donnees['departement'] !== '' && !v_longueur($donnees['departement'], 2, 80)) {
            $erreurs[] = 'Le département doit contenir entre 2 et 80 caractères.';
        }

        if (!v_requis($motDePasse))            $erreurs[] = 'Le mot de passe est obligatoire.';
        elseif (!v_mot_de_passe($motDePasse))  $erreurs[] = 'Le mot de passe doit contenir au moins 6 caractères.';
        elseif ($motDePasse !== $confirmation) $erreurs[] = 'Les deux mots de passe ne correspondent pas.';

        if (!$erreurs) {
            try {
                $u = new Utilisateur();
                $u->setNom($donnees['nom']);
                $u->setPrenom($donnees['prenom']);
                $u->setEmail($donnees['email']);
                $u->hasherMotDePasse($motDePasse);
                $u->setTelephone($donnees['telephone'] !== '' ? $donnees['telephone'] : null);
                $u->setDepartement($donnees['departement'] !== '' ? $donnees['departement'] : null);
                $u->setRole('utilisateur');
                $u->setStatut('actif');

                $utilisateurC->addUtilisateur($u);
                flash_set('success', 'Votre compte a été créé. Vous pouvez maintenant vous connecter.');
                redirect('login.php');
            } catch (Throwable $e) {
                $erreurs[] = 'Erreur lors de la création du compte : ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2454ff">
    <title>Créer un compte · ReservaSalles</title>
    <script>
        (function () {
            document.documentElement.classList.add('js');
            try {
                var t = localStorage.getItem('rs2-theme');
                if (t === 'dark' || t === 'light') document.documentElement.setAttribute('data-theme', t);
                if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches))
                    document.documentElement.classList.add('theme-dark');
            } catch (e) {}
        })();
    </script>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/app.css?v=10">
    <link rel="stylesheet" href="../../assets/css/shell.css?v=10">
    <link rel="stylesheet" href="../../assets/css/compat.css?v=10">
</head>
<body>

<div class="auth-page">
    <div class="auth-visuel">
        <div class="auth-visuel-content">
            <div class="brand" style="color:#fff;">
                <i class="fas fa-calendar-check"></i>
                <span style="color:#fff;">ReservaSalles</span>
            </div>
            <h2>Créez votre compte collaborateur.</h2>
            <p>Un compte suffit pour réserver dans tous les bâtiments de l'entreprise.</p>
            <div class="auth-points">
                <div class="auth-point"><i class="fas fa-door-open"></i> Accès à toutes les salles disponibles</div>
                <div class="auth-point"><i class="fas fa-clock-rotate-left"></i> Historique complet de vos réservations</div>
                <div class="auth-point"><i class="fas fa-pen-to-square"></i> Modification et annulation en autonomie</div>
            </div>
        </div>
    </div>

    <div class="auth-form-wrap">
        <div class="auth-form enter">
            <div class="row-between mb-5">
                <a href="index.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Accueil</a>
                <button class="theme-btn" type="button" aria-label="Changer de thème">
                    <i class="fas fa-sun i-sun"></i><i class="fas fa-moon i-moon"></i>
                </button>
            </div>
            <h1>Créer un compte</h1>
            <p>Quelques informations et c'est terminé.</p>

            <?php if ($erreurs): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-circle-exclamation"></i>
                    <div>
                        <strong>Merci de corriger :</strong>
                        <ul><?php foreach ($erreurs as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" id="formInscription" novalidate>
                <?= csrf_field() ?>

                <div class="form-grid" style="gap:14px;">
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
                </div>

                <div class="form-group" style="margin-top:14px;">
                    <label for="email">Adresse email <span class="req">*</span></label>
                    <input type="email" id="email" name="email" value="<?= e($donnees['email']) ?>">
                    <span class="erreur-champ" id="err-email"></span>
                </div>

                <div class="form-grid" style="gap:14px;margin-top:14px;">
                    <div class="form-group">
                        <label for="telephone">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone" value="<?= e($donnees['telephone']) ?>"
                               placeholder="21620100300">
                        <span class="erreur-champ" id="err-telephone"></span>
                    </div>
                    <div class="form-group">
                        <label for="departement">Département</label>
                        <input type="text" id="departement" name="departement"
                               value="<?= e($donnees['departement']) ?>" placeholder="Informatique">
                        <span class="erreur-champ" id="err-departement"></span>
                    </div>
                </div>

                <div class="form-grid" style="gap:14px;margin-top:14px;">
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
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top:20px;">
                    <i class="fas fa-user-plus"></i> Créer mon compte
                </button>
            </form>

            <div class="auth-footer">
                Déjà inscrit ? <a href="login.php">Se connecter</a>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/js/valider.js?v=10"></script>
<script src="../../assets/js/app.js?v=10"></script>
<script>
Valider.attacher('formInscription', {
    prenom: [
        { test: v => Valider.requis(v),          message: 'Le prénom est obligatoire.' },
        { test: v => Valider.texte(v, 2, 60),    message: 'Entre 2 et 60 caractères, pas uniquement des chiffres.' }
    ],
    nom: [
        { test: v => Valider.requis(v),          message: 'Le nom est obligatoire.' },
        { test: v => Valider.texte(v, 2, 60),    message: 'Entre 2 et 60 caractères, pas uniquement des chiffres.' }
    ],
    email: [
        { test: v => Valider.requis(v),          message: "L'adresse email est obligatoire." },
        { test: v => Valider.email(v),           message: 'Format attendu : nom@domaine.tn' }
    ],
    telephone: [
        { test: v => v.trim() === '' || Valider.telephone(v), message: '8 à 20 chiffres attendus.' }
    ],
    departement: [
        { test: v => v.trim() === '' || Valider.longueur(v, 2, 80), message: 'Entre 2 et 80 caractères.' }
    ],
    mot_de_passe: [
        { test: v => Valider.requis(v),          message: 'Le mot de passe est obligatoire.' },
        { test: v => Valider.motDePasse(v),      message: 'Au moins 6 caractères.' }
    ],
    confirmation: [
        { test: v => Valider.requis(v),          message: 'Merci de confirmer le mot de passe.' },
        { test: (v) => v === document.getElementById('mot_de_passe').value,
          message: 'Les deux mots de passe ne correspondent pas.' }
    ]
});
</script>
</body>
</html>
