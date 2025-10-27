<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

$usuario_id = $_SESSION['user_id'];
$curso_id = (int)($_GET['curso_id'] ?? 0);
if ($curso_id === 0) {
    http_response_code(400);
    echo 'Curso no especificado';
    exit;
}

// Inscripción y estado
$stmt = $conn->prepare("SELECT i.*, c.titulo FROM inscripciones i INNER JOIN cursos c ON i.curso_id = c.id WHERE i.usuario_id = :uid AND i.curso_id = :cid LIMIT 1");
$stmt->execute([':uid' => $usuario_id, ':cid' => $curso_id]);
$insc = $stmt->fetch();
if (!$insc || $insc['estado'] !== 'completado' || empty($insc['fecha_completado'])) {
    http_response_code(403);
    echo 'No autorizado o curso no completado';
    exit;
}

// Config certificado
$stmt = $conn->prepare("SELECT * FROM certificados_config WHERE curso_id = :cid LIMIT 1");
$stmt->execute([':cid' => $curso_id]);
$config = $stmt->fetch();
if (!$config || empty($config['template_path'])) {
    http_response_code(500);
    echo 'Certificado no configurado';
    exit;
}

// Ventana de descarga
$valid_days = (int)($config['valid_days'] ?? 15);
$fecha_completado = new DateTime($insc['fecha_completado']);
$descarga_hasta = (clone $fecha_completado)->modify('+' . $valid_days . ' days');
$hoy = new DateTime('now');
if ($hoy > $descarga_hasta) {
    http_response_code(403);
    echo 'La ventana de descarga ha expirado';
    exit;
}

// Datos dinámicos
$stmt = $conn->prepare("SELECT nombre FROM usuarios WHERE id = :uid LIMIT 1");
$stmt->execute([':uid' => $usuario_id]);
$usuario = $stmt->fetch();
$nombre_estudiante = $usuario ? $usuario['nombre'] : 'Estudiante';

$promedio = null;
if ((int)($config['mostrar_calificacion'] ?? 0) === 1) {
    $stmt = $conn->prepare("SELECT AVG(CASE WHEN ie.puntaje_obtenido IS NOT NULL THEN ie.puntaje_obtenido ELSE NULL END) as promedio FROM modulos m LEFT JOIN evaluaciones_modulo em ON m.id = em.modulo_id LEFT JOIN intentos_evaluacion ie ON em.id = ie.evaluacion_id AND ie.usuario_id = :uid WHERE m.curso_id = :cid");
    $stmt->execute([':uid' => $usuario_id, ':cid' => $curso_id]);
    $row = $stmt->fetch();
    if ($row && $row['promedio'] !== null) {
        $promedio = round($row['promedio'], 1);
    }
}

$fecha_texto = $fecha_completado->format('d/m/Y');

// Cargar plantilla de imagen
$template_rel = $config['template_path'];
$template_abs = __DIR__ . '/../' . str_replace(['../', './'], '', $template_rel);
if (!file_exists($template_abs)) {
    // Intentar ruta alternativa basada en raíz del proyecto
    $template_abs = dirname(__DIR__, 2) . '/' . ltrim($template_rel, '/');
}
if (!file_exists($template_abs)) {
    http_response_code(500);
    echo 'Plantilla no encontrada';
    exit;
}

$ext = strtolower(pathinfo($template_abs, PATHINFO_EXTENSION));
if ($ext === 'png') {
    $img = imagecreatefrompng($template_abs);
} elseif ($ext === 'jpg' || $ext === 'jpeg') {
    $img = imagecreatefromjpeg($template_abs);
} else {
    $data = file_get_contents($template_abs);
    $img = imagecreatefromstring($data);
}
if (!$img) {
    http_response_code(500);
    echo 'No se pudo cargar la imagen de la plantilla';
    exit;
}

$width = imagesx($img);
$height = imagesy($img);

// Resolver fuente
function resolveFontPath($family) {
    $candidates = [];
    $win = 'C:\\Windows\\Fonts\\';
    switch (strtolower($family)) {
        case 'helvetica':
            $candidates = [$win.'arial.ttf', $win.'ARIAL.TTF', $win.'calibri.ttf', $win.'tahoma.ttf', $win.'segoeui.ttf'];
            break;
        case 'times':
            $candidates = [$win.'times.ttf', $win.'TIMES.TTF', $win.'timesbd.ttf', $win.'timesi.ttf', $win.'timesbi.ttf', $win.'times new roman.ttf'];
            break;
        case 'courier':
            $candidates = [$win.'cour.ttf', $win.'COUR.TTF', $win.'courbd.ttf'];
            break;
        case 'dejavusans':
            $candidates = [$win.'DejaVuSans.ttf', $win.'dejavusans.ttf'];
            break;
        default:
            $candidates = [$win.'arial.ttf'];
    }
    foreach ($candidates as $p) {
        if (file_exists($p)) return $p;
    }
    // Fallback
    return $win.'arial.ttf';
}

