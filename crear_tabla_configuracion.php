<?php
require_once 'config/database.php';

try {
    // Crear tabla configuracion_plataforma
    $stmt = $conn->prepare('
        CREATE TABLE IF NOT EXISTS configuracion_plataforma (
            id INT PRIMARY KEY,
            nombre_plataforma VARCHAR(255) NOT NULL DEFAULT "IMT Cursos",
            logo_header VARCHAR(255) NOT NULL DEFAULT "Logo_IMT.png",
            logo_footer VARCHAR(255) NOT NULL DEFAULT "Logo_blanco.png",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ');
    $stmt->execute();
    
    // Insertar configuración por defecto
    $stmt = $conn->prepare('
        INSERT IGNORE INTO configuracion_plataforma (id, nombre_plataforma, logo_header, logo_footer, created_at) 
        VALUES (1, "IMT Cursos", "Logo_IMT.png", "Logo_blanco.png", NOW())
    ');
    $stmt->execute();
    
    echo "✅ Tabla configuracion_plataforma creada e inicializada correctamente\n";
    echo "📋 Configuración por defecto:\n";
    echo "   - Nombre: IMT Cursos\n";
    echo "   - Logo Header: Logo_IMT.png\n";
    echo "   - Logo Footer: Logo_blanco.png\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>