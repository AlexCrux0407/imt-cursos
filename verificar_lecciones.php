<?php
require_once 'config/database.php';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Verificación de Lecciones</h2>";
    
    // Mostrar todas las lecciones
    echo "<h3>Todas las Lecciones:</h3>";
    $stmt = $conn->query("SELECT * FROM lecciones ORDER BY id DESC");
    while ($leccion = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<p>ID: {$leccion['id']}, Módulo ID: {$leccion['modulo_id']}, Título: {$leccion['titulo']}, Orden: {$leccion['orden']}</p>";
    }
    
    // Verificar si los módulos existen
    echo "<h3>Verificación de Módulos Referenciados:</h3>";
    $stmt = $conn->query("
        SELECT DISTINCT l.modulo_id, m.id as modulo_existe, m.titulo as modulo_titulo, m.curso_id
        FROM lecciones l 
        LEFT JOIN modulos m ON l.modulo_id = m.id 
        ORDER BY l.modulo_id
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existe = $row['modulo_existe'] ? 'SÍ' : 'NO';
        $titulo = $row['modulo_titulo'] ?: 'N/A';
        $curso_id = $row['curso_id'] ?: 'N/A';
        echo "<p>Módulo ID: {$row['modulo_id']}, Existe: $existe, Título: $titulo, Curso ID: $curso_id</p>";
    }
    
    // Contar lecciones por módulo
    echo "<h3>Lecciones por Módulo:</h3>";
    $stmt = $conn->query("
        SELECT m.id, m.titulo, m.curso_id, COUNT(l.id) as num_lecciones
        FROM modulos m 
        LEFT JOIN lecciones l ON m.id = l.modulo_id 
        GROUP BY m.id, m.titulo, m.curso_id
        ORDER BY m.curso_id, m.id
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<p>Módulo ID: {$row['id']}, Título: {$row['titulo']}, Curso ID: {$row['curso_id']}, Lecciones: {$row['num_lecciones']}</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>