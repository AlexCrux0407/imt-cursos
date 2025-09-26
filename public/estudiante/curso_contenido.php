<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';



$page_title = 'Estudiante – Contenido del Curso';

$curso_id      = (int)($_GET['id'] ?? 0);
$estudiante_id = (int)($_SESSION['user_id'] ?? 0);

if ($curso_id === 0) {
    header('Location: ' . BASE_URL . '/estudiante/mis_cursos.php?error=curso_no_especificado');
    exit;
}

// Verificar inscripción y obtener información del curso
$stmt = $conn->prepare("
    SELECT c.*, i.progreso, i.fecha_inscripcion, i.estado AS estado_inscripcion,
           u.nombre AS docente_nombre
    FROM cursos c
    INNER JOIN inscripciones i ON c.id = i.curso_id
    LEFT JOIN usuarios u ON c.asignado_a = u.id
    WHERE c.id = :curso_id AND i.usuario_id = :estudiante_id
    LIMIT 1
");
$stmt->execute([':curso_id' => $curso_id, ':estudiante_id' => $estudiante_id]);
$curso = $stmt->fetch();

if (!$curso) {
    header('Location: ' . BASE_URL . '/estudiante/catalogo.php?error=no_inscrito');
    exit;
}

// Módulos (tarjetas de la derecha)
$stmt = $conn->prepare("
    SELECT m.*, COUNT(DISTINCT t.id) AS total_temas
    FROM modulos m
    LEFT JOIN temas t ON m.id = t.modulo_id
    WHERE m.curso_id = :curso_id
    GROUP BY m.id
    ORDER BY m.orden
");
$stmt->execute([':curso_id' => $curso_id]);
$modulos = $stmt->fetchAll();

// Estructura completa del curso (para la SIDEBAR)
$stmt = $conn->prepare("
    SELECT m.id AS modulo_id, m.titulo AS modulo_titulo, m.orden AS modulo_orden,
           t.id AS tema_id, t.titulo AS tema_titulo, t.orden AS tema_orden,
           st.id AS subtema_id, st.titulo AS subtema_titulo, st.orden AS subtema_orden,
           l.id AS leccion_id, l.titulo AS leccion_titulo, l.orden AS leccion_orden,
           IF(pl.id IS NULL, 0, 1) AS leccion_completada
    FROM modulos m
    LEFT JOIN temas t ON m.id = t.modulo_id
    LEFT JOIN subtemas st ON t.id = st.tema_id
    LEFT JOIN lecciones l ON st.id = l.subtema_id
    LEFT JOIN progreso_lecciones pl
           ON l.id = pl.leccion_id AND pl.usuario_id = :estudiante_id
    WHERE m.curso_id = :curso_id
    ORDER BY m.orden, t.orden, st.orden, l.orden
");
$stmt->execute([':curso_id' => $curso_id, ':estudiante_id' => $estudiante_id]);
$rows = $stmt->fetchAll();

// Armar $curso_estructura para el sidebar
$curso_estructura = [];
foreach ($rows as $row) {
    $mid = (int)$row['modulo_id'];
    if (!isset($curso_estructura[$mid])) {
        $curso_estructura[$mid] = [
            'id' => $mid,
            'titulo' => $row['modulo_titulo'],
            'orden' => (int)$row['modulo_orden'],
            'temas' => [],
            'total_lecciones' => 0,
            'lecciones_completadas' => 0
        ];
    }
    if (!empty($row['tema_id'])) {
        $tid = (int)$row['tema_id'];
        if (!isset($curso_estructura[$mid]['temas'][$tid])) {
            $curso_estructura[$mid]['temas'][$tid] = [
                'id' => $tid,
                'titulo' => $row['tema_titulo'],
                'orden' => (int)$row['tema_orden'],
                'subtemas' => []
            ];
        }
        if (!empty($row['subtema_id'])) {
            $sid = (int)$row['subtema_id'];
            if (!isset($curso_estructura[$mid]['temas'][$tid]['subtemas'][$sid])) {
                $curso_estructura[$mid]['temas'][$tid]['subtemas'][$sid] = [
                    'id' => $sid,
                    'titulo' => $row['subtema_titulo'],
                    'orden' => (int)$row['subtema_orden'],
                    'lecciones' => []
                ];
            }
            if (!empty($row['leccion_id'])) {
                $curso_estructura[$mid]['temas'][$tid]['subtemas'][$sid]['lecciones'][] = [
                    'id' => (int)$row['leccion_id'],
                    'titulo' => $row['leccion_titulo'],
                    'orden' => (int)$row['leccion_orden'],
                    'completada' => (bool)$row['leccion_completada']
                ];
                $curso_estructura[$mid]['total_lecciones']++;
                if ($row['leccion_completada']) {
                    $curso_estructura[$mid]['lecciones_completadas']++;
                }
            }
        }
    }
}

$cursoTituloSidebar = $curso['titulo'];
$moduloActualId     = 0;

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/estudiante.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/curso-sidebar.css">

<div class="contenido-con-sidebar" style="display:flex; gap:30px;">
    <?php
    $cursoTituloSidebar = $curso['titulo'];
    $moduloActualId     = 0;
    include __DIR__ . '/partials/curso_sidebar.php'; 
    ?>

    <!-- Contenido principal -->
    <div class="contenido-principal" style="flex:1;">
        <!-- Header del curso -->
        <div class="course-header-student">
            <div class="course-info">
                <h1 class="course-title"><?= htmlspecialchars($curso['titulo']) ?></h1>
                <?php if (!empty($curso['docente_nombre'])): ?>
                    <p class="course-instructor">Por <?= htmlspecialchars($curso['docente_nombre']) ?></p>
                <?php endif; ?>
            </div>
            <div class="course-progress">
                <div class="progress-circle">
                    <span class="progress-value"><?= number_format((float)$curso['progreso'], 0) ?>%</span>
                </div>
                <small class="progress-label">Completado</small>
            </div>
        </div>

        <?php if (isset($_GET['success']) && $_GET['success'] === 'inscripcion_exitosa'): ?>
            <div class="alert-success">
                ¡Te has inscrito exitosamente en el curso! Ahora puedes comenzar tu aprendizaje.
            </div>
        <?php endif; ?>

        <div class="course-content-container">
            <?php if (empty($curso['descripcion'] ?? '')): ?>
                <div class="empty-state">
                    <img src="<?= BASE_URL ?>/styles/iconos/desk.png" style="width:64px;height:64px;opacity:.5;margin-bottom:20px;">
                    <h3>Información del curso en preparación</h3>
                    <p>El docente está preparando la información de este curso. Regresa pronto.</p>
                </div>
            <?php else: ?>
                <div class="course-info-container">
                    <div class="contenido-texto">
                        <h2>Descripción del curso</h2>
                        <div class="course-description">
                            <?= nl2br(htmlspecialchars($curso['descripcion'] ?? '')) ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($curso['objetivo_general'])): ?>
                    <div class="contenido-texto" style="margin-top: 25px; border-left: 4px solid #27ae60;">
                        <h2>Objetivo general</h2>
                        <div class="course-objective">
                            <?= nl2br(htmlspecialchars($curso['objetivo_general'] ?? '')) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($curso['objetivos_especificos'])): ?>
                    <div class="contenido-texto" style="margin-top: 25px; border-left: 4px solid #f39c12;">
                        <h2>Objetivos específicos</h2>
                        <div class="course-specific-objectives">
                            <?= nl2br(htmlspecialchars($curso['objetivos_especificos'] ?? '')) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($curso['duracion'])): ?>
                    <div class="contenido-texto" style="margin-top: 25px; border-left: 4px solid #9b59b6;">
                        <h2>Duración</h2>
                        <div class="course-duration">
                            <?= htmlspecialchars($curso['duracion'] ?? '') ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($curso['dirigido_a'])): ?>
                    <div class="contenido-texto" style="margin-top: 25px; border-left: 4px solid #e74c3c;">
                        <h2>Dirigido a</h2>
                        <div class="course-target-audience">
                            <?= nl2br(htmlspecialchars($curso['dirigido_a'] ?? '')) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Script para otras funcionalidades que puedan ser necesarias
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
}
</script>

