<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('master');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y validar datos del formulario
    $curso_id = (int)($_POST['curso_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $dirigido_a = trim($_POST['dirigido_a'] ?? '');
    $estado = $_POST['estado'] ?? 'borrador';
    $asignado_a = (int)($_POST['asignado_a'] ?? 0);
    
    // Validaciones básicas
    if ($curso_id === 0) {
        header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=curso_no_especificado');
        exit;
    }
    
    if (empty($titulo)) {
        header('Location: ' . BASE_URL . '/master/editar_curso.php?id=' . $curso_id . '&error=titulo_requerido');
        exit;
    }
    
    // Validar categoría
    if (empty($categoria) || strlen($categoria) > 100) {
        header('Location: ' . BASE_URL . '/master/editar_curso.php?id=' . $curso_id . '&error=categoria_invalida');
        exit;
    }
    
    // Validar dirigido_a
    if (empty($dirigido_a) || strlen($dirigido_a) > 200) {
        header('Location: ' . BASE_URL . '/master/editar_curso.php?id=' . $curso_id . '&error=dirigido_a_invalido');
        exit;
    }
    
    // Validar que el estado sea válido
    $estados_validos = ['borrador', 'activo', 'revision', 'inactivo'];
    if (!in_array($estado, $estados_validos)) {
        $estado = 'borrador';
    }
    
    try {
        $conn->beginTransaction();
        
        // Verificar que el curso existe
        $stmt = $conn->prepare("SELECT id, titulo, asignado_a FROM cursos WHERE id = :curso_id");
        $stmt->execute([':curso_id' => $curso_id]);
        $curso_actual = $stmt->fetch();
        
        if (!$curso_actual) {
            $conn->rollback();
            header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=curso_no_encontrado');
            exit;
        }
        
        // Si se está asignando a un docente, validar que existe
        if ($asignado_a > 0) {
            $stmt = $conn->prepare("SELECT id, nombre FROM usuarios WHERE id = :docente_id AND role = 'docente'");
            $stmt->execute([':docente_id' => $asignado_a]);
            $docente = $stmt->fetch();
            
            if (!$docente) {
                $conn->rollback();
                header('Location: ' . BASE_URL . '/master/editar_curso.php?id=' . $curso_id . '&error=docente_no_encontrado');
                exit;
            }
        }
        
        // Preparar la consulta de actualización
        $campos_actualizacion = [
            'titulo = :titulo',
            'descripcion = :descripcion',
            'categoria = :categoria',
            'dirigido_a = :dirigido_a',
            'estado = :estado'
        ];
        
        $parametros = [
            ':curso_id' => $curso_id,
            ':titulo' => $titulo,
            ':descripcion' => $descripcion ?: null,
            ':categoria' => $categoria ?: null,
            ':dirigido_a' => $dirigido_a ?: null,
            ':estado' => $estado
        ];
        
        // Si hay cambio en la asignación, actualizar campos relacionados
        if ($curso_actual['asignado_a'] != $asignado_a) {
            if ($asignado_a > 0) {
                $campos_actualizacion[] = 'asignado_a = :asignado_a';
                $campos_actualizacion[] = 'fecha_asignacion = NOW()';
                $campos_actualizacion[] = 'estado_asignacion = :estado_asignacion';
                $parametros[':asignado_a'] = $asignado_a;
                $parametros[':estado_asignacion'] = 'pendiente';
            } else {
                $campos_actualizacion[] = 'asignado_a = NULL';
                $campos_actualizacion[] = 'fecha_asignacion = NULL';
                $campos_actualizacion[] = 'estado_asignacion = NULL';
            }
        }
        
        // Ejecutar la actualización
        $sql = "UPDATE cursos SET " . implode(', ', $campos_actualizacion) . " WHERE id = :curso_id";
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute($parametros);
        
        if ($result) {
            // Log de la acción para auditoría
            $cambios = [];
            if ($curso_actual['titulo'] !== $titulo) $cambios[] = "título";
            if ($curso_actual['asignado_a'] != $asignado_a) {
                if ($asignado_a > 0) {
                    $cambios[] = "asignado a docente ID: {$asignado_a}";
                } else {
                    $cambios[] = "desasignado de docente";
                }
            }
            
            $cambios_str = !empty($cambios) ? implode(', ', $cambios) : 'información general';
            error_log("Master {$_SESSION['user_id']} ({$_SESSION['nombre']}) actualizó el curso ID: {$curso_id} - Cambios: {$cambios_str}");
            
            $conn->commit();
            header('Location: ' . BASE_URL . '/master/editar_curso.php?id=' . $curso_id . '&success=curso_actualizado');
        } else {
            $conn->rollback();
            error_log("Error: execute() retornó false al actualizar curso ID: {$curso_id}");
            header('Location: ' . BASE_URL . '/master/editar_curso.php?id=' . $curso_id . '&error=execute_failed');
        }
        exit;
        
    } catch (PDOException $e) {
        $conn->rollback();
        error_log("PDOException al actualizar curso ID {$curso_id}: " . $e->getMessage());
        header('Location: ' . BASE_URL . '/master/editar_curso.php?id=' . $curso_id . '&error=database_error&details=' . urlencode($e->getMessage()));
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Exception al actualizar curso ID {$curso_id}: " . $e->getMessage());
        header('Location: ' . BASE_URL . '/master/editar_curso.php?id=' . $curso_id . '&error=general_error&details=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    // Método no permitido
    header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=method_not_allowed');
    exit;
}
?>