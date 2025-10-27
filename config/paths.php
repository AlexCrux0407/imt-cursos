<?php
/**
 * Rutas base del proyecto IMT-Cursos.
 */

if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $project_path = '/imt-cursos/public';

    if (strpos($host, 'localhost:') !== false || strpos($host, '127.0.0.1:') !== false) {
        define('BASE_URL', '');
    } else {
        define('BASE_URL', $project_path);
    }
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