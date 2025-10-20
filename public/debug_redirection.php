<?php
require_once __DIR__ . '/../config/database.php';

echo "<h2>Debug de Redirección - Evaluación</h2>";

// Verificar si hay intentos recientes
$stmt = $conn->prepare("
    SELECT 
        i.*,
        e.titulo as evaluacion_titulo,
        m.titulo as modulo_titulo,
        c.titulo as curso_titulo
    FROM intentos_evaluacion i
    INNER JOIN evaluaciones_modulo e ON i.evaluacion_id = e.id
    INNER JOIN modulos m ON e.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    ORDER BY i.fecha_inicio DESC
    LIMIT 5
");
$stmt->execute();
$intentos = $stmt->fetchAll();

echo "<h3>Últimos 5 intentos de evaluación:</h3>";
if (empty($intentos)) {
    echo "<p>No se encontraron intentos de evaluación.</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Usuario</th><th>Evaluación</th><th>Estado</th><th>Puntaje</th><th>Fecha</th><th>Curso ID</th></tr>";
    foreach ($intentos as $intento) {
        echo "<tr>";
        echo "<td>" . $intento['id'] . "</td>";
        echo "<td>" . $intento['usuario_id'] . "</td>";
        echo "<td>" . htmlspecialchars($intento['evaluacion_titulo']) . "</td>";
        echo "<td>" . $intento['estado'] . "</td>";
        echo "<td>" . ($intento['puntaje_obtenido'] ?? 'NULL') . "</td>";
        echo "<td>" . $intento['fecha_inicio'] . "</td>";
        echo "<td>" . $intento['curso_id'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Verificar si resultado_evaluacion.php existe
$resultado_file = __DIR__ . '/estudiante/resultado_evaluacion.php';
echo "<h3>Verificación de archivos:</h3>";
echo "<p>resultado_evaluacion.php existe: " . (file_exists($resultado_file) ? "SÍ" : "NO") . "</p>";

if (file_exists($resultado_file)) {
    echo "<p>Tamaño del archivo: " . filesize($resultado_file) . " bytes</p>";
    echo "<p>Permisos: " . substr(sprintf('%o', fileperms($resultado_file)), -4) . "</p>";
}

// Verificar BASE_URL
echo "<h3>Configuración:</h3>";
echo "<p>BASE_URL definido: " . (defined('BASE_URL') ? BASE_URL : 'NO DEFINIDO') . "</p>";

// Simular redirección
if (!empty($intentos)) {
    $ultimo_intento = $intentos[0];
    $intento_id = $ultimo_intento['id'];
    $mensaje = "Evaluación completada";
    $tipo = "success";
    
    echo "<h3>URL de redirección simulada:</h3>";
    $redirect_url = (defined('BASE_URL') ? BASE_URL : '/imt-cursos/public') . '/estudiante/resultado_evaluacion.php?intento_id=' . $intento_id . '&mensaje=' . urlencode($mensaje) . '&tipo=' . $tipo;
    echo "<p><strong>URL:</strong> " . htmlspecialchars($redirect_url) . "</p>";
    
    echo "<p><a href='" . htmlspecialchars($redirect_url) . "' target='_blank'>Probar redirección</a></p>";
}

// Verificar logs de error recientes
echo "<h3>Logs de error PHP (últimas líneas):</h3>";
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    $lines = file($error_log);
    $recent_lines = array_slice($lines, -10);
    echo "<pre>" . htmlspecialchars(implode('', $recent_lines)) . "</pre>";
} else {
    echo "<p>No se pudo acceder al log de errores PHP.</p>";
}
?>