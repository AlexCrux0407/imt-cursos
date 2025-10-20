<?php
require_once __DIR__ . '/../config/database.php';

echo "<h2>Debug: Investigación de Evaluación Relacionar Pares</h2>";

// 1. Verificar la pregunta de relacionar_pares
echo "<h3>1. Pregunta de Relacionar Pares (ID: 8)</h3>";
$stmt = $conn->prepare("SELECT * FROM preguntas_evaluacion WHERE evaluacion_id = 8 AND tipo = 'relacionar_pares'");
$stmt->execute();
$pregunta = $stmt->fetch();

if ($pregunta) {
    echo "<strong>Pregunta encontrada:</strong><br>";
    echo "ID: " . $pregunta['id'] . "<br>";
    echo "Pregunta: " . htmlspecialchars($pregunta['pregunta']) . "<br>";
    echo "Opciones: " . htmlspecialchars($pregunta['opciones']) . "<br>";
    echo "Respuesta Correcta: " . htmlspecialchars($pregunta['respuesta_correcta']) . "<br>";
    
    // Decodificar las respuestas correctas
    $correctas = json_decode($pregunta['respuesta_correcta'], true);
    echo "<strong>Respuestas correctas decodificadas:</strong><br>";
    foreach ($correctas as $indice => $valor) {
        echo "Par $indice: '$valor'<br>";
    }
} else {
    echo "No se encontró pregunta de relacionar_pares en evaluación 8<br>";
}

echo "<hr>";

// 2. Verificar los últimos intentos
echo "<h3>2. Últimos Intentos de Evaluación</h3>";
$stmt = $conn->prepare("
    SELECT ie.*, u.nombre, u.email 
    FROM intentos_evaluacion ie 
    JOIN usuarios u ON ie.usuario_id = u.id 
    WHERE ie.evaluacion_id = 8 
    ORDER BY ie.fecha_inicio DESC 
    LIMIT 5
");
$stmt->execute();
$intentos = $stmt->fetchAll();

foreach ($intentos as $intento) {
    echo "<strong>Intento ID: " . $intento['id'] . "</strong><br>";
    echo "Usuario: " . htmlspecialchars($intento['nombre']) . " (" . htmlspecialchars($intento['email']) . ")<br>";
    echo "Número de intento: " . $intento['numero_intento'] . "<br>";
    echo "Puntaje obtenido: " . ($intento['puntaje_obtenido'] ?? 'NULL') . "<br>";
    echo "Estado: " . $intento['estado'] . "<br>";
    echo "Fecha inicio: " . $intento['fecha_inicio'] . "<br>";
    echo "Fecha fin: " . ($intento['fecha_fin'] ?? 'NULL') . "<br>";
    echo "<br>";
}

echo "<hr>";

// 3. Verificar las respuestas del último intento
if (!empty($intentos)) {
    $ultimo_intento = $intentos[0];
    echo "<h3>3. Respuestas del Último Intento (ID: " . $ultimo_intento['id'] . ")</h3>";
    
    $stmt = $conn->prepare("
        SELECT * FROM respuestas_estudiante 
        WHERE intento_id = ? 
        ORDER BY pregunta_id
    ");
    $stmt->execute([$ultimo_intento['id']]);
    $respuestas = $stmt->fetchAll();
    
    echo "<strong>Total de respuestas encontradas: " . count($respuestas) . "</strong><br><br>";
    
    foreach ($respuestas as $respuesta) {
        echo "<strong>Respuesta ID: " . $respuesta['id'] . "</strong><br>";
        echo "Pregunta ID: " . $respuesta['pregunta_id'] . "<br>";
        echo "Respuesta: " . htmlspecialchars($respuesta['respuesta']) . "<br>";
        echo "Es correcta: " . ($respuesta['es_correcta'] === null ? 'NULL' : ($respuesta['es_correcta'] ? 'SÍ' : 'NO')) . "<br>";
        echo "Requiere revisión: " . ($respuesta['requiere_revision'] ? 'SÍ' : 'NO') . "<br>";
        echo "Fecha: " . $respuesta['fecha_respuesta'] . "<br>";
        
        // Si es una respuesta de par individual, decodificar
        if (strpos($respuesta['pregunta_id'], '_par_') !== false) {
            $datos_par = json_decode($respuesta['respuesta'], true);
            if ($datos_par) {
                echo "<em>Datos del par:</em><br>";
                echo "&nbsp;&nbsp;Índice: " . $datos_par['par_indice'] . "<br>";
                echo "&nbsp;&nbsp;Respuesta estudiante: '" . $datos_par['respuesta_estudiante'] . "'<br>";
                echo "&nbsp;&nbsp;Respuesta correcta: '" . $datos_par['respuesta_correcta'] . "'<br>";
            }
        }
        echo "<br>";
    }
    
    // 4. Calcular estadísticas
    echo "<hr>";
    echo "<h3>4. Estadísticas del Último Intento</h3>";
    
    $respuestas_correctas = 0;
    $total_respuestas = count($respuestas);
    
    foreach ($respuestas as $respuesta) {
        if ($respuesta['es_correcta'] == 1) {
            $respuestas_correctas++;
        }
    }
    
    echo "Respuestas correctas: $respuestas_correctas<br>";
    echo "Total respuestas: $total_respuestas<br>";
    
    if ($total_respuestas > 0) {
        $porcentaje_calculado = ($respuestas_correctas / $total_respuestas) * 100;
        echo "Porcentaje calculado: " . number_format($porcentaje_calculado, 2) . "%<br>";
    }
    
    echo "Puntaje almacenado en BD: " . ($ultimo_intento['puntaje_obtenido'] ?? 'NULL') . "<br>";
}

echo "<hr>";

// 5. Verificar estructura de la pregunta
if ($pregunta) {
    echo "<h3>5. Análisis de Opciones de la Pregunta</h3>";
    $opciones = json_decode($pregunta['opciones'], true);
    $correctas = json_decode($pregunta['respuesta_correcta'], true);
    
    echo "<strong>Opciones decodificadas:</strong><br>";
    if ($opciones && isset($opciones['izquierda']) && isset($opciones['derecha'])) {
        echo "Columna izquierda:<br>";
        foreach ($opciones['izquierda'] as $i => $item) {
            echo "&nbsp;&nbsp;$i: " . htmlspecialchars($item) . "<br>";
        }
        
        echo "Columna derecha:<br>";
        foreach ($opciones['derecha'] as $i => $item) {
            echo "&nbsp;&nbsp;$i: " . htmlspecialchars($item) . "<br>";
        }
        
        echo "<strong>Mapeo correcto esperado:</strong><br>";
        foreach ($correctas as $izq => $der) {
            $item_izq = $opciones['izquierda'][$izq] ?? "Índice $izq no encontrado";
            $item_der = $opciones['derecha'][$der] ?? "Índice $der no encontrado";
            echo "&nbsp;&nbsp;'$item_izq' → '$item_der' (índices: $izq → $der)<br>";
        }
    } else {
        echo "Error: Estructura de opciones no válida<br>";
        echo "Opciones raw: " . htmlspecialchars($pregunta['opciones']) . "<br>";
    }
}

?>