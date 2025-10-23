<?php
require_once __DIR__ . '/config/database.php';

function execSql($pdo, $sql) {
    try {
        $pdo->exec($sql);
        echo "OK: statement executed\n";
    } catch (Throwable $e) {
        echo "ERR: " . $e->getMessage() . "\n";
        exit(1);
    }
}

$usuarios = <<<SQL
CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  usuario VARCHAR(100) NOT NULL UNIQUE,
  telefono VARCHAR(50) DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('estudiante','docente','master','ejecutivo') NOT NULL,
  estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

$cursos = <<<SQL
CREATE TABLE IF NOT EXISTS cursos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(255) NOT NULL,
  descripcion TEXT,
  estado ENUM('activo','borrador','inactivo') NOT NULL DEFAULT 'activo',
  creado_por INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (creado_por),
  FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

$inscripciones = <<<SQL
CREATE TABLE IF NOT EXISTS inscripciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  curso_id INT NOT NULL,
  estado ENUM('activo','completado','cancelado') NOT NULL DEFAULT 'activo',
  progreso DECIMAL(5,2) DEFAULT 0,
  fecha_inscripcion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_completado DATETIME DEFAULT NULL,
  INDEX (usuario_id),
  INDEX (curso_id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

execSql($pdo, $usuarios);
execSql($pdo, $cursos);
execSql($pdo, $inscripciones);

echo "DB init complete\n";