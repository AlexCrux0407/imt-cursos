<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subtema_id = (int)($_POST['subtema_id'] ?? 0);
    $tema_id = (int)($_POST['tema_id'] ?? 0);
    $modulo_id = (int)($_POST['modulo_id'] ?? 0);
    $curso_id = (int)($_POST['curso_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $orden = (int)($_POST['orden'] ?? 1);
    
    if (empty($titulo) || $subtema_id === 0) {
        header('Location:'  . BASE_URL . '/docente/subtemas_tema.php?id=' . $tema_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=datos_invalidos');
        exit;
    }
    
    // Verificar permisos
    $stmt = $conn->prepare("
        SELECT s.id FROM subtemas s
        INNER JOIN temas t ON s.tema_id = t.id
        INNER JOIN modulos m ON t.modulo_id = m.id
        INNER JOIN cursos c ON m.curso_id = c.id
        WHERE s.id = :subtema_id AND c.creado_por = :docente_id
    ");
    $stmt->execute([':subtema_id' => $subtema_id, ':docente_id' => $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        header('Location:'  . BASE_URL . '/docente/admin_cursos.php?error=acceso_denegado');
        exit;
    }
    
    try {
        $stmt = $conn->prepare("
            UPDATE subtemas 
            SET titulo = :titulo, descripcion = :descripcion, orden = :orden
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':titulo' => $titulo,
            ':descripcion' => $descripcion,
            ':orden' => $orden,
            ':id' => $subtema_id
        ]);
        
        header('Location:'  . BASE_URL . '/docente/subtemas_tema.php?id=' . $tema_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&success=subtema_actualizado');
        exit;
        
    } catch (Exception $e) {
        header('Location:'  . BASE_URL . '/docente/editar_subtema.php?id=' . $subtema_id . '&tema_id=' . $tema_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=error_actualizar');
        exit;
    }
} else {
    header('Location:'  . BASE_URL . '/docente/admin_cursos.php');
    exit;
}
?>
