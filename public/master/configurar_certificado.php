<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('master');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Master – Configurar Certificado';
$curso_id = (int)($_GET['id'] ?? 0);
if ($curso_id === 0) {
    header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=curso_no_especificado');
    exit;
}

// Obtener curso
$stmt = $conn->prepare("SELECT id, titulo FROM cursos WHERE id = :curso_id");
$stmt->execute([':curso_id' => $curso_id]);
$curso = $stmt->fetch();
if (!$curso) {
    header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=curso_no_encontrado');
    exit;
}

// Obtener configuración existente
$stmt = $conn->prepare("SELECT * FROM certificados_config WHERE curso_id = :curso_id LIMIT 1");
$stmt->execute([':curso_id' => $curso_id]);
$config = $stmt->fetch();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/master.css">

<div class="contenido">
  <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; text-align: center;">
    <h1 style="margin:0; font-size: 2rem; font-weight: 600;">Configurar Certificado</h1>
    <p style="margin:10px 0 0 0; opacity:0.9;">Plantilla, posiciones y estilos de los campos</p>
  </div>

  <div class="form-container-body" style="margin-bottom: 20px;">
    <div class="div-fila" style="gap: 20px; align-items: center;">
      <div style="flex: 1;">
        <h3 style="color:#3498db; margin:0;">Curso: <?= htmlspecialchars($curso['titulo']) ?></h3>
      </div>
      <div>
        <a href="<?= BASE_URL ?>/master/editar_curso.php?id=<?= (int)$curso['id'] ?>" style="background:#6c757d; color:white; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:500;">← Volver</a>
      </div>
    </div>
  </div>

  <div class="form-container-body">
    <form action="<?= BASE_URL ?>/master/procesar_certificado.php" method="POST" enctype="multipart/form-data" id="certificadoForm">
      <input type="hidden" name="curso_id" value="<?= (int)$curso['id'] ?>">
      <div style="margin-bottom: 20px;">
        <label style="display:block; margin-bottom:8px; font-weight:500; color:#333;">Imagen de plantilla (PNG/JPG)</label>
        <input type="file" name="template" accept="image/png,image/jpeg" style="padding:8px; border:2px solid #e1e5e9; border-radius:8px;">
        <?php if (!empty($config['template_path'])): ?>
          <p style="margin-top:8px; color:#6c757d;">Actual: <?= htmlspecialchars($config['template_path']) ?></p>
        <?php endif; ?>
        <small style="color:#6c757d;">Si tienes PDF, conviértelo a imagen previamente.</small>
      </div>

      <div class="section">
        <h3>Estilos de Texto (Global)</h3>
        <div class="div-fila" style="gap: 15px;">
          <div style="flex:1;">
            <label>Fuente</label>
            <select name="font_family" id="font_family" style="width:100%; padding:10px; border:2px solid #e1e5e9; border-radius:8px;">
              <option value="Arial" <?= ($config['font_family'] ?? '') === 'Arial' ? 'selected' : '' ?>>Arial</option>
              <option value="Times New Roman" <?= ($config['font_family'] ?? '') === 'Times New Roman' ? 'selected' : '' ?>>Times New Roman</option>
              <option value="Verdana" <?= ($config['font_family'] ?? '') === 'Verdana' ? 'selected' : '' ?>>Verdana</option>
              <option value="Tahoma" <?= ($config['font_family'] ?? '') === 'Tahoma' ? 'selected' : '' ?>>Tahoma</option>
              <option value="Georgia" <?= ($config['font_family'] ?? '') === 'Georgia' ? 'selected' : '' ?>>Georgia</option>
            </select>
          </div>
          <div style="flex:1;">
            <label>Tamaño (pt)</label>
            <input type="number" name="font_size" id="font_size" value="<?= htmlspecialchars($config['font_size'] ?? '24') ?>" min="8" max="72" step="1" style="width:100%; padding:10px; border:2px solid #e1e5e9; border-radius:8px;">
          </div>
          <div style="flex:1;">
            <label>Color</label>
            <input type="color" name="font_color" id="font_color" value="<?= htmlspecialchars($config['font_color'] ?? '#000000') ?>" style="width:100%; padding:10px; border:2px solid #e1e5e9; border-radius:8px; height:42px;">
          </div>
        </div>
        <p style="color:#6b7280; margin-top:8px;">Estos estilos se aplican por defecto a todos los campos si no se define un estilo específico.</p>
      </div>

      <div class="section">
        <h3>Estilos por Campo</h3>
        <div class="div-fila" style="gap: 15px; flex-wrap: wrap;">
          <div style="flex:1; min-width:280px;">
            <label>Nombre - Fuente</label>
            <select name="nombre_font_family" id="nombre_font_family" style="width:100%; padding:10px; border:2px solid #e1e5e9; border-radius:8px;">
              <option value="">(Usar global)</option>
              <option value="Arial" <?= ($config['nombre_font_family'] ?? '') === 'Arial' ? 'selected' : '' ?>>Arial</option>
              <option value="Times New Roman" <?= ($config['nombre_font_family'] ?? '') === 'Times New Roman' ? 'selected' : '' ?>>Times New Roman</option>
              <option value="Verdana" <?= ($config['nombre_font_family'] ?? '') === 'Verdana' ? 'selected' : '' ?>>Verdana</option>
              <option value="Tahoma" <?= ($config['nombre_font_family'] ?? '') === 'Tahoma' ? 'selected' : '' ?>>Tahoma</option>
              <option value="Georgia" <?= ($config['nombre_font_family'] ?? '') === 'Georgia' ? 'selected' : '' ?>>Georgia</option>
            </select>
            <div class="div-fila" style="gap:10px; margin-top:8px;">
              <input type="number" name="nombre_font_size" id="nombre_font_size" value="<?= htmlspecialchars($config['nombre_font_size'] ?? '') ?>" placeholder="Tamaño (pt)" min="8" max="72" step="1" style="width:50%; padding:10px; border:2px solid #e1e5e9; border-radius:8px;">
              <input type="color" name="nombre_font_color" id="nombre_font_color" value="<?= htmlspecialchars($config['nombre_font_color'] ?? '') ?>" style="width:50%; padding:10px; border:2px solid #e1e5e9; border-radius:8px; height:42px;">
            </div>
          </div>

          <div style="flex:1; min-width:280px;">
            <label>Curso - Fuente</label>
            <select name="curso_font_family" id="curso_font_family" style="width:100%; padding:10px; border:2px solid #e1e5e9; border-radius:8px;">
              <option value="">(Usar global)</option>
              <option value="Arial" <?= ($config['curso_font_family'] ?? '') === 'Arial' ? 'selected' : '' ?>>Arial</option>
              <option value="Times New Roman" <?= ($config['curso_font_family'] ?? '') === 'Times New Roman' ? 'selected' : '' ?>>Times New Roman</option>
              <option value="Verdana" <?= ($config['curso_font_family'] ?? '') === 'Verdana' ? 'selected' : '' ?>>Verdana</option>
              <option value="Tahoma" <?= ($config['curso_font_family'] ?? '') === 'Tahoma' ? 'selected' : '' ?>>Tahoma</option>
              <option value="Georgia" <?= ($config['curso_font_family'] ?? '') === 'Georgia' ? 'selected' : '' ?>>Georgia</option>
            </select>
            <div class="div-fila" style="gap:10px; margin-top:8px;">
              <input type="number" name="curso_font_size" id="curso_font_size" value="<?= htmlspecialchars($config['curso_font_size'] ?? '') ?>" placeholder="Tamaño (pt)" min="8" max="72" step="1" style="width:50%; padding:10px; border:2px solid #e1e5e9; border-radius:8px;">
              <input type="color" name="curso_font_color" id="curso_font_color" value="<?= htmlspecialchars($config['curso_font_color'] ?? '') ?>" style="width:50%; padding:10px; border:2px solid #e1e5e9; border-radius:8px; height:42px;">
            </div>
          </div>

          <?php // Mostrar sección de calificación solo si la opción está activada ?>
          <div style="flex:1; min-width:280px; <?= (int)($config['mostrar_calificacion'] ?? 0) !== 1 ? 'display:none;' : '' ?>">
            <label>Calificación - Fuente</label>
            <select name="calificacion_font_family" id="calificacion_font_family" style="width:100%; padding:10px; border:2px solid #e1e5e9; border-radius:8px;">
              <option value="">(Usar global)</option>
              <option value="Arial" <?= ($config['calificacion_font_family'] ?? '') === 'Arial' ? 'selected' : '' ?>>Arial</option>
              <option value="Times New Roman" <?= ($config['calificacion_font_family'] ?? '') === 'Times New Roman' ? 'selected' : '' ?>>Times New Roman</option>
              <option value="Verdana" <?= ($config['calificacion_font_family'] ?? '') === 'Verdana' ? 'selected' : '' ?>>Verdana</option>
              <option value="Tahoma" <?= ($config['calificacion_font_family'] ?? '') === 'Tahoma' ? 'selected' : '' ?>>Tahoma</option>
              <option value="Georgia" <?= ($config['calificacion_font_family'] ?? '') === 'Georgia' ? 'selected' : '' ?>>Georgia</option>
            </select>
            <div class="div-fila" style="gap:10px; margin-top:8px;">
              <input type="number" name="calificacion_font_size" id="calificacion_font_size" value="<?= htmlspecialchars($config['calificacion_font_size'] ?? '') ?>" placeholder="Tamaño (pt)" min="8" max="72" step="1" style="width:50%; padding:10px; border:2px solid #e1e5e9; border-radius:8px;">
              <input type="color" name="calificacion_font_color" id="calificacion_font_color" value="<?= htmlspecialchars($config['calificacion_font_color'] ?? '') ?>" style="width:50%; padding:10px; border:2px solid #e1e5e9; border-radius:8px; height:42px;">
            </div>
          </div>

          <div style="flex:1; min-width:280px;">
            <label>Fecha - Fuente</label>
            <select name="fecha_font_family" id="fecha_font_family" style="width:100%; padding:10px; border:2px solid #e1e5e9; border-radius:8px;">
              <option value="">(Usar global)</option>
              <option value="Arial" <?= ($config['fecha_font_family'] ?? '') === 'Arial' ? 'selected' : '' ?>>Arial</option>
              <option value="Times New Roman" <?= ($config['fecha_font_family'] ?? '') === 'Times New Roman' ? 'selected' : '' ?>>Times New Roman</option>
              <option value="Verdana" <?= ($config['fecha_font_family'] ?? '') === 'Verdana' ? 'selected' : '' ?>>Verdana</option>
              <option value="Tahoma" <?= ($config['fecha_font_family'] ?? '') === 'Tahoma' ? 'selected' : '' ?>>Tahoma</option>
              <option value="Georgia" <?= ($config['fecha_font_family'] ?? '') === 'Georgia' ? 'selected' : '' ?>>Georgia</option>
            </select>
            <div class="div-fila" style="gap:10px; margin-top:8px;">
              <input type="number" name="fecha_font_size" id="fecha_font_size" value="<?= htmlspecialchars($config['fecha_font_size'] ?? '') ?>" placeholder="Tamaño (pt)" min="8" max="72" step="1" style="width:50%; padding:10px; border:2px solid #e1e5e9; border-radius:8px;">
              <input type="color" name="fecha_font_color" id="fecha_font_color" value="<?= htmlspecialchars($config['fecha_font_color'] ?? '') ?>" style="width:50%; padding:10px; border:2px solid #e1e5e9; border-radius:8px; height:42px;">
            </div>
          </div>
        </div>
        <p style="color:#6b7280; margin-top:8px;">Si dejas vacío un estilo, se usará el estilo global.</p>
      </div>

      <div style="margin-bottom: 20px;">
        <label style="display:block; margin-bottom:8px; font-weight:500; color:#333;">Campos y posiciones (arrastrables)</label>
        <div id="templatePreview" style="position:relative; border:1px solid #e1e5e9; border-radius:8px; overflow:hidden;">
          <?php if (!empty($config['template_path'])): ?>
            <img id="previewImage" src="<?= BASE_URL . '/' . ltrim($config['template_path'], '/') ?>" alt="Plantilla" style="width:100%; display:block;">
            <div class="drag-field" id="field-nombre">Nombre del Estudiante</div>
            <div class="drag-field" id="field-curso">Nombre del Curso</div>
            <?php if ((int)($config['mostrar_calificacion'] ?? 0) === 1): ?>
              <div class="drag-field" id="field-calificacion">Calificación</div>
            <?php endif; ?>
            <div class="drag-field" id="field-fecha">Fecha de Completado</div>
          <?php else: ?>
            <div style="padding:20px; color:#6c757d;">Sube una imagen de plantilla para activar la vista previa y arrastrar los campos.</div>
          <?php endif; ?>
        </div>
        <style>
          .drag-field { position:absolute; padding:6px 10px; background:rgba(52,152,219,0.15); border:1px dashed #3498db; border-radius:6px; color:#2c3e50; cursor:move; user-select:none; }
        </style>
        <input type="hidden" name="nombre_x" id="nombre_x" value="<?= htmlspecialchars($config['nombre_x'] ?? '') ?>">
        <input type="hidden" name="nombre_y" id="nombre_y" value="<?= htmlspecialchars($config['nombre_y'] ?? '') ?>">
        <input type="hidden" name="curso_x" id="curso_x" value="<?= htmlspecialchars($config['curso_x'] ?? '') ?>">
        <input type="hidden" name="curso_y" id="curso_y" value="<?= htmlspecialchars($config['curso_y'] ?? '') ?>">
        <input type="hidden" name="calificacion_x" id="calificacion_x" value="<?= htmlspecialchars($config['calificacion_x'] ?? '') ?>">
        <input type="hidden" name="calificacion_y" id="calificacion_y" value="<?= htmlspecialchars($config['calificacion_y'] ?? '') ?>">
        <input type="hidden" name="fecha_x" id="fecha_x" value="<?= htmlspecialchars($config['fecha_x'] ?? '') ?>">
        <input type="hidden" name="fecha_y" id="fecha_y" value="<?= htmlspecialchars($config['fecha_y'] ?? '') ?>">

        <div class="div-fila" style="gap: 15px; margin-top: 10px; flex-wrap: wrap;">
          <div style="flex:1; min-width:260px;">
            <label>Nombre (x%, y%)</label>
            <div class="div-fila" style="gap:10px;">
              <input type="number" step="0.1" min="0" max="100" id="nombre_x_input" value="<?= htmlspecialchars($config['nombre_x'] ?? '') ?>" style="width:100%; padding:8px; border:2px solid #e1e5e9; border-radius:8px;">
              <input type="number" step="0.1" min="0" max="100" id="nombre_y_input" value="<?= htmlspecialchars($config['nombre_y'] ?? '') ?>" style="width:100%; padding:8px; border:2px solid #e1e5e9; border-radius:8px;">
            </div>
          </div>
          <div style="flex:1; min-width:260px;">
            <label>Curso (x%, y%)</label>
            <div class="div-fila" style="gap:10px;">
              <input type="number" step="0.1" min="0" max="100" id="curso_x_input" value="<?= htmlspecialchars($config['curso_x'] ?? '') ?>" style="width:100%; padding:8px; border:2px solid #e1e5e9; border-radius:8px;">
              <input type="number" step="0.1" min="0" max="100" id="curso_y_input" value="<?= htmlspecialchars($config['curso_y'] ?? '') ?>" style="width:100%; padding:8px; border:2px solid #e1e5e9; border-radius:8px;">
            </div>
          </div>
          <div style="flex:1; min-width:260px;">
            <label>Calificación (x%, y%)</label>
            <div class="div-fila" style="gap:10px;">
              <input type="number" step="0.1" min="0" max="100" id="calificacion_x_input" value="<?= htmlspecialchars($config['calificacion_x'] ?? '') ?>" style="width:100%; padding:8px; border:2px solid #e1e5e9; border-radius:8px;">
              <input type="number" step="0.1" min="0" max="100" id="calificacion_y_input" value="<?= htmlspecialchars($config['calificacion_y'] ?? '') ?>" style="width:100%; padding:8px; border:2px solid #e1e5e9; border-radius:8px;">
            </div>
          </div>
          <div style="flex:1; min-width:260px;">
            <label>Fecha (x%, y%)</label>
            <div class="div-fila" style="gap:10px;">
              <input type="number" step="0.1" min="0" max="100" id="fecha_x_input" value="<?= htmlspecialchars($config['fecha_x'] ?? '') ?>" style="width:100%; padding:8px; border:2px solid #e1e5e9; border-radius:8px;">
              <input type="number" step="0.1" min="0" max="100" id="fecha_y_input" value="<?= htmlspecialchars($config['fecha_y'] ?? '') ?>" style="width:100%; padding:8px; border:2px solid #e1e5e9; border-radius:8px;">
            </div>
          </div>
        </div>
      </div>

      <div style="margin-bottom: 20px;">
        <label style="display:block; margin-bottom:8px; font-weight:500; color:#333;">Opciones</label>
        <div>
          <label style="display:flex; align-items:center; gap:8px;">
            <input type="checkbox" name="mostrar_calificacion" value="1" <?= (int)($config['mostrar_calificacion'] ?? 0) === 1 ? 'checked' : '' ?>> Mostrar calificación en el certificado
          </label>
        </div>
        <div style="margin-top:8px;">
          <label>Vigencia de descarga (días)</label>
          <input type="number" name="valid_days" min="1" max="60" value="<?= (int)($config['valid_days'] ?? 15) ?>" style="width:100%; padding:10px; border:2px solid #e1e5e9; border-radius:8px;">
          <small style="color:#6c757d;">Por defecto: 15 días</small>
        </div>
      </div>

      <div class="div-fila-alt" style="gap: 15px;">
        <button type="submit" style="background:#28a745; color:white; padding:12px 24px; border:none; border-radius:8px; font-size:1rem; font-weight:500; cursor:pointer;">Guardar Configuración</button>
        <a href="<?= BASE_URL ?>/master/editar_curso.php?id=<?= (int)$curso['id'] ?>" style="background:#6c757d; color:white; padding:12px 24px; border-radius:8px; text-decoration:none; font-size:1rem; font-weight:500;">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script>
      function ptToPx(pt) { return pt * (96 / 72); }

      function applyStylesField(el, familyInput, sizeInput, colorInput) {
        const globalFamily = document.getElementById('font_family').value || 'Arial';
        const globalSizePt = parseInt(document.getElementById('font_size').value || '24', 10);
        const globalColor = document.getElementById('font_color').value || '#000000';

        const family = (familyInput && familyInput.value) ? familyInput.value : globalFamily;
        const sizePt = (sizeInput && sizeInput.value) ? parseInt(sizeInput.value, 10) : globalSizePt;
        const color = (colorInput && colorInput.value) ? colorInput.value : globalColor;

        el.style.fontFamily = family;
        el.style.fontSize = ptToPx(sizePt) + 'px';
        el.style.color = color;
        el.style.textAlign = 'center';
        el.style.transform = 'translate(-50%, -50%)';
      }

      function applyStylesAll() {
        const nombreEl = document.getElementById('field-nombre');
        const cursoEl = document.getElementById('field-curso');
        const calEl = document.getElementById('field-calificacion');
        const fechaEl = document.getElementById('field-fecha');

        const nombreFamily = document.getElementById('nombre_font_family');
        const nombreSize = document.getElementById('nombre_font_size');
        const nombreColor = document.getElementById('nombre_font_color');

        const cursoFamily = document.getElementById('curso_font_family');
        const cursoSize = document.getElementById('curso_font_size');
        const cursoColor = document.getElementById('curso_font_color');

        const calFamily = document.getElementById('calificacion_font_family');
        const calSize = document.getElementById('calificacion_font_size');
        const calColor = document.getElementById('calificacion_font_color');

        const fechaFamily = document.getElementById('fecha_font_family');
        const fechaSize = document.getElementById('fecha_font_size');
        const fechaColor = document.getElementById('fecha_font_color');

        if (nombreEl) applyStylesField(nombreEl, nombreFamily, nombreSize, nombreColor);
        if (cursoEl) applyStylesField(cursoEl, cursoFamily, cursoSize, cursoColor);
        if (calEl) applyStylesField(calEl, calFamily, calSize, calColor);
        if (fechaEl) applyStylesField(fechaEl, fechaFamily, fechaSize, fechaColor);
      }

      function makeDraggable(fieldId, hiddenXId, hiddenYId, inputXId, inputYId) {
        const field = document.getElementById(fieldId);
        const container = document.getElementById('templatePreview');
        const hiddenX = document.getElementById(hiddenXId);
        const hiddenY = document.getElementById(hiddenYId);
        const inputX = document.getElementById(inputXId);
        const inputY = document.getElementById(inputYId);
        if (!field || !container) return;

        function setPosFromPerc(percX, percY) {
          const rect = container.getBoundingClientRect();
          const xPx = rect.width * (percX / 100);
          const yPx = rect.height * (percY / 100);
          field.style.left = xPx + 'px';
          field.style.top = yPx + 'px';
        }

        function syncHidden(percX, percY) {
          hiddenX.value = percX;
          hiddenY.value = percY;
          inputX.value = percX;
          inputY.value = percY;
        }

        field.addEventListener('mousedown', function(e) {
          e.preventDefault();
          const startRect = container.getBoundingClientRect();
          const startX = e.clientX - startRect.left;
          const startY = e.clientY - startRect.top;
          const origLeft = parseFloat(field.style.left || '0');
          const origTop = parseFloat(field.style.top || '0');

          function onMouseMove(ev) {
            const rect = container.getBoundingClientRect();
            const deltaX = ev.clientX - startRect.left - startX;
            const deltaY = ev.clientY - startRect.top - startY;
            let newLeft = origLeft + deltaX;
            let newTop = origTop + deltaY;
            newLeft = Math.max(0, Math.min(newLeft, rect.width));
            newTop = Math.max(0, Math.min(newTop, rect.height));
            field.style.left = newLeft + 'px';
            field.style.top = newTop + 'px';
            const percX = (newLeft / rect.width) * 100;
            const percY = (newTop / rect.height) * 100;
            syncHidden(parseFloat(percX.toFixed(2)), parseFloat(percY.toFixed(2)));
          }

          function onMouseUp() {
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
          }

          document.addEventListener('mousemove', onMouseMove);
          document.addEventListener('mouseup', onMouseUp);
        });

        inputX.addEventListener('change', function() {
          const x = parseFloat(inputX.value || '0');
          const y = parseFloat(inputY.value || '0');
          setPosFromPerc(x, y);
          syncHidden(x, y);
        });
        inputY.addEventListener('change', function() {
          const x = parseFloat(inputX.value || '0');
          const y = parseFloat(inputY.value || '0');
          setPosFromPerc(x, y);
          syncHidden(x, y);
        });

        const initX = parseFloat(hiddenX.value || inputX.value || '50');
        const initY = parseFloat(hiddenY.value || inputY.value || '50');
        setPosFromPerc(initX, initY);
        syncHidden(initX, initY);
      }

      window.addEventListener('load', function() {
        applyStylesAll();
        makeDraggable('field-nombre', 'nombre_x', 'nombre_y', 'nombre_x_input', 'nombre_y_input');
        makeDraggable('field-curso', 'curso_x', 'curso_y', 'curso_x_input', 'curso_y_input');
        <?php if ((int)($config['mostrar_calificacion'] ?? 0) === 1): ?>
          makeDraggable('field-calificacion', 'calificacion_x', 'calificacion_y', 'calificacion_x_input', 'calificacion_y_input');
        <?php endif; ?>
        makeDraggable('field-fecha', 'fecha_x', 'fecha_y', 'fecha_x_input', 'fecha_y_input');

        // Estilos en tiempo real: globales y por campo
        const styleInputs = [
          'font_family','font_size','font_color',
          'nombre_font_family','nombre_font_size','nombre_font_color',
          'curso_font_family','curso_font_size','curso_font_color',
          'calificacion_font_family','calificacion_font_size','calificacion_font_color',
          'fecha_font_family','fecha_font_size','fecha_font_color'
        ];
        styleInputs.forEach(id => {
          const el = document.getElementById(id);
          if (!el) return;
          el.addEventListener('input', applyStylesAll);
          el.addEventListener('change', applyStylesAll);
        });
      });
    </script>

<?php require __DIR__ . '/../partials/footer.php'; ?>