<?php
/**
 * Front controller de IMT-Cursos: bootstrap y despacho de rutas.
 */
session_start();

require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';

$router = require_once __DIR__ . '/../app/routes.php';
$router->dispatch();
