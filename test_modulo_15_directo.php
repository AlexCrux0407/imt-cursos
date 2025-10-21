<?php
require_once 'config/database.php';

echo "<h2>Test directo del Módulo 15</h2>";

try {
    // Verificar módulo 15
    $stmt = $conn->prepare("SELECT * FROM modulos WHERE id = 15");
    $stmt->execute();
    $modulo = $stmt->fetch();
    
    if ($modulo) {
        echo "<h3>✅ Módulo 15 encontrado:</h3>";
        echo "<p><strong>Título:</strong> " . htmlspecialchars($modulo['titulo']) . "</p>";
        echo "<p><strong>Curso ID:</strong> " . $modulo['curso_id'] . "</p>";
        echo "<p><strong>Orden:</strong> " . $modulo['orden'] . "</p>";
    } else {
        echo "<h3>❌ Módulo 15 NO encontrado</h3>";
        
        // Mostrar todos los módulos disponibles
        $stmt_all = $conn->prepare("SELECT id, titulo, curso_id FROM modulos ORDER BY curso_id, orden");
        $stmt_all->execute();
        $modulos = $stmt_all->fetchAll();
        
        echo "<h4>Módulos disponibles:</h4>";
        echo "<ul>";
        foreach ($modulos as $mod) {
            echo "<li>ID: {$mod['id']} - {$mod['titulo']} (Curso: {$mod['curso_id']})</li>";
        }
        echo "</ul>";
        
        exit;
    }
    
    // Verificar evaluaciones del módulo 15
    echo "<h3>Evaluaciones del Módulo 15:</h3>";
    $stmt_eval = $conn->prepare("SELECT * FROM evaluaciones_modulo WHERE modulo_id = 15");
    $stmt_eval->execute();
    $evaluaciones = $stmt_eval->fetchAll();
    
    if ($evaluaciones) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Título</th><th>Tipo</th><th>Activo</th><th>Puntaje Max</th><th>Intentos</th></tr>";
        foreach ($evaluaciones as $eval) {
            $activo_text = $eval['activo'] ? '✅ Sí' : '❌ No';
            echo "<tr>";
            echo "<td>{$eval['id']}</td>";
            echo "<td>" . htmlspecialchars($eval['titulo']) . "</td>";
            echo "<td>{$eval['tipo']}</td>";
            echo "<td>{$activo_text}</td>";
            echo "<td>{$eval['puntaje_maximo']}</td>";
            echo "<td>{$eval['intentos_permitidos']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Verificar preguntas para cada evaluación
        foreach ($evaluaciones as $eval) {
            echo "<h4>Preguntas para evaluación '{$eval['titulo']}' (ID: {$eval['id']}):</h4>";
            $stmt_preg = $conn->prepare("SELECT * FROM preguntas_evaluacion WHERE evaluacion_id = ?");
            $stmt_preg->execute([$eval['id']]);
            $preguntas = $stmt_preg->fetchAll();
            
            if ($preguntas) {
                echo "<ul>";
                foreach ($preguntas as $preg) {
                    echo "<li>ID: {$preg['id']} - Tipo: {$preg['tipo']} - Puntaje: {$preg['puntaje']}</li>";
                }
                echo "</ul>";
            } else {
                echo "<p>❌ No hay preguntas para esta evaluación</p>";
            }
        }
        
    } else {
        echo "<p>❌ No hay evaluaciones para el módulo 15</p>";
    }
    
    // Simular la consulta de modulo_contenido.php
    echo "<h3>Simulación de consulta de modulo_contenido.php:</h3>";
    $stmt_sim = $conn->prepare("
        SELECT 
            e.id, e.titulo, e.descripcion, e.tipo, e.puntaje_maximo, e.puntaje_minimo,
            e.tiempo_limite, e.intentos_permitidos, e.activo,
            COALESCE(COUNT(ie.id), 0) AS intentos_realizados,
            COALESCE(MAX(ie.puntaje), 0) AS mejor_puntaje,
            CASE 
                WHEN MAX(ie.puntaje) >= e.puntaje_minimo THEN 1 
                ELSE 0 
            END AS aprobado,
            CASE 
                WHEN COUNT(ie.id) >= e.intentos_permitidos THEN 1 
                ELSE 0 
            END AS intentos_agotados
        FROM evaluaciones_modulo e
        LEFT JOIN intentos_evaluacion ie ON e.id = ie.evaluacion_id AND ie.usuario_id = 1
        WHERE e.modulo_id = 15 AND e.activo = 1
        GROUP BY e.id
        ORDER BY e.id
    ");
    $stmt_sim->execute();
    $evaluaciones_sim = $stmt_sim->fetchAll();
    
    if ($evaluaciones_sim) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Título</th><th>Intentos</th><th>Mejor Puntaje</th><th>Aprobado</th><th>Intentos Agotados</th></tr>";
        foreach ($evaluaciones_sim as $eval) {
            $aprobado_text = $eval['aprobado'] ? '✅ Sí' : '❌ No';
            $agotados_text = $eval['intentos_agotados'] ? '❌ Sí' : '✅ No';
            echo "<tr>";
            echo "<td>{$eval['id']}</td>";
            echo "<td>" . htmlspecialchars($eval['titulo']) . "</td>";
            echo "<td>{$eval['intentos_realizados']}/{$eval['intentos_permitidos']}</td>";
            echo "<td>{$eval['mejor_puntaje']}/{$eval['puntaje_maximo']}</td>";
            echo "<td>{$aprobado_text}</td>";
            echo "<td>{$agotados_text}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>❌ La consulta simulada no devolvió evaluaciones activas</p>";
    }
    
} catch (Exception $e) {
    echo "<h3>❌ Error:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>