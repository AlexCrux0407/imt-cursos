<?php
require_once __DIR__ . '/../config/database.php';

echo "<h2>Debug - Simulación de Envío de Evaluación</h2>";

// Simular datos POST como si fuera una evaluación real
$evaluacion_id = 8; // La evaluación que estamos probando
$usuario_id = 1; // Asumiendo usuario ID 1

echo "<h3>Datos de prueba:</h3>";
echo "<p>Evaluación ID: $evaluacion_id</p>";
echo "<p>Usuario ID: $usuario_id</p>";

try {
    // Verificar si la evaluación existe
    $stmt = $conn->prepare("
        SELECT e.*, m.curso_id
        FROM evaluaciones_modulo e
        INNER JOIN modulos m ON e.modulo_id = m.id
        WHERE e.id = :evaluacion_id AND e.activo = 1
    ");
    $stmt->execute([':evaluacion_id' => $evaluacion_id]);
    $evaluacion = $stmt->fetch();
    
    if (!$evaluacion) {
        throw new Exception('Evaluación no encontrada');
    }
    
    echo "<h3>Evaluación encontrada:</h3>";
    echo "<pre>" . print_r($evaluacion, true) . "</pre>";
    
    // Verificar inscripción del estudiante
    $stmt = $conn->prepare("
        SELECT id FROM inscripciones 
        WHERE usuario_id = :usuario_id AND curso_id = :curso_id AND estado = 'activo'
    ");
    $stmt->execute([':usuario_id' => $usuario_id, ':curso_id' => $evaluacion['curso_id']]);
    $inscripcion = $stmt->fetch();
    
    if (!$inscripcion) {
        throw new Exception('No tienes acceso a esta evaluación');
    }
    
    echo "<h3>Inscripción verificada:</h3>";
    echo "<p>Inscripción ID: " . $inscripcion['id'] . "</p>";
    
    // Verificar intentos previos
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_intentos, MAX(puntaje_obtenido) as mejor_puntaje
        FROM intentos_evaluacion
        WHERE usuario_id = :usuario_id AND evaluacion_id = :evaluacion_id
    ");
    $stmt->execute([':usuario_id' => $usuario_id, ':evaluacion_id' => $evaluacion_id]);
    $intentos_info = $stmt->fetch();
    
    echo "<h3>Información de intentos:</h3>";
    echo "<p>Total intentos: " . $intentos_info['total_intentos'] . "</p>";
    echo "<p>Mejor puntaje: " . ($intentos_info['mejor_puntaje'] ?? 'NULL') . "</p>";
    echo "<p>Intentos permitidos: " . $evaluacion['intentos_permitidos'] . "</p>";
    
    // Obtener preguntas
    $stmt = $conn->prepare("
        SELECT * FROM preguntas_evaluacion
        WHERE evaluacion_id = :evaluacion_id
        ORDER BY orden ASC
    ");
    $stmt->execute([':evaluacion_id' => $evaluacion_id]);
    $preguntas = $stmt->fetchAll();
    
    echo "<h3>Preguntas de la evaluación:</h3>";
    echo "<p>Total preguntas: " . count($preguntas) . "</p>";
    
    foreach ($preguntas as $pregunta) {
        echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
        echo "<p><strong>ID:</strong> " . $pregunta['id'] . "</p>";
        echo "<p><strong>Tipo:</strong> " . $pregunta['tipo'] . "</p>";
        echo "<p><strong>Pregunta:</strong> " . htmlspecialchars($pregunta['pregunta']) . "</p>";
        echo "<p><strong>Puntaje:</strong> " . $pregunta['puntaje'] . "</p>";
        echo "</div>";
    }
    
    // Verificar BASE_URL
    echo "<h3>Configuración:</h3>";
    echo "<p>BASE_URL: " . (defined('BASE_URL') ? BASE_URL : 'NO DEFINIDO') . "</p>";
    
    // Simular respuestas correctas para pregunta 19 (emparejar_columnas)
    echo "<h3>Simulación de respuestas correctas:</h3>";
    $respuestas_correctas = [
        '19' => '[0,1,2,3,4,5,6,7]' // Respuesta correcta para emparejar_columnas
    ];
    
    foreach ($respuestas_correctas as $pregunta_id => $respuesta) {
        echo "<p>Pregunta $pregunta_id: $respuesta</p>";
    }
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Error:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h3>Formulario de prueba:</h3>";
echo "<form method='POST' action='estudiante/procesar_intento_evaluacion.php'>";
echo "<input type='hidden' name='evaluacion_id' value='8'>";
echo "<input type='hidden' name='respuesta_19' value='[0,1,2,3,4,5,6,7]'>";
echo "<button type='submit'>Enviar Evaluación de Prueba</button>";
echo "</form>";
?>