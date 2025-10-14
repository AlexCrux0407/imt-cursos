<?php
require_once 'config/database.php';

echo "Módulos del curso ID 4:\n";
$stmt = $conn->prepare('SELECT id, titulo, orden FROM modulos WHERE curso_id = 4 ORDER BY orden');
$stmt->execute();
while($row = $stmt->fetch()) {
    echo "ID: {$row['id']}, Título: {$row['titulo']}, Orden: {$row['orden']}\n";
}
?>