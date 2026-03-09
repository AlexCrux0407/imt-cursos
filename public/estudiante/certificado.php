<?php
// Vista Estudiante – Certificado: descarga y visualización
require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Estudiante – Certificado';
$usuario_id = $_SESSION['user_id'];
$curso_id = (int)($_GET['curso_id'] ?? 0);
if ($curso_id === 0) {
    header('Location: ' . BASE_URL . '/estudiante/mis_cursos.php?error=curso_no_especificado');
    exit;
}

// Obtener info de inscripción y curso
$stmt = $conn->prepare("SELECT i.*, c.titulo, c.duracion FROM inscripciones i INNER JOIN cursos c ON i.curso_id = c.id WHERE i.usuario_id = :uid AND i.curso_id = :cid LIMIT 1");
$stmt->execute([':uid' => $usuario_id, ':cid' => $curso_id]);
$insc = $stmt->fetch();
if (!$insc || $insc['estado'] !== 'completado') {
    header('Location: ' . BASE_URL . '/estudiante/mis_cursos.php?error=curso_no_completado');
    exit;
}

// Configuración de certificado
$stmt = $conn->prepare("SELECT * FROM certificados_config WHERE curso_id = :cid LIMIT 1");
$stmt->execute([':cid' => $curso_id]);
$config = $stmt->fetch();

// Calcular ventana de descarga
$valid_days = (int)($config['valid_days'] ?? 15);
$fecha_completado = $insc['fecha_completado'] ? new DateTime($insc['fecha_completado']) : null;
$hoy = new DateTime('now');
$descarga_hasta = $fecha_completado ? (clone $fecha_completado)->modify('+' . $valid_days . ' days') : null;
$descarga_permitida = $fecha_completado && $hoy <= $descarga_hasta;

// Preparar ID único de certificado (emitido una sola vez por inscripción)
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
} catch (Throwable $e) {}

$codigo_unico = null;
try {
  $stmt = $conn->prepare('SELECT codigo_unico FROM certificados_emitidos WHERE inscripcion_id = :iid LIMIT 1');
  $stmt->execute([':iid' => $insc['id']]);
  $codigo_unico = $stmt->fetchColumn();
  if (!$codigo_unico && $fecha_completado) {
    // Asignar el código al mostrar el certificado si aún no existe
    $codigo_unico = bin2hex(random_bytes(16));
    try {
      $stmt = $conn->prepare('INSERT INTO certificados_emitidos (inscripcion_id, usuario_id, curso_id, codigo_unico) VALUES (:iid, :uid, :cid, :code)');
      $stmt->execute([':iid' => $insc['id'], ':uid' => $usuario_id, ':cid' => $curso_id, ':code' => $codigo_unico]);
    } catch (Throwable $e2) {
      $stmt = $conn->prepare('SELECT codigo_unico FROM certificados_emitidos WHERE inscripcion_id = :iid LIMIT 1');
      $stmt->execute([':iid' => $insc['id']]);
      $codigo_unico = $stmt->fetchColumn() ?: $codigo_unico;
    }
  }
} catch (Throwable $e) {}
$restantes = $fecha_completado ? max(0, (int)$hoy->diff($descarga_hasta)->format('%a')) : 0;

// Obtener nombre del estudiante
$stmt = $conn->prepare("SELECT nombre FROM usuarios WHERE id = :uid LIMIT 1");
$stmt->execute([':uid' => $usuario_id]);
$usuario = $stmt->fetch();
$nombre_estudiante = $usuario ? format_nombre($usuario['nombre'], 'nombres_apellidos') : 'Estudiante';

