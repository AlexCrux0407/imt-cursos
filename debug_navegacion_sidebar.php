<?php
require_once 'config/database.php';

echo "=== DEBUG: NAVEGACIÓN DESDE SIDEBAR ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

$usuario_id = 7;
$curso_id = 4;

echo "Usuario ID: $usuario_id\n";
echo "Curso ID: $curso_id\n\n";

try {
    // 1. Simular cálculo de progreso como en mis_cursos.php
    echo "1. CÁLCULO DE PROGRESO COMO EN MIS_CURSOS.PHP:\n";
    echo "=============================================\n";
    
    $stmt = $conn->prepare("
        SELECT c.*, i.progreso, i.fecha_inscripcion, i.estado as estado_inscripcion,
               COUNT(DISTINCT m.id) as total_modulos,
               COUNT(DISTINCT CASE WHEN pm.completado = 1 THEN m.id END) as modulos_completados,
               COUNT(DISTINCT e.id) as total_evaluaciones,
               COUNT(DISTINCT CASE WHEN pm.evaluacion_completada = 1 THEN e.id END) as evaluaciones_completadas
        FROM inscripciones i
        INNER JOIN cursos c ON i.curso_id = c.id
        LEFT JOIN modulos m ON c.id = m.curso_id
        LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = i.usuario_id
        LEFT JOIN evaluaciones_modulo e ON m.id = e.modulo_id AND e.activo = 1
        WHERE c.id = :curso_id AND i.usuario_id = :usuario_id
        GROUP BY c.id, i.progreso, i.fecha_inscripcion, i.estado
    ");
    $stmt->execute([':curso_id' => $curso_id, ':usuario_id' => $usuario_id]);
    $curso_mis_cursos = $stmt->fetch();
    
    if ($curso_mis_cursos) {
        echo "Progreso almacenado en inscripciones: {$curso_mis_cursos['progreso']}%\n";
        echo "Módulos completados: {$curso_mis_cursos['modulos_completados']}/{$curso_mis_cursos['total_modulos']}\n";
        echo "Evaluaciones completadas: {$curso_mis_cursos['evaluaciones_completadas']}/{$curso_mis_cursos['total_evaluaciones']}\n";
        
        $progreso_calculado = ($curso_mis_cursos['evaluaciones_completadas'] / $curso_mis_cursos['total_evaluaciones']) * 100;
        echo "Progreso calculado: " . number_format($progreso_calculado, 2) . "%\n";
    }

    echo "\n";

    // 2. Simular cálculo de progreso como en curso_sidebar.php
    echo "2. CÁLCULO DE PROGRESO COMO EN CURSO_SIDEBAR.PHP:\n";
    echo "===============================================\n";
    
    // Obtener módulos y su progreso
    $stmt = $conn->prepare("
        SELECT m.id, m.titulo, m.orden,
               IF(pm.evaluacion_completada = 1, 1, 0) AS evaluacion_completada,
               pm.completado,
               pm.puntaje_evaluacion
        FROM modulos m
        LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = :usuario_id
        WHERE m.curso_id = :curso_id
        ORDER BY m.orden
    ");
    $stmt->execute([':curso_id' => $curso_id, ':usuario_id' => $usuario_id]);
    $modulos_sidebar = $stmt->fetchAll();
    
    $total_modulos_sidebar = count($modulos_sidebar);
    $evaluaciones_completadas_sidebar = 0;
    
    foreach ($modulos_sidebar as $modulo) {
        $estado = $modulo['evaluacion_completada'] ? 'COMPLETADO' : 'PENDIENTE';
        echo "- {$modulo['titulo']}: $estado";
        if ($modulo['evaluacion_completada']) {
            echo " (Puntuación: " . ($modulo['puntaje_evaluacion'] ?? 'N/A') . ")";
            $evaluaciones_completadas_sidebar++;
        }
        echo "\n";
    }
    
    $progreso_sidebar = $total_modulos_sidebar > 0 ? ($evaluaciones_completadas_sidebar / $total_modulos_sidebar) * 100 : 0;
    echo "Progreso calculado en sidebar: " . number_format($progreso_sidebar, 2) . "%\n";

    echo "\n";

    // 3. Simular navegación a modulo_contenido.php
    echo "3. SIMULANDO NAVEGACIÓN A MODULO_CONTENIDO.PHP:\n";
    echo "==============================================\n";
    
    $modulo1_id = 24; // Módulo 1
    
    // Verificar acceso al módulo (lógica de modulo_contenido.php)
    $stmt = $conn->prepare("
        SELECT m.*, c.titulo as curso_titulo, c.descripcion as curso_descripcion,
               i.usuario_id as inscrito
        FROM modulos m
        INNER JOIN cursos c ON m.curso_id = c.id
        LEFT JOIN inscripciones i ON c.id = i.curso_id AND i.usuario_id = :estudiante_id
        WHERE m.id = :modulo_id AND i.usuario_id IS NOT NULL
    ");
    $stmt->execute([':modulo_id' => $modulo1_id, ':estudiante_id' => $usuario_id]);
    $modulo_contenido = $stmt->fetch();
    
    if ($modulo_contenido) {
        echo "✅ Módulo encontrado: {$modulo_contenido['titulo']}\n";
        
        // Verificar progreso del módulo específico
        $stmt = $conn->prepare("
            SELECT * FROM progreso_modulos 
            WHERE modulo_id = :modulo_id AND usuario_id = :usuario_id
        ");
        $stmt->execute([':modulo_id' => $modulo1_id, ':usuario_id' => $usuario_id]);
        $progreso_modulo = $stmt->fetch();
        
        if ($progreso_modulo) {
            echo "Progreso del módulo:\n";
            echo "   - Completado: " . ($progreso_modulo['completado'] ? 'SÍ' : 'NO') . "\n";
            echo "   - Evaluación completada: " . ($progreso_modulo['evaluacion_completada'] ? 'SÍ' : 'NO') . "\n";
            echo "   - Puntuación: " . ($progreso_modulo['puntaje_evaluacion'] ?? 'N/A') . "\n";
        } else {
            echo "❌ No se encontró progreso para este módulo\n";
        }
    } else {
        echo "❌ Módulo no encontrado o acceso denegado\n";
    }

    echo "\n";

    // 4. Verificar estado actual en inscripciones
    echo "4. ESTADO ACTUAL EN TABLA INSCRIPCIONES:\n";
    echo "======================================\n";
    
    $stmt = $conn->prepare("
        SELECT progreso, estado, fecha_completado 
        FROM inscripciones 
        WHERE curso_id = :curso_id AND usuario_id = :usuario_id
    ");
    $stmt->execute([':curso_id' => $curso_id, ':usuario_id' => $usuario_id]);
    $inscripcion = $stmt->fetch();
    
    if ($inscripcion) {
        echo "Progreso en inscripciones: {$inscripcion['progreso']}%\n";
        echo "Estado: {$inscripcion['estado']}\n";
        echo "Fecha completado: " . ($inscripcion['fecha_completado'] ?? 'N/A') . "\n";
    }

    echo "\n";

    // 5. Comparar diferencias
    echo "5. ANÁLISIS DE DIFERENCIAS:\n";
    echo "==========================\n";
    
    if (isset($progreso_calculado) && isset($progreso_sidebar)) {
        if ($progreso_calculado != $progreso_sidebar) {
            echo "⚠️  INCONSISTENCIA DETECTADA:\n";
            echo "   - Progreso en mis_cursos: " . number_format($progreso_calculado, 2) . "%\n";
            echo "   - Progreso en sidebar: " . number_format($progreso_sidebar, 2) . "%\n";
            echo "   - Diferencia: " . number_format(abs($progreso_calculado - $progreso_sidebar), 2) . "%\n";
        } else {
            echo "✅ Los cálculos de progreso son consistentes\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DEL DEBUG ===\n";
?>