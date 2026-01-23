<?php
/**
 * Configuración local para Laragon
 * Este archivo sobrescribe las configuraciones de database.php
 */

// Configuración para Laragon local
$host = '127.0.0.1';
$port = '3306';
$db = 'imt_cursos';
$user = 'root';
$pass = ''; // Contraseña vacía por defecto en Laragon
$charset = 'utf8mb4';

// DSN y opciones PDO
$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $conn = new PDO($dsn, $user, $pass, $options);
    $pdo = $conn;
    
    // Ping rápido
    $conn->query("SELECT 1");
    
} catch (Throwable $e) {
    // No matar el script, solo registrar el error
    error_log('Error de conexión a la base de datos (local): ' . $e->getMessage());
    $conn = null;
}
