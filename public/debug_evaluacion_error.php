<?php
require_once __DIR__ . '/../config/database.php';

echo "<h2>Debug - Errores de Evaluación</h2>";

// Verificar logs de error PHP
$error_log_paths = [
    ini_get('error_log'),
    'C:\laragon\logs\php_errors.log',
    'C:\laragon\logs\error.log',
    __DIR__ . '/../logs/error.log'
];

echo "<h3>Verificando logs de error:</h3>";
foreach ($error_log_paths as $path) {
    if ($path && file_exists($path)) {
        echo "<h4>Log: $path</h4>";
        $lines = file($path);
        $recent_lines = array_slice($lines, -20); // Últimas 20 líneas
        echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow-y: auto;'>";
        echo htmlspecialchars(implode('', $recent_lines));
        echo "</pre>";
        break;
    }
}

// Verificar si hay errores en la sesión
session_start();
echo "<h3>Información de sesión:</h3>";
echo "<p>Usuario ID: " . ($_SESSION['user_id'] ?? 'NO DEFINIDO') . "</p>";
echo "<p>Rol: " . ($_SESSION['role'] ?? 'NO DEFINIDO') . "</p>";

// Simular el procesamiento paso a paso
echo "<h3>Simulación de procesamiento:</h3>";

try {
    // Paso 1: Verificar datos POST simulados
    $evaluacion_id = 8;
    $usuario_id = $_SESSION['user_id'] ?? 1;
    
    echo "<p>✓ Paso 1: Datos iniciales - Evaluación ID: $evaluacion_id, Usuario ID: $usuario_id</p>";
    
    // Paso 2: Verificar evaluación
    $stmt = $conn->prepare("
        SELECT e.*, m.curso_id
        FROM evaluaciones_modulo e
        INNER JOIN modulos m ON e.modulo_id = m.id
        WHERE e.id = :evaluacion_id AND e.activo = 1
    ");
    $stmt->execute([':evaluacion_id' => $evaluacion_id]);
    $evaluacion = $stmt->fetch();
    
    if (!$evaluacion) {
        throw new Exception('Evaluación no encontrada');
    }
    
    echo "<p>✓ Paso 2: Evaluación encontrada - Curso ID: " . $evaluacion['curso_id'] . "</p>";
    
    // Paso 3: Verificar inscripción
    $stmt = $conn->prepare("
        SELECT id FROM inscripciones 
        WHERE usuario_id = :usuario_id AND curso_id = :curso_id AND estado = 'activo'
    ");
    $stmt->execute([':usuario_id' => $usuario_id, ':curso_id' => $evaluacion['curso_id']]);
    if (!$stmt->fetch()) {
        throw new Exception('No tienes acceso a esta evaluación');
    }
    
    echo "<p>✓ Paso 3: Inscripción verificada</p>";
    
    // Paso 4: Verificar intentos
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_intentos, MAX(puntaje_obtenido) as mejor_puntaje
        FROM intentos_evaluacion
        WHERE usuario_id = :usuario_id AND evaluacion_id = :evaluacion_id
    ");
    $stmt->execute([':usuario_id' => $usuario_id, ':evaluacion_id' => $evaluacion_id]);
    $resultado_puntaje = $stmt->fetch();
    
    echo "<p>✓ Paso 4: Intentos verificados - Total: " . $resultado_puntaje['total_intentos'] . ", Mejor: " . ($resultado_puntaje['mejor_puntaje'] ?? 'NULL') . "</p>";
    
    // Paso 5: Verificar preguntas
    $stmt = $conn->prepare("
        SELECT * FROM preguntas_evaluacion
        WHERE evaluacion_id = :evaluacion_id
        ORDER BY orden ASC
    ");
    $stmt->execute([':evaluacion_id' => $evaluacion_id]);
    $preguntas = $stmt->fetchAll();
    
    if (empty($preguntas)) {
        throw new Exception('La evaluación no tiene preguntas configuradas');
    }
    
    echo "<p>✓ Paso 5: Preguntas encontradas - Total: " . count($preguntas) . "</p>";
    
    // Paso 6: Verificar BASE_URL
    echo "<p>✓ Paso 6: BASE_URL definido como: " . (defined('BASE_URL') ? BASE_URL : 'NO DEFINIDO') . "</p>";
    
    // Paso 7: Simular URL de redirección
    $intento_id = 999; // ID simulado
    $mensaje = "Evaluación completada";
    $tipo = "success";
    $redirect_url = BASE_URL . '/estudiante/resultado_evaluacion.php?intento_id=' . $intento_id . '&mensaje=' . urlencode($mensaje) . '&tipo=' . $tipo;
    
    echo "<p>✓ Paso 7: URL de redirección generada:</p>";
    echo "<p style='background: #e7f3ff; padding: 10px; word-break: break-all;'>" . htmlspecialchars($redirect_url) . "</p>";
    
    // Verificar si el archivo de destino existe
    $resultado_file = __DIR__ . '/estudiante/resultado_evaluacion.php';
    echo "<p>✓ Paso 8: Archivo destino existe: " . (file_exists($resultado_file) ? "SÍ" : "NO") . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error en paso: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Formulario para probar manualmente
echo "<h3>Prueba manual:</h3>";
echo "<form method='POST' action='estudiante/procesar_intento_evaluacion.php' target='_blank'>";
echo "<input type='hidden' name='evaluacion_id' value='8'>";
echo "<input type='hidden' name='respuesta_19' value='[0,1,2,3,4,5,6,7]'>";
echo "<button type='submit' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px;'>Enviar Evaluación de Prueba</button>";
echo "</form>";
echo "<p><small>Esto abrirá en una nueva pestaña para ver exactamente qué sucede.</small></p>";
?>