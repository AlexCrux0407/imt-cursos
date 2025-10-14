<?php
require_once 'config/database.php';

echo "=== FIX: REGISTRO PROGRESO MÓDULO 2 ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

$usuario_id = 7;
$modulo2_id = 25; // Módulo 2

echo "Usuario ID: $usuario_id\n";
echo "Módulo 2 ID: $modulo2_id\n\n";

try {
    // Verificar si ya existe el registro
    $stmt = $conn->prepare("
        SELECT * FROM progreso_modulos 
        WHERE modulo_id = :modulo_id AND usuario_id = :usuario_id
    ");
    $stmt->execute([':modulo_id' => $modulo2_id, ':usuario_id' => $usuario_id]);
    $existe = $stmt->fetch();

    if ($existe) {
        echo "⚠️  Ya existe un registro para este módulo:\n";
        echo "   - Completado: " . ($existe['completado'] ? 'SÍ' : 'NO') . "\n";
        echo "   - Evaluación completada: " . ($existe['evaluacion_completada'] ? 'SÍ' : 'NO') . "\n";
        echo "   - Puntuación: " . ($existe['puntaje_evaluacion'] ?? 'N/A') . "\n";
    } else {
        echo "✅ Creando registro inicial para el Módulo 2...\n";
        
        $stmt = $conn->prepare("
            INSERT INTO progreso_modulos (
                usuario_id, 
                modulo_id, 
                completado, 
                evaluacion_completada
            ) VALUES (
                :usuario_id, 
                :modulo_id, 
                0, 
                0
            )
        ");
        
        $resultado = $stmt->execute([
            ':usuario_id' => $usuario_id,
            ':modulo_id' => $modulo2_id
        ]);
        
        if ($resultado) {
            echo "✅ Registro creado exitosamente\n";
            
            // Verificar el registro creado
            $stmt = $conn->prepare("
                SELECT * FROM progreso_modulos 
                WHERE modulo_id = :modulo_id AND usuario_id = :usuario_id
            ");
            $stmt->execute([':modulo_id' => $modulo2_id, ':usuario_id' => $usuario_id]);
            $nuevo = $stmt->fetch();
            
            if ($nuevo) {
                echo "   - ID: {$nuevo['id']}\n";
                echo "   - Completado: " . ($nuevo['completado'] ? 'SÍ' : 'NO') . "\n";
                echo "   - Evaluación completada: " . ($nuevo['evaluacion_completada'] ? 'SÍ' : 'NO') . "\n";
            }
        } else {
            echo "❌ Error al crear el registro\n";
        }
    }

    echo "\n";

    // Verificar el estado actual del curso después del fix
    echo "VERIFICANDO ESTADO DESPUÉS DEL FIX:\n";
    echo "==================================\n";

    $stmt = $conn->prepare("
        SELECT c.*, i.progreso, i.fecha_inscripcion, i.estado as estado_inscripcion,
               COUNT(DISTINCT m.id) as total_modulos,
               COUNT(DISTINCT CASE WHEN pm.completado = 1 THEN m.id END) as modulos_completados,
               COUNT(DISTINCT e.id) as total_evaluaciones,
               COUNT(DISTINCT CASE WHEN pm.evaluacion_completada = 1 THEN e.id END) as evaluaciones_completadas
        FROM inscripciones i
        INNER JOIN cursos c ON i.curso_id = c.id
        LEFT JOIN modulos m ON c.id = m.curso_id
        LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = i.usuario_id
        LEFT JOIN evaluaciones_modulo e ON m.id = e.modulo_id AND e.activo = 1
        WHERE c.id = 4 AND i.usuario_id = :usuario_id
        GROUP BY c.id, i.progreso, i.fecha_inscripcion, i.estado
    ");
    $stmt->execute([':usuario_id' => $usuario_id]);
    $estado = $stmt->fetch();

    if ($estado) {
        echo "Progreso: {$estado['progreso']}%\n";
        echo "Módulos completados: {$estado['modulos_completados']}/{$estado['total_modulos']}\n";
        echo "Evaluaciones completadas: {$estado['evaluaciones_completadas']}/{$estado['total_evaluaciones']}\n";
    }

    echo "\n";

    // Verificar acceso a módulos
    echo "VERIFICANDO ACCESO A MÓDULOS:\n";
    echo "============================\n";

    $stmt = $conn->prepare("
        SELECT m.id, m.titulo, m.orden,
               IF(pm.evaluacion_completada = 1, 1, 0) AS evaluacion_completada
        FROM modulos m
        LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = :usuario_id
        WHERE m.curso_id = 4
        ORDER BY m.orden
    ");
    $stmt->execute([':usuario_id' => $usuario_id]);
    $modulos = $stmt->fetchAll();

    foreach ($modulos as $i => $modulo) {
        $accesible = ($i == 0) || ($i > 0 && $modulos[$i-1]['evaluacion_completada']);
        echo "- {$modulo['titulo']}: " . ($accesible ? 'ACCESIBLE' : 'BLOQUEADO') . "\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DEL FIX ===\n";
?>