<?php
require_once 'config/database.php';

try {
    echo "<h2>Creando evaluación del organigrama en el módulo correcto...</h2>";
    
    // Buscar el curso de gestión de calidad
    $stmt_curso = $conn->prepare("SELECT * FROM cursos WHERE titulo LIKE '%calidad%' OR titulo LIKE '%gestión%' OR titulo LIKE '%gestion%' LIMIT 1");
    $stmt_curso->execute();
    $curso = $stmt_curso->fetch();
    
    if (!$curso) {
        throw new Exception("No se encontró el curso de gestión de calidad");
    }
    
    echo "<p>✅ Curso encontrado: " . htmlspecialchars($curso['titulo']) . " (ID: {$curso['id']})</p>";
    
    // Buscar el módulo 3 de este curso
    $stmt_modulo = $conn->prepare("SELECT * FROM modulos WHERE curso_id = ? AND orden = 3 LIMIT 1");
    $stmt_modulo->execute([$curso['id']]);
    $modulo = $stmt_modulo->fetch();
    
    if (!$modulo) {
        throw new Exception("No se encontró el módulo 3 en el curso de gestión de calidad");
    }
    
    echo "<p>✅ Módulo 3 encontrado: " . htmlspecialchars($modulo['titulo']) . " (ID: {$modulo['id']})</p>";
    
    $modulo_id = $modulo['id'];
    
    // Verificar si ya existe una evaluación del organigrama
    $stmt_existe = $conn->prepare("SELECT id FROM evaluaciones_modulo WHERE modulo_id = ? AND titulo LIKE '%organigrama%'");
    $stmt_existe->execute([$modulo_id]);
    $evaluacion_existe = $stmt_existe->fetch();
    
    if ($evaluacion_existe) {
        echo "<p>⚠️ Ya existe una evaluación del organigrama (ID: " . $evaluacion_existe['id'] . "). Eliminando para recrear...</p>";
        
        // Eliminar preguntas asociadas
        $stmt_del_preguntas = $conn->prepare("DELETE FROM preguntas_evaluacion WHERE evaluacion_id = ?");
        $stmt_del_preguntas->execute([$evaluacion_existe['id']]);
        
        // Eliminar evaluación
        $stmt_del_eval = $conn->prepare("DELETE FROM evaluaciones_modulo WHERE id = ?");
        $stmt_del_eval->execute([$evaluacion_existe['id']]);
        
        echo "<p>✅ Evaluación anterior eliminada</p>";
    }
    
    $conn->beginTransaction();
    
    // Crear la evaluación del organigrama
    $stmt = $conn->prepare("
        INSERT INTO evaluaciones_modulo (
            modulo_id, titulo, descripcion, tipo, puntaje_maximo, puntaje_minimo,
            tiempo_limite, intentos_permitidos, activo, instrucciones, fecha_creacion
        ) VALUES (
            :modulo_id, :titulo, :descripcion, :tipo, :puntaje_maximo, :puntaje_minimo,
            :tiempo_limite, :intentos_permitidos, :activo, :instrucciones, NOW()
        )
    ");
    
    $stmt->execute([
        ':modulo_id' => $modulo_id,
        ':titulo' => 'Organigrama del IMT',
        ':descripcion' => 'Ejercicio interactivo para completar el organigrama del Instituto Mexicano del Transporte',
        ':tipo' => 'proyecto',
        ':puntaje_maximo' => 100,
        ':puntaje_minimo' => 70,
        ':tiempo_limite' => 30,
        ':intentos_permitidos' => 3,
        ':activo' => 1,
        ':instrucciones' => 'Completa el organigrama arrastrando los elementos a sus posiciones correctas. Debes obtener al menos 70 puntos para aprobar.'
    ]);
    
    $evaluacion_id = $conn->lastInsertId();
    echo "<p>✅ Evaluación creada con ID: $evaluacion_id</p>";
    
    // Crear la pregunta del organigrama
    $stmt_pregunta = $conn->prepare("
        INSERT INTO preguntas_evaluacion (
            evaluacion_id, tipo, pregunta, opciones, respuesta_correcta, puntaje, orden
        ) VALUES (
            :evaluacion_id, :tipo, :pregunta, :opciones, :respuesta_correcta, :puntaje, :orden
        )
    ");
    
    $opciones_json = json_encode([
        'elementos' => [
            'Dirección General',
            'Coordinación de Integración del Transporte',
            'Coordinación de Infraestructura del Transporte',
            'Coordinación de Seguridad y Operación del Transporte',
            'Coordinación de Planeación e Información'
        ],
        'posiciones' => [
            'direccion_general',
            'coord_integracion',
            'coord_infraestructura', 
            'coord_seguridad',
            'coord_planeacion'
        ]
    ]);
    
    $respuesta_correcta = json_encode([
        'direccion_general' => 'Dirección General',
        'coord_integracion' => 'Coordinación de Integración del Transporte',
        'coord_infraestructura' => 'Coordinación de Infraestructura del Transporte',
        'coord_seguridad' => 'Coordinación de Seguridad y Operación del Transporte',
        'coord_planeacion' => 'Coordinación de Planeación e Información'
    ]);
    
    $stmt_pregunta->execute([
        ':evaluacion_id' => $evaluacion_id,
        ':tipo' => 'completar_espacios',
        ':pregunta' => 'Completa el organigrama del Instituto Mexicano del Transporte arrastrando cada elemento a su posición correcta.',
        ':opciones' => $opciones_json,
        ':respuesta_correcta' => $respuesta_correcta,
        ':puntaje' => 100,
        ':orden' => 1
    ]);
    
    $pregunta_id = $conn->lastInsertId();
    echo "<p>✅ Pregunta creada con ID: $pregunta_id</p>";
    
    $conn->commit();
    echo "<h3>🎉 ¡Evaluación del organigrama creada exitosamente!</h3>";
    echo "<p><strong>Curso:</strong> " . htmlspecialchars($curso['titulo']) . "</p>";
    echo "<p><strong>Módulo:</strong> " . htmlspecialchars($modulo['titulo']) . " (ID: {$modulo_id})</p>";
    echo "<p><strong>ID de la evaluación:</strong> $evaluacion_id</p>";
    echo "<p><strong>ID de la pregunta:</strong> $pregunta_id</p>";
    echo "<p><a href='public/estudiante/modulo_contenido.php?id={$modulo_id}'>📚 Ver módulo en el sistema</a></p>";
    echo "<p><a href='test_modulo_15_directo.php'>🔍 Verificar evaluaciones</a></p>";
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    echo "<h3>❌ Error al crear la evaluación:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>