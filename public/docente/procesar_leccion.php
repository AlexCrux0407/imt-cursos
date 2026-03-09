<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $modulo_id = (int)($_POST['modulo_id'] ?? 0);
    $tema_id = (int)($_POST['tema_id'] ?? 0);
    $subtema_id = (int)($_POST['subtema_id'] ?? 0);
    $curso_id = (int)($_POST['curso_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $tipo = $_POST['tipo'] ?? 'documento';
    $recurso_url = trim($_POST['recurso_url'] ?? '');
    $orden = (int)($_POST['orden'] ?? 1);
    
    if (empty($titulo) || $modulo_id === 0) {
        $dest = $subtema_id > 0
            ? BASE_URL . '/docente/lecciones_subtema.php?id=' . $subtema_id . '&tema_id=' . $tema_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id
            : BASE_URL . '/docente/lecciones_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id;
        header('Location: ' . $dest . '&error=datos_invalidos');
        exit;
    }
    
    // Verificar que el módulo pertenece a un curso del docente
    $stmt = $conn->prepare("
        SELECT m.id FROM modulos m
        INNER JOIN cursos c ON m.curso_id = c.id
        WHERE m.id = :modulo_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
    ");
    $stmt->execute([
        ':modulo_id' => $modulo_id, 
        ':docente_id' => $_SESSION['user_id'],
        ':docente_id2' => $_SESSION['user_id']
    ]);
    
    if (!$stmt->fetch()) {
        header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=acceso_denegado');
        exit;
    }
    
    try {
        // Insertar lección primero para obtener el ID
        $stmt = $conn->prepare("
            INSERT INTO lecciones (modulo_id, tema_id, subtema_id, titulo, contenido, tipo, recurso_url, orden) 
            VALUES (:modulo_id, :tema_id, :subtema_id, :titulo, :contenido, :tipo, :recurso_url, :orden)
        ");
        
        $stmt->execute([
            ':modulo_id' => $modulo_id,
            ':tema_id' => $tema_id ?: null,
            ':subtema_id' => $subtema_id ?: null,
            ':titulo' => $titulo,
            ':contenido' => $contenido,
            ':tipo' => $tipo,
            ':recurso_url' => $recurso_url ?: null,
            ':orden' => $orden
        ]);
        
        $leccion_id = $conn->lastInsertId();
        
        // Manejar subida de archivo si existe usando la nueva estructura
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            require_once __DIR__ . '/../../app/upload_helper.php';
            
            try {
                $upload_helper = new UploadHelper($conn);
                $archivo_url = $upload_helper->handleFileUpload($_FILES['archivo'], 'leccion', $leccion_id);
                
                if ($archivo_url) {
                    // Actualizar la lección con la URL del archivo
                    $update_stmt = $conn->prepare("UPDATE lecciones SET recurso_url = :archivo_url WHERE id = :id");
                    $update_stmt->execute([':archivo_url' => $archivo_url, ':id' => $leccion_id]);
                }
            } catch (Exception $e) {
                error_log("Error subiendo archivo de lección: " . $e->getMessage());
                // Continuar sin archivo si hay error
            }
        }
        
        $dest = $subtema_id > 0
            ? BASE_URL . '/docente/lecciones_subtema.php?id=' . $subtema_id . '&tema_id=' . $tema_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id
            : BASE_URL . '/docente/lecciones_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id;
        header('Location: ' . $dest . '&success=leccion_creada');
        exit;
        
    } catch (Exception $e) {
        $dest = $subtema_id > 0
            ? BASE_URL . '/docente/lecciones_subtema.php?id=' . $subtema_id . '&tema_id=' . $tema_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id
            : BASE_URL . '/docente/lecciones_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id;
        header('Location: ' . $dest . '&error=error_crear');
        exit;
    }
} else {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php');
    exit;
}
?>
