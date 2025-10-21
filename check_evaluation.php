<?php
require_once 'config/database.php';

// Verificar que la evaluación 11 tenga preguntas de organigrama
$stmt = $conn->prepare("
    SELECT pe.*, e.titulo as evaluacion_titulo 
    FROM preguntas_evaluacion pe 
    JOIN evaluaciones e ON pe.evaluacion_id = e.id 
    WHERE pe.evaluacion_id = 11 AND pe.tipo = 'organigrama'
");
$stmt->execute();
$pregunta = $stmt->fetch();

if ($pregunta) {
    echo "✅ Pregunta de organigrama encontrada:\n";
    echo "ID: " . $pregunta['id'] . "\n";
    echo "Evaluación: " . $pregunta['evaluacion_titulo'] . "\n";
    echo "Pregunta: " . $pregunta['pregunta'] . "\n";
    echo "Respuesta correcta: " . $pregunta['respuesta_correcta'] . "\n";
    
    // Verificar que la respuesta correcta sea JSON válido
    $respuesta_json = json_decode($pregunta['respuesta_correcta'], true);
    if ($respuesta_json) {
        echo "\n✅ Respuesta correcta es JSON válido:\n";
        
        if (isset($respuesta_json['piezas'])) {
            echo "Piezas: " . count($respuesta_json['piezas']) . "\n";
            
            // Mostrar algunas piezas
            echo "\nPrimeras 3 piezas:\n";
            foreach (array_slice($respuesta_json['piezas'], 0, 3) as $pieza) {
                echo "- ID: " . $pieza['id'] . ", Texto: " . $pieza['texto'] . ", Tipo: " . $pieza['tipo'] . "\n";
            }
        }
        
        if (isset($respuesta_json['espacios'])) {
            echo "Espacios: " . count($respuesta_json['espacios']) . "\n";
            
            echo "\nPrimeros 3 espacios:\n";
            foreach (array_slice($respuesta_json['espacios'], 0, 3) as $espacio) {
                echo "- ID: " . $espacio['id'] . ", Acepta: " . implode(', ', $espacio['acepta']) . "\n";
            }
        }
    } else {
        echo "❌ Error: La respuesta correcta no es JSON válido\n";
    }
} else {
    echo "❌ No se encontró pregunta de organigrama para la evaluación 11\n";
}
?>