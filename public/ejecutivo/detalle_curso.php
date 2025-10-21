<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('ejecutivo');
require_once __DIR__ . '/../../config/database.php';

$curso_id = $_GET['id'] ?? 0;

if (!$curso_id) {
    header('Location: ' . BASE_URL . '/ejecutivo/detalles_cursos.php');
    exit;
}

// Obtener información del curso
$stmt = $conn->prepare("
    SELECT c.*, u.nombre as docente_nombre, u.email as docente_email
    FROM cursos c 
    LEFT JOIN usuarios u ON c.creado_por = u.id
    WHERE c.id = :curso_id AND c.estado != 'eliminado'
");
$stmt->execute([':curso_id' => $curso_id]);
$curso = $stmt->fetch();

if (!$curso) {
    header('Location: ' . BASE_URL . '/ejecutivo/detalles_cursos.php');
    exit;
}

$page_title = 'Ejecutivo – ' . $curso['titulo'];

// Estadísticas del curso
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT i.id) as total_inscritos,
        COUNT(DISTINCT CASE WHEN i.progreso = 100 THEN i.id END) as completados,
        COUNT(DISTINCT CASE WHEN i.progreso > 0 AND i.progreso < 100 THEN i.id END) as en_progreso,
        COUNT(DISTINCT CASE WHEN i.progreso = 0 THEN i.id END) as sin_iniciar,
        AVG(COALESCE(i.progreso, 0)) as promedio_progreso,
        COUNT(DISTINCT m.id) as total_modulos,
        COUNT(DISTINCT e.id) as total_evaluaciones
    FROM cursos c
    LEFT JOIN inscripciones i ON c.id = i.curso_id
    LEFT JOIN modulos m ON c.id = m.curso_id
    LEFT JOIN evaluaciones_modulo e ON m.id = e.modulo_id AND e.activo = 1
    WHERE c.id = :curso_id
    GROUP BY c.id
");
$stmt->execute([':curso_id' => $curso_id]);
$estadisticas = $stmt->fetch();

