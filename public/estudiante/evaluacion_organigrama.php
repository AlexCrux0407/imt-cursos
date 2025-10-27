<?php
// Vista Estudiante – Evaluación de organigrama

require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Evaluación del Organigrama';

$usuario_id = (int)($_SESSION['user_id'] ?? 0);
$evaluacion_id = $_GET['id'] ?? 0;

if (!$evaluacion_id) {
    header('Location: ' . BASE_URL . '/estudiante/mis_cursos.php');
    exit;
}

try {
    // Obtener información de la evaluación
    $stmt = $conn->prepare("
        SELECT e.*, m.titulo as modulo_titulo, c.titulo as curso_titulo, c.id as curso_id
        FROM evaluaciones_modulo e
        JOIN modulos m ON e.modulo_id = m.id
        JOIN cursos c ON m.curso_id = c.id
        WHERE e.id = :evaluacion_id AND e.activo = 1
    ");
    $stmt->execute([':evaluacion_id' => $evaluacion_id]);
    $evaluacion = $stmt->fetch();

    if (!$evaluacion) {
        throw new Exception('Evaluación no encontrada');
    }

    // Verificar si el usuario está inscrito en el curso
    $stmt = $conn->prepare("
        SELECT * FROM inscripciones 
        WHERE usuario_id = :usuario_id AND curso_id = :curso_id AND estado = 'activo'
    ");
    $stmt->execute([':usuario_id' => $usuario_id, ':curso_id' => $evaluacion['curso_id']]);
    $inscripcion = $stmt->fetch();

    if (!$inscripcion) {
        throw new Exception('No tienes acceso a esta evaluación');
    }

    // Verificar intentos previos
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_intentos,
               MAX(puntaje_obtenido) as mejor_puntaje
        FROM intentos_evaluacion 
        WHERE usuario_id = :usuario_id AND evaluacion_id = :evaluacion_id
    ");
    $stmt->execute([':usuario_id' => $usuario_id, ':evaluacion_id' => $evaluacion_id]);
    $intentos_info = $stmt->fetch();
    $intentos_realizados = $intentos_info['total_intentos'];
    $mejor_puntaje = $intentos_info['mejor_puntaje'] ?? 0;

    // Verificar si ya completó con 100%
    $ya_completada_100 = ($mejor_puntaje >= 100);

    // Verificar si puede tomar la evaluación
    $puede_tomar = ($evaluacion['intentos_permitidos'] == 0 || $intentos_realizados < $evaluacion['intentos_permitidos']);

    // Obtener la pregunta del organigrama
    $stmt = $conn->prepare("
        SELECT * FROM preguntas_evaluacion 
        WHERE evaluacion_id = :evaluacion_id AND tipo = 'organigrama'
        ORDER BY orden ASC 
        LIMIT 1
    ");
    $stmt->execute([':evaluacion_id' => $evaluacion_id]);
    $pregunta_organigrama = $stmt->fetch();
    
    if (!$pregunta_organigrama) {
        throw new Exception('No se encontró la pregunta del organigrama para esta evaluación');
    }

} catch (Exception $e) {
    error_log("Error en evaluacion_organigrama.php: " . $e->getMessage());
    header('Location: ' . BASE_URL . '/estudiante/mis_cursos.php?error=evaluacion_no_encontrada');
    exit;
}

$page_title = 'Evaluación: ' . $evaluacion['titulo'];
require __DIR__ . '/../partials/header.php';
?>

<div class="main-content">
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
        <?php else: ?>
            <div class="evaluation-info">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Tipo</div>
                        <div class="info-value">Organigrama Interactivo</div>
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
                    <div class="instructions-section">
                        <h5 class="instructions-title">📋 Instrucciones:</h5>
                        <p class="instructions-text"><?= nl2br(htmlspecialchars($evaluacion['instrucciones'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>

<style>
.evaluation-container {
    max-width: 1200px;
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

.instructions-section {
    margin-top: 20px;
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 10px;
    border-left: 4px solid #3498db;
}

.instructions-title {
    color: #2c3e50;
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.instructions-text {
    color: #495057;
    line-height: 1.6;
    margin: 0;
    font-size: 0.95rem;
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
    text-decoration: none;
    display: inline-block;
}

.btn-submit:hover {
    background: #229954;
    transform: translateY(-2px);
}
</style>

            <?php if ($evaluacion['tiempo_limite'] > 0): ?>
            <div class="timer-container" id="timer-container">
                <div class="timer-display" id="timer-display"><?= $evaluacion['tiempo_limite'] ?>:00</div>
                <div class="timer-label">Tiempo restante</div>
            </div>
            <?php endif; ?>

            <!-- Contenedor del organigrama -->
            <div class="organigrama-evaluation-container">
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>

            <div class="exercise-area">
                <div class="pieces-bank">
                    <h3>🧩 Banco de Elementos</h3>
                    <div id="piecesContainer">
                        <!-- Las piezas se generarán dinámicamente -->
                    </div>
                </div>

                <div class="organigrama-container">
                    <svg class="connections" id="connections">
                        <!-- Líneas de conexión del organigrama -->
                    </svg>
                    <div class="organigrama" id="organigrama">
                        <!-- Los espacios se generarán dinámicamente -->
                    </div>
                </div>
            </div>

            <div class="controls">
                <button class="btn btn-primary" onclick="verificarRespuestas()">🔍 Verificar Respuestas</button>
                <button class="btn btn-secondary" onclick="reiniciarEjercicio()">🔄 Reiniciar</button>
                <button class="btn btn-success" id="mostrarSolucionBtn" onclick="mostrarSolucion()" style="display: none;">💡 Mostrar Solución</button>
            </div>

            <div id="resultado" class="result" style="display: none;"></div>

            <!-- Formulario oculto para enviar respuestas -->
            <form id="evaluation-form" method="POST" action="procesar_intento_evaluacion.php" style="display: none;">
                <input type="hidden" name="evaluacion_id" value="<?= $evaluacion_id ?>">
                <input type="hidden" name="es_organigrama" value="1">
                <input type="hidden" name="respuesta_<?= $pregunta_organigrama['id'] ?>" id="respuesta_organigrama" value="">
                <!-- Debug: mostrar el ID de la pregunta -->
                <input type="hidden" name="debug_pregunta_id" value="<?= $pregunta_organigrama['id'] ?>">
            </form>
        </div>
    <?php endif; ?>
    </div>
</div>

<style>
.organigrama-evaluation-container {
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    padding: 20px;
    margin: 20px auto;
    max-width: 1400px;
    width: 95%;
    box-sizing: border-box;
}

.exercise-area {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    min-height: 500px;
    width: 100%;
}

.pieces-bank {
    flex: 0 0 260px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 15px;
    padding: 20px;
    max-height: 600px;
    overflow-y: auto;
    color: white;
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.pieces-bank h3 {
    margin-top: 0;
    color: white;
    text-align: center;
    font-size: 1.1em;
    margin-bottom: 15px;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.organigrama-container {
    flex: 1;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border: none;
    border-radius: 20px;
    padding: 15px;
    position: relative;
    min-height: 450px;
    box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow-x: auto;
    overflow-y: hidden;
    max-width: 100%;
}

.organigrama-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: transparent;
    pointer-events: none;
}

.piece {
    background: linear-gradient(135deg, #ffffff, #f8f9fa);
    color: #2c3e50;
    padding: 10px 12px;
    margin: 6px 0;
    border-radius: 10px;
    cursor: grab;
    user-select: none;
    font-weight: 600;
    text-align: center;
    transition: all 0.3s ease;
    border: 2px solid rgba(255, 255, 255, 0.3);
    font-size: 0.8em;
    line-height: 1.2;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(10px);
}

.piece:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    background: linear-gradient(135deg, #ffffff, #e3f2fd);
    border-color: rgba(255, 255, 255, 0.6);
}

.piece:active {
    cursor: grabbing;
    transform: scale(0.98);
}

.piece.dragging {
    opacity: 0.7;
    transform: rotate(3deg) scale(1.05);
    z-index: 1000;
}

.organigrama {
    position: relative;
    width: 1000px;
    height: 450px;
    margin: 0 auto;
    max-width: 100%;
}

.drop-zone {
    position: absolute;
    width: 170px;
    height: 75px;
    border: 2px dashed #cbd5e1;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75em;
    color: #64748b;
    transition: all 0.3s ease;
    text-align: center;
    padding: 6px;
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    line-height: 1.2;
}

.drop-zone.drag-over {
    border-color: #3b82f6;
    background: rgba(59, 130, 246, 0.1);
    border-style: solid;
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.2);
}

.drop-zone.filled {
    border-color: #10b981;
    background: rgba(16, 185, 129, 0.1);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.15);
}

.drop-zone.drop-error {
    background-color: #ffebee !important;
    border-color: #f44336 !important;
    animation: shake 0.5s ease-in-out;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.drop-zone.filled .piece {
    margin: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    cursor: pointer;
    border-radius: 8px;
    font-weight: 600;
}

/* Posiciones específicas del organigrama - Optimizadas para mejor distribución */
.director-general { top: 15px; left: 50%; transform: translateX(-50%); }

/* Segunda fila - Coordinadores principales con más espacio */
.coord-seguridad { top: 110px; left: 20px; }
.coord-infraestructura { top: 110px; left: 200px; }
.coord-transporte { top: 110px; left: 380px; }
.coord-logistica { top: 110px; left: 560px; }
.coord-administracion { top: 110px; left: 740px; }

/* Tercera fila - Divisiones con mejor separación */
.div-desarrollo { top: 210px; left: 20px; }
.div-investigacion { top: 210px; left: 200px; }
.div-laboratorios { top: 210px; left: 380px; }
.div-transito { top: 210px; left: 560px; }
.div-recursos { top: 210px; left: 740px; }

/* Cuarta fila - Unidades específicas con espaciado mejorado */
.unidad-seguridad { top: 310px; left: 30px; }
.unidad-transporte { top: 310px; left: 210px; }
.unidad-sistemas { top: 310px; left: 390px; }
.unidad-laboratorio { top: 310px; left: 570px; }
.unidad-adquisiciones { top: 310px; left: 750px; }

.controls {
    text-align: center;
    margin-top: 30px;
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.controls button {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 25px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    font-size: 0.95em;
    min-width: 120px;
}

.controls button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
}

.controls button:active {
    transform: translateY(0);
}

.controls button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.result {
    margin-top: 20px;
    padding: 20px;
    border-radius: 15px;
    text-align: center;
    font-weight: 600;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.result.success {
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
    color: #155724;
    border: 2px solid #10b981;
}

.result.error {
    background: linear-gradient(135deg, #f8d7da, #f5c6cb);
    color: #721c24;
    border: 2px solid #ef4444;
}

/* Responsive design para pantallas más pequeñas */
@media (max-width: 1400px) {
    .organigrama-evaluation-container {
        max-width: 100%;
        padding: 15px;
    }
    
    .exercise-area {
        flex-direction: column;
        gap: 15px;
    }
    
    .pieces-bank {
        flex: none;
        max-height: 200px;
        overflow-x: auto;
        overflow-y: hidden;
        white-space: nowrap;
    }
    
    .pieces-bank #piecesContainer {
        display: flex;
        gap: 10px;
        padding: 10px 0;
    }
    
    .pieces-bank .piece {
        flex: 0 0 auto;
        margin: 0;
        white-space: normal;
        min-width: 120px;
    }
    
    .organigrama-container {
        min-width: auto;
        overflow-x: auto;
        overflow-y: auto;
    }
    
    .organigrama {
        width: 1000px;
        height: 450px;
    }
    
    .drop-zone {
        width: 140px;
        height: 60px;
        font-size: 0.7em;
    }
}

@media (max-width: 768px) {
    .organigrama {
        width: 900px;
        height: 380px;
    }
    
    .drop-zone {
        width: 130px;
        height: 55px;
        font-size: 0.7em;
    }
    
    /* Ajustar posiciones para pantallas pequeñas */
    .coord-seguridad { left: 40px; }
    .coord-infraestructura { left: 180px; }
    .coord-transporte { left: 320px; }
    .coord-logistica { left: 460px; }
    .coord-administracion { left: 600px; }
    
    .div-desarrollo { left: 40px; }
    .div-investigacion { left: 180px; }
    .div-laboratorios { left: 320px; }
    .div-transito { left: 460px; }
    .div-recursos { left: 600px; }
    
    .unidad-seguridad { left: 40px; }
    .unidad-transporte { left: 180px; }
    .unidad-sistemas { left: 320px; }
    .unidad-laboratorio { left: 460px; }
    .unidad-adquisiciones { left: 600px; }
}

.progress-bar {
    width: 100%;
    height: 25px;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    border-radius: 15px;
    overflow: hidden;
    margin: 20px 0;
    box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.1);
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea, #764ba2);
    width: 0%;
    transition: width 0.5s ease;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
}

.connections {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 1;
}

.connection-line {
    stroke: #bdc3c7;
    stroke-width: 2;
    fill: none;
}

<?php if ($evaluacion['tiempo_limite'] > 0): ?>
.timer-container {
    position: fixed;
    top: 20px;
    right: 20px;
    background: linear-gradient(135deg, #ffffff, #f8fafc);
    border: 2px solid #ef4444;
    border-radius: 20px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(239, 68, 68, 0.2);
    z-index: 1000;
    backdrop-filter: blur(10px);
    min-width: 150px;
}

.timer-display {
    font-size: 2.2em;
    font-weight: 700;
    color: #ef4444;
    text-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);
    margin-bottom: 5px;
}

.timer-label {
    font-size: 0.9em;
    color: #64748b;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
/* Responsividad para pantallas medianas */
@media (max-width: 1200px) {
    .organigrama-evaluation-container {
        max-width: 95%;
        padding: 20px;
    }
    
    .exercise-area {
        flex-direction: column;
        gap: 20px;
    }
    
    .pieces-bank {
        flex: none;
        max-height: 200px;
        overflow-x: auto;
        overflow-y: hidden;
        white-space: nowrap;
    }
    
    .piece {
        display: inline-block;
        margin: 5px;
        white-space: normal;
        vertical-align: top;
        width: 160px;
    }
    
    .organigrama-container {
        min-height: 400px;
    }
    
    .drop-zone {
        width: 140px;
        height: 60px;
        font-size: 0.7em;
    }
    
    /* Ajustar posiciones para pantallas medianas */
    .director-general { top: 10px; left: 50%; transform: translateX(-50%); }
    .coord-seguridad { top: 100px; left: 20px; }
    .coord-infraestructura { top: 100px; left: 180px; }
    .coord-transporte { top: 100px; left: 340px; }
    .coord-logistica { top: 100px; left: 500px; }
    .coord-administracion { top: 100px; left: 660px; }
    
    .div-desarrollo { top: 190px; left: 10px; }
    .div-investigacion { top: 190px; left: 170px; }
    .div-laboratorios { top: 190px; left: 330px; }
    .div-transito { top: 190px; left: 490px; }
    .div-recursos { top: 190px; left: 650px; }
    
    .unidad-seguridad { top: 280px; left: 0px; }
    .unidad-transporte { top: 280px; left: 160px; }
    .unidad-sistemas { top: 280px; left: 320px; }
    .unidad-laboratorio { top: 280px; left: 480px; }
    .unidad-adquisiciones { top: 280px; left: 640px; }
}

/* Responsividad para pantallas pequeñas */
@media (max-width: 768px) {
    .organigrama-evaluation-container {
        padding: 15px;
        margin: 10px;
    }
    
    .pieces-bank {
        padding: 15px;
    }
    
    .piece {
        width: 120px;
        padding: 8px 12px;
        font-size: 0.8em;
    }
    
    .organigrama-container {
        padding: 15px;
        min-height: 350px;
    }
    
    .drop-zone {
        width: 100px;
        height: 50px;
        font-size: 0.6em;
        padding: 3px;
    }
    
    .controls {
        gap: 10px;
    }
    
    .controls button {
        padding: 10px 16px;
        font-size: 0.85em;
        min-width: 100px;
    }
    
    .timer-container {
        top: 10px;
        right: 10px;
        padding: 15px;
        min-width: 120px;
    }
    
    .timer-display {
        font-size: 1.8em;
    }
    
    /* Reorganizar organigrama para móviles */
    .director-general { top: 5px; left: 50%; transform: translateX(-50%); }
    
    .coord-seguridad { top: 70px; left: 10px; }
    .coord-infraestructura { top: 70px; left: 120px; }
    .coord-transporte { top: 130px; left: 10px; }
    .coord-logistica { top: 130px; left: 120px; }
    .coord-administracion { top: 190px; left: 65px; }
    
    .div-desarrollo { top: 250px; left: 10px; }
    .div-investigacion { top: 250px; left: 120px; }
    .div-laboratorios { top: 310px; left: 10px; }
    .div-transito { top: 310px; left: 120px; }
    .div-recursos { top: 370px; left: 65px; }
    
    .unidad-seguridad { top: 430px; left: 10px; }
    .unidad-transporte { top: 430px; left: 120px; }
    .unidad-sistemas { top: 490px; left: 10px; }
    .unidad-laboratorio { top: 490px; left: 120px; }
    .unidad-adquisiciones { top: 550px; left: 65px; }
}
<?php endif; ?>
</style>

<script>
const organigramaConfig = {
    piezas: [
        { id: 'director-general', texto: 'Director General', posicion: 'director-general' },
        { id: 'coord-seguridad', texto: 'Coordinación de Seguridad y Operación del Transporte', posicion: 'coord-seguridad' },
        { id: 'coord-infraestructura', texto: 'Coordinación de Infraestructura del Transporte', posicion: 'coord-infraestructura' },
        { id: 'coord-transporte', texto: 'Coordinación de Transporte Integrado y Logística', posicion: 'coord-transporte' },
        { id: 'coord-logistica', texto: 'Coordinación de Infraestructura de las Tecnologías', posicion: 'coord-logistica' },
        { id: 'coord-administracion', texto: 'Coordinación de Administración y Finanzas', posicion: 'coord-administracion' },
        { id: 'div-desarrollo', texto: 'División de Desarrollo y Diseño de Normas', posicion: 'div-desarrollo' },
        { id: 'div-investigacion', texto: 'División de Investigación y Desarrollo de Tecnologías', posicion: 'div-investigacion' },
        { id: 'div-laboratorios', texto: 'División de Laboratorios de Investigación', posicion: 'div-laboratorios' },
        { id: 'div-transito', texto: 'División de Tránsito Sostenible y Calidad Operativa', posicion: 'div-transito' },
        { id: 'div-recursos', texto: 'División de Recursos Financieros y Materiales', posicion: 'div-recursos' },
        { id: 'unidad-seguridad', texto: 'Unidad de Seguridad', posicion: 'unidad-seguridad' },
        { id: 'unidad-transporte', texto: 'Unidad de Transporte del Transporte', posicion: 'unidad-transporte' },
        { id: 'unidad-sistemas', texto: 'Unidad de Sistemas de Información Georreferenciada', posicion: 'unidad-sistemas' },
        { id: 'unidad-laboratorio', texto: 'Unidad de Laboratorio Nacional en Sistemas Logísticos', posicion: 'unidad-laboratorio' },
        { id: 'unidad-adquisiciones', texto: 'Unidad de Adquisiciones, Recursos Materiales y Servicios', posicion: 'unidad-adquisiciones' }
    ],
    espacios: [
        { id: 'director-general', clase: 'director-general', acepta: ['director-general'] },
        { id: 'coord-seguridad', clase: 'coord-seguridad', acepta: ['coord-seguridad'] },
        { id: 'coord-infraestructura', clase: 'coord-infraestructura', acepta: ['coord-infraestructura'] },
        { id: 'coord-transporte', clase: 'coord-transporte', acepta: ['coord-transporte'] },
        { id: 'coord-logistica', clase: 'coord-logistica', acepta: ['coord-logistica'] },
        { id: 'coord-administracion', clase: 'coord-administracion', acepta: ['coord-administracion'] },
        { id: 'div-desarrollo', clase: 'div-desarrollo', acepta: ['div-desarrollo'] },
        { id: 'div-investigacion', clase: 'div-investigacion', acepta: ['div-investigacion'] },
        { id: 'div-laboratorios', clase: 'div-laboratorios', acepta: ['div-laboratorios'] },
        { id: 'div-transito', clase: 'div-transito', acepta: ['div-transito'] },
        { id: 'div-recursos', clase: 'div-recursos', acepta: ['div-recursos'] },
        { id: 'unidad-seguridad', clase: 'unidad-seguridad', acepta: ['unidad-seguridad'] },
        { id: 'unidad-transporte', clase: 'unidad-transporte', acepta: ['unidad-transporte'] },
        { id: 'unidad-sistemas', clase: 'unidad-sistemas', acepta: ['unidad-sistemas'] },
        { id: 'unidad-laboratorio', clase: 'unidad-laboratorio', acepta: ['unidad-laboratorio'] },
        { id: 'unidad-adquisiciones', clase: 'unidad-adquisiciones', acepta: ['unidad-adquisiciones'] }
    ]
};

let respuestasUsuario = {};
let totalPiezas = organigramaConfig.piezas.length;
let evaluacionEnviada = false;
let solucionMostrada = false;

<?php if ($evaluacion['tiempo_limite'] > 0): ?>
// Timer functionality
let tiempoRestante = <?= $evaluacion['tiempo_limite'] * 60 ?>; // en segundos
let timerInterval;

function iniciarTimer() {
    timerInterval = setInterval(function() {
        tiempoRestante--;
        actualizarDisplayTimer();
        
        if (tiempoRestante <= 0) {
            clearInterval(timerInterval);
            enviarEvaluacionAutomaticamente();
        }
    }, 1000);
}

function actualizarDisplayTimer() {
    const minutos = Math.floor(tiempoRestante / 60);
    const segundos = tiempoRestante % 60;
    const display = document.getElementById('timer-display');
    display.textContent = `${minutos}:${segundos.toString().padStart(2, '0')}`;
    
    if (tiempoRestante <= 300) { // 5 minutos
        display.style.color = '#e74c3c';
    }
}

function enviarEvaluacionAutomaticamente() {
    if (!evaluacionEnviada) {
        alert('⏰ Se ha agotado el tiempo. La evaluación se enviará automáticamente.');
        enviarEvaluacion();
    }
}
<?php endif; ?>

// Inicializar el ejercicio
function inicializarEjercicio() {
    crearPiezas();
    crearEspacios();
    actualizarProgreso();
    <?php if ($evaluacion['tiempo_limite'] > 0): ?>
    iniciarTimer();
    <?php endif; ?>
}

// Crear las piezas arrastrables
function crearPiezas() {
    const container = document.getElementById('piecesContainer');
    
    // Verificar que el elemento existe antes de intentar modificarlo
    if (!container) {
        console.error('Error: No se encontró el elemento piecesContainer');
        return;
    }
    
    container.innerHTML = '';

    // Mezclar las piezas para que aparezcan en orden aleatorio
    const piezasMezcladas = [...organigramaConfig.piezas].sort(() => Math.random() - 0.5);

    piezasMezcladas.forEach(pieza => {
        const elemento = document.createElement('div');
        elemento.className = 'piece';
        elemento.draggable = true;
        elemento.dataset.pieceId = pieza.id;
        elemento.textContent = pieza.texto;

        elemento.addEventListener('dragstart', iniciarArrastre);
        elemento.addEventListener('dragend', finalizarArrastre);

        container.appendChild(elemento);
    });
}

// Crear los espacios de destino
function crearEspacios() {
    const organigrama = document.getElementById('organigrama');
    organigrama.innerHTML = '';

    organigramaConfig.espacios.forEach(espacio => {
        const elemento = document.createElement('div');
        elemento.className = `drop-zone ${espacio.clase}`;
        elemento.dataset.espacioId = espacio.id;
        elemento.dataset.acepta = JSON.stringify(espacio.acepta);
        elemento.textContent = 'Colocar aquí';

        elemento.addEventListener('dragover', permitirSoltar);
        elemento.addEventListener('dragleave', salirZona);
        elemento.addEventListener('drop', soltarPieza);

        organigrama.appendChild(elemento);
    });
}

// Eventos de drag and drop
function iniciarArrastre(e) {
    e.dataTransfer.setData('text/plain', e.target.dataset.pieceId);
    e.target.classList.add('dragging');
}

function finalizarArrastre(e) {
    e.target.classList.remove('dragging');
}

function permitirSoltar(e) {
    e.preventDefault();
    e.target.classList.add('drag-over');
}

function salirZona(e) {
    e.target.classList.remove('drag-over');
}

function soltarPieza(e) {
    e.preventDefault();
    const espacioId = e.target.dataset.espacioId;
    const pieceId = e.dataTransfer.getData('text/plain');
    const acepta = JSON.parse(e.target.dataset.acepta);

    e.target.classList.remove('drag-over');

    // Permitir colocar cualquier pieza en cualquier espacio
    // Remover pieza anterior si existe
    if (respuestasUsuario[espacioId]) {
        const piezaAnteriorId = respuestasUsuario[espacioId];
        devolverPiezaAlBanco(piezaAnteriorId);
    }

    // Colocar nueva pieza
    const pieza = document.querySelector(`[data-piece-id="${pieceId}"]`);
    const piezaClonada = pieza.cloneNode(true);
    piezaClonada.draggable = false;
    piezaClonada.addEventListener('click', () => devolverPiezaAlBanco(pieceId));

    e.target.innerHTML = '';
    e.target.appendChild(piezaClonada);
    e.target.classList.add('filled');

    // Ocultar pieza original
    pieza.style.display = 'none';

    // Guardar respuesta usando el ID de la pieza para evaluar correctamente
    respuestasUsuario[espacioId] = pieceId;

    actualizarProgreso();
    actualizarCampoRespuesta();
}

// Devolver pieza al banco
function devolverPiezaAlBanco(pieceId) {
    const piezaOriginal = document.querySelector(`[data-piece-id="${pieceId}"]`);
    piezaOriginal.style.display = 'block';

    // Remover de espacios ocupados (buscar por ID de pieza)
    Object.keys(respuestasUsuario).forEach(espacioId => {
        if (respuestasUsuario[espacioId] === pieceId) {
            const espacio = document.querySelector(`[data-espacio-id="${espacioId}"]`);
            espacio.innerHTML = 'Colocar aquí';
            espacio.classList.remove('filled');
            delete respuestasUsuario[espacioId];
        }
    });

    actualizarProgreso();
    actualizarCampoRespuesta();
}

// Actualizar barra de progreso
function actualizarProgreso() {
    const completadas = Object.keys(respuestasUsuario).length;
    const porcentaje = (completadas / totalPiezas) * 100;
    document.getElementById('progressFill').style.width = porcentaje + '%';
}

// Actualizar campo oculto con las respuestas
function actualizarCampoRespuesta() {
    const respuestasJSON = JSON.stringify(respuestasUsuario);
    console.log('Actualizando respuestas:', respuestasJSON);
    document.getElementById('respuesta_organigrama').value = respuestasJSON;
}

// Verificar respuestas y enviar automáticamente
function verificarRespuestas() {
    console.log('=== VERIFICAR RESPUESTAS ===');
    console.log('respuestasUsuario:', respuestasUsuario);
    console.log('Número de respuestas:', Object.keys(respuestasUsuario).length);
    
    if (evaluacionEnviada) return;
    
    // Verificar que el campo oculto tenga el valor correcto
    const campoRespuesta = document.getElementById('respuesta_organigrama');
    console.log('Valor del campo oculto antes de actualizar:', campoRespuesta.value);
    
    // Mostrar mensaje de procesamiento
    const resultado = document.getElementById('resultado');
    resultado.className = 'result';
    resultado.innerHTML = '⏳ Procesando respuestas...';
    resultado.style.display = 'block';
    
    // Deshabilitar botón de verificar para evitar múltiples envíos
    document.querySelector('button[onclick="verificarRespuestas()"]').disabled = true;
    
    // Enviar respuestas automáticamente
    evaluacionEnviada = true;
    <?php if ($evaluacion['tiempo_limite'] > 0): ?>
    clearInterval(timerInterval);
    <?php endif; ?>
    
    actualizarCampoRespuesta();
    
    console.log('Valor del campo oculto después de actualizar:', campoRespuesta.value);
    
    // Crear formulario temporal para envío con AJAX
    const formData = new FormData(document.getElementById('evaluation-form'));
    
    console.log('=== DATOS DEL FORMULARIO ===');
    for (let [key, value] of formData.entries()) {
        console.log(key + ':', value);
    }
    
    fetch('procesar_intento_evaluacion.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        return response.text(); // Cambiar a text() primero para debug
    })
    .then(text => {
        console.log('Response text:', text);
        try {
            const data = JSON.parse(text);
            mostrarResultadoFinal(data);
        } catch (e) {
            console.error('Error parsing JSON:', e);
            console.error('Raw response:', text);
            throw new Error('Invalid JSON response');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        resultado.className = 'result error';
        resultado.innerHTML = '❌ Error al procesar la evaluación. Por favor, intenta de nuevo.';
        evaluacionEnviada = false;
        document.querySelector('button[onclick="verificarRespuestas()"]').disabled = false;
    });
}

// Mostrar resultado final después del envío
function mostrarResultadoFinal(data) {
    const resultado = document.getElementById('resultado');
    const mostrarSolucionBtn = document.getElementById('mostrarSolucionBtn');
    
    let correctas = 0;
    let total = organigramaConfig.espacios.length;
    
    // Calcular respuestas correctas
    organigramaConfig.espacios.forEach(espacio => {
        const respuestaUsuario = respuestasUsuario[espacio.id];
        if (respuestaUsuario && espacio.acepta.includes(respuestaUsuario)) {
            correctas++;
        }
    });
    
    const porcentaje = Math.round((correctas / total) * 100);
    
    if (correctas === total) {
        resultado.className = 'result success';
        resultado.innerHTML = `
            <div style="text-align: center;">
                <h3>🎉 ¡Excelente trabajo!</h3>
                <p>Has completado correctamente el organigrama del IMT.</p>
                <div style="font-size: 1.2em; margin: 10px 0;">
                    <strong>Puntuación: ${correctas}/${total} (${porcentaje}%)</strong>
                </div>
                <p style="color: #28a745;">✅ Evaluación guardada exitosamente</p>
            </div>
        `;
    } else {
        resultado.className = 'result error';
        resultado.innerHTML = `
            <div style="text-align: center;">
                <h3>📊 Resultado de tu evaluación</h3>
                <p>Algunas piezas no están en la posición correcta.</p>
                <div style="font-size: 1.2em; margin: 10px 0;">
                    <strong>Puntuación: ${correctas}/${total} (${porcentaje}%)</strong>
                </div>
                <p style="color: #dc3545;">✅ Evaluación guardada exitosamente</p>
                <p style="margin-top: 15px;">Puedes ver la solución correcta para aprender:</p>
            </div>
        `;
        
        // Mostrar el botón de solución solo si no obtuvo el 100%
        mostrarSolucionBtn.style.display = 'inline-block';
    }
    
    resultado.style.display = 'block';
}

// Reiniciar ejercicio (no disponible después de enviar)
function reiniciarEjercicio() {
    if (evaluacionEnviada) {
        alert('⚠️ La evaluación ya ha sido enviada y no se puede reiniciar.');
        return;
    }
    
    respuestasUsuario = {};
    document.getElementById('resultado').style.display = 'none';
    document.getElementById('mostrarSolucionBtn').style.display = 'none';
    document.getElementById('mostrarSolucionBtn').disabled = false;
    document.getElementById('mostrarSolucionBtn').textContent = '💡 Mostrar Solución';
    document.querySelector('button[onclick="verificarRespuestas()"]').disabled = false;
    inicializarEjercicio();
}

// Mostrar solución (solo para aprendizaje, no afecta calificación)
function mostrarSolucion() {
    console.log('=== MOSTRAR SOLUCIÓN ===');
    console.log('organigramaConfig:', organigramaConfig);
    
    // Limpiar el organigrama primero
    document.querySelectorAll('.drop-zone').forEach(espacio => {
        espacio.innerHTML = 'Colocar aquí';
        espacio.classList.remove('filled');
        espacio.style.background = '';
    });
    
    // Mostrar todas las piezas en el banco
    document.querySelectorAll('[data-piece-id]').forEach(pieza => {
        pieza.style.display = 'block';
    });
    
    // Colocar cada pieza en su posición correcta
    organigramaConfig.piezas.forEach(pieza => {
        console.log('Procesando pieza:', pieza);
        
        // Buscar el espacio de destino usando el ID de la posición
        const espacio = document.querySelector(`[data-espacio-id="${pieza.posicion}"]`);
        console.log('Espacio encontrado:', espacio);
        
        // Buscar la pieza original en el banco
        const piezaOriginal = document.querySelector(`[data-piece-id="${pieza.id}"]`);
        console.log('Pieza original encontrada:', piezaOriginal);
        
        if (espacio && piezaOriginal) {
            // Crear una copia de la pieza
            const piezaClonada = piezaOriginal.cloneNode(true);
            piezaClonada.draggable = false;
            piezaClonada.style.opacity = '0.9';
            piezaClonada.style.border = '3px solid #28a745';
            piezaClonada.style.boxShadow = '0 0 10px rgba(40, 167, 69, 0.5)';
            
            // Colocar la pieza en el espacio
            espacio.innerHTML = '';
            espacio.appendChild(piezaClonada);
            espacio.classList.add('filled');
            espacio.style.background = 'rgba(40, 167, 69, 0.1)';
            
            // Ocultar la pieza original del banco
            piezaOriginal.style.display = 'none';
            
            console.log(`Pieza ${pieza.id} colocada en ${pieza.posicion}`);
        } else {
            console.error(`No se pudo colocar la pieza ${pieza.id} en ${pieza.posicion}`);
            console.error('Espacio:', espacio);
            console.error('Pieza original:', piezaOriginal);
        }
    });

    const resultado = document.getElementById('resultado');
    
    // Remover información de solución previa si existe
    const solucionExistente = resultado.querySelector('.solucion-info');
    if (solucionExistente) {
        solucionExistente.remove();
    }
    
    const solucionInfo = document.createElement('div');
    solucionInfo.className = 'solucion-info';
    solucionInfo.style.marginTop = '20px';
    solucionInfo.style.padding = '15px';
    solucionInfo.style.background = 'linear-gradient(135deg, #d4edda, #c3e6cb)';
    solucionInfo.style.border = '2px solid #28a745';
    solucionInfo.style.borderRadius = '8px';
    solucionInfo.style.color = '#155724';
    solucionInfo.innerHTML = `
        <div style="text-align: center;">
            <h4>💡 Solución Correcta</h4>
            <p>Esta es la estructura organizacional correcta del IMT.</p>
            <p><strong>Nota:</strong> Tu calificación ya fue guardada anteriormente.</p>
        </div>
    `;
    
    resultado.appendChild(solucionInfo);
    
    // Deshabilitar botón de mostrar solución
    const botonSolucion = document.getElementById('mostrarSolucionBtn');
    botonSolucion.disabled = true;
    botonSolucion.textContent = '✅ Solución Mostrada';
    
    solucionMostrada = true;
    console.log('=== SOLUCIÓN MOSTRADA ===');
}

// Inicializar cuando se carga la página
document.addEventListener('DOMContentLoaded', inicializarEjercicio);
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>