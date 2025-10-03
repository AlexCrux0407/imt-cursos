<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('master');
require_once __DIR__ . '/../../config/database.php';

// Configurar respuesta JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $curso_id = (int)($_POST['curso_id'] ?? 0);
    $docente_id = (int)($_POST['docente_id'] ?? 0);
    $instrucciones = trim($_POST['instrucciones'] ?? '');
    
    // Validaciones básicas
    if ($curso_id === 0) {
        echo json_encode(['success' => false, 'message' => 'Curso no especificado']);
        exit;
    }
    
    if ($docente_id === 0) {
        echo json_encode(['success' => false, 'message' => 'Docente no especificado']);
        exit;
    }
    
    try {
        $conn->beginTransaction();
        
        // Verificar que el curso existe y obtener información actual
        $stmt = $conn->prepare("SELECT id, titulo, asignado_a FROM cursos WHERE id = :curso_id");
        $stmt->execute([':curso_id' => $curso_id]);
        $curso = $stmt->fetch();
        
        if (!$curso) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Curso no encontrado']);
            exit;
        }
        
        // Verificar que el docente existe y tiene el rol correcto
        $stmt = $conn->prepare("SELECT id, nombre, email FROM usuarios WHERE id = :docente_id AND role = 'docente'");
        $stmt->execute([':docente_id' => $docente_id]);
        $docente = $stmt->fetch();
        
        if (!$docente) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Docente no encontrado o no tiene el rol correcto']);
            exit;
        }
        
        // Verificar si el curso ya está asignado al mismo docente
        if ($curso['asignado_a'] == $docente_id) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'El curso ya está asignado a ' . $docente['nombre']]);
            exit;
        }
        
        // Actualizar la asignación del curso
        $stmt = $conn->prepare("
            UPDATE cursos 
            SET asignado_a = :docente_id, 
                fecha_asignacion = NOW(), 
                estado_asignacion = 'pendiente'
            WHERE id = :curso_id
        ");
        
        $result = $stmt->execute([
            ':docente_id' => $docente_id,
            ':curso_id' => $curso_id
        ]);
        
        if ($result && $stmt->rowCount() > 0) {
            // Log de la acción para auditoría
            $docente_anterior = $curso['asignado_a'] ? "reasignado desde docente ID: {$curso['asignado_a']}" : "asignado por primera vez";
            $log_message = "Master {$_SESSION['user_id']} ({$_SESSION['nombre']}) asignó el curso ID: {$curso_id} ('{$curso['titulo']}') al docente ID: {$docente_id} ({$docente['nombre']}) - {$docente_anterior}";
            error_log($log_message);
            
            // Crear notificación para el docente
            try {
                $mensaje = "Se te ha asignado el curso '{$curso['titulo']}' para desarrollo de contenido.";
                if (!empty($instrucciones)) {
                    $mensaje .= "\n\nInstrucciones: {$instrucciones}";
                }
                
                $stmt = $conn->prepare("
                    INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, datos_extra) 
                    VALUES (:usuario_id, 'curso_asignado', :titulo, :mensaje, :datos_extra)
                ");
                
                $datos_extra = json_encode([
                    'curso_id' => $curso_id,
                    'asignado_por' => $_SESSION['nombre'] ?? 'Sistema',
                    'fecha_asignacion' => date('Y-m-d H:i:s')
                ]);
                
                $stmt->execute([
                    ':usuario_id' => $docente_id,
                    ':titulo' => "Curso Asignado: {$curso['titulo']}",
                    ':mensaje' => $mensaje,
                    ':datos_extra' => $datos_extra
                ]);
            } catch (Exception $notif_error) {
                // Si falla la notificación, continuar sin interrumpir la asignación
                error_log("Error al crear notificación: " . $notif_error->getMessage());
            }
            
            $conn->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => "Curso asignado exitosamente a {$docente['nombre']}"
            ]);
        } else {
            $conn->rollback();
            error_log("Error: No se pudo actualizar la asignación del curso ID: {$curso_id}");
            echo json_encode(['success' => false, 'message' => 'No se pudo completar la asignación']);
        }
        
    } catch (PDOException $e) {
        $conn->rollback();
        error_log("PDOException al asignar curso ID {$curso_id} a docente ID {$docente_id}: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Exception al asignar curso ID {$curso_id} a docente ID {$docente_id}: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
    }
} else {
    // Método no permitido
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