<style>
    .course-header-student {
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: #fff;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .module-card.locked {
        opacity: 0.7;
        background-color: #f8f9fa;
        border-left: 4px solid #adb5bd;
    }
    
    .module-card.completed {
        border-left: 4px solid #10b981;
    }
    
    .module-locked-message {
        color: #6c757d;
        font-style: italic;
        font-size: 0.9rem;
    }
    
    .btn-mark-complete {
        background: #f39c12;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9rem;
        margin-left: 10px;
    }
    
    .btn-mark-complete:hover {
        background: #e67e22;
    }

    .progress-circle {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        border: 4px solid rgba(255, 255, 255, .3);
        background: rgba(255, 255, 255, .1);
        display: flex;
        align-items: center;
        justify-content: center
    }

    .progress-value {
        font-size: 1.2rem;
        font-weight: 700
    }

    .progress-label {
        text-align: center;
        margin-top: 8px;
        opacity: .8
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        border: 1px solid #c3e6cb
    }

    .course-content-container {
        background: #fff;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, .08)
    }

    .modules-list {
        display: grid;
        gap: 20px
    }

    .module-card {
        border: 2px solid #e8ecef;
        border-radius: 12px;
        padding: 25px;
        transition: .3s
    }

    .module-card:hover {
        border-color: #3498db;
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(52, 152, 219, .15)
    }

    .module-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px
    }

    .module-title {
        color: #2c3e50;
        font-size: 1.3rem;
        font-weight: 600;
        margin: 0
    }

    .module-topics {
        background: #3498db;
        color: #fff;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: .8rem;
        font-weight: 500
    }

    .module-description {
        color: #7f8c8d;
        margin-bottom: 20px;
        line-height: 1.5
    }

    .btn-start-module {
        background: #3498db;
        color: #fff;
        padding: 12px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 500;
        display: inline-block
    }

    .btn-start-module:hover {
        background: #2980b9;
        transform: translateY(-1px)
    }
</style>

<?php require __DIR__ . '/../partials/footer.php'; ?>