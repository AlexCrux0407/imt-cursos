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
        header('Location:'  . BASE_URL . '/docente/temas_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=datos_invalidos');
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
        WHERE t.id = :tema_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
    ");
    $stmt->execute([
        ':tema_id' => $tema_id, 
        ':docente_id' => $_SESSION['user_id'],
        ':docente_id2' => $_SESSION['user_id']
    ]);
    $tema_actual = $stmt->fetch();
    
    if (!$tema_actual) {
        header('Location:'  . BASE_URL . '/docente/admin_cursos.php?error=acceso_denegado');
        exit;
    }
    
    // Manejar subida de archivo usando nueva estructura
    $nuevo_archivo = null;
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        require_once __DIR__ . '/../../app/upload_helper.php';
        
        try {
            $upload_helper = new UploadHelper($conn);
            $nuevo_archivo = $upload_helper->handleFileUpload($_FILES['archivo'], 'tema', $tema_id);
            
            if ($nuevo_archivo) {
                // Eliminar archivo anterior si existe y no es una URL externa
                if ($tema_actual['archivo_actual'] && strpos($tema_actual['archivo_actual'], '/uploads/') === 0) {
                    $upload_helper->deleteFile($tema_actual['archivo_actual']);
                }
            }
        } catch (Exception $e) {
            error_log("Error actualizando archivo de tema: " . $e->getMessage());
            // Continuar sin archivo si hay error
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
        
        header('Location: '  . BASE_URL . '/docente/temas_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&success=tema_actualizado');
        exit;
        
    } catch (Exception $e) {
        error_log("Error actualizando tema: " . $e->getMessage());
        header('Location: '  . BASE_URL . '/docente/editar_tema.php?id=' . $tema_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=error_actualizar');
        exit;
    }
} else {
    header('Location:'  . BASE_URL . '/docente/admin_cursos.php');
    exit;
}
?>
