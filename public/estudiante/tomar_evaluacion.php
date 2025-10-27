<?php
// Vista Estudiante – Tomar Evaluación: intento y respuestas

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
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/actividades.css">

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
    background: white;
}

.answer-option:hover {
    border-color: #3498db;
    background: #f8f9fa;
}

.answer-option.selected {
    border-color: #3498db;
    background: #e3f2fd;
}

.answer-option input[type="radio"],
.answer-option input[type="checkbox"] {
    margin-right: 12px;
    transform: scale(1.2);
}

.answer-option label {
    flex: 1;
    cursor: pointer;
    margin: 0;
}

/* Estilos para drag and drop */
.draggable-word {
    background: #3498db;
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    cursor: grab;
    user-select: none;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-block;
}

.draggable-word:hover {
    background: #2980b9;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.draggable-word:active {
    cursor: grabbing;
}

.draggable-word.used {
    background: #95a5a6;
    cursor: not-allowed;
    opacity: 0.6;
}

.drop-zone {
    display: inline-block;
    min-width: 120px;
    min-height: 35px;
    border: 2px dashed #dee2e6;
    border-radius: 6px;
    padding: 6px 12px;
    margin: 0 4px;
    position: relative;
    background: white;
    transition: all 0.3s ease;
    vertical-align: middle;
}

.drop-zone.drag-over {
    border-color: #3498db;
    background: #e3f2fd;
    border-style: solid;
}

.drop-zone.filled {
    border-color: #27ae60;
    background: #d5f4e6;
    border-style: solid;
}

.drop-placeholder {
    color: #adb5bd;
    font-size: 0.85rem;
    font-style: italic;
}

.drop-content {
    background: #27ae60;
    color: white;
    padding: 4px 8px;
    border-radius: 15px;
    font-size: 0.9rem;
    font-weight: 500;
    display: inline-block;
}

