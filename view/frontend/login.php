<?php
require_once dirname(__DIR__, 2) . '/init.php';

// Déjà connecté : on renvoie vers l'espace approprié
if (est_connecte()) {
    redirect(in_array(role_courant(), ['admin', 'gestionnaire'], true)
        ? '../backend/index.php' : 'index.php');
}

$utilisateurC = new UtilisateurC();
$erreurs      = [];
$email        = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_valide()) {
        $erreurs[] = 'Session expirée, merci de recommencer.';
    } else {
        $email     = trim($_POST['email'] ?? '');
        $motDePasse = $_POST['mot_de_passe'] ?? '';

        // ---- Contrôles de saisie côté serveur ----
        if (!v_requis($email)) {
            $erreurs[] = "L'adresse email est obligatoire.";
        } elseif (!v_email($email)) {
            $erreurs[] = "L'adresse email n'est pas valide.";
        }
        if (!v_requis($motDePasse)) {
            $erreurs[] = 'Le mot de passe est obligatoire.';
        }

        if (!$erreurs) {
            try {
                $u = $utilisateurC->authentifier($email, $motDePasse);
                if ($u === null) {
                    $erreurs[] = 'Identifiants incorrects, ou compte désactivé.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['utilisateur'] = $u;
                    flash_set('success', 'Bienvenue ' . $u['prenom'] . ' !');
                    redirect(in_array($u['role'], ['admin', 'gestionnaire'], true)
                        ? '../backend/index.php' : 'index.php');
                }
            } catch (Throwable $e) {
                $erreurs[] = 'Erreur technique : ' . $e->getMessage();
            }
        }
    }
}

$flashErreur = flash_get('error');
if ($flashErreur !== '') {
    $erreurs[] = $flashErreur;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — ReservaSalles</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="assets/css/front.css?v=7">
</head>
<body>

<div class="auth-page">

    <div class="auth-visuel">
        <div class="auth-visuel-content">
            <div class="brand" style="color:#fff;">
                <i class="fas fa-calendar-check"></i>
                <span style="color:#fff;">ReservaSalles</span>
            </div>
            <h2>Vos salles de réunion, réservées en trois clics.</h2>
            <p>Consultez les disponibilités en temps réel, soumettez votre demande
               et recevez la confirmation par email.</p>
            <div class="auth-points">
                <div class="auth-point"><i class="fas fa-calendar-days"></i> Calendrier interactif des disponibilités</div>
                <div class="auth-point"><i class="fas fa-shield-halved"></i> Détection automatique des conflits</div>
                <div class="auth-point"><i class="fas fa-envelope-open-text"></i> Notifications par email à chaque étape</div>
                <div class="auth-point"><i class="fas fa-sliders"></i> Filtres par capacité, équipement et bâtiment</div>
            </div>
        </div>
    </div>

    <div class="auth-form-wrap">
        <div class="auth-form">
            <h1>Connexion</h1>
            <p>Accédez à votre espace de réservation.</p>

            <?php if ($erreurs): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-circle-exclamation"></i>
                    <div>
                        <?php if (count($erreurs) === 1): ?>
                            <?= e($erreurs[0]) ?>
                        <?php else: ?>
                            <strong>Connexion impossible :</strong>
                            <ul><?php foreach ($erreurs as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php $succes = flash_get('success'); if ($succes !== ''): ?>
                <div class="alert alert-success"><i class="fas fa-circle-check"></i><span><?= e($succes) ?></span></div>
            <?php endif; ?>

            <form method="post" id="formConnexion" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="email">Adresse email <span class="req">*</span></label>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>"
                           placeholder="prenom.nom@reserva.tn" autocomplete="email">
                    <span class="erreur-champ" id="err-email"></span>
                </div>

                <div class="form-group">
                    <label for="mot_de_passe">Mot de passe <span class="req">*</span></label>
                    <input type="password" id="mot_de_passe" name="mot_de_passe"
                           placeholder="••••••" autocomplete="current-password">
                    <span class="erreur-champ" id="err-mot_de_passe"></span>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-right-to-bracket"></i> Se connecter
                </button>
            </form>

            <div class="auth-footer">
                Pas encore de compte ? <a href="register.php">Créer un compte</a><br>
                <a href="index.php" class="text-muted">← Retour à l'accueil</a>
            </div>

            <div class="comptes-demo">
                <strong><i class="fas fa-flask"></i> Comptes de démonstration (cliquez pour remplir)</strong>
                <div class="compte-demo" data-email="admin@reserva.tn">
                    <span>Administrateur bâtiments</span><code>admin@reserva.tn</code>
                </div>
                <div class="compte-demo" data-email="gestionnaire@reserva.tn">
                    <span>Gestionnaire réservations</span><code>gestionnaire@reserva.tn</code>
                </div>
                <div class="compte-demo" data-email="yassine@reserva.tn">
                    <span>Utilisateur</span><code>yassine@reserva.tn</code>
                </div>
                <div style="margin-top:8px;color:var(--gris-500);">Mot de passe commun : <code>123456</code></div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/validation.js"></script>
<script>
Valider.attacher('formConnexion', {
    email: [
        { test: v => Valider.requis(v), message: "L'adresse email est obligatoire." },
        { test: v => Valider.email(v),  message: "Format d'email invalide (exemple : nom@domaine.tn)." }
    ],
    mot_de_passe: [
        { test: v => Valider.requis(v),     message: 'Le mot de passe est obligatoire.' },
        { test: v => Valider.motDePasse(v), message: 'Le mot de passe fait au moins 6 caractères.' }
    ]
});

// Pré-remplissage des comptes de démonstration
document.querySelectorAll('.compte-demo').forEach(ligne => {
    ligne.addEventListener('click', () => {
        document.getElementById('email').value        = ligne.dataset.email;
        document.getElementById('mot_de_passe').value = '123456';
        Valider.nettoyerTout(document.getElementById('formConnexion'));
    });
});
</script>
</body>
</html>
