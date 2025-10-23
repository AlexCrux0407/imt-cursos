<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Estudiante – Dashboard';

$estudiante_id = $_SESSION['user_id'];

// Obtener estadísticas del estudiante
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT i.curso_id) as cursos_inscritos,
        COUNT(DISTINCT CASE WHEN i.estado = 'completado' THEN i.curso_id END) as cursos_completados,
        COUNT(DISTINCT CASE WHEN i.estado = 'completado' THEN i.id END) as certificados_obtenidos
    FROM inscripciones i
    WHERE i.usuario_id = :estudiante_id
");
$stmt->execute([':estudiante_id' => $estudiante_id]);
$estadisticas = $stmt->fetch();

// Obtener total de cursos disponibles
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT c.id) as cursos_disponibles
    FROM cursos c
    WHERE c.estado = 'activo'
");
$stmt->execute();
$cursos_disponibles = $stmt->fetch()['cursos_disponibles'];

// Obtener cursos en progreso del estudiante
$stmt = $conn->prepare("
    SELECT c.*, i.progreso, i.fecha_inscripcion, i.estado,
           u.nombre as docente_nombre
    FROM inscripciones i
    INNER JOIN cursos c ON i.curso_id = c.id
    INNER JOIN usuarios u ON c.creado_por = u.id
    WHERE i.usuario_id = :estudiante_id AND i.estado != 'completado'
    ORDER BY i.fecha_inscripcion DESC
    LIMIT 3
");
$stmt->execute([':estudiante_id' => $estudiante_id]);
$cursos_progreso = $stmt->fetchAll();

// Obtener cursos completados recientes
$stmt = $conn->prepare("
    SELECT c.*, i.progreso, i.fecha_inscripcion, i.fecha_completado,
           u.nombre as docente_nombre
    FROM inscripciones i
    INNER JOIN cursos c ON i.curso_id = c.id
    INNER JOIN usuarios u ON c.creado_por = u.id
    WHERE i.usuario_id = :estudiante_id AND i.estado = 'completado'
    ORDER BY i.fecha_completado DESC
    LIMIT 3
");
$stmt->execute([':estudiante_id' => $estudiante_id]);
$cursos_completados = $stmt->fetchAll();

// Obtener actividad reciente (próximas lecciones o recomendaciones)
$stmt = $conn->prepare("
    SELECT c.titulo as curso_titulo, c.id as curso_id, i.progreso, i.fecha_inscripcion
    FROM inscripciones i
    INNER JOIN cursos c ON i.curso_id = c.id
    WHERE i.usuario_id = :estudiante_id AND i.estado = 'activo'
    ORDER BY i.fecha_inscripcion DESC
    LIMIT 5
");
$stmt->execute([':estudiante_id' => $estudiante_id]);
$actividad_reciente = $stmt->fetchAll();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/estudiante.css">

<style>
/* Animaciones de entrada */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Dashboard del estudiante */
.student-dashboard {
    animation: fadeInUp 0.8s ease-out;
}

.student-welcome {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    padding: 40px 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    text-align: center;
}

.welcome-title {
    font-size: 2.2rem;
    font-weight: 600;
    margin-bottom: 10px;
}

.welcome-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
}

/* Estadísticas */
.stats-overview {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
    margin-bottom: 40px;
}

.stat-card {
    background: #fff;
    color: #2c3e50;
    padding: 22px 20px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    border-left: 4px solid var(--estudiante-primary, #3498db);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    display: block;
    text-decoration: none;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}

.stat-card .stat-value { color: #2c3e50; }
.stat-card .stat-label { color: #7f8c8d; opacity: 1; }

.stat-value {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 5px;
    line-height: 1;
}

.stat-label {
    font-size: 0.95rem;
    opacity: 0.9;
    margin: 0;
    font-weight: 500;
}

/* Secciones */
.dashboard-section {
    background: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.section-title {
    color: #2c3e50;
    font-size: 1.5rem;
    margin: 0;
}

.section-link {
    color: #3498db;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s ease;
}

.section-link:hover {
    color: #2980b9;
}

/* Grid de cursos */
.courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.course-card {
    border: 2px solid #e8ecef;
    border-radius: 12px;
    padding: 20px;
    background: #fafbfc;
    transition: all 0.3s ease;
}

.course-card:hover {
    border-color: #3498db;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(52, 152, 219, 0.15);
}

.course-header {
    margin-bottom: 15px;
}

.course-title {
    color: #2c3e50;
    margin-bottom: 5px;
    font-size: 1.1rem;
}

.course-instructor {
    color: #7f8c8d;
    font-size: 0.9rem;
    font-style: italic;
}

.course-description {
    color: #5a5c69;
    margin-bottom: 15px;
    line-height: 1.5;
}

/* Estado vacío */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #7f8c8d;
}

.empty-state h4 {
    color: #2c3e50;
    margin-bottom: 10px;
}

.empty-state p {
    margin-bottom: 20px;
}

.btn-primary {
    background: #3498db;
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: #2980b9;
    transform: translateY(-1px);
}

/* Botones de acción */
.btn-action {
    background: #3498db;
    color: white;
    padding: 10px 20px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    display: inline-block;
    transition: all 0.3s ease;
}

.btn-action:hover {
    background: #2980b9;
    transform: translateY(-1px);
}

/* Acciones rápidas */
.quick-actions {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}

.quick-actions h3 {
    color: #2c3e50;
    margin-bottom: 20px;
}

.action-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.action-btn {
    background: #3498db;
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.action-btn:hover {
    background: #2980b9;
    transform: translateY(-1px);
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-overview {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-overview {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .welcome-title {
        font-size: 1.8rem;
    }
    
    .courses-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}
/* Hero inmersivo (diseño alternativo: grid con tarjeta de video) */
.welcome-hero { border-radius: 18px; overflow: hidden; margin-bottom: 30px; background: linear-gradient(135deg, var(--estudiante-primary, #3498db), var(--estudiante-secondary, #2980b9)); box-shadow: 0 15px 30px rgba(0,0,0,0.08); }
.hero-grid { display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 22px; align-items: center; padding: clamp(24px, 5vw, 48px); }
.hero-left { color: #fff; }
.hero-title { font-size: clamp(2rem, 4.2vw, 2.6rem); font-weight: 800; margin-bottom: 10px; letter-spacing: 0.2px; }
.hero-subtitle { font-size: clamp(1rem, 2.1vw, 1.15rem); opacity: 0.95; }
.hero-actions { margin-top: 18px; display: inline-flex; gap: 12px; }
.hero-btn { background: rgba(255,255,255,0.18); color: #fff; border: 1px solid rgba(255,255,255,0.35); padding: 10px 16px; border-radius: 10px; cursor: pointer; backdrop-filter: blur(4px); transition: all .2s; }
.hero-btn:hover { background: rgba(255,255,255,0.26); transform: translateY(-1px); }

.hero-card { position: relative; border-radius: 14px; overflow: hidden; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.22); box-shadow: 0 10px 24px rgba(0,0,0,0.12); }
.hero-card video { display: block; width: 100%; height: auto; }
.hero-card .card-overlay { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,0.05), rgba(0,0,0,0.35)); pointer-events: none; }
.hero-card .card-controls { position: absolute; bottom: 10px; left: 10px; display: flex; gap: 8px; z-index: 2; }
.hero-card .card-btn { background: rgba(0,0,0,0.45); color: #fff; border: 1px solid rgba(255,255,255,0.35); padding: 6px 10px; border-radius: 8px; cursor: pointer; font-size: 0.9rem; transition: all .2s; }
.hero-card .card-btn:hover { background: rgba(0,0,0,0.6); }

@media (max-width: 900px) {
  .hero-grid { grid-template-columns: 1fr; padding: 22px; }
}
</style>

<div class="student-dashboard">
    <?php
    // Video de bienvenida: detecta y arma URL
    $welcome_video_filename = 'bienvenida.mp4';
    $welcome_video_path = PUBLIC_PATH . '/uploads/media/' . $welcome_video_filename;
    $welcome_video_url  = BASE_URL . '/uploads/media/' . $welcome_video_filename;
    $has_welcome_video  = file_exists($welcome_video_path);
    ?>

    <div class="welcome-hero">
      <div class="hero-grid">
        <div class="hero-left">
          <h1 class="hero-title">¡Hola, <?= htmlspecialchars($_SESSION['nombre']) ?>!</h1>
          <p class="hero-subtitle">Impulsa tu aprendizaje: retoma tu último curso y explora nuevas rutas.</p>
          <div class="hero-actions">
            <a href="<?= BASE_URL ?>/estudiante/mis_cursos.php" class="hero-btn">Continuar aprendizaje</a>
            <a href="<?= BASE_URL ?>/estudiante/catalogo.php" class="hero-btn">Explorar cursos</a>
          </div>
        </div>

        <?php if ($has_welcome_video): ?>
        <div class="hero-card" id="heroCard">
          <video id="heroVideo" controls autoplay muted playsinline preload="metadata">
            <source src="<?= htmlspecialchars($welcome_video_url) ?>" type="video/mp4">
          </video>
          <div class="card-overlay"></div>

        </div>
        <?php else: ?>
        <div class="hero-card" style="display:flex; align-items:center; justify-content:center; min-height: 200px;">
          <span style="color:#fff; opacity:0.85; padding: 12px;">Sube tu video bienvenida.mp4 en uploads/media para activar el hero</span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($has_welcome_video): ?>
    <?php endif; ?>

    <!-- Estadísticas principales -->
    <div class="stats-overview">
        <a class="stat-card" href="<?= BASE_URL ?>/estudiante/catalogo.php">
            <div class="stat-content">
                <h3 class="stat-value"><?= $cursos_disponibles ?: 0 ?></h3>
                <p class="stat-label">Cursos Disponibles</p>
            </div>
        </a>

        <a class="stat-card" href="<?= BASE_URL ?>/estudiante/mis_cursos.php">
            <div class="stat-content">
                <h3 class="stat-value"><?= $estadisticas['cursos_inscritos'] ?: 0 ?></h3>
                <p class="stat-label">Cursos Inscritos</p>
            </div>
        </a>

        <a class="stat-card" href="<?= BASE_URL ?>/estudiante/cursos_completados.php">
            <div class="stat-content">
                <h3 class="stat-value"><?= $estadisticas['cursos_completados'] ?: 0 ?></h3>
                <p class="stat-label">Cursos Completados</p>
            </div>
        </a>

        <a class="stat-card" href="<?= BASE_URL ?>/estudiante/cursos_completados.php">
            <div class="stat-content">
                <h3 class="stat-value"><?= $estadisticas['certificados_obtenidos'] ?: 0 ?></h3>
                <p class="stat-label">Certificados</p>
            </div>
        </a>
    </div>

    <!-- Recomendaciones o actividad reciente -->
    <div class="dashboard-section">
        <div class="section-header">
            <h2 class="section-title">Actividad Reciente</h2>
        </div>
        
        <?php if (empty($actividad_reciente)): ?>
            <div class="empty-state">
                <img src="<?= BASE_URL ?>/styles/iconos/entrada.png" style="width: 48px; height: 48px; opacity: 0.3; margin-bottom: 15px;">
                <h4>No hay actividad reciente</h4>
                <p>Tu actividad de aprendizaje aparecerá aquí</p>
            </div>
        <?php else: ?>
            <div class="activity-list">
                <?php foreach ($actividad_reciente as $actividad): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <img src="<?= BASE_URL ?>/styles/iconos/desk.png" style="width: 16px; height: 16px;">
                        </div>
                        <div class="activity-content">
                            <p class="activity-text">
                                Inscrito en <strong><?= htmlspecialchars($actividad['curso_titulo']) ?></strong>
                            </p>
                            <div class="activity-meta">
                                <span class="activity-progress">Progreso: <?= number_format($actividad['progreso'], 1) ?>%</span>
                                <span class="activity-date"><?= date('d/m/Y', strtotime($actividad['fecha_inscripcion'])) ?></span>
                            </div>
                        </div>
                        <div class="activity-action">
                            <a href="<?= BASE_URL ?>/estudiante/curso_contenido.php?id=<?= $actividad['curso_id'] ?>" class="btn-small">Continuar</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
