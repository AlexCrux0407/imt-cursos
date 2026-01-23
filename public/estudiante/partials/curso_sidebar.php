<?php
// Parcial Estudiante – Sidebar del curso
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

// ===== Modo Debug opcional para la sidebar =====
<?php $debugSidebar = isset($_GET['debug_sidebar']) && $_GET['debug_sidebar'] !== '0'; ?>
<?php if ($debugSidebar): ?>
  (function(){
    const pageName = '<?= basename($_SERVER['PHP_SELF']) ?>';
    function cssFiles(){
      return Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map(l => l.href.split('/').slice(-2).join('/'));
    }
    function metricsFor(sel){
      const el = document.querySelector(sel);
      if(!el) return { selector: sel, exists: false };
      const cs = getComputedStyle(el);
      const rect = el.getBoundingClientRect();
      return {
        selector: sel,
        exists: true,
        width: rect.width,
        height: rect.height,
        bg: cs.backgroundColor,
        color: cs.color,
        fontSize: cs.fontSize,
        lineHeight: cs.lineHeight,
        position: cs.position,
        top: cs.top,
        overflowY: cs.overflowY,
        borderRadius: cs.borderRadius,
        boxShadow: cs.boxShadow,
        borderColor: cs.borderColor,
        padding: cs.padding,
        margin: cs.margin
      };
    }
    function collect(){
      const sels = [
        '.sidebar-navegacion',
        '.sidebar-header',
        '.sidebar-titulo',
        '.sidebar-contenido',
        '.sidebar-modulo',
        '.sidebar-modulo .modulo-header',
        '.sidebar-modulo .modulo-titulo',
        '.sidebar-tema',
        '.sidebar-subtema',
        '.sidebar-leccion'
      ];
      return {
        page: pageName,
        cssFiles: cssFiles(),
        metrics: sels.map(metricsFor)
      };
    }
    function saveBaseline(){
      const data = collect();
      localStorage.setItem('IMT_sidebarBaseline', JSON.stringify(data));
      render(data, 'Baseline guardado');
    }
    function compare(){
      const baseStr = localStorage.getItem('IMT_sidebarBaseline');
      const curr = collect();
      if(!baseStr){ render(curr, 'No hay baseline. Guarda uno desde Curso.'); return; }
      const base = JSON.parse(baseStr);
      const diffs = [];
      curr.metrics.forEach(cm => {
        const bm = base.metrics.find(m => m.selector === cm.selector);
        if(!bm) return;
        Object.keys(cm).forEach(k => {
          if(['selector','exists'].includes(k)) return;
          const cv = cm[k];
          const bv = bm[k];
          if(String(cv) !== String(bv)){
            diffs.push({ selector: cm.selector, prop: k, baseline: String(bv), current: String(cv) });
          }
        });
      });
      render(curr, 'Comparación con baseline', diffs);
      // Señalar visualmente si hay diffs críticos
      if(diffs.some(d => d.selector === '.sidebar-navegacion' && (d.prop === 'bg' || d.prop === 'borderColor' || d.prop === 'boxShadow'))){
        const sn = document.querySelector('.sidebar-navegacion');
        if(sn){ sn.style.outline = '2px solid #e74c3c'; sn.style.outlineOffset = '2px'; }
      }
    }
    function render(data, title, diffs){
      let panel = document.getElementById('sidebar-debug-panel');
      if(!panel){
        panel = document.createElement('div');
        panel.id = 'sidebar-debug-panel';
        panel.style.position = 'fixed';
        panel.style.bottom = '10px';
        panel.style.right = '10px';
        panel.style.zIndex = '99999';
        panel.style.background = '#111827';
        panel.style.color = '#e5e7eb';
        panel.style.border = '1px solid #374151';
        panel.style.borderRadius = '8px';
        panel.style.padding = '10px';
        panel.style.fontSize = '12px';
        panel.style.maxWidth = '360px';
        panel.style.boxShadow = '0 8px 24px rgba(0,0,0,0.3)';
        document.body.appendChild(panel);
      }
      panel.innerHTML = '';
      const h = document.createElement('div'); h.textContent = `Sidebar Debug – ${title}`; h.style.fontWeight = '700'; h.style.marginBottom = '6px'; panel.appendChild(h);
      const info = document.createElement('div'); info.textContent = `Page: ${data.page}`; panel.appendChild(info);
      const css = document.createElement('div'); css.textContent = `CSS: ${data.cssFiles.join(', ')}`; css.style.margin = '6px 0'; panel.appendChild(css);
      const btns = document.createElement('div'); btns.style.display='flex'; btns.style.gap='6px'; btns.style.marginBottom='8px';
      const b1 = document.createElement('button'); b1.textContent = 'Guardar baseline'; b1.onclick = saveBaseline; stylizeBtn(b1);
      const b2 = document.createElement('button'); b2.textContent = 'Comparar baseline'; b2.onclick = compare; stylizeBtn(b2);
      const b3 = document.createElement('button'); b3.textContent = 'Ocultar'; b3.onclick = () => panel.style.display='none'; stylizeBtn(b3);
      btns.appendChild(b1); btns.appendChild(b2); btns.appendChild(b3); panel.appendChild(btns);
      if(diffs && diffs.length){
        const list = document.createElement('div');
        list.style.maxHeight = '200px'; list.style.overflow='auto'; list.style.borderTop='1px solid #374151'; list.style.paddingTop='6px';
        diffs.forEach(d => {
          const row = document.createElement('div');
          row.textContent = `${d.selector} – ${d.prop}: base=${d.baseline} vs curr=${d.current}`;
          list.appendChild(row);
        });
        panel.appendChild(list);
      }
      // dump en consola para inspección avanzada
      try{ console.group('IMT Sidebar Debug'); console.log('data', data); if(diffs) console.table(diffs); console.groupEnd(); }catch(e){}
    }
    function stylizeBtn(b){
      b.style.background = '#1f2937'; b.style.color='#e5e7eb'; b.style.border='1px solid #374151'; b.style.borderRadius='6px'; b.style.padding='6px 8px'; b.style.cursor='pointer';
      b.onmouseenter = () => { b.style.background = '#111827'; };
      b.onmouseleave = () => { b.style.background = '#1f2937'; };
    }
    // inicial
    document.addEventListener('DOMContentLoaded', function(){ render(collect(), 'Cargado'); });
  })();
<?php endif; ?>
</script>
