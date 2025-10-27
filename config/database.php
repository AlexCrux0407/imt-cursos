<?php
/**
 * Configuración de conexión PDO a MySQL usando variables de entorno.
 */
if (!defined('BASE_URL')) {
    $baseUrlEnv = getenv('BASE_URL');
    define('BASE_URL', $baseUrlEnv !== false ? rtrim($baseUrlEnv, '/') : '/imt-cursos/public');
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: 'imt_cursos';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '1917248zzz';
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
