<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Resumen General';

$docente_id = $_SESSION['user_id'];

// Obtener todos los cursos del docente con estadísticas
$stmt = $conn->prepare("
    SELECT 
        c.id,
        c.titulo,
        c.descripcion,
        c.estado,
        c.created_at,
        COUNT(DISTINCT i.usuario_id) as total_estudiantes,
        COUNT(DISTINCT CASE WHEN i.estado = 'completado' THEN i.usuario_id END) as estudiantes_completados,
        AVG(COALESCE(i.progreso, 0)) as progreso_promedio,
        MAX(i.fecha_inscripcion) as ultima_inscripcion
    FROM cursos c
    LEFT JOIN inscripciones i ON c.id = i.curso_id
    WHERE c.creado_por = :docente_id OR c.asignado_a = :docente_id2
    GROUP BY c.id, c.titulo, c.descripcion, c.estado, c.created_at
    ORDER BY c.created_at DESC
");
$stmt->execute([':docente_id' => $docente_id, ':docente_id2' => $docente_id]);
$cursos = $stmt->fetchAll();

// Obtener estadísticas generales
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT c.id) as total_cursos,
        COUNT(DISTINCT i.usuario_id) as total_estudiantes_unicos,
        COUNT(DISTINCT i.id) as total_inscripciones,
        COUNT(DISTINCT CASE WHEN i.estado = 'completado' THEN i.id END) as total_completados,
        AVG(COALESCE(i.progreso, 0)) as progreso_general
    FROM cursos c
    LEFT JOIN inscripciones i ON c.id = i.curso_id
    WHERE c.creado_por = :docente_id OR c.asignado_a = :docente_id2
");
$stmt->execute([':docente_id' => $docente_id, ':docente_id2' => $docente_id]);
$estadisticas_generales = $stmt->fetch();

