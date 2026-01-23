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
$stmt = $conn->prepare("\n    SELECT e.id, e.titulo FROM evaluaciones_modulo e\n    INNER JOIN modulos m ON e.modulo_id = m.id\n    INNER JOIN cursos c ON m.curso_id = c.id\n    WHERE e.id = :evaluacion_id AND m.id = :modulo_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)\n");
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

// Asegurar que columnas datetime involucradas permitan NULL para evitar fechas cero en MySQL estricto
try {
    // Hacer nullable inscripciones.fecha_completado
    $info = $conn->prepare("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inscripciones' AND COLUMN_NAME = 'fecha_completado'");
    $info->execute();
    $col = $info->fetch();
    if ($col && strtoupper($col['IS_NULLABLE']) !== 'YES') {
        $conn->exec("ALTER TABLE inscripciones MODIFY COLUMN fecha_completado DATETIME NULL DEFAULT NULL");
    }

    // Hacer nullable progreso_modulos.fecha_completado
    $info2 = $conn->prepare("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'progreso_modulos' AND COLUMN_NAME = 'fecha_completado'");
    $info2->execute();
    $col2 = $info2->fetch();
    if ($col2 && strtoupper($col2['IS_NULLABLE']) !== 'YES') {
        $conn->exec("ALTER TABLE progreso_modulos MODIFY COLUMN fecha_completado DATETIME NULL DEFAULT NULL");
    }

    // Hacer nullable progreso_modulos.fecha_evaluacion_completada
    $info3 = $conn->prepare("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'progreso_modulos' AND COLUMN_NAME = 'fecha_evaluacion_completada'");
    $info3->execute();
    $col3 = $info3->fetch();
    if ($col3 && strtoupper($col3['IS_NULLABLE']) !== 'YES') {
        $conn->exec("ALTER TABLE progreso_modulos MODIFY COLUMN fecha_evaluacion_completada DATETIME NULL DEFAULT NULL");
    }

    // Hacer nullable intentos_evaluacion.fecha_fin para evitar cero-fecha
    $info4 = $conn->prepare("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'intentos_evaluacion' AND COLUMN_NAME = 'fecha_fin'");
    $info4->execute();
    $col4 = $info4->fetch();
    if ($col4 && strtoupper($col4['IS_NULLABLE']) !== 'YES') {
        $conn->exec("ALTER TABLE intentos_evaluacion MODIFY COLUMN fecha_fin DATETIME NULL DEFAULT NULL");
    }

    // Hacer nullable evaluaciones_modulo.fecha_inicio y fecha_fin para evitar cero-fecha
    $info5 = $conn->prepare("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evaluaciones_modulo' AND COLUMN_NAME = 'fecha_inicio'");
    $info5->execute();
    $col5 = $info5->fetch();
    if ($col5 && strtoupper($col5['IS_NULLABLE']) !== 'YES') {
        $conn->exec("ALTER TABLE evaluaciones_modulo MODIFY COLUMN fecha_inicio DATETIME NULL DEFAULT NULL");
    }
    $info6 = $conn->prepare("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evaluaciones_modulo' AND COLUMN_NAME = 'fecha_fin'");
    $info6->execute();
    $col6 = $info6->fetch();
    if ($col6 && strtoupper($col6['IS_NULLABLE']) !== 'YES') {
        $conn->exec("ALTER TABLE evaluaciones_modulo MODIFY COLUMN fecha_fin DATETIME NULL DEFAULT NULL");
    }
} catch (Exception $e) {
    // No interrumpir flujo por fallos de migración; los updates más abajo usan NULL de forma segura
}

// Saneamiento defensivo: convertir fechas cero a NULL para evitar errores en modo estricto
try {
    $stmt = $conn->prepare("UPDATE evaluaciones_modulo SET fecha_inicio = NULL WHERE id = :evaluacion_id AND fecha_inicio = '0000-00-00 00:00:00'");
    $stmt->execute([':evaluacion_id' => $evaluacion_id]);

    $stmt = $conn->prepare("UPDATE evaluaciones_modulo SET fecha_fin = NULL WHERE id = :evaluacion_id AND fecha_fin = '0000-00-00 00:00:00'");
    $stmt->execute([':evaluacion_id' => $evaluacion_id]);

    $stmt = $conn->prepare("UPDATE intentos_evaluacion SET fecha_fin = NULL WHERE evaluacion_id = :evaluacion_id AND fecha_fin = '0000-00-00 00:00:00'");
    $stmt->execute([':evaluacion_id' => $evaluacion_id]);
} catch (Exception $e) {
    // Continuar incluso si el saneamiento falla
}

