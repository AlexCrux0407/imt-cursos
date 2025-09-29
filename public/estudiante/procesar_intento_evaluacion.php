<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/estudiante/dashboard.php');
    exit;
}

$evaluacion_id = (int)($_POST['evaluacion_id'] ?? 0);
$usuario_id = (int)($_SESSION['user_id'] ?? 0);

if ($evaluacion_id <= 0 || $usuario_id <= 0) {
    header('Location: ' . BASE_URL . '/estudiante/dashboard.php');
    exit;
}

try {
    $conn->beginTransaction();
    
    // Obtener información de la evaluación
    $stmt = $conn->prepare("
        SELECT e.*, m.curso_id
        FROM evaluaciones_modulo e
        INNER JOIN modulos m ON e.modulo_id = m.id
        WHERE e.id = :evaluacion_id AND e.activo = 1
    ");
    $stmt->execute([':evaluacion_id' => $evaluacion_id]);
    $evaluacion = $stmt->fetch();
    
    if (!$evaluacion) {
        throw new Exception('Evaluación no encontrada');
    }
    
    // Verificar que el estudiante esté inscrito en el curso
    $stmt = $conn->prepare("
        SELECT id FROM inscripciones 
        WHERE usuario_id = :usuario_id AND curso_id = :curso_id AND estado = 'activo'
    ");
    $stmt->execute([':usuario_id' => $usuario_id, ':curso_id' => $evaluacion['curso_id']]);
    if (!$stmt->fetch()) {
        throw new Exception('No tienes acceso a esta evaluación');
    }
    
    // Verificar si ya completó la evaluación
    $stmt = $conn->prepare("
        SELECT id FROM progreso_modulos
        WHERE usuario_id = :usuario_id AND modulo_id = :modulo_id AND evaluacion_completada = 1
    ");
    $stmt->execute([':usuario_id' => $usuario_id, ':modulo_id' => $evaluacion['modulo_id']]);
    if ($stmt->fetch()) {
        throw new Exception('Ya has completado esta evaluación');
    }
    
    // Contar intentos previos
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_intentos
        FROM intentos_evaluacion
        WHERE usuario_id = :usuario_id AND evaluacion_id = :evaluacion_id
    ");
    $stmt->execute([':usuario_id' => $usuario_id, ':evaluacion_id' => $evaluacion_id]);
    $intentos_realizados = $stmt->fetchColumn();
    
    // Verificar límite de intentos
    if ($evaluacion['intentos_permitidos'] > 0 && $intentos_realizados >= $evaluacion['intentos_permitidos']) {
        throw new Exception('Has agotado el número máximo de intentos');
    }
    
    // Obtener preguntas de la evaluación
    $stmt = $conn->prepare("
        SELECT * FROM preguntas_evaluacion
        WHERE evaluacion_id = :evaluacion_id
        ORDER BY orden ASC
    ");
    $stmt->execute([':evaluacion_id' => $evaluacion_id]);
    $preguntas = $stmt->fetchAll();
    
    if (empty($preguntas)) {
        throw new Exception('La evaluación no tiene preguntas configuradas');
    }
    
    // Crear nuevo intento
    $stmt = $conn->prepare("
        INSERT INTO intentos_evaluacion (usuario_id, evaluacion_id, fecha_intento, estado)
        VALUES (:usuario_id, :evaluacion_id, NOW(), 'en_progreso')
    ");
    $stmt->execute([
        ':usuario_id' => $usuario_id,
        ':evaluacion_id' => $evaluacion_id
    ]);
    $intento_id = $conn->lastInsertId();
    
    // Procesar respuestas y calcular puntaje
    $respuestas_correctas = 0;
    $total_preguntas = count($preguntas);
    $respuestas_procesadas = [];
    
    foreach ($preguntas as $pregunta) {
        $respuesta_estudiante = $_POST['respuesta_' . $pregunta['id']] ?? '';
        $es_correcta = false;
        
        // Evaluar respuesta según el tipo de pregunta
        switch ($pregunta['tipo']) {
            case 'multiple_choice':
                $opciones = json_decode($pregunta['opciones'], true);
                $respuesta_correcta = $pregunta['respuesta_correcta'];
                $es_correcta = ($respuesta_estudiante === $respuesta_correcta);
                break;
                
            case 'true_false':
                $respuesta_correcta = $pregunta['respuesta_correcta'];
                $es_correcta = ($respuesta_estudiante === $respuesta_correcta);
                break;
                
            case 'short_answer':
                // Para respuestas cortas, marcar como pendiente de revisión manual
                $es_correcta = null; // null indica que requiere revisión manual
                break;
        }
        
        // Guardar respuesta del estudiante
        $stmt = $conn->prepare("
            INSERT INTO respuestas_estudiante (intento_id, pregunta_id, respuesta, es_correcta, requiere_revision)
            VALUES (:intento_id, :pregunta_id, :respuesta, :es_correcta, :requiere_revision)
        ");
        $stmt->execute([
            ':intento_id' => $intento_id,
            ':pregunta_id' => $pregunta['id'],
            ':respuesta' => $respuesta_estudiante,
            ':es_correcta' => $es_correcta,
            ':requiere_revision' => ($es_correcta === null) ? 1 : 0
        ]);
        
        if ($es_correcta === true) {
            $respuestas_correctas++;
        }
        
        $respuestas_procesadas[] = [
            'pregunta_id' => $pregunta['id'],
            'respuesta' => $respuesta_estudiante,
            'es_correcta' => $es_correcta,
            'tipo' => $pregunta['tipo']
        ];
    }
    
    // Calcular puntaje
    $preguntas_automaticas = array_filter($respuestas_procesadas, function($r) {
        return $r['es_correcta'] !== null;
    });
    
    $preguntas_manuales = array_filter($respuestas_procesadas, function($r) {
        return $r['es_correcta'] === null;
    });
    
    if (count($preguntas_manuales) > 0) {
        // Hay preguntas que requieren revisión manual
        $estado_intento = 'pendiente_revision';
        $puntaje_obtenido = null;
    } else {
        // Todas las preguntas son automáticas
        $puntaje_obtenido = ($respuestas_correctas / $total_preguntas) * $evaluacion['puntaje_maximo'];
        $estado_intento = 'completado';
    }
    
    // Actualizar intento con el resultado
    $stmt = $conn->prepare("
        UPDATE intentos_evaluacion 
        SET estado = :estado, puntaje_obtenido = :puntaje, fecha_completado = NOW()
        WHERE id = :intento_id
    ");
    $stmt->execute([
        ':estado' => $estado_intento,
        ':puntaje' => $puntaje_obtenido,
        ':intento_id' => $intento_id
    ]);
    
    $conn->commit();
    
    // Redirigir según el resultado
    if ($estado_intento === 'pendiente_revision') {
        $mensaje = 'Tu evaluación ha sido enviada y está pendiente de revisión por el docente.';
        $tipo = 'info';
    } else {
        $aprobado = $puntaje_obtenido >= $evaluacion['puntaje_minimo_aprobacion'];
        if ($aprobado) {
            // Marcar progreso del módulo como completado
            $stmt = $conn->prepare("
                INSERT INTO progreso_modulos (usuario_id, modulo_id, completado, fecha_completado, evaluacion_completada, fecha_evaluacion_completada, puntaje_evaluacion)
                VALUES (:usuario_id, :modulo_id, 1, NOW(), 1, NOW(), :puntaje)
                ON DUPLICATE KEY UPDATE 
                    completado = 1, 
                    fecha_completado = NOW(), 
                    evaluacion_completada = 1, 
                    fecha_evaluacion_completada = NOW(), 
                    puntaje_evaluacion = :puntaje
            ");
            $stmt->execute([
                ':usuario_id' => $usuario_id,
                ':modulo_id' => $evaluacion['modulo_id'],
                ':puntaje' => $puntaje_obtenido
            ]);
            
            // Actualizar progreso del curso
            $stmt = $conn->prepare("
                SELECT m.curso_id, COUNT(m.id) AS total_modulos,
                       SUM(CASE WHEN pm.evaluacion_completada = 1 THEN 1 ELSE 0 END) AS modulos_completados
                FROM modulos m
                LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = :usuario_id
                WHERE m.curso_id = :curso_id
                GROUP BY m.curso_id
            ");
            $stmt->execute([
                ':usuario_id' => $usuario_id,
                ':curso_id' => $evaluacion['curso_id']
            ]);
            $curso_info = $stmt->fetch();
            
            if ($curso_info) {
                $progreso_porcentaje = ($curso_info['modulos_completados'] / $curso_info['total_modulos']) * 100;
                $estado_curso = ($progreso_porcentaje >= 100) ? 'completado' : 'activo';
                
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
                    ':curso_id' => $evaluacion['curso_id']
                ]);
            }
            
            $mensaje = '¡Felicitaciones! Has aprobado la evaluación con un puntaje de ' . number_format($puntaje_obtenido, 1) . '%. El módulo ha sido marcado como completado.';
            $tipo = 'success';
        } else {
            $mensaje = 'Has obtenido un puntaje de ' . number_format($puntaje_obtenido, 1) . '%. Necesitas al menos ' . $evaluacion['puntaje_minimo_aprobacion'] . '% para aprobar.';
            $tipo = 'warning';
        }
    }
    
    // Redirigir con mensaje
    $redirect_url = BASE_URL . '/estudiante/resultado_evaluacion.php?intento_id=' . $intento_id . '&mensaje=' . urlencode($mensaje) . '&tipo=' . $tipo;
    header('Location: ' . $redirect_url);
    exit;
    
} catch (Exception $e) {
    $conn->rollBack();
    $error_message = 'Error al procesar la evaluación: ' . $e->getMessage();
    $redirect_url = BASE_URL . '/estudiante/curso_contenido.php?id=' . $evaluacion['curso_id'] . '&error=' . urlencode($error_message);
    header('Location: ' . $redirect_url);
    exit;
}
?>