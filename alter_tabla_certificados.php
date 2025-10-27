<?php
require_once __DIR__ . '/config/database.php';

function columnExists(PDO $conn, string $table, string $column): bool {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function ensureColumn(PDO $conn, string $table, string $column, string $definition) {
    if (columnExists($conn, $table, $column)) {
        echo "[OK] Columna $column ya existe\n";
        return;
    }
    $sql = "ALTER TABLE $table ADD COLUMN $column $definition";
    $conn->exec($sql);
    echo "[ADDED] Columna $column añadida\n";
}

try {
    $conn->beginTransaction();

    // Posiciones para nombre del curso
    ensureColumn($conn, 'certificados_config', 'curso_x', 'FLOAT NULL');
    ensureColumn($conn, 'certificados_config', 'curso_y', 'FLOAT NULL');

    // Estilos por campo: nombre, curso, calificacion, fecha
    // familia
    ensureColumn($conn, 'certificados_config', 'nombre_font_family', 'VARCHAR(50) NULL');
    ensureColumn($conn, 'certificados_config', 'curso_font_family', 'VARCHAR(50) NULL');
    ensureColumn($conn, 'certificados_config', 'calificacion_font_family', 'VARCHAR(50) NULL');
    ensureColumn($conn, 'certificados_config', 'fecha_font_family', 'VARCHAR(50) NULL');

    // tamaño (pt)
    ensureColumn($conn, 'certificados_config', 'nombre_font_size', 'INT NULL');
    ensureColumn($conn, 'certificados_config', 'curso_font_size', 'INT NULL');
    ensureColumn($conn, 'certificados_config', 'calificacion_font_size', 'INT NULL');
    ensureColumn($conn, 'certificados_config', 'fecha_font_size', 'INT NULL');

    // color (#RRGGBB)
    ensureColumn($conn, 'certificados_config', 'nombre_font_color', 'VARCHAR(7) NULL');
    ensureColumn($conn, 'certificados_config', 'curso_font_color', 'VARCHAR(7) NULL');
    ensureColumn($conn, 'certificados_config', 'calificacion_font_color', 'VARCHAR(7) NULL');
    ensureColumn($conn, 'certificados_config', 'fecha_font_color', 'VARCHAR(7) NULL');

    $conn->commit();
    echo "[DONE] Alteraciones aplicadas correctamente.\n";
} catch (Exception $e) {
    $conn->rollBack();
    echo "[ERROR] " . $e->getMessage() . "\n";
}