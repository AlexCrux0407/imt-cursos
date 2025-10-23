<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('master');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . BASE_URL . '/master/admin_cursos.php');
  exit;
}

$curso_id = (int)($_POST['curso_id'] ?? 0);
if ($curso_id === 0) {
  header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=curso_no_especificado');
  exit;
}

// Subida de plantilla
$template_path = null;
if (!empty($_FILES['template']['name'])) {
  $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
  $mime = $_FILES['template']['type'] ?? '';
  if (!isset($allowed[$mime])) {
    header('Location: ' . BASE_URL . '/master/configurar_certificado.php?id=' . $curso_id . '&error=archivo_invalido');
    exit;
  }
  $ext = $allowed[$mime];
  $nombre = 'certificado_' . $curso_id . '_' . time() . '.' . $ext;
  $dest_dir = __DIR__ . '/../uploads/certificados';
  if (!is_dir($dest_dir)) {
    mkdir($dest_dir, 0777, true);
  }
  $dest = $dest_dir . '/' . $nombre;
  if (!move_uploaded_file($_FILES['template']['tmp_name'], $dest)) {
    header('Location: ' . BASE_URL . '/master/configurar_certificado.php?id=' . $curso_id . '&error=subida_fallida');
    exit;
  }
  // Ruta relativa para servir
  $template_path = 'uploads/certificados/' . $nombre;
}

// Recoger datos de estilo y posiciones
$font_family = $_POST['font_family'] ?? 'helvetica';
$font_size = (int)($_POST['font_size'] ?? 24);
$font_color = $_POST['font_color'] ?? '#000000';
$mostrar_calificacion = isset($_POST['mostrar_calificacion']) ? 1 : 0;
$valid_days = (int)($_POST['valid_days'] ?? 15);

$nombre_x = $_POST['nombre_x'] ?? null;
$nombre_y = $_POST['nombre_y'] ?? null;
$calificacion_x = $_POST['calificacion_x'] ?? null;
$calificacion_y = $_POST['calificacion_y'] ?? null;
$fecha_x = $_POST['fecha_x'] ?? null;
$fecha_y = $_POST['fecha_y'] ?? null;

// Validar rangos 0-100
function norm_perc($v) {
  if ($v === null || $v === '') return null;
  $n = floatval($v);
  if ($n < 0) $n = 0; if ($n > 100) $n = 100;
  return $n;
}
$nombre_x = norm_perc($nombre_x);
$nombre_y = norm_perc($nombre_y);
$calificacion_x = norm_perc($calificacion_x);
$calificacion_y = norm_perc($calificacion_y);
$fecha_x = norm_perc($fecha_x);
$fecha_y = norm_perc($fecha_y);

try {
  // Ver si existe registro
  $stmt = $conn->prepare('SELECT id FROM certificados_config WHERE curso_id = :curso_id LIMIT 1');
  $stmt->execute([':curso_id' => $curso_id]);
  $exists = $stmt->fetchColumn();

  if ($exists) {
    $sql = 'UPDATE certificados_config SET 
              ' . ($template_path ? 'template_path = :template_path, ' : '') . '
              font_family = :font_family,
              font_size = :font_size,
              font_color = :font_color,
              mostrar_calificacion = :mostrar_calificacion,
              valid_days = :valid_days,
              nombre_x = :nombre_x,
              nombre_y = :nombre_y,
              calificacion_x = :calificacion_x,
              calificacion_y = :calificacion_y,
              fecha_x = :fecha_x,
              fecha_y = :fecha_y
            WHERE curso_id = :curso_id';
    $stmt = $conn->prepare($sql);
    $params = [
      ':font_family' => $font_family,
      ':font_size' => $font_size,
      ':font_color' => $font_color,
      ':mostrar_calificacion' => $mostrar_calificacion,
      ':valid_days' => $valid_days,
      ':nombre_x' => $nombre_x,
      ':nombre_y' => $nombre_y,
      ':calificacion_x' => $calificacion_x,
      ':calificacion_y' => $calificacion_y,
      ':fecha_x' => $fecha_x,
      ':fecha_y' => $fecha_y,
      ':curso_id' => $curso_id,
    ];
    if ($template_path) $params[':template_path'] = $template_path;
    $stmt->execute($params);
  } else {
    $sql = 'INSERT INTO certificados_config (
              curso_id, template_path, font_family, font_size, font_color,
              mostrar_calificacion, valid_days,
              nombre_x, nombre_y, calificacion_x, calificacion_y, fecha_x, fecha_y
            ) VALUES (
              :curso_id, :template_path, :font_family, :font_size, :font_color,
              :mostrar_calificacion, :valid_days,
              :nombre_x, :nombre_y, :calificacion_x, :calificacion_y, :fecha_x, :fecha_y
            )';
    $stmt = $conn->prepare($sql);
    $stmt->execute([
      ':curso_id' => $curso_id,
      ':template_path' => $template_path,
      ':font_family' => $font_family,
      ':font_size' => $font_size,
      ':font_color' => $font_color,
      ':mostrar_calificacion' => $mostrar_calificacion,
      ':valid_days' => $valid_days,
      ':nombre_x' => $nombre_x,
      ':nombre_y' => $nombre_y,
      ':calificacion_x' => $calificacion_x,
      ':calificacion_y' => $calificacion_y,
      ':fecha_x' => $fecha_x,
      ':fecha_y' => $fecha_y,
    ]);
  }

  header('Location: ' . BASE_URL . '/master/configurar_certificado.php?id=' . $curso_id . '&ok=1');
  exit;
} catch (Exception $e) {
  error_log('Error guardando certificado: ' . $e->getMessage());
  header('Location: ' . BASE_URL . '/master/configurar_certificado.php?id=' . $curso_id . '&error=bd');
  exit;
}