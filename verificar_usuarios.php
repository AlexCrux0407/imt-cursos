<?php
require_once 'config/database.php';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Verificación de Tabla Usuarios</h2>";
    
    // Verificar estructura de la tabla usuarios
    echo "<h3>Estructura de la tabla 'usuarios':</h3>";
    $stmt = $conn->query("SHOW COLUMNS FROM usuarios");
    $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columnas as $columna) {
        echo "<p>Columna: {$columna['Field']}, Tipo: {$columna['Type']}, Null: {$columna['Null']}, Default: {$columna['Default']}</p>";
    }
    
    // Mostrar algunos usuarios de ejemplo
    echo "<h3>Usuarios en la base de datos:</h3>";
    $stmt = $conn->query("SELECT * FROM usuarios LIMIT 5");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($usuarios as $usuario) {
        echo "<p>Usuario: " . print_r($usuario, true) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>