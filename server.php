<?php
/**
 * Servidor PHP integrado para desarrollo local
 * Redirige automáticamente al login
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// Si es la raíz o el directorio del proyecto, redirigir al login
if ($uri === '/' || $uri === '' || $uri === '/imt-cursos-local/' || $uri === '/imt-cursos-local') {
    header('Location: /imt-cursos-local/public/login.php');
    exit;
}

// Si el archivo existe, servirlo directamente
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Si el archivo existe en public, servirlo directamente
if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
    return false;
}

// Todas las demás peticiones van al index.php de public
require_once __DIR__ . '/public/index.php';