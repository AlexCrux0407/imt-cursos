<?php
/**
 * Configuración centralizada de rutas del proyecto IMT-Cursos
 * Este archivo define todas las rutas base utilizadas en la aplicación
 */

// Definir BASE_URL solo si no está ya definida
if (!defined('BASE_URL')) {
    // Detectar automáticamente la URL base según el entorno
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    
    // Extraer la ruta base del proyecto
    $project_path = '/imt-cursos/public';
    
    // Para desarrollo local con servidor PHP integrado
    if (strpos($host, 'localhost:') !== false || strpos($host, '127.0.0.1:') !== false) {
        define('BASE_URL', '');
    } else {
        // Para servidor web tradicional (Apache/Nginx)
        define('BASE_URL', $project_path);
    }
}

// Definir otras rutas útiles
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