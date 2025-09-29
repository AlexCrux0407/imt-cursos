<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('master');
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $curso_id = (int)($_POST['curso_id'] ?? 0);
    $docente_id = (int)($_POST['docente_id'] ?? 0);
    $instrucciones = trim($_POST['instrucciones'] ?? '');
    
    if ($curso_id === 0 || $docente_id === 0) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        exit;
    }
    
    try {
        $conn->beginTransaction();
        
        // Verificar que el curso existe
        $stmt = $conn->prepare("SELECT titulo FROM cursos WHERE id = :id");
        $stmt->execute([':id' => $curso_id]);
        $curso = $stmt->fetch();
        
        if (!$curso) {
            echo json_encode(['success' => false, 'message' => 'Curso no encontrado']);
            exit;
        }
        
        // Verificar que el docente existe
        $stmt = $conn->prepare("SELECT nombre, email FROM usuarios WHERE id = :id AND role = 'docente'");
        $stmt->execute([':id' => $docente_id]);
        $docente = $stmt->fetch();
        
        if (!$docente) {
            echo json_encode(['success' => false, 'message' => 'Docente no encontrado']);
            exit;
        }
        
        // Actualizar asignación del curso
        $stmt = $conn->prepare("
            UPDATE cursos 
            SET asignado_a = :docente_id, 
                fecha_asignacion = NOW(), 
                estado_asignacion = 'pendiente'
            WHERE id = :curso_id
        ");
        $stmt->execute([
            ':docente_id' => $docente_id,
            ':curso_id' => $curso_id
        ]);
        
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
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error en asignación: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
