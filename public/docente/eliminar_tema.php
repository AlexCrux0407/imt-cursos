<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$tema_id = (int)($_GET['id'] ?? 0);
$modulo_id = (int)($_GET['modulo_id'] ?? 0);
$curso_id = (int)($_GET['curso_id'] ?? 0);

if ($tema_id === 0 || $modulo_id === 0) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=datos_invalidos');
    exit;
}

// Verificar permisos
$stmt = $conn->prepare("
    SELECT t.id, t.recurso_url FROM temas t
    INNER JOIN modulos m ON t.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE t.id = :tema_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id)
");
$stmt->execute([':tema_id' => $tema_id, ':docente_id' => $_SESSION['user_id']]);
$tema = $stmt->fetch();

if (!$tema) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=acceso_denegado');
    exit;
}

try {
    $conn->beginTransaction();
    
    // Obtener archivos para eliminar (tema y sus subtemas/lecciones) usando UploadHelper
    require_once __DIR__ . '/../../app/upload_helper.php';
    $upload_helper = new UploadHelper($conn);
    
    $stmt = $conn->prepare("
        SELECT t.recurso_url FROM temas t WHERE t.id = :tema_id AND t.recurso_url LIKE '/imt-cursos/uploads/%'
        UNION
        SELECT s.recurso_url FROM subtemas s WHERE s.tema_id = :tema_id AND s.recurso_url LIKE '/imt-cursos/uploads/%'
        UNION  
        SELECT l.recurso_url FROM lecciones l 
        INNER JOIN subtemas s ON l.subtema_id = s.id
        WHERE s.tema_id = :tema_id AND l.recurso_url LIKE '/imt-cursos/uploads/%'
    ");
    $stmt->execute([':tema_id' => $tema_id]);
    $archivos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Eliminar tema (CASCADE eliminará subtemas y lecciones)
    $stmt = $conn->prepare("DELETE FROM temas WHERE id = :id");
    $stmt->execute([':id' => $tema_id]);
    
    $conn->commit();
    
    // Eliminar archivos físicos usando UploadHelper
    foreach ($archivos as $archivo) {
        if ($archivo) {
            $upload_helper->deleteFile($archivo);
        }
    }
    
    header('Location: ' . BASE_URL . '/docente/temas_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&success=tema_eliminado');
    exit;
    
} catch (Exception $e) {
    $conn->rollBack();
    header('Location: ' . BASE_URL . '/docente/temas_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=error_eliminar');
    exit;
}
?>
