<?php
require_once 'config/database.php';

echo "=== ESTRUCTURA TABLA progreso_modulos ===\n";

try {
    $stmt = $conn->query('DESCRIBE progreso_modulos');
    while($row = $stmt->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>