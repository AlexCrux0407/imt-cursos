<?php
// Endpoint público para servir la imagen de plantilla del certificado
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paths.php';

header('Cache-Control: public, max-age=3600');

$curso_id = (int)($_GET['curso_id'] ?? 0);
if ($curso_id === 0) {
  http_response_code(400);
  echo 'curso_id requerido';
  exit;
}

try {
  $stmt = $conn->prepare('SELECT template_path, template_mime FROM certificados_config WHERE curso_id = :cid LIMIT 1');
  $stmt->execute([':cid' => $curso_id]);
  $cfg = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$cfg || empty($cfg['template_path'])) {
    http_response_code(404);
    echo 'Plantilla no configurada';
    exit;
  }

  $rel = $cfg['template_path'];
  $rel = ltrim(str_replace(['../', './'], '', $rel), '/');

  $candidates = [
    rtrim(PUBLIC_PATH, '/') . '/' . $rel,
    rtrim(ROOT_PATH, '/') . '/' . $rel,
    '/tmp/imt-cursos/' . $rel,
  ];

  $path = null;
  foreach ($candidates as $p) {
    if (file_exists($p)) { $path = $p; break; }
  }

  if (!$path) {
    http_response_code(404);
    echo 'Plantilla no encontrada';
    exit;
  }

  $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  $mime = 'image/jpeg';
  if ($ext === 'png') $mime = 'image/png';
  elseif ($ext === 'jpg' || $ext === 'jpeg') $mime = 'image/jpeg';
  elseif (!empty($cfg['template_mime'])) $mime = $cfg['template_mime'];

  header('Content-Type: ' . $mime);
  header('Content-Length: ' . filesize($path));
  readfile($path);
  exit;
} catch (Throwable $e) {
  http_response_code(500);
  echo 'Error: ' . substr($e->getMessage(), 0, 200);
  exit;
}