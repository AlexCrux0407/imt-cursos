<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pregunta_id = (int)($_POST['pregunta_id'] ?? 0);
    $evaluacion_id = (int)($_POST['evaluacion_id'] ?? 0);
    $modulo_id = (int)($_POST['modulo_id'] ?? 0);
    $curso_id = (int)($_POST['curso_id'] ?? 0);
    $pregunta = trim($_POST['pregunta'] ?? '');
    $tipo = $_POST['tipo'] ?? 'multiple_choice';
    $puntaje = (float)($_POST['puntaje'] ?? 1);
    $orden = (int)($_POST['orden'] ?? 1);
    $explicacion = trim($_POST['explicacion'] ?? '');
    
    if (empty($pregunta) || $pregunta_id === 0 || $evaluacion_id === 0) {
        header('Location: ' . BASE_URL . '/docente/preguntas_evaluacion.php?id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=datos_invalidos');
        exit;
    }
    
    // Verificar que la pregunta pertenece a una evaluación del docente
    $stmt = $conn->prepare("
        SELECT p.id FROM preguntas_evaluacion p
        INNER JOIN evaluaciones_modulo e ON p.evaluacion_id = e.id
        INNER JOIN modulos m ON e.modulo_id = m.id
        INNER JOIN cursos c ON m.curso_id = c.id
        WHERE p.id = :pregunta_id AND e.id = :evaluacion_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
    ");
    $stmt->execute([
        ':pregunta_id' => $pregunta_id,
        ':evaluacion_id' => $evaluacion_id,
        ':docente_id' => $_SESSION['user_id'],
        ':docente_id2' => $_SESSION['user_id']
    ]);
    
    if (!$stmt->fetch()) {
        header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=acceso_denegado');
        exit;
    }
    
    // Procesar respuesta correcta según el tipo
    $opciones = null;
    $respuesta_correcta = null;
    
    switch ($tipo) {
        case 'multiple_choice':
            $opciones_array = $_POST['opciones'] ?? [];
            $respuesta_correcta = $_POST['respuesta_correcta'] ?? null;
            
            if (count($opciones_array) < 2) {
                header('Location: ' . BASE_URL . '/docente/editar_pregunta.php?id=' . $pregunta_id . '&evaluacion_id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=opciones_insuficientes');
                exit;
            }
            
            $opciones = json_encode(array_values($opciones_array));
            break;
            
        case 'seleccion_multiple':
            $opciones_array = $_POST['opciones'] ?? [];
            $respuestas_correctas = $_POST['respuesta_correcta'] ?? [];
            
            if (count($opciones_array) < 2) {
                header('Location: ' . BASE_URL . '/docente/editar_pregunta.php?id=' . $pregunta_id . '&evaluacion_id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=opciones_insuficientes');
                exit;
            }
            
            $opciones = json_encode(array_values($opciones_array));
            $respuesta_correcta = json_encode(array_values($respuestas_correctas));
            break;
            
        case 'emparejar_columnas':
            $columna1 = $_POST['columna1'] ?? [];
            $columna2 = $_POST['columna2'] ?? [];
            
            if (count($columna1) < 2 || count($columna2) < 2) {
                header('Location: ' . BASE_URL . '/docente/editar_pregunta.php?id=' . $pregunta_id . '&evaluacion_id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=parejas_insuficientes');
                exit;
            }
            
            $opciones = json_encode(['pairs' => array_map(null, $columna1, $columna2)]);
            $respuesta_correcta = json_encode(array_combine(range(0, count($columna1) - 1), range(0, count($columna1) - 1)));
            break;
            
        case 'completar_espacios':
            $texto = trim($_POST['texto_completar'] ?? '');
            $respuestas = $_POST['blancos_respuestas'] ?? [];
            
            // Validar que hay texto y respuestas
            if (empty($texto) || empty($respuestas)) {
                header('Location: ' . BASE_URL . '/docente/editar_pregunta.php?id=' . $pregunta_id . '&evaluacion_id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=datos_incompletos');
                exit;
            }
            
            // Procesar distractores de forma segura
            $distractores = [];
            if (isset($_POST['distractores']) && is_array($_POST['distractores'])) {
                $distractores = array_filter(array_map('trim', $_POST['distractores']), function($d) { 
                    return !empty($d); 
                });
            }
            
            $opciones = json_encode([
                'texto' => $texto, 
                'blancos' => count($respuestas),
                'distractores' => $distractores
            ], JSON_UNESCAPED_UNICODE);
            
            $respuesta_correcta = json_encode(array_values($respuestas), JSON_UNESCAPED_UNICODE);
            break;
            
        case 'verdadero_falso':
            $respuesta_correcta = $_POST['respuesta_vf'] ?? null;
            break;
            
        case 'texto_corto':
        case 'texto_largo':
            $respuesta_correcta = trim($_POST['respuesta_texto'] ?? '');
            break;
    }
    
    try {
        $conn->beginTransaction();
        
        // Actualizar la pregunta
        $stmt = $conn->prepare("
            UPDATE preguntas_evaluacion 
            SET pregunta = :pregunta, 
                tipo = :tipo, 
                opciones = :opciones, 
                respuesta_correcta = :respuesta_correcta, 
                puntaje = :puntaje, 
                orden = :orden, 
                explicacion = :explicacion
            WHERE id = :pregunta_id
        ");
        
        $stmt->execute([
            ':pregunta' => $pregunta,
            ':tipo' => $tipo,
            ':opciones' => $opciones,
            ':respuesta_correcta' => $respuesta_correcta,
            ':puntaje' => $puntaje,
            ':orden' => $orden,
            ':explicacion' => $explicacion,
            ':pregunta_id' => $pregunta_id
        ]);
        
        $conn->commit();
        
        header('Location: ' . BASE_URL . '/docente/preguntas_evaluacion.php?id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&success=pregunta_actualizada');
        exit;
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error al actualizar pregunta: " . $e->getMessage());
        error_log("POST data: " . print_r($_POST, true));
        error_log("Pregunta ID: " . $pregunta_id . ", Tipo: " . $tipo);
        header('Location: ' . BASE_URL . '/docente/editar_pregunta.php?id=' . $pregunta_id . '&evaluacion_id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=error_servidor');
        exit;
    }
} else {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php');
    exit;
}
?>