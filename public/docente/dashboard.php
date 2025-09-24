<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Dashboard';

$docente_id = $_SESSION['user_id'];

// Obtener estadísticas del docente
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT CASE WHEN c.estado = 'activo' THEN c.id END) as cursos_activos,
        COUNT(DISTINCT i.usuario_id) as total_estudiantes,
        AVG(COALESCE(i.progreso, 0)) as promedio_avance,
        COUNT(DISTINCT CASE WHEN i.estado = 'completado' THEN i.id END) as certificados_emitidos
    FROM cursos c
    LEFT JOIN inscripciones i ON c.id = i.curso_id
    WHERE c.creado_por = :docente_id
");
$stmt->execute([':docente_id' => $docente_id]);
$estadisticas = $stmt->fetch();
?>

<?php require __DIR__ . '/../partials/header.php'; ?>
<?php require __DIR__ . '/../partials/nav.php'; ?>

<link rel="stylesheet" href="/imt-cursos/public/styles/css/docente.css">

<div class="dashboard-container">
    <div class="teacher-dashboard">
        <div class="teacher-welcome">
            <h1 class="welcome-title">¡Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?>!</h1>
            <p class="welcome-subtitle">Gestiona tus cursos y supervisa el progreso de tus estudiantes</p>
        </div>

        <!-- Estadísticas principales -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-content">
                    <h3 class="stat-value"><?= $estadisticas['cursos_activos'] ?: 0 ?></h3>
                    <p class="stat-label">Cursos Activos</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-content">
                    <h3 class="stat-value"><?= $estadisticas['total_estudiantes'] ?: 0 ?></h3>
                    <p class="stat-label">Estudiantes</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-content">
                    <h3 class="stat-value"><?= number_format($estadisticas['promedio_avance'] ?: 0, 0) ?>%</h3>
                    <p class="stat-label">Promedio Avance</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-content">
                    <h3 class="stat-value"><?= $estadisticas['certificados_emitidos'] ?: 0 ?></h3>
                    <p class="stat-label">Certificados</p>
                </div>
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
                <button class="action-btn" onclick="window.location.href='/imt-cursos/public/docente/admin_cursos.php'">
                    <img src="/imt-cursos/public/styles/iconos/addicon.png" alt="" style="width: 16px; height: 16px; filter: brightness(0) invert(1);">
                    Nuevo Curso
                </button>
                <button class="action-btn" onclick="window.location.href='/imt-cursos/public/docente/perfil.php'">
                    <img src="/imt-cursos/public/styles/iconos/edit.png" alt="" style="width: 16px; height: 16px; filter: brightness(0) invert(1);">
                    Editar Perfil
                </button>
                <button class="action-btn" onclick="window.location.href='/imt-cursos/public/docente/reportes.php'">
                    <img src="/imt-cursos/public/styles/iconos/detalles.png" alt="" style="width: 16px; height: 16px; filter: brightness(0) invert(1);">
                    Reportes
                </button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
