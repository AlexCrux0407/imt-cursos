<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$pregunta_id = (int)($_GET['id'] ?? 0);
$evaluacion_id = (int)($_GET['evaluacion_id'] ?? 0);
$modulo_id = (int)($_GET['modulo_id'] ?? 0);
$curso_id = (int)($_GET['curso_id'] ?? 0);

if ($pregunta_id === 0 || $evaluacion_id === 0) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=parametros_invalidos');
    exit;
}

// Verificar que la pregunta pertenece a una evaluación del docente
$stmt = $conn->prepare("
    SELECT p.orden FROM preguntas_evaluacion p
    INNER JOIN evaluaciones_modulo e ON p.evaluacion_id = e.id
    INNER JOIN modulos m ON e.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE p.id = :pregunta_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
");
$stmt->execute([':pregunta_id' => $pregunta_id, ':docente_id' => $_SESSION['user_id']]);
$pregunta = $stmt->fetch();

if (!$pregunta) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=acceso_denegado');
    exit;
}

try {
    $conn->beginTransaction();
    
    // Eliminar respuestas asociadas a esta pregunta
    $stmt = $conn->prepare("DELETE FROM respuestas WHERE pregunta_id = :pregunta_id");
    $stmt->execute([':pregunta_id' => $pregunta_id, ':docente_id2' => $_SESSION['user_id']]);
    
    // Eliminar la pregunta
    $stmt = $conn->prepare("DELETE FROM preguntas_evaluacion WHERE id = :pregunta_id");
    $stmt->execute([':pregunta_id' => $pregunta_id, ':docente_id2' => $_SESSION['user_id']]);
    
    // Reordenar las preguntas restantes
    $stmt = $conn->prepare("
        UPDATE preguntas_evaluacion 
        SET orden = orden - 1 
        WHERE evaluacion_id = :evaluacion_id AND orden > :orden_eliminado
    ");
    $stmt->execute([
        ':evaluacion_id' => $evaluacion_id,
        ':orden_eliminado' => $pregunta['orden']
    ]);
    
    // Actualizar el puntaje máximo de la evaluación
    $stmt = $conn->prepare("
        UPDATE evaluaciones_modulo 
        SET puntaje_maximo = (
            SELECT COALESCE(SUM(puntaje), 0) FROM preguntas_evaluacion 
            WHERE evaluacion_id = :evaluacion_id
        )
        WHERE id = :evaluacion_id
    ");
    $stmt->execute([':evaluacion_id' => $evaluacion_id, ':docente_id2' => $_SESSION['user_id']]);
    
    $conn->commit();
    
    header('Location: ' . BASE_URL . '/docente/preguntas_evaluacion.php?id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&success=pregunta_eliminada');
    exit;
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error eliminando pregunta: " . $e->getMessage());
    header('Location: ' . BASE_URL . '/docente/preguntas_evaluacion.php?id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=error_eliminar');
    exit;
}
?>