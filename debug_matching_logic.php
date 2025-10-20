<?php
require_once __DIR__ . '/config/database.php';

echo "=== ANÁLISIS DETALLADO DEL PROBLEMA DE EMPAREJAMIENTO ===\n\n";

// Obtener datos de la pregunta 19
$stmt = $conn->prepare("SELECT opciones, respuesta_correcta FROM preguntas_evaluacion WHERE id = 19");
$stmt->execute();
$pregunta = $stmt->fetch();

$opciones = json_decode($pregunta['opciones'], true);
$correctas = json_decode($pregunta['respuesta_correcta'], true);

echo "=== ESTRUCTURA DE LA PREGUNTA ===\n";
echo "Opciones disponibles (pairs):\n";
foreach ($opciones['pairs'] as $index => $pair) {
    echo "  Índice $index: {$pair['left']} -> {$pair['right']}\n";
}

echo "\n=== RESPUESTAS CORRECTAS ESPERADAS ===\n";
foreach ($correctas as $indice => $definicion_correcta) {
    echo "  Par $indice debe tener: $definicion_correcta\n";
}

echo "\n=== ANÁLISIS DE RESPUESTAS DEL USUARIO ===\n";

// Simular las respuestas que el usuario dice que dio correctamente
$respuestas_usuario = [
    0 => "7", // Usuario seleccionó índice 7 para el par 0
    1 => "1", // Usuario seleccionó índice 1 para el par 1  
    2 => "0", // Usuario seleccionó índice 0 para el par 2
    3 => "4", // Usuario seleccionó índice 4 para el par 3
    4 => "2", // Usuario seleccionó índice 2 para el par 4
    5 => "3", // Usuario seleccionó índice 3 para el par 5
    6 => "5", // Usuario seleccionó índice 5 para el par 6
    7 => "6"  // Usuario seleccionó índice 6 para el par 7
];

foreach ($respuestas_usuario as $par_indice => $indice_seleccionado) {
    echo "\nPar $par_indice:\n";
    echo "  Respuesta correcta esperada: {$correctas[$par_indice]}\n";
    echo "  Usuario seleccionó índice: $indice_seleccionado\n";
    
    if (isset($opciones['pairs'][$indice_seleccionado])) {
        $definicion_seleccionada = $opciones['pairs'][$indice_seleccionado]['right'];
        echo "  Definición seleccionada: $definicion_seleccionada\n";
        
        // Comparar
        $es_correcto = ($definicion_seleccionada === $correctas[$par_indice]);
        echo "  ¿Es correcto? " . ($es_correcto ? "SÍ" : "NO") . "\n";
        
        if (!$es_correcto) {
            // Buscar dónde debería ir esta definición
            foreach ($correctas as $indice_correcto => $def_correcta) {
                if ($definicion_seleccionada === $def_correcta) {
                    echo "  Esta definición debería ir en el par $indice_correcto\n";
                    break;
                }
            }
        }
    } else {
        echo "  ERROR: Índice seleccionado no existe\n";
    }
}

echo "\n=== MAPEO CORRECTO ===\n";
echo "Para que todas las respuestas sean correctas, el usuario debería seleccionar:\n";
foreach ($correctas as $par_indice => $definicion_correcta) {
    // Buscar qué índice tiene esta definición
    foreach ($opciones['pairs'] as $indice => $pair) {
        if ($pair['right'] === $definicion_correcta) {
            echo "  Par $par_indice -> Índice $indice ({$pair['left']})\n";
            break;
        }
    }
}
?>