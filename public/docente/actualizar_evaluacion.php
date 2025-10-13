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
$stmt = $conn->prepare("\n    SELECT e.id FROM evaluaciones_modulo e\n    INNER JOIN modulos m ON e.modulo_id = m.id\n    INNER JOIN cursos c ON m.curso_id = c.id\n    WHERE e.id = :evaluacion_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)\n");
$stmt->execute([':evaluacion_id' => $evaluacion_id, ':docente_id' => $_SESSION['user_id'], ':docente_id2' => $_SESSION['user_id']]);
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
    $stmt->execute([
        ':titulo' => $titulo,
        ':descripcion' => $descripcion,
        ':tipo' => $tipo,
        ':puntaje_maximo' => $puntaje_maximo,
        ':puntaje_minimo_aprobacion' => $puntaje_minimo_aprobacion,
        ':tiempo_limite' => $tiempo_limite,
        ':intentos_permitidos' => $intentos_permitidos,
        ':obligatorio' => $obligatorio,
        ':orden' => $orden,
        ':instrucciones' => $instrucciones,
        ':fecha_inicio' => $fecha_inicio,
        ':fecha_fin' => $fecha_fin,
        ':evaluacion_id' => $evaluacion_id
    ]);

    header('Location: ' . BASE_URL . '/docente/evaluaciones_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&success=evaluacion_actualizada');
    exit;
} catch (Exception $e) {
    error_log('Error actualizando evaluación: ' . $e->getMessage());
    header('Location: ' . BASE_URL . '/docente/editar_evaluacion.php?id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=error_actualizar');
    exit;
}