// Obtener estudiantes más activos
$stmt = $conn->prepare("
    SELECT 
        u.nombre,
        u.email,
        COUNT(i.id) as cursos_inscritos,
        COUNT(CASE WHEN i.estado = 'completado' THEN 1 END) as cursos_completados,
        AVG(i.progreso) as progreso_promedio
    FROM usuarios u
    INNER JOIN inscripciones i ON u.id = i.usuario_id
    INNER JOIN cursos c ON i.curso_id = c.id
    WHERE (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
    GROUP BY u.id, u.nombre, u.email
    ORDER BY cursos_inscritos DESC, progreso_promedio DESC
    LIMIT 10
");
$stmt->execute([':docente_id' => $docente_id, ':docente_id2' => $docente_id]);
$estudiantes_activos = $stmt->fetchAll();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/docente.css">

<style>
.summary-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-stat-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
    border-left: 4px solid #3498db;
}

.summary-stat-value {
    font-size: 2.5rem;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 8px;
}

.summary-stat-label {
    color: #7f8c8d;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.course-summary-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.course-summary-card:hover {
    transform: translateY(-2px);
}

.course-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.course-title {
    color: #2c3e50;
    font-size: 1.2rem;
    margin: 0;
}

.course-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-activo { background: #d4edda; color: #155724; }
.status-inactivo { background: #f8d7da; color: #721c24; }

.course-stats-mini {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin: 15px 0;
}

.mini-stat {
    text-align: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
}

.mini-stat-value {
    font-weight: bold;
    color: #3498db;
    font-size: 1.1rem;
}

.mini-stat-label {
    font-size: 0.8rem;
    color: #7f8c8d;
}

.students-section {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.students-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.students-table th,
.students-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.students-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #2c3e50;
}

.progress-mini {
    display: flex;
    align-items: center;
    gap: 10px;
}

.progress-bar-mini {
    flex: 1;
    height: 6px;
    background: #eee;
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill-mini {
    height: 100%;
    background: #3498db;
    transition: width 0.3s ease;
}
</style>

<div class="summary-container">
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white;">
        <div class="div-fila-alt-start">
            <div>
                <h1 style="font-size: 2rem; margin-bottom: 10px;">Resumen General</h1>
                <p style="opacity: 0.9;">Vista completa de todos tus cursos y estudiantes</p>
            </div>
            <a href="<?= BASE_URL ?>/docente/dashboard.php" class="btn" 
               style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white; padding: 12px 20px; border-radius: 8px; text-decoration: none;">
                ← Dashboard
            </a>
        </div>
    </div>

    <!-- Estadísticas Generales -->
    <div class="summary-stats">
        <div class="summary-stat-card">
            <div class="summary-stat-value"><?= $estadisticas_generales['total_cursos'] ?: 0 ?></div>
            <div class="summary-stat-label">Total Cursos</div>
        </div>
        <div class="summary-stat-card">
            <div class="summary-stat-value"><?= $estadisticas_generales['total_estudiantes_unicos'] ?: 0 ?></div>
            <div class="summary-stat-label"></div>
        </div>
        <div class="summary-stat-card">
            <div class="summary-stat-value"><?= $estadisticas_generales['total_inscripciones'] ?: 0 ?></div>
            <div class="summary-stat-label">Total Inscripciones</div>
        </div>
        <div class="summary-stat-card">
            <div class="summary-stat-value"><?= $estadisticas_generales['total_completados'] ?: 0 ?></div>
            <div class="summary-stat-label">Cursos Completados</div>
        </div>
        <div class="summary-stat-card">
            <div class="summary-stat-value"><?= number_format($estadisticas_generales['progreso_general'] ?: 0, 1) ?>%</div>
            <div class="summary-stat-label">Progreso General</div>
        </div>
    </div>

    <!-- Resumen de Cursos -->
    <div class="form-container-body">
        <h2 style="color: #3498db; margin-bottom: 20px;">Mis Cursos</h2>
        
        <?php if (empty($cursos)): ?>
            <div class="empty-state">
                <img src="<?= BASE_URL ?>/styles/iconos/desk.png" style="width: 64px; height: 64px; opacity: 0.5; margin-bottom: 20px;">
                <h3>No tienes cursos creados</h3>
                <p>Crea tu primer curso para comenzar</p>
                <a href="<?= BASE_URL ?>/docente/admin_cursos.php" class="btn btn-primary">Crear Curso</a>
            </div>
        <?php else: ?>
            <div class="courses-grid">
                <?php foreach ($cursos as $curso): ?>
                    <div class="course-summary-card">
                        <div class="course-header">
                            <h3 class="course-title"><?= htmlspecialchars($curso['titulo']) ?></h3>
                            <span class="course-status status-<?= $curso['estado'] ?>">
                                <?= ucfirst($curso['estado']) ?>
                            </span>
                        </div>
                        
                        <p style="color: #7f8c8d; font-size: 0.9rem; margin-bottom: 15px;">
                            <?= htmlspecialchars(substr($curso['descripcion'] ?: 'Sin descripción', 0, 100)) ?>...
                        </p>
                        
                        <div class="course-stats-mini">
                            <div class="mini-stat">
                                <div class="mini-stat-value"><?= $curso['total_estudiantes'] ?></div>
                                <div class="mini-stat-label">Estudiantes</div>
                            </div>
                            <div class="mini-stat">
                                <div class="mini-stat-value"><?= $curso['estudiantes_completados'] ?></div>
                                <div class="mini-stat-label">Completados</div>
                            </div>
                            <div class="mini-stat">
                                <div class="mini-stat-value"><?= number_format($curso['progreso_promedio'] ?: 0, 1) ?>%</div>
                                <div class="mini-stat-label">Progreso</div>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <a href="<?= BASE_URL ?>/docente/visualizar_curso.php?id=<?= $curso['id'] ?>" 
                               class="btn btn-primary" style="flex: 1; text-align: center; padding: 8px 12px; font-size: 0.9rem;">
                                Ver Detalles
                            </a>
                            <a href="<?= BASE_URL ?>/docente/editar_curso.php?id=<?= $curso['id'] ?>" 
                               class="btn btn-secondary" style="flex: 1; text-align: center; padding: 8px 12px; font-size: 0.9rem;">
                                Editar
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Estudiantes Más Activos -->
    <?php if (!empty($estudiantes_activos)): ?>
    <div class="students-section">
        <h2 style="color: #3498db; margin-bottom: 15px;">Estudiantes Más Activos</h2>
        <p style="color: #7f8c8d; margin-bottom: 20px;">Los 10 estudiantes con mayor participación en tus cursos</p>
        
        <table class="students-table">
            <thead>
                <tr>
                    <th>Estudiante</th>
                    <th style="text-align: center;">Cursos Inscritos</th>
                    <th style="text-align: center;">Completados</th>
                    <th style="text-align: center;">Progreso Promedio</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($estudiantes_activos as $estudiante): ?>
                    <tr>
                        <td>
                            <div>
                                <div style="font-weight: 500; color: #2c3e50;"><?= htmlspecialchars($estudiante['nombre']) ?></div>
                                <div style="font-size: 0.85rem; color: #7f8c8d;"><?= htmlspecialchars($estudiante['email']) ?></div>
                            </div>
                        </td>
                        <td style="text-align: center; font-weight: 500; color: #3498db;">
                            <?= $estudiante['cursos_inscritos'] ?>
                        </td>
                        <td style="text-align: center; font-weight: 500; color: #27ae60;">
                            <?= $estudiante['cursos_completados'] ?>
                        </td>
                        <td style="text-align: center;">
                            <div class="progress-mini">
                                <div class="progress-bar-mini">
                                    <div class="progress-fill-mini" style="width: <?= $estudiante['progreso_promedio'] ?>%;"></div>
                                </div>
                                <span style="font-weight: 500; color: #2c3e50; min-width: 45px;">
                                    <?= number_format($estudiante['progreso_promedio'] ?: 0, 1) ?>%
                                </span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>