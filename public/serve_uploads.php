<?php
require_once __DIR__ . '/../config/paths.php';

// Proxy seguro para servir archivos de cursos desde uploads/cursos,
// funcionando tanto si están en PUBLIC_PATH como en UPLOADS_PATH.

// Validar parámetro
$path = $_GET['path'] ?? '';
$path = trim($path);

// Solo permitimos rutas bajo "cursos/" y sin traversal
if ($path === '' || !preg_match('#^(cursos/)[A-Za-z0-9._/\-]+$#', $path)) {
    http_response_code(400);
    echo 'Parámetro de ruta inválido.';
    exit;
}

// Rechazar cualquier intento de traversal
if (strpos($path, '..') !== false) {
    http_response_code(400);
    echo 'Ruta no permitida.';
    exit;
}

$publicPath = rtrim(PUBLIC_PATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $path;
$rootPath   = rtrim(UPLOADS_PATH, '/\\') . DIRECTORY_SEPARATOR . $path; // en raíz ya está dentro de uploads

// Elegir el path existente y legible
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

// Detectar MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $filePath) ?: 'application/octet-stream';
finfo_close($finfo);

// Soporte básico de descarga directa
$filename = basename($filePath);

// Streaming con soporte de Range para videos/archivos grandes
$size = filesize($filePath);
$start = 0;
$end   = $size - 1;
$length = $size;

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=604800'); // 7 días
header('Content-Disposition: inline; filename="' . $filename . '"');

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