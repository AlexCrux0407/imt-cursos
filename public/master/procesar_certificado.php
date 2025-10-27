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
$template_mime = null;
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
  $template_mime = $mime;
}

// Recoger datos de estilo y posiciones
$font_family = $_POST['font_family'] ?? 'helvetica';
$font_size = (int)($_POST['font_size'] ?? 24);
$font_color = $_POST['font_color'] ?? '#000000';
$mostrar_calificacion = isset($_POST['mostrar_calificacion']) ? 1 : 0;
$valid_days = (int)($_POST['valid_days'] ?? 15);

// Estilos por campo (opcionales; si se dejan vacíos, se usa el global)
$nombre_font_family = $_POST['nombre_font_family'] ?? null;
$nombre_font_size = $_POST['nombre_font_size'] ?? null;
$nombre_font_color = $_POST['nombre_font_color'] ?? null;

$curso_font_family = $_POST['curso_font_family'] ?? null;
$curso_font_size = $_POST['curso_font_size'] ?? null;
$curso_font_color = $_POST['curso_font_color'] ?? null;

$calificacion_font_family = $_POST['calificacion_font_family'] ?? null;
$calificacion_font_size = $_POST['calificacion_font_size'] ?? null;
$calificacion_font_color = $_POST['calificacion_font_color'] ?? null;

$fecha_font_family = $_POST['fecha_font_family'] ?? null;
$fecha_font_size = $_POST['fecha_font_size'] ?? null;
$fecha_font_color = $_POST['fecha_font_color'] ?? null;

// Posiciones
$nombre_x = $_POST['nombre_x'] ?? null;
$nombre_y = $_POST['nombre_y'] ?? null;
$curso_x = $_POST['curso_x'] ?? null;
$curso_y = $_POST['curso_y'] ?? null;
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
$curso_x = norm_perc($curso_x);
$curso_y = norm_perc($curso_y);
$calificacion_x = norm_perc($calificacion_x);
$calificacion_y = norm_perc($calificacion_y);
$fecha_x = norm_perc($fecha_x);
$fecha_y = norm_perc($fecha_y);

