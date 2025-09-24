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
        header('Location: /imt-cursos/public/docente/subtemas_tema.php?id=' . $tema_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=datos_invalidos');
        exit;
    }
    
    // Verificar que el tema pertenece al docente
    $stmt = $conn->prepare("
        SELECT t.id FROM temas t
        INNER JOIN modulos m ON t.modulo_id = m.id
        INNER JOIN cursos c ON m.curso_id = c.id
        WHERE t.id = :tema_id AND c.creado_por = :docente_id
    ");
    $stmt->execute([':tema_id' => $tema_id, ':docente_id' => $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        header('Location: /imt-cursos/public/docente/admin_cursos.php?error=acceso_denegado');
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
        
        // Handle file upload if exists
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../uploads/subtemas/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_info = pathinfo($_FILES['archivo']['name']);
            $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'mp4', 'avi', 'mov', 'jpg', 'jpeg', 'png'];
            
            if (in_array(strtolower($file_info['extension']), $allowed_extensions)) {
                $new_filename = 'subtema_' . $subtema_id . '_' . time() . '.' . $file_info['extension'];
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['archivo']['tmp_name'], $upload_path)) {
                    $archivo_url = '/imt-cursos/uploads/subtemas/' . $new_filename;
                    
                    // Check if subtemas table has recurso_url column
                    $stmt = $conn->prepare("SHOW COLUMNS FROM subtemas LIKE 'recurso_url'");
                    $stmt->execute();
                    if ($stmt->fetch()) {
                        $update_stmt = $conn->prepare("UPDATE subtemas SET recurso_url = :archivo_url WHERE id = :id");
                        $update_stmt->execute([':archivo_url' => $archivo_url, ':id' => $subtema_id]);
                    }
                }
            }
        }
        
        header('Location: /imt-cursos/public/docente/subtemas_tema.php?id=' . $tema_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&success=subtema_creado');
        exit;
        
    } catch (Exception $e) {
        header('Location: /imt-cursos/public/docente/subtemas_tema.php?id=' . $tema_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=error_crear');
        exit;
    }
} else {
    header('Location: /imt-cursos/public/docente/admin_cursos.php');
    exit;
}
?>
