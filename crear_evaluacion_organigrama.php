<?php
require_once 'config/database.php';

try {
    echo "<h2>Creando evaluación del organigrama...</h2>";
    
    // Verificar que el módulo 15 existe
    $stmt_check = $conn->prepare("SELECT id, titulo FROM modulos WHERE id = 15");
    $stmt_check->execute();
    $modulo_existe = $stmt_check->fetch();
    
    if (!$modulo_existe) {
        throw new Exception("El módulo 15 no existe en la base de datos");
    }
    
    echo "<p>✅ Módulo encontrado: " . htmlspecialchars($modulo_existe['titulo']) . "</p>";
    
    // Verificar si ya existe una evaluación del organigrama
    $stmt_existe = $conn->prepare("SELECT id FROM evaluaciones_modulo WHERE modulo_id = 15 AND titulo LIKE '%organigrama%'");
    $stmt_existe->execute();
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
    
    // Insertar la evaluación del organigrama en el módulo 3 (ID: 15)
    $stmt = $conn->prepare("
        INSERT INTO evaluaciones_modulo (
            modulo_id, 
            titulo, 
            descripcion, 
            tipo, 
            puntaje_maximo, 
            puntaje_minimo_aprobacion, 
            tiempo_limite, 
            intentos_permitidos, 
            activo, 
            obligatorio, 
            orden, 
            instrucciones
        ) VALUES (
            :modulo_id,
            :titulo,
            :descripcion,
            :tipo,
            :puntaje_maximo,
            :puntaje_minimo_aprobacion,
            :tiempo_limite,
            :intentos_permitidos,
            :activo,
            :obligatorio,
            :orden,
            :instrucciones
        )
    ");
    
    $stmt->execute([
        ':modulo_id' => 15, // Módulo 3 del curso de Gestión de la Calidad IMT
        ':titulo' => 'Organigrama de la Gestión de la Calidad en el IMT',
        ':descripcion' => 'Actividad interactiva para colocar correctamente las piezas del organigrama institucional del IMT en el área de gestión de la calidad.',
        ':tipo' => 'proyecto',
        ':puntaje_maximo' => 100.00,
        ':puntaje_minimo_aprobacion' => 70.00,
        ':tiempo_limite' => 30, // 30 minutos
        ':intentos_permitidos' => 3,
        ':activo' => 1,
        ':obligatorio' => 1,
        ':orden' => 1,
        ':instrucciones' => 'Arrastra y suelta cada pieza del organigrama en su posición correcta. Debes colocar correctamente al menos 11 de las 16 piezas para aprobar la evaluación. Tienes 30 minutos para completar la actividad y puedes intentarlo hasta 3 veces.'
    ]);
    
    $evaluacion_id = $conn->lastInsertId();
    echo "<p>✅ Evaluación creada con ID: $evaluacion_id</p>";
    
    // Crear una pregunta tipo "completar_espacios" para el organigrama
    $stmt = $conn->prepare("
        INSERT INTO preguntas_evaluacion (
            evaluacion_id,
            pregunta,
            tipo,
            opciones,
            respuesta_correcta,
            puntaje,
            orden,
            explicacion
        ) VALUES (
            :evaluacion_id,
            :pregunta,
            :tipo,
            :opciones,
            :respuesta_correcta,
            :puntaje,
            :orden,
            :explicacion
        )
    ");
    
    // Definir las posiciones correctas del organigrama
    $posiciones_correctas = [
        'pos-1' => 'Coordinación de Seguridad y Operación del Transporte',
        'pos-2' => 'Coordinación de la Seguridad en la Infraestructura del Transporte',
        'pos-3' => 'Coordinación de Ingeniería Vehicular y Seguridad Estructural',
        'pos-4' => 'Coordinación de Ingeniería Vehicular y Seguridad Estructural',
        'pos-5' => 'Coordinación de Transporte Integrado y Logística',
        'pos-6' => 'Coordinación de Infraestructura de los Sistemas',
        'pos-7' => 'División de Desarrollo y Diseño de Normas',
        'pos-8' => 'División de Investigación y Desarrollo de Normas',
        'pos-9' => 'División de Laboratorios de Investigación',
        'pos-10' => 'División de Transición',
        'pos-11' => 'División de Transición Sostenible y Cambio Climático',
        'pos-12' => 'Unidad de Seguridad',
        'pos-13' => 'Unidad de Transición del Transporte',
        'pos-14' => 'Unidad de Laboratorios de Investigación',
        'pos-15' => 'Unidad de Recursos Financieros y Materiales',
        'pos-16' => 'Unidad de Apoyo Jurídico'
    ];
    
    $opciones = [
        'piezas' => [
            'Coordinación de Seguridad y Operación del Transporte',
            'Coordinación de la Seguridad en la Infraestructura del Transporte', 
            'Coordinación de Ingeniería Vehicular y Seguridad Estructural',
            'Coordinación de Transporte Integrado y Logística',
            'Coordinación de Infraestructura de los Sistemas',
            'División de Desarrollo y Diseño de Normas',
            'División de Investigación y Desarrollo de Normas',
            'División de Laboratorios de Investigación',
            'División de Transición',
            'División de Transición Sostenible y Cambio Climático',
            'Unidad de Seguridad',
            'Unidad de Transición del Transporte',
            'Unidad de Laboratorios de Investigación',
            'Unidad de Recursos Financieros y Materiales',
            'Unidad de Apoyo Jurídico',
            'Coordinación de Administración y Finanzas'
        ],
        'posiciones' => $posiciones_correctas,
        'puntaje_minimo' => 11 // Mínimo 11 de 16 correctas para aprobar
    ];
    
    $stmt->execute([
        ':evaluacion_id' => $evaluacion_id,
        ':pregunta' => 'Coloca cada pieza del organigrama en su posición correcta dentro de la estructura organizacional del IMT para el área de gestión de la calidad.',
        ':tipo' => 'completar_espacios',
        ':opciones' => json_encode($opciones),
        ':respuesta_correcta' => json_encode($posiciones_correctas),
        ':puntaje' => 100.00,
        ':orden' => 1,
        ':explicacion' => 'El organigrama del IMT está estructurado jerárquicamente con la Dirección General en la cima, seguida por las coordinaciones principales, divisiones especializadas y unidades operativas.'
    ]);
    
    $pregunta_id = $conn->lastInsertId();
    echo "<p>✅ Pregunta creada con ID: $pregunta_id</p>";
    
    $conn->commit();
    echo "<h3>🎉 ¡Evaluación del organigrama creada exitosamente!</h3>";
    echo "<p><strong>ID de la evaluación:</strong> $evaluacion_id</p>";
    echo "<p><strong>ID de la pregunta:</strong> $pregunta_id</p>";
    echo "<p><a href='public/test_modulo_contenido.php'>🔍 Verificar en el test del módulo</a></p>";
    echo "<p><a href='public/estudiante/modulo_contenido.php?modulo_id=15'>📚 Ver módulo 15</a></p>";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "<h3>❌ Error al crear la evaluación:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>