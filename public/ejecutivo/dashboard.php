<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('ejecutivo');
$page_title = 'Ejecutivo – Dashboard de Reportes';
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
            <span class="exec-stat-value">156</span>
            <span class="exec-stat-description">Total Usuarios</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value">24</span>
            <span class="exec-stat-description">Docentes</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value">132</span>
            <span class="exec-stat-description">Estudiantes</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value">45</span>
            <span class="exec-stat-description">Cursos Activos</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value">89%</span>
            <span class="exec-stat-description">Tasa Completación</span>
        </div>
    </div>

    <!-- Reports Grid -->
    <div class="reports-grid">
        <div class="report-card">
            <div class="report-icon">
                <img src="<?= BASE_URL ?>/styles/iconos/addicon.png" alt="Estudiantes">
            </div>
            <h3 class="report-title">Reportes de Estudiantes</h3>
            <p class="report-description">
                Visualiza estadísticas de estudiantes, progreso académico, inscripciones y rendimiento general.
            </p>
            <a href="<?= BASE_URL ?>/ejecutivo/reportes_estudiantes.php" class="report-link">
                Ver Reportes
            </a>
        </div>

        <div class="report-card">
            <div class="report-icon">
                <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="Docentes">
            </div>
            <h3 class="report-title">Reportes de Docentes</h3>
            <p class="report-description">
                Analiza la actividad docente, cursos asignados, estudiantes por docente y eficiencia educativa.
            </p>
            <a href="<?= BASE_URL ?>/ejecutivo/reportes_docentes.php" class="report-link">
                Ver Reportes
            </a>
        </div>

        <div class="report-card">
            <div class="report-icon">
                <img src="<?= BASE_URL ?>/styles/iconos/desk.png" alt="Cursos">
            </div>
            <h3 class="report-title">Reportes de Cursos</h3>
            <p class="report-description">
                Examina el desempeño de cursos, tasas de finalización, popularidad y efectividad del contenido.
            </p>
            <a href="<?= BASE_URL ?>/ejecutivo/reportes_cursos.php" class="report-link">
                Ver Reportes
            </a>
        </div>
    </div>

    <!-- Quick Reports -->
    <div class="quick-reports">
        <h2 class="quick-reports-title">Reportes Rápidos</h2>
        <div class="report-actions">
            <div class="report-action-item">
                <h4>Reporte Mensual</h4>
                <p>Estadísticas generales del mes actual</p>
                <small style="color: var(--exec-primary);">Disponible</small>
            </div>
            <div class="report-action-item">
                <h4>Análisis de Tendencias</h4>
                <p>Evolución de métricas en los últimos 6 meses</p>
                <small style="color: var(--exec-primary);">Disponible</small>
            </div>
            <div class="report-action-item">
                <h4>Reporte Ejecutivo</h4>
                <p>Resumen para dirección general</p>
                <small style="color: var(--exec-primary);">Disponible</small>
            </div>
            <div class="report-action-item">
                <h4>Exportar Datos</h4>
                <p>Descargar información en formatos Excel/PDF</p>
                <small style="color: var(--exec-accent);">Próximamente</small>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
