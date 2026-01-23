<?php
require_once __DIR__ . '/../config/paths.php';

/*
 Servicio de Uploads de Cursos (Proxy Seguro)
 - Sirve archivos desde `uploads/cursos` con validación de ruta.
 - Detecta MIME, cachea con ETag/Last-Modified y soporta Range.
 - Evita traversal y restringe a rutas bajo `cursos/`.
 */

// Proxy seguro para servir archivos de cursos desde uploads/cursos,
// funcionando tanto si están en PUBLIC_PATH como en UPLOADS_PATH.

$path = $_GET['path'] ?? '';
$path = trim($path);

// Validación y seguridad: rutas bajo "cursos/" y sin traversal
if ($path === '' || !preg_match('#^(cursos/)[A-Za-z0-9._/\-]+$#', $path)) {
    http_response_code(400);
    echo 'Parámetro de ruta inválido.';
    exit;
}

if (strpos($path, '..') !== false) {
    http_response_code(400);
    echo 'Ruta no permitida.';
    exit;
}

$publicPath = rtrim(PUBLIC_PATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $path;
$rootPath   = rtrim(UPLOADS_PATH, '/\\') . DIRECTORY_SEPARATOR . $path;

$filePath = null;
if (is_readable($publicPath)) {
    $filePath = $publicPath;
} elseif (is_readable($rootPath)) {
    $filePath = $rootPath;
}

if ($filePath === null) {
    http_response_code(404);
    echo 'Archivo no encontrado.';
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $filePath) ?: 'application/octet-stream';
finfo_close($finfo);

$filename = basename($filePath);

// Streaming con soporte de Range para videos/archivos grandes
$size = filesize($filePath);
$start = 0;
$end   = $size - 1;
$length = $size;

// Cabeceras de caché robustas: ETag y Last-Modified para revalidación
$mtime = filemtime($filePath);
$etag = '"' . md5($filename . '-' . $size . '-' . $mtime) . '"';
$lastModified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=0, must-revalidate');
header('ETag: ' . $etag);
header('Last-Modified: ' . $lastModified);
header('Content-Disposition: inline; filename="' . $filename . '"');

// Responder 304 Not Modified si el cliente tiene una copia válida
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit;
}
if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
    $ifModifiedSince = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
    if ($ifModifiedSince !== false && $ifModifiedSince >= $mtime) {
        http_response_code(304);
        exit;
    }
}

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
            http_response_code(416);
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

$chunkSize = 8192; // 8KB
$fp = fopen($filePath, 'rb');
if ($fp === false) {
    http_response_code(500);
    echo 'No se pudo abrir el archivo.';
    exit;
}

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