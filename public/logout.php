<?php
session_start();

// Definir BASE_URL si no está definida
if (!defined('BASE_URL')) {
    define('BASE_URL', '/imt-cursos/public');
}

require_once __DIR__ . '/../config/database.php';

$_SESSION = array();

// Destruir la cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Redireccionar al login
header('Location: ' . BASE_URL . '/login.php');
exit;
