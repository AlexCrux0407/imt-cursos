<?php
require_once 'config/database.php';

try {
    $conn->beginTransaction();
    
    echo "=== SCRIPT DE CORRECCIÓN DE PROGRESO ===\n";
    
    // Obtener todos los usuarios con intentos de evaluación exitosos
    $stmt = $conn->prepare("
        SELECT DISTINCT ie.usuario_id, e.modulo_id, e.id as evaluacion_id,
               MAX(ie.puntaje_obtenido) as mejor_puntaje,
               e.puntaje_minimo_aprobacion
        FROM intentos_evaluacion ie
        JOIN evaluaciones_modulo e ON ie.evaluacion_id = e.id
        WHERE ie.estado = 'completado' AND e.activo = 1
        GROUP BY ie.usuario_id, e.id
        HAVING mejor_puntaje >= e.puntaje_minimo_aprobacion
        ORDER BY ie.usuario_id, e.modulo_id
    ");
    $stmt->execute();
    $evaluaciones_aprobadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Evaluaciones aprobadas encontradas: " . count($evaluaciones_aprobadas) . "\n\n";
    
    // Agrupar por usuario y módulo
    $progreso_por_modulo = [];
    foreach ($evaluaciones_aprobadas as $eval) {
        $key = $eval['usuario_id'] . '_' . $eval['modulo_id'];
        if (!isset($progreso_por_modulo[$key])) {
            $progreso_por_modulo[$key] = [
                'usuario_id' => $eval['usuario_id'],
                'modulo_id' => $eval['modulo_id'],
                'evaluaciones_aprobadas' => 0,
                'mejor_puntaje' => 0
            ];
        }
        $progreso_por_modulo[$key]['evaluaciones_aprobadas']++;
        $progreso_por_modulo[$key]['mejor_puntaje'] = max(
            $progreso_por_modulo[$key]['mejor_puntaje'], 
            $eval['mejor_puntaje']
        );
    }
    
    // Para cada módulo con evaluaciones aprobadas, verificar si todas están aprobadas
    foreach ($progreso_por_modulo as $progreso) {
        $usuario_id = $progreso['usuario_id'];
        $modulo_id = $progreso['modulo_id'];
        
        // Contar total de evaluaciones activas del módulo
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total_evaluaciones
            FROM evaluaciones_modulo
            WHERE modulo_id = :modulo_id AND activo = 1
        ");
        $stmt->execute([':modulo_id' => $modulo_id]);
        $total_evaluaciones = $stmt->fetch()['total_evaluaciones'];
        
        // Si todas las evaluaciones están aprobadas, actualizar progreso_modulos
        if ($progreso['evaluaciones_aprobadas'] >= $total_evaluaciones) {
            echo "Actualizando progreso - Usuario: {$usuario_id}, Módulo: {$modulo_id}\n";
            echo "  Evaluaciones aprobadas: {$progreso['evaluaciones_aprobadas']}/{$total_evaluaciones}\n";
            echo "  Mejor puntaje: {$progreso['mejor_puntaje']}%\n";
            
            $stmt = $conn->prepare("
                INSERT INTO progreso_modulos 
                (usuario_id, modulo_id, completado, fecha_completado, evaluacion_completada, fecha_evaluacion_completada, puntaje_evaluacion)
                VALUES (:usuario_id, :modulo_id, 1, NOW(), 1, NOW(), :puntaje)
                ON DUPLICATE KEY UPDATE 
                    completado = 1, 
                    fecha_completado = NOW(), 
                    evaluacion_completada = 1, 
                    fecha_evaluacion_completada = NOW(), 
                    puntaje_evaluacion = :puntaje_update
            ");
            $stmt->execute([
                ':usuario_id' => $usuario_id,
                ':modulo_id' => $modulo_id,
                ':puntaje' => $progreso['mejor_puntaje'],
                ':puntaje_update' => $progreso['mejor_puntaje']
            ]);
            
            echo "  ✓ Progreso actualizado correctamente\n\n";
        } else {
            echo "Módulo incompleto - Usuario: {$usuario_id}, Módulo: {$modulo_id}\n";
            echo "  Evaluaciones aprobadas: {$progreso['evaluaciones_aprobadas']}/{$total_evaluaciones}\n\n";
        }
    }
    
    // Actualizar progreso de cursos
    echo "=== ACTUALIZANDO PROGRESO DE CURSOS ===\n";
    $stmt = $conn->prepare("
        SELECT DISTINCT i.usuario_id, i.curso_id
        FROM inscripciones i
        WHERE i.estado = 'activo'
    ");
    $stmt->execute();
    $inscripciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($inscripciones as $inscripcion) {
        $usuario_id = $inscripcion['usuario_id'];
        $curso_id = $inscripcion['curso_id'];
        
        // Calcular progreso del curso
        $stmt = $conn->prepare("
            SELECT COUNT(m.id) AS total_modulos,
                   SUM(CASE WHEN pm.evaluacion_completada = 1 THEN 1 ELSE 0 END) AS modulos_completados
            FROM modulos m
            LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = :usuario_id
            WHERE m.curso_id = :curso_id
        ");
        $stmt->execute([':usuario_id' => $usuario_id, ':curso_id' => $curso_id]);
        $curso_info = $stmt->fetch();
        
        if ($curso_info && $curso_info['total_modulos'] > 0) {
            $progreso_porcentaje = ($curso_info['modulos_completados'] / $curso_info['total_modulos']) * 100;
            $estado_curso = ($progreso_porcentaje >= 100) ? 'completado' : 'activo';
            
            echo "Usuario {$usuario_id}, Curso {$curso_id}: {$progreso_porcentaje}% ({$curso_info['modulos_completados']}/{$curso_info['total_modulos']} módulos)\n";
            
            $stmt = $conn->prepare("
                UPDATE inscripciones
                SET progreso = :progreso, estado = :estado" . 
                ($estado_curso === 'completado' ? ', fecha_completado = NOW()' : '') . "
                WHERE usuario_id = :usuario_id AND curso_id = :curso_id
            ");
            $stmt->execute([
                ':progreso' => $progreso_porcentaje,
                ':estado' => $estado_curso,
                ':usuario_id' => $usuario_id,
                ':curso_id' => $curso_id
            ]);
        }
    }
    
    $conn->commit();
    echo "\n✅ Corrección de progreso completada exitosamente.\n";
    
} catch (Exception $e) {
    $conn->rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>