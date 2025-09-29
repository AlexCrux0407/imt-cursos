<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $pregunta_id = (int)($input['pregunta_id'] ?? 0);
    $direccion = $input['direccion'] ?? '';
    $evaluacion_id = (int)($input['evaluacion_id'] ?? 0);
    
    if ($pregunta_id === 0 || !in_array($direccion, ['arriba', 'abajo']) || $evaluacion_id === 0) {
        echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
        exit;
    }
    
    try {
        // Verificar que la pregunta pertenece a una evaluación del docente
        $stmt = $conn->prepare("
            SELECT p.orden FROM preguntas_evaluacion p
            INNER JOIN evaluaciones_modulo e ON p.evaluacion_id = e.id
            INNER JOIN modulos m ON e.modulo_id = m.id
            INNER JOIN cursos c ON m.curso_id = c.id
            WHERE p.id = :pregunta_id AND c.creado_por = :docente_id
        ");
        $stmt->execute([':pregunta_id' => $pregunta_id, ':docente_id' => $_SESSION['user_id']]);
        $pregunta = $stmt->fetch();
        
        if (!$pregunta) {
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            exit;
        }
        
        $orden_actual = $pregunta['orden'];
        $nuevo_orden = $direccion === 'arriba' ? $orden_actual - 1 : $orden_actual + 1;
        
        // Verificar que el nuevo orden es válido
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total FROM preguntas_evaluacion 
            WHERE evaluacion_id = :evaluacion_id
        ");
        $stmt->execute([':evaluacion_id' => $evaluacion_id]);
        $total_preguntas = $stmt->fetch()['total'];
        
        if ($nuevo_orden < 1 || $nuevo_orden > $total_preguntas) {
            echo json_encode(['success' => false, 'message' => 'Movimiento no válido']);
            exit;
        }
        
        $conn->beginTransaction();
        
        // Intercambiar órdenes
        // Primero, encontrar la pregunta que tiene el orden destino
        $stmt = $conn->prepare("
            SELECT id FROM preguntas_evaluacion 
            WHERE evaluacion_id = :evaluacion_id AND orden = :nuevo_orden
        ");
        $stmt->execute([':evaluacion_id' => $evaluacion_id, ':nuevo_orden' => $nuevo_orden]);
        $pregunta_destino = $stmt->fetch();
        
        if ($pregunta_destino) {
            // Intercambiar los órdenes
            $stmt = $conn->prepare("
                UPDATE preguntas_evaluacion 
                SET orden = :orden_actual 
                WHERE id = :pregunta_destino_id
            ");
            $stmt->execute([
                ':orden_actual' => $orden_actual,
                ':pregunta_destino_id' => $pregunta_destino['id']
            ]);
        }
        
        // Actualizar el orden de la pregunta que se está moviendo
        $stmt = $conn->prepare("
            UPDATE preguntas_evaluacion 
            SET orden = :nuevo_orden 
            WHERE id = :pregunta_id
        ");
        $stmt->execute([
            ':nuevo_orden' => $nuevo_orden,
            ':pregunta_id' => $pregunta_id
        ]);
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Pregunta movida exitosamente']);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error moviendo pregunta: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al mover la pregunta']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>