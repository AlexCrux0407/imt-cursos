<?php
require_once __DIR__ . '/config/database.php';

echo "<h2>Debug Error Procesar Pregunta</h2>";

// Simular datos POST como los que se envían
$_POST = [
    'evaluacion_id' => 5,
    'modulo_id' => 2,
    'curso_id' => 3,
    'pregunta_id' => 0,
    'pregunta' => 'Pregunta de prueba',
    'tipo' => 'completar_espacios',
    'puntaje' => 1,
    'orden' => 1,
    'explicacion' => 'Explicación de prueba',
    'texto_completar' => 'El agua hierve a ___ grados centígrados',
    'blancos_respuestas' => ['100']
];

// Simular sesión
$_SESSION['user_id'] = 1;

echo "<h3>1. Datos POST simulados:</h3>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

// Procesar variables como en el archivo original
$evaluacion_id = (int)($_POST['evaluacion_id'] ?? 0);
$modulo_id = (int)($_POST['modulo_id'] ?? 0);
$curso_id = (int)($_POST['curso_id'] ?? 0);
$pregunta_id = (int)($_POST['pregunta_id'] ?? 0);
$pregunta = trim($_POST['pregunta'] ?? '');
$tipo = $_POST['tipo'] ?? 'multiple_choice';
$puntaje = (float)($_POST['puntaje'] ?? 1);
$orden = (int)($_POST['orden'] ?? 1);
$explicacion = trim($_POST['explicacion'] ?? '');

echo "<h3>2. Variables procesadas:</h3>";
echo "evaluacion_id: $evaluacion_id<br>";
echo "modulo_id: $modulo_id<br>";
echo "curso_id: $curso_id<br>";
echo "pregunta_id: $pregunta_id<br>";
echo "pregunta: $pregunta<br>";
echo "tipo: $tipo<br>";
echo "puntaje: $puntaje<br>";
echo "orden: $orden<br>";
echo "explicacion: $explicacion<br>";

// Verificar que la evaluación existe
echo "<h3>3. Verificando evaluación:</h3>";
try {
    $stmt = $conn->prepare("SELECT * FROM evaluaciones_modulo WHERE id = :evaluacion_id");
    $stmt->execute([':evaluacion_id' => $evaluacion_id]);
    $evaluacion = $stmt->fetch();
    
    if ($evaluacion) {
        echo "✓ Evaluación encontrada: " . $evaluacion['titulo'] . "<br>";
    } else {
        echo "✗ Evaluación NO encontrada<br>";
    }
} catch (Exception $e) {
    echo "✗ Error verificando evaluación: " . $e->getMessage() . "<br>";
}

// Verificar permisos del docente
echo "<h3>4. Verificando permisos:</h3>";
try {
    $stmt = $conn->prepare("
        SELECT e.id FROM evaluaciones_modulo e
        INNER JOIN modulos m ON e.modulo_id = m.id
        INNER JOIN cursos c ON m.curso_id = c.id
        WHERE e.id = :evaluacion_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id)
    ");
    $stmt->execute([
        ':evaluacion_id' => $evaluacion_id, 
        ':docente_id' => $_SESSION['user_id']
    ]);
    
    if ($stmt->fetch()) {
        echo "✓ Permisos verificados correctamente<br>";
    } else {
        echo "✗ Sin permisos para esta evaluación<br>";
    }
} catch (Exception $e) {
    echo "✗ Error verificando permisos: " . $e->getMessage() . "<br>";
}

// Procesar opciones según el tipo
echo "<h3>5. Procesando opciones para tipo '$tipo':</h3>";
$opciones = null;
$respuesta_correcta = null;

switch ($tipo) {
    case 'completar_espacios':
        $texto = trim($_POST['texto_completar'] ?? '');
        $respuestas = $_POST['blancos_respuestas'] ?? [];
        $opciones = json_encode(['texto' => $texto, 'blancos' => count($respuestas)]);
        $respuesta_correcta = json_encode(array_values($respuestas));
        echo "Texto: $texto<br>";
        echo "Respuestas: " . print_r($respuestas, true) . "<br>";
        echo "Opciones JSON: $opciones<br>";
        echo "Respuesta correcta JSON: $respuesta_correcta<br>";
        break;
    default:
        echo "Tipo no manejado en este debug<br>";
}

// Probar la inserción
echo "<h3>6. Probando inserción:</h3>";
try {
    $stmt = $conn->prepare("
        INSERT INTO preguntas_evaluacion (
            evaluacion_id, pregunta, tipo, opciones, respuesta_correcta, 
            puntaje, orden, explicacion
        ) VALUES (
            :evaluacion_id, :pregunta, :tipo, :opciones, :respuesta_correcta,
            :puntaje, :orden, :explicacion
        )
    ");
    
    $params = [
        ':evaluacion_id' => $evaluacion_id,
        ':pregunta' => $pregunta,
        ':tipo' => $tipo,
        ':opciones' => $opciones,
        ':respuesta_correcta' => $respuesta_correcta,
        ':puntaje' => $puntaje,
        ':orden' => $orden,
        ':explicacion' => $explicacion
    ];
    
    echo "Parámetros para inserción:<br>";
    echo "<pre>";
    print_r($params);
    echo "</pre>";
    
    $stmt->execute($params);
    echo "✓ Inserción exitosa. ID: " . $conn->lastInsertId() . "<br>";
    
} catch (Exception $e) {
    echo "✗ Error en inserción: " . $e->getMessage() . "<br>";
    echo "Código de error: " . $e->getCode() . "<br>";
}

// Verificar estructura de la tabla
echo "<h3>7. Estructura de la tabla preguntas_evaluacion:</h3>";
try {
    $stmt = $conn->query("DESCRIBE preguntas_evaluacion");
    echo "<table border='1'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error describiendo tabla: " . $e->getMessage();
}
?>