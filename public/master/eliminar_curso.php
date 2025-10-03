<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('master');
require_once __DIR__ . '/../../config/database.php';

$curso_id = (int)($_GET['id'] ?? 0);

if ($curso_id === 0) {
    header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=curso_no_especificado');
    exit;
}

try {
    $conn->beginTransaction();
    
    // Verificar que el curso existe y obtener información
    $stmt = $conn->prepare("
        SELECT c.titulo, c.estado,
               COUNT(DISTINCT i.id) as total_inscritos,
               COUNT(DISTINCT m.id) as total_modulos
        FROM cursos c
        LEFT JOIN inscripciones i ON c.id = i.curso_id
        LEFT JOIN modulos m ON c.id = m.curso_id
        WHERE c.id = :curso_id
        GROUP BY c.id
    ");
    $stmt->execute([':curso_id' => $curso_id]);
    $curso = $stmt->fetch();
    
    if (!$curso) {
        $conn->rollback();
        header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=curso_no_encontrado');
        exit;
    }
    
    // Verificar si hay estudiantes inscritos
    if ($curso['total_inscritos'] > 0) {
        $conn->rollback();
        header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=curso_con_inscritos&inscritos=' . $curso['total_inscritos']);
        exit;
    }
    
    // Eliminar contenido relacionado en orden correcto para evitar problemas de claves foráneas
    
    // 1. Eliminar lecciones de todos los subtemas del curso
    $stmt = $conn->prepare("
        DELETE l FROM lecciones l
        INNER JOIN subtemas st ON l.subtema_id = st.id
        INNER JOIN temas t ON st.tema_id = t.id
        INNER JOIN modulos m ON t.modulo_id = m.id
        WHERE m.curso_id = :curso_id
    ");
    $stmt->execute([':curso_id' => $curso_id]);
    $lecciones_eliminadas = $stmt->rowCount();
    
    // 2. Eliminar subtemas de todos los temas del curso
    $stmt = $conn->prepare("
        DELETE st FROM subtemas st
        INNER JOIN temas t ON st.tema_id = t.id
        INNER JOIN modulos m ON t.modulo_id = m.id
        WHERE m.curso_id = :curso_id
    ");
    $stmt->execute([':curso_id' => $curso_id]);
    $subtemas_eliminados = $stmt->rowCount();
    
    // 3. Eliminar temas de todos los módulos del curso
    $stmt = $conn->prepare("
        DELETE t FROM temas t
        INNER JOIN modulos m ON t.modulo_id = m.id
        WHERE m.curso_id = :curso_id
    ");
    $stmt->execute([':curso_id' => $curso_id]);
    $temas_eliminados = $stmt->rowCount();
    
    // 4. Eliminar módulos del curso
    $stmt = $conn->prepare("DELETE FROM modulos WHERE curso_id = :curso_id");
    $stmt->execute([':curso_id' => $curso_id]);
    $modulos_eliminados = $stmt->rowCount();
    
    // 5. Eliminar evaluaciones del curso (si existen)
    $stmt = $conn->prepare("DELETE FROM evaluaciones WHERE curso_id = :curso_id");
    $stmt->execute([':curso_id' => $curso_id]);
    $evaluaciones_eliminadas = $stmt->rowCount();
    
    // 6. Finalmente, eliminar el curso
    $stmt = $conn->prepare("DELETE FROM cursos WHERE id = :curso_id");
    $stmt->execute([':curso_id' => $curso_id]);
    
    if ($stmt->rowCount() > 0) {
        // Intentar eliminar directorio de archivos del curso si existe
        $curso_dir = __DIR__ . '/../uploads/cursos/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $curso['titulo']);
        if (is_dir($curso_dir)) {
            try {
                removeDirectory($curso_dir);
                $archivos_eliminados = true;
            } catch (Exception $e) {
                error_log("No se pudo eliminar el directorio del curso: " . $e->getMessage());
                $archivos_eliminados = false;
            }
        }
        
        // Log detallado de la eliminación para auditoría
        $log_message = "Master {$_SESSION['user_id']} ({$_SESSION['nombre']}) eliminó el curso ID: {$curso_id} - Título: {$curso['titulo']}";
        $log_message .= " | Contenido eliminado: {$modulos_eliminados} módulos, {$temas_eliminados} temas, {$subtemas_eliminados} subtemas, {$lecciones_eliminadas} lecciones";
        if ($evaluaciones_eliminadas > 0) {
            $log_message .= ", {$evaluaciones_eliminadas} evaluaciones";
        }
        error_log($log_message);
        
        $conn->commit();
        header('Location: ' . BASE_URL . '/master/admin_cursos.php?success=curso_eliminado&titulo=' . urlencode($curso['titulo']));
    } else {
        $conn->rollback();
        header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=error_eliminar');
    }
    exit;
    
} catch (PDOException $e) {
    $conn->rollback();
    error_log("PDOException al eliminar curso ID {$curso_id}: " . $e->getMessage());
    header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=database_error&details=' . urlencode($e->getMessage()));
    exit;
} catch (Exception $e) {
    $conn->rollback();
    error_log("Exception al eliminar curso ID {$curso_id}: " . $e->getMessage());
    header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=general_error&details=' . urlencode($e->getMessage()));
    exit;
}

/**
 * Función auxiliar para eliminar directorios recursivamente
 */
function removeDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            removeDirectory($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}
?>