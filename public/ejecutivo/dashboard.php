<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('ejecutivo');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Ejecutivo – Dashboard de Reportes';

// Obtener estadísticas generales
$stmt = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM usuarios) as total_usuarios,
        (SELECT COUNT(*) FROM usuarios WHERE role = 'docente' AND estado = 'activo') as total_docentes,
        (SELECT COUNT(*) FROM usuarios WHERE role = 'estudiante' AND estado = 'activo') as total_estudiantes,
        (SELECT COUNT(*) FROM cursos WHERE estado = 'activo') as cursos_activos,
        (SELECT COUNT(*) FROM inscripciones) as total_inscripciones,
        (SELECT AVG(progreso) FROM inscripciones WHERE progreso > 0) as tasa_completacion
");
$stmt->execute();
$stats = $stmt->fetch();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/ejecutivo.css">

<div class="exec-dashboard">
    <div class="exec-header">
        <h1 class="exec-title">Dashboard Ejecutivo</h1>
        <p class="exec-subtitle">Centro de reportes y análisis - Visualización de datos del sistema educativo</p>
    </div>

    <!-- Stats Overview -->
    <div class="exec-stats">
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= number_format($stats['total_usuarios']) ?></span>
            <span class="exec-stat-description">Total Usuarios</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= number_format($stats['total_docentes']) ?></span>
            <span class="exec-stat-description">Docentes</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= number_format($stats['total_estudiantes']) ?></span>
            <span class="exec-stat-description">Estudiantes</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= number_format($stats['cursos_activos']) ?></span>
            <span class="exec-stat-description">Cursos Activos</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= number_format($stats['tasa_completacion'], 1) ?>%</span>
            <span class="exec-stat-description">Tasa Completación</span>
        </div>
    </div>

    <!-- Reports Grid -->
    <div class="reports-grid">
        <div class="report-card">
            <div class="report-icon">
                <img src="<?= BASE_URL ?>/styles/iconos/addicon.png" alt="Estudiantes">
            </div>
            <h3 class="report-title">Detalles por Estudiante</h3>
            <p class="report-description">
                Visualiza el progreso individual de estudiantes, cursos inscritos y rendimiento académico detallado.
            </p>
            <a href="<?= BASE_URL ?>/ejecutivo/detalles_estudiantes.php" class="report-link">
                Ver Detalles
            </a>
        </div>

        <div class="report-card">
            <div class="report-icon">
                <img src="<?= BASE_URL ?>/styles/iconos/desk.png" alt="Cursos">
            </div>
            <h3 class="report-title">Detalles por Curso</h3>
            <p class="report-description">
                Examina el desempeño de cursos, estudiantes inscritos, tasas de finalización y estadísticas detalladas.
            </p>
            <a href="<?= BASE_URL ?>/ejecutivo/detalles_cursos.php" class="report-link">
                Ver Detalles
            </a>
        </div>

        <div class="report-card">
            <div class="report-icon">
                <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="Reportes">
            </div>
            <h3 class="report-title">Generar Reportes</h3>
            <p class="report-description">
                Genera reportes personalizados en PDF y Excel con datos de estudiantes, cursos y rendimiento general.
            </p>
            <a href="<?= BASE_URL ?>/ejecutivo/generar_reportes.php" class="report-link">
                Generar Reportes
            </a>
        </div>
    </div>

    <!-- Quick Reports -->
    <div class="quick-reports">
        <h2 class="quick-reports-title">Acciones Rápidas</h2>
        <div class="report-actions">
            <div class="report-action-item">
                <h4>Resumen General</h4>
                <p>Vista general de la plataforma con métricas clave</p>
                <small style="color: var(--exec-primary);">Disponible</small>
            </div>
            <div class="report-action-item">
                <h4>Análisis de Rendimiento</h4>
                <p>Evaluación del desempeño académico por curso y estudiante</p>
                <small style="color: var(--exec-primary);">Disponible</small>
            </div>
            <div class="report-action-item">
                <h4>Exportar a PDF</h4>
                <p>Generar reportes ejecutivos en formato PDF</p>
                <small style="color: var(--exec-primary);">Disponible</small>
            </div>
            <div class="report-action-item">
                <h4>Exportar a Excel</h4>
                <p>Descargar datos detallados en formato Excel</p>
                <small style="color: var(--exec-primary);">Disponible</small>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
