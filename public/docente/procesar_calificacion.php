<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/docente/revisar_evaluaciones.php');
    exit;
}

$intento_id = (int)($_POST['intento_id'] ?? 0);
$puntajes = $_POST['puntaje'] ?? [];
$comentarios = $_POST['comentario'] ?? [];

if ($intento_id <= 0) {
    header('Location: ' . BASE_URL . '/docente/revisar_evaluaciones.php?error=datos_invalidos');
    exit;
}

try {
    $conn->beginTransaction();
    
    // Verificar acceso del docente al intento
    $stmt = $conn->prepare("
        SELECT 
            i.*,
            e.puntaje_maximo,
            e.puntaje_minimo_aprobacion,
            e.modulo_id
        FROM intentos_evaluacion i
        INNER JOIN evaluaciones_modulo e ON i.evaluacion_id = e.id
        INNER JOIN modulos m ON e.modulo_id = m.id
        INNER JOIN cursos c ON m.curso_id = c.id
        WHERE i.id = :intento_id 
        AND i.estado = 'completado' 
        AND i.puntaje_obtenido IS NULL
        AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id)
    ");
    $stmt->execute([
        ':intento_id' => $intento_id, 
        ':docente_id' => $_SESSION['user_id']
    ]);
    $intento = $stmt->fetch();
    
    if (!$intento) {
        throw new Exception('Intento no encontrado o sin acceso');
    }
    
    // Obtener todas las respuestas del intento con sus puntajes
    $stmt = $conn->prepare("
        SELECT r.*, p.puntaje as puntaje_pregunta
        FROM respuestas_estudiante r
        INNER JOIN preguntas_evaluacion p ON r.pregunta_id = p.id
        WHERE r.intento_id = :intento_id
    ");
    $stmt->execute([':intento_id' => $intento_id]);
    $respuestas = $stmt->fetchAll();
    
    if (empty($respuestas)) {
        throw new Exception('No se encontraron respuestas para este intento');
    }
    
    $puntaje_total_obtenido = 0;
    $puntaje_total_posible = 0;
    
    // Procesar cada respuesta
    foreach ($respuestas as $respuesta) {
        $puntaje_total_posible += $respuesta['puntaje_pregunta'];
        
        if ($respuesta['requiere_revision'] == 1) {
            // Respuesta que requiere calificación manual
            $puntaje_asignado = (float)($puntajes[$respuesta['id']] ?? 0);
            $comentario = trim($comentarios[$respuesta['id']] ?? '');
            
            // Validar que el puntaje no exceda el máximo
            if ($puntaje_asignado > $respuesta['puntaje_pregunta']) {
                throw new Exception("El puntaje asignado ({$puntaje_asignado}) no puede ser mayor al puntaje máximo de la pregunta ({$respuesta['puntaje_pregunta']})");
            }
            
            if ($puntaje_asignado < 0) {
                throw new Exception("El puntaje no puede ser negativo");
            }
            
            // Actualizar la respuesta con la calificación
            $stmt = $conn->prepare("
                UPDATE respuestas_estudiante 
                SET es_correcta = :es_correcta, 
                    requiere_revision = 0,
                    comentario_docente = :comentario,
                    puntaje_asignado = :puntaje_asignado,
                    fecha_revision = NOW()
                WHERE id = :respuesta_id
            ");
            
            // Determinar si es correcta basado en el puntaje (consideramos correcta si obtiene más del 50% del puntaje)
            $es_correcta = ($puntaje_asignado >= ($respuesta['puntaje_pregunta'] * 0.5)) ? 1 : 0;
            
            $stmt->execute([
                ':es_correcta' => $es_correcta,
                ':comentario' => $comentario,
                ':puntaje_asignado' => $puntaje_asignado,
                ':respuesta_id' => $respuesta['id']
            ]);
            
            $puntaje_total_obtenido += $puntaje_asignado;
            
        } else {
            // Respuesta automática ya calificada
            if ($respuesta['es_correcta'] == 1) {
                $puntaje_total_obtenido += $respuesta['puntaje_pregunta'];
            }
        }
    }
    
    // Calcular puntaje como porcentaje (0-100)
    $puntaje_porcentaje = ($puntaje_total_posible > 0) 
        ? (($puntaje_total_obtenido / $puntaje_total_posible) * 100.0) 
        : 0.0;
    
    // Actualizar el intento con el puntaje final
    $stmt = $conn->prepare("
        UPDATE intentos_evaluacion 
        SET puntaje_obtenido = :puntaje_obtenido,
            fecha_revision = NOW()
        WHERE id = :intento_id
    ");
    $stmt->execute([
        ':puntaje_obtenido' => $puntaje_porcentaje,
        ':intento_id' => $intento_id
    ]);
    
    // Verificar si aprobó y actualizar progreso del módulo
    $aprobado = $puntaje_porcentaje >= $intento['puntaje_minimo_aprobacion'];
    
    if ($aprobado) {
        // Marcar progreso del módulo como completado
        $stmt = $conn->prepare("
            INSERT INTO progreso_modulos (
                usuario_id, modulo_id, completado, fecha_completado, 
                evaluacion_completada, fecha_evaluacion_completada, puntaje_evaluacion
            )
            VALUES (:usuario_id, :modulo_id, 1, NOW(), 1, NOW(), :puntaje)
            ON DUPLICATE KEY UPDATE 
                completado = 1, 
                fecha_completado = NOW(), 
                evaluacion_completada = 1, 
                fecha_evaluacion_completada = NOW(), 
                puntaje_evaluacion = :puntaje
        ");
        $stmt->execute([
            ':usuario_id' => $intento['usuario_id'],
            ':modulo_id' => $intento['modulo_id'],
            ':puntaje' => $puntaje_porcentaje
        ]);
        
        // Actualizar progreso general del curso
        $stmt = $conn->prepare("
            SELECT c.id as curso_id, COUNT(m.id) as total_modulos
            FROM cursos c
            INNER JOIN modulos m ON c.id = m.curso_id
            WHERE m.id = :modulo_id
            GROUP BY c.id
        ");
        $stmt->execute([':modulo_id' => $intento['modulo_id']]);
        $curso_info = $stmt->fetch();
        
        if ($curso_info) {
            // Contar módulos completados por el estudiante
            $stmt = $conn->prepare("
                SELECT COUNT(*) as modulos_completados
                FROM progreso_modulos pm
                INNER JOIN modulos m ON pm.modulo_id = m.id
                WHERE pm.usuario_id = :usuario_id 
                AND m.curso_id = :curso_id 
                AND pm.completado = 1
            ");
            $stmt->execute([
                ':usuario_id' => $intento['usuario_id'],
                ':curso_id' => $curso_info['curso_id']
            ]);
            $modulos_completados = $stmt->fetchColumn();
            
            // Calcular porcentaje de progreso
            $progreso_porcentaje = ($curso_info['total_modulos'] > 0) 
                ? (($modulos_completados / $curso_info['total_modulos']) * 100) 
                : 0;
            
            // Determinar estado del curso
            $estado_curso = ($progreso_porcentaje >= 100) ? 'completado' : 'activo';
            $fecha_completado = ($estado_curso === 'completado') ? 'NOW()' : 'NULL';
            
            // Actualizar inscripción
            $stmt = $conn->prepare("
                UPDATE inscripciones 
                SET progreso = :progreso, 
                    estado = :estado,
                    fecha_completado = " . ($fecha_completado === 'NULL' ? 'NULL' : $fecha_completado) . "
                WHERE usuario_id = :usuario_id AND curso_id = :curso_id
            ");
            $stmt->execute([
                ':progreso' => $progreso_porcentaje,
                ':estado' => $estado_curso,
                ':usuario_id' => $intento['usuario_id'],
                ':curso_id' => $curso_info['curso_id']
            ]);
        }
    }
    
    $conn->commit();
    
    // Redirigir con mensaje de éxito
    $mensaje = $aprobado ? 'evaluacion_aprobada' : 'evaluacion_reprobada';
    header('Location: ' . BASE_URL . '/docente/revisar_evaluaciones.php?success=' . $mensaje);
    exit;
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Error procesando calificación: " . $e->getMessage());
    header('Location: ' . BASE_URL . '/docente/calificar_intento.php?id=' . $intento_id . '&error=' . urlencode($e->getMessage()));
    exit;
}
?>