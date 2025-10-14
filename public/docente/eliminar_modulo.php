<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$modulo_id = (int)($_GET['id'] ?? 0);
$curso_id = (int)($_GET['curso_id'] ?? 0);

if ($modulo_id === 0 || $curso_id === 0) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=datos_invalidos');
    exit;
}

// Verificar permisos
$stmt = $conn->prepare("
    SELECT m.id, m.recurso_url FROM modulos m
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE m.id = :modulo_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id)
");
$stmt->execute([':modulo_id' => $modulo_id, ':docente_id' => $_SESSION['user_id']]);
$modulo = $stmt->fetch();

// DEBUG: Log verificación de permisos
error_log("DEBUG: Verificando permisos para módulo ID: " . $modulo_id . ", Usuario ID: " . $_SESSION['user_id']);
error_log("DEBUG: Módulo encontrado: " . ($modulo ? 'SÍ' : 'NO'));

if (!$modulo) {
    error_log("DEBUG: Acceso denegado - redirigiendo");
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=acceso_denegado');
    exit;
}

try {
    $conn->beginTransaction();
    
    // DEBUG: Log inicio de transacción
    error_log("DEBUG: Iniciando transacción para eliminar módulo");
    
    // Obtener archivos para eliminar usando UploadHelper
    require_once __DIR__ . '/../../app/upload_helper.php';
    $upload_helper = new UploadHelper($conn);
    
    $stmt = $conn->prepare("
        SELECT l.recurso_url FROM lecciones l
        INNER JOIN modulos m ON l.modulo_id = m.id
        WHERE m.id = ? AND l.recurso_url LIKE '/imt-cursos/uploads/%'
        UNION
        SELECT t.recurso_url FROM temas t
        WHERE t.modulo_id = ? AND t.recurso_url LIKE '/imt-cursos/uploads/%'
    ");
    $stmt->execute([$modulo_id, $modulo_id]);
    $archivos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // DEBUG: Log información del módulo
    error_log("DEBUG: Módulo recurso_url: " . ($modulo['recurso_url'] ?? 'NULL'));
    
    if (!empty($modulo['recurso_url']) && strpos($modulo['recurso_url'], '/uploads/') === 0) {
        $archivos[] = $modulo['recurso_url'];
        error_log("DEBUG: Agregado archivo del módulo a eliminar: " . $modulo['recurso_url']);
    }
    
    // DEBUG: Log antes de eliminar
    error_log("DEBUG: Intentando eliminar módulo ID: " . $modulo_id);
    
    // Eliminar módulo (CASCADE eliminará temas, subtemas y lecciones)
    $stmt = $conn->prepare("DELETE FROM modulos WHERE id = :id");
    $result = $stmt->execute([':id' => $modulo_id]);
    $rows_affected = $stmt->rowCount();
    
    // DEBUG: Log resultado
    error_log("DEBUG: Resultado eliminación - Success: " . ($result ? 'true' : 'false') . ", Rows affected: " . $rows_affected);
    
    $conn->commit();
    
    // DEBUG: Log commit exitoso
    error_log("DEBUG: Transacción completada exitosamente");
    
    // Eliminar archivos físicos usando UploadHelper
    error_log("DEBUG: Iniciando eliminación de archivos. Total archivos: " . count($archivos));
    foreach ($archivos as $archivo) {
        if ($archivo) {
            try {
                error_log("DEBUG: Eliminando archivo: " . $archivo);
                $upload_helper->deleteFile($archivo);
                error_log("DEBUG: Archivo eliminado exitosamente: " . $archivo);
            } catch (Exception $fileEx) {
                error_log("WARNING: Error al eliminar archivo " . $archivo . ": " . $fileEx->getMessage());
                // No interrumpir el proceso por errores de archivos
            }
        }
    }
    
    // DEBUG: Log antes de redirección
    error_log("DEBUG: Redirigiendo a modulos_curso.php con success");
    
    header('Location: ' . BASE_URL . '/docente/modulos_curso.php?id=' . $curso_id . '&success=modulo_eliminado');
    exit;
    
} catch (Exception $e) {
    $conn->rollBack();
    error_log("ERROR: Error al eliminar módulo: " . $e->getMessage());
    error_log("ERROR: Stack trace: " . $e->getTraceAsString());
    error_log("ERROR: Archivo: " . $e->getFile() . " Línea: " . $e->getLine());
    header('Location: ' . BASE_URL . '/docente/modulos_curso.php?id=' . $curso_id . '&error=error_eliminar&details=' . urlencode($e->getMessage()));
    exit;
}
?>