try {
    $conn->beginTransaction();
    
    // Eliminar respuestas de estudiantes asociadas a las preguntas de esta evaluación
    $stmt = $conn->prepare("\n        DELETE r FROM respuestas r\n        INNER JOIN preguntas_evaluacion p ON r.pregunta_id = p.id\n        WHERE p.evaluacion_id = :evaluacion_id\n    ");
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
    $stmt = $conn->prepare("\n        UPDATE progreso_modulos pm\n        LEFT JOIN (\n            SELECT ie.usuario_id, e.modulo_id,\n                   MAX(CASE WHEN ie.aprobado = 1 AND ie.estado = 'completado' THEN 1 ELSE 0 END) AS aprobado_flag,\n                   MAX(CASE WHEN ie.aprobado = 1 AND ie.estado = 'completado' THEN NULLIF(ie.fecha_fin, '0000-00-00 00:00:00') END) AS fecha_completado,\n                   MAX(CASE WHEN ie.aprobado = 1 AND ie.estado = 'completado' THEN ie.puntaje_obtenido END) AS puntaje_obtenido\n            FROM intentos_evaluacion ie\n            JOIN evaluaciones_modulo e ON e.id = ie.evaluacion_id\n            WHERE e.modulo_id = :modulo_id\n            GROUP BY ie.usuario_id, e.modulo_id\n        ) t ON t.usuario_id = pm.usuario_id AND t.modulo_id = pm.modulo_id\n        SET pm.evaluacion_completada = CASE WHEN IFNULL(t.aprobado_flag, 0) = 1 THEN 1 ELSE 0 END,\n            pm.fecha_evaluacion_completada = CASE WHEN IFNULL(t.aprobado_flag, 0) = 1 THEN t.fecha_completado ELSE NULL END,\n            pm.puntaje_evaluacion = CASE WHEN IFNULL(t.aprobado_flag, 0) = 1 THEN t.puntaje_obtenido ELSE NULL END\n        WHERE pm.modulo_id = :modulo_id2\n    ");
    $stmt->execute([':modulo_id' => $modulo_id, ':modulo_id2' => $modulo_id]);

    // Recalcular progreso del curso en inscripciones para todos los estudiantes del curso
    // Basado en evaluaciones completadas por módulo
    $stmt = $conn->prepare("\n        UPDATE inscripciones i\n        JOIN (\n            SELECT i.usuario_id AS uid, i.curso_id AS cid,\n                   COUNT(m.id) AS total_modulos,\n                   SUM(CASE WHEN pm.evaluacion_completada = 1 THEN 1 ELSE 0 END) AS modulos_completados\n            FROM inscripciones i\n            JOIN cursos c ON i.curso_id = c.id\n            JOIN modulos m ON m.curso_id = c.id\n            LEFT JOIN progreso_modulos pm ON pm.modulo_id = m.id AND pm.usuario_id = i.usuario_id\n            WHERE i.curso_id = :curso_id\n            GROUP BY i.usuario_id, i.curso_id\n        ) x ON x.uid = i.usuario_id AND x.cid = i.curso_id\n        SET i.progreso = CASE WHEN x.total_modulos > 0 THEN ROUND((x.modulos_completados / x.total_modulos) * 100, 2) ELSE 0 END,\n            i.estado = CASE WHEN x.total_modulos > 0 AND x.modulos_completados = x.total_modulos THEN 'completado' ELSE 'activo' END,\n            i.fecha_completado = CASE WHEN x.total_modulos > 0 AND x.modulos_completados = x.total_modulos THEN NOW() ELSE NULL END\n        WHERE i.curso_id = :curso_id2\n    ");
    $stmt->execute([':curso_id' => $curso_id, ':curso_id2' => $curso_id]);

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