$g_family = $config['font_family'] ?? 'helvetica';
$g_size_pt = (int)($config['font_size'] ?? 24);
$g_hex = trim($config['font_color'] ?? '#000000');

function resolveFieldStyle($cfg, $prefix, $fallbackFamily, $fallbackSizePt, $fallbackHex) {
    $family = $cfg[$prefix . '_font_family'] ?? $fallbackFamily;
    $size_pt = (int)($cfg[$prefix . '_font_size'] ?? $fallbackSizePt);
    $hex = trim($cfg[$prefix . '_font_color'] ?? $fallbackHex);
    return [$family, $size_pt, $hex];
}
function allocColor($img, $hex) {
    if ($hex[0] === '#') $hex = substr($hex, 1);
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return imagecolorallocate($img, $r, $g, $b);
}

// Utilidad para dibujar texto centrado en porcentajes
function drawTextPercentCentered($im, $text, $font, $size, $color, $xPerc, $yPerc, $imgW, $imgH) {
    if ($text === null || $text === '') return;
    if ($xPerc === null || $yPerc === null) return;
    $x = ($imgW * (floatval($xPerc) / 100.0));
    $y = ($imgH * (floatval($yPerc) / 100.0));
    $bbox = imagettfbbox($size, 0, $font, $text);
    $textW = abs($bbox[2] - $bbox[0]);
    $textH = abs($bbox[7] - $bbox[1]); // altura aproximada
    $drawX = $x - ($textW / 2);
    $drawY = $y + ($textH / 2);
    imagettftext($im, $size, 0, (int)$drawX, (int)$drawY, $color, $font, $text);
}

list($nombre_family, $nombre_size, $nombre_hex) = resolveFieldStyle($config, 'nombre', $g_family, $g_size_pt, $g_hex);
list($curso_family, $curso_size, $curso_hex) = resolveFieldStyle($config, 'curso', $g_family, $g_size_pt, $g_hex);
list($cal_family, $cal_size, $cal_hex) = resolveFieldStyle($config, 'calificacion', $g_family, $g_size_pt, $g_hex);
list($fecha_family, $fecha_size, $fecha_hex) = resolveFieldStyle($config, 'fecha', $g_family, $g_size_pt, $g_hex);

$nombre_font_path = resolveFontPath($nombre_family);
$nombre_color = allocColor($img, $nombre_hex);
$curso_font_path = resolveFontPath($curso_family);
$curso_color = allocColor($img, $curso_hex);
$cal_font_path = resolveFontPath($cal_family);
$cal_color = allocColor($img, $cal_hex);
$fecha_font_path = resolveFontPath($fecha_family);
$fecha_color = allocColor($img, $fecha_hex);

// Nombre
if (!empty($config['nombre_x']) && !empty($config['nombre_y'])) {
    drawTextPercentCentered($img, $nombre_estudiante, $nombre_font_path, $nombre_size, $nombre_color, $config['nombre_x'], $config['nombre_y'], $width, $height);
}
// Curso
if (!empty($config['curso_x']) && !empty($config['curso_y'])) {
    drawTextPercentCentered($img, $insc['titulo'], $curso_font_path, $curso_size, $curso_color, $config['curso_x'], $config['curso_y'], $width, $height);
}
// Calificación
if ((int)($config['mostrar_calificacion'] ?? 0) === 1 && $promedio !== null && !empty($config['calificacion_x']) && !empty($config['calificacion_y'])) {
    drawTextPercentCentered($img, 'Calificación: ' . number_format($promedio, 1), $cal_font_path, $cal_size, $cal_color, $config['calificacion_x'], $config['calificacion_y'], $width, $height);
}
// Fecha
if (!empty($config['fecha_x']) && !empty($config['fecha_y'])) {
    drawTextPercentCentered($img, $fecha_texto, $fecha_font_path, $fecha_size, $fecha_color, $config['fecha_x'], $config['fecha_y'], $width, $height);
}

// Salida PNG
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="certificado_curso_'.$curso_id.'_usuario_'.$usuario_id.'.png"');
imagepng($img);
imagedestroy($img);