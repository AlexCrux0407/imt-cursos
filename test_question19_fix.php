<?php
require_once __DIR__ . '/config/database.php';

echo "=== PRUEBA DE LA CORRECCIÓN DE LA PREGUNTA 19 ===\n\n";

// Simular las respuestas del usuario basadas en los datos proporcionados
$respuestas_usuario = [
    0 => "7", // Par 0: Usuario seleccionó "Sitio en la intranet..."
    1 => "1", // Par 1: Usuario seleccionó "Sistema para establecer la política..."
    2 => "0", // Par 2: Usuario seleccionó "Conjunto de elementos mutuamente relacionados..."
    3 => "4", // Par 3: Usuario seleccionó "Proveer soluciones al sector transporte..."
    4 => "2", // Par 4: Usuario seleccionó "Sistema para dirigir y controlar..."
    5 => "3", // Par 5: Usuario seleccionó "El IMT es una institución referente..."
    6 => "5", // Par 6: Usuario seleccionó "Tenemos el compromiso de satisfacer..."
    7 => "6"  // Par 7: Usuario seleccionó "Satisfacer las necesidades y expectativas..."
];

// Obtener datos de la pregunta
$stmt = $conn->prepare("SELECT * FROM preguntas_evaluacion WHERE id = 19");
$stmt->execute();
$pregunta = $stmt->fetch();

$opciones_data = json_decode($pregunta['opciones'], true);
$correctas = json_decode($pregunta['respuesta_correcta'], true);

// Función normalizeAndCompare (copiada del archivo principal)
function normalizeAndCompare($text1, $text2) {
    // Normalizar UTF-8
    $text1 = mb_convert_encoding($text1, 'UTF-8', 'UTF-8');
    $text2 = mb_convert_encoding($text2, 'UTF-8', 'UTF-8');
    
    // Comparación exacta primero
    if ($text1 === $text2) {
        return true;
    }
    
    // Comparación flexible usando similar_text
    $similarity = 0;
    similar_text($text1, $text2, $similarity);
    return $similarity >= 95;
}

echo "=== EVALUACIÓN CON LA NUEVA LÓGICA ===\n";

$total_pares = count($correctas);
$pares_correctos = 0;

foreach ($correctas as $indice => $valor_correcto) {
    $respuesta_estudiante_par = $respuestas_usuario[$indice] ?? null;
    
    // Obtener la definición seleccionada por el estudiante
    $definicion_estudiante = null;
    if ($respuesta_estudiante_par !== null) {
        $pairs = $opciones_data['pairs'] ?? [];
        if (isset($pairs[$respuesta_estudiante_par])) {
            $definicion_estudiante = $pairs[$respuesta_estudiante_par]['right'];
        }
    }
    
    // LÓGICA CORREGIDA: Comparar directamente
    $par_correcto = false;
    if ($definicion_estudiante !== null) {
        $par_correcto = normalizeAndCompare($definicion_estudiante, $valor_correcto);
    }
    
    if ($par_correcto) {
        $pares_correctos++;
    }
    
    echo "\nPar $indice:\n";
    echo "  Término: {$opciones_data['pairs'][$indice]['left']}\n";
    echo "  Respuesta correcta: $valor_correcto\n";
    echo "  Usuario seleccionó índice: $respuesta_estudiante_par\n";
    echo "  Definición seleccionada: $definicion_estudiante\n";
    echo "  Resultado: " . ($par_correcto ? "CORRECTO ✓" : "INCORRECTO ✗") . "\n";
}

echo "\n=== RESULTADO FINAL ===\n";
echo "Pares correctos: $pares_correctos / $total_pares\n";
echo "Porcentaje: " . round(($pares_correctos / $total_pares) * 100, 2) . "%\n";

if ($pares_correctos === $total_pares) {
    echo "¡TODAS LAS RESPUESTAS SON CORRECTAS! ✓\n";
} else {
    echo "Algunas respuestas siguen siendo incorrectas ✗\n";
}
?>