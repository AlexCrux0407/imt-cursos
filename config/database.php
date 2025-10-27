<?php
/**
 * Configuración de conexión PDO a MySQL usando variables de entorno.
 */
if (!defined('BASE_URL')) {
    $baseUrlEnv = getenv('BASE_URL');
    define('BASE_URL', $baseUrlEnv !== false ? rtrim($baseUrlEnv, '/') : '/');
}

// Compatibilidad con variables de Railway MySQL
$host = getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: '3306';
$db   = getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: 'imt_cursos';
$user = getenv('DB_USER') ?: getenv('MYSQLUSER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: getenv('MYSQLPASSWORD') ?: '1917248zzz';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $conn = new PDO($dsn, $user, $pass, $options);
    $pdo = $conn;
    $conn->query("SELECT 1");
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
