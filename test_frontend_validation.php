<?php
require_once __DIR__ . '/config/database.php';

echo "=== ANÁLISIS DE VALIDACIÓN DEL FRONTEND ===\n\n";

// Obtener la pregunta 19 para analizar su estructura
$stmt = $conn->prepare("SELECT * FROM preguntas_evaluacion WHERE id = 19");
$stmt->execute();
$pregunta = $stmt->fetch();

$opciones = json_decode($pregunta['opciones'], true);
$correctas = json_decode($pregunta['respuesta_correcta'], true);

echo "=== ESTRUCTURA DE LA PREGUNTA ===\n";
echo "Total de pares: " . count($opciones['pairs']) . "\n";
echo "Total de respuestas correctas esperadas: " . count($correctas) . "\n\n";

// Analizar las respuestas más recientes para ver patrones
$stmt = $conn->prepare("
    SELECT r.*, ie.usuario_id, ie.fecha_inicio
    FROM respuestas_estudiante r
    JOIN intentos_evaluacion ie ON r.intento_id = ie.id
    WHERE r.pregunta_id = 19 
    ORDER BY r.fecha_respuesta DESC 
    LIMIT 16
");
$stmt->execute();
$respuestas = $stmt->fetchAll();

// Agrupar respuestas por intento
$intentos = [];
foreach ($respuestas as $respuesta) {
    $data = json_decode($respuesta['respuesta'], true);
    $intento_id = $respuesta['intento_id'];
    
    if (!isset($intentos[$intento_id])) {
        $intentos[$intento_id] = [
            'usuario_id' => $respuesta['usuario_id'],
            'fecha' => $respuesta['fecha_respuesta'],
            'pares' => []
        ];
    }
    
    $intentos[$intento_id]['pares'][$data['par_indice']] = [
        'indice_seleccionado' => $data['respuesta_estudiante_indice'],
        'texto_seleccionado' => $data['respuesta_estudiante_texto'],
        'es_correcto' => $data['par_correcto']
    ];
}

echo "=== ANÁLISIS DE INTENTOS RECIENTES ===\n";
foreach ($intentos as $intento_id => $intento) {
    echo "\nIntento ID: $intento_id (Usuario: {$intento['usuario_id']})\n";
    echo "Fecha: {$intento['fecha']}\n";
    
    $pares_respondidos = 0;
    $pares_null = 0;
    $pares_correctos = 0;
    
    for ($i = 0; $i < count($correctas); $i++) {
        if (isset($intento['pares'][$i])) {
            $par = $intento['pares'][$i];
            if ($par['indice_seleccionado'] !== null) {
                $pares_respondidos++;
                if ($par['es_correcto']) {
                    $pares_correctos++;
                }
            } else {
                $pares_null++;
            }
            echo "  Par $i: " . ($par['indice_seleccionado'] ?? 'NULL') . " - " . ($par['es_correcto'] ? 'CORRECTO' : 'INCORRECTO') . "\n";
        } else {
            echo "  Par $i: NO ENCONTRADO\n";
        }
    }
    
    echo "  Resumen: $pares_respondidos respondidos, $pares_null null, $pares_correctos correctos\n";
    
    if ($pares_null > 0) {
        echo "  ⚠️  PROBLEMA: Este intento tiene pares sin responder\n";
    }
}

echo "\n=== RECOMENDACIONES ===\n";
echo "1. Verificar que la validación del frontend requiera TODOS los pares respondidos\n";
echo "2. Considerar si se debe permitir envío parcial o requerir completitud\n";
echo "3. Mejorar la UX para indicar claramente qué pares faltan por responder\n";
?>