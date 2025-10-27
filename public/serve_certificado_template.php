<?php
// Endpoint público para servir la imagen de plantilla del certificado
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paths.php';

header('Cache-Control: public, max-age=3600');

$curso_id = (int)($_GET['curso_id'] ?? 0);
$pathParam = isset($_GET['path']) ? (string)$_GET['path'] : null;
if ($curso_id === 0) {
  http_response_code(400);
  echo 'curso_id requerido';
  exit;
}

try {
  // Preferir ruta pasada por query si viene del editor y está saneada
  $rel = null;
  if ($pathParam) {
    $rel = ltrim(str_replace(['../', './'], '', $pathParam), '/');
  }
  if (!$rel) {
    $stmt = $conn->prepare('SELECT template_path, template_mime FROM certificados_config WHERE curso_id = :cid LIMIT 1');
    $stmt->execute([':cid' => $curso_id]);
    $cfg = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cfg || empty($cfg['template_path'])) {
      http_response_code(404);
      echo 'Plantilla no configurada';
      exit;
    }
    $rel = $cfg['template_path'];
  }
  // Normalizar ruta relativa
  $rel = ltrim(str_replace(['\\', '../', './'], ['/', '', ''], $rel), '/');

  $candidates = [
    rtrim(PUBLIC_PATH, '/') . '/' . $rel,
    rtrim(ROOT_PATH, '/') . '/' . $rel,
    '/tmp/imt-cursos/' . $rel,
    '/tmp/' . $rel,
  ];

  // Variantes adicionales para rutas de certificados en /tmp
  if (preg_match('#^uploads/certificados/[^/]+\.(png|jpg|jpeg)$#i', $rel)) {
    $basename = basename($rel);
    $candidates[] = '/tmp/uploads/certificados/' . $basename;
    $candidates[] = '/var/tmp/uploads/certificados/' . $basename;
  }

  $path = null;
  foreach ($candidates as $p) {
    if (file_exists($p)) { $path = $p; break; }
  }

  if (!$path) {
    // Fallback: buscar por patrón certificado_<curso_id>_* en ubicaciones conocidas
    $patternDirs = [
      rtrim(PUBLIC_PATH, '/') . '/uploads/certificados',
      rtrim(ROOT_PATH, '/') . '/uploads/certificados',
      '/tmp/imt-cursos/uploads/certificados',
      '/tmp/uploads/certificados',
      '/var/tmp/uploads/certificados',
    ];
    $found = [];
    foreach ($patternDirs as $dir) {
      if (!is_dir($dir)) continue;
      foreach (['png','jpg','jpeg'] as $ext) {
        $globPattern = $dir . '/certificado_' . $curso_id . '_*.' . $ext;
        $matches = glob($globPattern);
        if (is_array($matches)) {
          foreach ($matches as $m) { $found[] = $m; }
        }
      }
    }
    if (!empty($found)) {
      // Elegir el más reciente por mtime
      usort($found, function($a, $b){ return filemtime($b) <=> filemtime($a); });
      $path = $found[0];
    }

    if (!$path) {
      if (isset($_GET['debug'])) {
        header('Content-Type: application/json');
        $checks = [];
        foreach ($candidates as $p) { $checks[] = ['path' => $p, 'exists' => file_exists($p)]; }
        $dirChecks = [];
        foreach ($patternDirs as $d) {
          $dirChecks[] = ['dir' => $d, 'is_dir' => is_dir($d)];
        }
        echo json_encode([
          'curso_id' => $curso_id,
          'rel' => $rel,
          'candidates' => $checks,
          'pattern_dirs' => $dirChecks,
          'found_matches' => $found,
        ], JSON_PRETTY_PRINT);
        exit;
      }
      http_response_code(404);
      echo 'Plantilla no encontrada';
      exit;
    }
  }

  $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  $mime = 'image/jpeg';
  if ($ext === 'png') $mime = 'image/png';
  elseif ($ext === 'jpg' || $ext === 'jpeg') $mime = 'image/jpeg';

  header('Content-Type: ' . $mime);
  header('Content-Length: ' . filesize($path));
  readfile($path);
  exit;
} catch (Throwable $e) {
  http_response_code(500);
  echo 'Error: ' . substr($e->getMessage(), 0, 200);
  exit;
}