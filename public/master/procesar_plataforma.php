<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../app/auth.php';

// Verificar autenticación y rol
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'master') {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/master/admin_plataforma.php');
    exit();
}

try {
    // Preparación: DDL (CREATE/ALTER) hace commit implícito en MySQL.
    // Iniciaremos la transacción justo antes del bloque de UPDATE (DML).
    
    // Obtener configuración actual
    $stmt = $conn->prepare("SELECT * FROM configuracion_plataforma WHERE id = 1");
    $stmt->execute();
    $config_actual = $stmt->fetch();
    
    // Si no existe configuración, crear tabla
    if (!$config_actual) {
        $stmt = $conn->prepare("
            CREATE TABLE IF NOT EXISTS configuracion_plataforma (
                id INT PRIMARY KEY,
                nombre_plataforma VARCHAR(255) NOT NULL DEFAULT 'IMT Cursos',
                logo_header VARCHAR(255) NOT NULL DEFAULT 'Logo_IMT.png',
                logo_footer VARCHAR(255) NOT NULL DEFAULT 'Logo_blanco.png',
                video_bienvenida VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute();
        
        // Insertar configuración por defecto
        $stmt = $conn->prepare("
            INSERT INTO configuracion_plataforma (id, nombre_plataforma, logo_header, logo_footer, video_bienvenida, created_at) 
            VALUES (1, 'IMT Cursos', 'Logo_IMT.png', 'Logo_blanco.png', NULL, NOW())
        ");
        $stmt->execute();
        
        // Obtener la configuración recién creada
        $stmt = $conn->prepare("SELECT * FROM configuracion_plataforma WHERE id = 1");
        $stmt->execute();
        $config_actual = $stmt->fetch();
    }
    
    // Asegurar que la columna video_bienvenida exista si la tabla ya estaba creada
    $colCheck = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'configuracion_plataforma' AND COLUMN_NAME = 'video_bienvenida'");
    $colCheck->execute();
    $hasVideoCol = (int)$colCheck->fetchColumn() > 0;
    if (!$hasVideoCol) {
        $conn->exec("ALTER TABLE configuracion_plataforma ADD COLUMN video_bienvenida VARCHAR(255) DEFAULT NULL AFTER logo_footer");
        // Recargar configuración
        $stmt = $conn->prepare("SELECT * FROM configuracion_plataforma WHERE id = 1");
        $stmt->execute();
        $config_actual = $stmt->fetch();
    }

    // Validar y sanitizar nombre de plataforma
    $nombre_plataforma = trim($_POST['nombre_plataforma'] ?? '');
    if (empty($nombre_plataforma)) {
        throw new Exception('El nombre de la plataforma es obligatorio');
    }
    
    if (strlen($nombre_plataforma) > 255) {
        throw new Exception('El nombre de la plataforma no puede exceder 255 caracteres');
    }
    
    // Procesar logos
    $logo_header = $config_actual['logo_header'];
    $logo_footer = $config_actual['logo_footer'];
    
    // Directorio de destino
    $upload_dir = __DIR__ . '/../styles/iconos/';
    // Directorio de destino para videos de bienvenida
    $upload_dir_media = rtrim(PUBLIC_PATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'media';
    if (!is_dir($upload_dir_media)) {
        // Si existe como symlink hacia un directorio válido, considerar OK
        if (!(is_link($upload_dir_media) && is_dir($upload_dir_media))) {
            if (!@mkdir($upload_dir_media, 0775, true)) {
                throw new Exception("No se pudo crear el directorio de media en '" . $upload_dir_media . "'. Verifica permisos de escritura.");
            }
        }
    }
    if (!is_writable($upload_dir_media)) {
        @chmod($upload_dir_media, 0775);
        if (!is_writable($upload_dir_media)) {
            throw new Exception("El directorio de media no es escribible: '" . $upload_dir_media . "'. Ajusta permisos/propietario.");
        }
    }
    
    // Función para procesar upload de imagen
    function procesarUploadLogo($file, $upload_dir, $tipo) {
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return null; // No se subió archivo
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error al subir el archivo del logo $tipo");
        }
        
        // Validar tamaño (2MB máximo)
        if ($file['size'] > 2 * 1024 * 1024) {
            throw new Exception("El archivo del logo $tipo es demasiado grande (máximo 2MB)");
        }
        
        // Validar tipo de archivo
        $allowed_types = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/svg+xml'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            throw new Exception("Tipo de archivo no permitido para logo $tipo. Use PNG, JPG, JPEG, GIF o SVG");
        }
        
        // Generar nombre único
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . $tipo . '_' . time() . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        // Mover archivo
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception("Error al guardar el archivo del logo $tipo");
        }
        
        return $filename;
    }
    
    // Función para procesar upload de video de bienvenida
    function procesarUploadVideo($file, $upload_dir_media) {
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return null; // No se subió archivo
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $code = (int)$file['error'];
            $uploadMax = ini_get('upload_max_filesize');
            $postMax = ini_get('post_max_size');
            $msg = "Error al subir el archivo de video de bienvenida.";
            switch ($code) {
                case UPLOAD_ERR_INI_SIZE:
                    $msg = "El video excede upload_max_filesize (" . $uploadMax . ").";
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $msg = "El video excede MAX_FILE_SIZE definido en el formulario.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $msg = "El video se subió parcialmente. Verifica post_max_size (" . $postMax . ") y la conexión.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $msg = "Falta carpeta temporal en el servidor.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $msg = "No se pudo escribir el archivo en disco.";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $msg = "Una extensión detuvo la subida del archivo.";
                    break;
            }
            throw new Exception($msg . " Sube un MP4/WebM/Ogg menor a 50MB.");
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new Exception("El archivo no fue subido correctamente (tmp inválido).");
        }
        // Validar tamaño (máximo 50MB)
        if ($file['size'] > 50 * 1024 * 1024) {
            throw new Exception("El video de bienvenida es demasiado grande (máximo 50MB)");
        }
        // Validar tipo de archivo
        $allowed_types = ['video/mp4', 'video/webm', 'video/ogg'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime_type, $allowed_types)) {
            throw new Exception("Tipo de archivo no permitido para video de bienvenida. Use MP4, WEBM u OGG");
        }
        // Generar nombre único
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'video_bienvenida_' . time() . '.' . $extension;
        $filepath = rtrim($upload_dir_media, '/\\') . DIRECTORY_SEPARATOR . $filename;
        // Mover archivo
        if (!@move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception("Error al guardar el archivo de video de bienvenida");
        }
        // Devolver ruta relativa desde public
        return 'uploads/media/' . $filename;
    }
    
    // Procesar logo del header
    if (isset($_FILES['logo_header']) && $_FILES['logo_header']['error'] !== UPLOAD_ERR_NO_FILE) {
        $nuevo_logo_header = procesarUploadLogo($_FILES['logo_header'], $upload_dir, 'header');
        if ($nuevo_logo_header) {
            // Crear respaldo del logo anterior
            $logo_anterior = $upload_dir . $config_actual['logo_header'];
            if (file_exists($logo_anterior) && !in_array($config_actual['logo_header'], ['Logo_IMT.png', 'Logo_blanco.png'])) {
                $backup_name = 'backup_' . date('Y-m-d_H-i-s') . '_' . $config_actual['logo_header'];
                copy($logo_anterior, $upload_dir . $backup_name);
            }
            $logo_header = $nuevo_logo_header;
        }
    }
    
    // Procesar logo del footer
    if (isset($_FILES['logo_footer']) && $_FILES['logo_footer']['error'] !== UPLOAD_ERR_NO_FILE) {
        $nuevo_logo_footer = procesarUploadLogo($_FILES['logo_footer'], $upload_dir, 'footer');
        if ($nuevo_logo_footer) {
            // Crear respaldo del logo anterior
            $logo_anterior = $upload_dir . $config_actual['logo_footer'];
            if (file_exists($logo_anterior) && !in_array($config_actual['logo_footer'], ['Logo_IMT.png', 'Logo_blanco.png'])) {
                $backup_name = 'backup_' . date('Y-m-d_H-i-s') . '_' . $config_actual['logo_footer'];
                copy($logo_anterior, $upload_dir . $backup_name);
            }
            $logo_footer = $nuevo_logo_footer;
        }
    }
    
    // Valor actual y procesamiento del video de bienvenida
    $video_bienvenida = $config_actual['video_bienvenida'] ?? null;
    if (isset($_FILES['video_bienvenida']) && $_FILES['video_bienvenida']['error'] !== UPLOAD_ERR_NO_FILE) {
        $nuevo_video = procesarUploadVideo($_FILES['video_bienvenida'], $upload_dir_media);
        if ($nuevo_video) {
            $video_bienvenida = $nuevo_video;
        }
    }

    // Iniciar transacción para actualizar configuración (DML)
    $conn->beginTransaction();
    // Actualizar configuración en la base de datos
    $stmt = $conn->prepare("
        UPDATE configuracion_plataforma 
        SET nombre_plataforma = :nombre_plataforma,
            logo_header = :logo_header,
            logo_footer = :logo_footer,
            video_bienvenida = :video_bienvenida,
            updated_at = NOW()
        WHERE id = 1
    ");
    
    $stmt->execute([
        ':nombre_plataforma' => $nombre_plataforma,
        ':logo_header' => $logo_header,
        ':logo_footer' => $logo_footer,
        ':video_bienvenida' => $video_bienvenida
    ]);
    
    // Confirmar transacción
    $conn->commit();
    
    // Registrar actividad en log (opcional)
    error_log("Configuración de plataforma actualizada por usuario ID: " . $_SESSION['user_id'] . " - Nombre: $nombre_plataforma, Logo Header: $logo_header, Logo Footer: $logo_footer");
    
    // Redireccionar con mensaje de éxito
    $_SESSION['mensaje_exito'] = 'Configuración de plataforma actualizada correctamente';
    header('Location: ' . BASE_URL . '/master/admin_plataforma.php?success=1');
    exit();
    
} catch (Exception $e) {
    // Revertir transacción en caso de error (si hay una activa)
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Registrar error
    error_log("Error al actualizar configuración de plataforma: " . $e->getMessage());
    
    // Redireccionar con mensaje de error
    $_SESSION['mensaje_error'] = 'Error al actualizar la configuración: ' . $e->getMessage();
    header('Location: ' . BASE_URL . '/master/admin_plataforma.php?error=1');
    exit();
}
?>