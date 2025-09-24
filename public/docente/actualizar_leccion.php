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
        header('Location: /imt-cursos/public/docente/lecciones_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=datos_invalidos');
        exit;
    }
    
    // Verificar permisos
    $stmt = $conn->prepare("
        SELECT l.id, l.recurso_url as archivo_actual, l.orden as orden_actual FROM lecciones l
        INNER JOIN modulos m ON l.modulo_id = m.id
        INNER JOIN cursos c ON m.curso_id = c.id
        WHERE l.id = :leccion_id AND c.creado_por = :docente_id
    ");
    $stmt->execute([':leccion_id' => $leccion_id, ':docente_id' => $_SESSION['user_id']]);
    $leccion_actual = $stmt->fetch();
    
    if (!$leccion_actual) {
        header('Location: /imt-cursos/public/docente/admin_cursos.php?error=acceso_denegado');
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
            header('Location: /imt-cursos/public/docente/editar_leccion.php?id=' . $leccion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=orden_duplicado');
            exit;
        }
    }
    
    // Manejar subida de archivo
    $nuevo_archivo = null;
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../uploads/lecciones/';
        
        // Crear directorio si no existe
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_info = pathinfo($_FILES['archivo']['name']);
        $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'mp4', 'avi', 'mov', 'jpg', 'jpeg', 'png'];
        
        if (in_array(strtolower($file_info['extension']), $allowed_extensions)) {
            $new_filename = 'leccion_' . $leccion_id . '_' . time() . '.' . $file_info['extension'];
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['archivo']['tmp_name'], $upload_path)) {
                $nuevo_archivo = '/imt-cursos/uploads/lecciones/' . $new_filename;
                
                // Eliminar archivo anterior si existe y no es una URL externa
                if ($leccion_actual['archivo_actual'] && strpos($leccion_actual['archivo_actual'], '/uploads/') === 0) {
                    $archivo_anterior = __DIR__ . '/../..' . $leccion_actual['archivo_actual'];
                    if (file_exists($archivo_anterior)) {
                        unlink($archivo_anterior);
                    }
                }
            }
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
        
        header('Location: /imt-cursos/public/docente/lecciones_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&success=leccion_actualizada');
        exit;
        
    } catch (Exception $e) {
        header('Location: /imt-cursos/public/docente/editar_leccion.php?id=' . $leccion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=error_actualizar');
        exit;
    }
} else {
    header('Location: /imt-cursos/public/docente/admin_cursos.php');
    exit;
}
?>
