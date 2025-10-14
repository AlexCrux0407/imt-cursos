<?php
require_once __DIR__ . '/config/database.php';

echo "=== VERIFICACIÓN DE INCONSISTENCIA EN EVALUACIONES ===\n\n";

$usuario_id = 7;

// Buscar el curso ejemplo
$stmt = $conn->prepare("
    SELECT id, titulo 
    FROM cursos 
    WHERE titulo LIKE '%ejemplo%' OR descripcion LIKE '%ejemplo%'
    LIMIT 1
");
$stmt->execute();
$curso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$curso) {
    echo "No se encontró el curso ejemplo\n";
    exit;
}

echo "Curso encontrado: {$curso['titulo']} (ID: {$curso['id']})\n\n";

// 1. Verificar datos en la tabla inscripciones (como los ve "mis cursos")
echo "=== DATOS EN TABLA INSCRIPCIONES (Mis Cursos) ===\n";
$stmt = $conn->prepare("
    SELECT i.progreso, i.estado, i.fecha_inscripcion,
           COUNT(DISTINCT m.id) as total_modulos,
           COUNT(DISTINCT CASE WHEN pm.completado = 1 THEN m.id END) as modulos_completados,
           COUNT(DISTINCT e.id) as total_evaluaciones,
           COUNT(DISTINCT CASE WHEN pm.evaluacion_completada = 1 THEN e.id END) as evaluaciones_completadas
    FROM inscripciones i
    INNER JOIN cursos c ON i.curso_id = c.id
    LEFT JOIN modulos m ON c.id = m.curso_id
    LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = i.usuario_id
    LEFT JOIN evaluaciones_modulo e ON m.id = e.modulo_id AND e.activo = 1
    WHERE i.usuario_id = ? AND i.curso_id = ?
    GROUP BY i.progreso, i.estado, i.fecha_inscripcion
");
$stmt->execute([$usuario_id, $curso['id']]);
$inscripcion_data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($inscripcion_data) {
    echo "Progreso en inscripciones: {$inscripcion_data['progreso']}%\n";
    echo "Estado: {$inscripcion_data['estado']}\n";
    echo "Módulos completados: {$inscripcion_data['modulos_completados']}/{$inscripcion_data['total_modulos']}\n";
    echo "Evaluaciones completadas: {$inscripcion_data['evaluaciones_completadas']}/{$inscripcion_data['total_evaluaciones']}\n\n";
} else {
    echo "No se encontró inscripción\n\n";
}

// 2. Verificar datos como los ve la sidebar
echo "=== DATOS COMO LOS VE LA SIDEBAR ===\n";
$stmt = $conn->prepare("
    SELECT m.id AS modulo_id, m.titulo AS modulo_titulo, m.orden AS modulo_orden,
           IF(pm.evaluacion_completada = 1, 1, 0) AS evaluacion_completada,
           pm.completado, pm.puntaje_evaluacion, pm.fecha_evaluacion_completada
    FROM modulos m
    LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = ?
    WHERE m.curso_id = ?
    ORDER BY m.orden
");
$stmt->execute([$usuario_id, $curso['id']]);
$modulos_sidebar = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($modulos_sidebar as $modulo) {
    echo "Módulo: {$modulo['modulo_titulo']} (ID: {$modulo['modulo_id']})\n";
    echo "  Evaluación completada (sidebar): " . ($modulo['evaluacion_completada'] ? 'SÍ' : 'NO') . "\n";
    echo "  Completado: " . ($modulo['completado'] ? 'SÍ' : 'NO') . "\n";
    echo "  Puntaje: " . ($modulo['puntaje_evaluacion'] ?? 'N/A') . "\n";
    echo "  Fecha completado: " . ($modulo['fecha_evaluacion_completada'] ?? 'N/A') . "\n\n";
}

// 3. Verificar evaluaciones específicas del módulo 1
echo "=== EVALUACIONES ESPECÍFICAS DEL MÓDULO 1 ===\n";
$stmt = $conn->prepare("
    SELECT m.id as modulo_id, m.titulo as modulo_titulo
    FROM modulos m
    WHERE m.curso_id = ? AND m.orden = 1
");
$stmt->execute([$curso['id']]);
$modulo1 = $stmt->fetch(PDO::FETCH_ASSOC);

if ($modulo1) {
    echo "Módulo 1: {$modulo1['modulo_titulo']} (ID: {$modulo1['modulo_id']})\n\n";
    
    $stmt = $conn->prepare("
        SELECT e.id, e.titulo, e.activo, e.puntaje_minimo_aprobacion,
               COUNT(ie.id) as total_intentos,
               MAX(ie.puntaje_obtenido) as mejor_puntaje,
               CASE WHEN MAX(ie.puntaje_obtenido) >= e.puntaje_minimo_aprobacion THEN 'Aprobada' ELSE 'No Aprobada' END as estado_evaluacion
        FROM evaluaciones_modulo e
        LEFT JOIN intentos_evaluacion ie ON e.id = ie.evaluacion_id AND ie.usuario_id = ?
        WHERE e.modulo_id = ?
        GROUP BY e.id
        ORDER BY e.orden
    ");
    $stmt->execute([$usuario_id, $modulo1['modulo_id']]);
    $evaluaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($evaluaciones as $eval) {
        echo "Evaluación: {$eval['titulo']} (ID: {$eval['id']})\n";
        echo "  Activa: " . ($eval['activo'] ? 'SÍ' : 'NO') . "\n";
        echo "  Intentos: {$eval['total_intentos']}\n";
        echo "  Mejor puntaje: " . ($eval['mejor_puntaje'] ?? 'N/A') . "%\n";
        echo "  Puntaje mínimo: {$eval['puntaje_minimo_aprobacion']}%\n";
        echo "  Estado: {$eval['estado_evaluacion']}\n\n";
    }
}

// 4. Verificar si hay desincronización
echo "=== ANÁLISIS DE INCONSISTENCIA ===\n";
if ($inscripcion_data) {
    $progreso_inscripcion = $inscripcion_data['progreso'];
    $evaluaciones_completadas_inscripcion = $inscripcion_data['evaluaciones_completadas'];
    
    // Contar evaluaciones completadas según sidebar
    $evaluaciones_completadas_sidebar = 0;
    foreach ($modulos_sidebar as $modulo) {
        if ($modulo['evaluacion_completada']) {
            $evaluaciones_completadas_sidebar++;
        }
    }
    
    echo "Evaluaciones completadas según 'Mis Cursos': {$evaluaciones_completadas_inscripcion}\n";
    echo "Evaluaciones completadas según 'Sidebar': {$evaluaciones_completadas_sidebar}\n";
    echo "Progreso en inscripciones: {$progreso_inscripcion}%\n";
    
    if ($evaluaciones_completadas_inscripcion != $evaluaciones_completadas_sidebar) {
        echo "\n❌ INCONSISTENCIA DETECTADA:\n";
        echo "La sidebar y 'mis cursos' muestran diferentes estados de evaluaciones completadas.\n";
    } else {
        echo "\n✅ Los datos están sincronizados.\n";
    }
}

echo "\n=== FIN DE VERIFICACIÓN ===\n";
?>