<?php
/**
 * Rutas base del proyecto IMT-Cursos.
 */

if (!defined('BASE_URL')) {
    $baseUrlEnv = getenv('BASE_URL');
    if ($baseUrlEnv !== false && $baseUrlEnv !== '') {
        define('BASE_URL', rtrim($baseUrlEnv, '/'));
    } else {
        // Detección robusta del subdirectorio base (p. ej., /imt-cursos) sin incluir /public
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptName = str_replace('\\', '/', $scriptName);

        $autoBase = '/';
        if ($scriptName) {
            $posPublic = strpos($scriptName, '/public/');
            if ($posPublic !== false) {
                // Si el script está bajo /public, tomar la parte previa como base
                $autoBase = substr($scriptName, 0, $posPublic);
                if ($autoBase === '') { $autoBase = '/'; }
            } else {
                // Si el docroot ya es /public, dirname del script determina la base (posible subcarpeta)
                $dir = rtrim(dirname($scriptName), '/');
                $autoBase = ($dir === '' || $dir === '/') ? '/' : $dir;
            }
        }

        define('BASE_URL', rtrim($autoBase, '/'));
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