// Promedio/calificación opcional del curso
$promedio = null;
if ((int)($config['mostrar_calificacion'] ?? 0) === 1) {
    $promedio = calcularCalificacionFinal($curso_id, $usuario_id);
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/estudiante.css">

<div class="contenido">
  <div class="student-welcome" style="margin-bottom: 20px;">
    <h1 class="welcome-title">Certificado de Finalización</h1>
    <p class="welcome-subtitle">Curso: <?= htmlspecialchars($insc['titulo']) ?></p>
  </div>

  <?php if (!$config || empty($config['template_path'])): ?>
    <div class="empty-state" style="text-align:center; padding: 30px;">
      <img src="<?= BASE_URL ?>/styles/iconos/warning.png" alt="Sin plantilla" style="width:64px; opacity:0.6; margin-bottom:10px;">
      <h3>El certificado no está configurado aún</h3>
      <p>Por favor, contacta al administrador del curso.</p>
    </div>
  <?php else: ?>
    <div class="certificado-panel" style="background:#fff; border:1px solid #e1e5e9; border-radius:10px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); overflow:hidden;">
      <div class="certificado-preview" id="est-cert-container" style="position:relative; max-height:80vh; display:inline-block; margin:0 auto;">
        <img id="est-preview-image" src="<?= BASE_URL ?>/serve_certificado_template.php?curso_id=<?= (int)$curso_id ?>&path=<?= urlencode($config['template_path'] ?? '') ?>" alt="Plantilla" style="max-width:100%; max-height:80vh; width:auto; height:auto; display:block;">
        <?php 
          $g_family = htmlspecialchars($config['font_family'] ?? 'Arial');
          $g_size_pt = (int)($config['font_size'] ?? 24);
          $g_color = htmlspecialchars($config['font_color'] ?? '#000000');
          function resolveStyle($cfg, $prefix, $g_family, $g_size_pt, $g_color) {
            $fam = htmlspecialchars($cfg[$prefix . '_font_family'] ?? $g_family);
            $size_pt = (int)($cfg[$prefix . '_font_size'] ?? $g_size_pt);
            $color = htmlspecialchars($cfg[$prefix . '_font_color'] ?? $g_color);
            $size_px = (int)round($size_pt * 1.33);
            return [$fam, $size_px, $color];
          }
          list($nombre_fam, $nombre_px, $nombre_col) = resolveStyle($config, 'nombre', $g_family, $g_size_pt, $g_color);
          list($curso_fam, $curso_px, $curso_col) = resolveStyle($config, 'curso', $g_family, $g_size_pt, $g_color);
          list($cal_fam, $cal_px, $cal_col) = resolveStyle($config, 'calificacion', $g_family, $g_size_pt, $g_color);
          list($fecha_fam, $fecha_px, $fecha_col) = resolveStyle($config, 'fecha', $g_family, $g_size_pt, $g_color);
          list($dur_fam, $dur_px, $dur_col) = resolveStyle($config, 'duracion', $g_family, $g_size_pt, $g_color);
          $text_align = $config['text_align'] ?? 'center';
          if (!in_array($text_align, ['left', 'center', 'right'], true)) {
            $text_align = 'center';
          }
          $text_transform = $text_align === 'left'
            ? 'translate(0, -50%)'
            : ($text_align === 'right' ? 'translate(-100%, -50%)' : 'translate(-50%, -50%)');
        ?>
        <?php if (!empty($config['nombre_x']) && !empty($config['nombre_y'])): ?>
          <div class="est-cert-text" data-size-pt="<?= (int)($config['nombre_font_size'] ?? $g_size_pt) ?>" style="position:absolute; left: <?= floatval($config['nombre_x']) ?>%; top: <?= floatval($config['nombre_y']) ?>%; transform: <?= $text_transform ?>; text-align: <?= $text_align ?>; color: <?= $nombre_col ?>; font-size: <?= $nombre_px ?>px; font-family: <?= $nombre_fam ?>; font-weight: 600;"><?= htmlspecialchars($nombre_estudiante) ?></div>
        <?php endif; ?>
        <?php if (!empty($config['curso_x']) && !empty($config['curso_y'])): ?>
          <div class="est-cert-text" data-size-pt="<?= (int)($config['curso_font_size'] ?? $g_size_pt) ?>" style="position:absolute; left: <?= floatval($config['curso_x']) ?>%; top: <?= floatval($config['curso_y']) ?>%; transform: <?= $text_transform ?>; text-align: <?= $text_align ?>; color: <?= $curso_col ?>; font-size: <?= $curso_px ?>px; font-family: <?= $curso_fam ?>; font-weight: 600;"><?= htmlspecialchars($insc['titulo']) ?></div>
        <?php endif; ?>
        <?php if ((int)($config['mostrar_calificacion'] ?? 0) === 1 && !empty($config['calificacion_x']) && !empty($config['calificacion_y']) && $promedio !== null): ?>
          <div class="est-cert-text" data-size-pt="<?= (int)($config['calificacion_font_size'] ?? $g_size_pt) ?>" style="position:absolute; left: <?= floatval($config['calificacion_x']) ?>%; top: <?= floatval($config['calificacion_y']) ?>%; transform: <?= $text_transform ?>; text-align: <?= $text_align ?>; color: <?= $cal_col ?>; font-size: <?= $cal_px ?>px; font-family: <?= $cal_fam ?>;"><?= htmlspecialchars(number_format($promedio, 1)) ?></div>
        <?php endif; ?>
        <?php if (!empty($config['fecha_x']) && !empty($config['fecha_y']) && $fecha_completado): ?>
          <div class="est-cert-text" data-size-pt="<?= (int)($config['fecha_font_size'] ?? $g_size_pt) ?>" style="position:absolute; left: <?= floatval($config['fecha_x']) ?>%; top: <?= floatval($config['fecha_y']) ?>%; transform: <?= $text_transform ?>; text-align: <?= $text_align ?>; color: <?= $fecha_col ?>; font-size: <?= $fecha_px ?>px; font-family: <?= $fecha_fam ?>;"><?= $fecha_completado->format('d/m/Y') ?></div>
        <?php endif; ?>
        <?php if ((int)($config['mostrar_duracion'] ?? 0) === 1 && !empty($config['duracion_x']) && !empty($config['duracion_y']) && isset($insc['duracion']) && $insc['duracion'] !== null && $insc['duracion'] !== ''): ?>
          <div class="est-cert-text" data-size-pt="<?= (int)($config['duracion_font_size'] ?? $g_size_pt) ?>" style="position:absolute; left: <?= floatval($config['duracion_x']) ?>%; top: <?= floatval($config['duracion_y']) ?>%; transform: <?= $text_transform ?>; text-align: <?= $text_align ?>; color: <?= $dur_col ?>; font-size: <?= $dur_px ?>px; font-family: <?= $dur_fam ?>;"><?= (int)$insc['duracion'] ?></div>
        <?php endif; ?>
        <?php 
          // Render del ID del certificado en la esquina inferior derecha por defecto
          $codigo_x = isset($config['codigo_x']) ? floatval($config['codigo_x']) : 92.0;
          $codigo_y = isset($config['codigo_y']) ? floatval($config['codigo_y']) : 95.0;
          $codigo_fam = htmlspecialchars($config['codigo_font_family'] ?? ($config['font_family'] ?? 'Arial'));
          $codigo_size_pt = (int)($config['codigo_font_size'] ?? 12);
          $codigo_px = (int)round($codigo_size_pt * 1.33);
          $codigo_col = htmlspecialchars($config['codigo_font_color'] ?? '#2c3e50');
        ?>
        <?php if ($codigo_unico): ?>
          <div class="est-cert-text" data-size-pt="<?= $codigo_size_pt ?>" style="position:absolute; left: <?= $codigo_x ?>%; top: <?= $codigo_y ?>%; transform: <?= $text_transform ?>; text-align: <?= $text_align ?>; color: <?= $codigo_col ?>; font-size: <?= $codigo_px ?>px; font-family: <?= $codigo_fam ?>;">ID: <?= htmlspecialchars($codigo_unico) ?></div>
        <?php endif; ?>
      </div>

      <div class="certificado-actions" style="padding: 16px; display:flex; justify-content: space-between; align-items: center;">
        <div>
          <?php if ($descarga_permitida): ?>
            <span style="color:#28a745; font-weight:500;">Descarga disponible por <?= $restantes ?> día(s).</span>
            <small style="display:block; color:#6c757d;">Hasta: <?= $descarga_hasta ? $descarga_hasta->format('d/m/Y') : '' ?></small>
          <?php else: ?>
            <span style="color:#e74c3c; font-weight:500;">Descarga no disponible</span>
            <small style="display:block; color:#6c757d;">La ventana de <?= $valid_days ?> días desde la fecha de completado ha expirado.</small>
          <?php endif; ?>
        </div>
        <div>
          <a href="<?= BASE_URL ?>/estudiante/generar_certificado.php?curso_id=<?= (int)$curso_id ?>" class="btn-primary" style="<?= $descarga_permitida ? '' : 'pointer-events:none;opacity:0.5;' ?>">Descargar Certificado</a>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="navegacion-enlaces d-flex justify-content-center gap-3" style="margin-top:20px;">
    <a href="<?= BASE_URL ?>/estudiante/cursos_completados.php" class="btn btn-outline-primary">
      ← Volver a Completados
    </a>
    <a href="<?= BASE_URL ?>/estudiante/mis_cursos.php" class="btn btn-primary">
      Ver Mis Cursos →
    </a>
  </div>
</div>

<script>
  (function(){
    function ptToPx(pt){ return pt * (96/72); }
    function syncContainer(){
      var img = document.getElementById('est-preview-image');
      var container = document.getElementById('est-cert-container');
      if(!img || !container) return;
      // No fijar dimensiones; dejar que el contenedor se adapte al tamaño de la imagen
      container.style.width = '';
      container.style.height = '';
    }
    function rescale(){
      var img = document.getElementById('est-preview-image');
      if(!img) return;
      var rect = img.getBoundingClientRect();
      var rectW = rect.width || 1;
      var rectH = rect.height || 1;
      var naturalW = img.naturalWidth || rectW;
      var naturalH = img.naturalHeight || rectH;
      // Normalizar por devicePixelRatio para que el zoom del navegador no afecte la escala visual
      var dpr = window.devicePixelRatio || 1;
      var naturalCssW = naturalW / dpr;
      var naturalCssH = naturalH / dpr;
      var scaleW = rectW / (naturalCssW || rectW);
      var scaleH = rectH / (naturalCssH || rectH);
      var scale = Math.min(scaleW, scaleH);
      document.querySelectorAll('.est-cert-text').forEach(function(el){
        var spt = parseInt(el.getAttribute('data-size-pt')||'24',10);
        el.style.fontSize = (ptToPx(spt) * scale) + 'px';
      });
    }
    // Debounce para sincronizar tamaño y aplicar escalado sin saltos
    var syncTimer = null;
    function debouncedSync(){
      if(syncTimer) clearTimeout(syncTimer);
      syncTimer = setTimeout(function(){
        syncContainer();
        rescale();
      }, 100);
    }
    window.addEventListener('resize', debouncedSync);
    var img = document.getElementById('est-preview-image');
    if(img){ img.addEventListener('load', debouncedSync); }
    if(window.ResizeObserver){
      var ro = new ResizeObserver(function(){ debouncedSync(); });
      if(img) ro.observe(img);
    }
    debouncedSync();
  })();
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
