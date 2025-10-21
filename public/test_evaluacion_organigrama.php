<?php
// Archivo de prueba para verificar que la evaluación del organigrama aparece en el módulo 3

require_once '../config/database.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Verificación de la Evaluación del Organigrama en el Módulo 3</h2>";
    
    // Verificar que la evaluación existe
    $stmt = $pdo->prepare("
        SELECT id, titulo, descripcion, tipo, puntaje_total, tiempo_limite 
        FROM evaluaciones_modulo 
        WHERE modulo_id = 15 AND titulo LIKE '%organigrama%'
    ");
    $stmt->execute();
    $evaluacion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($evaluacion) {
        echo "<h3>✅ Evaluación encontrada:</h3>";
        echo "<ul>";
        echo "<li><strong>ID:</strong> " . $evaluacion['id'] . "</li>";
        echo "<li><strong>Título:</strong> " . $evaluacion['titulo'] . "</li>";
        echo "<li><strong>Descripción:</strong> " . $evaluacion['descripcion'] . "</li>";
        echo "<li><strong>Tipo:</strong> " . $evaluacion['tipo'] . "</li>";
        echo "<li><strong>Puntaje Total:</strong> " . $evaluacion['puntaje_total'] . "</li>";
        echo "<li><strong>Tiempo Límite:</strong> " . $evaluacion['tiempo_limite'] . " minutos</li>";
        echo "</ul>";
        
        // Verificar las preguntas asociadas
        $stmt_preguntas = $pdo->prepare("
            SELECT id, pregunta, tipo, puntaje, respuesta_correcta 
            FROM preguntas_evaluacion 
            WHERE evaluacion_id = ?
        ");
        $stmt_preguntas->execute([$evaluacion['id']]);
        $preguntas = $stmt_preguntas->fetchAll(PDO::FETCH_ASSOC);
        
        if ($preguntas) {
            echo "<h3>✅ Preguntas encontradas (" . count($preguntas) . "):</h3>";
            foreach ($preguntas as $pregunta) {
                echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
                echo "<strong>ID:</strong> " . $pregunta['id'] . "<br>";
                echo "<strong>Pregunta:</strong> " . $pregunta['pregunta'] . "<br>";
                echo "<strong>Tipo:</strong> " . $pregunta['tipo'] . "<br>";
                echo "<strong>Puntaje:</strong> " . $pregunta['puntaje'] . "<br>";
                echo "<strong>Respuesta Correcta:</strong> " . substr($pregunta['respuesta_correcta'], 0, 100) . "...<br>";
                echo "</div>";
            }
        } else {
            echo "<h3>❌ No se encontraron preguntas para esta evaluación</h3>";
        }
        
        // Verificar el módulo
        $stmt_modulo = $pdo->prepare("
            SELECT id, titulo, orden 
            FROM modulos 
            WHERE id = 15
        ");
        $stmt_modulo->execute();
        $modulo = $stmt_modulo->fetch(PDO::FETCH_ASSOC);
        
        if ($modulo) {
            echo "<h3>✅ Módulo encontrado:</h3>";
            echo "<ul>";
            echo "<li><strong>ID:</strong> " . $modulo['id'] . "</li>";
            echo "<li><strong>Título:</strong> " . $modulo['titulo'] . "</li>";
            echo "<li><strong>Orden:</strong> " . $modulo['orden'] . "</li>";
            echo "</ul>";
        }
        
        echo "<h3>🔗 Enlaces de prueba:</h3>";
        echo "<ul>";
        echo "<li><a href='estudiante/modulo_contenido.php?modulo_id=15' target='_blank'>Ver Módulo 3 (Contenido)</a></li>";
        echo "<li><a href='estudiante/evaluacion_organigrama.php?id=" . $evaluacion['id'] . "' target='_blank'>Evaluación del Organigrama</a></li>";
        echo "</ul>";
        
    } else {
        echo "<h3>❌ No se encontró la evaluación del organigrama en el módulo 15</h3>";
        
        // Verificar todas las evaluaciones del módulo 15
        $stmt_all = $pdo->prepare("
            SELECT id, titulo, descripcion, tipo 
            FROM evaluaciones_modulo 
            WHERE modulo_id = 15
        ");
        $stmt_all->execute();
        $todas_evaluaciones = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
        
        if ($todas_evaluaciones) {
            echo "<h4>Evaluaciones existentes en el módulo 15:</h4>";
            foreach ($todas_evaluaciones as $eval) {
                echo "<li>ID: " . $eval['id'] . " - " . $eval['titulo'] . " (" . $eval['tipo'] . ")</li>";
            }
        } else {
            echo "<h4>No hay evaluaciones en el módulo 15</h4>";
        }
    }
    
} catch (PDOException $e) {
    echo "<h3>❌ Error de conexión a la base de datos:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>