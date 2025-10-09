<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tema_id = (int)($_POST['tema_id'] ?? 0);
    $modulo_id = (int)($_POST['modulo_id'] ?? 0);
    $curso_id = (int)($_POST['curso_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $recurso_url = trim($_POST['recurso_url'] ?? '');
    $orden = (int)($_POST['orden'] ?? 1);
    
    if (empty($titulo) || $tema_id === 0) {
        header('Location: ' . BASE_URL . '/docente/subtemas_tema.php?id=' . $tema_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=datos_invalidos');
        exit;
    }
    
    // Verificar que el tema pertenece al docente
    $stmt = $conn->prepare("
        SELECT t.id FROM temas t
        INNER JOIN modulos m ON t.modulo_id = m.id
        INNER JOIN cursos c ON m.curso_id = c.id
        WHERE t.id = :tema_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
    ");
    $stmt->execute([':tema_id' => $tema_id, ':docente_id' => $_SESSION['user_id'], ':docente_id2' => $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=acceso_denegado');
        exit;
    }
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO subtemas (tema_id, titulo, descripcion, orden) 
            VALUES (:tema_id, :titulo, :descripcion, :orden)
        ");
        
        $stmt->execute([
            ':tema_id' => $tema_id,
            ':titulo' => $titulo,
            ':descripcion' => $descripcion,
            ':orden' => $orden
        ]);
        
        $subtema_id = $conn->lastInsertId();
        
        // Handle file upload if exists using new structure
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            require_once __DIR__ . '/../../app/upload_helper.php';
            
            try {
                $upload_helper = new UploadHelper($conn);
                $archivo_url = $upload_helper->handleFileUpload($_FILES['archivo'], 'subtema', $subtema_id);
                
                if ($archivo_url) {
                    // Check if subtemas table has recurso_url column
                    $stmt = $conn->prepare("SHOW COLUMNS FROM subtemas LIKE 'recurso_url'");
                    $stmt->execute();
                    $column_exists = $stmt->fetch();
                    
                    if ($column_exists) {
                        $update_stmt = $conn->prepare("UPDATE subtemas SET recurso_url = :archivo_url WHERE id = :id");
                        $update_stmt->execute([':archivo_url' => $archivo_url, ':id' => $subtema_id]);
                    }
                }
            } catch (Exception $e) {
                error_log("Error subiendo archivo de subtema: " . $e->getMessage());
                // Continuar sin archivo si hay error
            }
        }
        
        header('Location: <?= BASE_URL ?>/docente/subtemas_tema.php?id=' . $tema_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&success=subtema_creado');
        exit;
        
    } catch (Exception $e) {
        header('Location: <?= BASE_URL ?>/docente/subtemas_tema.php?id=' . $tema_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=error_crear');
        exit;
    }
} else {
    header('Location: <?= BASE_URL ?>/docente/admin_cursos.php');
    exit;
}
?>
