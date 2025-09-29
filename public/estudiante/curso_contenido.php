<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

// Definir BASE_URL si no está definido
if (!defined('BASE_URL')) {
    define('BASE_URL', '/imt-cursos/public');
}

$page_title = 'Estudiante – Contenido del Curso';

$curso_id      = (int)($_GET['id'] ?? 0);
$estudiante_id = (int)($_SESSION['user_id'] ?? 0);

if ($curso_id === 0) {
    header('Location: ' . BASE_URL . '/estudiante/mis_cursos.php?error=curso_no_especificado');
    exit;
}

// Verificar inscripción y obtener información del curso (UNA sola vez)
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

// Obtener módulos del curso con su progreso
$stmt = $conn->prepare("
    SELECT m.id, m.titulo, m.descripcion, m.orden,
           COUNT(l.id) as total_lecciones,
           COUNT(CASE WHEN pl.id IS NOT NULL THEN 1 END) as lecciones_completadas
    FROM modulos m
    LEFT JOIN temas t ON m.id = t.modulo_id
    LEFT JOIN lecciones l ON t.id = l.tema_id
    LEFT JOIN progreso_lecciones pl ON l.id = pl.leccion_id AND pl.usuario_id = :estudiante_id
    WHERE m.curso_id = :curso_id
    GROUP BY m.id, m.titulo, m.descripcion, m.orden
    ORDER BY m.orden
");
$stmt->execute([':curso_id' => $curso_id, ':estudiante_id' => $estudiante_id]);
$modulos = $stmt->fetchAll();

// Obtener evaluaciones del curso
$stmt = $conn->prepare("
    SELECT e.*, m.titulo as modulo_titulo,
           COUNT(ie.id) as intentos_realizados,
           MAX(ie.puntaje_obtenido) as mejor_calificacion
    FROM evaluaciones_modulo e
    LEFT JOIN modulos m ON e.modulo_id = m.id
    LEFT JOIN intentos_evaluacion ie ON e.id = ie.evaluacion_id AND ie.usuario_id = :estudiante_id
    WHERE m.curso_id = :curso_id AND e.activo = 1
    GROUP BY e.id
    ORDER BY e.orden
");
$stmt->execute([':curso_id' => $curso_id, ':estudiante_id' => $estudiante_id]);
$evaluaciones = $stmt->fetchAll();

// Estructura del curso para la sidebar
$stmt = $conn->prepare("
    SELECT m.id AS modulo_id, m.titulo AS modulo_titulo, m.orden AS modulo_orden,
           t.id AS tema_id, t.titulo AS tema_titulo, t.orden AS tema_orden,
           s.id AS subtema_id, s.titulo AS subtema_titulo, s.orden AS subtema_orden,
           l.id AS leccion_id, l.titulo AS leccion_titulo, l.orden AS leccion_orden,
           IF(pl.id IS NULL, 0, 1) AS leccion_completada
    FROM modulos m
    LEFT JOIN temas t ON m.id = t.modulo_id
    LEFT JOIN subtemas s ON t.id = s.tema_id
    LEFT JOIN lecciones l ON s.id = l.subtema_id
    LEFT JOIN progreso_lecciones pl ON l.id = pl.leccion_id AND pl.usuario_id = :estudiante_id
    WHERE m.curso_id = :curso_id
    ORDER BY m.orden, t.orden, s.orden, l.orden
");
$stmt->execute([':curso_id' => $curso_id, ':estudiante_id' => $estudiante_id]);
$rows = $stmt->fetchAll();

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

// Función para verificar si un módulo puede ser accedido
$__puedeAcceder = function($estructura, $moduloId) {
    $modulos = array_values($estructura);
    usort($modulos, fn($a, $b) => $a['orden'] <=> $b['orden']);

    foreach ($modulos as $i => $mod) {
        if ($mod['id'] == $moduloId) {
            if ($i == 0) return true; // Primer módulo siempre accesible
            $anterior = $modulos[$i - 1];
            $total = $anterior['total_lecciones'];
            $completadas = $anterior['lecciones_completadas'];
            return $total > 0 && $completadas >= $total;
        }
    }
    return false;
};

$cursoTituloSidebar = $curso['titulo'];

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/estudiante.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/curso-sidebar.css">

<div class="contenido-con-sidebar" style="display:flex; gap:30px;">
    <?php
    $moduloActualId = 0; // No hay módulo específico seleccionado
    include __DIR__ . '/partials/curso_sidebar.php';
    ?>

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
                <!-- Descripción -->
                <div class="collapsible-section sec-blue">
                    <div class="collapsible-header" onclick="toggleSection('descripcion')">
                        <h3>Descripción del curso</h3>
                        <span class="toggle-icon" id="icon-descripcion">▶</span>
                    </div>
                    <div class="collapsible-content" id="content-descripcion">
                        <div class="course-description">
                            <?= htmlspecialchars($curso['descripcion'] ?? '') ?>
                        </div>
                    </div>
                </div>

                <!-- Categoría -->
                <?php if (!empty($curso['categoria'])): ?>
                <div class="collapsible-section sec-purple">
                    <div class="collapsible-header" onclick="toggleSection('categoria')">
                        <h3>Categoría</h3>
                        <span class="toggle-icon" id="icon-categoria">▶</span>
                    </div>
                    <div class="collapsible-content" id="content-categoria">
                        <div class="course-category">
                            <?= htmlspecialchars($curso['categoria']) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Duración -->
                <?php if (!empty($curso['duracion'])): ?>
                <div class="collapsible-section sec-orange">
                    <div class="collapsible-header" onclick="toggleSection('duracion')">
                        <h3>Duración</h3>
                        <span class="toggle-icon" id="icon-duracion">▶</span>
                    </div>
                    <div class="collapsible-content" id="content-duracion">
                        <div class="course-duration">
                            <?= htmlspecialchars($curso['duracion']) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Dirigido a -->
                <?php if (!empty($curso['dirigido_a'])): ?>
                <div class="collapsible-section sec-teal">
                    <div class="collapsible-header" onclick="toggleSection('dirigido')">
                        <h3>Dirigido a</h3>
                        <span class="toggle-icon" id="icon-dirigido">▶</span>
                    </div>
                    <div class="collapsible-content" id="content-dirigido">
                        <div class="course-target">
                            <?= htmlspecialchars($curso['dirigido_a']) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Objetivo General -->
                <?php if (!empty($curso['objetivo_general'])): ?>
                <div class="collapsible-section sec-yellow">
                    <div class="collapsible-header" onclick="toggleSection('objetivo-general')">
                        <h3>Objetivo General</h3>
                        <span class="toggle-icon" id="icon-objetivo-general">▶</span>
                    </div>
                    <div class="collapsible-content" id="content-objetivo-general">
                        <div class="course-objective">
                            <?= nl2br(htmlspecialchars($curso['objetivo_general'])) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Objetivos Específicos -->
                <?php if (!empty($curso['objetivos_especificos'])): ?>
                <div class="collapsible-section sec-red">
                    <div class="collapsible-header" onclick="toggleSection('objetivos-especificos')">
                        <h3>Objetivos Específicos</h3>
                        <span class="toggle-icon" id="icon-objetivos-especificos">▶</span>
                    </div>
                    <div class="collapsible-content" id="content-objetivos-especificos">
                        <div class="course-specific-objectives">
                            <?= nl2br(htmlspecialchars($curso['objetivos_especificos'])) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Evaluaciones -->
                <?php if (!empty($evaluaciones)): ?>
                <div class="collapsible-section sec-slate">
                    <div class="collapsible-header" onclick="toggleSection('evaluaciones')">
                        <h3>Evaluaciones</h3>
                        <span class="toggle-icon" id="icon-evaluaciones">▶</span>
                    </div>
                    <div class="collapsible-content" id="content-evaluaciones">
                        <?php foreach ($evaluaciones as $evaluacion): ?>
                            <div class="evaluation-card">
                                <div class="evaluation-header">
                                    <h4><?= htmlspecialchars($evaluacion['titulo']) ?></h4>
                                    <?php if (!empty($evaluacion['modulo_titulo'])): ?>
                                        <span class="evaluation-module">Módulo: <?= htmlspecialchars($evaluacion['modulo_titulo']) ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($evaluacion['descripcion'])): ?>
                                    <p class="evaluation-description"><?= htmlspecialchars($evaluacion['descripcion']) ?></p>
                                <?php endif; ?>

                                <div class="evaluation-info">
                                    <div class="evaluation-stats">
                                        <span>⏱️ Tiempo límite: <?= (int)$evaluacion['tiempo_limite'] ?> minutos</span>
                                        <span>📊 Intentos realizados: <?= (int)$evaluacion['intentos_realizados'] ?></span>
                                        <?php if ($evaluacion['mejor_calificacion'] !== null): ?>
                                            <span>🏆 Mejor calificación: <?= number_format($evaluacion['mejor_calificacion'], 1) ?>%</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="evaluation-actions">
                                        <a href="<?= BASE_URL ?>/estudiante/tomar_evaluacion.php?id=<?= (int)$evaluacion['id'] ?>"
                                           class="btn btn-warning">
                                           <?= ((int)$evaluacion['intentos_realizados'] > 0) ? 'Reintentar' : 'Tomar' ?> evaluación
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleSection(sectionId) {
    const content = document.getElementById('content-' + sectionId);
    const icon = document.getElementById('icon-' + sectionId);
    const open = content.classList.toggle('show');
    if (icon) icon.textContent = open ? '▼' : '▶';
}

// Inicializar todas las secciones como colapsadas
document.addEventListener('DOMContentLoaded', function() {
    ['descripcion','categoria','duracion','dirigido','objetivo-general','objetivos-especificos','evaluaciones']
        .forEach(function(id){
            const content = document.getElementById('content-' + id);
            const icon = document.getElementById('icon-' + id);
            if (content) content.classList.remove('show');
            if (icon) icon.textContent = '▶';
        });
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>

<style>
/* ====== Header del curso ====== */
.course-header-student{
  background:linear-gradient(135deg,#3498db,#2980b9);
  color:#fff;
  padding:30px;
  border-radius:15px;
  margin-bottom:30px;
  display:flex;
  justify-content:space-between;
  align-items:center;
}
.progress-circle{ width:80px;height:80px;border-radius:50%;border:4px solid rgba(255,255,255,.3);background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center }
.progress-value{ font-size:1.2rem;font-weight:700 }
.progress-label{ text-align:center;margin-top:8px;opacity:.8 }
.alert-success{ background:#d4edda;color:#155724;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #c3e6cb }
.course-content-container{ background:#fff;border-radius:15px;padding:30px;box-shadow:0 5px 20px rgba(0,0,0,.08) }

/* ====== Tarjeta colapsable (UNA sola caja) ====== */
.collapsible-section{
  background:#fff;
  border:1px solid #e9ecef;
  border-left-width:4px;
  border-radius:8px;
  margin-bottom:20px;
  overflow:hidden;
}
.collapsible-header{
  padding:16px 20px;
  cursor:pointer;
  display:flex;
  justify-content:space-between;
  align-items:center;
  background:#fff;
  border-bottom:1px solid #e9ecef;
}
.collapsible-header:hover{ background:#f8f9fa; }
.toggle-icon{ font-size:.9rem;color:#6c757d; }
.collapsible-content{
  max-height:0;
  padding:0 20px;
  overflow:hidden;
  transition:max-height .3s ease, padding .3s ease;
}
.collapsible-content.show{
  max-height:999px;
  padding:20px;
}

/* Modificadores de color (borde izquierdo) */
.sec-blue   { border-left-color:#3498db; }
.sec-purple { border-left-color:#9b59b6; }
.sec-orange { border-left-color:#e67e22; }
.sec-teal   { border-left-color:#1abc9c; }
.sec-yellow { border-left-color:#f39c12; }
.sec-red    { border-left-color:#e74c3c; }
.sec-slate  { border-left-color:#607d8b; }

/* Otros estilos existentes (mantenidos) */
.modules-list{ display:grid; gap:20px }
.module-card{ border:2px solid #e8ecef;border-radius:12px;padding:25px;transition:.3s }
.module-card:hover{ border-color:#3498db; transform:translateY(-2px); box-shadow:0 8px 25px rgba(52,152,219,.15) }
.module-card.locked{ opacity:.7; background:#f8f9fa; border-left:4px solid #adb5bd }
.module-card.completed{ border-left:4px solid #10b981 }
.module-locked-message{ color:#6c757d;font-style:italic;font-size:.9rem }
.btn-mark-complete{ background:#f39c12;color:#fff;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:.9rem;margin-left:10px }
.btn-mark-complete:hover{ background:#e67e22 }
.module-header{ display:flex;justify-content:space-between;align-items:center;margin-bottom:15px }
.module-title{ color:#2c3e50;font-size:1.3rem;font-weight:600;margin:0 }
.module-description{ color:#7f8c8d;margin-bottom:20px;line-height:1.5 }
.btn-start-module{ background:#3498db;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:500;display:inline-block }
.btn-start-module:hover{ background:#2980b9;transform:translateY(-1px) }
</style>
