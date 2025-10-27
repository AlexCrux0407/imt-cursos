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
$stmt = $conn->prepare("SELECT i.*, c.titulo FROM inscripciones i INNER JOIN cursos c ON i.curso_id = c.id WHERE i.usuario_id = :uid AND i.curso_id = :cid LIMIT 1");
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
$restantes = $fecha_completado ? max(0, (int)$hoy->diff($descarga_hasta)->format('%a')) : 0;

// Obtener nombre del estudiante
$stmt = $conn->prepare("SELECT nombre FROM usuarios WHERE id = :uid LIMIT 1");
$stmt->execute([':uid' => $usuario_id]);
$usuario = $stmt->fetch();
$nombre_estudiante = $usuario ? $usuario['nombre'] : 'Estudiante';

// Promedio/calificación opcional del curso
$promedio = null;
if ((int)($config['mostrar_calificacion'] ?? 0) === 1) {
    $stmt = $conn->prepare("SELECT AVG(CASE WHEN ie.puntaje_obtenido IS NOT NULL THEN ie.puntaje_obtenido ELSE NULL END) as promedio FROM modulos m LEFT JOIN evaluaciones_modulo em ON m.id = em.modulo_id LEFT JOIN intentos_evaluacion ie ON em.id = ie.evaluacion_id AND ie.usuario_id = :uid WHERE m.curso_id = :cid");
    $stmt->execute([':uid' => $usuario_id, ':cid' => $curso_id]);
    $row = $stmt->fetch();
    if ($row && $row['promedio'] !== null) {
        $promedio = round($row['promedio'], 1);
    }
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
      <div class="certificado-preview" style="position:relative;">
        <img src="<?= BASE_URL . '/' . ltrim($config['template_path'], '/') ?>" alt="Plantilla" style="width:100%; display:block;">
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
        ?>
        <?php if (!empty($config['nombre_x']) && !empty($config['nombre_y'])): ?>
          <div style="position:absolute; left: <?= floatval($config['nombre_x']) ?>%; top: <?= floatval($config['nombre_y']) ?>%; transform: translate(-50%, -50%); color: <?= $nombre_col ?>; font-size: <?= $nombre_px ?>px; font-family: <?= $nombre_fam ?>; font-weight: 600;"><?= htmlspecialchars($nombre_estudiante) ?></div>
        <?php endif; ?>
        <?php if (!empty($config['curso_x']) && !empty($config['curso_y'])): ?>
          <div style="position:absolute; left: <?= floatval($config['curso_x']) ?>%; top: <?= floatval($config['curso_y']) ?>%; transform: translate(-50%, -50%); color: <?= $curso_col ?>; font-size: <?= $curso_px ?>px; font-family: <?= $curso_fam ?>; font-weight: 600;"><?= htmlspecialchars($insc['titulo']) ?></div>
        <?php endif; ?>
        <?php if ((int)($config['mostrar_calificacion'] ?? 0) === 1 && !empty($config['calificacion_x']) && !empty($config['calificacion_y']) && $promedio !== null): ?>
          <div style="position:absolute; left: <?= floatval($config['calificacion_x']) ?>%; top: <?= floatval($config['calificacion_y']) ?>%; transform: translate(-50%, -50%); color: <?= $cal_col ?>; font-size: <?= $cal_px ?>px; font-family: <?= $cal_fam ?>;">Calificación: <?= htmlspecialchars(number_format($promedio, 1)) ?></div>
        <?php endif; ?>
        <?php if (!empty($config['fecha_x']) && !empty($config['fecha_y']) && $fecha_completado): ?>
          <div style="position:absolute; left: <?= floatval($config['fecha_x']) ?>%; top: <?= floatval($config['fecha_y']) ?>%; transform: translate(-50%, -50%); color: <?= $fecha_col ?>; font-size: <?= $fecha_px ?>px; font-family: <?= $fecha_fam ?>;"><?= $fecha_completado->format('d/m/Y') ?></div>
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

  <div class="navegacion-enlaces" style="margin-top:20px;">
    <a href="<?= BASE_URL ?>/estudiante/cursos_completados.php" class="enlace-nav"><i class="icono-nav">←</i> Volver a Completados</a>
    <a href="<?= BASE_URL ?>/estudiante/mis_cursos.php" class="enlace-nav">Ver Mis Cursos <i class="icono-nav">→</i></a>
  </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>