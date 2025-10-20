<?php
require_once __DIR__ . '/config/database.php';

echo "=== ANÁLISIS DE RESPUESTAS NULL ===\n\n";

// Obtener las respuestas más recientes de la pregunta 19
$stmt = $conn->prepare("
    SELECT r.*, r.respuesta as respuesta_json
    FROM respuestas_estudiante r
    WHERE r.pregunta_id = 19 
    ORDER BY r.fecha_respuesta DESC 
    LIMIT 20
");
$stmt->execute();
$respuestas = $stmt->fetchAll();

echo "=== RESPUESTAS RECIENTES ===\n";
foreach ($respuestas as $respuesta) {
    $data = json_decode($respuesta['respuesta_json'], true);
    
    echo "ID: {$respuesta['id']} | ";
    echo "Par: {$data['par_indice']} | ";
    echo "Índice seleccionado: " . ($data['respuesta_estudiante_indice'] ?? 'NULL') . " | ";
    echo "Texto: " . (isset($data['respuesta_estudiante_texto']) ? substr($data['respuesta_estudiante_texto'], 0, 50) . '...' : 'NULL') . " | ";
    echo "Correcto: " . ($data['par_correcto'] ? 'SÍ' : 'NO') . "\n";
}

echo "\n=== ANÁLISIS DE PATRONES ===\n";

// Contar respuestas null vs no-null
$null_count = 0;
$valid_count = 0;
$pares_con_null = [];

foreach ($respuestas as $respuesta) {
    $data = json_decode($respuesta['respuesta_json'], true);
    
    if ($data['respuesta_estudiante_indice'] === null) {
        $null_count++;
        $pares_con_null[] = $data['par_indice'];
    } else {
        $valid_count++;
    }
}

echo "Respuestas con índice NULL: $null_count\n";
echo "Respuestas con índice válido: $valid_count\n";
echo "Pares que tienen respuestas NULL: " . implode(', ', array_unique($pares_con_null)) . "\n";

echo "\n=== VERIFICAR ESTRUCTURA DE LA PREGUNTA ===\n";

// Obtener la estructura de la pregunta
$stmt = $conn->prepare("SELECT opciones FROM preguntas_evaluacion WHERE id = 19");
$stmt->execute();
$pregunta = $stmt->fetch();
$opciones = json_decode($pregunta['opciones'], true);

echo "Total de pares en la pregunta: " . count($opciones['pairs']) . "\n";
echo "Pares disponibles:\n";
foreach ($opciones['pairs'] as $index => $pair) {
    echo "  $index: {$pair['left']} -> " . substr($pair['right'], 0, 50) . "...\n";
}

echo "\n=== POSIBLES CAUSAS ===\n";
echo "1. El estudiante no seleccionó ninguna opción para algunos pares\n";
echo "2. Hay un problema en el JavaScript del frontend que no envía todos los datos\n";
echo "3. Hay un problema en el procesamiento del backend\n";

// Verificar si hay intentos incompletos
$stmt = $conn->prepare("
    SELECT ie.id, ie.usuario_id, ie.fecha_inicio, ie.fecha_fin, ie.estado,
           COUNT(re.id) as respuestas_guardadas
    FROM intentos_evaluacion ie
    LEFT JOIN respuestas_estudiante re ON ie.id = re.intento_id AND re.pregunta_id = 19
    WHERE ie.evaluacion_id IN (SELECT id FROM evaluaciones WHERE modulo_id = 2)
    GROUP BY ie.id
    ORDER BY ie.fecha_inicio DESC
    LIMIT 10
");
$stmt->execute();
$intentos = $stmt->fetchAll();

echo "\n=== INTENTOS RECIENTES ===\n";
foreach ($intentos as $intento) {
    echo "Intento ID: {$intento['id']} | ";
    echo "Usuario: {$intento['usuario_id']} | ";
    echo "Estado: {$intento['estado']} | ";
    echo "Respuestas guardadas: {$intento['respuestas_guardadas']} | ";
    echo "Fecha: {$intento['fecha_inicio']}\n";
}
?>