<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $modulo_id = (int)($_POST['modulo_id'] ?? 0);
    $orden = (int)($_POST['orden'] ?? 0);
    $leccion_id = (int)($_POST['leccion_id'] ?? 0);
    
    // Verificar si el orden ya existe
    $stmt = $conn->prepare("
        SELECT l.id FROM lecciones l
        INNER JOIN modulos m ON l.modulo_id = m.id
        INNER JOIN cursos c ON m.curso_id = c.id
        WHERE l.modulo_id = :modulo_id AND l.orden = :orden 
        AND l.id != :leccion_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
    ");
    
    $stmt->execute([
        ':modulo_id' => $modulo_id,
        ':orden' => $orden,
        ':leccion_id' => $leccion_id,
        ':docente_id' => $_SESSION['user_id']
    ]);
    
    $existe = $stmt->fetch();
    
    echo json_encode([
        'disponible' => !$existe,
        'orden' => $orden
    ]);
} else {
    echo json_encode(['disponible' => false]);
}
?>
