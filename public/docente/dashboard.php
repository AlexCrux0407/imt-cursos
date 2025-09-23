<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
$page_title = 'Docente – Dashboard';
require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="/imt-cursos/public/styles/css/docente.css">

<div class="dashboard-container">
    <h1 class="dashboard-title">Dashboard Docente</h1>
    <p class="dashboard-subtitle">Bienvenido a tu panel de control, aquí puedes gestionar tus cursos y estudiantes</p>

    <!-- Stats Overview -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number">12</div>
            <div class="stat-label">Cursos Activos</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">248</div>
            <div class="stat-label">Estudiantes</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">89%</div>
            <div class="stat-label">Promedio Avance</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">15</div>
            <div class="stat-label">Certificados</div>
        </div>
    </div>

    <!-- Feature Cards -->
    <div class="feature-grid">
        <div class="feature-card">
            <div class="feature-icon">
                <img src="/imt-cursos/public/styles/iconos/config.png" alt="Administrar">
            </div>
            <h3 class="feature-title">Administración de Cursos</h3>
            <p class="feature-description">
                Gestiona el contenido de tus cursos, crea nuevas lecciones, actualiza materiales y organiza el contenido educativo.
            </p>
            <a href="/imt-cursos/public/docente/admin_cursos.php" class="feature-link">
                Administrar Cursos &rarr;
            </a>
        </div>

        <div class="feature-card">
            <div class="feature-icon">
                <img src="/imt-cursos/public/styles/iconos/detalles.png" alt="Visualizar">
            </div>
            <h3 class="feature-title">Visualización de Progreso</h3>
            <p class="feature-description">
                Monitorea el progreso de tus estudiantes, revisa estadísticas de avance y fechas de finalización de cursos.
            </p>
            <a href="/imt-cursos/public/docente/visualizar_curso.php" class="feature-link">
                Ver Progreso &rarr;
            </a>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <h3>Acciones Rápidas</h3>
        <div class="action-buttons">
            <button class="action-btn">Nuevo Curso</button>
            <button class="action-btn">Editar Perfil</button>
            <button class="action-btn">Reportes</button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
            <button class="action-btn">
                <img src="/imt-cursos/public/styles/iconos/detalles.png" alt="" style="width: 16px; height: 16px; filter: brightness(0) invert(1);">
                Reportes
            </button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
