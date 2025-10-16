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