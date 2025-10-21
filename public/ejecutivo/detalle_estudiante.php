<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('ejecutivo');
require_once __DIR__ . '/../../config/database.php';

$estudiante_id = $_GET['id'] ?? 0;

if (!$estudiante_id) {
    header('Location: ' . BASE_URL . '/ejecutivo/detalles_estudiantes.php');
    exit;
}

// Obtener información del estudiante
$stmt = $conn->prepare("
    SELECT u.*
    FROM usuarios u 
    WHERE u.id = :estudiante_id AND u.role = 'estudiante' AND u.estado = 'activo'
");
$stmt->execute([':estudiante_id' => $estudiante_id]);
$estudiante = $stmt->fetch();

if (!$estudiante) {
    header('Location: ' . BASE_URL . '/ejecutivo/detalles_estudiantes.php');
    exit;
}

$page_title = 'Ejecutivo – ' . $estudiante['nombre'];

// Estadísticas del estudiante
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT i.id) as total_cursos,
        COUNT(DISTINCT CASE WHEN i.progreso = 100 THEN i.id END) as cursos_completados,
        COUNT(DISTINCT CASE WHEN i.progreso > 0 AND i.progreso < 100 THEN i.id END) as cursos_en_progreso,
        COUNT(DISTINCT CASE WHEN i.progreso = 0 THEN i.id END) as cursos_sin_iniciar,
        AVG(COALESCE(i.progreso, 0)) as promedio_progreso,
        COUNT(DISTINCT pm.id) as modulos_completados,
        COUNT(DISTINCT em.id) as evaluaciones_realizadas,
        AVG(CASE WHEN em.calificacion IS NOT NULL THEN em.calificacion ELSE NULL END) as promedio_calificaciones
    FROM usuarios u
    LEFT JOIN inscripciones i ON u.id = i.usuario_id
    LEFT JOIN progreso_modulos pm ON i.id = pm.inscripcion_id AND pm.completado = 1
    LEFT JOIN evaluaciones_modulo em ON u.id = em.usuario_id
    WHERE u.id = :estudiante_id
    GROUP BY u.id
");
$stmt->execute([':estudiante_id' => $estudiante_id]);
$estadisticas = $stmt->fetch();

