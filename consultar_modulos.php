<?php
require_once 'config/database.php';

try {
    // Buscar el curso de gestión de calidad IMT
    $stmt = $conn->prepare("SELECT id, titulo FROM cursos WHERE titulo LIKE '%gestión%' OR titulo LIKE '%calidad%' OR titulo LIKE '%IMT%' ORDER BY id");
    $stmt->execute();
    $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== CURSOS ENCONTRADOS ===\n";
    foreach ($cursos as $curso) {
        echo "ID: {$curso['id']}, Título: {$curso['titulo']}\n";
        
        // Obtener módulos de este curso
        $stmt_modulos = $conn->prepare("SELECT id, titulo, orden FROM modulos WHERE curso_id = ? ORDER BY orden");
        $stmt_modulos->execute([$curso['id']]);
        $modulos = $stmt_modulos->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($modulos)) {
            echo "  MÓDULOS:\n";
            foreach ($modulos as $modulo) {
                echo "    Módulo {$modulo['orden']}: ID {$modulo['id']} - {$modulo['titulo']}\n";
            }
        } else {
            echo "    Sin módulos\n";
        }
        echo "\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>