<?php
require_once __DIR__ . '/config/database.php';

echo "=== DEBUG EVALUACIÓN EN TIEMPO REAL ===\n\n";

$usuario_id = 7;
$curso_id = 4; // curso ejemplo

echo "Timestamp actual: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Estado actual en progreso_modulos
echo "=== ESTADO EN PROGRESO_MODULOS ===\n";
$stmt = $conn->prepare("
    SELECT pm.*, m.titulo as modulo_titulo
    FROM progreso_modulos pm
    INNER JOIN modulos m ON pm.modulo_id = m.id
    WHERE pm.usuario_id = ? AND m.curso_id = ?
    ORDER BY m.orden
");
$stmt->execute([$usuario_id, $curso_id]);
$progreso_modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($progreso_modulos as $pm) {
    echo "Módulo: {$pm['modulo_titulo']} (ID: {$pm['modulo_id']})\n";
    echo "  Completado: " . ($pm['completado'] ? 'SÍ' : 'NO') . "\n";
    echo "  Evaluación completada: " . ($pm['evaluacion_completada'] ? 'SÍ' : 'NO') . "\n";
    echo "  Puntaje: {$pm['puntaje_evaluacion']}\n";
    echo "  Fecha completado: {$pm['fecha_completado']}\n";
    echo "  Fecha evaluación: {$pm['fecha_evaluacion_completada']}\n\n";
}

// 2. Estado en inscripciones
echo "=== ESTADO EN INSCRIPCIONES ===\n";
$stmt = $conn->prepare("
    SELECT progreso, estado, fecha_inscripcion, fecha_completado
    FROM inscripciones
    WHERE usuario_id = ? AND curso_id = ?
");
$stmt->execute([$usuario_id, $curso_id]);
$inscripcion = $stmt->fetch(PDO::FETCH_ASSOC);

if ($inscripcion) {
    echo "Progreso: {$inscripcion['progreso']}%\n";
    echo "Estado: {$inscripcion['estado']}\n";
    echo "Fecha inscripción: {$inscripcion['fecha_inscripcion']}\n";
    echo "Fecha completado: " . ($inscripcion['fecha_completado'] ?? 'N/A') . "\n\n";
}

// 3. Últimos intentos de evaluación
echo "=== ÚLTIMOS INTENTOS DE EVALUACIÓN ===\n";
$stmt = $conn->prepare("
    SELECT ie.*, e.titulo as evaluacion_titulo, m.titulo as modulo_titulo
    FROM intentos_evaluacion ie
    INNER JOIN evaluaciones_modulo e ON ie.evaluacion_id = e.id
    INNER JOIN modulos m ON e.modulo_id = m.id
    WHERE ie.usuario_id = ? AND m.curso_id = ?
    ORDER BY ie.fecha_inicio DESC
    LIMIT 5
");
$stmt->execute([$usuario_id, $curso_id]);
$intentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($intentos as $intento) {
    echo "Evaluación: {$intento['evaluacion_titulo']} ({$intento['modulo_titulo']})\n";
    echo "  Fecha: {$intento['fecha_inicio']}\n";
    echo "  Puntaje: {$intento['puntaje_obtenido']}%\n";
    echo "  Estado: {$intento['estado']}\n\n";
}

// 4. Verificar si hay evaluaciones pendientes de revisión
echo "=== EVALUACIONES PENDIENTES DE REVISIÓN ===\n";
$stmt = $conn->prepare("
    SELECT ie.*, e.titulo as evaluacion_titulo, m.titulo as modulo_titulo
    FROM intentos_evaluacion ie
    INNER JOIN evaluaciones_modulo e ON ie.evaluacion_id = e.id
    INNER JOIN modulos m ON e.modulo_id = m.id
    WHERE ie.usuario_id = ? AND m.curso_id = ? AND ie.estado = 'en_revision'
    ORDER BY ie.fecha_inicio DESC
");
$stmt->execute([$usuario_id, $curso_id]);
$pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($pendientes)) {
    echo "No hay evaluaciones pendientes de revisión.\n\n";
} else {
    foreach ($pendientes as $pendiente) {
        echo "Evaluación: {$pendiente['evaluacion_titulo']} ({$pendiente['modulo_titulo']})\n";
        echo "  Fecha intento: {$pendiente['fecha_inicio']}\n";
        echo "  Estado: {$pendiente['estado']}\n\n";
    }
}

// 5. Simular la consulta que hace la sidebar
echo "=== CONSULTA SIMULADA DE LA SIDEBAR ===\n";
$stmt = $conn->prepare("
    SELECT m.id AS modulo_id, m.titulo AS modulo_titulo, m.orden AS modulo_orden,
           t.id AS tema_id, t.titulo AS tema_titulo, t.orden AS tema_orden,
           s.id AS subtema_id, s.titulo AS subtema_titulo, s.orden AS subtema_orden,
           l.id AS leccion_id, l.titulo AS leccion_titulo, l.orden AS leccion_orden,
           IF(pl.id IS NULL, 0, 1) AS leccion_completada,
           IF(pm.evaluacion_completada = 1, 1, 0) AS evaluacion_completada
    FROM modulos m
    LEFT JOIN temas t ON m.id = t.modulo_id
    LEFT JOIN subtemas s ON t.id = s.tema_id
    LEFT JOIN lecciones l ON s.id = l.subtema_id
    LEFT JOIN progreso_lecciones pl ON l.id = pl.leccion_id AND pl.usuario_id = ?
    LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = ?
    WHERE m.curso_id = ?
    ORDER BY m.orden, t.orden, s.orden, l.orden
");
$stmt->execute([$usuario_id, $usuario_id, $curso_id]);
$sidebar_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$modulos_sidebar = [];
foreach ($sidebar_data as $row) {
    $mid = (int)$row['modulo_id'];
    if (!isset($modulos_sidebar[$mid])) {
        $modulos_sidebar[$mid] = [
            'id' => $mid,
            'titulo' => $row['modulo_titulo'],
            'orden' => (int)$row['modulo_orden'],
            'evaluacion_completada' => (bool)$row['evaluacion_completada']
        ];
    }
}

foreach ($modulos_sidebar as $modulo) {
    echo "Módulo sidebar: {$modulo['titulo']} (ID: {$modulo['id']})\n";
    echo "  Evaluación completada: " . ($modulo['evaluacion_completada'] ? 'SÍ' : 'NO') . "\n\n";
}

echo "=== FIN DEL DEBUG ===\n";
?>