try {
  // Ver si existe registro
  $stmt = $conn->prepare('SELECT id FROM certificados_config WHERE curso_id = :curso_id LIMIT 1');
  $stmt->execute([':curso_id' => $curso_id]);
  $exists = $stmt->fetchColumn();

  // Helper: verificar si existe una columna usando SHOW COLUMNS sin parámetros
  $hasCol = function(string $col) use ($conn) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) return false; // sanity check
    $sql = "SHOW COLUMNS FROM certificados_config LIKE '" . $col . "'";
    $q = $conn->query($sql);
    return (bool)$q->fetch(PDO::FETCH_ASSOC);
  };

  if ($exists) {
    $sets = [];
    $params = [':curso_id' => $curso_id];

    if ($template_path) { $sets[] = 'template_path = :template_path'; $params[':template_path'] = $template_path; }
    if ($hasCol('template_mime') && $template_mime) { $sets[] = 'template_mime = :template_mime'; $params[':template_mime'] = $template_mime; }
    $sets[] = 'font_family = :font_family'; $params[':font_family'] = $font_family;
    $sets[] = 'font_size = :font_size'; $params[':font_size'] = $font_size;
    $sets[] = 'font_color = :font_color'; $params[':font_color'] = $font_color;
    $sets[] = 'mostrar_calificacion = :mostrar_calificacion'; $params[':mostrar_calificacion'] = $mostrar_calificacion;
    $sets[] = 'valid_days = :valid_days'; $params[':valid_days'] = $valid_days;
    $sets[] = 'nombre_x = :nombre_x'; $params[':nombre_x'] = $nombre_x;
    $sets[] = 'nombre_y = :nombre_y'; $params[':nombre_y'] = $nombre_y;
    if ($hasCol('curso_x')) { $sets[] = 'curso_x = :curso_x'; $params[':curso_x'] = $curso_x; }
    if ($hasCol('curso_y')) { $sets[] = 'curso_y = :curso_y'; $params[':curso_y'] = $curso_y; }
    $sets[] = 'calificacion_x = :calificacion_x'; $params[':calificacion_x'] = $calificacion_x;
    $sets[] = 'calificacion_y = :calificacion_y'; $params[':calificacion_y'] = $calificacion_y;
    $sets[] = 'fecha_x = :fecha_x'; $params[':fecha_x'] = $fecha_x;
    $sets[] = 'fecha_y = :fecha_y'; $params[':fecha_y'] = $fecha_y;
    if ($hasCol('nombre_font_family')) { $sets[] = 'nombre_font_family = :nombre_font_family'; $params[':nombre_font_family'] = $nombre_font_family; }
    if ($hasCol('nombre_font_size')) { $sets[] = 'nombre_font_size = :nombre_font_size'; $params[':nombre_font_size'] = $nombre_font_size; }
    if ($hasCol('nombre_font_color')) { $sets[] = 'nombre_font_color = :nombre_font_color'; $params[':nombre_font_color'] = $nombre_font_color; }
    if ($hasCol('curso_font_family')) { $sets[] = 'curso_font_family = :curso_font_family'; $params[':curso_font_family'] = $curso_font_family; }
    if ($hasCol('curso_font_size')) { $sets[] = 'curso_font_size = :curso_font_size'; $params[':curso_font_size'] = $curso_font_size; }
    if ($hasCol('curso_font_color')) { $sets[] = 'curso_font_color = :curso_font_color'; $params[':curso_font_color'] = $curso_font_color; }
    if ($hasCol('calificacion_font_family')) { $sets[] = 'calificacion_font_family = :calificacion_font_family'; $params[':calificacion_font_family'] = $calificacion_font_family; }
    if ($hasCol('calificacion_font_size')) { $sets[] = 'calificacion_font_size = :calificacion_font_size'; $params[':calificacion_font_size'] = $calificacion_font_size; }
    if ($hasCol('calificacion_font_color')) { $sets[] = 'calificacion_font_color = :calificacion_font_color'; $params[':calificacion_font_color'] = $calificacion_font_color; }
    if ($hasCol('fecha_font_family')) { $sets[] = 'fecha_font_family = :fecha_font_family'; $params[':fecha_font_family'] = $fecha_font_family; }
    if ($hasCol('fecha_font_size')) { $sets[] = 'fecha_font_size = :fecha_font_size'; $params[':fecha_font_size'] = $fecha_font_size; }
    if ($hasCol('fecha_font_color')) { $sets[] = 'fecha_font_color = :fecha_font_color'; $params[':fecha_font_color'] = $fecha_font_color; }

    $sql = 'UPDATE certificados_config SET ' . implode(', ', $sets) . ' WHERE curso_id = :curso_id';
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
  } else {
    $cols = ['curso_id', 'font_family', 'font_size', 'font_color', 'mostrar_calificacion', 'valid_days', 'nombre_x', 'nombre_y', 'calificacion_x', 'calificacion_y', 'fecha_x', 'fecha_y'];
    $place = [':curso_id', ':font_family', ':font_size', ':font_color', ':mostrar_calificacion', ':valid_days', ':nombre_x', ':nombre_y', ':calificacion_x', ':calificacion_y', ':fecha_x', ':fecha_y'];
    $params = [
      ':curso_id' => $curso_id,
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
    ];
    if ($hasCol('template_path')) { $cols[] = 'template_path'; $place[] = ':template_path'; $params[':template_path'] = $template_path; }
    if ($hasCol('template_mime')) { $cols[] = 'template_mime'; $place[] = ':template_mime'; $params[':template_mime'] = $template_mime; }
    if ($hasCol('curso_x')) { $cols[] = 'curso_x'; $place[] = ':curso_x'; $params[':curso_x'] = $curso_x; }
    if ($hasCol('curso_y')) { $cols[] = 'curso_y'; $place[] = ':curso_y'; $params[':curso_y'] = $curso_y; }

    if ($hasCol('nombre_font_family')) { $cols[] = 'nombre_font_family'; $place[] = ':nombre_font_family'; $params[':nombre_font_family'] = $nombre_font_family; }
    if ($hasCol('nombre_font_size')) { $cols[] = 'nombre_font_size'; $place[] = ':nombre_font_size'; $params[':nombre_font_size'] = $nombre_font_size; }
    if ($hasCol('nombre_font_color')) { $cols[] = 'nombre_font_color'; $place[] = ':nombre_font_color'; $params[':nombre_font_color'] = $nombre_font_color; }
    if ($hasCol('curso_font_family')) { $cols[] = 'curso_font_family'; $place[] = ':curso_font_family'; $params[':curso_font_family'] = $curso_font_family; }
    if ($hasCol('curso_font_size')) { $cols[] = 'curso_font_size'; $place[] = ':curso_font_size'; $params[':curso_font_size'] = $curso_font_size; }
    if ($hasCol('curso_font_color')) { $cols[] = 'curso_font_color'; $place[] = ':curso_font_color'; $params[':curso_font_color'] = $curso_font_color; }
    if ($hasCol('calificacion_font_family')) { $cols[] = 'calificacion_font_family'; $place[] = ':calificacion_font_family'; $params[':calificacion_font_family'] = $calificacion_font_family; }
    if ($hasCol('calificacion_font_size')) { $cols[] = 'calificacion_font_size'; $place[] = ':calificacion_font_size'; $params[':calificacion_font_size'] = $calificacion_font_size; }
    if ($hasCol('calificacion_font_color')) { $cols[] = 'calificacion_font_color'; $place[] = ':calificacion_font_color'; $params[':calificacion_font_color'] = $calificacion_font_color; }
    if ($hasCol('fecha_font_family')) { $cols[] = 'fecha_font_family'; $place[] = ':fecha_font_family'; $params[':fecha_font_family'] = $fecha_font_family; }
    if ($hasCol('fecha_font_size')) { $cols[] = 'fecha_font_size'; $place[] = ':fecha_font_size'; $params[':fecha_font_size'] = $fecha_font_size; }
    if ($hasCol('fecha_font_color')) { $cols[] = 'fecha_font_color'; $place[] = ':fecha_font_color'; $params[':fecha_font_color'] = $fecha_font_color; }

    $sql = 'INSERT INTO certificados_config (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $place) . ')';
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
  }

  header('Location: ' . BASE_URL . '/master/configurar_certificado.php?id=' . $curso_id . '&ok=1');
  exit;
} catch (Exception $e) {
  $msg = substr($e->getMessage(), 0, 300);
  error_log('Error guardando certificado: ' . $msg);
  $qs = http_build_query(['id' => $curso_id, 'error' => 'bd', 'detalle' => $msg]);
  header('Location: ' . BASE_URL . '/master/configurar_certificado.php?' . $qs);
  exit;
}