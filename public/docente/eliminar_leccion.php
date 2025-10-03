<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$leccion_id = (int)($_GET['id'] ?? 0);
$modulo_id = (int)($_GET['modulo_id'] ?? 0);
$curso_id = (int)($_GET['curso_id'] ?? 0);

if ($leccion_id === 0 || $modulo_id === 0) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=datos_invalidos');
    exit;
}

// Verificar que la lección pertenece a un módulo de un curso del docente
$stmt = $conn->prepare("
    SELECT l.id FROM lecciones l
    INNER JOIN modulos m ON l.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE l.id = :leccion_id AND l.modulo_id = :modulo_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
");
$stmt->execute([
    ':leccion_id' => $leccion_id,
    ':modulo_id' => $modulo_id,
    ':docente_id' => $_SESSION['user_id']
]);

if (!$stmt->fetch()) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=acceso_denegado');
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM lecciones WHERE id = :id");
    $stmt->execute([':id' => $leccion_id, ':docente_id2' => $_SESSION['user_id']]);
    
    header('Location: ' . BASE_URL . '/docente/lecciones_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&success=leccion_eliminada');
    exit;
    
} catch (Exception $e) {
    header('Location: ' . BASE_URL . '/docente/lecciones_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=error_eliminar');
    exit;
}
?>
