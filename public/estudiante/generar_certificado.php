<?php
// Vista Estudiante – Generar certificado PDF descargable
require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$usuario_id = $_SESSION['user_id'];
$curso_id = (int)($_GET['curso_id'] ?? 0);
if ($curso_id === 0) {
    http_response_code(400);
    echo 'Curso no especificado';
    exit;
}

// Inscripción y estado
$stmt = $conn->prepare("SELECT i.*, c.titulo, c.duracion FROM inscripciones i INNER JOIN cursos c ON i.curso_id = c.id WHERE i.usuario_id = :uid AND i.curso_id = :cid LIMIT 1");
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

// ID único del certificado (emitido una sola vez por inscripción)
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS certificados_emitidos (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        inscripcion_id BIGINT UNSIGNED NOT NULL,
        usuario_id BIGINT UNSIGNED NOT NULL,
        curso_id BIGINT UNSIGNED NOT NULL,
        codigo_unico VARCHAR(64) NOT NULL,
        fecha_emision DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_inscripcion (inscripcion_id),
        UNIQUE KEY uniq_codigo (codigo_unico),
        KEY idx_usuario (usuario_id),
        KEY idx_curso (curso_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    // no interrumpir la descarga si la creación falla; se intentará de nuevo en la vista
}

$codigo_unico = null;
try {
    $stmt = $conn->prepare("SELECT codigo_unico FROM certificados_emitidos WHERE inscripcion_id = :iid LIMIT 1");
    $stmt->execute([':iid' => $insc['id']]);
    $codigo_unico = $stmt->fetchColumn();
    if (!$codigo_unico) {
        $codigo_unico = bin2hex(random_bytes(16));
        try {
            $stmt = $conn->prepare("INSERT INTO certificados_emitidos (inscripcion_id, usuario_id, curso_id, codigo_unico) VALUES (:iid, :uid, :cid, :code)");
            $stmt->execute([':iid' => $insc['id'], ':uid' => $usuario_id, ':cid' => $curso_id, ':code' => $codigo_unico]);
        } catch (Throwable $e2) {
            // En caso de carrera, recuperar el existente
            $stmt = $conn->prepare("SELECT codigo_unico FROM certificados_emitidos WHERE inscripcion_id = :iid LIMIT 1");
            $stmt->execute([':iid' => $insc['id']]);
            $codigo_unico = $stmt->fetchColumn() ?: $codigo_unico;
        }
    }
} catch (Throwable $e) {
    // Si algo falla, generar un código efímero (no persistido) para no bloquear la descarga
    if (!$codigo_unico) { $codigo_unico = bin2hex(random_bytes(16)); }
}

// Datos dinámicos
$stmt = $conn->prepare("SELECT nombre FROM usuarios WHERE id = :uid LIMIT 1");
$stmt->execute([':uid' => $usuario_id]);
$usuario = $stmt->fetch();
$nombre_estudiante = $usuario ? format_nombre($usuario['nombre'], 'nombres_apellidos') : 'Estudiante';

$promedio = null;
if ((int)($config['mostrar_calificacion'] ?? 0) === 1) {
    $promedio = calcularCalificacionFinal($curso_id, $usuario_id);
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
    // Fallback adicional para PaaS: /tmp
    $template_abs = '/tmp/imt-cursos/' . ltrim($template_rel, '/');
}
if (!file_exists($template_abs)) {
    http_response_code(500);
    echo 'Plantilla no encontrada';
    exit;
}

// Preparar generación en PDF con TCPDF usando la imagen como fondo

function resolvePdfFont(string $family): string {
    $f = strtolower(trim($family));
    switch ($f) {
        // Familias TCPDF nativas
        case 'helvetica':
        case 'times':
        case 'courier':
        case 'dejavusans':
            return $f;
        // Mapeos desde opciones del master
        case 'arial':
            return 'helvetica';
        case 'times new roman':
        case 'georgia':
            return 'times';
        case 'verdana':
        case 'tahoma':
            return 'dejavusans';
        default:
            return 'dejavusans';
    }
}

// Intentar usar fuentes TTF del sistema en Windows para métricas exactas
function findWindowsFontPath(string $family, string $style = ''): ?string {
    $base = 'C:\\Windows\\Fonts\\';
    $f = strtolower(trim($family));
    $bold = strtolower($style) === 'b';
    $candidates = [];
    switch ($f) {
        case 'arial':
            $candidates = $bold ? ['arialbd.ttf', 'ARIALBD.TTF'] : ['arial.ttf', 'ARIAL.TTF'];
            break;
        case 'verdana':
            $candidates = $bold ? ['verdanab.ttf', 'VERDANAB.TTF'] : ['verdana.ttf', 'VERDANA.TTF'];
            break;
        case 'tahoma':
            $candidates = $bold ? ['tahomabd.ttf', 'TAHOMABD.TTF'] : ['tahoma.ttf', 'TAHOMA.TTF'];
            break;
        case 'georgia':
            $candidates = $bold ? ['georgiab.ttf', 'GEORGIAB.TTF'] : ['georgia.ttf', 'GEORGIA.TTF'];
            break;
        case 'times new roman':
            // Varios nombres posibles en Windows
            $candidates = $bold ? ['timesbd.ttf', 'TIMESBD.TTF'] : ['times.ttf', 'TIMES.TTF', 'tnr.ttf', 'TNR.TTF'];
            break;
        default:
            return null;
    }
    foreach ($candidates as $fname) {
        $path = $base . $fname;
        if (@file_exists($path)) {
            return $path;
        }
    }
    return null;
}

function resolveEffectiveFont(string $familyOrig, string $style = ''): array {
    $path = findWindowsFontPath($familyOrig, $style);
    if ($path) {
        try {
            // Registrar fuente TTF del sistema
            $fontname = TCPDF_FONTS::addTTFfont($path, 'TrueTypeUnicode', '', 32);
            if ($fontname) {
                // Para TTF registradas no usamos estilos de TCPDF (bold/italic),
                // se requiere otra TTF para negrita, ya manejada en findWindowsFontPath
                return [$fontname, ''];
            }
        } catch (Exception $e) {
            // Si falla, continuamos con mapeo estándar
        }
    }
    // Fallback a fuentes internas
    return [resolvePdfFont($familyOrig), $style];
}

function adjustSizeForFamily(string $origFamily, int $sizePt): int {
    // El tamaño configurado ya está en puntos; no aplicar compensación
    return max(8, (int)round($sizePt));
}

function resolveFieldStyle(array $cfg, string $prefix, string $fallbackFamily, int $fallbackSizePt, string $fallbackHex, string $style = ''): array {
    $familyOrig = $cfg[$prefix . '_font_family'] ?? $fallbackFamily;
    $size_pt_raw = (int)($cfg[$prefix . '_font_size'] ?? $fallbackSizePt);
    $hex = trim($cfg[$prefix . '_font_color'] ?? $fallbackHex);
    list($familyPdf, $styleUsed) = resolveEffectiveFont($familyOrig, $style);
    $size_pt = adjustSizeForFamily($familyOrig, $size_pt_raw);
    return [$familyPdf, $size_pt, $hex, $styleUsed];
}

function hexToRgb(string $hex): array {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return [$r, $g, $b];
}

// Obtener dimensiones de la plantilla
$imgInfo = @getimagesize($template_abs);
if (!$imgInfo) {
    http_response_code(500);
    echo 'No se pudo leer la plantilla de certificado';
    exit;
}
$imgWpx = (int)$imgInfo[0];
$imgHpx = (int)$imgInfo[1];

// Elegir orientación según imagen
$orientation = ($imgWpx >= $imgHpx) ? 'L' : 'P';
$pdf = new TCPDF($orientation, 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('IMT Cursos');
$pdf->SetAuthor('IMT Cursos');
$pdf->SetTitle('Certificado');
$pdf->SetSubject('Certificado de finalización');
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);
$pdf->AddPage();

// Calcular rectángulo donde dibujar la imagen manteniendo proporción
$pageW = $pdf->getPageWidth();
$pageH = $pdf->getPageHeight();
$imgRatio = $imgWpx / max(1, $imgHpx);
$pageRatio = $pageW / max(1, $pageH);
if ($imgRatio >= $pageRatio) {
    $drawW = $pageW;
    $drawH = $pageW / $imgRatio;
    $drawX = 0;
    $drawY = ($pageH - $drawH) / 2.0;
} else {
    $drawH = $pageH;
    $drawW = $pageH * $imgRatio;
    $drawX = ($pageW - $drawW) / 2.0;
    $drawY = 0;
}

// Escalamos el tamaño en puntos según cómo se dibuja la plantilla en la página,
// igual que en el preview (pt * (96/72) * scalePreview) pero en PDF:
// PDF mm height = pt * (25.4/72) * scalePdf
// donde scalePdf = drawW(mm) / (imgWpx * 25.4/96)
const CSS_PX_TO_MM = 25.4 / 96.0;
const PT_TO_MM = 25.4 / 72.0;
// Factor de escala que relaciona el tamaño dibujado (mm) vs tamaño CSS natural (mm)
$scaleW = $drawW / max(1.0, ($imgWpx * CSS_PX_TO_MM));
$scaleH = $drawH / max(1.0, ($imgHpx * CSS_PX_TO_MM));
$scalePdf = min($scaleW, $scaleH);

// Componer textos directamente sobre la imagen (GD) para garantizar fidelidad visual
function gdCreateFrom($path, $mime) {
    switch (strtolower($mime)) {
        case 'image/png': return imagecreatefrompng($path);
        case 'image/jpeg':
        case 'image/jpg': return imagecreatefromjpeg($path);
        case 'image/webp': return function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : null;
        default: return null;
    }
}

function gdSavePng($img, $path) {
    imagesavealpha($img, true);
    imagealphablending($img, true);
    return imagepng($img, $path, 6);
}

function ptToPxGD($pt) { return $pt * (96.0 / 72.0); }

function drawTextCenteredOnImage($img, string $text, string $familyOrig, string $style, int $sizePt, array $rgb, float $xPerc, float $yPerc, string $align = 'center'): void {
    if ($text === '') return;
    if (!in_array($align, ['left', 'center', 'right'], true)) {
        $align = 'center';
    }
    $w = imagesx($img);
    $h = imagesy($img);
    $centerX = $w * ($xPerc / 100.0);
    $centerY = $h * ($yPerc / 100.0);
    // Elegir ruta TTF (Windows) o fallback Arial
    $bold = strtolower($style) === 'b';
    $path = findWindowsFontPath($familyOrig, $bold ? 'B' : '');
    if (!$path) {
        $path = findWindowsFontPath('Arial', $bold ? 'B' : '') ?? findWindowsFontPath('Verdana', $bold ? 'B' : '');
    }
    if (!$path) return; // si no hay TTF disponible, omitimos dibujo para evitar errores

    $px = max(8, (int)round(ptToPxGD($sizePt)));
    $color = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
    // Medir caja para centrar
    $bbox = imagettfbbox($px, 0, $path, $text);
    if (!$bbox) return;
    $textW = abs($bbox[2] - $bbox[0]);
    $textH = abs($bbox[7] - $bbox[1]);
    if ($align === 'left') {
        $startX = (int)round($centerX);
    } elseif ($align === 'right') {
        $startX = (int)round($centerX - $textW);
    } else {
        $startX = (int)round($centerX - ($textW / 2.0));
    }
    // En imagettftext Y es la línea de base, para centrar elevamos la mitad de la altura
    $baselineY = (int)round($centerY + ($textH / 2.0));
    imagettftext($img, $px, 0, $startX, $baselineY, $color, $path, $text);
}

// Cargar y componer
$mime = $imgInfo['mime'] ?? 'image/png';
$gdImg = gdCreateFrom($template_abs, $mime);
if ($gdImg) {
    // Globales y campos
    $g_family = $config['font_family'] ?? 'Arial';
    $g_size_pt = (int)($config['font_size'] ?? 24);
    $g_hex = trim($config['font_color'] ?? '#000000');

    list($nombre_family, $nombre_size, $nombre_hex, $nombre_style) = resolveFieldStyle($config, 'nombre', $g_family, $g_size_pt, $g_hex, 'B');
    list($curso_family, $curso_size, $curso_hex, $curso_style) = resolveFieldStyle($config, 'curso', $g_family, $g_size_pt, $g_hex, 'B');
    list($cal_family, $cal_size, $cal_hex, $cal_style) = resolveFieldStyle($config, 'calificacion', $g_family, $g_size_pt, $g_hex, '');
    list($fecha_family, $fecha_size, $fecha_hex, $fecha_style) = resolveFieldStyle($config, 'fecha', $g_family, $g_size_pt, $g_hex, '');
    list($dur_family, $dur_size, $dur_hex, $dur_style) = resolveFieldStyle($config, 'duracion', $g_family, $g_size_pt, $g_hex, '');
    // Estilo para código único (con valores por defecto si no está configurado)
    $codigo_family = ($config['codigo_font_family'] ?? $g_family);
    $codigo_size_base = (int)($config['codigo_font_size'] ?? max(12, (int)round($g_size_pt * 0.6)));
    $codigo_hex = trim($config['codigo_font_color'] ?? '#2c3e50');

    $text_align = $config['text_align'] ?? 'center';
    if (!in_array($text_align, ['left', 'center', 'right'], true)) {
        $text_align = 'center';
    }
    if (!empty($config['nombre_x']) && !empty($config['nombre_y'])) {
        drawTextCenteredOnImage($gdImg, $nombre_estudiante, $nombre_family, $nombre_style, $nombre_size, hexToRgb($nombre_hex), floatval($config['nombre_x']), floatval($config['nombre_y']), $text_align);
    }
    if (!empty($config['curso_x']) && !empty($config['curso_y'])) {
        drawTextCenteredOnImage($gdImg, $insc['titulo'], $curso_family, $curso_style, $curso_size, hexToRgb($curso_hex), floatval($config['curso_x']), floatval($config['curso_y']), $text_align);
    }
    if ((int)($config['mostrar_calificacion'] ?? 0) === 1 && $promedio !== null && !empty($config['calificacion_x']) && !empty($config['calificacion_y'])) {
        drawTextCenteredOnImage($gdImg, number_format($promedio, 1), $cal_family, $cal_style, $cal_size, hexToRgb($cal_hex), floatval($config['calificacion_x']), floatval($config['calificacion_y']), $text_align);
    }
    if (!empty($config['fecha_x']) && !empty($config['fecha_y'])) {
        drawTextCenteredOnImage($gdImg, $fecha_texto, $fecha_family, $fecha_style, $fecha_size, hexToRgb($fecha_hex), floatval($config['fecha_x']), floatval($config['fecha_y']), $text_align);
    }

    // Duración estimada (horas), opcional
    if ((int)($config['mostrar_duracion'] ?? 0) === 1 && !empty($config['duracion_x']) && !empty($config['duracion_y']) && isset($insc['duracion']) && $insc['duracion'] !== null && $insc['duracion'] !== '') {
        $dur_text = (string)(int)$insc['duracion'];
        drawTextCenteredOnImage($gdImg, $dur_text, $dur_family, $dur_style, $dur_size, hexToRgb($dur_hex), floatval($config['duracion_x']), floatval($config['duracion_y']), $text_align);
    }

    // Dibujar el ID único del certificado
    $codigo_x = isset($config['codigo_x']) ? floatval($config['codigo_x']) : 92.0;
    $codigo_y = isset($config['codigo_y']) ? floatval($config['codigo_y']) : 95.0;
    drawTextCenteredOnImage($gdImg, 'ID: ' . $codigo_unico, $codigo_family, '', $codigo_size_base, hexToRgb($codigo_hex), $codigo_x, $codigo_y, $text_align);

    // Guardar imagen compuesta temporalmente
    $tmpDir = dirname(__DIR__, 2) . '/uploads/temp';
    if (!is_dir($tmpDir)) @mkdir($tmpDir, 0777, true);
    $tmpOut = $tmpDir . '/certificado_comp_' . $curso_id . '_' . $usuario_id . '.png';
    gdSavePng($gdImg, $tmpOut);
    imagedestroy($gdImg);

    // Dibujar imagen compuesta en el PDF
    $pdf->Image($tmpOut, $drawX, $drawY, $drawW, $drawH, '', '', '', false, 300);
} else {
    // Fallback: solo fondo si GD falla
    $pdf->Image($template_abs, $drawX, $drawY, $drawW, $drawH, '', '', '', false, 300);
}

// Estilos globales y por campo
$g_family = $config['font_family'] ?? 'dejavusans';
$g_size_pt = (int)($config['font_size'] ?? 24);
$g_hex = trim($config['font_color'] ?? '#000000');

list($nombre_family, $nombre_size_base, $nombre_hex, $nombre_style) = resolveFieldStyle($config, 'nombre', $g_family, $g_size_pt, $g_hex, 'B');
list($curso_family, $curso_size_base, $curso_hex, $curso_style) = resolveFieldStyle($config, 'curso', $g_family, $g_size_pt, $g_hex, 'B');
list($cal_family, $cal_size_base, $cal_hex, $cal_style) = resolveFieldStyle($config, 'calificacion', $g_family, $g_size_pt, $g_hex, '');
list($fecha_family, $fecha_size_base, $fecha_hex, $fecha_style) = resolveFieldStyle($config, 'fecha', $g_family, $g_size_pt, $g_hex, '');

// Aplicar escala en puntos para que la altura visual coincida con el preview
$nombre_size = max(8, (int)round($nombre_size_base * $scalePdf));
$curso_size = max(8, (int)round($curso_size_base * $scalePdf));
$cal_size = max(8, (int)round($cal_size_base * $scalePdf));
$fecha_size = max(8, (int)round($fecha_size_base * $scalePdf));

function drawTextPercentCenteredPdf(TCPDF $pdf, string $text, string $family, int $sizePt, array $rgb, $xPerc, $yPerc, float $drawX, float $drawY, float $drawW, float $drawH, string $style = ''): void {
    if ($text === '') return;
    if ($xPerc === null || $yPerc === null) return;
    $xPercF = floatval($xPerc);
    $yPercF = floatval($yPerc);
    $centerX = $drawX + ($drawW * ($xPercF / 100.0));
    $centerY = $drawY + ($drawH * ($yPercF / 100.0));

    $pdf->SetFont($family, $style, $sizePt);
    $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
    $tw = max($pdf->GetStringWidth($text), 1);
    $lineHeightMm = max($pdf->getFontSize() * 0.352778, 4); // altura mínima para estabilidad
    $startX = $centerX - ($tw / 2.0);
    $startY = $centerY - ($lineHeightMm / 2.0);
    // Usar MultiCell para asegurar centrado horizontal y vertical consistente
    $pdf->MultiCell($tw, $lineHeightMm, $text, 0, 'C', false, 0, $startX, $startY, true, 0, false, true, $lineHeightMm, 'M', true);
}

// Ya no dibujamos texto con TCPDF cuando GD compone la imagen

// Descarga PDF
$filename = 'certificado_curso_'.$curso_id.'_usuario_'.$usuario_id.'.pdf';
if (ob_get_length()) {
    ob_end_clean();
}
$pdf->Output($filename, 'D');
