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
$nombres = trim($input['nombres'] ?? '');
$apellidos = trim($input['apellidos'] ?? '');
$nombre = trim($input['nombre'] ?? '');
if ($nombre === '') {
    $nombre = trim($nombres . ' ' . $apellidos);
}

// Validar datos requeridos
$required_fields = ['email', 'password', 'role'];
foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => "El campo $field es requerido"]);
        exit;
    }
}
if ($nombre === '') {
    echo json_encode(['success' => false, 'message' => 'El nombre es requerido']);
    exit;
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

$stmt = $conn->prepare("SHOW COLUMNS FROM usuarios LIKE 'tipo_estudiante'");
$stmt->execute();
$tiene_tipo_estudiante = (bool)$stmt->fetch();
$tipo_estudiante = $input['tipo_estudiante'] ?? 'interno';
if ($tiene_tipo_estudiante && !in_array($tipo_estudiante, ['interno', 'externo'], true)) {
    echo json_encode(['success' => false, 'message' => 'Tipo de estudiante no válido']);
    exit;
}

try {
    // Crear el usuario
    $password_hash = password_hash($input['password'], PASSWORD_DEFAULT);
    $estado = $input['estado'] ?? 'activo';

    $stmt = $conn->prepare("SHOW COLUMNS FROM usuarios LIKE 'nombre'");
    $stmt->execute();
    $tiene_nombre = (bool)$stmt->fetch();
    $stmt = $conn->prepare("SHOW COLUMNS FROM usuarios LIKE 'nombres'");
    $stmt->execute();
    $tiene_nombres = (bool)$stmt->fetch();
    $stmt = $conn->prepare("SHOW COLUMNS FROM usuarios LIKE 'apellidos'");
    $stmt->execute();
    $tiene_apellidos = (bool)$stmt->fetch();

    $columnas = [];
    $placeholders = [];
    $parametros = [
        ':email' => $input['email'],
        ':password' => $password_hash,
        ':role' => $input['role'],
        ':estado' => $estado
    ];

    if ($tiene_nombre) {
        $columnas[] = 'nombre';
        $placeholders[] = ':nombre';
        $parametros[':nombre'] = $nombre;
    }
    if ($tiene_nombres) {
        $columnas[] = 'nombres';
        $placeholders[] = ':nombres';
        $parametros[':nombres'] = $nombres;
    }
    if ($tiene_apellidos) {
        $columnas[] = 'apellidos';
        $placeholders[] = ':apellidos';
        $parametros[':apellidos'] = $apellidos;
    }
    
    $columnas = array_merge($columnas, ['email', 'password', 'role', 'estado', 'created_at']);
    $placeholders = array_merge($placeholders, [':email', ':password', ':role', ':estado', 'NOW()']);
    
    if ($tiene_tipo_estudiante) {
        $columnas[] = 'tipo_estudiante';
        $placeholders[] = ':tipo_estudiante';
        $parametros[':tipo_estudiante'] = $tipo_estudiante;
    }

    $sql = "INSERT INTO usuarios (" . implode(', ', $columnas) . ") VALUES (" . implode(', ', $placeholders) . ")";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute($parametros);
    
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
