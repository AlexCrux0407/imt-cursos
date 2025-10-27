<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/paths.php';

/**
 * Helpers de autenticación y sesión.
 */

/**
 * Indica si hay sesión iniciada con rol.
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['role']);
}

/**
 * Redirige al login si no hay sesión.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /login.php?m=auth');
        exit;
    }
}

/**
 * Exige un rol específico o redirige al login.
 */
function require_role($required_role)
{
    if (!isset($_SESSION['role'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }

    $user_role = $_SESSION['role'];
    if ($required_role === 'estudiante' && $user_role === 'estudiante') {
        return;
    }

    if ($user_role !== $required_role) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Cierra sesión y redirige al login.
 */
function logout_and_redirect(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: /login.php?m=logout');
    exit;
}
