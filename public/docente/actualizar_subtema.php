<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subtema_id = (int)($_POST['subtema_id'] ?? 0);
    $tema_id = (int)($_POST['tema_id'] ?? 0);
    $modulo_id = (int)($_POST['modulo_id'] ?? 0);
    $curso_id = (int)($_POST['curso_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $orden = (int)($_POST['orden'] ?? 1);
    $recurso_url = trim($_POST['recurso_url'] ?? '');
    
    if (empty($titulo) || $subtema_id === 0) {
        header('Location:'  . BASE_URL . '/docente/subtemas_tema.php?id=' . $tema_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=datos_invalidos');
        exit;
    }
    
    // Verificar permisos
    $stmt = $conn->prepare("
        SELECT s.id FROM subtemas s
        INNER JOIN temas t ON s.tema_id = t.id
        INNER JOIN modulos m ON t.modulo_id = m.id
        INNER JOIN cursos c ON m.curso_id = c.id
        WHERE s.id = :subtema_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
    ");
    $stmt->execute([':subtema_id' => $subtema_id, ':docente_id' => $_SESSION['user_id'], ':docente_id2' => $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        header('Location:'  . BASE_URL . '/docente/admin_cursos.php?error=acceso_denegado');
        exit;
    }
    
    // Manejo de recurso/archivo si la tabla soporta recurso_url
    $stmt = $conn->prepare("SHOW COLUMNS FROM subtemas LIKE 'recurso_url'");
    $stmt->execute();
    $column_exists = $stmt->fetch();
    if (!$column_exists) {
        try {
            $conn->exec("ALTER TABLE subtemas ADD COLUMN recurso_url VARCHAR(255) NULL");
            // Reconsultar existencia
            $stmt = $conn->prepare("SHOW COLUMNS FROM subtemas LIKE 'recurso_url'");
            $stmt->execute();
            $column_exists = $stmt->fetch();
        } catch (Exception $e) {
            error_log('No se pudo crear la columna recurso_url en subtemas: ' . $e->getMessage());
        }
    }

    // Obtener recurso actual si existe columna
    $archivo_actual = null;
    if ($column_exists) {
        $stmt = $conn->prepare("SELECT recurso_url FROM subtemas WHERE id = :id");
        $stmt->execute([':id' => $subtema_id]);
        $row = $stmt->fetch();
        $archivo_actual = $row['recurso_url'] ?? null;
    }

    // Manejar subida de archivo único (compatibilidad antigua)
    $nuevo_archivo = null;
    if ($column_exists && isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        require_once __DIR__ . '/../../app/upload_helper.php';
        try {
            $upload_helper = new UploadHelper($conn);
            $nuevo_archivo = $upload_helper->handleFileUpload($_FILES['archivo'], 'subtema', $subtema_id);
            if ($nuevo_archivo && $archivo_actual && strpos($archivo_actual, '/uploads/') === 0) {
                $upload_helper->deleteFile($archivo_actual);
            }
        } catch (Exception $e) {
            error_log('Error actualizando archivo de subtema: ' . $e->getMessage());
        }
    }

    // Manejar subida de múltiples archivos nuevos (archivos[])
    if (isset($_FILES['archivos']) && is_array($_FILES['archivos']['name'])) {
        require_once __DIR__ . '/../../app/upload_helper.php';
        try {
            // Asegurar existencia de tabla subtema_recursos
            $checkTable = $conn->query("SHOW TABLES LIKE 'subtema_recursos'");
            if (!$checkTable->fetch()) {
                $conn->exec("CREATE TABLE IF NOT EXISTS subtema_recursos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    subtema_id INT NOT NULL,
                    url VARCHAR(255) NOT NULL,
                    nombre VARCHAR(255) NULL,
                    tipo VARCHAR(50) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX(subtema_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }

            $upload_helper = new UploadHelper($conn);
            $names = $_FILES['archivos']['name'];
            $tmp_names = $_FILES['archivos']['tmp_name'];
            $errors = $_FILES['archivos']['error'];
            $sizes = $_FILES['archivos']['size'];
            $types = $_FILES['archivos']['type'];

            for ($i = 0; $i < count($names); $i++) {
                if ($errors[$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $names[$i],
                        'type' => $types[$i] ?? '',
                        'tmp_name' => $tmp_names[$i],
                        'error' => $errors[$i],
                        'size' => $sizes[$i]
                    ];

                    try {
                        $archivo_url_multi = $upload_helper->handleFileUpload($file, 'subtema', $subtema_id);
                        if ($archivo_url_multi) {
                            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            $ins = $conn->prepare("INSERT INTO subtema_recursos (subtema_id, url, nombre, tipo) VALUES (:sid, :url, :nombre, :tipo)");
                            $ins->execute([
                                ':sid' => $subtema_id,
                                ':url' => $archivo_url_multi,
                                ':nombre' => $file['name'],
                                ':tipo' => $ext
                            ]);
                        }
                    } catch (Exception $e) {
                        error_log('Error subiendo archivo múltiple de subtema: ' . $e->getMessage());
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Error procesando múltiples archivos de subtema: ' . $e->getMessage());
        }
    }

    try {
        $stmt = $conn->prepare("
            UPDATE subtemas 
            SET titulo = :titulo, descripcion = :descripcion, orden = :orden
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':titulo' => $titulo,
            ':descripcion' => $descripcion,
            ':orden' => $orden,
            ':id' => $subtema_id
        ]);
        
        // Actualizar recurso_url si existe columna (compatibilidad): usa el nuevo archivo único o la URL manual
        if ($column_exists) {
            $url_final = $nuevo_archivo ?: $recurso_url;
            $stmt = $conn->prepare("
                UPDATE subtemas 
                SET recurso_url = :recurso_url
                WHERE id = :id
            ");
            $stmt->execute([
                ':recurso_url' => $url_final ?: null,
                ':id' => $subtema_id
            ]);
        }
        
        header('Location:'  . BASE_URL . '/docente/subtemas_tema.php?id=' . $tema_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&success=subtema_actualizado');
        exit;
        
    } catch (Exception $e) {
        header('Location:'  . BASE_URL . '/docente/editar_subtema.php?id=' . $subtema_id . '&tema_id=' . $tema_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=error_actualizar');
        exit;
    }
} else {
    header('Location:'  . BASE_URL . '/docente/admin_cursos.php');
    exit;
}
?>
