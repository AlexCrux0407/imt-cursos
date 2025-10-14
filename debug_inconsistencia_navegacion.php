<?php
require_once __DIR__ . '/config/database.php';

echo "=== DEBUG: INCONSISTENCIA DE NAVEGACIÓN ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

$usuario_id = 7;
$curso_id = 4; // curso-ejemplo

echo "Usuario ID: $usuario_id\n";
echo "Curso ID: $curso_id (curso-ejemplo)\n\n";

// 1. Estado desde "mis cursos"
echo "1. ESTADO DESDE 'MIS CURSOS':\n";
echo "================================\n";

$stmt = $conn->prepare("
    SELECT c.*, i.progreso, i.fecha_inscripcion, i.estado as estado_inscripcion,
           u.nombre as docente_nombre,
           COUNT(DISTINCT m.id) as total_modulos,
           COUNT(DISTINCT CASE WHEN pm.completado = 1 THEN m.id END) as modulos_completados,
           COUNT(DISTINCT e.id) as total_evaluaciones,
           COUNT(DISTINCT CASE WHEN pm.evaluacion_completada = 1 THEN e.id END) as evaluaciones_completadas
    FROM inscripciones i
    INNER JOIN cursos c ON i.curso_id = c.id
    LEFT JOIN usuarios u ON c.creado_por = u.id
    LEFT JOIN modulos m ON c.id = m.curso_id
    LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = i.usuario_id
    LEFT JOIN evaluaciones_modulo e ON m.id = e.modulo_id AND e.activo = 1
    WHERE c.id = :curso_id AND i.usuario_id = :usuario_id
    GROUP BY c.id, i.progreso, i.fecha_inscripcion, i.estado, u.nombre
");
$stmt->execute([':curso_id' => $curso_id, ':usuario_id' => $usuario_id]);
$curso_mis_cursos = $stmt->fetch();

if ($curso_mis_cursos) {
    echo "Progreso general: {$curso_mis_cursos['progreso']}%\n";
    echo "Total módulos: {$curso_mis_cursos['total_modulos']}\n";
    echo "Módulos completados: {$curso_mis_cursos['modulos_completados']}\n";
    echo "Total evaluaciones: {$curso_mis_cursos['total_evaluaciones']}\n";
    echo "Evaluaciones completadas: {$curso_mis_cursos['evaluaciones_completadas']}\n";
} else {
    echo "No se encontró el curso en 'mis cursos'\n";
}

echo "\n";

// 2. Estado desde "curso_contenido.php"
echo "2. ESTADO DESDE 'CURSO_CONTENIDO.PHP':\n";
echo "=====================================\n";

// Obtener información del curso
$stmt = $conn->prepare("
    SELECT c.*, i.progreso, i.fecha_inscripcion, i.estado AS estado_inscripcion,
           u.nombre AS docente_nombre
    FROM cursos c
    INNER JOIN inscripciones i ON c.id = i.curso_id
    LEFT JOIN usuarios u ON c.asignado_a = u.id
    WHERE c.id = :curso_id AND i.usuario_id = :usuario_id
    LIMIT 1
");
$stmt->execute([':curso_id' => $curso_id, ':usuario_id' => $usuario_id]);
$curso_contenido = $stmt->fetch();

if ($curso_contenido) {
    echo "Progreso general: {$curso_contenido['progreso']}%\n";
} else {
    echo "No se encontró el curso en 'curso_contenido'\n";
}

// Estructura del curso para la sidebar
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
    LEFT JOIN progreso_lecciones pl ON l.id = pl.leccion_id AND pl.usuario_id = :usuario_id1
    LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = :usuario_id2
    WHERE m.curso_id = :curso_id
    ORDER BY m.orden, t.orden, s.orden, l.orden
");
$stmt->execute([
    ':curso_id' => $curso_id, 
    ':usuario_id1' => $usuario_id,
    ':usuario_id2' => $usuario_id
]);
$rows = $stmt->fetchAll();

$curso_estructura = [];
foreach ($rows as $row) {
    $mid = (int)$row['modulo_id'];
    if (!isset($curso_estructura[$mid])) {
        $curso_estructura[$mid] = [
            'id' => $mid,
            'titulo' => $row['modulo_titulo'],
            'orden' => (int)$row['modulo_orden'],
            'temas' => [],
            'total_lecciones' => 0,
            'lecciones_completadas' => 0,
            'evaluacion_completada' => (bool)$row['evaluacion_completada']
        ];
    }

    if (!empty($row['leccion_id'])) {
        $curso_estructura[$mid]['total_lecciones']++;
        if ($row['leccion_completada']) {
            $curso_estructura[$mid]['lecciones_completadas']++;
        }
    }
}

echo "\nEstructura de módulos:\n";
foreach ($curso_estructura as $modulo) {
    echo "- Módulo {$modulo['orden']}: {$modulo['titulo']}\n";
    echo "  Evaluación completada: " . ($modulo['evaluacion_completada'] ? 'SÍ' : 'NO') . "\n";
    echo "  Lecciones: {$modulo['lecciones_completadas']}/{$modulo['total_lecciones']}\n";
}

echo "\n";

// 3. Lógica de desbloqueo
echo "3. LÓGICA DE DESBLOQUEO:\n";
echo "========================\n";

// Función para verificar si un módulo puede ser accedido
$puedeAcceder = function($estructura, $moduloId) {
    $modulos = array_values($estructura);
    usort($modulos, fn($a, $b) => $a['orden'] <=> $b['orden']);

    foreach ($modulos as $i => $mod) {
        if ($mod['id'] == $moduloId) {
            if ($i == 0) return true; // Primer módulo siempre accesible
            $anterior = $modulos[$i - 1];
            // Un módulo es accesible si el anterior tiene su evaluación completada
            return isset($anterior['evaluacion_completada']) && $anterior['evaluacion_completada'];
        }
    }
    return false;
};

foreach ($curso_estructura as $modulo) {
    $accesible = $puedeAcceder($curso_estructura, $modulo['id']);
    echo "- Módulo {$modulo['orden']}: " . ($accesible ? 'ACCESIBLE' : 'BLOQUEADO') . "\n";
}

echo "\n";

// 4. Verificar progreso_modulos directamente
echo "4. ESTADO EN PROGRESO_MODULOS:\n";
echo "==============================\n";

$stmt = $conn->prepare("
    SELECT m.id, m.titulo, m.orden,
           pm.completado, pm.evaluacion_completada, pm.puntaje_evaluacion,
           pm.fecha_completado, pm.fecha_evaluacion_completada
    FROM modulos m
    LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = :usuario_id
    WHERE m.curso_id = :curso_id
    ORDER BY m.orden
");
$stmt->execute([':curso_id' => $curso_id, ':usuario_id' => $usuario_id]);
$progreso_modulos = $stmt->fetchAll();

foreach ($progreso_modulos as $pm) {
    echo "- Módulo {$pm['orden']}: {$pm['titulo']}\n";
    echo "  Completado: " . ($pm['completado'] ? 'SÍ' : 'NO') . "\n";
    echo "  Evaluación completada: " . ($pm['evaluacion_completada'] ? 'SÍ' : 'NO') . "\n";
    echo "  Puntaje: " . ($pm['puntaje_evaluacion'] ?? 'N/A') . "\n";
    echo "  Fecha completado: " . ($pm['fecha_completado'] ?? 'N/A') . "\n";
    echo "  Fecha evaluación: " . ($pm['fecha_evaluacion_completada'] ?? 'N/A') . "\n";
    echo "\n";
}

echo "=== FIN DEL DEBUG ===\n";
?>