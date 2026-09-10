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
        $email      = trim($_POST['email'] ?? '');
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
if ($flashErreur !== '') { $erreurs[] = $flashErreur; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2454ff">
    <title>Connexion · ReservaSalles</title>
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

<div class="auth">

    <!-- ============================================ VISUEL -->
    <aside class="auth-art">
        <span class="hero-grid" aria-hidden="true"></span>

        <a href="index.php" class="brand" style="color:#fff">
            <span class="brand-mark"><i class="fas fa-calendar-check"></i></span>
            <span>ReservaSalles<small style="color:var(--brand-400)">Espaces &amp; réunions</small></span>
        </a>

        <div class="enter">
            <h2>Vos salles de réunion, réservées en trois clics.</h2>
            <p>
                Le planning de chaque salle, créneau par créneau. Les conflits sont
                écartés avant l'envoi, et vous suivez votre demande jusqu'à la réponse
                du gestionnaire.
            </p>
            <div class="auth-pts">
                <div class="auth-pt"><i class="fas fa-calendar-days"></i> Calendrier interactif des disponibilités</div>
                <div class="auth-pt"><i class="fas fa-shield-halved"></i> Détection automatique des chevauchements</div>
                <div class="auth-pt"><i class="fas fa-envelope-open-text"></i> Notification par email à chaque étape</div>
                <div class="auth-pt"><i class="fas fa-sliders"></i> Filtres par capacité, équipement et bâtiment</div>
            </div>
        </div>

        <p class="t-sm" style="color:rgba(255,255,255,.45)">
            Besoin d'un accès ? Contactez le gestionnaire des réservations.
        </p>
    </aside>

    <!-- ============================================ FORMULAIRE -->
    <div class="auth-form-side">
        <div class="auth-card enter">

            <div class="row-between mb-5">
                <a href="index.php" class="btn btn-ghost btn-sm">
                    <i class="fas fa-arrow-left"></i> Accueil
                </a>
                <button class="theme-btn" type="button" aria-label="Changer de thème">
                    <i class="fas fa-sun i-sun"></i><i class="fas fa-moon i-moon"></i>
                </button>
            </div>

            <h1>Connexion</h1>
            <p class="muted">Accédez à votre espace de réservation.</p>

            <?php if ($erreurs): ?>
                <div class="alert alert-bad">
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
                <div class="alert alert-ok"><i class="fas fa-circle-check"></i><span><?= e($succes) ?></span></div>
            <?php endif; ?>

            <form method="post" id="formConnexion" novalidate class="stack g-4">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="email">Adresse email <span class="req">*</span></label>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>"
                           placeholder="prenom.nom@reserva.tn" autocomplete="email">
                    <span class="err" id="err-email"></span>
                </div>

                <div class="field">
                    <label for="mot_de_passe">Mot de passe <span class="req">*</span></label>
                    <input type="password" id="mot_de_passe" name="mot_de_passe"
                           placeholder="••••••" autocomplete="current-password">
                    <span class="err" id="err-mot_de_passe"></span>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block mt-2">
                    <i class="fas fa-right-to-bracket"></i> Se connecter
                </button>
            </form>

            <div class="auth-foot">
                Pas encore de compte ? <a href="register.php">Créer un compte</a>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/js/valider.js?v=10"></script>
<script src="../../assets/js/app.js?v=10"></script>
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
</script>
</body>
</html>