// Obtener cursos del estudiante con detalles
$stmt = $conn->prepare("
    SELECT i.*, c.titulo, c.descripcion, c.estado as curso_estado,
           u_docente.nombre as docente_nombre,
           COUNT(DISTINCT m.id) as total_modulos,
           COUNT(DISTINCT pm.id) as modulos_completados,
           COUNT(DISTINCT em.id) as evaluaciones_realizadas,
           AVG(CASE WHEN em.calificacion IS NOT NULL THEN em.calificacion ELSE NULL END) as promedio_curso
    FROM inscripciones i
    INNER JOIN cursos c ON i.curso_id = c.id
    LEFT JOIN usuarios u_docente ON c.creado_por = u_docente.id
    LEFT JOIN modulos m ON c.id = m.curso_id
    LEFT JOIN progreso_modulos pm ON i.id = pm.inscripcion_id AND pm.completado = 1
    LEFT JOIN evaluaciones_modulo em ON m.id = em.modulo_id AND em.usuario_id = i.usuario_id
    WHERE i.usuario_id = :estudiante_id
    GROUP BY i.id, c.id
    ORDER BY i.fecha_inscripcion DESC
");
$stmt->execute([':estudiante_id' => $estudiante_id]);
$cursos_estudiante = $stmt->fetchAll();

// Obtener actividad reciente del estudiante
$stmt = $conn->prepare("
    SELECT 'modulo_completado' as tipo, 
           pm.fecha_completado as fecha,
           m.titulo as descripcion,
           c.titulo as curso
    FROM progreso_modulos pm
    INNER JOIN inscripciones i ON pm.inscripcion_id = i.id
    INNER JOIN modulos m ON pm.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE i.usuario_id = :estudiante_id AND pm.completado = 1
    
    UNION ALL
    
    SELECT 'evaluacion_realizada' as tipo,
           em.fecha_evaluacion as fecha,
           CONCAT('Evaluación - ', m.titulo) as descripcion,
           c.titulo as curso
    FROM evaluaciones_modulo em
    INNER JOIN modulos m ON em.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE em.usuario_id = :estudiante_id
    
    UNION ALL
    
    SELECT 'inscripcion' as tipo,
           i.fecha_inscripcion as fecha,
           'Inscripción al curso' as descripcion,
           c.titulo as curso
    FROM inscripciones i
    INNER JOIN cursos c ON i.curso_id = c.id
    WHERE i.usuario_id = :estudiante_id
    
    ORDER BY fecha DESC
    LIMIT 10
");
$stmt->execute([':estudiante_id' => $estudiante_id]);
$actividad_reciente = $stmt->fetchAll();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/ejecutivo.css">

<div class="exec-dashboard">
    <div class="exec-header">
        <div class="header-navigation">
            <a href="<?= BASE_URL ?>/ejecutivo/detalles_estudiantes.php" class="btn-back">
                ← Volver a Estudiantes
            </a>
        </div>
        
        <h1 class="exec-title"><?= htmlspecialchars($estudiante['nombre']) ?></h1>
        <p class="exec-subtitle">Perfil completo y análisis de rendimiento académico</p>
        
        <div class="student-meta-info">
            <div class="meta-item">
                <strong>Email:</strong> <?= htmlspecialchars($estudiante['email']) ?>
            </div>
            <?php if ($estudiante['telefono']): ?>
                <div class="meta-item">
                    <strong>Teléfono:</strong> <?= htmlspecialchars($estudiante['telefono']) ?>
                </div>
            <?php endif; ?>
            <div class="meta-item">
                <strong>Registro:</strong> <?= date('d/m/Y H:i', strtotime($estudiante['created_at'])) ?>
            </div>
            <div class="meta-item">
                <strong>Estado:</strong> 
                <span class="estado-badge estado-<?= $estudiante['estado'] ?>"><?= ucfirst($estudiante['estado']) ?></span>
            </div>
        </div>
        
        <div class="exec-actions">
            <a href="<?= BASE_URL ?>/ejecutivo/generar_reportes.php?tipo=estudiante&id=<?= $estudiante_id ?>" class="btn-export">
                <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="Exportar">
                Generar Reporte
            </a>
        </div>
    </div>

    <!-- Estadísticas del Estudiante -->
    <div class="exec-stats">
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= $estadisticas['total_cursos'] ?></span>
            <span class="exec-stat-description">Cursos Inscritos</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= $estadisticas['cursos_completados'] ?></span>
            <span class="exec-stat-description">Cursos Completados</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= number_format($estadisticas['promedio_progreso'], 1) ?>%</span>
            <span class="exec-stat-description">Progreso Promedio</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= $estadisticas['modulos_completados'] ?></span>
            <span class="exec-stat-description">Módulos Completados</span>
        </div>
    </div>

    <!-- Progreso General -->
    <div class="progress-overview">
        <h3>Distribución de Cursos</h3>
        <div class="progress-distribution">
            <div class="progress-segment completados" style="width: <?= $estadisticas['total_cursos'] > 0 ? ($estadisticas['cursos_completados'] / $estadisticas['total_cursos']) * 100 : 0 ?>%">
                <span><?= $estadisticas['cursos_completados'] ?> Completados</span>
            </div>
            <div class="progress-segment en-progreso" style="width: <?= $estadisticas['total_cursos'] > 0 ? ($estadisticas['cursos_en_progreso'] / $estadisticas['total_cursos']) * 100 : 0 ?>%">
                <span><?= $estadisticas['cursos_en_progreso'] ?> En Progreso</span>
            </div>
            <div class="progress-segment sin-iniciar" style="width: <?= $estadisticas['total_cursos'] > 0 ? ($estadisticas['cursos_sin_iniciar'] / $estadisticas['total_cursos']) * 100 : 0 ?>%">
                <span><?= $estadisticas['cursos_sin_iniciar'] ?> Sin Iniciar</span>
            </div>
        </div>
    </div>

    <!-- Rendimiento Académico -->
    <?php if ($estadisticas['promedio_calificaciones']): ?>
        <div class="academic-performance">
            <h3>Rendimiento Académico</h3>
            <div class="performance-metrics">
                <div class="metric-item">
                    <span class="metric-value"><?= number_format($estadisticas['promedio_calificaciones'], 1) ?></span>
                    <span class="metric-label">Promedio General</span>
                </div>
                <div class="metric-item">
                    <span class="metric-value"><?= $estadisticas['evaluaciones_realizadas'] ?></span>
                    <span class="metric-label">Evaluaciones Realizadas</span>
                </div>
                <div class="metric-grade">
                    <?php 
                    $promedio = $estadisticas['promedio_calificaciones'];
                    $grade_class = '';
                    if ($promedio >= 90) $grade_class = 'excelente';
                    elseif ($promedio >= 80) $grade_class = 'bueno';
                    elseif ($promedio >= 70) $grade_class = 'regular';
                    else $grade_class = 'bajo';
                    ?>
                    <span class="grade-indicator <?= $grade_class ?>">
                        <?php
                        if ($promedio >= 90) echo 'Excelente';
                        elseif ($promedio >= 80) echo 'Bueno';
                        elseif ($promedio >= 70) echo 'Regular';
                        else echo 'Necesita Mejorar';
                        ?>
                    </span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Cursos del Estudiante -->
    <div class="courses-section">
        <h3>Cursos Inscritos (<?= count($cursos_estudiante) ?>)</h3>
        
        <?php if (empty($cursos_estudiante)): ?>
            <div class="no-results">
                <p>El estudiante no está inscrito en ningún curso.</p>
            </div>
        <?php else: ?>
            <div class="courses-grid">
                <?php foreach ($cursos_estudiante as $curso): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <h4><?= htmlspecialchars($curso['titulo']) ?></h4>
                            <span class="course-status status-<?= $curso['progreso'] == 100 ? 'completado' : ($curso['progreso'] > 0 ? 'en-progreso' : 'sin-iniciar') ?>">
                                <?php
                                if ($curso['progreso'] == 100) echo 'Completado';
                                elseif ($curso['progreso'] > 0) echo 'En Progreso';
                                else echo 'Sin Iniciar';
                                ?>
                            </span>
                        </div>
                        
                        <div class="course-info">
                            <p class="course-description"><?= htmlspecialchars(substr($curso['descripcion'], 0, 100)) ?>...</p>
                            <p class="course-teacher">
                                <strong>Docente:</strong> <?= htmlspecialchars($curso['docente_nombre']) ?>
                            </p>
                        </div>
                        
                        <div class="course-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $curso['progreso'] ?>%"></div>
                            </div>
                            <span class="progress-text"><?= number_format($curso['progreso'], 1) ?>% completado</span>
                        </div>
                        
                        <div class="course-stats">
                            <div class="stat-row">
                                <span class="stat-label">Módulos:</span>
                                <span class="stat-value"><?= $curso['modulos_completados'] ?>/<?= $curso['total_modulos'] ?></span>
                            </div>
                            <div class="stat-row">
                                <span class="stat-label">Evaluaciones:</span>
                                <span class="stat-value"><?= $curso['evaluaciones_realizadas'] ?></span>
                            </div>
                            <?php if ($curso['promedio_curso']): ?>
                                <div class="stat-row">
                                    <span class="stat-label">Promedio:</span>
                                    <span class="stat-value grade"><?= number_format($curso['promedio_curso'], 1) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="course-dates">
                            <div class="date-item">
                                <span class="date-label">Inscripción:</span>
                                <span class="date-value"><?= date('d/m/Y', strtotime($curso['fecha_inscripcion'])) ?></span>
                            </div>
                            <?php if ($curso['fecha_completado']): ?>
                                <div class="date-item">
                                    <span class="date-label">Completado:</span>
                                    <span class="date-value"><?= date('d/m/Y', strtotime($curso['fecha_completado'])) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Actividad Reciente -->
    <?php if (!empty($actividad_reciente)): ?>
        <div class="activity-section">
            <h3>Actividad Reciente</h3>
            <div class="activity-timeline">
                <?php foreach ($actividad_reciente as $actividad): ?>
                    <div class="activity-item">
                        <div class="activity-icon <?= $actividad['tipo'] ?>">
                            <?php
                            switch ($actividad['tipo']) {
                                case 'modulo_completado':
                                    echo '<img src="' . BASE_URL . '/styles/iconos/desk.png" alt="Módulo">';
                                    break;
                                case 'evaluacion_realizada':
                                    echo '<img src="' . BASE_URL . '/styles/iconos/edit.png" alt="Evaluación">';
                                    break;
                                case 'inscripcion':
                                    echo '<img src="' . BASE_URL . '/styles/iconos/addicon.png" alt="Inscripción">';
                                    break;
                            }
                            ?>
                        </div>
                        <div class="activity-content">
                            <div class="activity-description">
                                <strong><?= htmlspecialchars($actividad['descripcion']) ?></strong>
                            </div>
                            <div class="activity-course"><?= htmlspecialchars($actividad['curso']) ?></div>
                            <div class="activity-date"><?= date('d/m/Y H:i', strtotime($actividad['fecha'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>