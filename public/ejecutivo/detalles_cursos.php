<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('ejecutivo');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Ejecutivo – Detalles por Curso';

// Filtros
$search = $_GET['search'] ?? '';
$estado_filter = $_GET['estado'] ?? '';
$docente_filter = $_GET['docente'] ?? '';
$order = $_GET['order'] ?? 'titulo';

// Construir consulta con filtros
$where_conditions = ["c.estado != 'eliminado'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.titulo LIKE :search OR c.descripcion LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($estado_filter)) {
    $where_conditions[] = "c.estado = :estado";
    $params[':estado'] = $estado_filter;
}

if (!empty($docente_filter)) {
    $where_conditions[] = "c.creado_por = :docente";
    $params[':docente'] = $docente_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Determinar orden
switch($order) {
    case 'fecha':
        $order_clause = 'c.created_at DESC';
        break;
    case 'estudiantes':
        $order_clause = 'total_inscritos DESC';
        break;
    case 'progreso':
        $order_clause = 'promedio_progreso DESC';
        break;
    default:
        $order_clause = 'c.titulo ASC';
        break;
}

// Obtener cursos con estadísticas
$stmt = $conn->prepare("
    SELECT c.*, 
           u.nombre as docente_nombre,
           COUNT(DISTINCT i.id) as total_inscritos,
           COUNT(DISTINCT CASE WHEN i.progreso = 100 THEN i.id END) as estudiantes_completados,
           COUNT(DISTINCT CASE WHEN i.progreso > 0 AND i.progreso < 100 THEN i.id END) as estudiantes_en_progreso,
           COUNT(DISTINCT CASE WHEN i.progreso = 0 THEN i.id END) as estudiantes_sin_iniciar,
           AVG(COALESCE(i.progreso, 0)) as promedio_progreso,
           COUNT(DISTINCT m.id) as total_modulos,
           COUNT(DISTINCT e.id) as total_evaluaciones
    FROM cursos c 
    LEFT JOIN usuarios u ON c.creado_por = u.id
    LEFT JOIN inscripciones i ON c.id = i.curso_id 
    LEFT JOIN modulos m ON c.id = m.curso_id
    LEFT JOIN evaluaciones_modulo e ON m.id = e.modulo_id AND e.activo = 1
    WHERE $where_clause
    GROUP BY c.id 
    ORDER BY $order_clause
");

$stmt->execute($params);
$cursos = $stmt->fetchAll();

// Obtener lista de docentes para filtro
$stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.nombre 
    FROM usuarios u 
    INNER JOIN cursos c ON u.id = c.creado_por 
    WHERE u.role = 'docente' AND u.estado = 'activo'
    ORDER BY u.nombre
");
$stmt->execute();
$docentes = $stmt->fetchAll();

// Estadísticas generales
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT c.id) as total_cursos,
        COUNT(DISTINCT CASE WHEN c.estado = 'activo' THEN c.id END) as cursos_activos,
        COUNT(DISTINCT i.id) as total_inscripciones,
        AVG(COALESCE(i.progreso, 0)) as promedio_general
    FROM cursos c
    LEFT JOIN inscripciones i ON c.id = i.curso_id
    WHERE c.estado != 'eliminado'
");
$stmt->execute();
$estadisticas = $stmt->fetch();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/ejecutivo.css">

<div class="exec-dashboard">
    <div class="exec-header">
        <h1 class="exec-title">Detalles por Curso</h1>
        <p class="exec-subtitle">Análisis detallado del rendimiento y estadísticas de cada curso</p>
        
        <div class="exec-actions">
            <a href="<?= BASE_URL ?>/ejecutivo/generar_reportes.php?tipo=cursos" class="btn-export">
                <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="Exportar">
                Generar Reporte
            </a>
        </div>
    </div>

    <!-- Estadísticas Generales -->
    <div class="exec-stats">
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= number_format($estadisticas['total_cursos']) ?></span>
            <span class="exec-stat-description">Total Cursos</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= number_format($estadisticas['cursos_activos']) ?></span>
            <span class="exec-stat-description">Cursos Activos</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= number_format($estadisticas['total_inscripciones']) ?></span>
            <span class="exec-stat-description">Total Inscripciones</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= number_format($estadisticas['promedio_general'], 1) ?>%</span>
            <span class="exec-stat-description">Progreso Promedio</span>
        </div>
    </div>

    <!-- Filtros -->
    <div class="exec-filters">
        <form method="GET" class="filters-form">
            <div class="filter-group">
                <input type="text" name="search" placeholder="Buscar por título o descripción..." 
                       value="<?= htmlspecialchars($search) ?>" class="filter-input">
            </div>
            
            <div class="filter-group">
                <select name="estado" class="filter-select">
                    <option value="">Todos los estados</option>
                    <option value="activo" <?= $estado_filter === 'activo' ? 'selected' : '' ?>>Activo</option>
                    <option value="borrador" <?= $estado_filter === 'borrador' ? 'selected' : '' ?>>Borrador</option>
                    <option value="inactivo" <?= $estado_filter === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
            
            <div class="filter-group">
                <select name="docente" class="filter-select">
                    <option value="">Todos los docentes</option>
                    <?php foreach ($docentes as $docente): ?>
                        <option value="<?= $docente['id'] ?>" <?= $docente_filter == $docente['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($docente['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <select name="order" class="filter-select">
                    <option value="titulo" <?= $order === 'titulo' ? 'selected' : '' ?>>Ordenar por Título</option>
                    <option value="fecha" <?= $order === 'fecha' ? 'selected' : '' ?>>Ordenar por Fecha</option>
                    <option value="estudiantes" <?= $order === 'estudiantes' ? 'selected' : '' ?>>Ordenar por Estudiantes</option>
                    <option value="progreso" <?= $order === 'progreso' ? 'selected' : '' ?>>Ordenar por Progreso</option>
                </select>
            </div>
            
            <button type="submit" class="btn-filter">Filtrar</button>
            <a href="<?= BASE_URL ?>/ejecutivo/detalles_cursos.php" class="btn-clear">Limpiar</a>
        </form>
    </div>

    <!-- Lista de Cursos -->
    <div class="courses-list">
        <?php if (empty($cursos)): ?>
            <div class="no-results">
                <p>No se encontraron cursos que coincidan con los filtros aplicados.</p>
            </div>
        <?php else: ?>
            <?php foreach ($cursos as $curso): ?>
                <div class="course-detail-card">
                    <div class="course-header">
                        <div class="course-info">
                            <h3 class="course-title"><?= htmlspecialchars($curso['titulo']) ?></h3>
                            <p class="course-description"><?= htmlspecialchars(substr($curso['descripcion'], 0, 150)) ?>...</p>
                            <div class="course-meta">
                                <span class="course-docente">
                                    <img src="<?= BASE_URL ?>/styles/iconos/addicon.png" alt="Docente">
                                    <?= htmlspecialchars($curso['docente_nombre']) ?>
                                </span>
                                <span class="course-estado estado-<?= $curso['estado'] ?>">
                                    <?= ucfirst($curso['estado']) ?>
                                </span>
                                <span class="course-fecha">
                                    Creado: <?= date('d/m/Y', strtotime($curso['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="course-stats-summary">
                            <div class="stat-item">
                                <span class="stat-value"><?= $curso['total_inscritos'] ?></span>
                                <span class="stat-label">Estudiantes</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?= number_format($curso['promedio_progreso'], 1) ?>%</span>
                                <span class="stat-label">Progreso Promedio</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="course-details">
                        <div class="detail-section">
                            <h4>Estructura del Curso</h4>
                            <div class="structure-info">
                                <span class="structure-item">
                                    <img src="<?= BASE_URL ?>/styles/iconos/desk.png" alt="Módulos">
                                    <?= $curso['total_modulos'] ?> Módulos
                                </span>
                                <span class="structure-item">
                                    <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="Evaluaciones">
                                    <?= $curso['total_evaluaciones'] ?> Evaluaciones
                                </span>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4>Distribución de Estudiantes</h4>
                            <div class="student-distribution">
                                <div class="distribution-item completados">
                                    <span class="distribution-count"><?= $curso['estudiantes_completados'] ?></span>
                                    <span class="distribution-label">Completados</span>
                                </div>
                                <div class="distribution-item en-progreso">
                                    <span class="distribution-count"><?= $curso['estudiantes_en_progreso'] ?></span>
                                    <span class="distribution-label">En Progreso</span>
                                </div>
                                <div class="distribution-item sin-iniciar">
                                    <span class="distribution-count"><?= $curso['estudiantes_sin_iniciar'] ?></span>
                                    <span class="distribution-label">Sin Iniciar</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4>Barra de Progreso</h4>
                            <div class="progress-bar-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $curso['promedio_progreso'] ?>%"></div>
                                </div>
                                <span class="progress-text"><?= number_format($curso['promedio_progreso'], 1) ?>% completado</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="course-actions">
                        <a href="<?= BASE_URL ?>/ejecutivo/detalle_curso.php?id=<?= $curso['id'] ?>" class="btn-detail">
                            Ver Detalle Completo
                        </a>
                        <a href="<?= BASE_URL ?>/ejecutivo/generar_reportes.php?tipo=curso&id=<?= $curso['id'] ?>" class="btn-export-small">
                            Exportar Datos
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>