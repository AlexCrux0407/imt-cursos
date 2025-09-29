<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

// Procesar solicitudes POST para marcar progreso
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => 'Error desconocido'];
    
    $tipo = $_POST['tipo'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $usuario_id = (int)($_SESSION['user_id'] ?? 0);
    
    if ($id <= 0 || $usuario_id <= 0) {
        $response['message'] = 'Parámetros inválidos';
        echo json_encode($response);
        exit;
    }
    
    try {
        $conn->beginTransaction();
        
        switch ($tipo) {
            case 'leccion':
                // Solo marcar lección como completada (sin progreso automático)
                $stmt = $conn->prepare("
                    INSERT INTO progreso_lecciones (usuario_id, leccion_id, completado, fecha_completado)
                    VALUES (:usuario_id, :leccion_id, 1, NOW())
                    ON DUPLICATE KEY UPDATE completado = 1, fecha_completado = NOW()
                ");
                $stmt->execute([':usuario_id' => $usuario_id, ':leccion_id' => $id]);
                break;
                
            case 'tema':
                // Solo marcar tema como completado (sin progreso automático)
                $stmt = $conn->prepare("
                    INSERT INTO progreso_temas (usuario_id, tema_id, completado, fecha_completado)
                    VALUES (:usuario_id, :tema_id, 1, NOW())
                    ON DUPLICATE KEY UPDATE completado = 1, fecha_completado = NOW()
                ");
                $stmt->execute([':usuario_id' => $usuario_id, ':tema_id' => $id]);
                break;
                
            case 'evaluacion':
                // Procesar evaluación completada y actualizar progreso del módulo
                $evaluacion_id = $id;
                
                // Verificar que la evaluación existe y obtener información del módulo
                $stmt = $conn->prepare("
                    SELECT e.modulo_id, e.puntaje_minimo_aprobacion, m.curso_id
                    FROM evaluaciones_modulo e
                    INNER JOIN modulos m ON e.modulo_id = m.id
                    WHERE e.id = :evaluacion_id AND e.activo = 1
                ");
                $stmt->execute([':evaluacion_id' => $evaluacion_id]);
                $evaluacion_info = $stmt->fetch();
                
                if (!$evaluacion_info) {
                    $response['message'] = 'Evaluación no encontrada o inactiva';
                    echo json_encode($response);
                    $conn->rollBack();
                    exit;
                }
                
                // Verificar si el estudiante ya completó esta evaluación con éxito
                $stmt = $conn->prepare("
                    SELECT puntaje_obtenido
                    FROM progreso_modulos
                    WHERE usuario_id = :usuario_id AND modulo_id = :modulo_id 
                    AND evaluacion_completada = 1
                ");
                $stmt->execute([
                    ':usuario_id' => $usuario_id, 
                    ':modulo_id' => $evaluacion_info['modulo_id']
                ]);
                $progreso_existente = $stmt->fetch();
                
                if ($progreso_existente) {
                    $response['message'] = 'Ya has completado la evaluación de este módulo';
                    echo json_encode($response);
                    $conn->rollBack();
                    exit;
                }
                
                // Obtener el último intento de evaluación del estudiante
                $stmt = $conn->prepare("
                    SELECT puntaje_obtenido, estado
                    FROM intentos_evaluacion
                    WHERE usuario_id = :usuario_id AND evaluacion_id = :evaluacion_id
                    ORDER BY fecha_intento DESC
                    LIMIT 1
                ");
                $stmt->execute([
                    ':usuario_id' => $usuario_id,
                    ':evaluacion_id' => $evaluacion_id
                ]);
                $ultimo_intento = $stmt->fetch();
                
                if (!$ultimo_intento || $ultimo_intento['estado'] !== 'completado') {
                    $response['message'] = 'No se encontró un intento completado para esta evaluación';
                    echo json_encode($response);
                    $conn->rollBack();
                    exit;
                }
                
                $puntaje_obtenido = $ultimo_intento['puntaje_obtenido'];
                $aprobado = $puntaje_obtenido >= $evaluacion_info['puntaje_minimo_aprobacion'];
                
                if (!$aprobado) {
                    $response['message'] = 'No has alcanzado el puntaje mínimo para aprobar esta evaluación';
                    echo json_encode($response);
                    $conn->rollBack();
                    exit;
                }
                
                // Marcar módulo como completado con evaluación aprobada
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
                    ':modulo_id' => $evaluacion_info['modulo_id'],
                    ':puntaje' => $puntaje_obtenido
                ]);
                
                // Verificar si todos los módulos del curso están completados (con evaluaciones aprobadas)
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
                    ':curso_id' => $evaluacion_info['curso_id']
                ]);
                $curso_info = $stmt->fetch();
                
                if ($curso_info && $curso_info['total_modulos'] == $curso_info['modulos_completados']) {
                    // Todos los módulos del curso están completados, marcar curso como completado
                    $stmt = $conn->prepare("
                        UPDATE inscripciones
                        SET progreso = 100, fecha_completado = NOW(), estado = 'completado'
                        WHERE usuario_id = :usuario_id AND curso_id = :curso_id
                    ");
                    $stmt->execute([
                        ':usuario_id' => $usuario_id, 
                        ':curso_id' => $evaluacion_info['curso_id']
                    ]);
                } else {
                    // Actualizar progreso parcial del curso
                    $progreso_porcentaje = $curso_info ? ($curso_info['modulos_completados'] / $curso_info['total_modulos']) * 100 : 0;
                    $stmt = $conn->prepare("
                        UPDATE inscripciones
                        SET progreso = :progreso
                        WHERE usuario_id = :usuario_id AND curso_id = :curso_id
                    ");
                    $stmt->execute([
                        ':progreso' => $progreso_porcentaje,
                        ':usuario_id' => $usuario_id, 
                        ':curso_id' => $evaluacion_info['curso_id']
                    ]);
                }
                break;
                
            default:
                $response['message'] = 'Tipo de contenido no válido';
                echo json_encode($response);
                $conn->rollBack();
                exit;
        }
        
        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Progreso actualizado correctamente';
    } catch (PDOException $e) {
        $conn->rollBack();
        $response['message'] = 'Error en la base de datos: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}
?>

// Función para marcar como completado un módulo, tema o lección
function marcarComoCompletado(tipo, id) {
    fetch('marcar_progreso.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `tipo=${tipo}&id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recargar la página para mostrar los cambios
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al procesar la solicitud');
    });
}
