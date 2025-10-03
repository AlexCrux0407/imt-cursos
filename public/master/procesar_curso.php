<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('master');
require_once __DIR__ . '/../../config/database.php';

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=csrf_invalid');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y validar datos del formulario
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $dirigido_a = trim($_POST['dirigido_a'] ?? '');
    $estado = $_POST['estado'] ?? 'borrador';
    $asignado_a = (int)($_POST['asignado_a'] ?? 0);
    
    // Validaciones del lado del servidor
    $errores = [];
    
    if (empty($titulo)) {
        $errores[] = 'titulo_requerido';
    } elseif (strlen($titulo) > 255) {
        $errores[] = 'titulo_muy_largo';
    } elseif (!preg_match('/^[a-zA-Z0-9\s\-\.\,\:\;\!\?\(\)áéíóúÁÉÍÓÚñÑüÜ]+$/', $titulo)) {
        $errores[] = 'titulo_caracteres_invalidos';
    }
    
    if (empty($categoria)) {
        $errores[] = 'categoria_requerida';
    }
    
    if (empty($dirigido_a)) {
        $errores[] = 'dirigido_a_requerido';
    }
    
    if (!empty($descripcion) && strlen($descripcion) > 1000) {
        $errores[] = 'descripcion_muy_larga';
    }
    
    // Validar que el estado sea válido
    $estados_validos = ['borrador', 'activo', 'revision', 'inactivo'];
    if (!in_array($estado, $estados_validos)) {
        $estado = 'borrador';
    }
    
    // Validar categoría
    if (empty($categoria) || strlen($categoria) > 100) {
        $errores[] = 'categoria_invalida';
    }
    
    // Validar dirigido_a
    if (empty($dirigido_a) || strlen($dirigido_a) > 200) {
        $errores[] = 'dirigido_a_invalido';
    }
    
    // Si hay errores, redirigir con los errores
    if (!empty($errores)) {
        $error_string = implode(',', $errores);
        header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=' . $error_string);
        exit;
    }
    
    try {
        $conn->beginTransaction();
        
        // Verificar si ya existe un curso con el mismo título
        $stmt = $conn->prepare("SELECT id FROM cursos WHERE titulo = :titulo");
        $stmt->execute([':titulo' => $titulo]);
        if ($stmt->fetch()) {
            $conn->rollback();
            header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=titulo_duplicado');
            exit;
        }
        
        // Si se asigna a un docente, validar que existe
        if ($asignado_a > 0) {
            $stmt = $conn->prepare("SELECT id, nombre FROM usuarios WHERE id = :docente_id AND role = 'docente'");
            $stmt->execute([':docente_id' => $asignado_a]);
            $docente = $stmt->fetch();
            
            if (!$docente) {
                $conn->rollback();
                header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=docente_no_encontrado');
                exit;
            }
        }
        
        // Preparar la consulta de inserción
        $sql = "INSERT INTO cursos (titulo, descripcion, categoria, dirigido_a, estado, creado_por";
        $valores = "(:titulo, :descripcion, :categoria, :dirigido_a, :estado, :creado_por";
        $parametros = [
            ':titulo' => $titulo,
            ':descripcion' => $descripcion ?: null,
            ':categoria' => $categoria,
            ':dirigido_a' => $dirigido_a,
            ':estado' => $estado,
            ':creado_por' => $_SESSION['user_id']
        ];
        
        // Si hay asignación, agregar campos relacionados
        if ($asignado_a > 0) {
            $sql .= ", asignado_a, fecha_asignacion, estado_asignacion";
            $valores .= ", :asignado_a, NOW(), :estado_asignacion";
            $parametros[':asignado_a'] = $asignado_a;
            $parametros[':estado_asignacion'] = 'pendiente';
        }
        
        $sql .= ") VALUES " . $valores . ")";
        
        // DEBUGGING: Log de la consulta SQL y parámetros
        error_log("=== DEBUGGING MASTER PROCESAR_CURSO ===");
        error_log("SQL Query: " . $sql);
        error_log("Parámetros: " . print_r($parametros, true));
        error_log("Número de parámetros: " . count($parametros));
        error_log("Número de placeholders en SQL: " . substr_count($sql, ':'));
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute($parametros);
        
        if ($result) {
            $curso_id = $conn->lastInsertId();
            
            // Log de la acción para auditoría
            $log_message = "Master {$_SESSION['user_id']} ({$_SESSION['nombre']}) creó el curso ID: {$curso_id} - Título: {$titulo}";
            if ($asignado_a > 0) {
                $log_message .= " - Asignado a docente ID: {$asignado_a}";
            }
            error_log($log_message);
            
            $conn->commit();
            
            // Regenerar token CSRF para la siguiente operación
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            header('Location: ' . BASE_URL . '/master/admin_cursos.php?success=curso_creado&id=' . $curso_id);
        } else {
            $conn->rollback();
            error_log("Error: execute() retornó false al crear curso");
            header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=execute_failed');
        }
        exit;
        
    } catch (PDOException $e) {
        $conn->rollback();
        error_log("PDOException al crear curso: " . $e->getMessage());
        header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=database_error&details=' . urlencode($e->getMessage()));
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Exception al crear curso: " . $e->getMessage());
        header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=general_error&details=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    // Método no permitido
    header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=method_not_allowed');
    exit;
}
?>