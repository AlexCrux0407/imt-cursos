<?php
require_once 'config/database.php';

echo "<h2>Verificación de Módulos por Curso</h2>";

try {
    // Primero, mostrar todos los cursos
    echo "<h3>Cursos disponibles:</h3>";
    $stmt_cursos = $conn->prepare("SELECT * FROM cursos ORDER BY id");
    $stmt_cursos->execute();
    $cursos = $stmt_cursos->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Título</th><th>Descripción</th></tr>";
    foreach ($cursos as $curso) {
        echo "<tr>";
        echo "<td>{$curso['id']}</td>";
        echo "<td>" . htmlspecialchars($curso['titulo']) . "</td>";
        echo "<td>" . htmlspecialchars(substr($curso['descripcion'] ?? '', 0, 100)) . "...</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Buscar específicamente el curso de gestión de calidad
    echo "<h3>Buscando curso de Gestión de Calidad:</h3>";
    $stmt_calidad = $conn->prepare("SELECT * FROM cursos WHERE titulo LIKE '%calidad%' OR titulo LIKE '%gestión%' OR titulo LIKE '%gestion%'");
    $stmt_calidad->execute();
    $cursos_calidad = $stmt_calidad->fetchAll();
    
    if ($cursos_calidad) {
        foreach ($cursos_calidad as $curso) {
            echo "<h4>✅ Curso encontrado: {$curso['titulo']} (ID: {$curso['id']})</h4>";
            
            // Mostrar módulos de este curso
            $stmt_modulos = $conn->prepare("SELECT * FROM modulos WHERE curso_id = ? ORDER BY orden");
            $stmt_modulos->execute([$curso['id']]);
            $modulos = $stmt_modulos->fetchAll();
            
            if ($modulos) {
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr><th>ID</th><th>Orden</th><th>Título</th><th>Evaluaciones</th></tr>";
                
                foreach ($modulos as $modulo) {
                    // Contar evaluaciones por módulo
                    $stmt_eval_count = $conn->prepare("SELECT COUNT(*) as total FROM evaluaciones_modulo WHERE modulo_id = ?");
                    $stmt_eval_count->execute([$modulo['id']]);
                    $eval_count = $stmt_eval_count->fetch()['total'];
                    
                    echo "<tr>";
                    echo "<td>{$modulo['id']}</td>";
                    echo "<td>{$modulo['orden']}</td>";
                    echo "<td>" . htmlspecialchars($modulo['titulo']) . "</td>";
                    echo "<td>{$eval_count}</td>";
                    echo "</tr>";
                    
                    // Si es el módulo 3, mostrar más detalles
                    if ($modulo['orden'] == 3) {
                        echo "<tr><td colspan='4' style='background-color: #f0f0f0;'>";
                        echo "<strong>🎯 MÓDULO 3 ENCONTRADO - ID: {$modulo['id']}</strong><br>";
                        echo "Título: " . htmlspecialchars($modulo['titulo']) . "<br>";
                        
                        // Mostrar evaluaciones existentes
                        $stmt_evals = $conn->prepare("SELECT * FROM evaluaciones_modulo WHERE modulo_id = ?");
                        $stmt_evals->execute([$modulo['id']]);
                        $evaluaciones = $stmt_evals->fetchAll();
                        
                        if ($evaluaciones) {
                            echo "Evaluaciones existentes:<br>";
                            foreach ($evaluaciones as $eval) {
                                $activo = $eval['activo'] ? '✅' : '❌';
                                echo "- {$activo} {$eval['titulo']} (ID: {$eval['id']})<br>";
                            }
                        } else {
                            echo "❌ No hay evaluaciones en este módulo<br>";
                        }
                        echo "</td></tr>";
                    }
                }
                echo "</table>";
            } else {
                echo "<p>❌ No hay módulos en este curso</p>";
            }
        }
    } else {
        echo "<p>❌ No se encontró curso de gestión de calidad</p>";
        echo "<p>Mostrando todos los cursos para referencia:</p>";
    }
    
} catch (Exception $e) {
    echo "<h3>❌ Error:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>