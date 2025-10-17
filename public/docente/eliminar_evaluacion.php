<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$evaluacion_id = (int)($_GET['id'] ?? 0);
$modulo_id = (int)($_GET['modulo_id'] ?? 0);
$curso_id = (int)($_GET['curso_id'] ?? 0);

if ($evaluacion_id === 0 || $modulo_id === 0 || $curso_id === 0) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=parametros_invalidos');
    exit;
}

// Verificar que la evaluación pertenece a un módulo del docente
$stmt = $conn->prepare("
    SELECT e.id, e.titulo FROM evaluaciones_modulo e
    INNER JOIN modulos m ON e.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE e.id = :evaluacion_id AND m.id = :modulo_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
");
$stmt->execute([
    ':evaluacion_id' => $evaluacion_id,
    ':modulo_id' => $modulo_id,
    ':docente_id' => $_SESSION['user_id'],
    ':docente_id2' => $_SESSION['user_id']
]);
$evaluacion = $stmt->fetch();

if (!$evaluacion) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=acceso_denegado');
    exit;
}

try {
    $conn->beginTransaction();
    
    // Eliminar respuestas de estudiantes asociadas a las preguntas de esta evaluación
    $stmt = $conn->prepare("
        DELETE r FROM respuestas r
        INNER JOIN preguntas_evaluacion p ON r.pregunta_id = p.id
        WHERE p.evaluacion_id = :evaluacion_id
    ");
    $stmt->execute([':evaluacion_id' => $evaluacion_id]);
    
    // Eliminar intentos de evaluación
    $stmt = $conn->prepare("DELETE FROM intentos_evaluacion WHERE evaluacion_id = :evaluacion_id");
    $stmt->execute([':evaluacion_id' => $evaluacion_id]);
    
    // Eliminar todas las preguntas de la evaluación
    $stmt = $conn->prepare("DELETE FROM preguntas_evaluacion WHERE evaluacion_id = :evaluacion_id");
    $stmt->execute([':evaluacion_id' => $evaluacion_id]);
    
    // Eliminar la evaluación
    $stmt = $conn->prepare("DELETE FROM evaluaciones_modulo WHERE id = :evaluacion_id");
    $stmt->execute([':evaluacion_id' => $evaluacion_id]);

    // Recalcular progreso del módulo para todos los estudiantes del módulo afectado
    // Basado en intentos aprobados de cualquier evaluación restante del módulo
    $stmt = $conn->prepare("\n        UPDATE progreso_modulos pm\n        LEFT JOIN (\n            SELECT ie.usuario_id, e.modulo_id,\n                   MAX(CASE WHEN ie.aprobado = 1 AND ie.estado = 'completado' THEN 1 ELSE 0 END) AS aprobado_flag,\n                   MAX(CASE WHEN ie.aprobado = 1 AND ie.estado = 'completado' THEN ie.fecha_fin END) AS fecha_completado,\n                   MAX(CASE WHEN ie.aprobado = 1 AND ie.estado = 'completado' THEN ie.puntaje_obtenido END) AS puntaje_obtenido\n            FROM intentos_evaluacion ie\n            JOIN evaluaciones_modulo e ON e.id = ie.evaluacion_id\n            WHERE e.modulo_id = :modulo_id\n            GROUP BY ie.usuario_id, e.modulo_id\n        ) t ON t.usuario_id = pm.usuario_id AND t.modulo_id = pm.modulo_id\n        SET pm.evaluacion_completada = CASE WHEN IFNULL(t.aprobado_flag, 0) = 1 THEN 1 ELSE 0 END,\n            pm.fecha_evaluacion_completada = CASE WHEN IFNULL(t.aprobado_flag, 0) = 1 THEN t.fecha_completado ELSE NULL END,\n            pm.puntaje_evaluacion = CASE WHEN IFNULL(t.aprobado_flag, 0) = 1 THEN t.puntaje_obtenido ELSE NULL END\n        WHERE pm.modulo_id = :modulo_id\n    ");
    $stmt->execute([':modulo_id' => $modulo_id]);

    // Recalcular progreso del curso en inscripciones para todos los estudiantes del curso
    // Basado en evaluaciones completadas por módulo
    $stmt = $conn->prepare("\n        UPDATE inscripciones i\n        JOIN (\n            SELECT i.usuario_id AS uid, i.curso_id AS cid,\n                   COUNT(m.id) AS total_modulos,\n                   SUM(CASE WHEN pm.evaluacion_completada = 1 THEN 1 ELSE 0 END) AS modulos_completados\n            FROM inscripciones i\n            JOIN cursos c ON i.curso_id = c.id\n            JOIN modulos m ON m.curso_id = c.id\n            LEFT JOIN progreso_modulos pm ON pm.modulo_id = m.id AND pm.usuario_id = i.usuario_id\n            WHERE i.curso_id = :curso_id\n            GROUP BY i.usuario_id, i.curso_id\n        ) x ON x.uid = i.usuario_id AND x.cid = i.curso_id\n        SET i.progreso = CASE WHEN x.total_modulos > 0 THEN ROUND((x.modulos_completados / x.total_modulos) * 100, 2) ELSE 0 END,\n            i.estado = CASE WHEN x.total_modulos > 0 AND x.modulos_completados = x.total_modulos THEN 'completado' ELSE 'en_progreso' END,\n            i.fecha_completado = CASE WHEN x.total_modulos > 0 AND x.modulos_completados = x.total_modulos THEN i.fecha_completado ELSE NULL END\n        WHERE i.curso_id = :curso_id\n    ");
    $stmt->execute([':curso_id' => $curso_id]);

    $conn->commit();
    
    header('Location: ' . BASE_URL . '/docente/evaluaciones_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&success=evaluacion_eliminada');
    exit;
    
} catch (Exception $e) {
    $conn->rollback();
    
    // Log detallado del error
    $error_details = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'evaluacion_id' => $evaluacion_id,
        'modulo_id' => $modulo_id,
        'curso_id' => $curso_id,
        'error_message' => $e->getMessage(),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine(),
        'stack_trace' => $e->getTraceAsString()
    ];
    
    error_log("Error eliminando evaluación: " . json_encode($error_details));
    
    header('Location: ' . BASE_URL . '/docente/evaluaciones_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=error_eliminar&details=' . urlencode($e->getMessage()));
    exit;
}
?>