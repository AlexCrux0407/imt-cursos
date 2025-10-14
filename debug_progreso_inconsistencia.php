<?php
require_once __DIR__ . '/config/database.php';

// Primero, obtener un curso válido de la base de datos
$stmt = $conn->prepare("SELECT id, titulo FROM cursos LIMIT 1");
$stmt->execute();
$curso = $stmt->fetch();

if (!$curso) {
    echo "No hay cursos en la base de datos.\n";
    exit;
}

$curso_id = $curso['id'];
echo "=== ANÁLISIS DE INCONSISTENCIA EN CÁLCULO DE PROGRESO ===\n";
echo "Curso: {$curso['titulo']} (ID: $curso_id)\n\n";

// Obtener un estudiante válido
$stmt = $conn->prepare("SELECT id FROM usuarios LIMIT 1");
$stmt->execute();
$estudiante = $stmt->fetch();

if (!$estudiante) {
    echo "No hay estudiantes en la base de datos.\n";
    exit;
}

$estudiante_id = $estudiante['id'];
echo "Estudiante ID: $estudiante_id\n\n";

// 1. Consulta como en mis_cursos.php
echo "1. CONSULTA COMO EN MIS_CURSOS.PHP:\n";
echo "-----------------------------------\n";

$stmt = $conn->prepare("
    SELECT 
        c.id, c.titulo,
        COUNT(DISTINCT m.id) as total_modulos,
        COUNT(DISTINCT CASE WHEN pm.evaluacion_completada = 1 THEN m.id END) as modulos_completados,
        ROUND(
            (COUNT(DISTINCT CASE WHEN pm.evaluacion_completada = 1 THEN m.id END) / 
             COUNT(DISTINCT m.id)) * 100, 0
        ) as progreso_porcentaje
    FROM cursos c
    INNER JOIN inscripciones i ON c.id = i.curso_id AND i.usuario_id = :uid1
    LEFT JOIN modulos m ON c.id = m.curso_id
    LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = :uid2
    WHERE c.id = :curso_id
    GROUP BY c.id
");
$stmt->execute([':uid1' => $estudiante_id, ':uid2' => $estudiante_id, ':curso_id' => $curso_id]);
$resultado_mis_cursos = $stmt->fetch();

if ($resultado_mis_cursos) {
    echo "Título: " . $resultado_mis_cursos['titulo'] . "\n";
    echo "Total módulos: " . $resultado_mis_cursos['total_modulos'] . "\n";
    echo "Módulos completados: " . $resultado_mis_cursos['modulos_completados'] . "\n";
    echo "Progreso: " . $resultado_mis_cursos['progreso_porcentaje'] . "%\n\n";
} else {
    echo "No se encontró el curso.\n\n";
}

// 2. Consulta detallada de módulos y su estado
echo "2. ESTADO DETALLADO DE MÓDULOS:\n";
echo "-------------------------------\n";

$stmt = $conn->prepare("
    SELECT 
        m.id, m.titulo, m.orden,
        pm.completado,
        pm.evaluacion_completada,
        pm.fecha_completado,
        pm.fecha_evaluacion_completada,
        pm.puntaje_evaluacion,
        CASE 
            WHEN pm.evaluacion_completada = 1 THEN 'COMPLETADO'
            WHEN pm.completado = 1 THEN 'CONTENIDO COMPLETADO'
            WHEN pm.id IS NOT NULL THEN 'EN PROGRESO'
            ELSE 'SIN INICIAR'
        END as estado_modulo
    FROM modulos m
    LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = :uid
    WHERE m.curso_id = :curso_id
    ORDER BY m.orden
");
$stmt->execute([':uid' => $estudiante_id, ':curso_id' => $curso_id]);
$modulos_detalle = $stmt->fetchAll();

foreach ($modulos_detalle as $mod) {
    echo "Módulo {$mod['orden']}: {$mod['titulo']}\n";
    echo "  - ID: {$mod['id']}\n";
    echo "  - Completado: " . ($mod['completado'] ? 'SÍ' : 'NO') . "\n";
    echo "  - Evaluación completada: " . ($mod['evaluacion_completada'] ? 'SÍ' : 'NO') . "\n";
    echo "  - Estado: {$mod['estado_modulo']}\n";
    echo "  - Fecha completado: " . ($mod['fecha_completado'] ?: 'N/A') . "\n";
    echo "  - Fecha eval completada: " . ($mod['fecha_evaluacion_completada'] ?: 'N/A') . "\n";
    echo "  - Puntaje evaluación: " . ($mod['puntaje_evaluacion'] ?: 'N/A') . "\n";
    echo "\n";
}

// 3. Consulta como en curso_sidebar.php
echo "3. CONSULTA COMO EN CURSO_SIDEBAR.PHP:\n";
echo "-------------------------------------\n";

$stmt = $conn->prepare("
    SELECT m.id, m.titulo, m.orden,
           IF(pm.evaluacion_completada = 1, 1, 0) AS evaluacion_completada
    FROM modulos m
    LEFT JOIN progreso_modulos pm
      ON pm.modulo_id = m.id AND pm.usuario_id = :uid
    WHERE m.curso_id = :curso_id
    ORDER BY m.orden ASC
");
$stmt->execute([':curso_id' => $curso_id, ':uid' => $estudiante_id]);
$modulos_sidebar = $stmt->fetchAll();

$total_sidebar = count($modulos_sidebar);
$completados_sidebar = 0;

foreach ($modulos_sidebar as $mod) {
    echo "Módulo {$mod['orden']}: {$mod['titulo']}\n";
    echo "  - Evaluación completada: " . ($mod['evaluacion_completada'] ? 'SÍ' : 'NO') . "\n";
    
    if ($mod['evaluacion_completada']) {
        $completados_sidebar++;
    }
}

$progreso_sidebar = $total_sidebar > 0 ? ($completados_sidebar / $total_sidebar) * 100 : 0;
echo "\nProgreso calculado en sidebar: {$progreso_sidebar}%\n";
echo "Total módulos: {$total_sidebar}\n";
echo "Completados: {$completados_sidebar}\n\n";

// 4. Verificar si hay diferencias
echo "4. ANÁLISIS DE DIFERENCIAS:\n";
echo "---------------------------\n";

if ($resultado_mis_cursos) {
    $progreso_mis_cursos = (float)$resultado_mis_cursos['progreso_porcentaje'];
    $progreso_sidebar_calc = (float)$progreso_sidebar;
    
    echo "Progreso en mis_cursos.php: {$progreso_mis_cursos}%\n";
    echo "Progreso en sidebar: {$progreso_sidebar_calc}%\n";
    
    if ($progreso_mis_cursos == $progreso_sidebar_calc) {
        echo "✅ Los cálculos son CONSISTENTES\n";
    } else {
        echo "❌ Los cálculos son INCONSISTENTES\n";
        echo "Diferencia: " . abs($progreso_mis_cursos - $progreso_sidebar_calc) . "%\n";
    }
}

// 5. Verificar estructura del curso para sidebar
echo "\n5. ESTRUCTURA PARA SIDEBAR:\n";
echo "---------------------------\n";

$stmt = $conn->prepare("
    SELECT m.id  AS modulo_id, m.titulo AS modulo_titulo, m.orden AS modulo_orden,
           t.id  AS tema_id,   t.titulo AS tema_titulo,   t.orden AS tema_orden,
           st.id AS subtema_id, st.titulo AS subtema_titulo, st.orden AS subtema_orden,
           l.id  AS leccion_id, l.titulo AS leccion_titulo, l.orden AS leccion_orden,
           IF(pl.id IS NULL, 0, 1) AS leccion_completada
    FROM modulos m
    LEFT JOIN temas t      ON m.id = t.modulo_id
    LEFT JOIN subtemas st  ON t.id = st.tema_id
    LEFT JOIN lecciones l  ON st.id = l.subtema_id
    LEFT JOIN progreso_lecciones pl
           ON l.id = pl.leccion_id AND pl.usuario_id = :uid
    WHERE m.curso_id = :curso_id
    ORDER BY m.orden, t.orden, st.orden, l.orden
");
$stmt->execute([':curso_id' => $curso_id, ':uid' => $estudiante_id]);
$estructura_curso = $stmt->fetchAll();

$curso_estructura = [];
foreach ($estructura_curso as $row) {
    $mid = (int)$row['modulo_id'];
    if (!isset($curso_estructura[$mid])) {
        $curso_estructura[$mid] = [
            'id' => $mid,
            'titulo' => $row['modulo_titulo'],
            'orden' => (int)$row['modulo_orden'],
            'total_lecciones' => 0,
            'lecciones_completadas' => 0
        ];
    }
    if (!empty($row['leccion_id'])) {
        $curso_estructura[$mid]['total_lecciones']++;
        if ($row['leccion_completada']) {
            $curso_estructura[$mid]['lecciones_completadas']++;
        }
    }
}

echo "Estructura construida para sidebar:\n";
foreach ($curso_estructura as $mod) {
    echo "Módulo {$mod['orden']}: {$mod['titulo']}\n";
    echo "  - Total lecciones: {$mod['total_lecciones']}\n";
    echo "  - Lecciones completadas: {$mod['lecciones_completadas']}\n";
    
    // AQUÍ ESTÁ EL PROBLEMA: No se agrega evaluacion_completada a la estructura
    echo "  - ❌ FALTA: evaluacion_completada no se agrega a la estructura\n";
    echo "\n";
}

echo "\n=== PROBLEMA IDENTIFICADO ===\n";
echo "El problema está en modulo_contenido.php (y posiblemente otros archivos):\n";
echo "- Se construye la estructura del curso para el sidebar\n";
echo "- Pero NO se agrega la información de 'evaluacion_completada' de progreso_modulos\n";
echo "- El sidebar necesita esta información para mostrar correctamente el progreso\n";
echo "- Por eso se ve inconsistente entre mis_cursos.php y las páginas de contenido\n";
?>