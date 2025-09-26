<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $modulo_id = (int)($_POST['modulo_id'] ?? 0);
    $curso_id = (int)($_POST['curso_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $recurso_url = trim($_POST['recurso_url'] ?? '');
    $orden = (int)($_POST['orden'] ?? 1);
    
    if (empty($titulo) || $modulo_id === 0) {
        header('Location: ' . BASE_URL . '/docente/temas_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=datos_invalidos');
        exit;
    }
    
    // Verificar que el módulo pertenece al docente
    $stmt = $conn->prepare("
        SELECT m.id FROM modulos m
        INNER JOIN cursos c ON m.curso_id = c.id
        WHERE m.id = :modulo_id AND c.creado_por = :docente_id
    ");
    $stmt->execute([':modulo_id' => $modulo_id, ':docente_id' => $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=acceso_denegado');
        exit;
    }
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO temas (modulo_id, titulo, descripcion, contenido, recurso_url, orden) 
            VALUES (:modulo_id, :titulo, :descripcion, :contenido, :recurso_url, :orden)
        ");
        
        $stmt->execute([
            ':modulo_id' => $modulo_id,
            ':titulo' => $titulo,
            ':descripcion' => $descripcion,
            ':contenido' => $contenido ?: null,
            ':recurso_url' => $recurso_url ?: null,
            ':orden' => $orden
        ]);
        
        $tema_id = $conn->lastInsertId();
        
        // Handle file upload if exists
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../../uploads/temas/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_info = pathinfo($_FILES['archivo']['name']);
            $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'mp4', 'avi', 'mov', 'jpg', 'jpeg', 'png'];
            
            if (in_array(strtolower($file_info['extension']), $allowed_extensions)) {
                $new_filename = 'tema_' . $tema_id . '_' . time() . '.' . $file_info['extension'];
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['archivo']['tmp_name'], $upload_path)) {
                    $archivo_url = '/imt-cursos/uploads/temas/' . $new_filename;
                    $update_stmt = $conn->prepare("UPDATE temas SET recurso_url = :archivo_url WHERE id = :id");
                    $update_stmt->execute([':archivo_url' => $archivo_url, ':id' => $tema_id]);
                }
            }
        }
        
        header('Location: ' . BASE_URL . '/docente/temas_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&success=tema_creado');
        exit;
        
    } catch (Exception $e) {
        header('Location: ' . BASE_URL . '/docente/temas_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=error_crear');
        exit;
    }
} else {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php');
    exit;
}
?>
