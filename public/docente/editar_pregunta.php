<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

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
.form-container {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.form-container-header {
    background: linear-gradient(90deg, #e67e22, #d35400);
    color: white;
    padding: 20px;
}

.form-container-body {
    padding: 30px;
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
    border-color: #e67e22;
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
    background: #e67e22;
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

.btn-primary { background: #e67e22; color: white; }
.btn-secondary { background: #95a5a6; color: white; }

.btn-action:hover {
    transform: translateY(-1px);
    opacity: 0.9;
}
</style>

<div class="contenido">
    <div class="form-container">
        <div class="form-container-header">
            <div>
                <h2 style="margin: 0;">Editar Pregunta</h2>
                <p style="opacity: 0.9;"><?= htmlspecialchars($pregunta['evaluacion_titulo']) ?> · <?= htmlspecialchars($pregunta['modulo_titulo']) ?></p>
            </div>
        </div>

        <div class="form-container-body">
            <form action="<?= BASE_URL ?>/docente/actualizar_pregunta.php" method="POST">
                <input type="hidden" name="pregunta_id" value="<?= $pregunta_id ?>">
                <input type="hidden" name="evaluacion_id" value="<?= $evaluacion_id ?>">
                <input type="hidden" name="modulo_id" value="<?= $modulo_id ?>">
                <input type="hidden" name="curso_id" value="<?= $curso_id ?>">

                <div class="form-group">
                    <label for="pregunta">Pregunta *</label>
                    <textarea id="pregunta" name="pregunta" class="form-control" rows="3" required><?= htmlspecialchars($pregunta['pregunta']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="tipo">Tipo de Pregunta *</label>
                    <select id="tipo" name="tipo" class="form-control" required onchange="mostrarOpciones()">
                        <option value="multiple_choice" <?= $pregunta['tipo'] === 'multiple_choice' ? 'selected' : '' ?>>Opción Múltiple</option>
                        <option value="verdadero_falso" <?= $pregunta['tipo'] === 'verdadero_falso' ? 'selected' : '' ?>>Verdadero/Falso</option>
                        <option value="texto_corto" <?= $pregunta['tipo'] === 'texto_corto' ? 'selected' : '' ?>>Texto Corto</option>
                        <option value="texto_largo" <?= $pregunta['tipo'] === 'texto_largo' ? 'selected' : '' ?>>Texto Largo</option>
                    </select>
                </div>

                <!-- Opciones para Múltiple Choice -->
                <div id="multipleContainer" class="opciones-container">
                    <label>Opciones de Respuesta</label>
                    <div id="opcionesList">
                        <?php if ($pregunta['tipo'] === 'multiple_choice' && !empty($opciones)): ?>
                            <?php foreach ($opciones as $index => $opcion): ?>
                                <div class="opcion-input">
                                    <input type="text" name="opciones[]" value="<?= htmlspecialchars($opcion) ?>" placeholder="Opción <?= $index + 1 ?>" required>
                                    <label style="display: flex; align-items: center; gap: 4px; margin: 0;">
                                        <input type="radio" name="respuesta_correcta" value="<?= $index ?>" <?= $pregunta['respuesta_correcta'] == $index ? 'checked' : '' ?>>
                                        Correcta
                                    </label>
                                    <?php if ($index > 1): ?>
                                        <button type="button" class="btn-remove-opcion" onclick="this.parentElement.remove()">×</button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn-add-opcion" onclick="agregarOpcion()">+ Agregar Opción</button>
                </div>

                <!-- Opciones para Verdadero/Falso -->
                <div id="vfContainer" class="opciones-container">
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

<script>
function mostrarOpciones() {
    const tipo = document.getElementById('tipo').value;
    const containers = ['multipleContainer', 'vfContainer', 'textoContainer'];
    
    // Ocultar todos los contenedores
    containers.forEach(id => {
        document.getElementById(id).style.display = 'none';
    });
    
    // Mostrar el contenedor apropiado
    switch (tipo) {
        case 'multiple_choice':
            document.getElementById('multipleContainer').style.display = 'block';
            if (document.getElementById('opcionesList').children.length === 0) {
                inicializarOpciones();
            }
            break;
        case 'verdadero_falso':
            document.getElementById('vfContainer').style.display = 'block';
            break;
        case 'texto_corto':
        case 'texto_largo':
            document.getElementById('textoContainer').style.display = 'block';
            break;
    }
}

function inicializarOpciones() {
    const lista = document.getElementById('opcionesList');
    lista.innerHTML = '';
    // Crear dos opciones por defecto
    for (let i = 0; i < 2; i++) {
        agregarOpcion();
    }
}

function agregarOpcion() {
    const lista = document.getElementById('opcionesList');
    const index = lista.children.length;
    const div = document.createElement('div');
    div.className = 'opcion-input';
    div.innerHTML = `
        <input type="text" name="opciones[]" placeholder="Opción ${index + 1}" required>
        <label style="display: flex; align-items: center; gap: 4px; margin: 0;">
            <input type="radio" name="respuesta_correcta" value="${index}">
            Correcta
        </label>
        ${index > 1 ? '<button type="button" class="btn-remove-opcion" onclick="this.parentElement.remove()">×</button>' : ''}
    `;
    lista.appendChild(div);
}

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    mostrarOpciones();
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>