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
$stmt = $conn->prepare("\n    SELECT t.id, t.recurso_url FROM temas t\n    INNER JOIN modulos m ON t.modulo_id = m.id\n    INNER JOIN cursos c ON m.curso_id = c.id\n    WHERE t.id = :tema_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)\n");
$stmt->execute([
    ':tema_id' => $tema_id, 
    ':docente_id' => $_SESSION['user_id'],
    ':docente_id2' => $_SESSION['user_id']
]);
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
    
    // Detectar si la tabla subtemas tiene columna recurso_url
    $colCheck = $conn->prepare("SHOW COLUMNS FROM subtemas LIKE 'recurso_url'");
    $colCheck->execute();
    $subtemasHasRecurso = (bool)$colCheck->fetch();

    // Construir consulta de archivos de forma segura según columnas existentes
    $sql = "\n        SELECT t.recurso_url FROM temas t WHERE t.id = ? AND t.recurso_url LIKE '/imt-cursos/uploads/%'\n    ";
    $params = [$tema_id];

    if ($subtemasHasRecurso) {
        $sql .= "\n        UNION\n        SELECT s.recurso_url FROM subtemas s WHERE s.tema_id = ? AND s.recurso_url LIKE '/imt-cursos/uploads/%'\n        ";
        $params[] = $tema_id;
    }

    $sql .= "\n        UNION\n        SELECT l.recurso_url FROM lecciones l \n        INNER JOIN subtemas s ON l.subtema_id = s.id\n        WHERE s.tema_id = ? AND l.recurso_url LIKE '/imt-cursos/uploads/%'\n    ";
    $params[] = $tema_id;

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $archivos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Eliminar dependencias explícitamente por si la FK no tiene ON DELETE CASCADE
    // 1) Eliminar lecciones de los subtemas del tema
    $stmt = $conn->prepare("\n        DELETE l FROM lecciones l\n        INNER JOIN subtemas s ON l.subtema_id = s.id\n        WHERE s.tema_id = :tema_id\n    ");
    $stmt->execute([':tema_id' => $tema_id]);

    // 2) Eliminar subtemas del tema
    $stmt = $conn->prepare("DELETE FROM subtemas WHERE tema_id = :tema_id");
    $stmt->execute([':tema_id' => $tema_id]);

    // 3) Eliminar tema
    $stmt = $conn->prepare("DELETE FROM temas WHERE id = :id");
    $stmt->execute([':id' => $tema_id]);
    
    $conn->commit();
    
    // Eliminar archivos físicos usando UploadHelper (fuera de la transacción)
    foreach ($archivos as $archivo) {
        if ($archivo) {
            try {
                $upload_helper->deleteFile($archivo);
            } catch (Exception $fileEx) {
                error_log("WARNING: Error al eliminar archivo $archivo: " . $fileEx->getMessage());
            }
        }
    }
    
    header('Location: ' . BASE_URL . '/docente/temas_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&success=tema_eliminado');
    exit;
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error eliminando tema ID {$tema_id} (modulo {$modulo_id}, curso {$curso_id}): " . $e->getMessage());
    error_log("Archivo: " . $e->getFile() . " Línea: " . $e->getLine());
    header('Location: ' . BASE_URL . '/docente/temas_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=error_eliminar&details=' . urlencode($e->getMessage()));
    exit;
}
?>