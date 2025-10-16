<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Calificar Evaluación';

$intento_id = (int)($_GET['id'] ?? 0);

if ($intento_id <= 0) {
    header('Location: ' . BASE_URL . '/docente/revisar_evaluaciones.php');
    exit;
}

// Obtener información del intento y verificar acceso del docente
$stmt = $conn->prepare("
    SELECT 
        i.*,
        u.nombre as estudiante_nombre,
        u.email as estudiante_email,
        e.titulo as evaluacion_titulo,
        e.puntaje_maximo,
        e.puntaje_minimo_aprobacion,
        m.titulo as modulo_titulo,
        m.id as modulo_id,
        c.titulo as curso_titulo,
        c.id as curso_id
    FROM intentos_evaluacion i
    INNER JOIN usuarios u ON i.usuario_id = u.id
    INNER JOIN evaluaciones_modulo e ON i.evaluacion_id = e.id
    INNER JOIN modulos m ON e.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE i.id = :intento_id 
    AND i.estado = 'completado' 
    AND i.puntaje_obtenido IS NULL
    AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
");
$stmt->execute([
    ':intento_id' => $intento_id, 
    ':docente_id' => $_SESSION['user_id'],
    ':docente_id2' => $_SESSION['user_id']
]);
$intento = $stmt->fetch();

if (!$intento) {
    header('Location: ' . BASE_URL . '/docente/revisar_evaluaciones.php?error=intento_no_encontrado');
    exit;
}

// Obtener todas las respuestas del intento con las preguntas
$stmt = $conn->prepare("
    SELECT 
        r.*,
        p.pregunta,
        p.tipo,
        p.opciones,
        p.respuesta_correcta,
        p.puntaje
    FROM respuestas_estudiante r
    INNER JOIN preguntas_evaluacion p ON r.pregunta_id = p.id
    WHERE r.intento_id = :intento_id
    ORDER BY p.orden ASC
");
$stmt->execute([':intento_id' => $intento_id]);
$respuestas = $stmt->fetchAll();

require __DIR__ . '/../partials/header.php';
?>

<div class="container">
    <div class="page-header">
        <div class="header-content">
            <h1 class="page-title">
                <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="Calificar" class="page-icon">
                Calificar Evaluación
            </h1>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>/docente/revisar_evaluaciones.php">Revisar Evaluaciones</a> → 
                <?= htmlspecialchars($intento['evaluacion_titulo']) ?>
            </div>
        </div>
    </div>

    <div class="intento-info">
        <div class="info-grid">
            <div class="info-section">
                <h3>Información del Estudiante</h3>
                <div class="estudiante-card">
                    <img src="<?= BASE_URL ?>/styles/iconos/user.png" alt="Estudiante" class="estudiante-avatar">
                    <div class="estudiante-datos">
                        <div class="estudiante-nombre"><?= htmlspecialchars($intento['estudiante_nombre']) ?></div>
                        <div class="estudiante-email"><?= htmlspecialchars($intento['estudiante_email']) ?></div>
                    </div>
                </div>
            </div>
            
            <div class="info-section">
                <h3>Información de la Evaluación</h3>
                <div class="evaluacion-datos">
                    <div class="dato-item">
                        <span class="dato-label">Curso:</span>
                        <span class="dato-valor"><?= htmlspecialchars($intento['curso_titulo']) ?></span>
                    </div>
                    <div class="dato-item">
                        <span class="dato-label">Módulo:</span>
                        <span class="dato-valor"><?= htmlspecialchars($intento['modulo_titulo']) ?></span>
                    </div>
                    <div class="dato-item">
                        <span class="dato-label">Evaluación:</span>
                        <span class="dato-valor"><?= htmlspecialchars($intento['evaluacion_titulo']) ?></span>
                    </div>
                    <div class="dato-item">
                        <span class="dato-label">Intento:</span>
                        <span class="dato-valor">#<?= $intento['numero_intento'] ?></span>
                    </div>
                    <div class="dato-item">
                        <span class="dato-label">Enviado:</span>
                        <span class="dato-valor"><?= date('d/m/Y H:i', strtotime($intento['fecha_fin'])) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="<?= BASE_URL ?>/docente/procesar_calificacion.php" class="calificacion-form">
        <input type="hidden" name="intento_id" value="<?= $intento_id ?>">
        
        <div class="respuestas-container">
            <?php 
            $pregunta_numero = 1;
            $total_puntaje_disponible = 0;
            foreach ($respuestas as $respuesta): 
                $total_puntaje_disponible += $respuesta['puntaje'];
            ?>
                <div class="respuesta-card <?= $respuesta['requiere_revision'] ? 'requiere-revision' : 'automatica' ?>">
                    <div class="pregunta-header">
                        <div class="pregunta-numero">Pregunta <?= $pregunta_numero++ ?></div>
                        <div class="pregunta-tipo"><?= ucfirst($respuesta['tipo']) ?></div>
                        <div class="pregunta-puntaje"><?= $respuesta['puntaje'] ?> pts</div>
                    </div>

                    <div class="pregunta-contenido">
                        <h4><?= htmlspecialchars($respuesta['pregunta']) ?></h4>
                        
                        <?php if ($respuesta['tipo'] === 'opcion_multiple' || $respuesta['tipo'] === 'verdadero_falso'): ?>
                            <?php 
                            $opciones = json_decode($respuesta['opciones'], true) ?: [];
                            ?>
                            <div class="opciones-pregunta">
                                <?php foreach ($opciones as $opcion): ?>
                                    <div class="opcion-item <?= $opcion === $respuesta['respuesta'] ? 'seleccionada' : '' ?>">
                                        <?= htmlspecialchars($opcion) ?>
                                        <?php if ($opcion === $respuesta['respuesta']): ?>
                                            <span class="marca-seleccion">← Respuesta del estudiante</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="respuesta-correcta">
                                <strong>Respuesta correcta:</strong> <?= htmlspecialchars($respuesta['respuesta_correcta']) ?>
                            </div>
                            
                            <div class="resultado-automatico">
                                <?php if ($respuesta['es_correcta']): ?>
                                    <span class="resultado-correcto">✓ Correcta</span>
                                <?php else: ?>
                                    <span class="resultado-incorrecto">✗ Incorrecta</span>
                                <?php endif; ?>
                            </div>
                            
                        <?php else: ?>
                            <div class="respuesta-texto">
                                <div class="respuesta-estudiante">
                                    <h5>Respuesta del estudiante:</h5>
                                    <div class="texto-respuesta"><?= nl2br(htmlspecialchars($respuesta['respuesta'])) ?></div>
                                </div>
                                
                                <?php if (!empty($respuesta['respuesta_correcta'])): ?>
                                    <div class="respuesta-modelo">
                                        <h5>Respuesta modelo/sugerida:</h5>
                                        <div class="texto-modelo"><?= nl2br(htmlspecialchars($respuesta['respuesta_correcta'])) ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="calificacion-manual">
                                    <label for="puntaje_<?= $respuesta['id'] ?>">Puntaje asignado:</label>
                                    <div class="puntaje-input">
                                        <input type="number" 
                                               id="puntaje_<?= $respuesta['id'] ?>" 
                                               name="puntaje[<?= $respuesta['id'] ?>]" 
                                               min="0" 
                                               max="<?= $respuesta['puntaje'] ?>" 
                                               step="0.1" 
                                               class="form-control"
                                               required>
                                        <span class="puntaje-max">/ <?= $respuesta['puntaje'] ?> pts</span>
                                    </div>
                                    
                                    <div class="comentario-docente">
                                        <label for="comentario_<?= $respuesta['id'] ?>">Comentario (opcional):</label>
                                        <textarea id="comentario_<?= $respuesta['id'] ?>" 
                                                  name="comentario[<?= $respuesta['id'] ?>]" 
                                                  class="form-control" 
                                                  rows="2" 
                                                  placeholder="Comentario sobre la respuesta..."></textarea>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="calificacion-resumen">
            <div class="resumen-info">
                <div class="puntaje-info">
                    <span class="label">Puntaje máximo posible:</span>
                    <span class="valor"><?= $total_puntaje_disponible ?> pts</span>
                </div>
                <div class="puntaje-info">
                    <span class="label">Puntaje mínimo para aprobar:</span>
                    <span class="valor"><?= $intento['puntaje_minimo_aprobacion'] ?>%</span>
                </div>
            </div>
            
            <div class="acciones-calificacion">
                <a href="<?= BASE_URL ?>/docente/revisar_evaluaciones.php" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-primary">
                    <img src="<?= BASE_URL ?>/styles/iconos/check.png" alt="Guardar">
                    Guardar Calificación
                </button>
            </div>
        </div>
    </form>
</div>

<style>
.intento-info {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.info-section h3 {
    margin: 0 0 15px 0;
    color: #2c3e50;
    font-size: 1.1rem;
}

.estudiante-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
}

.estudiante-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
}

.estudiante-nombre {
    font-weight: 500;
    color: #2c3e50;
    margin-bottom: 5px;
}

.estudiante-email {
    color: #666;
    font-size: 0.9rem;
}

.evaluacion-datos {
    display: grid;
    gap: 8px;
}

.dato-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.dato-label {
    font-weight: 500;
    color: #666;
}

.dato-valor {
    color: #2c3e50;
}

.respuestas-container {
    display: grid;
    gap: 20px;
    margin-bottom: 30px;
}

.respuesta-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.respuesta-card.requiere-revision {
    border-left: 4px solid #e74c3c;
}

.respuesta-card.automatica {
    border-left: 4px solid #27ae60;
}

.pregunta-header {
    background: #f8f9fa;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #eee;
}

.pregunta-numero {
    font-weight: bold;
    color: #2c3e50;
}

.pregunta-tipo {
    background: #3498db;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
}

.pregunta-puntaje {
    font-weight: bold;
    color: #e74c3c;
}

.pregunta-contenido {
    padding: 20px;
}

.pregunta-contenido h4 {
    margin: 0 0 15px 0;
    color: #2c3e50;
}

.opciones-pregunta {
    margin: 15px 0;
}

.opcion-item {
    padding: 8px 12px;
    margin: 5px 0;
    border-radius: 4px;
    background: #f8f9fa;
}

.opcion-item.seleccionada {
    background: #e3f2fd;
    border: 1px solid #2196f3;
    font-weight: 500;
}

.marca-seleccion {
    color: #2196f3;
    font-weight: bold;
    margin-left: 10px;
}

.respuesta-correcta {
    margin: 15px 0;
    padding: 10px;
    background: #e8f5e8;
    border-radius: 4px;
    color: #2e7d32;
}

.resultado-automatico {
    margin-top: 10px;
}

.resultado-correcto {
    color: #27ae60;
    font-weight: bold;
}

.resultado-incorrecto {
    color: #e74c3c;
    font-weight: bold;
}

.respuesta-texto {
    display: grid;
    gap: 20px;
}

.respuesta-estudiante, .respuesta-modelo {
    padding: 15px;
    border-radius: 6px;
}

.respuesta-estudiante {
    background: #fff3e0;
    border: 1px solid #ffcc80;
}

.respuesta-modelo {
    background: #e8f5e8;
    border: 1px solid #a5d6a7;
}

.respuesta-estudiante h5, .respuesta-modelo h5 {
    margin: 0 0 10px 0;
    font-size: 0.9rem;
    color: #666;
}

.texto-respuesta, .texto-modelo {
    color: #2c3e50;
    line-height: 1.5;
}

.calificacion-manual {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 6px;
    margin-top: 15px;
}

.puntaje-input {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 10px 0;
}

.puntaje-input input {
    width: 100px;
}

.puntaje-max {
    color: #666;
    font-weight: 500;
}

.comentario-docente {
    margin-top: 15px;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 0.9rem;
}

.calificacion-resumen {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.resumen-info {
    display: grid;
    gap: 10px;
}

.puntaje-info {
    display: flex;
    gap: 10px;
}

.puntaje-info .label {
    color: #666;
}

.puntaje-info .valor {
    font-weight: bold;
    color: #2c3e50;
}

.acciones-calificacion {
    display: flex;
    gap: 15px;
}

.btn-primary, .btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-primary {
    background: #27ae60;
    color: white;
}

.btn-primary:hover {
    background: #219a52;
}

.btn-secondary {
    background: #95a5a6;
    color: white;
}

.btn-secondary:hover {
    background: #7f8c8d;
    text-decoration: none;
    color: white;
}

.btn-primary img, .btn-secondary img {
    width: 16px;
    height: 16px;
}

.breadcrumb {
    color: #666;
    font-size: 0.9rem;
    margin-top: 5px;
}

.breadcrumb a {
    color: #3498db;
    text-decoration: none;
}

.breadcrumb a:hover {
    text-decoration: underline;
}

@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .calificacion-resumen {
        flex-direction: column;
        gap: 20px;
        align-items: stretch;
    }
    
    .acciones-calificacion {
        justify-content: center;
    }
}
</style>

<?php require __DIR__ . '/../partials/footer.php'; ?>