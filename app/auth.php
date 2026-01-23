<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/paths.php';

/*
 Utilidades de Autenticación
 - Verifica sesión y roles, protege rutas.
 - Redirige al login y cierra sesión con limpieza de cookies.
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
        header('Location: ' . BASE_URL . '/login.php?m=auth');
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

    $user_role = strtolower($_SESSION['role']);
    $required_role = strtolower($required_role);
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
    header('Location: ' . BASE_URL . '/login.php?m=logout');
    exit;
}

function split_nombre_apellidos(string $full): array
{
    $clean = trim(preg_replace('/\s+/', ' ', $full));
    if ($clean === '') {
        return ['', ''];
    }
    $parts = preg_split('/\s+/', $clean);
    $count = count($parts);
    if ($count === 1) {
        return [$parts[0], ''];
    }
    if ($count === 2) {
        return [$parts[0], $parts[1]];
    }
    $toLower = function (string $value): string {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    };
    $lower = array_map($toLower, $parts);
    $second_names = [
        'jose','maría','maria','juan','carlos','luis','ana','luisa','jesus','jesús','miguel','angel','ángel','javier','francisco','antonio','pedro','andrea','paula','camila','sofia','sofía','lucia','lucía','david','daniel','fernando','jorge','alejandro','mariana'
    ];
    if ($count === 3) {
        if (in_array($lower[1], $second_names, true)) {
            return [$parts[0] . ' ' . $parts[1], $parts[2]];
        }
        return [$parts[0], $parts[1] . ' ' . $parts[2]];
    }
    $apellidos = implode(' ', array_slice($parts, -2));
    $nombres = implode(' ', array_slice($parts, 0, -2));
    return [$nombres, $apellidos];
}

function format_nombre(string $full, string $orden = 'apellidos_nombres'): string
{
    $clean = trim($full);
    if ($clean === '') {
        return $clean;
    }
    list($nombres, $apellidos) = split_nombre_apellidos($clean);
    if ($apellidos === '') {
        return $nombres;
    }
    if ($orden === 'nombres_apellidos') {
        return trim($nombres . ' ' . $apellidos);
    }
    return trim($apellidos . ' ' . $nombres);
}
