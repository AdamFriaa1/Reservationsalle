<?php
/**
 * Bootstrap commun de l'application : session, anti-cache, autoload des
 * Model/Controller, connexion PDO (config.php) et fonctions utilitaires.
 * À inclure en première ligne de CHAQUE vue.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!headers_sent()) {
    header('Cache-Control: no-cache, must-revalidate');
}

define('APP_ROOT', __DIR__);
define('APP_NAME', 'ReservaSalles');

require_once APP_ROOT . '/config.php';   // connexion PDO (classe config)

// --- Autoload simple des classes Model et Controller -------------------
spl_autoload_register(function (string $classe): void {
    foreach ([APP_ROOT . '/model/', APP_ROOT . '/controller/'] as $dossier) {
        $fichier = $dossier . $classe . '.php';
        if (file_exists($fichier)) {
            require_once $fichier;
            return;
        }
    }
});

// =====================================================================
//  FONCTIONS UTILITAIRES
// =====================================================================
/**
 * Fonctions utilitaires partagées par toutes les vues.
 */

// =====================================================================
//  COMPATIBILITÉ : repli si l'extension mbstring n'est pas activée
//  (XAMPP l'active par défaut ; ce repli évite une page blanche sinon)
// =====================================================================
if (!function_exists('mb_strlen')) {
    function mb_strlen($chaine, $encodage = null) { return strlen((string)$chaine); }
}
if (!function_exists('mb_substr')) {
    function mb_substr($chaine, $debut, $longueur = null, $encodage = null) {
        return $longueur === null ? substr((string)$chaine, $debut)
                                  : substr((string)$chaine, $debut, $longueur);
    }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper($chaine, $encodage = null) { return strtoupper((string)$chaine); }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($chaine, $encodage = null) { return strtolower((string)$chaine); }
}

// =====================================================================
//  SÉCURITÉ / AFFICHAGE
// =====================================================================

