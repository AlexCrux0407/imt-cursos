<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Gestionar Preguntas';

$evaluacion_id = $_GET['id'] ?? 0;
$modulo_id = $_GET['modulo_id'] ?? 0;
$curso_id = $_GET['curso_id'] ?? 0;

// Verificar que la evaluación pertenece a un módulo del docente
$stmt = $conn->prepare("
    SELECT e.*, m.titulo as modulo_titulo, c.titulo as curso_titulo
    FROM evaluaciones_modulo e
    INNER JOIN modulos m ON e.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE e.id = :evaluacion_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id)
");
$stmt->execute([':evaluacion_id' => $evaluacion_id, ':docente_id' => $_SESSION['user_id']]);
$evaluacion = $stmt->fetch();

if (!$evaluacion) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=evaluacion_no_encontrada');
    exit;
}

// Obtener preguntas de la evaluación
$stmt = $conn->prepare("
    SELECT * FROM preguntas_evaluacion 
    WHERE evaluacion_id = :evaluacion_id 
    ORDER BY orden ASC, id ASC
");
$stmt->execute([':evaluacion_id' => $evaluacion_id]);
$preguntas = $stmt->fetchAll();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/docente.css">

<style>
.pregunta-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-left: 4px solid #3498db;
    transition: all 0.3s ease;
}

.pregunta-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.pregunta-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.pregunta-info h4 {
    color: #2c3e50;
    margin: 0 0 8px 0;
    font-size: 1.1rem;
}

.pregunta-tipo {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    text-transform: uppercase;
}

