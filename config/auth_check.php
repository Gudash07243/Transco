<?php
// ============================================================
//  config/auth_check.php — Vérification des sessions et rôles
// ============================================================

// Afficher les erreurs (à désactiver en production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Empêche le navigateur de mettre en cache les pages authentifiées.
 * Cela évite qu'un bouton "retour" affiche une ancienne page après déconnexion.
 */
function sendNoCacheHeaders(): void {
    if (headers_sent()) {
        return;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
    header('Pragma: no-cache');
    header('Expires: 0');
}

sendNoCacheHeaders();

/**
 * Vérifie que l'utilisateur est connecté.
 * Si non, redirige vers la page de connexion.
 */
function requireLogin(): void {
    if (!isset($_SESSION['id_user']) || !isset($_SESSION['role'])) {
        header('Location: ' . getBaseUrl() . '/public/login.php');
        exit;
    }
}

/**
 * Vérifie que l'utilisateur a le bon rôle.
 * Si non, redirige vers son propre dashboard ou la page de connexion.
 *
 * @param string|array $roles  Rôle(s) autorisé(s) : 'ADMIN', 'CONTROLEUR', 'CLIENT'
 */
function requireRole($roles): void {
    requireLogin();

    if (is_string($roles)) {
        $roles = [$roles];
    }

    if (!in_array($_SESSION['role'], $roles, true)) {
        // Redirige l'utilisateur vers son propre espace
        redirectToDashboard();
    }
}

/**
 * Redirige vers le dashboard correspondant au rôle de la session active.
 */
function redirectToDashboard(): void {
    $base = getBaseUrl();
    switch ($_SESSION['role'] ?? '') {
        case 'ADMIN':
            header('Location: ' . $base . '/admin/admin_dashboard.php');
            break;
        case 'CONTROLEUR':
            header('Location: ' . $base . '/controlleur/scan.php');
            break;
        case 'CLIENT':
            header('Location: ' . $base . '/client/dashboard.php');
            break;
        default:
            header('Location: ' . $base . '/public/login.php');
    }
    exit;
}

/**
 * Retourne l'URL de base du projet (sans slash final).
 */
function getBaseUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Détecte le sous-dossier si le projet n'est pas à la racine
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir    = $script !== '' ? rtrim(str_replace('\\', '/', dirname($script)), '/') : '';

    $segments = array_values(array_filter(explode('/', trim($dir, '/')), 'strlen'));
    $appDirs  = ['admin', 'client', 'controlleur', 'public', 'config'];

    if (!empty($segments) && in_array(end($segments), $appDirs, true)) {
        array_pop($segments);
    }

    $base = !empty($segments) ? '/' . implode('/', $segments) : '';
    return $protocol . '://' . $host . $base;
}

/**
 * Déconnecte l'utilisateur et détruit la session.
 */
function logout(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Vider les données de session
    $_SESSION = [];

    // Supprimer aussi le cookie de session côté navigateur
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
    header('Location: ' . getBaseUrl() . '/public/login.php');
    exit;
}

/**
 * Retourne true si l'utilisateur est connecté.
 */
function isLoggedIn(): bool {
    return isset($_SESSION['id_user']) && isset($_SESSION['role']);
}
