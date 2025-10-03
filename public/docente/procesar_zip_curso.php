<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/curso_scanner.php';

header('Content-Type: application/json');

// DEBUGGING: Log del inicio del procesamiento
error_log("=== INICIO PROCESAMIENTO ZIP ===");
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

try {
    // Validar que se recibió el archivo
    if (!isset($_FILES['archivo_zip']) || $_FILES['archivo_zip']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No se recibió un archivo válido');
    }

    // Validar que se recibió el ID del curso
    if (!isset($_POST['curso_id']) || empty($_POST['curso_id'])) {
        throw new Exception('ID de curso no válido');
    }

    $curso_id = (int)$_POST['curso_id'];
    $reemplazar_contenido = isset($_POST['reemplazar_contenido']) && $_POST['reemplazar_contenido'] === '1';

    // Verificar que el curso existe y pertenece al docente
    $stmt = $conn->prepare("
        SELECT c.*, 
               CASE 
                   WHEN EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'cursos' AND COLUMN_NAME = 'asignado_a') 
                   THEN c.asignado_a = :docente_id1 
                   ELSE c.creado_por = :docente_id2 
               END as tiene_acceso
        FROM cursos c 
        WHERE c.id = :curso_id
    ");
    $stmt->execute([
        ':curso_id' => $curso_id, 
        ':docente_id1' => $_SESSION['user_id'],
        ':docente_id2' => $_SESSION['user_id']
    ]);
    $curso = $stmt->fetch();

    if (!$curso || !$curso['tiene_acceso']) {
        throw new Exception('No tienes permisos para modificar este curso');
    }

    // Validar que es un archivo ZIP
    $archivo = $_FILES['archivo_zip'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if ($extension !== 'zip') {
        throw new Exception('El archivo debe ser un ZIP válido');
    }

    // Crear directorio temporal para extraer el ZIP
    $temp_dir = __DIR__ . '/../../uploads/temp/curso_' . $curso_id . '_' . time();
    if (!is_dir(dirname($temp_dir))) {
        mkdir(dirname($temp_dir), 0755, true);
    }
    if (!mkdir($temp_dir, 0755, true)) {
        throw new Exception('No se pudo crear el directorio temporal');
    }

    try {
        // Extraer el ZIP
        $zip = new ZipArchive();
        $result = $zip->open($archivo['tmp_name']);
        
        if ($result !== TRUE) {
            throw new Exception('No se pudo abrir el archivo ZIP: ' . $result);
        }

        if (!$zip->extractTo($temp_dir)) {
            throw new Exception('No se pudo extraer el contenido del ZIP');
        }
        $zip->close();

        // Escanear el contenido extraído usando el scanner existente
        $contenido_escaneado = scanImportedCourse($temp_dir);
        
        // DEBUGGING: Log del contenido escaneado
        error_log("=== DEBUGGING ZIP SCANNER ===");
        error_log("Directorio temporal: " . $temp_dir);
        error_log("Contenido escaneado: " . print_r($contenido_escaneado, true));
        error_log("Número de lecciones encontradas: " . count($contenido_escaneado['lecciones']));

        if (empty($contenido_escaneado['lecciones'])) {
            error_log("ERROR: No se encontraron lecciones en el ZIP");
            throw new Exception('No se encontró contenido válido en el ZIP. Verifica la estructura de carpetas.');
        }

        // Iniciar transacción
        $conn->beginTransaction();

        // Si se debe reemplazar contenido, eliminar el existente
        if ($reemplazar_contenido) {
            // Eliminar lecciones
            $stmt = $conn->prepare("
                DELETE l FROM lecciones l
                INNER JOIN subtemas s ON l.subtema_id = s.id
                INNER JOIN temas t ON s.tema_id = t.id
                INNER JOIN modulos m ON t.modulo_id = m.id
                WHERE m.curso_id = :curso_id
            ");
            $stmt->execute([':curso_id' => $curso_id]);

            // Eliminar subtemas
            $stmt = $conn->prepare("
                DELETE s FROM subtemas s
                INNER JOIN temas t ON s.tema_id = t.id
                INNER JOIN modulos m ON t.modulo_id = m.id
                WHERE m.curso_id = :curso_id
            ");
            $stmt->execute([':curso_id' => $curso_id]);

            // Eliminar temas
            $stmt = $conn->prepare("
                DELETE t FROM temas t
                INNER JOIN modulos m ON t.modulo_id = m.id
                WHERE m.curso_id = :curso_id
            ");
            $stmt->execute([':curso_id' => $curso_id]);

            // Eliminar módulos
            $stmt = $conn->prepare("DELETE FROM modulos WHERE curso_id = :curso_id");
            $stmt->execute([':curso_id' => $curso_id]);
        }

        // Crear directorio de destino para el curso
        $curso_dir = __DIR__ . '/../uploads/cursos/' . sanitizeFileName($curso['titulo']);
        if (!is_dir($curso_dir)) {
            mkdir($curso_dir, 0755, true);
        }

        // Copiar archivos del ZIP al directorio del curso
        copyDirectory($temp_dir, $curso_dir);

        // Procesar el contenido escaneado
        $modulos_creados = [];
        $temas_creados = [];
        $subtemas_creados = [];

        foreach ($contenido_escaneado['lecciones'] as $leccion_data) {
            $modulo_orden = $leccion_data['modulo_orden'];
            $tema_orden = $leccion_data['tema_orden'];
            $subtema_orden = $leccion_data['subtema_orden'];
            $leccion_orden = $leccion_data['leccion_orden'];

            // Crear o obtener módulo
            $modulo_key = $modulo_orden;
            if (!isset($modulos_creados[$modulo_key])) {
                $stmt = $conn->prepare("
                    INSERT INTO modulos (curso_id, titulo, descripcion, orden, created_at) 
                    VALUES (:curso_id, :titulo, :descripcion, :orden, NOW())
                    ON DUPLICATE KEY UPDATE titulo = VALUES(titulo)
                ");
                
                // DEBUGGING: Log de la consulta SQL y parámetros para módulos
                error_log("=== DEBUGGING ZIP PROCESAR - MODULOS ===");
                error_log("SQL Query: INSERT INTO modulos (curso_id, titulo, descripcion, orden, created_at) VALUES (:curso_id, :titulo, :descripcion, :orden, NOW()) ON DUPLICATE KEY UPDATE titulo = VALUES(titulo)");
                $params_modulo = [
                    ':curso_id' => $curso_id,
                    ':titulo' => 'Módulo ' . str_pad($modulo_orden, 2, '0', STR_PAD_LEFT),
                    ':descripcion' => 'Módulo ' . $modulo_orden . ' importado desde ZIP',
                    ':orden' => $modulo_orden
                ];
                error_log("Parámetros módulo: " . print_r($params_modulo, true));
                error_log("Número de parámetros: " . count($params_modulo));
                
                $stmt->execute($params_modulo);
                $modulos_creados[$modulo_key] = $conn->lastInsertId() ?: getExistingModuloId($conn, $curso_id, $modulo_orden);
            }

            // Crear o obtener tema
            $tema_key = $modulo_orden . '_' . $tema_orden;
            if (!isset($temas_creados[$tema_key])) {
                $stmt = $conn->prepare("
                    INSERT INTO temas (modulo_id, titulo, descripcion, orden, created_at) 
                    VALUES (:modulo_id, :titulo, :descripcion, :orden, NOW())
                    ON DUPLICATE KEY UPDATE titulo = VALUES(titulo)
                ");
                
                // DEBUGGING: Log de la consulta SQL y parámetros para temas
                error_log("=== DEBUGGING ZIP PROCESAR - TEMAS ===");
                error_log("SQL Query: INSERT INTO temas (modulo_id, titulo, descripcion, orden, created_at) VALUES (:modulo_id, :titulo, :descripcion, :orden, NOW()) ON DUPLICATE KEY UPDATE titulo = VALUES(titulo)");
                $params_tema = [
                    ':modulo_id' => $modulos_creados[$modulo_key],
                    ':titulo' => 'Tema ' . str_pad($tema_orden, 2, '0', STR_PAD_LEFT),
                    ':descripcion' => 'Tema ' . $tema_orden . ' del módulo ' . $modulo_orden,
                    ':orden' => $tema_orden
                ];
                error_log("Parámetros tema: " . print_r($params_tema, true));
                error_log("Número de parámetros: " . count($params_tema));
                
                $stmt->execute($params_tema);
                $temas_creados[$tema_key] = $conn->lastInsertId() ?: getExistingTemaId($conn, $modulos_creados[$modulo_key], $tema_orden);
            }

            // Crear o obtener subtema
            $subtema_key = $modulo_orden . '_' . $tema_orden . '_' . $subtema_orden;
            if (!isset($subtemas_creados[$subtema_key])) {
                $stmt = $conn->prepare("
                    INSERT INTO subtemas (tema_id, titulo, descripcion, orden, created_at) 
                    VALUES (:tema_id, :titulo, :descripcion, :orden, NOW())
                    ON DUPLICATE KEY UPDATE titulo = VALUES(titulo)
                ");
                
                // DEBUGGING: Log de la consulta SQL y parámetros para subtemas
                error_log("=== DEBUGGING ZIP PROCESAR - SUBTEMAS ===");
                error_log("SQL Query: INSERT INTO subtemas (tema_id, titulo, descripcion, orden, created_at) VALUES (:tema_id, :titulo, :descripcion, :orden, NOW()) ON DUPLICATE KEY UPDATE titulo = VALUES(titulo)");
                $params_subtema = [
                    ':tema_id' => $temas_creados[$tema_key],
                    ':titulo' => 'Subtema ' . str_pad($subtema_orden, 2, '0', STR_PAD_LEFT),
                    ':descripcion' => 'Subtema ' . $subtema_orden . ' del tema ' . $tema_orden,
                    ':orden' => $subtema_orden
                ];
                error_log("Parámetros subtema: " . print_r($params_subtema, true));
                error_log("Número de parámetros: " . count($params_subtema));
                
                $stmt->execute($params_subtema);
                $subtemas_creados[$subtema_key] = $conn->lastInsertId() ?: getExistingSubtemaId($conn, $temas_creados[$tema_key], $subtema_orden);
            }

            // Crear lección
            $stmt = $conn->prepare("
                INSERT INTO lecciones (modulo_id, subtema_id, tema_id, titulo, contenido, tipo, orden, created_at) 
                VALUES (:modulo_id, :subtema_id, :tema_id, :titulo, :contenido, 'documento', :orden, NOW())
                ON DUPLICATE KEY UPDATE 
                    titulo = VALUES(titulo),
                    contenido = VALUES(contenido)
            ");

            // Leer el contenido del archivo HTML
            $archivo_leccion = $curso_dir . '/' . $leccion_data['path'];
            $contenido_html = file_exists($archivo_leccion) ? file_get_contents($archivo_leccion) : '';

            // DEBUGGING: Log de la consulta SQL y parámetros para lecciones
            error_log("=== DEBUGGING ZIP PROCESAR - LECCIONES ===");
            error_log("SQL Query: INSERT INTO lecciones (modulo_id, subtema_id, tema_id, titulo, contenido, tipo, orden, created_at) VALUES (:modulo_id, :subtema_id, :tema_id, :titulo, :contenido, 'documento', :orden, NOW()) ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), contenido = VALUES(contenido)");
            $params_leccion = [
                ':modulo_id' => $modulos_creados[$modulo_key],
                ':subtema_id' => $subtemas_creados[$subtema_key],
                ':tema_id' => $temas_creados[$tema_key],
                ':titulo' => $leccion_data['titulo'],
                ':contenido' => $contenido_html,
                ':orden' => $leccion_orden
            ];
            error_log("Parámetros lección: " . print_r($params_leccion, true));
            error_log("Número de parámetros: " . count($params_leccion));

            $stmt->execute($params_leccion);
        }

        // Confirmar transacción
        $conn->commit();
        
        // DEBUGGING: Confirmar que la transacción se completó
        error_log("=== TRANSACCIÓN COMPLETADA EXITOSAMENTE ===");
        error_log("Curso ID: " . $curso_id);
        error_log("Lecciones procesadas: " . count($contenido_escaneado['lecciones']));

        // Limpiar directorio temporal
        removeDirectory($temp_dir);

        echo json_encode([
            'success' => true,
            'message' => 'Contenido importado exitosamente',
            'lecciones_procesadas' => count($contenido_escaneado['lecciones'])
        ]);

    } catch (Exception $e) {
        // Limpiar directorio temporal en caso de error
        if (is_dir($temp_dir)) {
            removeDirectory($temp_dir);
        }
        throw $e;
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    
    // DEBUGGING: Log detallado del error
    error_log("=== ERROR EN PROCESAMIENTO ZIP ===");
    error_log("Tipo de excepción: " . get_class($e));
    error_log("Mensaje: " . $e->getMessage());
    error_log("Código: " . $e->getCode());
    error_log("Archivo: " . $e->getFile());
    error_log("Línea: " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Funciones auxiliares
function sanitizeFileName($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '-', $filename);
    $filename = preg_replace('/-+/', '-', $filename);
    return trim($filename, '-');
}

function copyDirectory($src, $dst) {
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        // Obtener la ruta relativa manualmente
        $itemPath = $item->getPathname();
        $relativePath = substr($itemPath, strlen($src) + 1);
        $target = $dst . DIRECTORY_SEPARATOR . $relativePath;
        
        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0755, true);
            }
        } else {
            copy($item, $target);
        }
    }
}

function removeDirectory($dir) {
    if (!is_dir($dir)) return;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item);
        } else {
            unlink($item);
        }
    }
    rmdir($dir);
}

function getExistingModuloId($conn, $curso_id, $orden) {
    $stmt = $conn->prepare("SELECT id FROM modulos WHERE curso_id = :curso_id AND orden = :orden");
    $stmt->execute([':curso_id' => $curso_id, ':orden' => $orden]);
    $result = $stmt->fetch();
    return $result ? $result['id'] : null;
}

function getExistingTemaId($conn, $modulo_id, $orden) {
    $stmt = $conn->prepare("SELECT id FROM temas WHERE modulo_id = :modulo_id AND orden = :orden");
    $stmt->execute([':modulo_id' => $modulo_id, ':orden' => $orden]);
    $result = $stmt->fetch();
    return $result ? $result['id'] : null;
}

function getExistingSubtemaId($conn, $tema_id, $orden) {
    $stmt = $conn->prepare("SELECT id FROM subtemas WHERE tema_id = :tema_id AND orden = :orden");
    $stmt->execute([':tema_id' => $tema_id, ':orden' => $orden]);
    $result = $stmt->fetch();
    return $result ? $result['id'] : null;
}
?>