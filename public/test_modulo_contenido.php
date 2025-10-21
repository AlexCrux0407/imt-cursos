<?php
// Script de prueba para verificar el contenido del módulo 15
require_once '../config/database.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $modulo_id = 15;
    $estudiante_id = 7; // ID de prueba
    
    echo "<h2>Test: Contenido del Módulo 15</h2>";
    
    // Obtener información del módulo
    $stmt = $pdo->prepare("
        SELECT m.*, c.titulo as curso_titulo
        FROM modulos m
        INNER JOIN cursos c ON m.curso_id = c.id
        WHERE m.id = :modulo_id
    ");
    $stmt->execute([':modulo_id' => $modulo_id]);
    $modulo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($modulo) {
        echo "<h3>✅ Módulo encontrado:</h3>";
        echo "<p><strong>Título:</strong> " . htmlspecialchars($modulo['titulo']) . "</p>";
        echo "<p><strong>Curso:</strong> " . htmlspecialchars($modulo['curso_titulo']) . "</p>";
        echo "<p><strong>Orden:</strong> " . $modulo['orden'] . "</p>";
    } else {
        echo "<h3>❌ Módulo no encontrado</h3>";
        exit;
    }
    
    // Verificar si el módulo tiene evaluaciones activas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM evaluaciones_modulo
        WHERE modulo_id = :modulo_id AND activo = 1
    ");
    $stmt->execute([':modulo_id' => $modulo_id]);
    $tiene_eval = ((int)($stmt->fetch()['total'] ?? 0)) > 0;
    
    echo "<h3>Evaluaciones activas: " . ($tiene_eval ? 'SÍ' : 'NO') . "</h3>";
    
    // Obtener evaluaciones del módulo (misma consulta que modulo_contenido.php)
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
    $stmt->execute([':modulo_id' => $modulo_id, ':estudiante_id' => $estudiante_id]);
    $evaluaciones_modulo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($evaluaciones_modulo)) {
        echo "<h3>✅ Evaluaciones encontradas (" . count($evaluaciones_modulo) . "):</h3>";
        
        foreach ($evaluaciones_modulo as $index => $evaluacion) {
            echo "<div style='border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 8px;'>";
            echo "<h4>" . htmlspecialchars($evaluacion['titulo']) . "</h4>";
            echo "<p><strong>ID:</strong> " . $evaluacion['id'] . "</p>";
            echo "<p><strong>Tipo:</strong> " . $evaluacion['tipo'] . "</p>";
            echo "<p><strong>Descripción:</strong> " . htmlspecialchars($evaluacion['descripcion']) . "</p>";
            echo "<p><strong>Activo:</strong> " . ($evaluacion['activo'] ? 'SÍ' : 'NO') . "</p>";
            echo "<p><strong>Intentos realizados:</strong> " . $evaluacion['intentos_realizados'] . "</p>";
            echo "<p><strong>Mejor calificación:</strong> " . ($evaluacion['mejor_calificacion'] ?? 'N/A') . "</p>";
            
            // Simular la lógica de botones
            if ($evaluacion['sin_intentos']) {
                echo "<p><strong>Estado:</strong> Sin intentos disponibles</p>";
            } elseif ($evaluacion['aprobada']) {
                echo "<p><strong>Estado:</strong> Aprobada</p>";
                echo "<p><strong>Botón:</strong> Ver Resultado</p>";
            } elseif ($evaluacion['intentos_realizados'] > 0) {
                echo "<p><strong>Estado:</strong> Intentos previos, puede reintentar</p>";
                echo "<p><strong>Botón:</strong> Reintentar Evaluación</p>";
            } else {
                echo "<p><strong>Estado:</strong> Disponible para iniciar</p>";
                
                // Verificar si es organigrama
                if (strpos(strtolower($evaluacion['titulo']), 'organigrama') !== false) {
                    echo "<p><strong>Botón:</strong> Iniciar Organigrama (evaluacion_organigrama.php)</p>";
                } else {
                    echo "<p><strong>Botón:</strong> Iniciar Evaluación (tomar_evaluacion.php)</p>";
                }
            }
            echo "</div>";
        }
        
        echo "<h3>🔗 Enlaces de prueba:</h3>";
        echo "<ul>";
        foreach ($evaluaciones_modulo as $eval) {
            if (strpos(strtolower($eval['titulo']), 'organigrama') !== false) {
                echo "<li><a href='estudiante/evaluacion_organigrama.php?id=" . $eval['id'] . "' target='_blank'>Evaluación Organigrama (ID: " . $eval['id'] . ")</a></li>";
            } else {
                echo "<li><a href='estudiante/tomar_evaluacion.php?id=" . $eval['id'] . "' target='_blank'>Evaluación Regular (ID: " . $eval['id'] . ")</a></li>";
            }
        }
        echo "</ul>";
        
    } else {
        echo "<h3>❌ No se encontraron evaluaciones activas para este módulo</h3>";
        
        // Verificar todas las evaluaciones (incluso inactivas)
        $stmt_all = $pdo->prepare("
            SELECT id, titulo, activo, tipo
            FROM evaluaciones_modulo 
            WHERE modulo_id = :modulo_id
        ");
        $stmt_all->execute([':modulo_id' => $modulo_id]);
        $todas_evaluaciones = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
        
        if ($todas_evaluaciones) {
            echo "<h4>Todas las evaluaciones del módulo (incluso inactivas):</h4>";
            foreach ($todas_evaluaciones as $eval) {
                echo "<p>ID: " . $eval['id'] . " - " . htmlspecialchars($eval['titulo']) . " (Activo: " . ($eval['activo'] ? 'SÍ' : 'NO') . ", Tipo: " . $eval['tipo'] . ")</p>";
            }
        } else {
            echo "<h4>No hay evaluaciones en absoluto para este módulo</h4>";
        }
    }
    
} catch (PDOException $e) {
    echo "<h3>❌ Error de conexión a la base de datos:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>