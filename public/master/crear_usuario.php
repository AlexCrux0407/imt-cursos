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

// Validar datos requeridos
$required_fields = ['nombre', 'email', 'usuario', 'password', 'role'];
foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => "El campo $field es requerido"]);
        exit;
    }
}

// Validar rol
$allowed_roles = ['docente', 'ejecutivo', 'estudiante'];
if (!in_array($input['role'], $allowed_roles)) {
    echo json_encode(['success' => false, 'message' => 'Rol no válido']);
    exit;
}

// Validar email
if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email no válido']);
    exit;
}

// Verificar si el email ya existe
$stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = :email");
$stmt->execute([':email' => $input['email']]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'El email ya está registrado']);
    exit;
}

// Verificar si el usuario ya existe
$stmt = $conn->prepare("SELECT id FROM usuarios WHERE usuario = :usuario");
$stmt->execute([':usuario' => $input['usuario']]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'El nombre de usuario ya está en uso']);
    exit;
}

try {
    // Crear el usuario
    $password_hash = password_hash($input['password'], PASSWORD_DEFAULT);
    $estado = $input['estado'] ?? 'activo';
    
    $sql = "INSERT INTO usuarios (nombre, email, usuario, password, role, estado, created_at) 
            VALUES (:nombre, :email, :usuario, :password, :role, :estado, NOW())";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        ':nombre' => $input['nombre'],
        ':email' => $input['email'],
        ':usuario' => $input['usuario'],
        ':password' => $password_hash,
        ':role' => $input['role'],
        ':estado' => $estado
    ]);
    
    if ($result) {
        $user_id = $conn->lastInsertId();
        echo json_encode([
            'success' => true, 
            'message' => ucfirst($input['role']) . ' creado exitosamente',
            'user_id' => $user_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al crear el usuario']);
    }
    
} catch (PDOException $e) {
    error_log("Error al crear usuario: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}
?>