.tipo-multiple_choice { background: #e8f4fd; color: #2980b9; }
.tipo-verdadero_falso { background: #e8f5e8; color: #27ae60; }
.tipo-texto_corto { background: #fef9e7; color: #f39c12; }
.tipo-texto_largo { background: #fdeaea; color: #e74c3c; }
.tipo-seleccion_multiple { background: #f3e5f5; color: #8e44ad; }

.pregunta-contenido {
    margin: 16px 0;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 8px;
}

.opciones-lista {
    margin-top: 12px;
}

.opcion-item {
    padding: 8px 12px;
    margin: 4px 0;
    border-radius: 6px;
    background: white;
    border: 1px solid #e8ecef;
}

.opcion-correcta {
    background: #d4edda;
    border-color: #27ae60;
    color: #155724;
    font-weight: 500;
}

.pregunta-stats {
    display: flex;
    gap: 20px;
    margin: 12px 0;
    font-size: 0.9rem;
    color: #7f8c8d;
}

.pregunta-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-action {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s ease;
}

.btn-primary { background: #3498db; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-warning { background: #f39c12; color: white; }
.btn-danger { background: #e74c3c; color: white; }
.btn-secondary { background: #95a5a6; color: white; }

.btn-action:hover {
    transform: translateY(-1px);
    opacity: 0.9;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #7f8c8d;
}

.empty-state img {
    width: 120px;
    opacity: 0.5;
    margin-bottom: 20px;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    width: 90%;
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    color: #2c3e50;
    margin-bottom: 8px;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #e8ecef;
    border-radius: 8px;
    font-size: 16px;
}

.form-control:focus {
    border-color: #3498db;
    outline: none;
}

.opciones-container {
    display: none;
}

.opcion-input {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.opcion-input input[type="text"] {
    flex: 1;
}

.opcion-input input[type="radio"] {
    margin: 0;
}

.btn-add-opcion {
    background: #3498db;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
}

.btn-remove-opcion {
    background: #e74c3c;
    color: white;
    border: none;
    padding: 6px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.8rem;
}
</style>

<div class="contenido">
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white;">
        <div class="div-fila-alt-start">
            <div>
                <h1 style="font-size: 2rem; margin-bottom: 10px;">Gestionar Preguntas</h1>
                <p style="opacity: 0.9;"><?= htmlspecialchars($evaluacion['titulo']) ?> - <?= htmlspecialchars($evaluacion['modulo_titulo']) ?></p>
            </div>
            <div style="display: flex; gap: 12px;">
                <button onclick="window.location.href='<?= BASE_URL ?>/docente/evaluaciones_modulo.php?id=<?= $modulo_id ?>&curso_id=<?= $curso_id ?>'" 
                        style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    ← Volver a Evaluaciones
                </button>
                <button onclick="mostrarFormularioNuevaPregunta()" 
                        class="btn-action btn-primary">
                    + Nueva Pregunta
                </button>
            </div>
        </div>
    </div>

    <div class="form-container-body">
        <?php if (empty($preguntas)): ?>
            <div class="empty-state">
                <img src="<?= BASE_URL ?>/styles/iconos/pregunta.png" alt="Sin preguntas">
                <h3>No hay preguntas creadas</h3>
                <p>Crea la primera pregunta para esta evaluación para que los estudiantes puedan completarla.</p>
                <button onclick="mostrarFormularioNuevaPregunta()" class="btn-action btn-primary" style="margin-top: 20px;">
                    Crear Primera Pregunta
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($preguntas as $index => $pregunta): ?>
                <div class="pregunta-card">
                    <div class="pregunta-header">
                        <div class="pregunta-info">
                            <h4>Pregunta <?= $index + 1 ?></h4>
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                <span class="pregunta-tipo tipo-<?= $pregunta['tipo'] ?>">
                                    <?= str_replace('_', ' ', ucfirst($pregunta['tipo'])) ?>
                                </span>
                                <span style="color: #7f8c8d; font-size: 0.9rem;">Orden: <?= $pregunta['orden'] ?></span>
                            </div>
                        </div>
                        <div class="pregunta-stats">
                            <span><strong><?= $pregunta['puntaje'] ?></strong> puntos</span>
                        </div>
                    </div>

                    <div class="pregunta-contenido">
                        <div style="font-weight: 500; color: #2c3e50; margin-bottom: 12px;">
                            <?= nl2br(htmlspecialchars($pregunta['pregunta'])) ?>
                        </div>

                        <?php if (in_array($pregunta['tipo'], ['multiple_choice', 'seleccion_multiple'])): ?>
                            <?php 
                            $opciones = json_decode($pregunta['opciones'], true) ?? [];
                            $respuesta_correcta = $pregunta['respuesta_correcta'];
                            ?>
                            <?php if (!empty($opciones)): ?>
                                <div class="opciones-lista">
                                    <strong>Opciones:</strong>
                                    <?php foreach ($opciones as $key => $opcion): ?>
                                        <div class="opcion-item <?= ($key == $respuesta_correcta) ? 'opcion-correcta' : '' ?>">
                                            <?= chr(65 + $key) ?>. <?= htmlspecialchars($opcion) ?>
                                            <?php if ($key == $respuesta_correcta): ?>
                                                <span style="float: right;">✓ Correcta</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($pregunta['tipo'] == 'verdadero_falso'): ?>
                            <div class="opciones-lista">
                                <strong>Respuesta correcta:</strong>
                                <div class="opcion-item opcion-correcta">
                                    <?= $pregunta['respuesta_correcta'] == '1' ? 'Verdadero' : 'Falso' ?>
                                </div>
                            </div>
                        <?php elseif (in_array($pregunta['tipo'], ['texto_corto', 'texto_largo'])): ?>
                            <?php if (!empty($pregunta['respuesta_correcta'])): ?>
                                <div class="opciones-lista">
                                    <strong>Respuesta esperada:</strong>
                                    <div class="opcion-item opcion-correcta">
                                        <?= nl2br(htmlspecialchars($pregunta['respuesta_correcta'])) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (!empty($pregunta['explicacion'])): ?>
                            <div style="margin-top: 12px; padding: 12px; background: #e8f4fd; border-radius: 6px;">
                                <strong>Explicación:</strong><br>
                                <?= nl2br(htmlspecialchars($pregunta['explicacion'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="pregunta-actions">
                        <button onclick="editarPregunta(<?= $pregunta['id'] ?>)" class="btn-action btn-warning">
                            ✏️ Editar
                        </button>
                        <button onclick="moverPregunta(<?= $pregunta['id'] ?>, 'arriba')" class="btn-action btn-secondary" <?= $index == 0 ? 'disabled' : '' ?>>
                            ↑ Subir
                        </button>
                        <button onclick="moverPregunta(<?= $pregunta['id'] ?>, 'abajo')" class="btn-action btn-secondary" <?= $index == count($preguntas) - 1 ? 'disabled' : '' ?>>
                            ↓ Bajar
                        </button>
                        <button onclick="confirmarEliminarPregunta(<?= $pregunta['id'] ?>, <?= $index + 1 ?>)" class="btn-action btn-danger">
                            🗑️ Eliminar
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nueva/Editar Pregunta -->
<div id="modalPregunta" class="modal">
    <div class="modal-content">
        <h2 id="modalTitulo">Nueva Pregunta</h2>
        
        <form id="formPregunta" action="<?= BASE_URL ?>/docente/procesar_pregunta.php" method="POST" novalidate>
            <input type="hidden" name="evaluacion_id" value="<?= $evaluacion_id ?>">
            <input type="hidden" name="modulo_id" value="<?= $modulo_id ?>">
            <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
            <input type="hidden" name="pregunta_id" id="pregunta_id" value="">
            
            <div class="form-group">
                <label for="pregunta">Pregunta *</label>
                <textarea name="pregunta" id="pregunta" class="form-control" rows="3" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="tipo">Tipo de Pregunta *</label>
                <select name="tipo" id="tipo" class="form-control" required onchange="cambiarTipoPregunta()">
                    <option value="multiple_choice">Opción Múltiple</option>
                    <option value="verdadero_falso">Verdadero/Falso</option>
                    <option value="texto_corto">Texto Corto</option>
                    <option value="texto_largo">Texto Largo</option>
                    <option value="seleccion_multiple">Selección Múltiple</option>
                    <option value="emparejar_columnas">Emparejar Columnas</option>
                    <option value="completar_espacios">Completar Espacios</option>
                </select>
            </div>

            <!-- Opciones para Multiple Choice y Selección Múltiple -->
            <div id="opcionesContainer" class="opciones-container">
                <label>Opciones</label>
                <div id="opcionesList">
                    <!-- Las opciones se generan dinámicamente -->
                </div>
                <button type="button" class="btn-add-opcion" onclick="agregarOpcion()">+ Agregar Opción</button>
            </div>

            <!-- Respuesta para Verdadero/Falso -->
            <div id="verdaderoFalsoContainer" class="opciones-container">
                <label>Respuesta Correcta</label>
                <div style="display: flex; gap: 20px; margin-top: 8px;">
                    <label style="display: flex; align-items: center; gap: 8px;">
                        <input type="radio" name="respuesta_vf" value="1"> Verdadero
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px;">
                        <input type="radio" name="respuesta_vf" value="0"> Falso
                    </label>
                </div>
            </div>

            <!-- Respuesta para Texto -->
            <div id="textoContainer" class="opciones-container">
                <label for="respuesta_texto">Respuesta Esperada (opcional)</label>
                <textarea name="respuesta_texto" id="respuesta_texto" class="form-control" rows="2" 
                          placeholder="Deja vacío para revisión manual"></textarea>
            </div>

            <!-- Emparejar Columnas -->
            <div id="emparejarContainer" class="opciones-container" style="display:none;">
                <label>Relación de Columnas</label>
                <div id="parejasList"></div>
                <button type="button" class="btn-add-opcion" onclick="agregarPareja()">+ Agregar Pareja</button>
                <p style="margin-top:8px;color:#6c757d;">Se guardará como pares izquierda-derecha.</p>
            </div>

            <!-- Completar Espacios -->
            <div id="completarContainer" class="opciones-container" style="display:none;">
                <label>Texto con espacios</label>
                <textarea name="texto_completar" id="texto_completar" class="form-control" rows="3" placeholder="Usa {{blank}} para marcar espacios a completar" oninput="detectarBlancos()"></textarea>
                <div id="blancosList" style="margin-top:10px;"></div>
                <p style="margin-top:8px;color:#6c757d;">Detectamos espacios y generamos campos de respuesta.</p>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label for="puntaje">Puntaje</label>
                    <input type="number" name="puntaje" id="puntaje" class="form-control" value="1" min="0.1" step="0.1" required>
                </div>
                <div class="form-group">
                    <label for="orden">Orden</label>
                    <input type="number" name="orden" id="orden" class="form-control" value="<?= count($preguntas) + 1 ?>" min="1" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="explicacion">Explicación (opcional)</label>
                <textarea name="explicacion" id="explicacion" class="form-control" rows="2" 
                          placeholder="Explicación que se mostrará después de responder"></textarea>
            </div>
            
            <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 30px;">
                <button type="button" onclick="cerrarModal()" class="btn-action btn-secondary">
                    Cancelar
                </button>
                <button type="submit" class="btn-action btn-primary">
                    Guardar Pregunta
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let contadorOpciones = 0;

function mostrarFormularioNuevaPregunta() {
    document.getElementById('modalTitulo').textContent = 'Nueva Pregunta';
    document.getElementById('formPregunta').reset();
    document.getElementById('pregunta_id').value = '';
    document.getElementById('orden').value = <?= count($preguntas) + 1 ?>;
    cambiarTipoPregunta();
    document.getElementById('modalPregunta').style.display = 'flex';
}

function editarPregunta(id) {
    // Aquí cargarías los datos de la pregunta via AJAX
    // Por simplicidad, redirigimos a una página de edición
    window.location.href = `<?= BASE_URL ?>/docente/editar_pregunta.php?id=${id}&evaluacion_id=<?= $evaluacion_id ?>&modulo_id=<?= $modulo_id ?>&curso_id=<?= $curso_id ?>`;
}

function cerrarModal() {
    document.getElementById('modalPregunta').style.display = 'none';
}

function cambiarTipoPregunta() {
    const tipo = document.getElementById('tipo').value;
    const containers = document.querySelectorAll('.opciones-container');
    
    // Ocultar todos los contenedores
    containers.forEach(container => {
        container.style.display = 'none';
        // Deshabilitar inputs dentro de contenedores ocultos para evitar bloqueo por required
        container.querySelectorAll('input, textarea, select').forEach(el => {
            el.disabled = true;
        });
    });
    
    // Mostrar el contenedor apropiado
    if (tipo === 'multiple_choice' || tipo === 'seleccion_multiple') {
        const c = document.getElementById('opcionesContainer');
        c.style.display = 'block';
        c.querySelectorAll('input, textarea, select').forEach(el => { el.disabled = false; });
        inicializarOpciones();
    } else if (tipo === 'verdadero_falso') {
        const c = document.getElementById('verdaderoFalsoContainer');
        c.style.display = 'block';
        c.querySelectorAll('input, textarea, select').forEach(el => { el.disabled = false; });
        inicializarVerdaderoFalso();
    } else if (tipo === 'texto_corto' || tipo === 'texto_largo') {
        const c = document.getElementById('textoContainer');
        c.style.display = 'block';
        c.querySelectorAll('input, textarea, select').forEach(el => { el.disabled = false; });
        inicializarTexto();
    } else if (tipo === 'emparejar_columnas') {
        const c = document.getElementById('emparejarContainer');
        c.style.display = 'block';
        c.querySelectorAll('input, textarea, select').forEach(el => { el.disabled = false; });
        inicializarParejas();
    } else if (tipo === 'completar_espacios') {
        const c = document.getElementById('completarContainer');
        c.style.display = 'block';
        c.querySelectorAll('input, textarea, select').forEach(el => { el.disabled = false; });
        inicializarBlancos();
    }
}

function inicializarOpciones() {
    const lista = document.getElementById('opcionesList');
    lista.innerHTML = '';
    contadorOpciones = 0;
    
    // Agregar 4 opciones por defecto
    for (let i = 0; i < 4; i++) {
        agregarOpcion();
    }
}

function agregarOpcion() {
    const lista = document.getElementById('opcionesList');
    const tipo = document.getElementById('tipo').value;
    const inputType = tipo === 'seleccion_multiple' ? 'checkbox' : 'radio';
    
    const div = document.createElement('div');
    div.className = 'opcion-input';
    div.innerHTML = `
        <span style="min-width: 20px;">${String.fromCharCode(65 + contadorOpciones)}.</span>
        <input type="text" name="opciones[]" placeholder="Escribe la opción..." required>
        <input type="${inputType}" name="respuesta_correcta${tipo === 'seleccion_multiple' ? '[]' : ''}" value="${contadorOpciones}">
        <button type="button" class="btn-remove-opcion" onclick="eliminarOpcion(this)">×</button>
    `;
    
    lista.appendChild(div);
    contadorOpciones++;
}

function eliminarOpcion(btn) {
    const opciones = document.querySelectorAll('.opcion-input');
    if (opciones.length > 2) { // Mantener al menos 2 opciones
        btn.parentElement.remove();
        actualizarLetrasOpciones();
    }
}

// Inicializar Verdadero/Falso por defecto
function inicializarVerdaderoFalso() {
    const verdadero = document.querySelector('input[name="respuesta_vf"][value="1"]');
    if (verdadero) verdadero.checked = true;
}

// Inicializar campos de texto para asegurar presencia
function inicializarTexto() {
    const campo = document.getElementById('respuesta_texto');
    if (campo && campo.value === '') {
        campo.value = '';
    }
}

// Emparejar Columnas
function inicializarParejas() {
    const lista = document.getElementById('parejasList');
    lista.innerHTML = '';
    // Crear dos parejas por defecto
    for (let i = 0; i < 2; i++) agregarPareja();
}

function agregarPareja() {
    const lista = document.getElementById('parejasList');
    const div = document.createElement('div');
    div.className = 'opcion-input';
    div.style.display = 'grid';
    div.style.gridTemplateColumns = '1fr 1fr auto';
    div.style.gap = '10px';
    div.innerHTML = `
        <input type="text" name="col_izquierda[]" placeholder="Columna izquierda" required>
        <input type="text" name="col_derecha[]" placeholder="Columna derecha" required>
        <button type="button" class="btn-remove-opcion" onclick="this.parentElement.remove()">×</button>
    `;
    lista.appendChild(div);
}

// Completar Espacios
function inicializarBlancos() {
    document.getElementById('blancosList').innerHTML = '';
}

function detectarBlancos() {
    const texto = document.getElementById('texto_completar').value;
    const cont = document.getElementById('blancosList');
    cont.innerHTML = '';
    const regex = /\{\{blank\}\}/g;
    const matches = texto.match(regex) || [];
    if (matches.length === 0) return;
    const info = document.createElement('div');
    info.style.marginBottom = '8px';
    info.textContent = `Espacios detectados: ${matches.length}`;
    cont.appendChild(info);
    for (let i = 0; i < matches.length; i++) {
        const div = document.createElement('div');
        div.className = 'opcion-input';
        div.innerHTML = `
            <label>Respuesta ${i + 1}</label>
            <input type="text" name="blancos_respuestas[]" placeholder="Respuesta correcta para el espacio ${i + 1}" required>
        `;
        cont.appendChild(div);
    }
}

function actualizarLetrasOpciones() {
    const opciones = document.querySelectorAll('.opcion-input');
    opciones.forEach((opcion, index) => {
        const letra = opcion.querySelector('span');
        letra.textContent = String.fromCharCode(65 + index) + '.';
        
        const radio = opcion.querySelector('input[type="radio"], input[type="checkbox"]');
        if (radio) {
            radio.value = index;
        }
    });
}

function moverPregunta(id, direccion) {
    if (confirm(`¿Mover la pregunta ${direccion}?`)) {
        fetch(`<?= BASE_URL ?>/docente/mover_pregunta.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                pregunta_id: id,
                direccion: direccion,
                evaluacion_id: <?= $evaluacion_id ?>
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error al mover la pregunta');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al mover la pregunta');
        });
    }
}

function confirmarEliminarPregunta(id, numero) {
    if (confirm(`¿Estás seguro de que deseas eliminar la Pregunta ${numero}?\n\nEsta acción eliminará:\n- La pregunta y sus opciones\n- Todas las respuestas de los estudiantes\n\nEsta acción NO se puede deshacer.`)) {
        window.location.href = `<?= BASE_URL ?>/docente/eliminar_pregunta.php?id=${id}&evaluacion_id=<?= $evaluacion_id ?>&modulo_id=<?= $modulo_id ?>&curso_id=<?= $curso_id ?>`;
    }
}

// Inicializar el formulario
document.addEventListener('DOMContentLoaded', function() {
    cambiarTipoPregunta();
    const form = document.getElementById('formPregunta');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const tipo = document.getElementById('tipo').value;
            const pregunta = document.getElementById('pregunta');
            const puntaje = document.getElementById('puntaje');
            const orden = document.getElementById('orden');
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Guardando...';
            }

            if (!pregunta.value.trim()) {
                alert('La pregunta es obligatoria.');
                pregunta.focus();
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Guardar Pregunta'; }
                return;
            }
            if (!puntaje.value || Number(puntaje.value) <= 0) {
                alert('El puntaje debe ser mayor a 0.');
                puntaje.focus();
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Guardar Pregunta'; }
                return;
            }
            if (!orden.value || Number(orden.value) < 1) {
                alert('El orden debe ser al menos 1.');
                orden.focus();
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Guardar Pregunta'; }
                return;
            }

            if (tipo === 'multiple_choice' || tipo === 'seleccion_multiple') {
                const opciones = document.querySelectorAll('#opcionesList .opcion-input input[type="text"]');
                if (opciones.length < 2) {
                    alert('Agrega al menos 2 opciones.');
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Guardar Pregunta'; }
                    return;
                }
                for (const op of opciones) {
                    if (!op.value.trim()) {
                        alert('Todas las opciones deben tener texto.');
                        op.focus();
                        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Guardar Pregunta'; }
                        return;
                    }
                }
                if (tipo === 'multiple_choice') {
                    const radios = document.querySelectorAll('#opcionesList input[type="radio"]');
                    const alguno = Array.from(radios).some(r => r.checked);
                    if (!alguno) {
                        alert('Selecciona una opción correcta.');
                        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Guardar Pregunta'; }
                        return;
                    }
                }
            } else if (tipo === 'verdadero_falso') {
                const elegido = document.querySelector('input[name="respuesta_vf"]:checked');
                if (!elegido) {
                    alert('Selecciona Verdadero o Falso.');
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Guardar Pregunta'; }
                    return;
                }
            } else if (tipo === 'emparejar_columnas') {
                const izquierdas = document.querySelectorAll('input[name="col_izquierda[]"]');
                const derechas = document.querySelectorAll('input[name="col_derecha[]"]');
                if (izquierdas.length < 1 || derechas.length < 1) {
                    alert('Agrega al menos una pareja.');
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Guardar Pregunta'; }
                    return;
                }
                for (let i = 0; i < izquierdas.length; i++) {
                    if (!izquierdas[i].value.trim() || !derechas[i].value.trim()) {
                        alert('Completa ambas columnas en cada pareja.');
                        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Guardar Pregunta'; }
                        return;
                    }
                }
            } else if (tipo === 'completar_espacios') {
                const texto = document.getElementById('texto_completar').value;
                const blanks = (texto.match(/\{\{blank\}\}/g) || []).length;
                const respuestas = document.querySelectorAll('input[name="blancos_respuestas[]"]');
                if (blanks === 0) {
                    alert('Incluye al menos un {{blank}} en el texto.');
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Guardar Pregunta'; }
                    return;
                }
                if (respuestas.length !== blanks) {
                    alert('Proporciona respuestas para todos los espacios.');
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Guardar Pregunta'; }
                    return;
                }
                for (const r of respuestas) {
                    if (!r.value.trim()) {
                        alert('Completa las respuestas de los espacios.');
                        r.focus();
                        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Guardar Pregunta'; }
                        return;
                    }
                }
            }
            // Envío programático tras validar
            form.submit();
        });
    }
});

// Cerrar modal al hacer clic fuera
document.getElementById('modalPregunta').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModal();
    }
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>