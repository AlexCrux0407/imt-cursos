<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Mostrar todos los datos recibidos
    error_log("POST data: " . print_r($_POST, true));
    
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $dirigido_a = trim($_POST['dirigido_a'] ?? '');
    $estado = $_POST['estado'] ?? 'borrador';
    
    if (empty($titulo)) {
        header('Location: /imt-cursos/public/docente/admin_cursos.php?error=titulo_requerido');
        exit;
    }
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO cursos (titulo, descripcion, categoria, dirigido_a, estado, creado_por) 
            VALUES (:titulo, :descripcion, :categoria, :dirigido_a, :estado, :creado_por)
        ");
        
        $result = $stmt->execute([
            ':titulo' => $titulo,
            ':descripcion' => $descripcion,
            ':categoria' => $categoria ?: null,
            ':dirigido_a' => $dirigido_a ?: null,
            ':estado' => $estado,
            ':creado_por' => $_SESSION['user_id']
        ]);
        
        if ($result) {
            $curso_id = $conn->lastInsertId();
            error_log("Curso creado exitosamente con ID: " . $curso_id);
            header('Location: /imt-cursos/public/docente/admin_cursos.php?success=curso_creado');
        } else {
            error_log("Error: execute() retornó false");
            header('Location: /imt-cursos/public/docente/admin_cursos.php?error=execute_failed');
        }
        exit;
        
    } catch (PDOException $e) {
        error_log("PDOException: " . $e->getMessage());
        header('Location: /imt-cursos/public/docente/admin_cursos.php?error=database_error&details=' . urlencode($e->getMessage()));
        exit;
    } catch (Exception $e) {
        error_log("Exception: " . $e->getMessage());
        header('Location: /imt-cursos/public/docente/admin_cursos.php?error=general_error&details=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: /imt-cursos/public/docente/admin_cursos.php?error=method_not_allowed');
    exit;
}
?>