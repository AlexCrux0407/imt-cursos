<?php
/**
 * Conexión PDO a MySQL leyendo primero las variables de Railway (MYSQL*).
 * Fallback a DB_* 
 */

// No definas BASE_URL aquí salvo que venga del entorno;
// deja que config/paths.php detecte automáticamente la base bajo /public.
if (!defined('BASE_URL')) {
    $baseUrlEnv = getenv('BASE_URL');
    if ($baseUrlEnv !== false && $baseUrlEnv !== '') {
        define('BASE_URL', rtrim($baseUrlEnv, '/'));
    }
}

// 1) Prioriza MYSQL* de Railway; si no existen, usa DB_* (local)
//    Fallback por defecto orientado a Laragon: host 127.0.0.1, puerto 3306, db "imt_cursos", user "root", sin password.
$host    = getenv('MYSQLHOST')      ?: getenv('DB_HOST') ?: '127.0.0.1';
$port    = getenv('MYSQLPORT')      ?: getenv('DB_PORT') ?: '3306';
$db      = getenv('MYSQLDATABASE')  ?: getenv('DB_NAME') ?: 'imt_cursos';
$user    = getenv('MYSQLUSER')      ?: getenv('DB_USER') ?: 'root';
$pass    = getenv('MYSQLPASSWORD')  ?: (getenv('DB_PASSWORD') ?: getenv('DB_PASS') ?: '');
$charset = getenv('DB_CHARSET')     ?: 'utf8mb4';

// 1.1) Zona horaria: usa TZ si está definida; por defecto CDMX
try {
    $tz = getenv('TZ');
    if ($tz) { date_default_timezone_set($tz); }
    elseif (function_exists('date_default_timezone_get')) { date_default_timezone_set('America/Mexico_City'); }
} catch (Throwable $e) { /* noop */ }

// 2) DSN y opciones PDO
$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $conn = new PDO($dsn, $user, $pass, $options);
    // Alias opcional si el resto del código usa $pdo
    $pdo = $conn;

    // Ping rápido
    $conn->query("SELECT 1");
} catch (Throwable $e) {
    http_response_code(500);
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}
