<?php
require_once '../config/database.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Debug: Evaluaciones del Módulo 15</h2>";
    
    // Verificar todas las evaluaciones del módulo 15
    $stmt = $pdo->prepare("
        SELECT id, titulo, descripcion, tipo, activo, puntaje_total, tiempo_limite, orden, fecha_creacion
        FROM evaluaciones_modulo 
        WHERE modulo_id = 15
        ORDER BY orden, id
    ");
    $stmt->execute();
    $evaluaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($evaluaciones) {
        echo "<h3>Evaluaciones encontradas en el módulo 15:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Título</th><th>Tipo</th><th>Activo</th><th>Puntaje</th><th>Tiempo</th><th>Orden</th><th>Fecha Creación</th></tr>";
        
        foreach ($evaluaciones as $eval) {
            echo "<tr>";
            echo "<td>" . $eval['id'] . "</td>";
            echo "<td>" . htmlspecialchars($eval['titulo']) . "</td>";
            echo "<td>" . $eval['tipo'] . "</td>";
            echo "<td>" . ($eval['activo'] ? 'SÍ' : 'NO') . "</td>";
            echo "<td>" . $eval['puntaje_total'] . "</td>";
            echo "<td>" . $eval['tiempo_limite'] . "</td>";
            echo "<td>" . $eval['orden'] . "</td>";
            echo "<td>" . $eval['fecha_creacion'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Verificar preguntas para cada evaluación
        foreach ($evaluaciones as $eval) {
            echo "<h4>Preguntas de la evaluación: " . htmlspecialchars($eval['titulo']) . " (ID: " . $eval['id'] . ")</h4>";
            
            $stmt_preguntas = $pdo->prepare("
                SELECT id, pregunta, tipo, puntaje, orden
                FROM preguntas_evaluacion 
                WHERE evaluacion_id = ?
                ORDER BY orden, id
            ");
            $stmt_preguntas->execute([$eval['id']]);
            $preguntas = $stmt_preguntas->fetchAll(PDO::FETCH_ASSOC);
            
            if ($preguntas) {
                echo "<ul>";
                foreach ($preguntas as $pregunta) {
                    echo "<li>ID: " . $pregunta['id'] . " - " . htmlspecialchars(substr($pregunta['pregunta'], 0, 100)) . "... (Tipo: " . $pregunta['tipo'] . ", Puntaje: " . $pregunta['puntaje'] . ")</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>❌ No hay preguntas para esta evaluación</p>";
            }
        }
        
    } else {
        echo "<h3>❌ No se encontraron evaluaciones en el módulo 15</h3>";
        
        // Verificar si el módulo 15 existe
        $stmt_modulo = $pdo->prepare("SELECT id, titulo, curso_id FROM modulos WHERE id = 15");
        $stmt_modulo->execute();
        $modulo = $stmt_modulo->fetch(PDO::FETCH_ASSOC);
        
        if ($modulo) {
            echo "<p>✅ El módulo 15 existe: " . htmlspecialchars($modulo['titulo']) . " (Curso ID: " . $modulo['curso_id'] . ")</p>";
        } else {
            echo "<p>❌ El módulo 15 no existe en la base de datos</p>";
        }
    }
    
    // Verificar la consulta que usa modulo_contenido.php
    echo "<h3>Simulando consulta de modulo_contenido.php:</h3>";
    $estudiante_id = 7; // ID de ejemplo
    
    $stmt = $pdo->prepare("
        SELECT e.*, 
               COUNT(ie.id) as intentos_realizados,
               MAX(ie.puntaje_obtenido) as mejor_calificacion,
               CASE 
                   WHEN MAX(ie.puntaje_obtenido) >= 100.0 THEN 1 
                   ELSE 0 
               END as aprobada,
               CASE 
                   WHEN e.intentos_permitidos > 0 AND COUNT(ie.id) >= e.intentos_permitidos 
                        AND (MAX(ie.puntaje_obtenido) < 100.0 OR MAX(ie.puntaje_obtenido) IS NULL) THEN 1
                   ELSE 0 
               END as sin_intentos
        FROM evaluaciones_modulo e
        LEFT JOIN intentos_evaluacion ie ON e.id = ie.evaluacion_id AND ie.usuario_id = :estudiante_id
        WHERE e.modulo_id = :modulo_id AND e.activo = 1
        GROUP BY e.id
        ORDER BY e.orden
    ");
    $stmt->execute([':modulo_id' => 15, ':estudiante_id' => $estudiante_id]);
    $evaluaciones_activas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($evaluaciones_activas) {
        echo "<p>✅ La consulta de modulo_contenido.php encuentra " . count($evaluaciones_activas) . " evaluación(es) activa(s)</p>";
        foreach ($evaluaciones_activas as $eval) {
            echo "<p>- " . htmlspecialchars($eval['titulo']) . " (ID: " . $eval['id'] . ", Activo: " . ($eval['activo'] ? 'SÍ' : 'NO') . ")</p>";
        }
    } else {
        echo "<p>❌ La consulta de modulo_contenido.php NO encuentra evaluaciones activas</p>";
    }
    
} catch (PDOException $e) {
    echo "<h3>❌ Error de conexión a la base de datos:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>