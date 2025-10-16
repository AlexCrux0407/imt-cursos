<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leccion_id = (int)($_POST['leccion_id'] ?? 0);
    $modulo_id = (int)($_POST['modulo_id'] ?? 0);
    $curso_id = (int)($_POST['curso_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $tipo = $_POST['tipo'] ?? 'documento';
    $recurso_url = trim($_POST['recurso_url'] ?? '');
    $orden = (int)($_POST['orden'] ?? 1);
    
    if (empty($titulo) || $leccion_id === 0) {
        header('Location: ' . BASE_URL . '/docente/lecciones_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=datos_invalidos');
        exit;
    }
    
    // Verificar permisos
    $stmt = $conn->prepare("
        SELECT l.id, l.recurso_url as archivo_actual, l.orden as orden_actual FROM lecciones l
        INNER JOIN modulos m ON l.modulo_id = m.id
        INNER JOIN cursos c ON m.curso_id = c.id
        WHERE l.id = :leccion_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
    ");
    $stmt->execute([':leccion_id' => $leccion_id, ':docente_id' => $_SESSION['user_id'], ':docente_id2' => $_SESSION['user_id']]);
    $leccion_actual = $stmt->fetch();
    
    if (!$leccion_actual) {
        header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=acceso_denegado');
        exit;
    }
    
    // Verificar si el orden ya existe en otra lección del mismo módulo
    if ($orden != $leccion_actual['orden_actual']) {
        $stmt = $conn->prepare("
            SELECT id FROM lecciones 
            WHERE modulo_id = :modulo_id AND orden = :orden AND id != :leccion_id
        ");
        $stmt->execute([
            ':modulo_id' => $modulo_id,
            ':orden' => $orden,
            ':leccion_id' => $leccion_id
        ]);
        
        if ($stmt->fetch()) {
            header('Location: ' . BASE_URL . '/docente/editar_leccion.php?id=' . $leccion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=orden_duplicado');
            exit;
        }
    }
    
    // Manejar subida de archivo usando nueva estructura
    $nuevo_archivo = null;
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        require_once __DIR__ . '/../../app/upload_helper.php';
        
        try {
            $upload_helper = new UploadHelper($conn);
            $nuevo_archivo = $upload_helper->handleFileUpload($_FILES['archivo'], 'leccion', $leccion_id);
            
            if ($nuevo_archivo) {
                // Eliminar archivo anterior si existe y no es una URL externa
                if ($leccion_actual['archivo_actual'] && strpos($leccion_actual['archivo_actual'], '/uploads/') === 0) {
                    $upload_helper->deleteFile($leccion_actual['archivo_actual']);
                }
            }
        } catch (Exception $e) {
            error_log("Error actualizando archivo de lección: " . $e->getMessage());
            // Continuar sin archivo si hay error
        }
    }
    
    try {
        // Si se subió un nuevo archivo, usar ese; si no, mantener el URL actual o el nuevo URL
        $url_final = $nuevo_archivo ?: $recurso_url;
        
        $stmt = $conn->prepare("
            UPDATE lecciones 
            SET titulo = :titulo, contenido = :contenido, tipo = :tipo, 
                recurso_url = :recurso_url, orden = :orden
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':titulo' => $titulo,
            ':contenido' => $contenido,
            ':tipo' => $tipo,
            ':recurso_url' => $url_final ?: null,
            ':orden' => $orden,
            ':id' => $leccion_id
        ]);
        
        header('Location: ' . BASE_URL . '/docente/lecciones_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&success=leccion_actualizada');
        exit;
        
    } catch (Exception $e) {
        header('Location: ' . BASE_URL . '/docente/editar_leccion.php?id=' . $leccion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=error_actualizar');
        exit;
    }
} else {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php');
    exit;
}
?>
