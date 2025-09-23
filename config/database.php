<?php
$host = '127.0.0.1';
$db   = 'imt_cursos';
$user = 'root';
$pass = '1917248zzz';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $conn = new PDO($dsn, $user, $pass, $options);
    // Verificar que la conexión funciona
    $conn->query("SELECT 1");
} catch (PDOException $e) {
    // En desarrollo, mostrar el error. En producción, loggear sin mostrar.
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
