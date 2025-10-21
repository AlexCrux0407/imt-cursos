<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('ejecutivo');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Ejecutivo – Detalles por Estudiante';

// Filtros
$search = $_GET['search'] ?? '';
$curso_filter = $_GET['curso'] ?? '';
$progreso_filter = $_GET['progreso'] ?? '';
$order = $_GET['order'] ?? 'nombre';

// Construir consulta con filtros
$where_conditions = ["u.role = 'estudiante'", "u.estado = 'activo'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.nombre LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($curso_filter)) {
    $where_conditions[] = "i.curso_id = :curso";
    $params[':curso'] = $curso_filter;
}

if (!empty($progreso_filter)) {
    switch ($progreso_filter) {
        case 'completado':
            $where_conditions[] = "AVG(COALESCE(i.progreso, 0)) = 100";
            break;
        case 'en_progreso':
            $where_conditions[] = "AVG(COALESCE(i.progreso, 0)) > 0 AND AVG(COALESCE(i.progreso, 0)) < 100";
            break;
        case 'sin_iniciar':
            $where_conditions[] = "AVG(COALESCE(i.progreso, 0)) = 0";
            break;
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Determinar orden
switch($order) {
    case 'email':
        $order_clause = 'u.email ASC';
        break;
    case 'cursos':
        $order_clause = 'total_cursos DESC';
        break;
    case 'progreso':
        $order_clause = 'promedio_progreso DESC';
        break;
    case 'actividad':
        $order_clause = 'ultima_actividad DESC';
        break;
    default:
        $order_clause = 'u.nombre ASC';
        break;
}

// Obtener estudiantes con estadísticas
$stmt = $conn->prepare("
    SELECT u.*, 
           COUNT(DISTINCT i.id) as total_cursos,
           COUNT(DISTINCT CASE WHEN i.progreso = 100 THEN i.id END) as cursos_completados,
           COUNT(DISTINCT CASE WHEN i.progreso > 0 AND i.progreso < 100 THEN i.id END) as cursos_en_progreso,
           COUNT(DISTINCT CASE WHEN i.progreso = 0 THEN i.id END) as cursos_sin_iniciar,
           AVG(COALESCE(i.progreso, 0)) as promedio_progreso,
           COUNT(DISTINCT pm.id) as modulos_completados,
           COUNT(DISTINCT ie.id) as evaluaciones_realizadas,
           AVG(CASE WHEN ie.puntaje_obtenido IS NOT NULL THEN ie.puntaje_obtenido ELSE NULL END) as promedio_calificaciones,
           MAX(GREATEST(
               COALESCE(i.updated_at, '1970-01-01'),
               COALESCE(pm.fecha_completado, '1970-01-01'),
               COALESCE(ie.fecha_fin, '1970-01-01')
           )) as ultima_actividad
    FROM usuarios u 
    LEFT JOIN inscripciones i ON u.id = i.usuario_id
    LEFT JOIN progreso_modulos pm ON u.id = pm.usuario_id
    LEFT JOIN intentos_evaluacion ie ON u.id = ie.usuario_id
    WHERE $where_clause
    GROUP BY u.id 
    ORDER BY $order_clause
");

$stmt->execute($params);
$estudiantes = $stmt->fetchAll();

// Obtener lista de cursos para filtro
$stmt = $conn->prepare("
    SELECT DISTINCT c.id, c.titulo 
    FROM cursos c 
    INNER JOIN inscripciones i ON c.id = i.curso_id
    WHERE c.estado = 'activo'
    ORDER BY c.titulo
");
$stmt->execute();
$cursos = $stmt->fetchAll();

// Estadísticas generales
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT u.id) as total_estudiantes,
        COUNT(DISTINCT i.id) as total_inscripciones,
        AVG(COALESCE(i.progreso, 0)) as promedio_general,
        COUNT(DISTINCT CASE WHEN i.progreso = 100 THEN i.id END) as inscripciones_completadas
    FROM usuarios u
    LEFT JOIN inscripciones i ON u.id = i.usuario_id
    WHERE u.role = 'estudiante' AND u.estado = 'activo'
");
$stmt->execute();
$estadisticas = $stmt->fetch();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/ejecutivo.css">

<div class="exec-dashboard">
    <div class="exec-header">
        <h1 class="exec-title">Detalles por Estudiante</h1>
        <p class="exec-subtitle">Análisis detallado del rendimiento y progreso de cada estudiante</p>
        
        <div class="exec-actions">
            <a href="<?= BASE_URL ?>/ejecutivo/generar_reportes.php?tipo=estudiantes" class="btn-export">
                <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="Exportar">
                Generar Reporte
            </a>
        </div>
    </div>

    <!-- Estadísticas Generales -->
    <div class="exec-stats">
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= number_format($estadisticas['total_estudiantes']) ?></span>
            <span class="exec-stat-description">Total Estudiantes</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= number_format($estadisticas['total_inscripciones']) ?></span>
            <span class="exec-stat-description">Total Inscripciones</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= number_format($estadisticas['promedio_general'], 1) ?>%</span>
            <span class="exec-stat-description">Progreso Promedio</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= number_format($estadisticas['inscripciones_completadas']) ?></span>
            <span class="exec-stat-description">Cursos Completados</span>
        </div>
    </div>

    <!-- Filtros -->
    <div class="exec-filters">
        <form method="GET" class="filters-form">
            <div class="filter-group">
                <input type="text" name="search" placeholder="Buscar por nombre o email..." 
                       value="<?= htmlspecialchars($search) ?>" class="filter-input">
            </div>
            
            <div class="filter-group">
                <select name="curso" class="filter-select">
                    <option value="">Todos los cursos</option>
                    <?php foreach ($cursos as $curso): ?>
                        <option value="<?= $curso['id'] ?>" <?= $curso_filter == $curso['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($curso['titulo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <select name="progreso" class="filter-select">
                    <option value="">Todos los progresos</option>
                    <option value="completado" <?= $progreso_filter === 'completado' ? 'selected' : '' ?>>Completado</option>
                    <option value="en_progreso" <?= $progreso_filter === 'en_progreso' ? 'selected' : '' ?>>En Progreso</option>
                    <option value="sin_iniciar" <?= $progreso_filter === 'sin_iniciar' ? 'selected' : '' ?>>Sin Iniciar</option>
                </select>
            </div>
            
            <div class="filter-group">
                <select name="order" class="filter-select">
                    <option value="nombre" <?= $order === 'nombre' ? 'selected' : '' ?>>Ordenar por Nombre</option>
                    <option value="email" <?= $order === 'email' ? 'selected' : '' ?>>Ordenar por Email</option>
                    <option value="cursos" <?= $order === 'cursos' ? 'selected' : '' ?>>Ordenar por Cursos</option>
                    <option value="progreso" <?= $order === 'progreso' ? 'selected' : '' ?>>Ordenar por Progreso</option>
                    <option value="actividad" <?= $order === 'actividad' ? 'selected' : '' ?>>Ordenar por Actividad</option>
                </select>
            </div>
            
            <button type="submit" class="btn-filter">Filtrar</button>
            <a href="<?= BASE_URL ?>/ejecutivo/detalles_estudiantes.php" class="btn-clear">Limpiar</a>
        </form>
    </div>

    <!-- Lista de Estudiantes -->
    <div class="students-list">
        <?php if (empty($estudiantes)): ?>
            <div class="no-results">
                <p>No se encontraron estudiantes que coincidan con los filtros aplicados.</p>
            </div>
        <?php else: ?>
            <?php foreach ($estudiantes as $estudiante): ?>
                <div class="student-detail-card">
                    <div class="student-header">
                        <div class="student-info">
                            <h3 class="student-name"><?= htmlspecialchars($estudiante['nombre']) ?></h3>
                            <p class="student-email"><?= htmlspecialchars($estudiante['email']) ?></p>
                            <?php if (isset($estudiante['telefono']) && $estudiante['telefono']): ?>
                                <p class="student-phone"><?= htmlspecialchars($estudiante['telefono']) ?></p>
                            <?php endif; ?>
                            <div class="student-meta">
                                <span class="student-registro">
                                    Registro: <?= date('d/m/Y', strtotime($estudiante['created_at'])) ?>
                                </span>
                                <?php if ($estudiante['ultima_actividad'] && $estudiante['ultima_actividad'] != '1970-01-01 00:00:00'): ?>
                                    <span class="student-actividad">
                                        Última actividad: <?= date('d/m/Y H:i', strtotime($estudiante['ultima_actividad'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="student-stats-summary">
                            <div class="stat-item">
                                <span class="stat-value"><?= $estudiante['total_cursos'] ?></span>
                                <span class="stat-label">Cursos</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?= number_format($estudiante['promedio_progreso'], 1) ?>%</span>
                                <span class="stat-label">Progreso Promedio</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="student-details">
                        <div class="detail-section">
                            <h4>Distribución de Cursos</h4>
                            <div class="course-distribution">
                                <div class="distribution-item completados">
                                    <span class="distribution-count"><?= $estudiante['cursos_completados'] ?></span>
                                    <span class="distribution-label">Completados</span>
                                </div>
                                <div class="distribution-item en-progreso">
                                    <span class="distribution-count"><?= $estudiante['cursos_en_progreso'] ?></span>
                                    <span class="distribution-label">En Progreso</span>
                                </div>
                                <div class="distribution-item sin-iniciar">
                                    <span class="distribution-count"><?= $estudiante['cursos_sin_iniciar'] ?></span>
                                    <span class="distribution-label">Sin Iniciar</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4>Actividad de Aprendizaje</h4>
                            <div class="learning-activity">
                                <div class="activity-item">
                                    <img src="<?= BASE_URL ?>/styles/iconos/desk.png" alt="Módulos">
                                    <span class="activity-count"><?= $estudiante['modulos_completados'] ?></span>
                                    <span class="activity-label">Módulos Completados</span>
                                </div>
                                <div class="activity-item">
                                    <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="Evaluaciones">
                                    <span class="activity-count"><?= $estudiante['evaluaciones_realizadas'] ?></span>
                                    <span class="activity-label">Evaluaciones Realizadas</span>
                                </div>
                                <?php if ($estudiante['promedio_calificaciones']): ?>
                                    <div class="activity-item">
                                        <img src="<?= BASE_URL ?>/styles/iconos/addicon.png" alt="Promedio">
                                        <span class="activity-count"><?= number_format($estudiante['promedio_calificaciones'], 1) ?></span>
                                        <span class="activity-label">Promedio de Calificaciones</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4>Progreso General</h4>
                            <div class="progress-bar-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $estudiante['promedio_progreso'] ?>%"></div>
                                </div>
                                <span class="progress-text"><?= number_format($estudiante['promedio_progreso'], 1) ?>% de progreso promedio</span>
                            </div>
                        </div>
                        
                        <?php if ($estudiante['total_cursos'] > 0): ?>
                            <div class="detail-section">
                                <h4>Rendimiento por Curso</h4>
                                <div class="course-performance">
                                    <?php
                                    // Obtener cursos específicos del estudiante
                                    $stmt_cursos = $conn->prepare("
                                        SELECT c.titulo, i.progreso, i.fecha_inscripcion, i.fecha_completado,
                                               COUNT(DISTINCT pm.id) as modulos_completados,
                                               COUNT(DISTINCT m.id) as total_modulos,
                                               AVG(CASE WHEN ie.puntaje_obtenido IS NOT NULL THEN ie.puntaje_obtenido ELSE NULL END) as promedio_curso
                                        FROM inscripciones i
                                        INNER JOIN cursos c ON i.curso_id = c.id
                                        LEFT JOIN modulos m ON c.id = m.curso_id
                                        LEFT JOIN progreso_modulos pm ON i.usuario_id = pm.usuario_id AND m.id = pm.modulo_id AND pm.completado = 1
                                        LEFT JOIN intentos_evaluacion ie ON ie.evaluacion_id IN (
                                            SELECT em.id FROM evaluaciones_modulo em WHERE em.modulo_id = m.id
                                        ) AND ie.usuario_id = i.usuario_id
                                        WHERE i.usuario_id = :usuario_id
                                        GROUP BY c.id, i.id
                                        ORDER BY i.fecha_inscripcion DESC
                                    ");
                                    $stmt_cursos->execute([':usuario_id' => $estudiante['id']]);
                                    $cursos_estudiante = $stmt_cursos->fetchAll();
                                    ?>
                                    
                                    <?php foreach ($cursos_estudiante as $curso_est): ?>
                                        <div class="course-performance-item">
                                            <div class="course-name"><?= htmlspecialchars($curso_est['titulo']) ?></div>
                                            <div class="course-progress">
                                                <div class="progress-bar-small">
                                                    <div class="progress-fill" style="width: <?= $curso_est['progreso'] ?>%"></div>
                                                </div>
                                                <span><?= number_format($curso_est['progreso'], 1) ?>%</span>
                                            </div>
                                            <div class="course-modules">
                                                <?= $curso_est['modulos_completados'] ?>/<?= $curso_est['total_modulos'] ?> módulos
                                            </div>
                                            <?php if ($curso_est['promedio_curso']): ?>
                                                <div class="course-grade">
                                                    Promedio: <?= number_format($curso_est['promedio_curso'], 1) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="student-actions">
                        <a href="<?= BASE_URL ?>/ejecutivo/detalle_estudiante.php?id=<?= $estudiante['id'] ?>" class="btn-detail">
                            Ver Detalle Completo
                        </a>
                        <a href="<?= BASE_URL ?>/ejecutivo/generar_reportes.php?tipo=estudiante&id=<?= $estudiante['id'] ?>" class="btn-export-small">
                            Exportar Datos
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>