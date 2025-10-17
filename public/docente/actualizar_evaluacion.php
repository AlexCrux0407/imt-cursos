<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php');
    exit;
}

$evaluacion_id = (int)($_POST['evaluacion_id'] ?? 0);
$modulo_id = (int)($_POST['modulo_id'] ?? 0);
$curso_id = (int)($_POST['curso_id'] ?? 0);
$titulo = trim($_POST['titulo'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$tipo = $_POST['tipo'] ?? 'examen';
$puntaje_maximo = (float)($_POST['puntaje_maximo'] ?? 100);
$puntaje_minimo_aprobacion = (float)($_POST['puntaje_minimo_aprobacion'] ?? 70);
$tiempo_limite = isset($_POST['tiempo_limite']) && $_POST['tiempo_limite'] !== '' ? (int)$_POST['tiempo_limite'] : null;
$intentos_permitidos = (int)($_POST['intentos_permitidos'] ?? 1);
$instrucciones = trim($_POST['instrucciones'] ?? '');
$obligatorio = isset($_POST['obligatorio']) ? 1 : 0;
$orden = (int)($_POST['orden'] ?? 1);
$fecha_inicio = !empty($_POST['fecha_inicio']) ? date('Y-m-d H:i:s', strtotime($_POST['fecha_inicio'])) : null;
$fecha_fin = !empty($_POST['fecha_fin']) ? date('Y-m-d H:i:s', strtotime($_POST['fecha_fin'])) : null;

if ($evaluacion_id === 0 || empty($titulo) || $modulo_id === 0) {
    header('Location: ' . BASE_URL . '/docente/evaluaciones_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=datos_invalidos');
    exit;
}

// Verificar acceso del docente a la evaluación
$stmt = $conn->prepare("
    SELECT e.id FROM evaluaciones_modulo e
    INNER JOIN modulos m ON e.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE e.id = :evaluacion_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
");
$stmt->execute([
    ':evaluacion_id' => $evaluacion_id, 
    ':docente_id' => $_SESSION['user_id'],
    ':docente_id2' => $_SESSION['user_id']
]);
if (!$stmt->fetch()) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=acceso_denegado');
    exit;
}

// Validación puntaje mínimo
if ($puntaje_minimo_aprobacion > $puntaje_maximo) {
    header('Location: ' . BASE_URL . '/docente/editar_evaluacion.php?id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=puntaje_invalido');
    exit;
}

try {
    $stmt = $conn->prepare("\n        UPDATE evaluaciones_modulo SET\n            titulo = :titulo,\n            descripcion = :descripcion,\n            tipo = :tipo,\n            puntaje_maximo = :puntaje_maximo,\n            puntaje_minimo_aprobacion = :puntaje_minimo_aprobacion,\n            tiempo_limite = :tiempo_limite,\n            intentos_permitidos = :intentos_permitidos,\n            obligatorio = :obligatorio,\n            orden = :orden,\n            instrucciones = :instrucciones,\n            fecha_inicio = :fecha_inicio,\n            fecha_fin = :fecha_fin,\n            fecha_actualizacion = CURRENT_TIMESTAMP\n        WHERE id = :evaluacion_id\n    ");
    try {
        $stmt->bindValue(':titulo', $titulo, PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
        $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':puntaje_maximo', $puntaje_maximo);
        $stmt->bindValue(':puntaje_minimo_aprobacion', $puntaje_minimo_aprobacion);
        if ($tiempo_limite === null) {
            $stmt->bindValue(':tiempo_limite', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':tiempo_limite', $tiempo_limite, PDO::PARAM_INT);
        }
        $stmt->bindValue(':intentos_permitidos', $intentos_permitidos, PDO::PARAM_INT);
        $stmt->bindValue(':obligatorio', $obligatorio, PDO::PARAM_INT);
        $stmt->bindValue(':orden', $orden, PDO::PARAM_INT);
        $stmt->bindValue(':instrucciones', $instrucciones, PDO::PARAM_STR);
        if ($fecha_inicio === null) {
            $stmt->bindValue(':fecha_inicio', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':fecha_inicio', $fecha_inicio, PDO::PARAM_STR);
        }
        if ($fecha_fin === null) {
            $stmt->bindValue(':fecha_fin', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':fecha_fin', $fecha_fin, PDO::PARAM_STR);
        }
        $stmt->bindValue(':evaluacion_id', $evaluacion_id, PDO::PARAM_INT);

        $stmt->execute();
    } catch (Exception $e) {
        error_log('Actualizar evaluación – fallo en UPDATE evaluaciones_modulo: ' . $e->getMessage());
        throw $e;
    }

    // Recalcular progreso del módulo basado en intentos aprobados actuales
    $stmt = $conn->prepare("\n        UPDATE progreso_modulos pm\n        LEFT JOIN (\n            SELECT ie.usuario_id, e.modulo_id,\n                   MAX(CASE WHEN ie.aprobado = 1 AND ie.estado = 'completado' THEN ie.fecha_fin END) AS fecha_completado,\n                   MAX(CASE WHEN ie.aprobado = 1 AND ie.estado = 'completado' THEN ie.puntaje_obtenido END) AS puntaje_obtenido,\n                   MAX(CASE WHEN ie.aprobado = 1 AND ie.estado = 'completado' THEN 1 ELSE 0 END) AS aprobado_flag\n            FROM intentos_evaluacion ie\n            JOIN evaluaciones_modulo e ON e.id = ie.evaluacion_id\n            WHERE e.id = :evaluacion_id\n            GROUP BY ie.usuario_id, e.modulo_id\n        ) t ON t.usuario_id = pm.usuario_id AND t.modulo_id = pm.modulo_id\n        SET pm.evaluacion_completada = CASE WHEN IFNULL(t.aprobado_flag, 0) = 1 THEN 1 ELSE 0 END,\n            pm.fecha_evaluacion_completada = CASE WHEN IFNULL(t.aprobado_flag, 0) = 1 THEN t.fecha_completado ELSE NULL END,\n            pm.puntaje_evaluacion = CASE WHEN IFNULL(t.aprobado_flag, 0) = 1 THEN t.puntaje_obtenido ELSE NULL END\n        WHERE pm.modulo_id = :modulo_id\n    ");
    try {
        $stmt->execute([':evaluacion_id' => $evaluacion_id, ':modulo_id' => $modulo_id]);
    } catch (Exception $e) {
        error_log('Actualizar evaluación – fallo al recalcular progreso_modulos: ' . $e->getMessage());
        // No interrumpir actualización de la evaluación si el recalculo falla
    }

    // Recalcular progreso del curso en inscripciones para todos los estudiantes del curso
    $stmt = $conn->prepare("\n        UPDATE inscripciones i\n        JOIN (\n            SELECT i.usuario_id AS uid, i.curso_id AS cid,\n                   COUNT(m.id) AS total_modulos,\n                   SUM(CASE WHEN pm.evaluacion_completada = 1 THEN 1 ELSE 0 END) AS modulos_completados\n            FROM inscripciones i\n            JOIN cursos c ON i.curso_id = c.id\n            JOIN modulos m ON m.curso_id = c.id\n            LEFT JOIN progreso_modulos pm ON pm.modulo_id = m.id AND pm.usuario_id = i.usuario_id\n            WHERE i.curso_id = :curso_id\n            GROUP BY i.usuario_id, i.curso_id\n        ) x ON x.uid = i.usuario_id AND x.cid = i.curso_id\n        SET i.progreso = CASE WHEN x.total_modulos > 0 THEN ROUND((x.modulos_completados / x.total_modulos) * 100, 2) ELSE 0 END,\n            i.estado = CASE WHEN x.total_modulos > 0 AND x.modulos_completados = x.total_modulos THEN 'completado' ELSE 'en_progreso' END,\n            i.fecha_completado = CASE WHEN x.total_modulos > 0 AND x.modulos_completados = x.total_modulos THEN i.fecha_completado ELSE NULL END\n        WHERE i.curso_id = :curso_id\n    ");
    try {
        $stmt->execute([':curso_id' => $curso_id]);
    } catch (Exception $e) {
        error_log('Actualizar evaluación – fallo al recalcular inscripciones: ' . $e->getMessage());
        // No interrumpir actualización de la evaluación si el recalculo falla
    }

    header('Location: ' . BASE_URL . '/docente/evaluaciones_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&success=evaluacion_actualizada');
    exit;
} catch (Exception $e) {
    error_log('Error actualizando evaluación: ' . $e->getMessage());
    header('Location: ' . BASE_URL . '/docente/editar_evaluacion.php?id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=error_actualizar');
    exit;
}