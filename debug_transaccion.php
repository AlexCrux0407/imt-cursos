<?php
require_once 'config/database.php';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Estado de la Base de Datos - Verificación de Transacciones</h2>";
    
    // Obtener el último curso
    $stmt = $conn->prepare("SELECT * FROM cursos ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $ultimo_curso = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ultimo_curso) {
        echo "<h3>Último Curso Creado:</h3>";
        echo "<p>ID: {$ultimo_curso['id']}</p>";
        echo "<p>Título: " . (isset($ultimo_curso['titulo']) ? $ultimo_curso['titulo'] : 'N/A') . "</p>";
        echo "<p>Fecha: " . (isset($ultimo_curso['fecha_creacion']) ? $ultimo_curso['fecha_creacion'] : 'N/A') . "</p>";
        
        // Mostrar todas las columnas disponibles
        echo "<h4>Columnas disponibles en la tabla cursos:</h4>";
        echo "<pre>" . print_r($ultimo_curso, true) . "</pre>";
        
        $curso_id = $ultimo_curso['id'];
        
        // Verificar módulos
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM modulos WHERE curso_id = ?");
        $stmt->execute([$curso_id]);
        $modulos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Verificar temas (a través de módulos)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM temas t 
            INNER JOIN modulos m ON t.modulo_id = m.id 
            WHERE m.curso_id = ?
        ");
        $stmt->execute([$curso_id]);
        $temas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Verificar subtemas (a través de temas y módulos)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM subtemas s 
            INNER JOIN temas t ON s.tema_id = t.id 
            INNER JOIN modulos m ON t.modulo_id = m.id 
            WHERE m.curso_id = ?
        ");
        $stmt->execute([$curso_id]);
        $subtemas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Verificar lecciones (a través de módulos)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM lecciones l 
            INNER JOIN modulos m ON l.modulo_id = m.id 
            WHERE m.curso_id = ?
        ");
        $stmt->execute([$curso_id]);
        $lecciones = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo "<h3>Contenido Asociado:</h3>";
        echo "<p>Módulos: $modulos</p>";
        echo "<p>Temas: $temas</p>";
        echo "<p>Subtemas: $subtemas</p>";
        echo "<p>Lecciones: $lecciones</p>";
        
        // Verificar si hay datos en las tablas pero no asociados al curso
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM modulos");
        $stmt->execute();
        $total_modulos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM temas");
        $stmt->execute();
        $total_temas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM subtemas");
        $stmt->execute();
        $total_subtemas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM lecciones");
        $stmt->execute();
        $total_lecciones = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo "<h3>Total en Base de Datos (todos los cursos):</h3>";
        echo "<p>Total Módulos: $total_modulos</p>";
        echo "<p>Total Temas: $total_temas</p>";
        echo "<p>Total Subtemas: $total_subtemas</p>";
        echo "<p>Total Lecciones: $total_lecciones</p>";
        
        // Mostrar últimos registros insertados
        echo "<h3>Últimos 5 registros por tabla:</h3>";
        
        echo "<h4>Módulos:</h4>";
        $stmt = $conn->prepare("SELECT * FROM modulos ORDER BY id DESC LIMIT 5");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<p>ID: {$row['id']}, Curso: " . (isset($row['curso_id']) ? $row['curso_id'] : 'N/A') . ", Nombre: " . (isset($row['nombre']) ? $row['nombre'] : 'N/A') . "</p>";
        }
        
        echo "<h4>Lecciones:</h4>";
        $stmt = $conn->prepare("SELECT * FROM lecciones ORDER BY id DESC LIMIT 5");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<p>ID: {$row['id']}, Curso: " . (isset($row['curso_id']) ? $row['curso_id'] : 'N/A') . ", Título: " . (isset($row['titulo']) ? $row['titulo'] : 'N/A') . "</p>";
        }
        
    } else {
        echo "<p>No se encontraron cursos en la base de datos.</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>