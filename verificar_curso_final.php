<?php
require_once __DIR__ . '/config/database.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🔍 Verificando el último curso procesado</h2>";
    
    // Obtener el último curso creado
    $stmt = $pdo->prepare("
        SELECT id, titulo, descripcion, created_at
        FROM cursos 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $curso = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$curso) {
        echo "<p style='color: red;'>❌ No se encontraron cursos</p>";
        exit;
    }
    
    $curso_id = $curso['id'];
    echo "<h3>📚 Curso: {$curso['titulo']} (ID: $curso_id)</h3>";
    echo "<p><strong>Creado:</strong> {$curso['created_at']}</p>";
    
    // Verificar módulos
    $stmt = $pdo->prepare("
        SELECT id, titulo, descripcion, orden 
        FROM modulos 
        WHERE curso_id = ? 
        ORDER BY orden
    ");
    $stmt->execute([$curso_id]);
    $modulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h4>📋 Módulos (" . count($modulos) . "):</h4>";
    if (empty($modulos)) {
        echo "<p style='color: orange;'>⚠️ No hay módulos</p>";
    } else {
        echo "<ul>";
        foreach ($modulos as $modulo) {
            echo "<li><strong>Módulo {$modulo['orden']}:</strong> {$modulo['titulo']} (ID: {$modulo['id']})</li>";
            
            // Verificar temas de este módulo
            $stmt_temas = $pdo->prepare("
                SELECT id, titulo, orden 
                FROM temas 
                WHERE modulo_id = ? 
                ORDER BY orden
            ");
            $stmt_temas->execute([$modulo['id']]);
            $temas = $stmt_temas->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($temas)) {
                echo "<ul>";
                foreach ($temas as $tema) {
                    echo "<li><strong>Tema {$tema['orden']}:</strong> {$tema['titulo']} (ID: {$tema['id']})</li>";
                    
                    // Verificar subtemas
                    $stmt_subtemas = $pdo->prepare("
                        SELECT id, titulo, orden 
                        FROM subtemas 
                        WHERE tema_id = ? 
                        ORDER BY orden
                    ");
                    $stmt_subtemas->execute([$tema['id']]);
                    $subtemas = $stmt_subtemas->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($subtemas)) {
                        echo "<ul>";
                        foreach ($subtemas as $subtema) {
                            echo "<li><strong>Subtema {$subtema['orden']}:</strong> {$subtema['titulo']} (ID: {$subtema['id']})</li>";
                            
                            // Verificar lecciones
                            $stmt_lecciones = $pdo->prepare("
                                SELECT id, titulo, tipo, orden 
                                FROM lecciones 
                                WHERE subtema_id = ? 
                                ORDER BY orden
                            ");
                            $stmt_lecciones->execute([$subtema['id']]);
                            $lecciones = $stmt_lecciones->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (!empty($lecciones)) {
                                echo "<ul>";
                                foreach ($lecciones as $leccion) {
                                    echo "<li><strong>Lección {$leccion['orden']}:</strong> {$leccion['titulo']} ({$leccion['tipo']}) (ID: {$leccion['id']})</li>";
                                }
                                echo "</ul>";
                            }
                        }
                        echo "</ul>";
                    }
                }
                echo "</ul>";
            }
        }
        echo "</ul>";
    }
    
    // Resumen final
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM modulos WHERE curso_id = ?) as total_modulos,
            (SELECT COUNT(*) FROM temas t INNER JOIN modulos m ON t.modulo_id = m.id WHERE m.curso_id = ?) as total_temas,
            (SELECT COUNT(*) FROM subtemas s INNER JOIN temas t ON s.tema_id = t.id INNER JOIN modulos m ON t.modulo_id = m.id WHERE m.curso_id = ?) as total_subtemas,
            (SELECT COUNT(*) FROM lecciones l INNER JOIN subtemas s ON l.subtema_id = s.id INNER JOIN temas t ON s.tema_id = t.id INNER JOIN modulos m ON t.modulo_id = m.id WHERE m.curso_id = ?) as total_lecciones
    ");
    $stmt->execute([$curso_id, $curso_id, $curso_id, $curso_id]);
    $totales = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h4>📊 Resumen:</h4>";
    echo "<ul>";
    echo "<li><strong>Módulos:</strong> {$totales['total_modulos']}</li>";
    echo "<li><strong>Temas:</strong> {$totales['total_temas']}</li>";
    echo "<li><strong>Subtemas:</strong> {$totales['total_subtemas']}</li>";
    echo "<li><strong>Lecciones:</strong> {$totales['total_lecciones']}</li>";
    echo "</ul>";
    
    if ($totales['total_lecciones'] > 0) {
        echo "<p style='color: green;'>✅ <strong>El curso tiene contenido!</strong></p>";
    } else {
        echo "<p style='color: red;'>❌ <strong>El curso está vacío</strong></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>