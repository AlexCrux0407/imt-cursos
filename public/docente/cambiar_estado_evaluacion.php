<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $evaluacion_id = (int)($input['evaluacion_id'] ?? 0);
    $activo = (bool)($input['activo'] ?? false);
    
    if ($evaluacion_id === 0) {
        echo json_encode(['success' => false, 'message' => 'ID de evaluación inválido']);
        exit;
    }
    
    try {
        // Verificar que la evaluación pertenece a un módulo del docente
        $stmt = $conn->prepare("
            SELECT e.id FROM evaluaciones_modulo e
            INNER JOIN modulos m ON e.modulo_id = m.id
            INNER JOIN cursos c ON m.curso_id = c.id
            WHERE e.id = :evaluacion_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
        ");
        $stmt->execute([':evaluacion_id' => $evaluacion_id, ':docente_id' => $_SESSION['user_id'], ':docente_id2' => $_SESSION['user_id']]);
        
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            exit;
        }
        
        // Actualizar el estado
        $stmt = $conn->prepare("
            UPDATE evaluaciones_modulo 
            SET activo = :activo, fecha_actualizacion = CURRENT_TIMESTAMP
            WHERE id = :evaluacion_id
        ");
        $stmt->execute([':activo' => $activo ? 1 : 0, ':evaluacion_id' => $evaluacion_id]);
        
        echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
        
    } catch (Exception $e) {
        error_log("Error cambiando estado de evaluación: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>