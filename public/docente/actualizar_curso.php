<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $curso_id = (int)($_POST['curso_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $objetivo_general = trim($_POST['objetivo_general'] ?? '');
    $objetivos_especificos = trim($_POST['objetivos_especificos'] ?? '');
    $duracion = trim($_POST['duracion'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $dirigido_a = trim($_POST['dirigido_a'] ?? '');
    $estado = $_POST['estado'] ?? 'borrador';
    
    // Debug temporal
    error_log("Actualización - Objetivo general recibido: '{$objetivo_general}'");
    error_log("Actualización - Objetivos específicos recibidos: '{$objetivos_especificos}'");
    error_log("Actualización - Duración recibida: '{$duracion}'");
    
    if (empty($titulo) || $curso_id === 0) {
        header('Location:'  . BASE_URL .'/docente/admin_cursos.php?error=datos_invalidos');
        exit;
    }
    
    // Verificar que el curso pertenece al docente
    $stmt = $conn->prepare("SELECT id FROM cursos WHERE id = :id AND (creado_por = :docente_id OR asignado_a = :docente_id2)");
    $stmt->execute([
        ':id' => $curso_id, 
        ':docente_id' => $_SESSION['user_id'],
        ':docente_id2' => $_SESSION['user_id']
    ]);
    
    if (!$stmt->fetch()) {
        header('Location:'  . BASE_URL . '/docente/admin_cursos.php?error=acceso_denegado');
        exit;
    }
    
    try {
        // Verificar si las nuevas columnas existen antes de intentar actualizarlas
        $stmt = $conn->prepare("SHOW COLUMNS FROM cursos LIKE 'objetivo_general'");
        $stmt->execute();
        $columnas_nuevas_existen = $stmt->fetch();
        
        if ($columnas_nuevas_existen) {
            // Actualizar con todos los campos incluyendo los nuevos
            $stmt = $conn->prepare("
                UPDATE cursos 
                SET titulo = :titulo, 
                    descripcion = :descripcion, 
                    objetivo_general = :objetivo_general,
                    objetivos_especificos = :objetivos_especificos, 
                    duracion = :duracion, 
                    categoria = :categoria, 
                    dirigido_a = :dirigido_a, 
                    estado = :estado, 
                    updated_at = NOW()
                WHERE id = :id AND creado_por = :creado_por
            ");
            
            $result = $stmt->execute([
                ':titulo' => $titulo,
                ':descripcion' => $descripcion,
                ':objetivo_general' => $objetivo_general ?: null,
                ':objetivos_especificos' => $objetivos_especificos ?: null,
                ':duracion' => $duracion ?: null,
                ':categoria' => $categoria ?: null,
                ':dirigido_a' => $dirigido_a ?: null,
                ':estado' => $estado,
                ':id' => $curso_id,
                ':creado_por' => $_SESSION['user_id']
            ]);
        } else {
            // Actualizar solo los campos básicos si las nuevas columnas no existen
            $stmt = $conn->prepare("
                UPDATE cursos 
                SET titulo = :titulo, 
                    descripcion = :descripcion, 
                    categoria = :categoria, 
                    dirigido_a = :dirigido_a, 
                    estado = :estado, 
                    updated_at = NOW()
                WHERE id = :id AND creado_por = :creado_por
            ");
            
            $result = $stmt->execute([
                ':titulo' => $titulo,
                ':descripcion' => $descripcion,
                ':categoria' => $categoria ?: null,
                ':dirigido_a' => $dirigido_a ?: null,
                ':estado' => $estado,
                ':id' => $curso_id,
                ':creado_por' => $_SESSION['user_id']
            ]);
        }
        
        if ($result) {
            header('Location:'  . BASE_URL . '/docente/admin_cursos.php?success=curso_actualizado');
        } else {
            header('Location:'  . BASE_URL . '/docente/editar_curso.php?id=' . $curso_id . '&error=no_changes');
        }
        exit;
        
    } catch (Exception $e) {
        error_log("Error actualizando curso: " . $e->getMessage());
        header('Location:'  . BASE_URL . '/docente/editar_curso.php?id=' . $curso_id . '&error=error_actualizar&details=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location:'  . BASE_URL . '/docente/admin_cursos.php');
    exit;
}
?>
