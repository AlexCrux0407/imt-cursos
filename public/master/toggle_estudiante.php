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

$estudiante_id = (int)$input['id'];
$nuevo_estado = $input['estado'];

// Validar estado
if (!in_array($nuevo_estado, ['activo', 'inactivo'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Estado inválido']);
    exit;
}

try {
    // Verificar que el usuario existe y es estudiante
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE id = :id AND role = 'estudiante'");
    $stmt->execute([':id' => $estudiante_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Estudiante no encontrado']);
        exit;
    }
    
    // Actualizar estado
    $stmt = $conn->prepare("UPDATE usuarios SET estado = :estado WHERE id = :id");
    $stmt->execute([
        ':estado' => $nuevo_estado,
        ':id' => $estudiante_id
    ]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Estado actualizado correctamente',
        'nuevo_estado' => $nuevo_estado
    ]);
    
} catch (Exception $e) {
    error_log("Error al cambiar estado de estudiante: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>