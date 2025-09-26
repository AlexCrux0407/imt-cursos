<?php
// Seguridad de variables (por si la vista llamante no las definió)
$curso_estructura   = $curso_estructura   ?? [];
$cursoTituloSidebar = $cursoTituloSidebar ?? 'Curso';
$moduloActualId     = isset($moduloActualId) ? (int)$moduloActualId : 0;

// Helper: determina si un módulo es accesible (mismo criterio que usas en vistas)
$__puedeAcceder = function(array $mods, int $targetId): bool {
    if (empty($mods)) return true;
    $modsOrden = array_values($mods);
    usort($modsOrden, fn($a, $b) => (int)($a['orden'] ?? 0) <=> (int)($b['orden'] ?? 0));
    foreach ($modsOrden as $idx => $m) {
        if ((int)($m['id'] ?? 0) === $targetId) {
            if ($idx === 0) return true; // primer módulo
            $prev = $modsOrden[$idx - 1];
            $tot  = (int)($prev['total_lecciones'] ?? 0);
            $done = (int)($prev['lecciones_completadas'] ?? 0);
            return $tot > 0 && $done >= $tot;
        }
    }
    return true; // fallback
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
        $genTotal += (int)($mCalc['total_lecciones'] ?? 0);
        $genDone  += (int)($mCalc['lecciones_completadas'] ?? 0);
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
          $tot      = (int)($modItem['total_lecciones'] ?? 0);
          $done     = (int)($modItem['lecciones_completadas'] ?? 0);
          $pMod     = $tot > 0 ? ($done / $tot) * 100 : 0;
          $completo = $tot > 0 && $done >= $tot;
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
                <small><?= $done ?>/<?= $tot ?> lecciones</small>
                <div class="barra-mini">
                  <div class="fill-mini" style="width: <?= $pMod ?>%"></div>
                </div>
              </div>
            </div>
            <div class="expand-icon" id="expand-<?= $modId ?>">▼</div>
          </div>

          <div class="modulo-contenido" id="contenido-<?= $modId ?>" style="display: none;">
            <?php if ($acceso): ?>
              <a class="modulo-link" href="<?= BASE_URL ?>/estudiante/modulo_contenido.php?id=<?= $modId ?>">
                📄 Ver contenido del módulo
              </a>
            <?php endif; ?>

            <?php if (!empty($modItem['temas'])): ?>
              <?php foreach ($modItem['temas'] as $temaItem): ?>
                <div class="sidebar-tema">
                  <div class="tema-header">
                    <span class="tema-numero"><?= (int)($temaItem['orden'] ?? 0) ?>.</span>
                    <span class="tema-titulo"><?= htmlspecialchars($temaItem['titulo'] ?? 'Tema', ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($acceso): ?>
                      <a class="tema-link" href="<?= BASE_URL ?>/estudiante/tema_contenido.php?id=<?= (int)($temaItem['id'] ?? 0) ?>">Ver</a>
                    <?php endif; ?>
                  </div>

                  <?php if (!empty($temaItem['subtemas'])): ?>
                    <?php foreach ($temaItem['subtemas'] as $subItem): ?>
                      <div class="sidebar-subtema">
                        <div class="subtema-header">
                          <span class="subtema-titulo"><?= htmlspecialchars($subItem['titulo'] ?? 'Subtema', ENT_QUOTES, 'UTF-8') ?></span>
                          <?php if ($acceso): ?>
                            <a class="subtema-link" href="<?= BASE_URL ?>/estudiante/subtema_contenido.php?id=<?= (int)($subItem['id'] ?? 0) ?>">Ver</a>
                          <?php endif; ?>
                        </div>

                        <?php if (!empty($subItem['lecciones'])): ?>
                          <div class="lecciones-lista">
                            <?php foreach ($subItem['lecciones'] as $lecItem): ?>
                              <?php $ok = !empty($lecItem['completada']); ?>
                              <div class="sidebar-leccion <?= $ok ? 'completada' : '' ?>">
                                <span class="leccion-estado"><?= $ok ? '✓' : '○' ?></span>
                                <span class="leccion-titulo"><?= htmlspecialchars($lecItem['titulo'] ?? 'Lección', ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($acceso): ?>
                                  <a class="leccion-link" href="<?= BASE_URL ?>/estudiante/leccion.php?id=<?= (int)($lecItem['id'] ?? 0) ?>">
                                    <?= $ok ? 'Revisar' : 'Estudiar' ?>
                                  </a>
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
