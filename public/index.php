<?php
// Controlador frontal del sistema IMT-Cursos
session_start();

// Cargar configuración
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';

// Cargar y ejecutar rutas
$router = require_once __DIR__ . '/../app/routes.php';
$router->dispatch();
