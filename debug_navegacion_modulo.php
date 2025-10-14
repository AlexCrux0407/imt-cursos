<?php
require_once __DIR__ . '/config/database.php';

echo "=== DEBUG: NAVEGACIÓN DESDE MIS CURSOS AL MÓDULO ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

$usuario_id = 7;
$curso_id = 4; // curso-ejemplo
$modulo_id = 24; // Módulo 01 (ID real)

echo "Usuario ID: $usuario_id\n";
echo "Curso ID: $curso_id (curso-ejemplo)\n";
echo "Módulo ID: $modulo_id (Módulo 01)\n\n";

// 1. Estado ANTES de navegar (como en mis_cursos.php)
echo "1. ESTADO ANTES DE NAVEGAR (MIS CURSOS):\n";
echo "========================================\n";

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
$estado_antes = $stmt->fetch();

if ($estado_antes) {
    echo "Progreso: {$estado_antes['progreso']}%\n";
    echo "Módulos completados: {$estado_antes['modulos_completados']}/{$estado_antes['total_modulos']}\n";
    echo "Evaluaciones completadas: {$estado_antes['evaluaciones_completadas']}/{$estado_antes['total_evaluaciones']}\n";
}

echo "\n";

// 2. Simular navegación a curso_contenido.php
echo "2. SIMULANDO NAVEGACIÓN A CURSO_CONTENIDO.PHP:\n";
echo "==============================================\n";

// Verificar inscripción y obtener información del curso (como en curso_contenido.php)
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
$curso = $stmt->fetch();

echo "Progreso después de cargar curso_contenido.php: {$curso['progreso']}%\n";

// Estructura del curso para la sidebar (como en curso_contenido.php)
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
            'evaluacion_completada' => (bool)$row['evaluacion_completada']
        ];
    }
}

echo "Estructura cargada correctamente\n";

// Función para verificar si un módulo puede ser accedido (como en curso_contenido.php)
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

echo "\nEstado de acceso a módulos:\n";
foreach ($curso_estructura as $modulo) {
    $accesible = $puedeAcceder($curso_estructura, $modulo['id']);
    echo "- Módulo {$modulo['orden']}: " . ($accesible ? 'ACCESIBLE' : 'BLOQUEADO') . "\n";
}

echo "\n";

// 3. Simular navegación a modulo_contenido.php
echo "3. SIMULANDO NAVEGACIÓN A MODULO_CONTENIDO.PHP:\n";
echo "===============================================\n";

// Verificar acceso al módulo (como en modulo_contenido.php)
$stmt = $conn->prepare("
    SELECT m.id, m.titulo, m.descripcion, m.contenido, m.orden, m.curso_id,
           c.titulo AS curso_titulo, c.descripcion AS curso_descripcion
    FROM modulos m
    INNER JOIN cursos c ON m.curso_id = c.id
    INNER JOIN inscripciones i ON i.curso_id = c.id AND i.usuario_id = :uid
    WHERE m.id = :modulo_id
    LIMIT 1
");
$stmt->execute([':modulo_id' => $modulo_id, ':uid' => $usuario_id]);
$modulo = $stmt->fetch();

if ($modulo) {
    echo "Módulo encontrado: {$modulo['titulo']}\n";
    
    // Control de acceso: solo permitir si el módulo anterior tiene evaluación aprobada
    $stmt = $conn->prepare("
        SELECT m.id, m.orden,
               IF(pm.evaluacion_completada = 1, 1, 0) AS evaluacion_completada
        FROM modulos m
        LEFT JOIN progreso_modulos pm
          ON pm.modulo_id = m.id AND pm.usuario_id = :uid
        WHERE m.curso_id = :curso_id
        ORDER BY m.orden ASC
    ");
    $stmt->execute([':curso_id' => $modulo['curso_id'], ':uid' => $usuario_id]);
    $modsAcceso = $stmt->fetchAll();

    $accesoPermitido = true;
    $idxActual = -1;
    foreach ($modsAcceso as $i => $mrow) {
        if ((int)$mrow['id'] === (int)$modulo['id']) { 
            $idxActual = $i; 
            break; 
        }
    }
    if ($idxActual > 0) {
        $prevEvalCompletada = (bool)$modsAcceso[$idxActual - 1]['evaluacion_completada'];
        $accesoPermitido = $prevEvalCompletada;
    }
    
    echo "Acceso permitido: " . ($accesoPermitido ? 'SÍ' : 'NO') . "\n";
    
    if (!$accesoPermitido) {
        echo "REDIRECCIÓN: Módulo bloqueado, redirigiendo a curso_contenido.php\n";
    } else {
        echo "Acceso concedido al módulo\n";
    }
} else {
    echo "Módulo no encontrado o acceso denegado\n";
}

echo "\n";

// 4. Estado DESPUÉS de toda la navegación
echo "4. ESTADO DESPUÉS DE LA NAVEGACIÓN:\n";
echo "===================================\n";

$stmt = $conn->prepare("
    SELECT c.*, i.progreso, i.fecha_inscripcion, i.estado as estado_inscripcion
    FROM cursos c
    INNER JOIN inscripciones i ON c.id = i.curso_id
    WHERE c.id = :curso_id AND i.usuario_id = :usuario_id
    LIMIT 1
");
$stmt->execute([':curso_id' => $curso_id, ':usuario_id' => $usuario_id]);
$estado_despues = $stmt->fetch();

if ($estado_despues) {
    echo "Progreso final: {$estado_despues['progreso']}%\n";
    echo "Estado: {$estado_despues['estado_inscripcion']}\n";
}

// Verificar si hubo cambios
if ($estado_antes && $estado_despues) {
    if ($estado_antes['progreso'] != $estado_despues['progreso']) {
        echo "\n⚠️  CAMBIO DETECTADO: El progreso cambió de {$estado_antes['progreso']}% a {$estado_despues['progreso']}%\n";
    } else {
        echo "\n✅ Sin cambios: El progreso se mantiene en {$estado_despues['progreso']}%\n";
    }
}

echo "\n=== FIN DEL DEBUG ===\n";
?>