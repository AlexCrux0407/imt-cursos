<?php
require_once __DIR__ . '/config/database.php';

// Obtener datos de la pregunta 19
$stmt = $conn->prepare("SELECT id, pregunta, opciones, respuesta_correcta FROM preguntas_evaluacion WHERE id = 19");
$stmt->execute();
$pregunta = $stmt->fetch();

if (!$pregunta) {
    echo "No se encontró la pregunta 19\n";
    exit;
}

echo "=== ANÁLISIS DE LA PREGUNTA 19 ===\n\n";
echo "ID: " . $pregunta['id'] . "\n";
echo "Pregunta: " . $pregunta['pregunta'] . "\n\n";

// Analizar opciones
echo "=== OPCIONES ===\n";
$opciones = json_decode($pregunta['opciones'], true);
if ($opciones && isset($opciones['pairs'])) {
    foreach ($opciones['pairs'] as $index => $pair) {
        echo "Índice $index:\n";
        echo "  Left: " . $pair['left'] . "\n";
        echo "  Right: " . $pair['right'] . "\n\n";
    }
} else {
    echo "Error al decodificar opciones\n";
    echo "Opciones RAW: " . $pregunta['opciones'] . "\n\n";
}

// Analizar respuesta correcta
echo "=== RESPUESTA CORRECTA ===\n";
$correctas = json_decode($pregunta['respuesta_correcta'], true);
if ($correctas) {
    foreach ($correctas as $indice => $valor_correcto) {
        echo "Par $indice: $valor_correcto\n";
    }
} else {
    echo "Error al decodificar respuesta correcta\n";
    echo "Respuesta correcta RAW: " . $pregunta['respuesta_correcta'] . "\n";
}

echo "\n=== ANÁLISIS DE RESPUESTAS RECIENTES ===\n";
// Obtener las últimas respuestas de la pregunta 19
$stmt = $conn->prepare("
    SELECT r.*, u.nombre 
    FROM respuestas_estudiante r 
    JOIN intentos_evaluacion i ON r.intento_id = i.id 
    JOIN usuarios u ON i.usuario_id = u.id 
    WHERE r.pregunta_id = 19 
    ORDER BY r.fecha_respuesta DESC 
    LIMIT 10
");
$stmt->execute();
$respuestas = $stmt->fetchAll();

foreach ($respuestas as $resp) {
    echo "\nRespuesta ID: " . $resp['id'] . "\n";
    echo "Usuario: " . $resp['nombre'] . "\n";
    echo "Es correcta: " . ($resp['es_correcta'] ? 'SÍ' : 'NO') . "\n";
    
    $respuesta_data = json_decode($resp['respuesta'], true);
    if ($respuesta_data) {
        echo "Par índice: " . $respuesta_data['par_indice'] . "\n";
        echo "Respuesta estudiante: " . $respuesta_data['respuesta_estudiante_texto'] . "\n";
        echo "Respuesta correcta: " . $respuesta_data['respuesta_correcta'] . "\n";
        echo "Par correcto: " . ($respuesta_data['par_correcto'] ? 'SÍ' : 'NO') . "\n";
    }
    echo "---\n";
}
?>