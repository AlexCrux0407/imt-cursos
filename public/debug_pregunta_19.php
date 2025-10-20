<?php
require_once __DIR__ . '/../config/database.php';

echo "<h2>Debug: Investigación Pregunta ID 19</h2>";

// 1. Verificar la pregunta ID 19
echo "<h3>1. Información de la Pregunta ID 19</h3>";
$stmt = $conn->prepare("SELECT * FROM preguntas_evaluacion WHERE id = 19");
$stmt->execute();
$pregunta = $stmt->fetch();

if ($pregunta) {
    echo "<strong>Pregunta encontrada:</strong><br>";
    echo "ID: " . $pregunta['id'] . "<br>";
    echo "Evaluación ID: " . $pregunta['evaluacion_id'] . "<br>";
    echo "Tipo: " . $pregunta['tipo'] . "<br>";
    echo "Pregunta: " . htmlspecialchars($pregunta['pregunta']) . "<br>";
    echo "Opciones: " . htmlspecialchars($pregunta['opciones']) . "<br>";
    echo "Respuesta Correcta: " . htmlspecialchars($pregunta['respuesta_correcta']) . "<br>";
    
    // Decodificar las opciones
    $opciones = json_decode($pregunta['opciones'], true);
    echo "<h4>Opciones decodificadas:</h4>";
    if ($opciones) {
        echo "<pre>" . print_r($opciones, true) . "</pre>";
    } else {
        echo "Error al decodificar opciones<br>";
    }
    
    // Decodificar las respuestas correctas
    $correctas = json_decode($pregunta['respuesta_correcta'], true);
    echo "<h4>Respuestas correctas decodificadas:</h4>";
    if ($correctas) {
        echo "<pre>" . print_r($correctas, true) . "</pre>";
    } else {
        echo "Error al decodificar respuestas correctas<br>";
    }
} else {
    echo "Pregunta ID 19 NO encontrada<br>";
}

echo "<hr>";

// 2. Verificar todas las preguntas de la evaluación 8
echo "<h3>2. Todas las Preguntas de la Evaluación 8</h3>";
$stmt = $conn->prepare("SELECT id, tipo, pregunta FROM preguntas_evaluacion WHERE evaluacion_id = 8 ORDER BY orden");
$stmt->execute();
$preguntas = $stmt->fetchAll();

if ($preguntas) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Tipo</th><th>Pregunta</th></tr>";
    foreach ($preguntas as $p) {
        echo "<tr>";
        echo "<td>" . $p['id'] . "</td>";
        echo "<td>" . $p['tipo'] . "</td>";
        echo "<td>" . htmlspecialchars(substr($p['pregunta'], 0, 100)) . "...</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No se encontraron preguntas para la evaluación 8<br>";
}

echo "<hr>";

// 3. Verificar las respuestas almacenadas para la pregunta 19
echo "<h3>3. Respuestas Almacenadas para Pregunta 19</h3>";
$stmt = $conn->prepare("
    SELECT re.*, ie.usuario_id, u.nombre 
    FROM respuestas_estudiante re 
    JOIN intentos_evaluacion ie ON re.intento_id = ie.id 
    JOIN usuarios u ON ie.usuario_id = u.id 
    WHERE re.pregunta_id = '19' OR re.pregunta_id LIKE '19_par_%'
    ORDER BY re.id DESC 
    LIMIT 10
");
$stmt->execute();
$respuestas = $stmt->fetchAll();

if ($respuestas) {
    foreach ($respuestas as $resp) {
        echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
        echo "<strong>Respuesta ID:</strong> " . $resp['id'] . "<br>";
        echo "<strong>Usuario:</strong> " . htmlspecialchars($resp['nombre']) . "<br>";
        echo "<strong>Pregunta ID:</strong> " . $resp['pregunta_id'] . "<br>";
        echo "<strong>Respuesta:</strong> " . htmlspecialchars($resp['respuesta']) . "<br>";
        echo "<strong>Es correcta:</strong> " . ($resp['es_correcta'] ? 'SÍ' : 'NO') . "<br>";
        echo "<strong>Fecha:</strong> " . $resp['fecha_respuesta'] . "<br>";
        echo "</div>";
    }
} else {
    echo "No se encontraron respuestas para la pregunta 19<br>";
}

echo "<hr>";

// 4. Simular el procesamiento de una respuesta de ejemplo
if ($pregunta && $pregunta['tipo'] === 'relacionar_pares') {
    echo "<h3>4. Simulación de Procesamiento</h3>";
    
    $correctas = json_decode($pregunta['respuesta_correcta'], true);
    $respuesta_ejemplo = json_decode('{"0":"0","1":"1","2":"2","3":"3","4":"4","5":"5","6":"7","7":"6"}', true);
    
    echo "<strong>Respuesta de ejemplo:</strong><br>";
    echo "<pre>" . print_r($respuesta_ejemplo, true) . "</pre>";
    
    echo "<strong>Respuestas correctas:</strong><br>";
    echo "<pre>" . print_r($correctas, true) . "</pre>";
    
    echo "<strong>Comparación par por par:</strong><br>";
    foreach ($correctas as $indice => $valor_correcto) {
        $respuesta_estudiante = $respuesta_ejemplo[$indice] ?? 'NO_EXISTE';
        $es_correcto = isset($respuesta_ejemplo[$indice]) && $respuesta_ejemplo[$indice] == $valor_correcto;
        
        echo "Par $indice: ";
        echo "Estudiante='$respuesta_estudiante', ";
        echo "Correcto='$valor_correcto', ";
        echo "Resultado=" . ($es_correcto ? 'CORRECTO' : 'INCORRECTO') . "<br>";
    }
}

?>