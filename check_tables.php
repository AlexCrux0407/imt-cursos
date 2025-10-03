<?php
require_once 'config/database.php';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Estructura de las Tablas</h2>";
    
    $tables = ['modulos', 'temas', 'subtemas', 'lecciones'];
    
    foreach ($tables as $table) {
        echo "<h3>Tabla: $table</h3>";
        try {
            $stmt = $conn->query("DESCRIBE $table");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<p>{$row['Field']} - {$row['Type']}</p>";
            }
        } catch (Exception $e) {
            echo "<p>Error: " . $e->getMessage() . "</p>";
        }
        echo "<br>";
    }
    
} catch (Exception $e) {
    echo "<p>Error de conexión: " . $e->getMessage() . "</p>";
}
?>