.remove-word {
    background: #e74c3c;
    color: white;
    border: none;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 12px;
    cursor: pointer;
    margin-left: 5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.remove-word:hover {
    background: #c0392b;
}

.word-bank {
    transition: all 0.3s ease;
}

.word-bank.drag-active {
    border-color: #3498db;
    background: #f8f9fa;
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
                            <?php 
                            $data = json_decode($pregunta['opciones'], true); 
                            $pairs = $data['pairs'] ?? []; 
                            
                            // Debug: Verificar los datos
                            echo "<!-- DEBUG: Tipo de pregunta: " . $pregunta['tipo'] . " -->";
                            echo "<!-- DEBUG: Opciones raw: " . htmlspecialchars($pregunta['opciones']) . " -->";
                            echo "<!-- DEBUG: Data decoded: " . htmlspecialchars(json_encode($data)) . " -->";
                            echo "<!-- DEBUG: Pairs: " . htmlspecialchars(json_encode($pairs)) . " -->";
                            
                            // Crear array de definiciones con sus índices originales
                            $definiciones_con_indices = [];
                            foreach ($pairs as $idx => $pair) {
                                $definiciones_con_indices[] = [
                                    'texto' => $pair['right'],
                                    'indice_original' => $idx
                                ];
                            }
                            
                            // Mezclar las definiciones manteniendo el índice original
                            shuffle($definiciones_con_indices);
                            
                            echo "<!-- DEBUG: Definiciones con índices: " . htmlspecialchars(json_encode($definiciones_con_indices)) . " -->";
                            ?>
                            <script type="application/json" id="pairs-data-<?= $pregunta['id'] ?>"><?= json_encode($pairs) ?></script>
                            <script type="application/json" id="definiciones-data-<?= $pregunta['id'] ?>"><?= json_encode($definiciones_con_indices) ?></script>
                            <div class="actividad-relacionar-pares" data-pregunta-id="<?= $pregunta['id'] ?>">
                                <div class="instrucciones-relacionar">
                                    <p>Conecta cada concepto de la columna izquierda with su definición correspondiente en la columna derecha haciendo clic en ambos elementos.</p>
                                </div>
                                <div class="contenedor-columnas">
                                    <div class="columna-conceptos">
                                        <h4>Conceptos</h4>
                                        <?php foreach ($pairs as $idx => $pair): ?>
                                            <div class="elemento-concepto" data-id="<?= $idx ?>" data-value="<?= htmlspecialchars($pair['left']) ?>">
                                                <?= htmlspecialchars($pair['left']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="columna-definiciones">
                                        <h4>Definiciones</h4>
                                        <?php foreach ($definiciones_con_indices as $idx => $def_data): ?>
                                            <div class="elemento-definicion" data-id="<?= $idx ?>" data-indice-original="<?= $def_data['indice_original'] ?>" data-value="<?= htmlspecialchars($def_data['texto']) ?>">
                                                <?= htmlspecialchars($def_data['texto']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <svg class="lineas-conexion" width="100%" height="100%">
                                    <!-- Las líneas se dibujarán aquí dinámicamente -->
                                </svg>
                                <input type="hidden" name="respuesta_<?= $pregunta['id'] ?>" value="">
                            </div>
                            
                            <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const actividadId = <?= $pregunta['id'] ?>;
                                console.log('Buscando actividad con ID:', actividadId);
                                
                                const container = document.querySelector(`[data-pregunta-id="${actividadId}"]`);
                                console.log('Container encontrado:', container);
                                
                                if (!container) {
                                    console.error('No se encontró el contenedor para la pregunta', actividadId);
                                    return;
                                }
                                
                                console.log('Todos los atributos del container:', container.attributes);
                                
                                const svg = container.querySelector('.lineas-conexion');
                                const hiddenInput = container.querySelector('input[type="hidden"]');
                                
                                console.log('SVG encontrado:', svg);
                                console.log('SVG rect:', svg ? svg.getBoundingClientRect() : 'SVG no encontrado');
                                console.log('Container rect:', container.getBoundingClientRect());
                                
                                let conexiones = new Map();
                                let elementoSeleccionado = null;
                                
                                console.log('Inicializando actividad relacionar_pares:', actividadId);
                                
                                // Configurar eventos de clic
                                const elementos = container.querySelectorAll('.elemento-concepto, .elemento-definicion');
                                console.log('Elementos encontrados:', elementos.length);
                                
                                elementos.forEach(elemento => {
                                    elemento.addEventListener('click', function(e) {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        console.log('Clic en elemento:', this.textContent.trim());
                                        manejarClick(this);
                                    });
                                });
                                
                                function manejarClick(elemento) {
                                    if (!elementoSeleccionado) {
                                        // Primer elemento seleccionado
                                        elementoSeleccionado = elemento;
                                        elemento.classList.add('seleccionado');
                                        console.log('Elemento seleccionado:', elemento.textContent.trim());
                                    } else if (elementoSeleccionado === elemento) {
                                        // Deseleccionar el mismo elemento
                                        elemento.classList.remove('seleccionado');
                                        elementoSeleccionado = null;
                                        console.log('Elemento deseleccionado');
                                    } else {
                                        // Segundo elemento seleccionado - intentar crear conexión
                                        const primerEsConcepto = elementoSeleccionado.classList.contains('elemento-concepto');
                                        const segundoEsConcepto = elemento.classList.contains('elemento-concepto');
                                        
                                        console.log('Primer elemento es concepto:', primerEsConcepto);
                                        console.log('Segundo elemento es concepto:', segundoEsConcepto);
                                        
                                        // Solo permitir conexiones entre concepto y definición
                                        if (primerEsConcepto !== segundoEsConcepto) {
                                            const concepto = primerEsConcepto ? elementoSeleccionado : elemento;
                                            const definicion = primerEsConcepto ? elemento : elementoSeleccionado;
                                            
                                            crearConexion(concepto, definicion);
                                        } else {
                                            console.log('No se puede conectar elementos del mismo tipo');
                                        }
                                        
                                        // Limpiar selección
                                        elementoSeleccionado.classList.remove('seleccionado');
                                        elemento.classList.remove('seleccionado');
                                        elementoSeleccionado = null;
                                    }
                                }
                                
                                function crearConexion(concepto, definicion) {
                                    const conceptoId = concepto.getAttribute('data-id');
                                    const definicionId = definicion.getAttribute('data-id');
                                    
                                    console.log('Creando conexión:', conceptoId, '->', definicionId);
                                    
                                    // Eliminar conexión existente del concepto si existe
                                    if (conexiones.has(conceptoId)) {
                                        const lineaAnterior = svg.querySelector(`line[data-concepto-id="${conceptoId}"]`);
                                        if (lineaAnterior) {
                                            const defAnteriorId = lineaAnterior.getAttribute('data-definicion-id');
                                            const defAnterior = container.querySelector(`[data-id="${defAnteriorId}"]`);
                                            if (defAnterior) defAnterior.classList.remove('conectado');
                                            lineaAnterior.remove();
                                        }
                                    }
                                    
                                    // Eliminar conexión existente de la definición si existe
                                    const lineaDefinicion = svg.querySelector(`line[data-definicion-id="${definicionId}"]`);
                                    if (lineaDefinicion) {
                                        const conceptoAnteriorId = lineaDefinicion.getAttribute('data-concepto-id');
                                        const conceptoAnterior = container.querySelector(`[data-id="${conceptoAnteriorId}"]`);
                                        if (conceptoAnterior) conceptoAnterior.classList.remove('conectado');
                                        conexiones.delete(conceptoAnteriorId);
                                        lineaDefinicion.remove();
                                    }
                                    
                                    // Crear nueva conexión
                                    conexiones.set(conceptoId, definicionId);
                                    crearLinea(concepto, definicion);
                                    concepto.classList.add('conectado');
                                    definicion.classList.add('conectado');
                                    
                                    actualizarCampoOculto();
                                    
                                    // Llamar a updateProgress después de actualizar el campo
                                    if (typeof updateProgress === 'function') {
                                        updateProgress();
                                    }
                                }
                                
                                function crearLinea(concepto, definicion) {
                                    const containerRect = container.getBoundingClientRect();
                                    const conceptoRect = concepto.getBoundingClientRect();
                                    const definicionRect = definicion.getBoundingClientRect();
                                    
                                    // Calcular coordenadas relativas al contenedor
                                    const x1 = conceptoRect.right - containerRect.left;
                                    const y1 = conceptoRect.top + conceptoRect.height / 2 - containerRect.top;
                                    const x2 = definicionRect.left - containerRect.left;
                                    const y2 = definicionRect.top + definicionRect.height / 2 - containerRect.top;
                                    
                                    console.log('Coordenadas línea:', {x1, y1, x2, y2});
                                    
                                    const linea = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                                    linea.setAttribute('x1', x1);
                                    linea.setAttribute('y1', y1);
                                    linea.setAttribute('x2', x2);
                                    linea.setAttribute('y2', y2);
                                    linea.setAttribute('stroke', '#007bff');
                                    linea.setAttribute('stroke-width', '3');
                                    linea.setAttribute('data-concepto-id', concepto.getAttribute('data-id'));
                                    linea.setAttribute('data-definicion-id', definicion.getAttribute('data-id'));
                                    linea.style.pointerEvents = 'stroke';
                                    linea.style.cursor = 'pointer';
                                    
                                    svg.appendChild(linea);
                                    console.log('Línea creada y agregada al SVG');
                                    
                                    // Agregar evento de clic para eliminar la línea
                                    linea.addEventListener('click', function(e) {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        const conceptoId = linea.getAttribute('data-concepto-id');
                                        const definicionId = linea.getAttribute('data-definicion-id');
                                        
                                        conexiones.delete(conceptoId);
                                        linea.remove();
                                        
                                        const conceptoEl = container.querySelector(`[data-id="${conceptoId}"]`);
                                        const definicionEl = container.querySelector(`[data-id="${definicionId}"]`);
                                        
                                        if (conceptoEl) conceptoEl.classList.remove('conectado');
                                        if (definicionEl) definicionEl.classList.remove('conectado');
                                        
                                        actualizarCampoOculto();
                                        
                                        // Llamar a updateProgress después de actualizar el campo
                                        if (typeof updateProgress === 'function') {
                                            updateProgress();
                                        }
                                    });
                                }
                                
                                function actualizarCampoOculto() {
                                    if (hiddenInput) {
                                        // Crear objeto de respuestas usando índices numéricos
                                        const respuestas = {};
                                        
                                        // Obtener los pares originales y las definiciones con índices desde los script tags
                                        const preguntaId = container.getAttribute('data-pregunta-id');
                                        const pairsScript = document.getElementById(`pairs-data-${preguntaId}`);
                                        const definicionesScript = document.getElementById(`definiciones-data-${preguntaId}`);
                                        
                                        console.log('Script pairs:', pairsScript);
                                        console.log('Script definiciones:', definicionesScript);
                                        
                                        const pairs = pairsScript ? JSON.parse(pairsScript.textContent) : [];
                                        const definiciones = definicionesScript ? JSON.parse(definicionesScript.textContent) : [];
                                        
                                        console.log('Pairs originales:', pairs);
                                        console.log('Definiciones con índices:', definiciones);
                                        console.log('Conexiones Map:', Array.from(conexiones.entries()));
                                        
                                        // Para cada concepto (índice de la izquierda), encontrar qué definición está conectada
                                        pairs.forEach((pair, conceptoIdx) => {
                                            const conceptoId = conceptoIdx.toString();
                                            if (conexiones.has(conceptoId)) {
                                                const definicionIdMezclado = conexiones.get(conceptoId);
                                                // Obtener el índice original de la definición seleccionada
                                                const indiceOriginal = definiciones[parseInt(definicionIdMezclado)].indice_original;
                                                respuestas[conceptoIdx] = indiceOriginal;
                                                console.log(`Concepto ${conceptoIdx} (${pair.left}) → Definición mezclada ${definicionIdMezclado} → Índice original ${indiceOriginal}`);
                                            }
                                        });
                                        
                                        hiddenInput.value = JSON.stringify(respuestas);
                                        console.log('Campo actualizado con índices originales:', respuestas);
                                        
                                        // Llamar a updateProgress
                                        console.log('[DEBUG] Verificando si updateProgress existe:', typeof updateProgress);
                                        if (typeof updateProgress === 'function') {
                                            console.log('[DEBUG] Llamando a updateProgress()...');
                                            updateProgress();
                                            console.log('[DEBUG] updateProgress() ejecutado');
                                        } else {
                                            console.log('[DEBUG] ERROR: updateProgress no es una función');
                                        }
                                    }
                                }
                            });
                            </script>
                            </div>
                        <?php elseif ($pregunta['tipo'] === 'completar_espacios'): ?>
                            <?php 
                            $data = json_decode($pregunta['opciones'], true); 
                            $texto = $data['texto'] ?? ''; 
                            $blancos = $data['blancos'] ?? 0;
                            $respuestas_correctas = json_decode($pregunta['respuesta_correcta'], true) ?? [];
                            $distractores = $data['distractores'] ?? [];
                            
                            // Crear opciones mezcladas para arrastrar (correctas + distractores)
                            $opciones_arrastrar = array_merge($respuestas_correctas, $distractores);
                            shuffle($opciones_arrastrar);
                            ?>
                            
                            <!-- Texto con espacios en blanco -->
                            <div class="completar-texto" style="background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:20px;font-size:1.1rem;line-height:1.8;">
                                <?php
                                // Reemplazar {{blank}} con zonas de drop
                                $texto_con_drops = $texto;
                                for ($i = 0; $i < (int)$blancos; $i++) {
                                    $drop_zone = '<span class="drop-zone" data-blank-index="' . $i . '" ondrop="drop(event)" ondragover="allowDrop(event)" ondragleave="dragLeave(event)">
                                        <input type="hidden" name="respuesta_' . $pregunta['id'] . '[' . $i . ']" class="drop-input">
                                        <span class="drop-placeholder">Arrastra aquí</span>
                                        <span class="drop-content" style="display:none;"></span>
                                        <button type="button" class="remove-word" onclick="removeWord(this)" style="display:none;">×</button>
                                    </span>';
                                    $texto_con_drops = preg_replace('/\{\{blank\}\}/', $drop_zone, $texto_con_drops, 1);
                                }
                                echo $texto_con_drops;
                                ?>
                            </div>
                            
                            <!-- Banco de palabras para arrastrar -->
                            <div class="word-bank" style="background:white;border:2px dashed #dee2e6;border-radius:8px;padding:15px;min-height:80px;">
                                <h5 style="margin:0 0 15px 0;color:#6c757d;font-size:0.9rem;">Banco de palabras (arrastra las palabras a los espacios correspondientes):</h5>
                                <div class="draggable-words" style="display:flex;flex-wrap:wrap;gap:10px;">
                                    <?php foreach ($opciones_arrastrar as $index => $opcion): ?>
                                        <div class="draggable-word" draggable="true" ondragstart="drag(event)" data-word="<?= htmlspecialchars($opcion) ?>">
                                            <?= htmlspecialchars($opcion) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
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
    
    console.log(`[DEBUG] === INICIO updateProgress ===`);
    console.log(`[DEBUG] Total de contenedores encontrados: ${totalQuestions}`);
    containers.forEach(container => {
        const id = container.dataset.preguntaId;
        const tipo = container.dataset.tipo;
        let answered = false;
        
        console.log(`[DEBUG] === PROCESANDO PREGUNTA ${id} ===`);
        console.log(`[DEBUG] Tipo detectado: "${tipo}"`);
        console.log(`[DEBUG] Tipo es relacionar_pares: ${tipo === 'relacionar_pares'}`);
        
        if (tipo === 'multiple_choice' || tipo === 'verdadero_falso') {
            answered = !!container.querySelector(`input[type="radio"][name="respuesta_${id}"]:checked`);
        } else if (tipo === 'seleccion_multiple') {
            answered = container.querySelectorAll(`input[type="checkbox"][name="respuesta_${id}[]"]:checked`).length > 0;
        } else if (tipo === 'texto_corto' || tipo === 'texto_largo') {
            const ta = container.querySelector(`textarea[name="respuesta_${id}"]`);
            answered = !!(ta && ta.value.trim() !== '');
        } else if (tipo === 'emparejar_columnas') {
            // Verificar si la actividad de emparejar columnas tiene respuestas en campo oculto
            const campoRespuesta = document.querySelector(`input[name="respuesta_${id}"]`);
            console.log(`[DEBUG] === VALIDANDO EMPAREJAR_COLUMNAS PREGUNTA ${id} ===`);
            console.log(`[DEBUG] Campo encontrado:`, campoRespuesta);
            console.log(`[DEBUG] Valor del campo:`, campoRespuesta ? campoRespuesta.value : 'null');
            
            if (campoRespuesta && campoRespuesta.value && campoRespuesta.value.trim() !== '') {
                try {
                    const respuestas = JSON.parse(campoRespuesta.value);
                    console.log(`[DEBUG] Respuestas parseadas exitosamente:`, respuestas);
                    
                    // Verificar que no sea un objeto vacío
                    const numRespuestas = Object.keys(respuestas).length;
                    console.log(`[DEBUG] Número de respuestas (Object.keys):`, numRespuestas);
                    
                    if (numRespuestas > 0) {
                        // Obtener el número total de pares esperados desde los datos JSON
                        const scriptPairs = document.getElementById(`pairs-data-${id}`);
                        console.log(`[DEBUG] Script pairs encontrado:`, scriptPairs);
                        if (scriptPairs) {
                            const pairs = JSON.parse(scriptPairs.textContent);
                            console.log(`[DEBUG] Pairs esperados:`, pairs.length, `Respuestas dadas:`, numRespuestas);
                            answered = numRespuestas >= pairs.length;
                            console.log(`[DEBUG] Comparación: ${numRespuestas} >= ${pairs.length} = ${answered}`);
                        } else {
                            console.log(`[DEBUG] No se encontró script pairs para pregunta ${id}, usando fallback`);
                            answered = numRespuestas >= 3; // Fallback para emparejar columnas
                            console.log(`[DEBUG] Fallback: ${numRespuestas} >= 3 = ${answered}`);
                        }
                    } else {
                        answered = false;
                        console.log(`[DEBUG] Pregunta ${id} NO respondida - objeto vacío`);
                    }
                } catch (e) {
                    console.log(`[DEBUG] Error parseando respuestas:`, e);
                    answered = false;
                }
            } else {
                console.log(`[DEBUG] Campo vacío, no encontrado, o valor vacío para pregunta ${id}`);
                answered = false;
            }
            console.log(`[DEBUG] === RESULTADO FINAL EMPAREJAR_COLUMNAS PREGUNTA ${id}: ${answered} ===`);
        } else if (tipo === 'completar_espacios') {
            const inputs = container.querySelectorAll(`input[name^="respuesta_${id}["]`);
            answered = inputs.length > 0 && Array.from(inputs).every(inp => inp.value && inp.value.trim() !== '');
        } else if (tipo === 'relacionar_pares') {
                            // Verificar si la actividad de relacionar columnas tiene respuestas en campo oculto
                            const campoRespuesta = document.querySelector(`input[name="respuesta_${id}"]`);
                            console.log(`[DEBUG] === VALIDANDO RELACIONAR_PARES PREGUNTA ${id} ===`);
                            console.log(`[DEBUG] Campo encontrado:`, campoRespuesta);
                            console.log(`[DEBUG] Valor del campo:`, campoRespuesta ? campoRespuesta.value : 'null');
                            console.log(`[DEBUG] Tipo del campo:`, campoRespuesta ? typeof campoRespuesta.value : 'null');
                            
                            if (campoRespuesta && campoRespuesta.value && campoRespuesta.value.trim() !== '') {
                                try {
                                    const respuestas = JSON.parse(campoRespuesta.value);
                                    console.log(`[DEBUG] Respuestas parseadas exitosamente:`, respuestas);
                                    console.log(`[DEBUG] Tipo de respuestas:`, typeof respuestas);
                                    
                                    // Verificar que no sea un objeto vacío
                                    const numRespuestas = Object.keys(respuestas).length;
                                    console.log(`[DEBUG] Número de respuestas (Object.keys):`, numRespuestas);
                                    console.log(`[DEBUG] Keys de respuestas:`, Object.keys(respuestas));
                                    
                                    if (numRespuestas > 0) {
                                        // Obtener el número total de pares esperados desde los datos JSON
                                        const scriptPairs = document.getElementById(`pairs-data-${id}`);
                                        console.log(`[DEBUG] Script pairs encontrado:`, scriptPairs);
                                        if (scriptPairs) {
                                            const pairs = JSON.parse(scriptPairs.textContent);
                                            console.log(`[DEBUG] Pairs esperados:`, pairs.length, `Respuestas dadas:`, numRespuestas);
                                            answered = numRespuestas >= pairs.length;
                                            console.log(`[DEBUG] Comparación: ${numRespuestas} >= ${pairs.length} = ${answered}`);
                                        } else {
                                            console.log(`[DEBUG] No se encontró script pairs para pregunta ${id}, usando fallback`);
                                            answered = numRespuestas >= 5; // Reducir el fallback para testing
                                            console.log(`[DEBUG] Fallback: ${numRespuestas} >= 5 = ${answered}`);
                                        }
                                    } else {
                                        answered = false;
                                        console.log(`[DEBUG] Pregunta ${id} NO respondida - objeto vacío`);
                                    }
                                } catch (e) {
                                    console.log(`[DEBUG] Error parseando respuestas:`, e);
                                    console.log(`[DEBUG] Valor que causó error:`, campoRespuesta.value);
                                    answered = false;
                                }
                            } else {
                                console.log(`[DEBUG] Campo vacío, no encontrado, o valor vacío para pregunta ${id}`);
                                console.log(`[DEBUG] Campo existe:`, !!campoRespuesta);
                                console.log(`[DEBUG] Campo tiene valor:`, campoRespuesta ? !!campoRespuesta.value : false);
                                console.log(`[DEBUG] Valor no está vacío:`, campoRespuesta && campoRespuesta.value ? campoRespuesta.value.trim() !== '' : false);
                                answered = false;
                            }
                            console.log(`[DEBUG] === RESULTADO FINAL PREGUNTA ${id}: ${answered} ===`);
        }
        
        console.log(`[DEBUG] Pregunta ${id} respondida: ${answered}`);
        if (answered) answeredQuestions++;
    });
    
    console.log(`[DEBUG] === RESUMEN FINAL ===`);
    console.log(`[DEBUG] Preguntas respondidas: ${answeredQuestions}`);
    console.log(`[DEBUG] Total de preguntas: ${totalQuestions}`);
    console.log(`[DEBUG] ¿Todas respondidas? ${answeredQuestions === totalQuestions}`);
    
    const progress = (answeredQuestions / totalQuestions) * 100;
    console.log(`[DEBUG] Progreso calculado: ${answeredQuestions}/${totalQuestions} = ${progress}%`);
    
    const progressFill = document.getElementById('progress-fill');
    if (progressFill) {
        progressFill.style.width = progress + '%';
        console.log(`[DEBUG] Barra de progreso actualizada a ${progress}%`);
    }
    
    const submitBtn = document.getElementById('submit-btn');
    console.log(`[DEBUG] Botón submit encontrado:`, submitBtn);
    console.log(`[DEBUG] Estado actual del botón:`, submitBtn ? (submitBtn.disabled ? 'DESHABILITADO' : 'HABILITADO') : 'NO ENCONTRADO');
    
    const shouldEnable = answeredQuestions === totalQuestions;
    console.log(`[DEBUG] ¿Debería habilitarse? ${shouldEnable} (${answeredQuestions} === ${totalQuestions})`);
    
    if (submitBtn) {
        submitBtn.disabled = !shouldEnable;
        console.log(`[DEBUG] Botón actualizado a:`, submitBtn.disabled ? 'DESHABILITADO' : 'HABILITADO');
    }
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

// Funciones para drag and drop
function drag(ev) {
    ev.dataTransfer.setData("text", ev.target.getAttribute('data-word'));
    ev.target.style.opacity = '0.5';
}

function allowDrop(ev) {
    ev.preventDefault();
    ev.target.closest('.drop-zone').classList.add('drag-over');
}

function dragLeave(ev) {
    ev.target.closest('.drop-zone').classList.remove('drag-over');
}

function drop(ev) {
    ev.preventDefault();
    const dropZone = ev.target.closest('.drop-zone');
    const word = ev.dataTransfer.getData("text");
    
    // Remover clase de drag-over
    dropZone.classList.remove('drag-over');
    
    // Si ya hay una palabra, devolverla al banco
    const currentInput = dropZone.querySelector('.drop-input');
    if (currentInput.value) {
        returnWordToBank(currentInput.value);
    }
    
    // Colocar la nueva palabra
    const placeholder = dropZone.querySelector('.drop-placeholder');
    const content = dropZone.querySelector('.drop-content');
    const removeBtn = dropZone.querySelector('.remove-word');
    
    placeholder.style.display = 'none';
    content.style.display = 'inline-block';
    content.textContent = word;
    removeBtn.style.display = 'inline-flex';
    currentInput.value = word;
    
    dropZone.classList.add('filled');
    
    // Marcar la palabra como usada en el banco
    markWordAsUsed(word);
    
    // Actualizar progreso
    updateProgress();
}

function removeWord(btn) {
    const dropZone = btn.closest('.drop-zone');
    const input = dropZone.querySelector('.drop-input');
    const word = input.value;
    
    // Limpiar la zona de drop
    const placeholder = dropZone.querySelector('.drop-placeholder');
    const content = dropZone.querySelector('.drop-content');
    
    placeholder.style.display = 'inline';
    content.style.display = 'none';
    btn.style.display = 'none';
    input.value = '';
    
    dropZone.classList.remove('filled');
    
    // Devolver la palabra al banco
    returnWordToBank(word);
    
    // Actualizar progreso
    updateProgress();
}

function markWordAsUsed(word) {
    const wordElements = document.querySelectorAll('.draggable-word');
    wordElements.forEach(el => {
        if (el.getAttribute('data-word') === word) {
            el.classList.add('used');
            el.draggable = false;
        }
    });
}

function returnWordToBank(word) {
    const wordElements = document.querySelectorAll('.draggable-word');
    wordElements.forEach(el => {
        if (el.getAttribute('data-word') === word) {
            el.classList.remove('used');
            el.draggable = true;
            el.style.opacity = '1';
        }
    });
}

// FUNCIÓN DUPLICADA ELIMINADA - updateProgress ya está definida arriba con logging completo

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

<script src="<?= BASE_URL ?>/styles/js/actividades.js"></script>
<script>
// Inicializar actividades de relacionar columnas con líneas
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar todas las actividades de relacionar columnas
    const actividadesRelacionar = document.querySelectorAll('.actividad-relacionar-pares');
    console.log('Actividades encontradas:', actividadesRelacionar.length);
    
    actividadesRelacionar.forEach(function(actividad, index) {
        console.log(`Inicializando actividad ${index}:`, actividad);
        
        // Configurar directamente los eventos de clic sin usar el renderizador
        const conceptos = actividad.querySelectorAll('.elemento-concepto');
        const definiciones = actividad.querySelectorAll('.elemento-definicion');
        const svg = actividad.querySelector('.lineas-conexion');
        
        let elementoSeleccionado = null;
        let conexiones = new Map();
        
        // Función para actualizar el campo oculto
        function actualizarCampoOculto() {
            const preguntaId = actividad.getAttribute('data-pregunta-id');
            const campoRespuesta = actividad.querySelector(`input[name="respuesta_${preguntaId}"]`);
            
            if (campoRespuesta) {
                // Obtener los pares originales y las definiciones con índices desde los script tags
                const pairsScript = document.getElementById(`pairs-data-${preguntaId}`);
                const definicionesScript = document.getElementById(`definiciones-data-${preguntaId}`);
                
                const pairs = pairsScript ? JSON.parse(pairsScript.textContent) : [];
                const definiciones = definicionesScript ? JSON.parse(definicionesScript.textContent) : [];
                
                console.log('Pairs originales:', pairs);
                console.log('Definiciones con índices:', definiciones);
                console.log('Conexiones Map:', Array.from(conexiones.entries()));
                
                // Crear objeto de respuestas usando índices originales
                const respuestas = {};
                
                // Para cada concepto (índice de la izquierda), encontrar qué definición está conectada
                pairs.forEach((pair, conceptoIdx) => {
                    const conceptoId = conceptoIdx.toString();
                    if (conexiones.has(conceptoId)) {
                        const definicionIdMezclado = conexiones.get(conceptoId);
                        // Obtener el índice original de la definición seleccionada
                        const indiceOriginal = definiciones[parseInt(definicionIdMezclado)].indice_original;
                        respuestas[conceptoIdx] = indiceOriginal;
                        console.log(`Concepto ${conceptoIdx} (${pair.left}) → Definición mezclada ${definicionIdMezclado} → Índice original ${indiceOriginal}`);
                    }
                });
                
                campoRespuesta.value = JSON.stringify(respuestas);
                console.log('Campo actualizado con índices originales:', respuestas);
                
                // Llamar a updateProgress
                console.log('[DEBUG] Verificando si updateProgress existe:', typeof updateProgress);
                if (typeof updateProgress === 'function') {
                    console.log('[DEBUG] Llamando a updateProgress()...');
                    updateProgress();
                    console.log('[DEBUG] updateProgress() ejecutado');
                } else {
                    console.log('[DEBUG] ERROR: updateProgress no es una función');
                }
            }
        }
        
        // Función para crear una línea entre dos elementos
        function crearLinea(concepto, definicion) {
            const conceptoRect = concepto.getBoundingClientRect();
            const definicionRect = definicion.getBoundingClientRect();
            const svgRect = svg.getBoundingClientRect();
            
            const x1 = conceptoRect.right - svgRect.left;
            const y1 = conceptoRect.top + conceptoRect.height / 2 - svgRect.top;
            const x2 = definicionRect.left - svgRect.left;
            const y2 = definicionRect.top + definicionRect.height / 2 - svgRect.top;
            
            const linea = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            linea.setAttribute('x1', x1);
            linea.setAttribute('y1', y1);
            linea.setAttribute('x2', x2);
            linea.setAttribute('y2', y2);
            linea.setAttribute('stroke', '#007bff');
            linea.setAttribute('stroke-width', '2');
            linea.setAttribute('data-concepto-id', concepto.getAttribute('data-id'));
            linea.setAttribute('data-definicion-id', definicion.getAttribute('data-id'));
            
            svg.appendChild(linea);
            
            // Agregar evento de clic para eliminar la línea
            linea.addEventListener('click', function() {
                const conceptoId = linea.getAttribute('data-concepto-id');
                conexiones.delete(conceptoId);
                linea.remove();
                concepto.classList.remove('conectado');
                definicion.classList.remove('conectado');
                actualizarCampoOculto();
            });
        }
        
        // Agregar eventos de clic a conceptos y definiciones
        [...conceptos, ...definiciones].forEach(elemento => {
            elemento.addEventListener('click', function() {
                if (elementoSeleccionado === null) {
                    // Primer clic - seleccionar elemento
                    elementoSeleccionado = elemento;
                    elemento.classList.add('seleccionado');
                } else if (elementoSeleccionado === elemento) {
                    // Clic en el mismo elemento - deseleccionar
                    elemento.classList.remove('seleccionado');
                    elementoSeleccionado = null;
                } else {
                    // Segundo clic - intentar crear conexión
                    const esConcepto1 = elementoSeleccionado.classList.contains('elemento-concepto');
                    const esConcepto2 = elemento.classList.contains('elemento-concepto');
                    
                    if (esConcepto1 !== esConcepto2) {
                        // Uno es concepto y otro definición - crear conexión
                        const concepto = esConcepto1 ? elementoSeleccionado : elemento;
                        const definicion = esConcepto1 ? elemento : elementoSeleccionado;
                        
                        const conceptoId = concepto.getAttribute('data-id');
                        const definicionId = definicion.getAttribute('data-id');
                        
                        // Eliminar conexión existente del concepto si existe
                        if (conexiones.has(conceptoId)) {
                            const lineaAnterior = svg.querySelector(`line[data-concepto-id="${conceptoId}"]`);
                            if (lineaAnterior) {
                                const defAnteriorId = lineaAnterior.getAttribute('data-definicion-id');
                                const defAnterior = actividad.querySelector(`[data-id="${defAnteriorId}"]`);
                                if (defAnterior) defAnterior.classList.remove('conectado');
                                lineaAnterior.remove();
                            }
                        }
                        
                        // Eliminar conexión existente de la definición si existe
                        const lineaDefinicion = svg.querySelector(`line[data-definicion-id="${definicionId}"]`);
                        if (lineaDefinicion) {
                            const conceptoAnteriorId = lineaDefinicion.getAttribute('data-concepto-id');
                            const conceptoAnterior = actividad.querySelector(`[data-id="${conceptoAnteriorId}"]`);
                            if (conceptoAnterior) conceptoAnterior.classList.remove('conectado');
                            conexiones.delete(conceptoAnteriorId);
                            lineaDefinicion.remove();
                        }
                        
                        // Crear nueva conexión
                        conexiones.set(conceptoId, definicionId);
                        crearLinea(concepto, definicion);
                        concepto.classList.add('conectado');
                        definicion.classList.add('conectado');
                        
                        actualizarCampoOculto();
                    }
                    
                    // Limpiar selección
                    elementoSeleccionado.classList.remove('seleccionado');
                    elemento.classList.remove('seleccionado');
                    elementoSeleccionado = null;
                }
            });
        });
    });
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>