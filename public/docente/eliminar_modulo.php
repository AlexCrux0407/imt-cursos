<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$modulo_id = (int)($_GET['id'] ?? 0);
$curso_id = (int)($_GET['curso_id'] ?? 0);

if ($modulo_id === 0 || $curso_id === 0) {
    header('Location: /imt-cursos/public/docente/admin_cursos.php?error=datos_invalidos');
    exit;
}

// Verificar permisos
$stmt = $conn->prepare("
    SELECT m.id, m.recurso_url FROM modulos m
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE m.id = :modulo_id AND c.creado_por = :docente_id
");
$stmt->execute([':modulo_id' => $modulo_id, ':docente_id' => $_SESSION['user_id']]);
$modulo = $stmt->fetch();

if (!$modulo) {
    header('Location: /imt-cursos/public/docente/admin_cursos.php?error=acceso_denegado');
    exit;
}

try {
    $conn->beginTransaction();
    
    // Obtener archivos para eliminar
    $stmt = $conn->prepare("
        SELECT recurso_url FROM lecciones l
        INNER JOIN modulos m ON l.modulo_id = m.id
        WHERE m.id = :modulo_id AND l.recurso_url LIKE '/imt-cursos/uploads/%'
        UNION
        SELECT recurso_url FROM temas t
        WHERE t.modulo_id = :modulo_id AND t.recurso_url LIKE '/imt-cursos/uploads/%'
    ");
    $stmt->execute([':modulo_id' => $modulo_id]);
    $archivos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if ($modulo['recurso_url'] && strpos($modulo['recurso_url'], '/uploads/') === 0) {
        $archivos[] = $modulo['recurso_url'];
    }
    
    // Eliminar módulo (CASCADE eliminará temas, subtemas y lecciones)
    $stmt = $conn->prepare("DELETE FROM modulos WHERE id = :id");
    $stmt->execute([':id' => $modulo_id]);
    
    $conn->commit();
    
    // Eliminar archivos físicos
    foreach ($archivos as $archivo) {
        if ($archivo) {
            $archivo_path = __DIR__ . '/../..' . $archivo;
            if (file_exists($archivo_path)) {
                @unlink($archivo_path);
            }
        }
    }
    
    header('Location: /imt-cursos/public/docente/modulos_curso.php?id=' . $curso_id . '&success=modulo_eliminado');
    exit;
    
} catch (Exception $e) {
    $conn->rollBack();
    header('Location: /imt-cursos/public/docente/modulos_curso.php?id=' . $curso_id . '&error=error_eliminar');
    exit;
}
?>
