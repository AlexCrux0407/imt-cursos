<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('master');
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id']) || !isset($input['estado'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$docente_id = (int)$input['id'];
$nuevo_estado = $input['estado'];

// Validar estado
if (!in_array($nuevo_estado, ['activo', 'inactivo'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Estado inválido']);
    exit;
}

try {
    // Verificar que el usuario existe y es docente
    $stmt = $conn->prepare("SELECT id, nombre FROM usuarios WHERE id = :id AND role = 'docente'");
    $stmt->execute([':id' => $docente_id]);
    $docente = $stmt->fetch();
    
    if (!$docente) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Docente no encontrado']);
        exit;
    }
    
    // Si se está desactivando el docente, verificar si tiene cursos asignados activos
    if ($nuevo_estado === 'inactivo') {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as cursos_activos 
            FROM cursos 
            WHERE asignado_a = :docente_id AND estado = 'activo'
        ");
        $stmt->execute([':docente_id' => $docente_id]);
        $result = $stmt->fetch();
        
        if ($result['cursos_activos'] > 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'No se puede desactivar el docente porque tiene cursos activos asignados. Primero reasigne o desactive los cursos.'
            ]);
            exit;
        }
    }
    
    // Actualizar estado
    $stmt = $conn->prepare("UPDATE usuarios SET estado = :estado WHERE id = :id");
    $stmt->execute([
        ':estado' => $nuevo_estado,
        ':id' => $docente_id
    ]);
    
    // Log de la acción (opcional)
    error_log("Master user {$_SESSION['user_id']} changed teacher {$docente_id} ({$docente['nombre']}) status to {$nuevo_estado}");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Estado del docente actualizado correctamente',
        'nuevo_estado' => $nuevo_estado,
        'docente_nombre' => $docente['nombre']
    ]);
    
} catch (Exception $e) {
    error_log("Error al cambiar estado de docente: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>