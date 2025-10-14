<?php
require_once 'config/database.php';

try {
    // Verificar todos los usuarios
    echo "=== USUARIOS REGISTRADOS ===\n";
    $stmt = $conn->prepare("SELECT id, nombre, email FROM usuarios ORDER BY id");
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($usuarios as $usuario) {
        echo "Usuario {$usuario['id']}: {$usuario['nombre']} ({$usuario['email']})\n";
    }
    
    // Verificar todos los cursos
    echo "\n=== CURSOS DISPONIBLES ===\n";
    $stmt = $conn->prepare("SELECT id, titulo FROM cursos ORDER BY id");
    $stmt->execute();
    $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($cursos as $curso) {
        echo "Curso {$curso['id']}: {$curso['titulo']}\n";
    }
    
    // Verificar inscripciones
    echo "\n=== INSCRIPCIONES ===\n";
    $stmt = $conn->prepare("SELECT i.*, u.nombre, c.titulo FROM inscripciones i JOIN usuarios u ON i.usuario_id = u.id JOIN cursos c ON i.curso_id = c.id ORDER BY i.id");
    $stmt->execute();
    $inscripciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($inscripciones as $insc) {
        echo "Inscripción {$insc['id']}: {$insc['nombre']} en {$insc['titulo']} - Estado: {$insc['estado']} - Progreso: {$insc['progreso']}%\n";
    }
    
    // Verificar progreso de módulos para todos los usuarios
    $stmt = $conn->prepare("SELECT * FROM progreso_modulos ORDER BY usuario_id, modulo_id");
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n=== PROGRESO DE MÓDULOS (TODOS LOS USUARIOS) ===\n";
    foreach($resultados as $row) {
        echo "Usuario: {$row['usuario_id']}, Módulo: {$row['modulo_id']}, ";
        echo "Completado: " . ($row['completado'] ? 'Sí' : 'No') . ", ";
        echo "Evaluación: " . ($row['evaluacion_completada'] ? 'Sí' : 'No') . ", ";
        echo "Puntaje: " . ($row['puntaje_evaluacion'] ?? 'N/A') . "\n";
    }
    
    // Verificar módulos de todos los cursos
    echo "\n=== MÓDULOS DE TODOS LOS CURSOS ===\n";
    $stmt = $conn->prepare("SELECT m.id, m.titulo, m.orden, m.curso_id, c.titulo as curso_titulo FROM modulos m JOIN cursos c ON m.curso_id = c.id ORDER BY m.curso_id, m.orden");
    $stmt->execute();
    $modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($modulos as $modulo) {
        echo "Módulo {$modulo['id']}: {$modulo['titulo']} (Orden: {$modulo['orden']}) - Curso: {$modulo['curso_titulo']}\n";
    }
    
    // Verificar evaluaciones completadas
    echo "\n=== EVALUACIONES COMPLETADAS ===\n";
    $stmt = $conn->prepare("
        SELECT e.id, e.titulo, e.modulo_id, 
               MAX(ie.puntaje_obtenido) as mejor_puntaje,
               e.puntaje_minimo_aprobacion,
               COUNT(ie.id) as total_intentos,
               CASE WHEN MAX(ie.puntaje_obtenido) >= e.puntaje_minimo_aprobacion THEN 'Aprobada' ELSE 'No Aprobada' END as estado
        FROM evaluaciones_modulo e
        LEFT JOIN intentos_evaluacion ie ON e.id = ie.evaluacion_id AND ie.usuario_id = 7
        WHERE e.activo = 1
        GROUP BY e.id
        ORDER BY e.modulo_id, e.orden
    ");
    $stmt->execute();
    $evaluaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($evaluaciones as $eval) {
        echo "Evaluación {$eval['id']} (Módulo {$eval['modulo_id']}): {$eval['titulo']}\n";
        echo "  Intentos: {$eval['total_intentos']}, Mejor puntaje: " . ($eval['mejor_puntaje'] ?? 'No realizada') . "% ";
        echo "(Mínimo: {$eval['puntaje_minimo_aprobacion']}%) - {$eval['estado']}\n";
    }
    
    // Verificar intentos de evaluación específicos
    echo "\n=== INTENTOS DE EVALUACIÓN USUARIO 7 ===\n";
    $stmt = $conn->prepare("
        SELECT ie.*, e.titulo as evaluacion_titulo, e.modulo_id
        FROM intentos_evaluacion ie
        JOIN evaluaciones_modulo e ON ie.evaluacion_id = e.id
        WHERE ie.usuario_id = 7
        ORDER BY ie.fecha_inicio DESC
    ");
    $stmt->execute();
    $intentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($intentos as $intento) {
        echo "Intento {$intento['id']}: {$intento['evaluacion_titulo']} (Módulo {$intento['modulo_id']})\n";
        echo "  Estado: {$intento['estado']}, Puntaje: {$intento['puntaje_obtenido']}%, ";
        echo "Fecha: {$intento['fecha_inicio']} - {$intento['fecha_fin']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>