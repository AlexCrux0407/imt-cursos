<?php
require_once __DIR__ . '/config/database.php';

try {
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS certificados_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  curso_id INT NOT NULL UNIQUE,
  template_path VARCHAR(255) DEFAULT NULL,
  template_mime VARCHAR(50) DEFAULT NULL,
  nombre_x FLOAT DEFAULT NULL,
  nombre_y FLOAT DEFAULT NULL,
  calificacion_x FLOAT DEFAULT NULL,
  calificacion_y FLOAT DEFAULT NULL,
  fecha_x FLOAT DEFAULT NULL,
  fecha_y FLOAT DEFAULT NULL,
  mostrar_calificacion TINYINT(1) NOT NULL DEFAULT 0,
  font_family VARCHAR(100) NOT NULL DEFAULT 'helvetica',
  font_size INT NOT NULL DEFAULT 24,
  font_color VARCHAR(7) NOT NULL DEFAULT '#000000',
  valid_days INT NOT NULL DEFAULT 15,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (curso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

    $conn->exec($sql);
    echo "Tabla certificados_config creada o ya existente.\n";
    echo "OK\n";
} catch (Throwable $e) {
    echo "Error creando tabla certificados_config: " . $e->getMessage() . "\n";
    exit(1);
}