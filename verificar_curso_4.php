<?php
require_once 'config/database.php';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Verificación del Curso ID 4</h2>";
    
    // Obtener información del curso ID 4
    $stmt = $conn->prepare("SELECT * FROM cursos WHERE id = 4");
    $stmt->execute();
    $curso = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($curso) {
        echo "<h3>Información del Curso:</h3>";
        echo "<p>ID: {$curso['id']}</p>";
        echo "<p>Título: {$curso['titulo']}</p>";
        echo "<p>Creado: {$curso['created_at']}</p>";
        
        // Verificar módulos
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM modulos WHERE curso_id = 4");
        $stmt->execute();
        $modulos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Verificar temas (a través de módulos)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM temas t 
            INNER JOIN modulos m ON t.modulo_id = m.id 
            WHERE m.curso_id = 4
        ");
        $stmt->execute();
        $temas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Verificar subtemas (a través de temas y módulos)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM subtemas s 
            INNER JOIN temas t ON s.tema_id = t.id 
            INNER JOIN modulos m ON t.modulo_id = m.id 
            WHERE m.curso_id = 4
        ");
        $stmt->execute();
        $subtemas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Verificar lecciones (a través de módulos)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM lecciones l 
            INNER JOIN modulos m ON l.modulo_id = m.id 
            WHERE m.curso_id = 4
        ");
        $stmt->execute();
        $lecciones = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo "<h3>Contenido Asociado al Curso ID 4:</h3>";
        echo "<p>Módulos: $modulos</p>";
        echo "<p>Temas: $temas</p>";
        echo "<p>Subtemas: $subtemas</p>";
        echo "<p>Lecciones: $lecciones</p>";
        
        // Mostrar detalles de módulos
        echo "<h3>Módulos del Curso ID 4:</h3>";
        $stmt = $conn->prepare("SELECT * FROM modulos WHERE curso_id = 4 ORDER BY orden");
        $stmt->execute();
        while ($modulo = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<p>Módulo ID: {$modulo['id']}, Título: {$modulo['titulo']}, Orden: {$modulo['orden']}</p>";
        }
        
        // Mostrar detalles de lecciones
        echo "<h3>Lecciones del Curso ID 4:</h3>";
        $stmt = $conn->prepare("
            SELECT l.*, m.titulo as modulo_titulo 
            FROM lecciones l 
            INNER JOIN modulos m ON l.modulo_id = m.id 
            WHERE m.curso_id = 4 
            ORDER BY l.orden
        ");
        $stmt->execute();
        while ($leccion = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<p>Lección ID: {$leccion['id']}, Título: {$leccion['titulo']}, Módulo: {$leccion['modulo_titulo']}, Orden: {$leccion['orden']}</p>";
        }
        
    } else {
        echo "<p>No se encontró el curso con ID 4.</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>