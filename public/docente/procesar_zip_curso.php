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
    $hasColumn = function (string $table, string $column) use ($conn): bool {
        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND COLUMN_NAME = :column
        ");
        $stmt->execute([':table' => $table, ':column' => $column]);
        return (int)$stmt->fetchColumn() > 0;
    };
    $leccionesHasContenidoEmbebido = $hasColumn('lecciones', 'contenido_embebido');
    $detectaEmbebido = function (string $html): bool {
        if (preg_match('/<(img|video|audio|iframe|object|embed)\b/i', $html)) {
            return true;
        }
        if (preg_match('/\.(pdf|mp4|mp3|png|jpe?g|gif|webp)\b/i', $html)) {
            return true;
        }
        return false;
    };

    // Verificar que el curso existe y pertenece al docente
    $stmt = $conn->prepare("
        SELECT c.*, 
               CASE 
                   WHEN EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'cursos' AND COLUMN_NAME = 'asignado_a') 
                   THEN c.asignado_a = :docente_id1 
                   ELSE c.creado_por = :docente_id 
               END as tiene_acceso
        FROM cursos c 
        WHERE c.id = :curso_id
    ");
    $stmt->execute([
        ':curso_id' => $curso_id,
        ':docente_id1' => $_SESSION['user_id'],
        ':docente_id' => $_SESSION['user_id']
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
        if (!class_exists('ZipArchive')) {
            throw new Exception('La extensión ZIP no está habilitada en el servidor');
        }

        // Extraer el ZIP
        $zip = new ZipArchive();
        $result = $zip->open($archivo['tmp_name']);

        if ($result !== TRUE) {
            throw new Exception('No se pudo abrir el archivo ZIP: ' . $result);
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false) {
                continue;
            }
            $stat = $zip->statIndex($i);
            $entry = str_replace('\\', '/', $entry);
            $destPath = $temp_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry);
            $isDir = substr($entry, -1) === '/';
            if (!$isDir && $stat && ($stat['size'] ?? 0) === 0 && ($stat['crc'] ?? 1) === 0 && !preg_match('/\.[^\/]+$/', $entry)) {
                $isDir = true;
            }
            if ($isDir) {
                if (!is_dir($destPath)) {
                    if (file_exists($destPath)) {
                        throw new Exception('No se pudo crear directorio: ' . $entry);
                    }
                    mkdir($destPath, 0755, true);
                }
                continue;
            }
            $dir = dirname($destPath);
            if ($dir !== '.' && !is_dir($dir)) {
                if (file_exists($dir)) {
                    throw new Exception('No se pudo crear directorio: ' . $entry);
                }
                mkdir($dir, 0755, true);
            }
            $stream = $zip->getStream($entry);
            if ($stream === false) {
                if (!is_dir($destPath)) {
                    if (file_exists($destPath)) {
                        throw new Exception('No se pudo crear directorio: ' . $entry);
                    }
                    mkdir($destPath, 0755, true);
                }
                continue;
            }
            $out = fopen($destPath, 'wb');
            if ($out === false) {
                throw new Exception('No se pudo escribir un archivo del ZIP: ' . $entry);
            }
            while (!feof($stream)) {
                $data = fread($stream, 8192);
                if ($data === false) {
                    break;
                }
                fwrite($out, $data);
            }
            fclose($stream);
            fclose($out);
        }
        $zip->close();

        // Escanear el contenido extraído usando el scanner existente
        $contenido_escaneado = scanImportedCourse($temp_dir);

        // DEBUGGING: Log del contenido escaneado
        error_log("=== DEBUGGING ZIP SCANNER ===");
        error_log("Directorio temporal: " . $temp_dir);
        error_log("Contenido escaneado: " . print_r($contenido_escaneado, true));
        error_log("Número de lecciones encontradas: " . count($contenido_escaneado['lecciones'] ?? []));
        error_log("Módulos detectados: " . count($contenido_escaneado['modulos_detectados'] ?? []));
        error_log("Temas detectados: " . count($contenido_escaneado['temas_detectados'] ?? []));
        error_log("Subtemas detectados: " . count($contenido_escaneado['subtemas_detectados'] ?? []));

        $hayContenido = false;

        if (!empty($contenido_escaneado['lecciones'])
            || !empty($contenido_escaneado['modulos_detectados'])
            || !empty($contenido_escaneado['temas_detectados'])
            || !empty($contenido_escaneado['subtemas_detectados'])
        ) {
            $hayContenido = true;
        }

        $contenido_base = $temp_dir . DIRECTORY_SEPARATOR . 'contenido';
        if (is_dir($contenido_base)) {
            $dirs = scandir($contenido_base);
            foreach ($dirs as $d) {
                if ($d === '.' || $d === '..') continue;
                if (is_file($contenido_base . DIRECTORY_SEPARATOR . $d . DIRECTORY_SEPARATOR . 'index.html')) {
                    $hayContenido = true;
                    break;
                }
            }
        }

        if (!$hayContenido) {
            error_log("ERROR: No se encontró contenido válido en el ZIP");
            throw new Exception('No se encontró contenido válido en el ZIP.');
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
        $uploads_base = 'uploads/cursos/' . sanitizeFileName($curso['titulo']) . '/';

        // Copiar archivos del ZIP al directorio del curso
        copyDirectory($temp_dir, $curso_dir);

        // Procesar el contenido escaneado
        $modulos_creados = [];
        $temas_creados = [];
        $subtemas_creados = [];
        $modulo_dirs = [];
        $tema_dirs = [];
        $subtema_dirs = [];
        $contenido_base = $curso_dir . DIRECTORY_SEPARATOR . 'contenido';

        $modulos_detectados = $contenido_escaneado['modulos_detectados'] ?? [];
        $temas_detectados = $contenido_escaneado['temas_detectados'] ?? [];
        $subtemas_detectados = $contenido_escaneado['subtemas_detectados'] ?? [];

        foreach ($modulos_detectados as $modulo_data) {
            $modulo_orden = (int)$modulo_data['modulo_orden'];
            $modulo_key = $modulo_orden;
            if (isset($modulos_creados[$modulo_key])) {
                continue;
            }
            if (!empty($modulo_data['dir'])) {
                $modulo_dirs[$modulo_key] = $modulo_data['dir'];
            }
            $modulo_recurso_url = '';
            if (!empty($modulo_data['index_path'])) {
                $modulo_recurso_url = $uploads_base . ltrim($modulo_data['index_path'], '/\\');
            } else {
                $modulo_dir = $modulo_dirs[$modulo_key] ?? findDirByOrder($contenido_base, 'modulo', $modulo_orden);
                if ($modulo_dir !== null) {
                    $modulo_dirs[$modulo_key] = $modulo_dir;
                    $modulo_index_path = $contenido_base . DIRECTORY_SEPARATOR . $modulo_dir . DIRECTORY_SEPARATOR . 'index.html';
                    if (is_file($modulo_index_path)) {
                        $modulo_recurso_url = $uploads_base . makeRelativePath($curso_dir, $modulo_index_path);
                    }
                }
            }
            $stmt = $conn->prepare("
                INSERT INTO modulos (curso_id, titulo, descripcion, orden, recurso_url, created_at) 
                VALUES (:curso_id, :titulo, :descripcion, :orden, :recurso_url, NOW())
                ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), recurso_url = VALUES(recurso_url)
            ");
            $params_modulo = [
                ':curso_id' => $curso_id,
                ':titulo' => 'Módulo ' . str_pad($modulo_orden, 2, '0', STR_PAD_LEFT),
                ':descripcion' => 'Módulo ' . $modulo_orden . ' importado desde ZIP',
                ':orden' => $modulo_orden,
                ':recurso_url' => $modulo_recurso_url
            ];
            $stmt->execute($params_modulo);
            $modulos_creados[$modulo_key] = $conn->lastInsertId() ?: getExistingModuloId($conn, $curso_id, $modulo_orden);
        }

        foreach ($temas_detectados as $tema_data) {
            $modulo_orden = (int)$tema_data['modulo_orden'];
            $tema_orden = (int)$tema_data['tema_orden'];
            $modulo_key = $modulo_orden;
            $tema_key = $modulo_orden . '_' . $tema_orden;

            if (!isset($modulos_creados[$modulo_key])) {
                $modulo_dir = $modulo_dirs[$modulo_key] ?? findDirByOrder($contenido_base, 'modulo', $modulo_orden);
                if ($modulo_dir !== null) {
                    $modulo_dirs[$modulo_key] = $modulo_dir;
                }
                $modulo_recurso_url = '';
                if ($modulo_dir) {
                    $modulo_index_path = $contenido_base . DIRECTORY_SEPARATOR . $modulo_dir . DIRECTORY_SEPARATOR . 'index.html';
                    if (is_file($modulo_index_path)) {
                        $modulo_recurso_url = $uploads_base . makeRelativePath($curso_dir, $modulo_index_path);
                    }
                }
                $stmt = $conn->prepare("
                    INSERT INTO modulos (curso_id, titulo, descripcion, orden, recurso_url, created_at) 
                    VALUES (:curso_id, :titulo, :descripcion, :orden, :recurso_url, NOW())
                    ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), recurso_url = VALUES(recurso_url)
                ");
                $params_modulo = [
                    ':curso_id' => $curso_id,
                    ':titulo' => 'Módulo ' . str_pad($modulo_orden, 2, '0', STR_PAD_LEFT),
                    ':descripcion' => 'Módulo ' . $modulo_orden . ' importado desde ZIP',
                    ':orden' => $modulo_orden,
                    ':recurso_url' => $modulo_recurso_url
                ];
                $stmt->execute($params_modulo);
                $modulos_creados[$modulo_key] = $conn->lastInsertId() ?: getExistingModuloId($conn, $curso_id, $modulo_orden);
            }

            if (isset($temas_creados[$tema_key])) {
                continue;
            }

            $modulo_dir = $modulo_dirs[$modulo_key] ?? findDirByOrder($contenido_base, 'modulo', $modulo_orden);
            if ($modulo_dir !== null) {
                $modulo_dirs[$modulo_key] = $modulo_dir;
            }
            if (!empty($tema_data['dir'])) {
                $tema_dirs[$tema_key] = $tema_data['dir'];
            }
            $tema_dir = $tema_dirs[$tema_key] ?? null;
            if (!$tema_dir && $modulo_dir) {
                $tema_dir = findDirByOrder($contenido_base . DIRECTORY_SEPARATOR . $modulo_dir, 'tema', $tema_orden);
                if ($tema_dir !== null) {
                    $tema_dirs[$tema_key] = $tema_dir;
                }
            }
            $tema_recurso_url = '';
            if (!empty($tema_data['index_path'])) {
                $tema_recurso_url = $uploads_base . ltrim($tema_data['index_path'], '/\\');
            } elseif (!empty($modulo_dir) && !empty($tema_dir)) {
                $tema_index_path = $contenido_base . DIRECTORY_SEPARATOR . $modulo_dir . DIRECTORY_SEPARATOR . $tema_dir . DIRECTORY_SEPARATOR . 'index.html';
                if (is_file($tema_index_path)) {
                    $tema_recurso_url = $uploads_base . makeRelativePath($curso_dir, $tema_index_path);
                }
            }
            $stmt = $conn->prepare("
                INSERT INTO temas (modulo_id, titulo, descripcion, orden, recurso_url, created_at) 
                VALUES (:modulo_id, :titulo, :descripcion, :orden, :recurso_url, NOW())
                ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), recurso_url = VALUES(recurso_url)
            ");
            $params_tema = [
                ':modulo_id' => $modulos_creados[$modulo_key],
                ':titulo' => 'Tema ' . str_pad($tema_orden, 2, '0', STR_PAD_LEFT),
                ':descripcion' => 'Tema ' . $tema_orden . ' del módulo ' . $modulo_orden,
                ':orden' => $tema_orden,
                ':recurso_url' => $tema_recurso_url
            ];
            $stmt->execute($params_tema);
            $temas_creados[$tema_key] = $conn->lastInsertId() ?: getExistingTemaId($conn, $modulos_creados[$modulo_key], $tema_orden);
        }

        foreach ($subtemas_detectados as $subtema_data) {
            $modulo_orden = (int)$subtema_data['modulo_orden'];
            $tema_orden = (int)$subtema_data['tema_orden'];
            $subtema_orden = (int)$subtema_data['subtema_orden'];
            $modulo_key = $modulo_orden;
            $tema_key = $modulo_orden . '_' . $tema_orden;
            $subtema_key = $tema_key . '_' . $subtema_orden;

            if (!isset($modulos_creados[$modulo_key])) {
                $modulo_dir = $modulo_dirs[$modulo_key] ?? findDirByOrder($contenido_base, 'modulo', $modulo_orden);
                if ($modulo_dir !== null) {
                    $modulo_dirs[$modulo_key] = $modulo_dir;
                }
                $modulo_recurso_url = '';
                if ($modulo_dir) {
                    $modulo_index_path = $contenido_base . DIRECTORY_SEPARATOR . $modulo_dir . DIRECTORY_SEPARATOR . 'index.html';
                    if (is_file($modulo_index_path)) {
                        $modulo_recurso_url = $uploads_base . makeRelativePath($curso_dir, $modulo_index_path);
                    }
                }
                $stmt = $conn->prepare("
                    INSERT INTO modulos (curso_id, titulo, descripcion, orden, recurso_url, created_at) 
                    VALUES (:curso_id, :titulo, :descripcion, :orden, :recurso_url, NOW())
                    ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), recurso_url = VALUES(recurso_url)
                ");
                $params_modulo = [
                    ':curso_id' => $curso_id,
                    ':titulo' => 'Módulo ' . str_pad($modulo_orden, 2, '0', STR_PAD_LEFT),
                    ':descripcion' => 'Módulo ' . $modulo_orden . ' importado desde ZIP',
                    ':orden' => $modulo_orden,
                    ':recurso_url' => $modulo_recurso_url
                ];
                $stmt->execute($params_modulo);
                $modulos_creados[$modulo_key] = $conn->lastInsertId() ?: getExistingModuloId($conn, $curso_id, $modulo_orden);
            }

            if (!isset($temas_creados[$tema_key])) {
                $modulo_dir = $modulo_dirs[$modulo_key] ?? findDirByOrder($contenido_base, 'modulo', $modulo_orden);
                if ($modulo_dir !== null) {
                    $modulo_dirs[$modulo_key] = $modulo_dir;
                }
                $tema_dir = $tema_dirs[$tema_key] ?? null;
                if (!$tema_dir && $modulo_dir) {
                    $tema_dir = findDirByOrder($contenido_base . DIRECTORY_SEPARATOR . $modulo_dir, 'tema', $tema_orden);
                    if ($tema_dir !== null) {
                        $tema_dirs[$tema_key] = $tema_dir;
                    }
                }
                $tema_recurso_url = '';
                if (!empty($modulo_dir) && !empty($tema_dir)) {
                    $tema_index_path = $contenido_base . DIRECTORY_SEPARATOR . $modulo_dir . DIRECTORY_SEPARATOR . $tema_dir . DIRECTORY_SEPARATOR . 'index.html';
                    if (is_file($tema_index_path)) {
                        $tema_recurso_url = $uploads_base . makeRelativePath($curso_dir, $tema_index_path);
                    }
                }
                $stmt = $conn->prepare("
                    INSERT INTO temas (modulo_id, titulo, descripcion, orden, recurso_url, created_at) 
                    VALUES (:modulo_id, :titulo, :descripcion, :orden, :recurso_url, NOW())
                    ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), recurso_url = VALUES(recurso_url)
                ");
                $params_tema = [
                    ':modulo_id' => $modulos_creados[$modulo_key],
                    ':titulo' => 'Tema ' . str_pad($tema_orden, 2, '0', STR_PAD_LEFT),
                    ':descripcion' => 'Tema ' . $tema_orden . ' del módulo ' . $modulo_orden,
                    ':orden' => $tema_orden,
                    ':recurso_url' => $tema_recurso_url
                ];
                $stmt->execute($params_tema);
                $temas_creados[$tema_key] = $conn->lastInsertId() ?: getExistingTemaId($conn, $modulos_creados[$modulo_key], $tema_orden);
            }

            if (isset($subtemas_creados[$subtema_key])) {
                continue;
            }

            $modulo_dir = $modulo_dirs[$modulo_key] ?? findDirByOrder($contenido_base, 'modulo', $modulo_orden);
            if ($modulo_dir !== null) {
                $modulo_dirs[$modulo_key] = $modulo_dir;
            }
            $tema_dir = $tema_dirs[$tema_key] ?? null;
            if (!$tema_dir && $modulo_dir) {
                $tema_dir = findDirByOrder($contenido_base . DIRECTORY_SEPARATOR . $modulo_dir, 'tema', $tema_orden);
                if ($tema_dir !== null) {
                    $tema_dirs[$tema_key] = $tema_dir;
                }
            }
            if (!empty($subtema_data['dir'])) {
                $subtema_dirs[$subtema_key] = $subtema_data['dir'];
            }
            $subtema_dir = $subtema_dirs[$subtema_key] ?? null;
            if (!$subtema_dir && $modulo_dir && $tema_dir) {
                $subtema_dir = findDirByOrder($contenido_base . DIRECTORY_SEPARATOR . $modulo_dir . DIRECTORY_SEPARATOR . $tema_dir, 'subtema', $subtema_orden);
                if ($subtema_dir !== null) {
                    $subtema_dirs[$subtema_key] = $subtema_dir;
                }
            }
            $subtema_recurso_url = '';
            if (!empty($subtema_data['index_path'])) {
                $subtema_recurso_url = $uploads_base . ltrim($subtema_data['index_path'], '/\\');
            } elseif (!empty($modulo_dir) && !empty($tema_dir) && !empty($subtema_dir)) {
                $subtema_index_path = $contenido_base . DIRECTORY_SEPARATOR . $modulo_dir . DIRECTORY_SEPARATOR . $tema_dir . DIRECTORY_SEPARATOR . $subtema_dir . DIRECTORY_SEPARATOR . 'index.html';
                if (is_file($subtema_index_path)) {
                    $subtema_recurso_url = $uploads_base . makeRelativePath($curso_dir, $subtema_index_path);
                }
            }
            $stmt = $conn->prepare("
                INSERT INTO subtemas (tema_id, titulo, descripcion, orden, recurso_url, created_at) 
                VALUES (:tema_id, :titulo, :descripcion, :orden, :recurso_url, NOW())
                ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), recurso_url = VALUES(recurso_url)
            ");
            $params_subtema = [
                ':tema_id' => $temas_creados[$tema_key],
                ':titulo' => 'Subtema ' . str_pad($subtema_orden, 2, '0', STR_PAD_LEFT),
                ':descripcion' => 'Subtema ' . $subtema_orden . ' del tema ' . $tema_orden,
                ':orden' => $subtema_orden,
                ':recurso_url' => $subtema_recurso_url
            ];
            $stmt->execute($params_subtema);
            $subtemas_creados[$subtema_key] = $conn->lastInsertId() ?: getExistingSubtemaId($conn, $temas_creados[$tema_key], $subtema_orden);
        }

        foreach ($contenido_escaneado['lecciones'] ?? [] as $leccion_data) {
            $modulo_orden = $leccion_data['modulo_orden'];
            $tema_orden = $leccion_data['tema_orden'];
            $subtema_orden = $leccion_data['subtema_orden'];
            $leccion_orden = $leccion_data['leccion_orden'];

            // Crear o obtener módulo
            $modulo_key = $modulo_orden;
            if (!isset($modulos_creados[$modulo_key])) {
                $modulo_dir = $modulo_dirs[$modulo_key] ?? findDirByOrder($contenido_base, 'modulo', $modulo_orden);
                if ($modulo_dir !== null) {
                    $modulo_dirs[$modulo_key] = $modulo_dir;
                }
                $modulo_recurso_url = '';
                if (!empty($modulo_dir)) {
                    $modulo_index_path = $contenido_base . DIRECTORY_SEPARATOR . $modulo_dir . DIRECTORY_SEPARATOR . 'index.html';
                    if (is_file($modulo_index_path)) {
                        $modulo_recurso_url = $uploads_base . makeRelativePath($curso_dir, $modulo_index_path);
                    }
                }
                $stmt = $conn->prepare("
                    INSERT INTO modulos (curso_id, titulo, descripcion, orden, recurso_url, created_at) 
                    VALUES (:curso_id, :titulo, :descripcion, :orden, :recurso_url, NOW())
                    ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), recurso_url = VALUES(recurso_url)
                ");

                // DEBUGGING: Log de la consulta SQL y parámetros para módulos
                error_log("=== DEBUGGING ZIP PROCESAR - MODULOS ===");
                error_log("SQL Query: INSERT INTO modulos (curso_id, titulo, descripcion, orden, recurso_url, created_at) VALUES (:curso_id, :titulo, :descripcion, :orden, :recurso_url, NOW()) ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), recurso_url = VALUES(recurso_url)");
                $params_modulo = [
                    ':curso_id' => $curso_id,
                    ':titulo' => 'Módulo ' . str_pad($modulo_orden, 2, '0', STR_PAD_LEFT),
                    ':descripcion' => 'Módulo ' . $modulo_orden . ' importado desde ZIP',
                    ':orden' => $modulo_orden,
                    ':recurso_url' => $modulo_recurso_url
                ];
                error_log("Parámetros módulo: " . print_r($params_modulo, true));
                error_log("Número de parámetros: " . count($params_modulo));

                $stmt->execute($params_modulo);
                $modulos_creados[$modulo_key] = $conn->lastInsertId() ?: getExistingModuloId($conn, $curso_id, $modulo_orden);
            }

            // Crear o obtener tema
            $tema_key = $modulo_orden . '_' . $tema_orden;
            if (!isset($temas_creados[$tema_key])) {
                $modulo_dir = $modulo_dirs[$modulo_key] ?? findDirByOrder($contenido_base, 'modulo', $modulo_orden);
                if ($modulo_dir !== null) {
                    $modulo_dirs[$modulo_key] = $modulo_dir;
                }
                $tema_dir = null;
                if (!empty($modulo_dir)) {
                    $tema_dir = $tema_dirs[$tema_key] ?? findDirByOrder($contenido_base . DIRECTORY_SEPARATOR . $modulo_dir, 'tema', $tema_orden);
                    if ($tema_dir !== null) {
                        $tema_dirs[$tema_key] = $tema_dir;
                    }
                }
                $tema_recurso_url = '';
                if (!empty($modulo_dir) && !empty($tema_dir)) {
                    $tema_index_path = $contenido_base . DIRECTORY_SEPARATOR . $modulo_dir . DIRECTORY_SEPARATOR . $tema_dir . DIRECTORY_SEPARATOR . 'index.html';
                    if (is_file($tema_index_path)) {
                        $tema_recurso_url = $uploads_base . makeRelativePath($curso_dir, $tema_index_path);
                    }
                }
                $stmt = $conn->prepare("
                    INSERT INTO temas (modulo_id, titulo, descripcion, orden, recurso_url, created_at) 
                    VALUES (:modulo_id, :titulo, :descripcion, :orden, :recurso_url, NOW())
                    ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), recurso_url = VALUES(recurso_url)
                ");

                // DEBUGGING: Log de la consulta SQL y parámetros para temas
                error_log("=== DEBUGGING ZIP PROCESAR - TEMAS ===");
                error_log("SQL Query: INSERT INTO temas (modulo_id, titulo, descripcion, orden, recurso_url, created_at) VALUES (:modulo_id, :titulo, :descripcion, :orden, :recurso_url, NOW()) ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), recurso_url = VALUES(recurso_url)");
                $params_tema = [
                    ':modulo_id' => $modulos_creados[$modulo_key],
                    ':titulo' => 'Tema ' . str_pad($tema_orden, 2, '0', STR_PAD_LEFT),
                    ':descripcion' => 'Tema ' . $tema_orden . ' del módulo ' . $modulo_orden,
                    ':orden' => $tema_orden,
                    ':recurso_url' => $tema_recurso_url
                ];
                error_log("Parámetros tema: " . print_r($params_tema, true));
                error_log("Número de parámetros: " . count($params_tema));

                $stmt->execute($params_tema);
                $temas_creados[$tema_key] = $conn->lastInsertId() ?: getExistingTemaId($conn, $modulos_creados[$modulo_key], $tema_orden);
            }

            // Crear o obtener subtema
            $subtema_key = $modulo_orden . '_' . $tema_orden . '_' . $subtema_orden;
            if (!isset($subtemas_creados[$subtema_key])) {
                $modulo_dir = $modulo_dirs[$modulo_key] ?? findDirByOrder($contenido_base, 'modulo', $modulo_orden);
                if ($modulo_dir !== null) {
                    $modulo_dirs[$modulo_key] = $modulo_dir;
                }
                $tema_dir = $tema_dirs[$tema_key] ?? null;
                if ($tema_dir === null && !empty($modulo_dir)) {
                    $tema_dir = findDirByOrder($contenido_base . DIRECTORY_SEPARATOR . $modulo_dir, 'tema', $tema_orden);
                    if ($tema_dir !== null) {
                        $tema_dirs[$tema_key] = $tema_dir;
                    }
                }
                $subtema_dir = null;
                if (!empty($modulo_dir) && !empty($tema_dir)) {
                    $subtema_dir = $subtema_dirs[$subtema_key] ?? findDirByOrder($contenido_base . DIRECTORY_SEPARATOR . $modulo_dir . DIRECTORY_SEPARATOR . $tema_dir, 'subtema', $subtema_orden);
                    if ($subtema_dir !== null) {
                        $subtema_dirs[$subtema_key] = $subtema_dir;
                    }
                }
                $subtema_recurso_url = '';
                if (!empty($modulo_dir) && !empty($tema_dir) && !empty($subtema_dir)) {
                    $subtema_index_path = $contenido_base . DIRECTORY_SEPARATOR . $modulo_dir . DIRECTORY_SEPARATOR . $tema_dir . DIRECTORY_SEPARATOR . $subtema_dir . DIRECTORY_SEPARATOR . 'index.html';
                    if (is_file($subtema_index_path)) {
                        $subtema_recurso_url = $uploads_base . makeRelativePath($curso_dir, $subtema_index_path);
                    }
                }
                $stmt = $conn->prepare("
                    INSERT INTO subtemas (tema_id, titulo, descripcion, orden, recurso_url, created_at) 
                    VALUES (:tema_id, :titulo, :descripcion, :orden, :recurso_url, NOW())
                    ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), recurso_url = VALUES(recurso_url)
                ");

                // DEBUGGING: Log de la consulta SQL y parámetros para subtemas
                error_log("=== DEBUGGING ZIP PROCESAR - SUBTEMAS ===");
                error_log("SQL Query: INSERT INTO subtemas (tema_id, titulo, descripcion, orden, recurso_url, created_at) VALUES (:tema_id, :titulo, :descripcion, :orden, :recurso_url, NOW()) ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), recurso_url = VALUES(recurso_url)");
                $params_subtema = [
                    ':tema_id' => $temas_creados[$tema_key],
                    ':titulo' => 'Subtema ' . str_pad($subtema_orden, 2, '0', STR_PAD_LEFT),
                    ':descripcion' => 'Subtema ' . $subtema_orden . ' del tema ' . $tema_orden,
                    ':orden' => $subtema_orden,
                    ':recurso_url' => $subtema_recurso_url
                ];
                error_log("Parámetros subtema: " . print_r($params_subtema, true));
                error_log("Número de parámetros: " . count($params_subtema));

                $stmt->execute($params_subtema);
                $subtemas_creados[$subtema_key] = $conn->lastInsertId() ?: getExistingSubtemaId($conn, $temas_creados[$tema_key], $subtema_orden);
            }

            $contenido_embebido = null;
            if ($leccionesHasContenidoEmbebido && !empty($leccion_data['path'])) {
                $leccion_path = $curso_dir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $leccion_data['path']);
                $html = is_file($leccion_path) ? @file_get_contents($leccion_path) : false;
                if ($html !== false && $detectaEmbebido($html)) {
                    $contenido_embebido = 1;
                }
            }

            $columns = 'modulo_id, subtema_id, tema_id, titulo, contenido, recurso_url, tipo, orden, created_at';
            $values = ':modulo_id, :subtema_id, :tema_id, :titulo, :contenido, :recurso_url, \'documento\', :orden, NOW()';
            $updates = 'titulo = VALUES(titulo), contenido = VALUES(contenido), recurso_url = VALUES(recurso_url)';
            if ($leccionesHasContenidoEmbebido) {
                $columns = 'modulo_id, subtema_id, tema_id, titulo, contenido, recurso_url, contenido_embebido, tipo, orden, created_at';
                $values = ':modulo_id, :subtema_id, :tema_id, :titulo, :contenido, :recurso_url, :contenido_embebido, \'documento\', :orden, NOW()';
                $updates .= ', contenido_embebido = VALUES(contenido_embebido)';
            }

            $stmt = $conn->prepare("
                INSERT INTO lecciones ($columns)
                VALUES ($values)
                ON DUPLICATE KEY UPDATE
                    $updates
            ");

            // DEBUGGING: Log de la consulta SQL y parámetros para lecciones
            error_log("=== DEBUGGING ZIP PROCESAR - LECCIONES ===");
            error_log("SQL Query: INSERT INTO lecciones ($columns) VALUES ($values) ON DUPLICATE KEY UPDATE $updates");
            $params_leccion = [
                ':modulo_id' => $modulos_creados[$modulo_key],
                ':subtema_id' => $subtemas_creados[$subtema_key],
                ':tema_id' => $temas_creados[$tema_key],
                ':titulo' => $leccion_data['titulo'],
                ':contenido' => '',
                ':recurso_url' => strpos($leccion_data['path'], 'uploads/cursos/') === 0
                    ? $leccion_data['path']
                    : $uploads_base . ltrim($leccion_data['path'], '/\\'),
                ':orden' => $leccion_orden
            ];
            if ($leccionesHasContenidoEmbebido) {
                $params_leccion[':contenido_embebido'] = $contenido_embebido;
            }
            error_log("Parámetros lección: " . print_r($params_leccion, true));
            error_log("Número de parámetros: " . count($params_leccion));

            $stmt->execute($params_leccion);
        }

        // Confirmar transacción
        $conn->commit();

        // DEBUGGING: Confirmar que la transacción se completó
        error_log("=== TRANSACCIÓN COMPLETADA EXITOSAMENTE ===");
        error_log("Curso ID: " . $curso_id);
        error_log("Lecciones procesadas: " . count($contenido_escaneado['lecciones'] ?? []));

        // Limpiar directorio temporal
        removeDirectory($temp_dir);

        echo json_encode([
            'success' => true,
            'message' => 'Contenido importado exitosamente',
            'lecciones_procesadas' => count($contenido_escaneado['lecciones'] ?? [])
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
function findDirByOrder($baseDir, $prefix, $order)
{
    if (!is_dir($baseDir)) return null;
    $items = @scandir($baseDir);
    if ($items === false) return null;
    foreach ($items as $name) {
        if ($name === '.' || $name === '..') continue;
        $full = $baseDir . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($full)) continue;
        if (preg_match('/^' . preg_quote($prefix, '/') . '[-_]?(\d+)/i', $name, $m)) {
            if ((int)$m[1] === (int)$order) return $name;
        }
    }
    return null;
}

function makeRelativePath($baseDir, $filePath)
{
    $relative = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $filePath);
    return str_replace('\\', '/', $relative);
}

function sanitizeFileName($filename)
{
    $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '-', $filename);
    $filename = preg_replace('/-+/', '-', $filename);
    return trim($filename, '-');
}

function copyDirectory($src, $dst)
{
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

function removeDirectory($dir)
{
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

function getExistingModuloId($conn, $curso_id, $orden)
{
    $stmt = $conn->prepare("SELECT id FROM modulos WHERE curso_id = :curso_id AND orden = :orden");
    $stmt->execute([':curso_id' => $curso_id, ':orden' => $orden]);
    $result = $stmt->fetch();
    return $result ? $result['id'] : null;
}

function getExistingTemaId($conn, $modulo_id, $orden)
{
    $stmt = $conn->prepare("SELECT id FROM temas WHERE modulo_id = :modulo_id AND orden = :orden");
    $stmt->execute([':modulo_id' => $modulo_id, ':orden' => $orden]);
    $result = $stmt->fetch();
    return $result ? $result['id'] : null;
}

function getExistingSubtemaId($conn, $tema_id, $orden)
{
    $stmt = $conn->prepare("SELECT id FROM subtemas WHERE tema_id = :tema_id AND orden = :orden");
    $stmt->execute([':tema_id' => $tema_id, ':orden' => $orden]);
    $result = $stmt->fetch();
    return $result ? $result['id'] : null;
}