// Obtener estudiantes inscritos con su progreso
$stmt = $conn->prepare("
    SELECT i.*, u.nombre, u.email, u.telefono,
           i.progreso,
           i.fecha_inscripcion,
           i.fecha_completado,
           COUNT(DISTINCT pm.id) as modulos_completados,
           COUNT(DISTINCT em.id) as evaluaciones_realizadas,
           AVG(CASE WHEN em.calificacion IS NOT NULL THEN em.calificacion ELSE NULL END) as promedio_calificaciones
    FROM inscripciones i
    INNER JOIN usuarios u ON i.usuario_id = u.id
    LEFT JOIN progreso_modulos pm ON i.id = pm.inscripcion_id AND pm.completado = 1
    LEFT JOIN evaluaciones_modulo em ON pm.modulo_id = em.modulo_id AND em.usuario_id = u.id
    WHERE i.curso_id = :curso_id
    GROUP BY i.id, u.id
    ORDER BY i.progreso DESC, u.nombre ASC
");
$stmt->execute([':curso_id' => $curso_id]);
$estudiantes = $stmt->fetchAll();

// Obtener módulos del curso
$stmt = $conn->prepare("
    SELECT m.*, 
           COUNT(DISTINCT pm.id) as estudiantes_completados,
           COUNT(DISTINCT e.id) as evaluaciones_count
    FROM modulos m
    LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.completado = 1
    LEFT JOIN evaluaciones_modulo e ON m.id = e.modulo_id AND e.activo = 1
    WHERE m.curso_id = :curso_id
    GROUP BY m.id
    ORDER BY m.orden ASC
");
$stmt->execute([':curso_id' => $curso_id]);
$modulos = $stmt->fetchAll();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/ejecutivo.css">

<div class="exec-dashboard">
    <div class="exec-header">
        <div class="header-navigation">
            <a href="<?= BASE_URL ?>/ejecutivo/detalles_cursos.php" class="btn-back">
                ← Volver a Cursos
            </a>
        </div>
        
        <h1 class="exec-title"><?= htmlspecialchars($curso['titulo']) ?></h1>
        <p class="exec-subtitle"><?= htmlspecialchars($curso['descripcion']) ?></p>
        
        <div class="course-meta-info">
            <div class="meta-item">
                <strong>Docente:</strong> <?= htmlspecialchars($curso['docente_nombre']) ?>
                <span class="meta-email">(<?= htmlspecialchars($curso['docente_email']) ?>)</span>
            </div>
            <div class="meta-item">
                <strong>Estado:</strong> 
                <span class="estado-badge estado-<?= $curso['estado'] ?>"><?= ucfirst($curso['estado']) ?></span>
            </div>
            <div class="meta-item">
                <strong>Creado:</strong> <?= date('d/m/Y H:i', strtotime($curso['created_at'])) ?>
            </div>
        </div>
        
        <div class="exec-actions">
            <a href="<?= BASE_URL ?>/ejecutivo/generar_reportes.php?tipo=curso&id=<?= $curso_id ?>" class="btn-export">
                <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="Exportar">
                Generar Reporte
            </a>
        </div>
    </div>

    <!-- Estadísticas del Curso -->
    <div class="exec-stats">
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= $estadisticas['total_inscritos'] ?></span>
            <span class="exec-stat-description">Estudiantes Inscritos</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= $estadisticas['completados'] ?></span>
            <span class="exec-stat-description">Completados</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= number_format($estadisticas['promedio_progreso'], 1) ?>%</span>
            <span class="exec-stat-description">Progreso Promedio</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= $estadisticas['total_modulos'] ?></span>
            <span class="exec-stat-description">Módulos</span>
        </div>
    </div>

    <!-- Progreso General -->
    <div class="progress-overview">
        <h3>Distribución de Progreso</h3>
        <div class="progress-distribution">
            <div class="progress-segment completados" style="width: <?= $estadisticas['total_inscritos'] > 0 ? ($estadisticas['completados'] / $estadisticas['total_inscritos']) * 100 : 0 ?>%">
                <span><?= $estadisticas['completados'] ?> Completados</span>
            </div>
            <div class="progress-segment en-progreso" style="width: <?= $estadisticas['total_inscritos'] > 0 ? ($estadisticas['en_progreso'] / $estadisticas['total_inscritos']) * 100 : 0 ?>%">
                <span><?= $estadisticas['en_progreso'] ?> En Progreso</span>
            </div>
            <div class="progress-segment sin-iniciar" style="width: <?= $estadisticas['total_inscritos'] > 0 ? ($estadisticas['sin_iniciar'] / $estadisticas['total_inscritos']) * 100 : 0 ?>%">
                <span><?= $estadisticas['sin_iniciar'] ?> Sin Iniciar</span>
            </div>
        </div>
    </div>

    <!-- Módulos del Curso -->
    <div class="modules-section">
        <h3>Módulos del Curso</h3>
        <div class="modules-grid">
            <?php foreach ($modulos as $modulo): ?>
                <div class="module-card">
                    <h4><?= htmlspecialchars($modulo['titulo']) ?></h4>
                    <p><?= htmlspecialchars(substr($modulo['descripcion'], 0, 100)) ?>...</p>
                    <div class="module-stats">
                        <div class="module-stat">
                            <span class="stat-value"><?= $modulo['estudiantes_completados'] ?></span>
                            <span class="stat-label">Completados</span>
                        </div>
                        <div class="module-stat">
                            <span class="stat-value"><?= $modulo['evaluaciones_count'] ?></span>
                            <span class="stat-label">Evaluaciones</span>
                        </div>
                        <div class="module-progress">
                            <?php 
                            $completion_rate = $estadisticas['total_inscritos'] > 0 ? 
                                ($modulo['estudiantes_completados'] / $estadisticas['total_inscritos']) * 100 : 0;
                            ?>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $completion_rate ?>%"></div>
                            </div>
                            <span><?= number_format($completion_rate, 1) ?>%</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Lista de Estudiantes -->
    <div class="students-section">
        <h3>Estudiantes Inscritos (<?= count($estudiantes) ?>)</h3>
        
        <?php if (empty($estudiantes)): ?>
            <div class="no-results">
                <p>No hay estudiantes inscritos en este curso.</p>
            </div>
        <?php else: ?>
            <div class="students-table-container">
                <table class="students-table">
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Contacto</th>
                            <th>Progreso</th>
                            <th>Módulos</th>
                            <th>Evaluaciones</th>
                            <th>Promedio</th>
                            <th>Inscripción</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estudiantes as $estudiante): ?>
                            <tr>
                                <td>
                                    <div class="student-info">
                                        <strong><?= htmlspecialchars($estudiante['nombre']) ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <div class="contact-info">
                                        <div><?= htmlspecialchars($estudiante['email']) ?></div>
                                        <?php if ($estudiante['telefono']): ?>
                                            <div class="phone"><?= htmlspecialchars($estudiante['telefono']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="progress-cell">
                                        <div class="progress-bar-small">
                                            <div class="progress-fill" style="width: <?= $estudiante['progreso'] ?>%"></div>
                                        </div>
                                        <span class="progress-text"><?= number_format($estudiante['progreso'], 1) ?>%</span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="modules-completed">
                                        <?= $estudiante['modulos_completados'] ?>/<?= $estadisticas['total_modulos'] ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="evaluations-count">
                                        <?= $estudiante['evaluaciones_realizadas'] ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($estudiante['promedio_calificaciones']): ?>
                                        <span class="grade-average">
                                            <?= number_format($estudiante['promedio_calificaciones'], 1) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="no-grade">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="inscription-date">
                                        <?= date('d/m/Y', strtotime($estudiante['fecha_inscripcion'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($estudiante['progreso'] == 100): ?>
                                        <span class="status-badge completado">
                                            Completado
                                            <?php if ($estudiante['fecha_completado']): ?>
                                                <small><?= date('d/m/Y', strtotime($estudiante['fecha_completado'])) ?></small>
                                            <?php endif; ?>
                                        </span>
                                    <?php elseif ($estudiante['progreso'] > 0): ?>
                                        <span class="status-badge en-progreso">En Progreso</span>
                                    <?php else: ?>
                                        <span class="status-badge sin-iniciar">Sin Iniciar</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>