<?php
require_once __DIR__ . '/../config/paths.php';

// Proxy seguro para servir videos de bienvenida desde uploads/media,
// tanto si están en PUBLIC_PATH como si están en UPLOADS_PATH.

// Validar parámetro
$file = $_GET['file'] ?? '';
$file = trim($file);

// Solo permitimos nombres simples de archivo, sin rutas ni traversal
if ($file === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $file)) {
    http_response_code(400);
    echo 'Parámetro de archivo inválido.';
    exit;
}

// Limitar a extensiones de video conocidas
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$allowed_ext = ['mp4', 'webm', 'ogg'];
if (!in_array($ext, $allowed_ext, true)) {
    http_response_code(400);
    echo 'Tipo de archivo no permitido.';
    exit;
}

$publicPath = rtrim(PUBLIC_PATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $file;
$rootPath   = rtrim(UPLOADS_PATH, '/\\') . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $file;

// Elegir el path existente y legible
$path = null;
if (is_readable($publicPath)) {
    $path = $publicPath;
} elseif (is_readable($rootPath)) {
    $path = $rootPath;
}

if ($path === null) {
    http_response_code(404);
    echo 'Archivo no encontrado.';
    exit;
}

// Detectar MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $path) ?: 'application/octet-stream';
finfo_close($finfo);

// Streaming con soporte de Range
$size = filesize($path);
$start = 0;
$end   = $size - 1;
$length = $size;

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=604800'); // 7 días

// Manejar peticiones parciales
if (isset($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/bytes=([0-9]*)-([0-9]*)/', $_SERVER['HTTP_RANGE'], $matches)) {
        $rangeStart = $matches[1] !== '' ? (int)$matches[1] : null;
        $rangeEnd   = $matches[2] !== '' ? (int)$matches[2] : null;

        if ($rangeStart !== null) {
            $start = $rangeStart;
        }
        if ($rangeEnd !== null && $rangeEnd < $end) {
            $end = $rangeEnd;
        }

        if ($start > $end || $start >= $size) {
            header('Content-Range: bytes */' . $size);
            http_response_code(416); // Range Not Satisfiable
            exit;
        }

        $length = $end - $start + 1;
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        header('Content-Length: ' . $length);
        http_response_code(206); // Partial Content
    }
} else {
    header('Content-Length: ' . $length);
}

// Enviar contenido
$chunkSize = 8192; // 8KB
$fp = fopen($path, 'rb');
if ($fp === false) {
    http_response_code(500);
    echo 'No se pudo abrir el archivo.';
    exit;
}

// Posicionar al inicio de rango
if ($start > 0) {
    fseek($fp, $start);
}

while (!feof($fp) && $length > 0) {
    $buffer = fread($fp, min($chunkSize, $length));
    echo $buffer;
    $length -= strlen($buffer);
    @ob_flush();
    flush();
}

fclose($fp);
exit;
?>