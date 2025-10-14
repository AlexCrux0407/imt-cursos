<?php
// Seguridad de variables (por si la vista llamante no las definió)
$curso_estructura   = $curso_estructura   ?? [];
$cursoTituloSidebar = $cursoTituloSidebar ?? 'Curso';
$moduloActualId     = isset($moduloActualId) ? (int)$moduloActualId : 0;

// Variables para detectar la página actual
$paginaActual = basename($_SERVER['PHP_SELF']);
$idActual = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Helper: determina si un módulo es accesible 
$__puedeAcceder = function(array $mods, int $targetId): bool {
    if (empty($mods)) return true;
    $modsOrden = array_values($mods);
    usort($modsOrden, fn($a, $b) => (int)($a['orden'] ?? 0) <=> (int)($b['orden'] ?? 0));
    foreach ($modsOrden as $idx => $m) {
        if ((int)($m['id'] ?? 0) === $targetId) {
            if ($idx === 0) return true; // primer módulo
            $prev = $modsOrden[$idx - 1];
            // Un módulo es accesible si el anterior tiene su evaluación completada
            return isset($prev['evaluacion_completada']) && $prev['evaluacion_completada'];
        }
    }
    return true; // fallback
};

// Helper: determina si un elemento está activo
$__esActivo = function($tipo, $id) use ($paginaActual, $idActual) {
    switch ($tipo) {
        case 'modulo':
            return $paginaActual === 'modulo_contenido.php' && $idActual === $id;
        case 'tema':
            return $paginaActual === 'tema_contenido.php' && $idActual === $id;
        case 'subtema':
            return $paginaActual === 'subtema_contenido.php' && $idActual === $id;
        case 'leccion':
            return $paginaActual === 'leccion.php' && $idActual === $id;
        default:
            return false;
    }
};
?>

