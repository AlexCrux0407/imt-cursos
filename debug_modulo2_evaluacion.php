<?php
require_once 'config/database.php';

echo "=== DEBUG: EVALUACIÓN MÓDULO 2 ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

$usuario_id = 7;
$curso_id = 4;
$modulo2_id = 25; // ID real del Módulo 2

echo "Usuario ID: $usuario_id\n";
echo "Curso ID: $curso_id\n";
echo "Módulo 2 ID: $modulo2_id\n\n";

// 1. Verificar si existe evaluación activa para el Módulo 2
echo "1. VERIFICANDO EVALUACIÓN DEL MÓDULO 2:\n";
echo "======================================\n";

$stmt = $conn->prepare("
    SELECT e.*, m.titulo as modulo_titulo
    FROM evaluaciones_modulo e
    INNER JOIN modulos m ON e.modulo_id = m.id
    WHERE e.modulo_id = :modulo_id
");
$stmt->execute([':modulo_id' => $modulo2_id]);
$evaluacion = $stmt->fetch();

if ($evaluacion) {
    echo "✅ Evaluación encontrada:\n";
    echo "   - ID: {$evaluacion['id']}\n";
    echo "   - Título: {$evaluacion['titulo']}\n";
    echo "   - Activa: " . ($evaluacion['activo'] ? 'SÍ' : 'NO') . "\n";
    echo "   - Intentos permitidos: {$evaluacion['intentos_permitidos']}\n";
    echo "   - Nota mínima: {$evaluacion['nota_minima']}\n";
    echo "   - Duración: {$evaluacion['duracion_minutos']} minutos\n";
} else {
    echo "❌ NO se encontró evaluación para el Módulo 2\n";
}

echo "\n";

// 2. Verificar intentos del usuario en esta evaluación
echo "2. VERIFICANDO INTENTOS DEL USUARIO:\n";
echo "===================================\n";

if ($evaluacion) {
    $stmt = $conn->prepare("
        SELECT ie.*, 
               DATE_FORMAT(ie.fecha_inicio, '%Y-%m-%d %H:%i:%s') as fecha_inicio_fmt,
               DATE_FORMAT(ie.fecha_fin, '%Y-%m-%d %H:%i:%s') as fecha_fin_fmt
        FROM intentos_evaluacion ie
        WHERE ie.evaluacion_id = :evaluacion_id AND ie.usuario_id = :usuario_id
        ORDER BY ie.fecha_inicio DESC
    ");
    $stmt->execute([':evaluacion_id' => $evaluacion['id'], ':usuario_id' => $usuario_id]);
    $intentos = $stmt->fetchAll();

    if ($intentos) {
        echo "✅ Intentos encontrados: " . count($intentos) . "\n\n";
        foreach ($intentos as $i => $intento) {
            echo "Intento " . ($i + 1) . ":\n";
            echo "   - ID: {$intento['id']}\n";
            echo "   - Estado: {$intento['estado']}\n";
            echo "   - Nota: " . ($intento['nota'] ?? 'N/A') . "\n";
            echo "   - Aprobado: " . ($intento['aprobado'] ? 'SÍ' : 'NO') . "\n";
            echo "   - Fecha inicio: {$intento['fecha_inicio_fmt']}\n";
            echo "   - Fecha fin: " . ($intento['fecha_fin_fmt'] ?? 'N/A') . "\n";
            echo "   - Tiempo usado: " . ($intento['tiempo_usado_minutos'] ?? 'N/A') . " min\n\n";
        }
    } else {
        echo "❌ NO se encontraron intentos para esta evaluación\n";
    }
} else {
    echo "⚠️  No se puede verificar intentos sin evaluación\n";
}

echo "\n";

// 3. Verificar estado en progreso_modulos
echo "3. VERIFICANDO PROGRESO_MODULOS:\n";
echo "===============================\n";

$stmt = $conn->prepare("
    SELECT pm.*,
           DATE_FORMAT(pm.fecha_completado, '%Y-%m-%d %H:%i:%s') as fecha_completado_fmt
    FROM progreso_modulos pm
    WHERE pm.modulo_id = :modulo_id AND pm.usuario_id = :usuario_id
");
$stmt->execute([':modulo_id' => $modulo2_id, ':usuario_id' => $usuario_id]);
$progreso = $stmt->fetch();

if ($progreso) {
    echo "✅ Registro de progreso encontrado:\n";
    echo "   - Completado: " . ($progreso['completado'] ? 'SÍ' : 'NO') . "\n";
    echo "   - Evaluación completada: " . ($progreso['evaluacion_completada'] ? 'SÍ' : 'NO') . "\n";
    echo "   - Puntuación: " . ($progreso['puntuacion'] ?? 'N/A') . "\n";
    echo "   - Fecha completado: " . ($progreso['fecha_completado_fmt'] ?? 'N/A') . "\n";
} else {
    echo "❌ NO se encontró registro en progreso_modulos\n";
}

echo "\n";

// 4. Verificar progreso general del curso
echo "4. VERIFICANDO PROGRESO GENERAL DEL CURSO:\n";
echo "=========================================\n";

$stmt = $conn->prepare("
    SELECT i.progreso, i.estado,
           COUNT(DISTINCT m.id) as total_modulos,
           COUNT(DISTINCT CASE WHEN pm.completado = 1 THEN m.id END) as modulos_completados,
           COUNT(DISTINCT e.id) as total_evaluaciones,
           COUNT(DISTINCT CASE WHEN pm.evaluacion_completada = 1 THEN e.id END) as evaluaciones_completadas
    FROM inscripciones i
    INNER JOIN cursos c ON i.curso_id = c.id
    LEFT JOIN modulos m ON c.id = m.curso_id
    LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = i.usuario_id
    LEFT JOIN evaluaciones_modulo e ON m.id = e.modulo_id AND e.activo = 1
    WHERE i.curso_id = :curso_id AND i.usuario_id = :usuario_id
    GROUP BY i.progreso, i.estado
");
$stmt->execute([':curso_id' => $curso_id, ':usuario_id' => $usuario_id]);
$estado_curso = $stmt->fetch();

if ($estado_curso) {
    echo "Progreso actual: {$estado_curso['progreso']}%\n";
    echo "Estado: {$estado_curso['estado']}\n";
    echo "Módulos completados: {$estado_curso['modulos_completados']}/{$estado_curso['total_modulos']}\n";
    echo "Evaluaciones completadas: {$estado_curso['evaluaciones_completadas']}/{$estado_curso['total_evaluaciones']}\n";
}

echo "\n";

// 5. Verificar todas las preguntas de la evaluación
if ($evaluacion) {
    echo "5. VERIFICANDO PREGUNTAS DE LA EVALUACIÓN:\n";
    echo "=========================================\n";
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_preguntas
        FROM preguntas_evaluacion
        WHERE evaluacion_id = :evaluacion_id
    ");
    $stmt->execute([':evaluacion_id' => $evaluacion['id']]);
    $preguntas = $stmt->fetch();
    
    echo "Total de preguntas: {$preguntas['total_preguntas']}\n";
    
    if ($preguntas['total_preguntas'] == 0) {
        echo "⚠️  La evaluación no tiene preguntas configuradas\n";
    }
}

echo "\n=== FIN DEL DEBUG ===\n";
?>