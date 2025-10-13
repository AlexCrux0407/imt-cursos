<?php
// Script de debug para probar el flujo de evaluación
require_once __DIR__ . '/config/database.php';

echo "<h2>Debug del flujo de evaluación</h2>";

// 1. Verificar si hay evaluaciones disponibles
echo "<h3>1. Evaluaciones disponibles:</h3>";
$stmt = $conn->prepare("
    SELECT e.id, e.titulo, m.titulo as modulo, c.titulo as curso
    FROM evaluaciones_modulo e
    INNER JOIN modulos m ON e.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE e.activo = 1
    LIMIT 5
");
$stmt->execute();
$evaluaciones = $stmt->fetchAll();

if (empty($evaluaciones)) {
    echo "<p style='color: red;'>❌ No hay evaluaciones activas en el sistema</p>";
} else {
    echo "<ul>";
    foreach ($evaluaciones as $eval) {
        echo "<li>ID: {$eval['id']} - {$eval['titulo']} (Módulo: {$eval['modulo']}, Curso: {$eval['curso']})</li>";
    }
    echo "</ul>";
}

// 2. Verificar si hay usuarios estudiantes
echo "<h3>2. Usuarios estudiantes:</h3>";
$stmt = $conn->prepare("SELECT id, nombre, email FROM usuarios WHERE rol = 'estudiante' LIMIT 5");
$stmt->execute();
$estudiantes = $stmt->fetchAll();

if (empty($estudiantes)) {
    echo "<p style='color: red;'>❌ No hay estudiantes en el sistema</p>";
} else {
    echo "<ul>";
    foreach ($estudiantes as $est) {
        echo "<li>ID: {$est['id']} - {$est['nombre']} ({$est['email']})</li>";
    }
    echo "</ul>";
}

// 3. Verificar intentos de evaluación recientes
echo "<h3>3. Intentos de evaluación recientes:</h3>";
$stmt = $conn->prepare("
    SELECT ie.*, u.nombre, e.titulo as evaluacion
    FROM intentos_evaluacion ie
    INNER JOIN usuarios u ON ie.usuario_id = u.id
    INNER JOIN evaluaciones_modulo e ON ie.evaluacion_id = e.id
    ORDER BY ie.fecha_inicio DESC
    LIMIT 10
");
$stmt->execute();
$intentos = $stmt->fetchAll();

if (empty($intentos)) {
    echo "<p style='color: orange;'>⚠️ No hay intentos de evaluación registrados</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Usuario</th><th>Evaluación</th><th>Fecha</th><th>Completado</th><th>Puntaje</th></tr>";
    foreach ($intentos as $intento) {
        $completado = $intento['fecha_completado'] ? '✅' : '⏳';
        $puntaje = $intento['puntaje_obtenido'] ?? 'Pendiente';
        echo "<tr>";
        echo "<td>{$intento['id']}</td>";
        echo "<td>{$intento['nombre']}</td>";
        echo "<td>{$intento['evaluacion']}</td>";
        echo "<td>{$intento['fecha_inicio']}</td>";
        echo "<td>{$completado}</td>";
        echo "<td>{$puntaje}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 4. Verificar configuración de BASE_URL
echo "<h3>4. Configuración BASE_URL:</h3>";
echo "<p>BASE_URL definida como: <strong>" . (defined('BASE_URL') ? BASE_URL : 'NO DEFINIDA') . "</strong></p>";

// 5. Verificar logs de error de PHP
echo "<h3>5. Verificar logs de error:</h3>";
$error_log = ini_get('error_log');
echo "<p>Archivo de log de errores: <strong>$error_log</strong></p>";

if (file_exists($error_log)) {
    $recent_errors = shell_exec("tail -20 $error_log 2>/dev/null");
    if ($recent_errors) {
        echo "<pre style='background: #f0f0f0; padding: 10px; max-height: 300px; overflow-y: scroll;'>$recent_errors</pre>";
    } else {
        echo "<p>No se pudieron leer los logs recientes</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠️ Archivo de log no encontrado o no accesible</p>";
}

echo "<hr>";
echo "<p><strong>Instrucciones:</strong></p>";
echo "<ol>";
echo "<li>Inicia sesión como estudiante</li>";
echo "<li>Ve a un curso e intenta tomar una evaluación</li>";
echo "<li>Completa la evaluación y envía las respuestas</li>";
echo "<li>Observa si aparecen mensajes de debug en los logs del servidor</li>";
echo "</ol>";
?>