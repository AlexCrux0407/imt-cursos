<?php
require_once 'config/database.php';

try {
    echo "=== VERIFICACIÓN CURSO EJEMPLO ===\n";
    
    // Buscar el curso "ejemplo"
    $stmt = $conn->prepare("
        SELECT id, titulo, descripcion 
        FROM cursos 
        WHERE titulo LIKE '%ejemplo%' OR descripcion LIKE '%ejemplo%'
    ");
    $stmt->execute();
    $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cursos)) {
        echo "❌ No se encontró ningún curso con 'ejemplo' en el título o descripción.\n";
        
        // Mostrar todos los cursos disponibles
        $stmt = $conn->prepare("SELECT id, titulo FROM cursos ORDER BY id");
        $stmt->execute();
        $todos_cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nCursos disponibles:\n";
        foreach ($todos_cursos as $curso) {
            echo "- ID {$curso['id']}: {$curso['titulo']}\n";
        }
        exit;
    }
    
    foreach ($cursos as $curso) {
        echo "\n📚 CURSO ENCONTRADO:\n";
        echo "ID: {$curso['id']}\n";
        echo "Título: {$curso['titulo']}\n";
        
        $curso_id = $curso['id'];
        
        // Verificar inscripción del usuario 7
        $stmt = $conn->prepare("
            SELECT progreso, fecha_inscripcion, estado
            FROM inscripciones 
            WHERE usuario_id = 7 AND curso_id = ?
        ");
        $stmt->execute([$curso_id]);
        $inscripcion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$inscripcion) {
            echo "❌ El usuario 7 no está inscrito en este curso.\n";
            continue;
        }
        
        echo "\n📋 INSCRIPCIÓN:\n";
        echo "Progreso: {$inscripcion['progreso']}%\n";
        echo "Estado: {$inscripcion['estado']}\n";
        echo "Fecha inscripción: {$inscripcion['fecha_inscripcion']}\n";
        
        // Obtener módulos del curso
        $stmt = $conn->prepare("
            SELECT m.id, m.titulo, m.orden,
                   pm.completado as modulo_completado,
                   pm.evaluacion_completada,
                   pm.fecha_completado
            FROM modulos m
            LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = 7
            WHERE m.curso_id = ?
            ORDER BY m.orden
        ");
        $stmt->execute([$curso_id]);
        $modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\n📖 MÓDULOS DEL CURSO:\n";
        foreach ($modulos as $i => $modulo) {
            echo "Módulo {$modulo['orden']}: {$modulo['titulo']}\n";
            echo "  Completado: " . ($modulo['modulo_completado'] ? 'Sí' : 'No') . "\n";
            echo "  Evaluación completada: " . ($modulo['evaluacion_completada'] ? 'Sí' : 'No') . "\n";
            
            if ($modulo['fecha_completado']) {
                echo "  Fecha completado: {$modulo['fecha_completado']}\n";
            }
            
            // Verificar evaluaciones de este módulo
            $stmt_eval = $conn->prepare("
                SELECT e.id, e.titulo, e.activo, e.puntaje_minimo_aprobacion,
                       COUNT(ie.id) as total_intentos,
                       MAX(ie.puntaje_obtenido) as mejor_puntaje,
                       CASE WHEN MAX(ie.puntaje_obtenido) >= e.puntaje_minimo_aprobacion THEN 'Aprobada' ELSE 'No Aprobada' END as estado
                FROM evaluaciones_modulo e
                LEFT JOIN intentos_evaluacion ie ON e.id = ie.evaluacion_id AND ie.usuario_id = 7
                WHERE e.modulo_id = ?
                GROUP BY e.id
                ORDER BY e.orden
            ");
            $stmt_eval->execute([$modulo['id']]);
            $evaluaciones = $stmt_eval->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($evaluaciones)) {
                echo "  Evaluaciones:\n";
                $evaluaciones_activas = 0;
                $evaluaciones_aprobadas = 0;
                
                foreach ($evaluaciones as $eval) {
                    if ($eval['activo']) {
                        $evaluaciones_activas++;
                        if ($eval['estado'] === 'Aprobada') {
                            $evaluaciones_aprobadas++;
                        }
                    }
                    
                    echo "    - {$eval['titulo']} (ID: {$eval['id']})\n";
                    echo "      Activa: " . ($eval['activo'] ? 'Sí' : 'No') . "\n";
                    echo "      Intentos: {$eval['total_intentos']}\n";
                    echo "      Mejor puntaje: " . ($eval['mejor_puntaje'] ?? 'No realizada') . "%\n";
                    echo "      Estado: {$eval['estado']}\n";
                }
                
                echo "  Resumen evaluaciones: {$evaluaciones_aprobadas}/{$evaluaciones_activas} aprobadas\n";
                
                if ($evaluaciones_activas > 0 && $evaluaciones_aprobadas < $evaluaciones_activas) {
                    echo "  ⚠️  PROBLEMA: Faltan " . ($evaluaciones_activas - $evaluaciones_aprobadas) . " evaluación(es) por aprobar\n";
                }
            } else {
                echo "  Sin evaluaciones\n";
            }
            
            // Verificar si el siguiente módulo debería estar desbloqueado
            if ($i < count($modulos) - 1) {
                $siguiente_modulo = $modulos[$i + 1];
                $puede_acceder = ($modulo['evaluacion_completada'] == 1);
                echo "  Siguiente módulo desbloqueado: " . ($puede_acceder ? 'Sí' : 'No') . "\n";
            }
            
            echo "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>