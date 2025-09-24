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
        header('Location: /imt-cursos/public/docente/temas_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=datos_invalidos');
        exit;
    }
    
    // Verificar si las nuevas columnas existen
    $stmt = $conn->prepare("SHOW COLUMNS FROM temas LIKE 'contenido'");
    $stmt->execute();
    $columnas_nuevas_existen = $stmt->fetch();
    
    // Verificar permisos
    $stmt = $conn->prepare("
        SELECT t.id FROM temas t
        INNER JOIN modulos m ON t.modulo_id = m.id
        INNER JOIN cursos c ON m.curso_id = c.id
        WHERE t.id = :tema_id AND c.creado_por = :docente_id
    ");
    $stmt->execute([':tema_id' => $tema_id, ':docente_id' => $_SESSION['user_id']]);
    $tema_actual = $stmt->fetch();
    
    if (!$tema_actual) {
        header('Location: /imt-cursos/public/docente/admin_cursos.php?error=acceso_denegado');
        exit;
    }
    
    // Manejar subida de archivo
    $nuevo_archivo = null;
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK && $columnas_nuevas_existen) {
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
                $nuevo_archivo = '/imt-cursos/uploads/temas/' . $new_filename;
            }
        }
    }
    
    try {
        if ($columnas_nuevas_existen) {
            // Actualizar con todos los campos incluyendo los nuevos
            $url_final = $nuevo_archivo ?: $recurso_url;
            
            $stmt = $conn->prepare("
                UPDATE temas 
                SET titulo = :titulo, descripcion = :descripcion, contenido = :contenido, 
                    recurso_url = :recurso_url, orden = :orden
                WHERE id = :id
            ");
            
            $stmt->execute([
                ':titulo' => $titulo,
                ':descripcion' => $descripcion,
                ':contenido' => $contenido ?: null,
                ':recurso_url' => $url_final ?: null,
                ':orden' => $orden,
                ':id' => $tema_id
            ]);
        } else {
            // Actualizar solo los campos básicos
            $stmt = $conn->prepare("
                UPDATE temas 
                SET titulo = :titulo, descripcion = :descripcion, orden = :orden
                WHERE id = :id
            ");
            
            $stmt->execute([
                ':titulo' => $titulo,
                ':descripcion' => $descripcion ?: null,
                ':orden' => $orden,
                ':id' => $tema_id
            ]);
        }
        
        header('Location: /imt-cursos/public/docente/temas_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&success=tema_actualizado');
        exit;
        
    } catch (Exception $e) {
        error_log("Error actualizando tema: " . $e->getMessage());
        header('Location: /imt-cursos/public/docente/editar_tema.php?id=' . $tema_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=error_actualizar');
        exit;
    }
} else {
    header('Location: /imt-cursos/public/docente/admin_cursos.php');
    exit;
}
?>
