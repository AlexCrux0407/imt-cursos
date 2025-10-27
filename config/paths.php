<?php
/**
 * Rutas base del proyecto IMT-Cursos.
 */

if (!defined('BASE_URL')) {
    $baseUrlEnv = getenv('BASE_URL');
    define('BASE_URL', $baseUrlEnv !== false ? rtrim($baseUrlEnv, '/') : '/');
}

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', ROOT_PATH . '/public');
}

if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . '/app');
}

if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', ROOT_PATH . '/config');
}

if (!defined('UPLOADS_PATH')) {
    define('UPLOADS_PATH', ROOT_PATH . '/uploads');
}