/** Échappe une valeur avant affichage HTML (protection XSS). */
function e($valeur): string
{
    return htmlspecialchars((string)($valeur ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Redirige puis stoppe le script. */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// =====================================================================
//  JETON CSRF
// =====================================================================

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_valide(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// =====================================================================
//  AUTHENTIFICATION / RÔLES
// =====================================================================

function est_connecte(): bool
{
    return isset($_SESSION['utilisateur']);
}

function utilisateur_courant(): ?array
{
    return $_SESSION['utilisateur'] ?? null;
}

function role_courant(): string
{
    return $_SESSION['utilisateur']['role'] ?? 'invite';
}

function id_courant(): ?int
{
    return isset($_SESSION['utilisateur']['id_utilisateur'])
        ? (int)$_SESSION['utilisateur']['id_utilisateur']
        : null;
}

/** Exige une session ouverte, sinon renvoie vers le login. */
function exiger_connexion(string $prefixe = '../frontend/'): void
{
    if (!est_connecte()) {
        $_SESSION['flash_error'] = "Vous devez vous connecter pour accéder à cette page.";
        redirect($prefixe . 'login.php');
    }
}

/** Exige un des rôles passés en paramètre. */
function exiger_role(array $roles, string $prefixe = '../frontend/'): void
{
    exiger_connexion($prefixe);
    if (!in_array(role_courant(), $roles, true)) {
        $_SESSION['flash_error'] = "Accès refusé : vos droits ne permettent pas cette action.";
        redirect($prefixe . 'index.php');
    }
}

// =====================================================================
//  MESSAGES FLASH
// =====================================================================

function flash_set(string $type, string $message): void
{
    $_SESSION['flash_' . $type] = $message;
}

function flash_get(string $type): string
{
    $cle = 'flash_' . $type;
    $msg = $_SESSION[$cle] ?? '';
    unset($_SESSION[$cle]);
    return $msg;
}

/** Affiche les alertes success / error stockées en session. */
function flash_afficher(): void
{
    $succes = flash_get('success');
    $erreur = flash_get('error');
    if ($succes !== '') {
        echo '<div class="alert alert-success"><i class="fas fa-circle-check"></i><span>' . e($succes) . '</span></div>';
    }
    if ($erreur !== '') {
        echo '<div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i><span>' . e($erreur) . '</span></div>';
    }
}

// =====================================================================
//  VALIDATION (côté serveur — obligatoire, le JS n'est qu'un confort)
// =====================================================================

function v_requis($valeur): bool
{
    return trim((string)$valeur) !== '';
}

function v_longueur($valeur, int $min, int $max): bool
{
    $len = mb_strlen(trim((string)$valeur));
    return $len >= $min && $len <= $max;
}

function v_email($valeur): bool
{
    return (bool)filter_var(trim((string)$valeur), FILTER_VALIDATE_EMAIL);
}

function v_entier($valeur, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): bool
{
    if (!is_numeric($valeur) || (int)$valeur != $valeur) {
        return false;
    }
    $n = (int)$valeur;
    return $n >= $min && $n <= $max;
}

function v_telephone($valeur): bool
{
    return (bool)preg_match('/^[0-9 +\-]{8,20}$/', trim((string)$valeur));
}

/** Vérifie qu'une chaîne est une date/heure valide au format donné. */
function v_datetime($valeur, string $format = 'Y-m-d H:i'): bool
{
    $d = DateTime::createFromFormat($format, trim((string)$valeur));
    return $d !== false && $d->format($format) === trim((string)$valeur);
}

function v_dans(array $liste, $valeur): bool
{
    return in_array($valeur, $liste, true);
}

/** Mot de passe : 6 caractères minimum, au moins une lettre et un chiffre. */
function v_mot_de_passe($valeur): bool
{
    $v = (string)$valeur;
    return mb_strlen($v) >= 6;
}

// =====================================================================
//  FORMATAGE
// =====================================================================

const JOURS_FR = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
const MOIS_FR  = [1=>'Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

function fmt_date(?string $sqlDate): string
{
    if (empty($sqlDate)) return '—';
    $ts = strtotime($sqlDate);
    return JOURS_FR[(int)date('w', $ts)] . ' ' . date('d', $ts) . ' ' . MOIS_FR[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

function fmt_datetime(?string $sqlDate): string
{
    if (empty($sqlDate)) return '—';
    return fmt_date($sqlDate) . ' à ' . date('H:i', strtotime($sqlDate));
}

function fmt_heure(?string $sqlDate): string
{
    return empty($sqlDate) ? '—' : date('H:i', strtotime($sqlDate));
}

function fmt_duree(string $debut, string $fin): string
{
    return fmt_minutes((int)round((strtotime($fin) - strtotime($debut)) / 60));
}

/** Met en forme une durée déjà exprimée en minutes (ex. 90 → « 1h30 »). */
function fmt_minutes(int $minutes): string
{
    if ($minutes < 0) $minutes = 0;
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    if ($h === 0) return $m . ' min';
    return $m === 0 ? $h . 'h' : $h . 'h' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
}

/** Libellés lisibles des statuts de réservation. */
function libelle_statut(string $statut): string
{
    return [
        'en_attente' => 'En attente',
        'validee'    => 'Validée',
        'refusee'    => 'Refusée',
        'annulee'    => 'Annulée',
        'terminee'   => 'Terminée',
    ][$statut] ?? $statut;
}

function classe_statut(string $statut): string
{
    return [
        'en_attente' => 'badge-warning',
        'validee'    => 'badge-success',
        'refusee'    => 'badge-danger',
        'annulee'    => 'badge-muted',
        'terminee'   => 'badge-info',
    ][$statut] ?? 'badge-muted';
}

function libelle_type_salle(string $type): string
{
    return [
        'reunion'    => 'Réunion',
        'conference' => 'Conférence',
        'formation'  => 'Formation',
        'visio'      => 'Visioconférence',
        'coworking'  => 'Coworking',
    ][$type] ?? $type;
}

function libelle_etat_salle(string $etat): string
{
    return [
        'disponible'   => 'Disponible',
        'maintenance'  => 'En maintenance',
        'indisponible' => 'Indisponible',
    ][$etat] ?? $etat;
}

function libelle_role(string $role): string
{
    return [
        'admin'        => 'Administrateur Bâtiments',
        'gestionnaire' => 'Gestionnaire de Réservations',
        'utilisateur'  => 'Utilisateur',
    ][$role] ?? $role;
}

/** Transforme "Vidéoprojecteur, Wifi" en tableau propre. */
function liste_equipements(?string $equipements): array
{
    if (empty($equipements)) return [];
    return array_values(array_filter(array_map('trim', explode(',', $equipements))));
}
