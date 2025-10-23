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

      <div style="margin-bottom: 20px;">
        <label style="display:block; margin-bottom:8px; font-weight:500; color:#333;">Estilos de texto</label>
        <div class="div-fila" style="gap: 15px;">
          <div style="flex:1;">
            <select name="font_family" style="width:100%; padding:10px; border:2px solid #e1e5e9; border-radius:8px;">
              <?php $font = $config['font_family'] ?? 'helvetica'; ?>
              <option value="helvetica" <?= $font==='helvetica'?'selected':'' ?>>Helvetica</option>
              <option value="times" <?= $font==='times'?'selected':'' ?>>Times</option>
              <option value="courier" <?= $font==='courier'?'selected':'' ?>>Courier</option>
              <option value="dejavusans" <?= $font==='dejavusans'?'selected':'' ?>>DejaVu Sans</option>
            </select>
          </div>
          <div style="flex:1;">
            <input type="number" name="font_size" min="10" max="72" value="<?= (int)($config['font_size'] ?? 24) ?>" style="width:100%; padding:10px; border:2px solid #e1e5e9; border-radius:8px;" placeholder="Tamaño (pt)">
          </div>
          <div style="flex:1;">
            <input type="text" name="font_color" value="<?= htmlspecialchars($config['font_color'] ?? '#000000') ?>" style="width:100%; padding:10px; border:2px solid #e1e5e9; border-radius:8px;" placeholder="#000000">
          </div>
        </div>
      </div>

      <div style="margin-bottom: 20px;">
        <label style="display:block; margin-bottom:8px; font-weight:500; color:#333;">Campos y posiciones (arrastrables)</label>
        <div id="templatePreview" style="position:relative; border:1px solid #e1e5e9; border-radius:8px; overflow:hidden;">
          <?php if (!empty($config['template_path'])): ?>
            <img id="previewImage" src="<?= BASE_URL . '/' . ltrim($config['template_path'], '/') ?>" alt="Plantilla" style="width:100%; display:block;">
            <div class="drag-field" id="field-nombre">Nombre del Estudiante</div>
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
        <input type="hidden" name="calificacion_x" id="calificacion_x" value="<?= htmlspecialchars($config['calificacion_x'] ?? '') ?>">
        <input type="hidden" name="calificacion_y" id="calificacion_y" value="<?= htmlspecialchars($config['calificacion_y'] ?? '') ?>">
        <input type="hidden" name="fecha_x" id="fecha_x" value="<?= htmlspecialchars($config['fecha_x'] ?? '') ?>">
        <input type="hidden" name="fecha_y" id="fecha_y" value="<?= htmlspecialchars($config['fecha_y'] ?? '') ?>">

        <div class="div-fila" style="gap: 15px; margin-top: 10px;">
          <div style="flex:1;">
            <label>Nombre (x%, y%)</label>
            <div class="div-fila" style="gap:10px;">
              <input type="number" step="0.1" min="0" max="100" id="nombre_x_input" value="<?= htmlspecialchars($config['nombre_x'] ?? '') ?>" style="width:100%; padding:8px; border:2px solid #e1e5e9; border-radius:8px;">
              <input type="number" step="0.1" min="0" max="100" id="nombre_y_input" value="<?= htmlspecialchars($config['nombre_y'] ?? '') ?>" style="width:100%; padding:8px; border:2px solid #e1e5e9; border-radius:8px;">
            </div>
          </div>
          <div style="flex:1;">
            <label>Calificación (x%, y%)</label>
            <div class="div-fila" style="gap:10px;">
              <input type="number" step="0.1" min="0" max="100" id="calificacion_x_input" value="<?= htmlspecialchars($config['calificacion_x'] ?? '') ?>" style="width:100%; padding:8px; border:2px solid #e1e5e9; border-radius:8px;">
              <input type="number" step="0.1" min="0" max="100" id="calificacion_y_input" value="<?= htmlspecialchars($config['calificacion_y'] ?? '') ?>" style="width:100%; padding:8px; border:2px solid #e1e5e9; border-radius:8px;">
            </div>
          </div>
          <div style="flex:1;">
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
(function(){
  const img = document.getElementById('previewImage');
  const fNombre = document.getElementById('field-nombre');
  const fCalif = document.getElementById('field-calificacion');
  const fFecha = document.getElementById('field-fecha');
  const nombreX = document.getElementById('nombre_x');
  const nombreY = document.getElementById('nombre_y');
  const califX = document.getElementById('calificacion_x');
  const califY = document.getElementById('calificacion_y');
  const fechaX = document.getElementById('fecha_x');
  const fechaY = document.getElementById('fecha_y');

  const nombreXInput = document.getElementById('nombre_x_input');
  const nombreYInput = document.getElementById('nombre_y_input');
  const califXInput = document.getElementById('calificacion_x_input');
  const califYInput = document.getElementById('calificacion_y_input');
  const fechaXInput = document.getElementById('fecha_x_input');
  const fechaYInput = document.getElementById('fecha_y_input');

  function setInitial(field, xPerc, yPerc) {
    if (!img || !field || !xPerc || !yPerc) return;
    const rect = img.getBoundingClientRect();
    field.style.left = (rect.width * (parseFloat(xPerc) / 100)) + 'px';
    field.style.top = (rect.height * (parseFloat(yPerc) / 100)) + 'px';
  }

  function makeDraggable(field, xHidden, yHidden, xInput, yInput) {
    if (!field || !img) return;
    let isDragging = false;
    field.addEventListener('mousedown', function(e){ isDragging = true; e.preventDefault(); });
    document.addEventListener('mouseup', function(){ isDragging = false; });
    document.addEventListener('mousemove', function(e){
      if(!isDragging) return;
      const rect = img.getBoundingClientRect();
      const container = document.getElementById('templatePreview').getBoundingClientRect();
      let x = e.clientX - rect.left;
      let y = e.clientY - rect.top;
      if (x < 0) x = 0; if (y < 0) y = 0;
      if (x > rect.width) x = rect.width;
      if (y > rect.height) y = rect.height;
      field.style.left = x + 'px';
      field.style.top = y + 'px';
      const xPerc = (x / rect.width) * 100;
      const yPerc = (y / rect.height) * 100;
      if (xHidden) xHidden.value = xPerc.toFixed(2);
      if (yHidden) yHidden.value = yPerc.toFixed(2);
      if (xInput) xInput.value = xPerc.toFixed(2);
      if (yInput) yInput.value = yPerc.toFixed(2);
    });
  }

  if (img) {
    img.addEventListener('load', function(){
      setInitial(fNombre, nombreX.value, nombreY.value);
      setInitial(fCalif, califX.value, califY.value);
      setInitial(fFecha, fechaX.value, fechaY.value);
    });
    makeDraggable(fNombre, nombreX, nombreY, nombreXInput, nombreYInput);
    makeDraggable(fCalif, califX, califY, califXInput, califYInput);
    makeDraggable(fFecha, fechaX, fechaY, fechaXInput, fechaYInput);

    // Inputs manuales
    function bindInputs(field, xHidden, yHidden, xInput, yInput){
      [xInput, yInput].forEach(inp => {
        if (!inp) return;
        inp.addEventListener('input', function(){
          const rect = img.getBoundingClientRect();
          const x = Math.max(0, Math.min(rect.width, rect.width * (parseFloat(xInput.value || 0) / 100)));
          const y = Math.max(0, Math.min(rect.height, rect.height * (parseFloat(yInput.value || 0) / 100)));
          field.style.left = x + 'px';
          field.style.top = y + 'px';
          xHidden.value = (parseFloat(xInput.value || 0)).toFixed(2);
          yHidden.value = (parseFloat(yInput.value || 0)).toFixed(2);
        });
      });
    }
    bindInputs(fNombre, nombreX, nombreY, nombreXInput, nombreYInput);
    bindInputs(fCalif, califX, califY, califXInput, califYInput);
    bindInputs(fFecha, fechaX, fechaY, fechaXInput, fechaYInput);
  }
})();
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>