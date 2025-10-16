<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/paths.php';

$page_title = 'Docente – Editar Pregunta';

$pregunta_id = (int)($_GET['id'] ?? 0);
$evaluacion_id = (int)($_GET['evaluacion_id'] ?? 0);
$modulo_id = (int)($_GET['modulo_id'] ?? 0);
$curso_id = (int)($_GET['curso_id'] ?? 0);

if ($pregunta_id === 0 || $evaluacion_id === 0) {
    header('Location: ' . BASE_URL . '/docente/preguntas_evaluacion.php?id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=pregunta_invalida');
    exit;
}

// Verificar que la pregunta pertenece a una evaluación del docente y obtener datos
$stmt = $conn->prepare("
    SELECT p.*, e.titulo AS evaluacion_titulo, m.titulo AS modulo_titulo, c.titulo AS curso_titulo
    FROM preguntas_evaluacion p
    INNER JOIN evaluaciones_modulo e ON p.evaluacion_id = e.id
    INNER JOIN modulos m ON e.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE p.id = :pregunta_id AND e.id = :evaluacion_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
");
$stmt->execute([
    ':pregunta_id' => $pregunta_id,
    ':evaluacion_id' => $evaluacion_id,
    ':docente_id' => $_SESSION['user_id'],
    ':docente_id2' => $_SESSION['user_id']
]);
$pregunta = $stmt->fetch();

if (!$pregunta) {
    header('Location: ' . BASE_URL . '/docente/preguntas_evaluacion.php?id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=pregunta_no_encontrada');
    exit;
}

// Decodificar opciones si existen
$opciones = [];
if (!empty($pregunta['opciones'])) {
    $opciones = json_decode($pregunta['opciones'], true) ?? [];
}

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
.tipo-emparejar_columnas { background: #e8f4fd; color: #2980b9; }
.tipo-completar_espacios { background: #fef9e7; color: #f39c12; }

.modal {
    display: block;
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
    margin: 20px auto;
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
    box-sizing: border-box;
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

.opcion-input input[type="radio"],
.opcion-input input[type="checkbox"] {
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

.btn-action {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-primary { background: #3498db; color: white; }
.btn-secondary { background: #95a5a6; color: white; }

.btn-action:hover {
    transform: translateY(-1px);
    opacity: 0.9;
}
</style>

<div class="contenido">
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white;">
        <div class="div-fila-alt-start">
            <div>
                <h1 style="font-size: 2rem; margin-bottom: 10px;">Editar Pregunta</h1>
                <p style="opacity: 0.9;"><?= htmlspecialchars($pregunta['evaluacion_titulo']) ?> - <?= htmlspecialchars($pregunta['modulo_titulo']) ?></p>
            </div>
            <div style="display: flex; gap: 12px;">
                <button onclick="window.location.href='<?= BASE_URL ?>/docente/preguntas_evaluacion.php?id=<?= $evaluacion_id ?>&modulo_id=<?= $modulo_id ?>&curso_id=<?= $curso_id ?>'" 
                        style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    ← Volver a Preguntas
                </button>
            </div>
        </div>
    </div>

    <div class="form-container-body">
        <?php if (isset($_GET['success']) || isset($_GET['error'])): ?>
            <div style="margin-bottom: 20px;">
                <?php if (isset($_GET['success'])): ?>
                    <div style="background: #d4edda; color: #155724; padding: 12px 16px; border-radius: 8px; border: 1px solid #c3e6cb; margin-bottom: 20px;">
                        <strong>✓ Éxito:</strong> 
                        <?php
                        switch($_GET['success']) {
                            case 'pregunta_actualizada':
                                echo 'La pregunta ha sido actualizada exitosamente.';
                                break;
                            default:
                                echo 'Operación completada exitosamente.';
                        }
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div style="background: #f8d7da; color: #721c24; padding: 12px 16px; border-radius: 8px; border: 1px solid #f5c6cb; margin-bottom: 20px;">
                        <strong>✗ Error:</strong> 
                        <?php
                        switch($_GET['error']) {
                            case 'error_procesar':
                                echo 'Ocurrió un error al procesar la pregunta. Por favor, verifica los datos e inténtalo nuevamente.';
                                break;
                            case 'error_espacios':
                                echo 'Error al procesar la pregunta de completar espacios. Verifica que el texto contenga espacios marcados con {{blank}} y que hayas completado las respuestas correctas.';
                                break;
                            case 'datos_incompletos':
                                echo 'Faltan datos requeridos. Para preguntas de completar espacios, asegúrate de incluir el texto y las respuestas correctas.';
                                break;
                            case 'datos_invalidos':
                                echo 'Los datos proporcionados no son válidos.';
                                break;
                            case 'pregunta_no_encontrada':
                                echo 'La pregunta solicitada no fue encontrada.';
                                break;
                            case 'sin_permisos':
                                echo 'No tienes permisos para realizar esta acción.';
                                break;
                            case 'error_servidor':
                                echo 'Error interno del servidor. Por favor, inténtalo nuevamente en unos momentos.';
                                break;
                            default:
                                echo 'Ocurrió un error inesperado. Por favor, inténtalo nuevamente.';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Modal Editar Pregunta -->
        <div id="modalPregunta" class="modal">
            <div class="modal-content">
                <h2 id="modalTitulo">Editar Pregunta</h2>
                
                <form id="formPregunta" action="<?= BASE_URL ?>/docente/actualizar_pregunta.php" method="POST" novalidate>
                    <input type="hidden" name="pregunta_id" value="<?= $pregunta_id ?>">
                    <input type="hidden" name="evaluacion_id" value="<?= $evaluacion_id ?>">
                    <input type="hidden" name="modulo_id" value="<?= $modulo_id ?>">
                    <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
                    
                    <div class="form-group">
                        <label for="pregunta">Pregunta *</label>
                        <textarea name="pregunta" id="pregunta" class="form-control" rows="3" required><?= htmlspecialchars($pregunta['pregunta']) ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="tipo">Tipo de Pregunta *</label>
                        <select name="tipo" id="tipo" class="form-control" required onchange="cambiarTipoPregunta()">
                            <option value="multiple_choice" <?= $pregunta['tipo'] === 'multiple_choice' ? 'selected' : '' ?>>Opción Múltiple</option>
                            <option value="verdadero_falso" <?= $pregunta['tipo'] === 'verdadero_falso' ? 'selected' : '' ?>>Verdadero/Falso</option>
                            <option value="texto_corto" <?= $pregunta['tipo'] === 'texto_corto' ? 'selected' : '' ?>>Texto Corto</option>
                            <option value="texto_largo" <?= $pregunta['tipo'] === 'texto_largo' ? 'selected' : '' ?>>Texto Largo</option>
                            <option value="seleccion_multiple" <?= $pregunta['tipo'] === 'seleccion_multiple' ? 'selected' : '' ?>>Selección Múltiple</option>
                            <option value="emparejar_columnas" <?= $pregunta['tipo'] === 'emparejar_columnas' ? 'selected' : '' ?>>Emparejar Columnas</option>
                            <option value="completar_espacios" <?= $pregunta['tipo'] === 'completar_espacios' ? 'selected' : '' ?>>Completar Espacios</option>
                        </select>
                    </div>

                    <!-- Opciones para Multiple Choice y Selección Múltiple -->
                    <div id="opcionesContainer" class="opciones-container">
                        <label>Opciones</label>
                        <div id="opcionesList">
                            <!-- Las opciones se cargan dinámicamente -->
                        </div>
                        <button type="button" class="btn-add-opcion" onclick="agregarOpcion()">+ Agregar Opción</button>
                    </div>

                    <!-- Respuesta para Verdadero/Falso -->
                    <div id="verdaderoFalsoContainer" class="opciones-container">
                        <label>Respuesta Correcta</label>
                        <div style="display: flex; gap: 20px; margin-top: 8px;">
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input type="radio" name="respuesta_vf" value="1" <?= $pregunta['respuesta_correcta'] === '1' ? 'checked' : '' ?>> Verdadero
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input type="radio" name="respuesta_vf" value="0" <?= $pregunta['respuesta_correcta'] === '0' ? 'checked' : '' ?>> Falso
                            </label>
                        </div>
                    </div>

                    <!-- Respuesta para Texto -->
                    <div id="textoContainer" class="opciones-container">
                        <label for="respuesta_texto">Respuesta Esperada (opcional)</label>
                        <textarea name="respuesta_texto" id="respuesta_texto" class="form-control" rows="2" 
                                  placeholder="Deja vacío para revisión manual"><?= htmlspecialchars($pregunta['respuesta_correcta'] ?? '') ?></textarea>
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
                        
                        <!-- Opciones distractoras -->
                        <div id="distractoresContainer" style="margin-top:15px; display:none;">
                            <label>Opciones adicionales</label>
                            <p style="margin-bottom:10px;color:#6c757d;font-size:0.9em;">Agrega opciones extra para enriquecer el banco de palabras.</p>
                            <div id="distractoresList"></div>
                            <button type="button" class="btn-add-opcion" onclick="agregarDistractor()" style="margin-top:8px;">+ Agregar Opción</button>
                        </div>
                        
                        <p style="margin-top:8px;color:#6c757d;">Detectamos espacios y generamos campos de respuesta.</p>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label for="puntaje">Puntaje</label>
                            <input type="number" name="puntaje" id="puntaje" class="form-control" value="<?= (float)$pregunta['puntaje'] ?>" min="0.1" step="0.1" required>
                        </div>
                        <div class="form-group">
                            <label for="orden">Orden</label>
                            <input type="number" name="orden" id="orden" class="form-control" value="<?= (int)$pregunta['orden'] ?>" min="1" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="explicacion">Explicación (opcional)</label>
                        <textarea name="explicacion" id="explicacion" class="form-control" rows="2" 
                                  placeholder="Explicación que se mostrará después de responder"><?= htmlspecialchars($pregunta['explicacion'] ?? '') ?></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 30px;">
                        <a href="<?= BASE_URL ?>/docente/preguntas_evaluacion.php?id=<?= $evaluacion_id ?>&modulo_id=<?= $modulo_id ?>&curso_id=<?= $curso_id ?>" 
                           class="btn-action btn-secondary">
                            Cancelar
                        </a>
                        <button type="submit" class="btn-action btn-primary">
                            Actualizar Pregunta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let contadorOpciones = 0;
const preguntaData = <?= json_encode($pregunta) ?>;
const opcionesData = <?= json_encode($opciones) ?>;

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
        cargarOpciones();
    } else if (tipo === 'verdadero_falso') {
        const c = document.getElementById('verdaderoFalsoContainer');
        c.style.display = 'block';
        c.querySelectorAll('input, textarea, select').forEach(el => { el.disabled = false; });
    } else if (tipo === 'texto_corto' || tipo === 'texto_largo') {
        const c = document.getElementById('textoContainer');
        c.style.display = 'block';
        c.querySelectorAll('input, textarea, select').forEach(el => { el.disabled = false; });
    } else if (tipo === 'emparejar_columnas') {
        const c = document.getElementById('emparejarContainer');
        c.style.display = 'block';
        c.querySelectorAll('input, textarea, select').forEach(el => { el.disabled = false; });
        cargarParejas();
    } else if (tipo === 'completar_espacios') {
        const c = document.getElementById('completarContainer');
        c.style.display = 'block';
        c.querySelectorAll('input, textarea, select').forEach(el => { el.disabled = false; });
        cargarBlancos();
    }
}

function cargarOpciones() {
    const lista = document.getElementById('opcionesList');
    lista.innerHTML = '';
    contadorOpciones = 0;
    
    if (opcionesData && Object.keys(opcionesData).length > 0) {
        // Cargar opciones existentes
        Object.keys(opcionesData).forEach((key, index) => {
            const div = document.createElement('div');
            div.className = 'opcion-input';
            const tipo = document.getElementById('tipo').value;
            const inputType = tipo === 'seleccion_multiple' ? 'checkbox' : 'radio';
            const isCorrect = preguntaData.respuesta_correcta == key || 
                             (tipo === 'seleccion_multiple' && preguntaData.respuesta_correcta && preguntaData.respuesta_correcta.includes(key));
            
            div.innerHTML = `
                <span style="min-width: 20px;">${String.fromCharCode(65 + index)}.</span>
                <input type="text" name="opciones[]" value="${opcionesData[key]}" placeholder="Escribe la opción..." required>
                <input type="${inputType}" name="respuesta_correcta${tipo === 'seleccion_multiple' ? '[]' : ''}" value="${key}" ${isCorrect ? 'checked' : ''}>
                <button type="button" class="btn-remove-opcion" onclick="eliminarOpcion(this)">×</button>
            `;
            
            lista.appendChild(div);
            contadorOpciones++;
        });
    } else {
        // Crear opciones por defecto si no hay datos
        for (let i = 0; i < 4; i++) {
            agregarOpcion();
        }
    }
}

function cargarParejas() {
    const lista = document.getElementById('parejasList');
    lista.innerHTML = '';
    
    if (opcionesData && opcionesData.pairs) {
        opcionesData.pairs.forEach(pair => {
            const div = document.createElement('div');
            div.className = 'opcion-input';
            div.style.display = 'grid';
            div.style.gridTemplateColumns = '1fr 1fr auto';
            div.style.gap = '10px';
            div.innerHTML = `
                <input type="text" name="col_izquierda[]" value="${pair.left}" placeholder="Columna izquierda" required>
                <input type="text" name="col_derecha[]" value="${pair.right}" placeholder="Columna derecha" required>
                <button type="button" class="btn-remove-opcion" onclick="this.parentElement.remove()">×</button>
            `;
            lista.appendChild(div);
        });
    } else {
        // Crear parejas por defecto
        for (let i = 0; i < 2; i++) agregarPareja();
    }
}

function cargarBlancos() {
    const textoField = document.getElementById('texto_completar');
    const blancosList = document.getElementById('blancosList');
    const distractoresList = document.getElementById('distractoresList');
    
    if (opcionesData && opcionesData.texto) {
        textoField.value = opcionesData.texto;
        detectarBlancos();
        
        // Cargar respuestas existentes
        if (preguntaData.respuesta_correcta) {
            const respuestas = JSON.parse(preguntaData.respuesta_correcta);
            const inputs = blancosList.querySelectorAll('input[name="blancos_respuestas[]"]');
            respuestas.forEach((respuesta, index) => {
                if (inputs[index]) {
                    inputs[index].value = respuesta;
                }
            });
        }
        
        // Cargar distractores existentes
        if (opcionesData.distractores && Array.isArray(opcionesData.distractores)) {
            opcionesData.distractores.forEach(distractor => {
                if (distractor.trim()) {
                    const div = document.createElement('div');
                    div.className = 'opcion-input';
                    div.style.display = 'flex';
                    div.style.gap = '10px';
                    div.style.alignItems = 'center';
                    div.innerHTML = `
                        <input type="text" name="distractores[]" placeholder="Opción adicional" style="flex: 1;" value="${distractor}">
                        <button type="button" class="btn-remove-opcion" onclick="this.parentElement.remove()">×</button>
                    `;
                    distractoresList.appendChild(div);
                }
            });
        }
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

function detectarBlancos() {
    const texto = document.getElementById('texto_completar').value;
    const cont = document.getElementById('blancosList');
    const distractoresContainer = document.getElementById('distractoresContainer');
    
    cont.innerHTML = '';
    const regex = /\{\{blank\}\}/g;
    const matches = texto.match(regex) || [];
    
    if (matches.length === 0) {
        distractoresContainer.style.display = 'none';
        return;
    }
    
    // Mostrar contenedor de distractores cuando hay espacios
    distractoresContainer.style.display = 'block';
    
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

// Nueva función para agregar distractores
function agregarDistractor() {
    const lista = document.getElementById('distractoresList');
    const div = document.createElement('div');
    div.className = 'opcion-input';
    div.style.display = 'flex';
    div.style.gap = '10px';
    div.style.alignItems = 'center';
    div.innerHTML = `
        <input type="text" name="distractores[]" placeholder="Opción adicional" style="flex: 1;">
        <button type="button" class="btn-remove-opcion" onclick="this.parentElement.remove()">×</button>
    `;
    lista.appendChild(div);
}

function actualizarLetrasOpciones() {
    const opciones = document.querySelectorAll('#opcionesList .opcion-input');
    opciones.forEach((opcion, index) => {
        const letra = opcion.querySelector('span');
        letra.textContent = String.fromCharCode(65 + index) + '.';
        
        const radio = opcion.querySelector('input[type="radio"], input[type="checkbox"]');
        if (radio) {
            radio.value = index;
        }
    });
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
                submitBtn.textContent = 'Actualizando...';
            }

            if (!pregunta.value.trim()) {
                alert('La pregunta es obligatoria.');
                pregunta.focus();
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Actualizar Pregunta'; }
                return;
            }
            if (!puntaje.value || Number(puntaje.value) <= 0) {
                alert('El puntaje debe ser mayor a 0.');
                puntaje.focus();
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Actualizar Pregunta'; }
                return;
            }
            if (!orden.value || Number(orden.value) < 1) {
                alert('El orden debe ser al menos 1.');
                orden.focus();
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Actualizar Pregunta'; }
                return;
            }

            if (tipo === 'multiple_choice' || tipo === 'seleccion_multiple') {
                const opciones = document.querySelectorAll('#opcionesList .opcion-input input[type="text"]');
                if (opciones.length < 2) {
                    alert('Agrega al menos 2 opciones.');
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Actualizar Pregunta'; }
                    return;
                }
                for (const op of opciones) {
                    if (!op.value.trim()) {
                        alert('Todas las opciones deben tener texto.');
                        op.focus();
                        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Actualizar Pregunta'; }
                        return;
                    }
                }
                if (tipo === 'multiple_choice') {
                    const radios = document.querySelectorAll('#opcionesList input[type="radio"]');
                    const alguno = Array.from(radios).some(r => r.checked);
                    if (!alguno) {
                        alert('Selecciona una opción correcta.');
                        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Actualizar Pregunta'; }
                        return;
                    }
                }
            } else if (tipo === 'verdadero_falso') {
                const elegido = document.querySelector('input[name="respuesta_vf"]:checked');
                if (!elegido) {
                    alert('Selecciona Verdadero o Falso.');
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Actualizar Pregunta'; }
                    return;
                }
            } else if (tipo === 'emparejar_columnas') {
                const izquierdas = document.querySelectorAll('input[name="col_izquierda[]"]');
                const derechas = document.querySelectorAll('input[name="col_derecha[]"]');
                if (izquierdas.length < 1 || derechas.length < 1) {
                    alert('Agrega al menos una pareja.');
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Actualizar Pregunta'; }
                    return;
                }
                for (let i = 0; i < izquierdas.length; i++) {
                    if (!izquierdas[i].value.trim() || !derechas[i].value.trim()) {
                        alert('Completa ambas columnas en cada pareja.');
                        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Actualizar Pregunta'; }
                        return;
                    }
                }
            } else if (tipo === 'completar_espacios') {
                const texto = document.getElementById('texto_completar').value;
                const blanks = (texto.match(/\{\{blank\}\}/g) || []).length;
                const respuestas = document.querySelectorAll('input[name="blancos_respuestas[]"]');
                if (blanks === 0) {
                    alert('Incluye al menos un {{blank}} en el texto.');
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Actualizar Pregunta'; }
                    return;
                }
                if (respuestas.length !== blanks) {
                    alert('Proporciona respuestas para todos los espacios.');
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Actualizar Pregunta'; }
                    return;
                }
                for (const r of respuestas) {
                    if (!r.value.trim()) {
                        alert('Completa las respuestas de los espacios.');
                        r.focus();
                        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Actualizar Pregunta'; }
                        return;
                    }
                }
            }
            
            // Envío programático tras validar
            form.submit();
        });
    }
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>