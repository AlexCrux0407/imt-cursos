<?php
require_once 'config/database.php';

try {
    echo "<h2>Verificación Final de la Evaluación del Organigrama</h2>";
    
    // Buscar todas las evaluaciones del organigrama
    echo "<h3>1. Buscando evaluaciones del organigrama existentes:</h3>";
    $stmt_buscar = $conn->prepare("SELECT * FROM evaluaciones_modulo WHERE titulo LIKE '%organigrama%'");
    $stmt_buscar->execute();
    $evaluaciones_existentes = $stmt_buscar->fetchAll();
    
    if ($evaluaciones_existentes) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Módulo ID</th><th>Título</th><th>Activo</th><th>Acciones</th></tr>";
        foreach ($evaluaciones_existentes as $eval) {
            $activo = $eval['activo'] ? '✅ Sí' : '❌ No';
            echo "<tr>";
            echo "<td>{$eval['id']}</td>";
            echo "<td>{$eval['modulo_id']}</td>";
            echo "<td>" . htmlspecialchars($eval['titulo']) . "</td>";
            echo "<td>{$activo}</td>";
            echo "<td><a href='?eliminar={$eval['id']}'>🗑️ Eliminar</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>❌ No se encontraron evaluaciones del organigrama</p>";
    }
    
    // Eliminar evaluación si se solicita
    if (isset($_GET['eliminar'])) {
        $eval_id = (int)$_GET['eliminar'];
        echo "<h3>2. Eliminando evaluación ID: $eval_id</h3>";
        
        // Eliminar preguntas primero
        $stmt_del_preg = $conn->prepare("DELETE FROM preguntas_evaluacion WHERE evaluacion_id = ?");
        $stmt_del_preg->execute([$eval_id]);
        
        // Eliminar evaluación
        $stmt_del_eval = $conn->prepare("DELETE FROM evaluaciones_modulo WHERE id = ?");
        $stmt_del_eval->execute([$eval_id]);
        
        echo "<p>✅ Evaluación eliminada</p>";
        echo "<p><a href='verificar_evaluacion_final.php'>🔄 Recargar página</a></p>";
        exit;
    }
    
    // Verificar el módulo correcto (ID: 28)
    echo "<h3>3. Verificando módulo 28 (Organización del IMT):</h3>";
    $stmt_mod = $conn->prepare("SELECT * FROM modulos WHERE id = 28");
    $stmt_mod->execute();
    $modulo_28 = $stmt_mod->fetch();
    
    if ($modulo_28) {
        echo "<p>✅ Módulo encontrado: " . htmlspecialchars($modulo_28['titulo']) . "</p>";
        echo "<p><strong>Curso ID:</strong> {$modulo_28['curso_id']}</p>";
        echo "<p><strong>Orden:</strong> {$modulo_28['orden']}</p>";
        
        // Verificar evaluaciones en este módulo
        $stmt_eval_28 = $conn->prepare("SELECT * FROM evaluaciones_modulo WHERE modulo_id = 28");
        $stmt_eval_28->execute();
        $evals_28 = $stmt_eval_28->fetchAll();
        
        if ($evals_28) {
            echo "<p>✅ Evaluaciones existentes en módulo 28:</p>";
            foreach ($evals_28 as $eval) {
                echo "<p>- {$eval['titulo']} (ID: {$eval['id']})</p>";
            }
        } else {
            echo "<p>❌ No hay evaluaciones en el módulo 28</p>";
        }
    } else {
        echo "<p>❌ Módulo 28 no encontrado</p>";
    }
    
    // Botón para crear la evaluación en el módulo correcto
    echo "<h3>4. Crear evaluación en el módulo correcto:</h3>";
    if (isset($_GET['crear'])) {
        echo "<p>🔄 Creando evaluación del organigrama en módulo 28...</p>";
        
        $conn->beginTransaction();
        
        // Crear la evaluación
        $stmt_crear = $conn->prepare("
            INSERT INTO evaluaciones_modulo (
                modulo_id, titulo, descripcion, tipo, puntaje_maximo, puntaje_minimo_aprobacion,
                tiempo_limite, intentos_permitidos, activo, instrucciones, fecha_creacion
            ) VALUES (
                28, 'Organigrama del IMT', 
                'Ejercicio interactivo para completar el organigrama del Instituto Mexicano del Transporte',
                'proyecto', 100, 70, 30, 3, 1,
                'Completa el organigrama arrastrando los elementos a sus posiciones correctas. Debes obtener al menos 70 puntos para aprobar.',
                NOW()
            )
        ");
        $stmt_crear->execute();
        $eval_id = $conn->lastInsertId();
        
        // Crear la pregunta
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
        
        $stmt_pregunta = $conn->prepare("
            INSERT INTO preguntas_evaluacion (
                evaluacion_id, tipo, pregunta, opciones, respuesta_correcta, puntaje, orden
            ) VALUES (
                ?, 'completar_espacios',
                'Completa el organigrama del Instituto Mexicano del Transporte arrastrando cada elemento a su posición correcta.',
                ?, ?, 100, 1
            )
        ");
        $stmt_pregunta->execute([$eval_id, $opciones_json, $respuesta_correcta]);
        $pregunta_id = $conn->lastInsertId();
        
        $conn->commit();
        
        echo "<p>✅ ¡Evaluación creada exitosamente!</p>";
        echo "<p><strong>ID de evaluación:</strong> $eval_id</p>";
        echo "<p><strong>ID de pregunta:</strong> $pregunta_id</p>";
        echo "<p><a href='public/estudiante/modulo_contenido.php?id=28'>📚 Ver módulo 28</a></p>";
        echo "<p><a href='verificar_evaluacion_final.php'>🔄 Verificar resultado</a></p>";
        
    } else {
        echo "<p><a href='?crear=1' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🎯 Crear Evaluación del Organigrama en Módulo 28</a></p>";
    }
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    echo "<h3>❌ Error:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>