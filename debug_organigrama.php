<?php
require_once 'config/database.php';

$stmt = $conn->prepare('SELECT id, pregunta, respuesta_correcta FROM preguntas_evaluacion WHERE evaluacion_id = 11 AND tipo = "organigrama"');
$stmt->execute();
$pregunta = $stmt->fetch();

if ($pregunta) {
    echo "ID: " . $pregunta['id'] . "\n";
    echo "Pregunta: " . $pregunta['pregunta'] . "\n";
    echo "Respuesta correcta RAW: " . $pregunta['respuesta_correcta'] . "\n";
    
    $respuesta_decodificada = json_decode($pregunta['respuesta_correcta'], true);
    echo "Respuesta decodificada:\n";
    print_r($respuesta_decodificada);
    
    echo "\nTipo de respuesta decodificada: " . gettype($respuesta_decodificada) . "\n";
    
    if (is_array($respuesta_decodificada)) {
        echo "Claves en la respuesta:\n";
        foreach ($respuesta_decodificada as $key => $value) {
            echo "  $key => " . (is_array($value) ? 'Array' : $value) . " (tipo: " . gettype($value) . ")\n";
        }
    }
} else {
    echo "No se encontró pregunta de organigrama para evaluación 11\n";
}
?>