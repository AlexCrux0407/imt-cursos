<?php
/**
 * Conexión PDO a MySQL leyendo primero las variables de Railway (MYSQL*).
 * Fallback a DB_* 
 */

if (!defined('BASE_URL')) {
    $baseUrlEnv = getenv('BASE_URL');
    define('BASE_URL', $baseUrlEnv !== false ? rtrim($baseUrlEnv, '/') : '/');
}

// 1) Prioriza MYSQL* de Railway; si no existen, usa DB_* (externa)
$host    = getenv('MYSQLHOST')      ?: getenv('DB_HOST') ?: '127.0.0.1';
$port    = getenv('MYSQLPORT')      ?: getenv('DB_PORT') ?: '3306';
$db      = getenv('MYSQLDATABASE')  ?: getenv('DB_NAME') ?: '';
$user    = getenv('MYSQLUSER')      ?: getenv('DB_USER') ?: '';
$pass    = getenv('MYSQLPASSWORD')  ?: getenv('DB_PASSWORD') ?: '';
$charset = getenv('DB_CHARSET')     ?: 'utf8mb4';

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
