<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$subtema_id = (int)($_GET['id'] ?? 0);
$tema_id = (int)($_GET['tema_id'] ?? 0);
$modulo_id = (int)($_GET['modulo_id'] ?? 0);
$curso_id = (int)($_GET['curso_id'] ?? 0);

if ($subtema_id === 0 || $tema_id === 0) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=datos_invalidos');
    exit;
}

// Verificar permisos
$stmt = $conn->prepare("
    SELECT s.id FROM subtemas s
    INNER JOIN temas t ON s.tema_id = t.id
    INNER JOIN modulos m ON t.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE s.id = :subtema_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id)
");
$stmt->execute([':subtema_id' => $subtema_id, ':docente_id' => $_SESSION['user_id']]);

if (!$stmt->fetch()) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=acceso_denegado');
    exit;
}

try {
    $conn->beginTransaction();
    
    // Eliminar subtema (CASCADE eliminará lecciones)
    $stmt = $conn->prepare("DELETE FROM subtemas WHERE id = :id");
    $stmt->execute([':id' => $subtema_id]);
    
    $conn->commit();
    
    header('Location: ' . BASE_URL . '/docente/subtemas_tema.php?id=' . $tema_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&success=subtema_eliminado');
    exit;
    
} catch (Exception $e) {
    $conn->rollBack();
    header('Location: ' . BASE_URL . '/docente/subtemas_tema.php?id=' . $tema_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=error_eliminar');
    exit;
}
?>
