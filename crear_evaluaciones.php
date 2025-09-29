<?php
require_once __DIR__ . '/config/database.php';

try {
    echo "Creando tablas para el sistema de evaluaciones...\n";
    
    // Tabla de evaluaciones por módulo
    $sql_evaluaciones = "
    CREATE TABLE IF NOT EXISTS evaluaciones_modulo (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        modulo_id INT UNSIGNED NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        descripcion TEXT,
        tipo ENUM('examen', 'tarea', 'proyecto', 'quiz') NOT NULL DEFAULT 'examen',
        puntaje_maximo DECIMAL(5,2) NOT NULL DEFAULT 100.00,
        puntaje_minimo_aprobacion DECIMAL(5,2) NOT NULL DEFAULT 70.00,
        tiempo_limite INT NULL COMMENT 'Tiempo límite en minutos, NULL = sin límite',
        intentos_permitidos INT NOT NULL DEFAULT 1,
        fecha_inicio DATETIME NULL,
        fecha_fin DATETIME NULL,
        activo BOOLEAN NOT NULL DEFAULT TRUE,
        obligatorio BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Si es obligatorio para completar el módulo',
        orden INT NOT NULL DEFAULT 1,
        instrucciones TEXT,
        recursos_permitidos TEXT COMMENT 'JSON con recursos permitidos durante la evaluación',
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE CASCADE,
        INDEX idx_modulo_evaluaciones (modulo_id),
        INDEX idx_evaluaciones_activas (activo, obligatorio)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $conn->exec($sql_evaluaciones);
    echo "✓ Tabla 'evaluaciones_modulo' creada exitosamente.\n";
    
    // Tabla de preguntas para evaluaciones
    $sql_preguntas = "
    CREATE TABLE IF NOT EXISTS preguntas_evaluacion (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        evaluacion_id INT UNSIGNED NOT NULL,
        pregunta TEXT NOT NULL,
        tipo ENUM('multiple_choice', 'verdadero_falso', 'texto_corto', 'texto_largo', 'seleccion_multiple') NOT NULL,
        opciones JSON NULL COMMENT 'Opciones para preguntas de opción múltiple',
        respuesta_correcta TEXT NULL COMMENT 'Respuesta correcta o clave de respuesta',
        puntaje DECIMAL(5,2) NOT NULL DEFAULT 1.00,
        orden INT NOT NULL DEFAULT 1,
        explicacion TEXT NULL COMMENT 'Explicación de la respuesta correcta',
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones_modulo(id) ON DELETE CASCADE,
        INDEX idx_evaluacion_preguntas (evaluacion_id, orden)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $conn->exec($sql_preguntas);
    echo "✓ Tabla 'preguntas_evaluacion' creada exitosamente.\n";
    
    // Tabla de intentos de evaluación por estudiante
    $sql_intentos = "
    CREATE TABLE IF NOT EXISTS intentos_evaluacion (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        evaluacion_id INT UNSIGNED NOT NULL,
        usuario_id INT UNSIGNED NOT NULL,
        numero_intento INT NOT NULL DEFAULT 1,
        fecha_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_fin TIMESTAMP NULL,
        tiempo_transcurrido INT NULL COMMENT 'Tiempo en segundos',
        puntaje_obtenido DECIMAL(5,2) NULL,
        puntaje_maximo DECIMAL(5,2) NOT NULL,
        aprobado BOOLEAN NULL COMMENT 'NULL = en progreso, TRUE = aprobado, FALSE = reprobado',
        respuestas JSON NULL COMMENT 'Respuestas del estudiante',
        estado ENUM('en_progreso', 'completado', 'abandonado', 'tiempo_agotado') NOT NULL DEFAULT 'en_progreso',
        comentarios_docente TEXT NULL,
        fecha_revision TIMESTAMP NULL,
        revisado_por INT UNSIGNED NULL COMMENT 'ID del docente que revisó',
        FOREIGN KEY (evaluacion_id) REFERENCES evaluaciones_modulo(id) ON DELETE CASCADE,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (revisado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
        UNIQUE KEY unique_intento (evaluacion_id, usuario_id, numero_intento),
        INDEX idx_estudiante_intentos (usuario_id, estado),
        INDEX idx_evaluacion_intentos (evaluacion_id, estado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $conn->exec($sql_intentos);
    echo "✓ Tabla 'intentos_evaluacion' creada exitosamente.\n";
    
    // Tabla de progreso de módulos actualizada para incluir evaluaciones
    $sql_progreso_modulos_update = "
    ALTER TABLE progreso_modulos 
    ADD COLUMN evaluacion_completada BOOLEAN NOT NULL DEFAULT FALSE AFTER completado,
    ADD COLUMN fecha_evaluacion_completada TIMESTAMP NULL AFTER fecha_completado,
    ADD COLUMN puntaje_evaluacion DECIMAL(5,2) NULL AFTER fecha_evaluacion_completada;
    ";
    
    try {
        $conn->exec($sql_progreso_modulos_update);
        echo "✓ Tabla 'progreso_modulos' actualizada con campos de evaluación.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "✓ Tabla 'progreso_modulos' ya tiene los campos de evaluación.\n";
        } else {
            throw $e;
        }
    }
    
    // Tabla de configuración de evaluaciones por curso
    $sql_config_evaluaciones = "
    CREATE TABLE IF NOT EXISTS configuracion_evaluaciones (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        curso_id INT UNSIGNED NOT NULL,
        requiere_evaluaciones BOOLEAN NOT NULL DEFAULT TRUE,
        puntaje_minimo_curso DECIMAL(5,2) NOT NULL DEFAULT 70.00,
        permitir_reintentos BOOLEAN NOT NULL DEFAULT TRUE,
        mostrar_resultados_inmediatos BOOLEAN NOT NULL DEFAULT FALSE,
        mostrar_respuestas_correctas BOOLEAN NOT NULL DEFAULT FALSE,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
        UNIQUE KEY unique_curso_config (curso_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $conn->exec($sql_config_evaluaciones);
    echo "✓ Tabla 'configuracion_evaluaciones' creada exitosamente.\n";
    
    echo "\n🎉 Todas las tablas para el sistema de evaluaciones han sido creadas exitosamente.\n";
    echo "\nTablas creadas:\n";
    echo "- evaluaciones_modulo: Evaluaciones/actividades por módulo\n";
    echo "- preguntas_evaluacion: Preguntas de las evaluaciones\n";
    echo "- intentos_evaluacion: Intentos de evaluación por estudiante\n";
    echo "- configuracion_evaluaciones: Configuración de evaluaciones por curso\n";
    echo "- progreso_modulos: Actualizada con campos de evaluación\n";
    
} catch (PDOException $e) {
    echo "❌ Error al crear las tablas: " . $e->getMessage() . "\n";
    exit(1);
}
?>