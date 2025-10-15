<?php

require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Tomar Evaluación';

$evaluacion_id = (int)($_GET['id'] ?? 0);
$usuario_id = (int)($_SESSION['user_id'] ?? 0);

if ($evaluacion_id <= 0) {
    header('Location: ' . BASE_URL . '/estudiante/dashboard.php');
    exit;
}

// Obtener información de la evaluación
$stmt = $conn->prepare("
    SELECT e.*, m.titulo as modulo_titulo, c.titulo as curso_titulo, c.id as curso_id
    FROM evaluaciones_modulo e
    INNER JOIN modulos m ON e.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE e.id = :evaluacion_id AND e.activo = 1
");
$stmt->execute([':evaluacion_id' => $evaluacion_id]);
$evaluacion = $stmt->fetch();

if (!$evaluacion) {
    header('Location: ' . BASE_URL . '/estudiante/dashboard.php');
    exit;
}

// Verificar que el estudiante esté inscrito en el curso
$stmt = $conn->prepare("
    SELECT id FROM inscripciones 
    WHERE usuario_id = :usuario_id AND curso_id = :curso_id AND estado = 'activo'
");
$stmt->execute([':usuario_id' => $usuario_id, ':curso_id' => $evaluacion['curso_id']]);
if (!$stmt->fetch()) {
    header('Location: ' . BASE_URL . '/estudiante/dashboard.php');
    exit;
}

// Verificar si ya completó la evaluación con 100%
$stmt = $conn->prepare("
    SELECT MAX(puntaje_obtenido) as mejor_puntaje
    FROM intentos_evaluacion
    WHERE usuario_id = :usuario_id AND evaluacion_id = :evaluacion_id
");
$stmt->execute([':usuario_id' => $usuario_id, ':evaluacion_id' => $evaluacion_id]);
$resultado_puntaje = $stmt->fetch();
$ya_completada_100 = $resultado_puntaje && $resultado_puntaje['mejor_puntaje'] >= 100.0;

// Contar intentos previos
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_intentos
    FROM intentos_evaluacion
    WHERE usuario_id = :usuario_id AND evaluacion_id = :evaluacion_id
");
$stmt->execute([':usuario_id' => $usuario_id, ':evaluacion_id' => $evaluacion_id]);
$intentos_realizados = $stmt->fetchColumn();

// Verificar si puede tomar la evaluación
$puede_tomar = !$ya_completada_100 && ($evaluacion['intentos_permitidos'] == 0 || $intentos_realizados < $evaluacion['intentos_permitidos']);

// Obtener preguntas de la evaluación
$stmt = $conn->prepare("
    SELECT * FROM preguntas_evaluacion
    WHERE evaluacion_id = :evaluacion_id
    ORDER BY orden ASC
");
$stmt->execute([':evaluacion_id' => $evaluacion_id]);
$preguntas = $stmt->fetchAll();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/estudiante.css">

<style>
.evaluation-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
}

.evaluation-header {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    text-align: center;
}

.evaluation-title {
    font-size: 2rem;
    margin-bottom: 10px;
}

.evaluation-info {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.info-item {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.info-label {
    font-size: 0.9rem;
    color: #6c757d;
    margin-bottom: 5px;
}

.info-value {
    font-size: 1.2rem;
    font-weight: 600;
    color: #2c3e50;
}

.question-container {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}

.question-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 20px;
}

.question-number {
    background: #3498db;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.question-text {
    font-size: 1.1rem;
    color: #2c3e50;
    margin-bottom: 20px;
    line-height: 1.6;
}

.answer-options {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.answer-option {
    display: flex;
    align-items: center;
    padding: 15px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.answer-option:hover {
    border-color: #3498db;
    background: #f8f9fa;
}

.answer-option.selected {
    border-color: #3498db;
    background: #e3f2fd;
}

.answer-option input[type="radio"] {
    margin-right: 12px;
    transform: scale(1.2);
}

.timer-container {
    position: fixed;
    top: 100px;
    right: 20px;
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    text-align: center;
    min-width: 150px;
}

.timer-display {
    font-size: 1.5rem;
    font-weight: 600;
    color: #e74c3c;
    margin-bottom: 5px;
}

.timer-label {
    font-size: 0.9rem;
    color: #6c757d;
}

.submit-container {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-top: 30px;
    text-align: center;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}

.btn-submit {
    background: #27ae60;
    color: white;
    border: none;
    padding: 15px 40px;
    border-radius: 8px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-submit:hover {
    background: #229954;
    transform: translateY(-2px);
}

.btn-submit:disabled {
    background: #95a5a6;
    cursor: not-allowed;
    transform: none;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 20px;
}

.progress-fill {
    height: 100%;
    background: #3498db;
    transition: width 0.3s ease;
}

@media (max-width: 768px) {
    .timer-container {
        position: static;
        margin-bottom: 20px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="evaluation-container">
    <div class="evaluation-header">
        <h1 class="evaluation-title"><?= htmlspecialchars($evaluacion['titulo']) ?></h1>
        <p><?= htmlspecialchars($evaluacion['modulo_titulo']) ?> - <?= htmlspecialchars($evaluacion['curso_titulo']) ?></p>
    </div>

    <?php if ($ya_completada_100): ?>
        <div class="alert alert-success">
            <h4>✅ Evaluación completada con 100%</h4>
            <p>Ya has completado esta evaluación con un puntaje perfecto de <strong>100%</strong>.</p>
            <a href="<?= BASE_URL ?>/estudiante/curso_contenido.php?id=<?= $evaluacion['curso_id'] ?>" class="btn-submit">Volver al curso</a>
        </div>
    <?php elseif (!$puede_tomar): ?>
        <div class="alert alert-warning">
            <h4>⚠️ No puedes tomar esta evaluación</h4>
            <p>Has agotado el número máximo de intentos permitidos (<?= $evaluacion['intentos_permitidos'] ?>).</p>
            <a href="<?= BASE_URL ?>/estudiante/curso_contenido.php?id=<?= $evaluacion['curso_id'] ?>" class="btn-submit">Volver al curso</a>
        </div>
    <?php elseif (empty($preguntas)): ?>
        <div class="alert alert-warning">
            <h4>⚠️ Evaluación sin preguntas</h4>
            <p>Esta evaluación aún no tiene preguntas configuradas.</p>
            <a href="<?= BASE_URL ?>/estudiante/curso_contenido.php?id=<?= $evaluacion['curso_id'] ?>" class="btn-submit">Volver al curso</a>
        </div>
    <?php else: ?>
        <div class="evaluation-info">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Preguntas</div>
                    <div class="info-value"><?= count($preguntas) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Puntaje Máximo</div>
                    <div class="info-value"><?= $evaluacion['puntaje_maximo'] ?>%</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Puntaje Mínimo</div>
                    <div class="info-value"><?= $evaluacion['puntaje_minimo_aprobacion'] ?>%</div>
                </div>
                <?php if ($evaluacion['tiempo_limite'] > 0): ?>
                <div class="info-item">
                    <div class="info-label">Tiempo Límite</div>
                    <div class="info-value"><?= $evaluacion['tiempo_limite'] ?> min</div>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <div class="info-label">Intentos</div>
                    <div class="info-value"><?= $intentos_realizados + 1 ?>/<?= $evaluacion['intentos_permitidos'] ?: '∞' ?></div>
                </div>
            </div>
            
            <?php if (!empty($evaluacion['instrucciones'])): ?>
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <h5>Instrucciones:</h5>
                    <p><?= nl2br(htmlspecialchars($evaluacion['instrucciones'])) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($evaluacion['tiempo_limite'] > 0): ?>
        <div class="timer-container" id="timer-container">
            <div class="timer-display" id="timer-display"><?= $evaluacion['tiempo_limite'] ?>:00</div>
            <div class="timer-label">Tiempo restante</div>
        </div>
        <?php endif; ?>

        <form id="evaluation-form" method="POST" action="procesar_intento_evaluacion.php">
            <input type="hidden" name="evaluacion_id" value="<?= $evaluacion_id ?>">
            
            <div class="progress-bar">
                <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
            </div>

            <?php foreach ($preguntas as $index => $pregunta): ?>
                <div class="question-container" data-pregunta-id="<?= $pregunta['id'] ?>" data-tipo="<?= $pregunta['tipo'] ?>">
                    <div class="question-header">
                        <div class="question-number"><?= $index + 1 ?></div>
                        <div style="flex: 1; margin-left: 15px;">
                            <div class="question-text"><?= nl2br(htmlspecialchars($pregunta['pregunta'])) ?></div>
                            <?php if (!empty($pregunta['explicacion'])): ?>
                                <div class="question-explanation" style="font-size: 0.85em; color: #6c757d; margin-top: 8px; font-style: italic;">
                                    <?= nl2br(htmlspecialchars($pregunta['explicacion'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="answer-options">
                        <?php if ($pregunta['tipo'] === 'multiple_choice'): ?>
                            <?php
                            $opciones = json_decode($pregunta['opciones'], true);
                            if ($opciones):
                                foreach ($opciones as $key => $opcion):
                            ?>
                                <label class="answer-option" onclick="selectOption(this)">
                                    <input type="radio" name="respuesta_<?= $pregunta['id'] ?>" value="<?= $key ?>" onchange="updateProgress()">
                                    <span><?= htmlspecialchars($opcion) ?></span>
                                </label>
                            <?php 
                                endforeach;
                            endif;
                            ?>
                        <?php elseif ($pregunta['tipo'] === 'seleccion_multiple'): ?>
                            <?php
                            $opciones = json_decode($pregunta['opciones'], true);
                            if ($opciones):
                                foreach ($opciones as $key => $opcion):
                            ?>
                                <label class="answer-option" onclick="selectOption(this)">
                                    <input type="checkbox" name="respuesta_<?= $pregunta['id'] ?>[]" value="<?= $key ?>" onchange="updateProgress()">
                                    <span><?= htmlspecialchars($opcion) ?></span>
                                </label>
                            <?php 
                                endforeach;
                            endif;
                            ?>
                        <?php elseif ($pregunta['tipo'] === 'verdadero_falso'): ?>
                            <label class="answer-option" onclick="selectOption(this)">
                                <input type="radio" name="respuesta_<?= $pregunta['id'] ?>" value="1" onchange="updateProgress()">
                                <span>Verdadero</span>
                            </label>
                            <label class="answer-option" onclick="selectOption(this)">
                                <input type="radio" name="respuesta_<?= $pregunta['id'] ?>" value="0" onchange="updateProgress()">
                                <span>Falso</span>
                            </label>
                        <?php elseif ($pregunta['tipo'] === 'texto_corto' || $pregunta['tipo'] === 'texto_largo'): ?>
                            <textarea name="respuesta_<?= $pregunta['id'] ?>" rows="4" style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-family: inherit;" placeholder="Escribe tu respuesta aquí..." onchange="updateProgress()" oninput="updateProgress()"></textarea>
                        <?php elseif ($pregunta['tipo'] === 'emparejar_columnas'): ?>
                            <?php $data = json_decode($pregunta['opciones'], true); $pairs = $data['pairs'] ?? []; $derecha = array_column($pairs, 'right'); shuffle($derecha); ?>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <div>
                                    <?php foreach ($pairs as $idx => $pair): ?>
                                    <div class="answer-option" style="display:flex;align-items:center;gap:8px;">
                                        <span style="min-width:20px;"><?= $idx + 1 ?>.</span>
                                        <span><?= htmlspecialchars($pair['left']) ?></span>
                                        <select name="respuesta_<?= $pregunta['id'] ?>[<?= $idx ?>]" onchange="updateProgress()" style="margin-left:auto;">
                                            <option value="">Selecciona</option>
                                            <?php foreach ($derecha as $opt): ?>
                                                <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php elseif ($pregunta['tipo'] === 'completar_espacios'): ?>
                            <?php $data = json_decode($pregunta['opciones'], true); $texto = $data['texto'] ?? ''; $blancos = $data['blancos'] ?? 0; ?>
                            <div style="background:#f8f9fa;padding:12px;border-radius:8px;margin-bottom:8px;">
                                <?= nl2br(htmlspecialchars($texto)) ?>
                            </div>
                            <?php for ($i = 0; $i < (int)$blancos; $i++): ?>
                                <div class="answer-option">
                                    <label>Espacio <?= $i + 1 ?></label>
                                    <input type="text" name="respuesta_<?= $pregunta['id'] ?>[<?= $i ?>]" onchange="updateProgress()" oninput="updateProgress()" placeholder="Completa la palabra faltante">
                                </div>
                            <?php endfor; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="submit-container">
                <button type="submit" class="btn-submit" id="submit-btn" disabled>
                    Enviar Evaluación
                </button>
                <p style="margin-top: 15px; color: #6c757d; font-size: 0.9rem;">
                    Asegúrate de revisar todas tus respuestas antes de enviar.
                </p>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
let timeLimit = <?= $evaluacion['tiempo_limite'] ?: 0 ?>;
let timeRemaining = timeLimit * 60; // Convertir a segundos
let timerInterval;

function selectOption(label) {
    const input = label.querySelector('input');
    const container = label.closest('.question-container');
    if (input && input.type === 'checkbox') {
        // Para selección múltiple, alternar solo este label
        label.classList.toggle('selected', input.checked);
        // Actualizar progreso inmediatamente al hacer clic
        updateProgress();
        return;
    }
    // Para radios, marcar solo uno
    container.querySelectorAll('.answer-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    label.classList.add('selected');
    // Actualizar progreso para radios
    updateProgress();
}

function updateProgress() {
    const containers = document.querySelectorAll('.question-container');
    const totalQuestions = containers.length;
    let answeredQuestions = 0;
    containers.forEach(container => {
        const id = container.dataset.preguntaId;
        const tipo = container.dataset.tipo;
        let answered = false;
        if (tipo === 'multiple_choice' || tipo === 'verdadero_falso') {
            answered = !!container.querySelector(`input[type="radio"][name="respuesta_${id}"]:checked`);
        } else if (tipo === 'seleccion_multiple') {
            answered = container.querySelectorAll(`input[type="checkbox"][name="respuesta_${id}[]"]:checked`).length > 0;
        } else if (tipo === 'texto_corto' || tipo === 'texto_largo') {
            const ta = container.querySelector(`textarea[name="respuesta_${id}"]`);
            answered = !!(ta && ta.value.trim() !== '');
        } else if (tipo === 'emparejar_columnas') {
            const selects = container.querySelectorAll(`select[name^="respuesta_${id}["]`);
            answered = selects.length > 0 && Array.from(selects).every(s => s.value && s.value.trim() !== '');
        } else if (tipo === 'completar_espacios') {
            const inputs = container.querySelectorAll(`input[name^="respuesta_${id}["]`);
            answered = inputs.length > 0 && Array.from(inputs).every(inp => inp.value && inp.value.trim() !== '');
        }
        if (answered) answeredQuestions++;
    });
    const progress = (answeredQuestions / totalQuestions) * 100;
    document.getElementById('progress-fill').style.width = progress + '%';
    document.getElementById('submit-btn').disabled = answeredQuestions !== totalQuestions;
}

function startTimer() {
    if (timeLimit <= 0) return;
    
    timerInterval = setInterval(() => {
        timeRemaining--;
        
        const minutes = Math.floor(timeRemaining / 60);
        const seconds = timeRemaining % 60;
        
        document.getElementById('timer-display').textContent = 
            minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
        
        // Cambiar color cuando queda poco tiempo
        if (timeRemaining <= 300) { // 5 minutos
            document.getElementById('timer-display').style.color = '#e74c3c';
        }
        
        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            alert('¡Tiempo agotado! La evaluación se enviará automáticamente.');
            document.getElementById('evaluation-form').submit();
        }
    }, 1000);
}

// Confirmar antes de salir de la página
window.addEventListener('beforeunload', function(e) {
    if (timeLimit > 0 && timeRemaining > 0) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    if (timeLimit > 0) {
        startTimer();
    }
    updateProgress();
});

// Manejar envío del formulario
document.getElementById('evaluation-form').addEventListener('submit', function(e) {
    if (!confirm('¿Estás seguro de que quieres enviar la evaluación? No podrás modificar tus respuestas después.')) {
        e.preventDefault();
    } else {
        clearInterval(timerInterval);
    }
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>