<div class="sidebar-navegacion">
  <div class="sidebar-header">
    <h3 class="sidebar-titulo">
      <?= htmlspecialchars($cursoTituloSidebar, ENT_QUOTES, 'UTF-8') ?>
    </h3>

    <?php
    $genTotal = 0; $genDone = 0;
    foreach ($curso_estructura as $mCalc) {
        $genTotal += 1; // Cada módulo cuenta como 1
        if (isset($mCalc['evaluacion_completada']) && $mCalc['evaluacion_completada']) {
            $genDone += 1; // Módulo completado si su evaluación está aprobada
        }
    }
    $porcGen = $genTotal > 0 ? ($genDone / $genTotal) * 100 : 0;
    ?>
    <div class="progreso-general">
      <div class="progreso-info">
        <span>Progreso general</span>
        <span class="progreso-porcentaje"><?= number_format($porcGen, 0) ?>%</span>
      </div>
      <div class="progreso-barra-mini">
        <div class="progreso-fill-mini" style="width: <?= $porcGen ?>%; background-color: #0d6efd;"></div>
      </div>
    </div>
  </div>

  <div class="sidebar-contenido">
    <?php if (empty($curso_estructura)): ?>
      <div class="text-muted">Aún no hay contenido.</div>
    <?php else: ?>
      <?php foreach ($curso_estructura as $modItem): ?>
        <?php
          $modId    = (int)($modItem['id'] ?? 0);
          $acceso   = $__puedeAcceder($curso_estructura, $modId);
          $evaluacion_completada = isset($modItem['evaluacion_completada']) && $modItem['evaluacion_completada'];
          $completo = $evaluacion_completada;
          $isActual = ($modId === $moduloActualId);
        ?>
        <div class="sidebar-modulo <?= $acceso ? 'accesible' : 'bloqueado' ?> <?= $completo ? 'completado' : '' ?> <?= $isActual ? 'actual' : '' ?>">
          <div class="modulo-header" onclick="IMT.toggleModulo(<?= $modId ?>)">
            <div class="modulo-icon" aria-hidden="true">
              <?php if (!$acceso): ?>
                🔒
              <?php elseif ($completo): ?>
                ✅
              <?php else: ?>
                📚
              <?php endif; ?>
            </div>
            <div class="modulo-info">
              <span class="modulo-titulo"><?= htmlspecialchars($modItem['titulo'] ?? 'Módulo', ENT_QUOTES, 'UTF-8') ?></span>
              <div class="modulo-progreso">
                <small><?= $evaluacion_completada ? 'Evaluación completada' : 'Evaluación pendiente' ?></small>
                <div class="barra-mini">
                  <div class="fill-mini" style="width: <?= $evaluacion_completada ? 100 : 0 ?>%"></div>
                </div>
              </div>
            </div>
            <div class="expand-icon" id="expand-<?= $modId ?>">▼</div>
          </div>

          <div class="modulo-contenido" id="contenido-<?= $modId ?>" style="display: none;">
            <?php if ($acceso): ?>
              <?php $moduloActivo = $__esActivo('modulo', $modId); ?>
              <?php if (!$moduloActivo): ?>
                <a class="modulo-link" href="<?= BASE_URL ?>/estudiante/modulo_contenido.php?id=<?= $modId ?>">
                  📄 Ver contenido del módulo
                </a>
              <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($modItem['temas'])): ?>
              <?php foreach ($modItem['temas'] as $temaItem): ?>
                <?php $temaActivo = $__esActivo('tema', (int)($temaItem['id'] ?? 0)); ?>
                <div class="sidebar-tema <?= $temaActivo ? 'activo' : '' ?>">
                  <div class="tema-header">
                    <span class="tema-numero"><?= (int)($temaItem['orden'] ?? 0) ?>.</span>
                    <span class="tema-titulo"><?= htmlspecialchars($temaItem['titulo'] ?? 'Tema', ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($acceso): ?>
                      <?php if (!$temaActivo): ?>
                        <a class="tema-link" href="<?= BASE_URL ?>/estudiante/tema_contenido.php?id=<?= (int)($temaItem['id'] ?? 0) ?>">Ver</a>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>

                  <?php if (!empty($temaItem['subtemas'])): ?>
                    <?php foreach ($temaItem['subtemas'] as $subItem): ?>
                      <?php $subtemaActivo = $__esActivo('subtema', (int)($subItem['id'] ?? 0)); ?>
                      <div class="sidebar-subtema <?= $subtemaActivo ? 'activo' : '' ?>">
                        <div class="subtema-header">
                          <span class="subtema-titulo"><?= htmlspecialchars($subItem['titulo'] ?? 'Subtema', ENT_QUOTES, 'UTF-8') ?></span>
                          <?php if ($acceso): ?>
                            <?php if (!$subtemaActivo): ?>
                              <a class="subtema-link" href="<?= BASE_URL ?>/estudiante/subtema_contenido.php?id=<?= (int)($subItem['id'] ?? 0) ?>">Ver</a>
                            <?php endif; ?>
                          <?php endif; ?>
                        </div>

                        <?php if (!empty($subItem['lecciones'])): ?>
                          <div class="lecciones-lista">
                            <?php foreach ($subItem['lecciones'] as $lecItem): ?>
                              <?php 
                                $ok = !empty($lecItem['completada']); 
                                $leccionActiva = $__esActivo('leccion', (int)($lecItem['id'] ?? 0));
                              ?>
                              <div class="sidebar-leccion <?= $ok ? 'completada' : '' ?> <?= $leccionActiva ? 'activo' : '' ?>">
                                <span class="leccion-estado"><?= $ok ? '✓' : '○' ?></span>
                                <span class="leccion-titulo"><?= htmlspecialchars($lecItem['titulo'] ?? 'Lección', ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($acceso): ?>
                                  <?php if (!$leccionActiva): ?>
                                    <a class="leccion-link" href="<?= BASE_URL ?>/estudiante/leccion.php?id=<?= (int)($lecItem['id'] ?? 0) ?>">
                                      <?= $ok ? 'Revisar' : 'Estudiar' ?>
                                    </a>
                                  <?php endif; ?>
                                <?php endif; ?>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
// Namespace simple para evitar colisiones con otras vistas
window.IMT = window.IMT || {};
IMT.toggleModulo = function(moduloId) {
  const cont = document.getElementById('contenido-' + moduloId);
  const exp  = document.getElementById('expand-' + moduloId);
  if (!cont || !exp) return;
  const visible = (cont.style.display !== 'none' && cont.style.display !== '');
  cont.style.display = visible ? 'none' : 'block';
  exp.textContent = visible ? '▼' : '▲';
};

// Auto-expandir el módulo actual (si $moduloActualId > 0)
<?php if (!empty($moduloActualId)): ?>
document.addEventListener('DOMContentLoaded', function() {
  IMT.toggleModulo(<?= (int)$moduloActualId ?>);
});
<?php endif; ?>
</script>
