<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$curso_id = (int)($_GET['id'] ?? 0);

if ($curso_id === 0) {
    header('Location: /imt-cursos/public/docente/admin_cursos.php?error=curso_invalido');
    exit;
}

// Verificar que el curso pertenece al docente
$stmt = $conn->prepare("SELECT titulo FROM cursos WHERE id = :id AND creado_por = :docente_id");
$stmt->execute([':id' => $curso_id, ':docente_id' => $_SESSION['user_id']]);
$curso = $stmt->fetch();

if (!$curso) {
    header('Location: /imt-cursos/public/docente/admin_cursos.php?error=acceso_denegado');
    exit;
}

try {
    // Iniciar transacción
    $conn->beginTransaction();
    
    // 1. Obtener archivos de lecciones para eliminar físicamente
    $stmt = $conn->prepare("
        SELECT l.recurso_url 
        FROM lecciones l
        INNER JOIN modulos m ON l.modulo_id = m.id
        WHERE m.curso_id = :curso_id AND l.recurso_url LIKE '/imt-cursos/uploads/%'
    ");
    $stmt->execute([':curso_id' => $curso_id]);
    $archivos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 2. Eliminar registros de la base de datos (el ON DELETE CASCADE se encarga de la cascada)
    $stmt = $conn->prepare("DELETE FROM cursos WHERE id = :id AND creado_por = :docente_id");
    $result = $stmt->execute([':id' => $curso_id, ':docente_id' => $_SESSION['user_id']]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception("No se pudo eliminar el curso o ya no existe");
    }
    
    // Confirmar transacción
    $conn->commit();
    
    // 3. Eliminar archivos físicos después de confirmar la transacción
    foreach ($archivos as $archivo) {
        if ($archivo && strpos($archivo, '/uploads/') !== false) {
            $archivo_path = __DIR__ . '/../..' . $archivo;
            if (file_exists($archivo_path)) {
                @unlink($archivo_path); // @ para evitar warnings si el archivo no se puede eliminar
            }
        }
    }
    
    header('Location: /imt-cursos/public/docente/admin_cursos.php?success=curso_eliminado');
    exit;
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Log del error para debugging (opcional)
    error_log("Error eliminando curso ID $curso_id: " . $e->getMessage());
    
    header('Location: /imt-cursos/public/docente/admin_cursos.php?error=error_eliminar&details=' . urlencode($e->getMessage()));
    exit;
}
?>
