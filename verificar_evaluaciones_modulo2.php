<?php
require_once 'config/database.php';

try {
    echo "=== VERIFICACIÓN EVALUACIONES MÓDULO 2 ===\n";
    
    // Obtener todas las evaluaciones del módulo 2
    $stmt = $conn->prepare("
        SELECT e.id, e.titulo, e.puntaje_minimo_aprobacion, e.activo,
               COUNT(ie.id) as total_intentos,
               MAX(ie.puntaje_obtenido) as mejor_puntaje,
               CASE WHEN MAX(ie.puntaje_obtenido) >= e.puntaje_minimo_aprobacion THEN 'Aprobada' ELSE 'No Aprobada' END as estado
        FROM evaluaciones_modulo e
        LEFT JOIN intentos_evaluacion ie ON e.id = ie.evaluacion_id AND ie.usuario_id = 7
        WHERE e.modulo_id = 2
        GROUP BY e.id
        ORDER BY e.orden
    ");
    $stmt->execute();
    $evaluaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_evaluaciones = 0;
    $evaluaciones_aprobadas = 0;
    
    foreach ($evaluaciones as $eval) {
        if ($eval['activo']) {
            $total_evaluaciones++;
            if ($eval['estado'] === 'Aprobada') {
                $evaluaciones_aprobadas++;
            }
        }
        
        echo "Evaluación {$eval['id']}: {$eval['titulo']}\n";
        echo "  Activa: " . ($eval['activo'] ? 'Sí' : 'No') . "\n";
        echo "  Intentos: {$eval['total_intentos']}\n";
        echo "  Mejor puntaje: " . ($eval['mejor_puntaje'] ?? 'No realizada') . "%\n";
        echo "  Mínimo requerido: {$eval['puntaje_minimo_aprobacion']}%\n";
        echo "  Estado: {$eval['estado']}\n\n";
    }
    
    echo "RESUMEN:\n";
    echo "Total evaluaciones activas: {$total_evaluaciones}\n";
    echo "Evaluaciones aprobadas: {$evaluaciones_aprobadas}\n";
    echo "Módulo completado: " . ($evaluaciones_aprobadas >= $total_evaluaciones ? 'SÍ' : 'NO') . "\n";
    
    if ($evaluaciones_aprobadas < $total_evaluaciones) {
        echo "\n⚠️  PROBLEMA IDENTIFICADO:\n";
        echo "El usuario debe aprobar TODAS las evaluaciones del módulo para desbloquearlo.\n";
        echo "Faltan por aprobar: " . ($total_evaluaciones - $evaluaciones_aprobadas) . " evaluación(es).\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>