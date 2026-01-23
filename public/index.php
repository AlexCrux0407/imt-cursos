<?php
/*
 Front Controller de IMT-Cursos
 - Inicializa sesión, carga configuración y helpers.
 - Construye router y despacha la solicitud actual.
 */
session_start();

require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';

$router = require_once __DIR__ . '/../app/routes.php';
$router->dispatch();
<?php
/**
 * Redirección automática al login
 */
header('Location: public/login.php');
exit;