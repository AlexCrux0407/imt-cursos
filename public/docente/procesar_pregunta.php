<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $evaluacion_id = (int)($_POST['evaluacion_id'] ?? 0);
    $modulo_id = (int)($_POST['modulo_id'] ?? 0);
    $curso_id = (int)($_POST['curso_id'] ?? 0);
    $pregunta_id = (int)($_POST['pregunta_id'] ?? 0);
    $pregunta = trim($_POST['pregunta'] ?? '');
    $tipo = $_POST['tipo'] ?? 'multiple_choice';
    $puntaje = (float)($_POST['puntaje'] ?? 1);
    $orden = (int)($_POST['orden'] ?? 1);
    $explicacion = trim($_POST['explicacion'] ?? '');
    
    if (empty($pregunta) || $evaluacion_id === 0) {
        header('Location: ' . BASE_URL . '/docente/preguntas_evaluacion.php?id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=datos_invalidos');
        exit;
    }
    
    // Verificar que la evaluación pertenece a un módulo del docente
    $stmt = $conn->prepare("
        SELECT e.id FROM evaluaciones_modulo e
        INNER JOIN modulos m ON e.modulo_id = m.id
        INNER JOIN cursos c ON m.curso_id = c.id
        WHERE e.id = :evaluacion_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
    ");
    $stmt->execute([':evaluacion_id' => $evaluacion_id, ':docente_id' => $_SESSION['user_id'], ':docente_id2' => $_SESSION['user_id']]);
    
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
                header('Location: ' . BASE_URL . '/docente/preguntas_evaluacion.php?id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=opciones_insuficientes');
                exit;
            }
            
            $opciones = json_encode(array_values($opciones_array));
            break;
            
        case 'seleccion_multiple':
            $opciones_array = $_POST['opciones'] ?? [];
            $respuestas_correctas = $_POST['respuesta_correcta'] ?? [];
            
            if (count($opciones_array) < 2 || empty($respuestas_correctas)) {
                header('Location: ' . BASE_URL . '/docente/preguntas_evaluacion.php?id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=opciones_insuficientes');
                exit;
            }
            
            $opciones = json_encode(array_values($opciones_array));
            $respuesta_correcta = json_encode($respuestas_correctas);
            break;
            
        case 'verdadero_falso':
            $respuesta_correcta = $_POST['respuesta_vf'] ?? null;
            
            if ($respuesta_correcta === null) {
                header('Location: ' . BASE_URL . '/docente/preguntas_evaluacion.php?id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=respuesta_requerida');
                exit;
            }
            break;
            
        case 'texto_corto':
        case 'texto_largo':
            $respuesta_correcta = trim($_POST['respuesta_texto'] ?? '');
            // Para preguntas de texto, la respuesta puede estar vacía (revisión manual)
            break;

        case 'emparejar_columnas':
            $col_izquierda = $_POST['col_izquierda'] ?? [];
            $col_derecha = $_POST['col_derecha'] ?? [];
            if (empty($col_izquierda) || empty($col_derecha) || count($col_izquierda) !== count($col_derecha)) {
                header('Location: ' . BASE_URL . '/docente/preguntas_evaluacion.php?id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=opciones_insuficientes');
                exit;
            }
            $pairs = [];
            foreach ($col_izquierda as $i => $left) {
                $pairs[] = ['left' => trim($left), 'right' => trim($col_derecha[$i] ?? '')];
            }
            $opciones = json_encode(['pairs' => $pairs]);
            // Guardamos como respuestas correctas el arreglo de la columna derecha en el mismo orden
            $respuesta_correcta = json_encode(array_column($pairs, 'right'));
            break;

        case 'completar_espacios':
            $texto = trim($_POST['texto_completar'] ?? '');
            $respuestas = $_POST['blancos_respuestas'] ?? [];
            $opciones = json_encode(['texto' => $texto, 'blancos' => count($respuestas)]);
            $respuesta_correcta = json_encode(array_values($respuestas));
            break;
    }
    
    try {
        if ($pregunta_id > 0) {
            // Actualizar pregunta existente
            $stmt = $conn->prepare("
                UPDATE preguntas_evaluacion 
                SET pregunta = :pregunta, tipo = :tipo, opciones = :opciones, 
                    respuesta_correcta = :respuesta_correcta, puntaje = :puntaje, 
                    orden = :orden, explicacion = :explicacion
                WHERE id = :pregunta_id AND evaluacion_id = :evaluacion_id
            ");
            
            $stmt->execute([
                ':pregunta' => $pregunta,
                ':tipo' => $tipo,
                ':opciones' => $opciones,
                ':respuesta_correcta' => $respuesta_correcta,
                ':puntaje' => $puntaje,
                ':orden' => $orden,
                ':explicacion' => $explicacion,
                ':pregunta_id' => $pregunta_id,
                ':evaluacion_id' => $evaluacion_id
            ]);
            
            $mensaje = 'pregunta_actualizada';
        } else {
            // Crear nueva pregunta
            $stmt = $conn->prepare("
                INSERT INTO preguntas_evaluacion (
                    evaluacion_id, pregunta, tipo, opciones, respuesta_correcta, 
                    puntaje, orden, explicacion
                ) VALUES (
                    :evaluacion_id, :pregunta, :tipo, :opciones, :respuesta_correcta,
                    :puntaje, :orden, :explicacion
                )
            ");
            
            $stmt->execute([
                ':evaluacion_id' => $evaluacion_id,
                ':pregunta' => $pregunta,
                ':tipo' => $tipo,
                ':opciones' => $opciones,
                ':respuesta_correcta' => $respuesta_correcta,
                ':puntaje' => $puntaje,
                ':orden' => $orden,
                ':explicacion' => $explicacion
            ]);
            
            $mensaje = 'pregunta_creada';
        }
        
        // Actualizar el puntaje máximo de la evaluación
        $stmt = $conn->prepare("
            UPDATE evaluaciones_modulo 
            SET puntaje_maximo = (
                SELECT SUM(puntaje) FROM preguntas_evaluacion 
                WHERE evaluacion_id = :evaluacion_id
            )
            WHERE id = :evaluacion_id
        ");
        $stmt->execute([':evaluacion_id' => $evaluacion_id]);
        
        header('Location: ' . BASE_URL . '/docente/preguntas_evaluacion.php?id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&success=' . $mensaje);
        exit;
        
    } catch (Exception $e) {
        error_log("Error procesando pregunta: " . $e->getMessage());
        header('Location: ' . BASE_URL . '/docente/preguntas_evaluacion.php?id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=error_procesar');
        exit;
    }
} else {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php');
    exit;
}
?>