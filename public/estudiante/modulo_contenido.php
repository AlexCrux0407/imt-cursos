<?php

declare(strict_types=1);

/* Asegurar sesión y BASE_URL antes de usarla en redirects/rutas */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('BASE_URL')) {
    // Ajusta este valor a tu entorno. Para Laragon típico:
    define('BASE_URL', '/imt-cursos/public');
}

require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Estudiante – Contenido del Módulo';

$modulo_id     = (int)($_GET['id'] ?? 0);
$estudiante_id = (int)($_SESSION['user_id'] ?? 0);

if ($modulo_id === 0) {
    header('Location: ' . BASE_URL . '/estudiante/mis_cursos.php?error=modulo_no_especificado');
    exit;
}
if ($estudiante_id === 0) {
    header('Location: ' . BASE_URL . '/login.php?m=auth');
    exit;
}

/** Verificar acceso: módulo válido y estudiante inscrito en ese curso */
$stmt = $conn->prepare("
    SELECT m.id, m.titulo, m.descripcion, m.contenido, m.orden, m.curso_id,
           c.titulo AS curso_titulo, c.descripcion AS curso_descripcion
    FROM modulos m
    INNER JOIN cursos c ON m.curso_id = c.id
    INNER JOIN inscripciones i ON i.curso_id = c.id AND i.usuario_id = :uid
    WHERE m.id = :modulo_id
    LIMIT 1
");
$stmt->execute([':modulo_id' => $modulo_id, ':uid' => $estudiante_id]);
$modulo = $stmt->fetch();

// DEBUG: Verificar qué datos se están obteniendo
echo "<!-- DEBUG MODULO: ";
var_dump($modulo);
echo " -->";

if (!$modulo) {
    header('Location: ' . BASE_URL . '/estudiante/catalogo.php?error=acceso_denegado');
    exit;
}

/** Temas del módulo */
$stmt = $conn->prepare("
    SELECT t.*, COUNT(DISTINCT st.id) AS total_subtemas
    FROM temas t
    LEFT JOIN subtemas st ON t.id = st.tema_id
    WHERE t.modulo_id = :modulo_id
    GROUP BY t.id
    ORDER BY t.orden
");
$stmt->execute([':modulo_id' => $modulo_id]);
$temas = $stmt->fetchAll();

/** Lecciones directas del módulo (si tu esquema las usa así) */
$stmt = $conn->prepare("
    SELECT l.*
    FROM lecciones l
    WHERE l.modulo_id = :modulo_id
    ORDER BY l.orden
");
$stmt->execute([':modulo_id' => $modulo_id]);
$lecciones = $stmt->fetchAll();

/** Progreso del módulo (considerando lecciones por subtemas) */
$stmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT l.id) AS total_lecciones,
        COUNT(DISTINCT CASE WHEN pl.id IS NOT NULL THEN l.id END) AS lecciones_completadas
    FROM modulos m
    LEFT JOIN temas t      ON m.id = t.modulo_id
    LEFT JOIN subtemas st  ON t.id = st.tema_id
    LEFT JOIN lecciones l  ON st.id = l.subtema_id
    LEFT JOIN progreso_lecciones pl ON l.id = pl.leccion_id AND pl.usuario_id = :uid
    WHERE m.id = :modulo_id
");
$stmt->execute([':modulo_id' => $modulo_id, ':uid' => $estudiante_id]);
$progreso_modulo = $stmt->fetch() ?: ['total_lecciones' => 0, 'lecciones_completadas' => 0];

$porcentaje_completado = 0.0;
if ((int)$progreso_modulo['total_lecciones'] > 0) {
    $porcentaje_completado =
        ((int)$progreso_modulo['lecciones_completadas'] / (int)$progreso_modulo['total_lecciones']) * 100.0;
}
$porcentaje_completado = max(0, min(100, $porcentaje_completado));
$modulo_completado = $porcentaje_completado >= 100.0;

/** Siguiente módulo del curso */
$stmt = $conn->prepare("
    SELECT m.*
    FROM modulos m
    WHERE m.curso_id = :curso_id AND m.orden > :orden_actual
    ORDER BY m.orden ASC
    LIMIT 1
");
$stmt->execute([':curso_id' => $modulo['curso_id'], ':orden_actual' => $modulo['orden']]);
$siguiente_modulo = $stmt->fetch();

/** Estructura completa del curso (para la barra lateral) */
$stmt = $conn->prepare("
    SELECT m.id  AS modulo_id, m.titulo AS modulo_titulo, m.orden AS modulo_orden,
           t.id  AS tema_id,   t.titulo AS tema_titulo,   t.orden AS tema_orden,
           st.id AS subtema_id, st.titulo AS subtema_titulo, st.orden AS subtema_orden,
           l.id  AS leccion_id, l.titulo AS leccion_titulo, l.orden AS leccion_orden,
           IF(pl.id IS NULL, 0, 1) AS leccion_completada
    FROM modulos m
    LEFT JOIN temas t      ON m.id = t.modulo_id
    LEFT JOIN subtemas st  ON t.id = st.tema_id
    LEFT JOIN lecciones l  ON st.id = l.subtema_id
    LEFT JOIN progreso_lecciones pl
           ON l.id = pl.leccion_id AND pl.usuario_id = :uid
    WHERE m.curso_id = :curso_id
    ORDER BY m.orden, t.orden, st.orden, l.orden
");
$stmt->execute([':curso_id' => $modulo['curso_id'], ':uid' => $estudiante_id]);
$estructura_curso = $stmt->fetchAll();

/** Armar arreglo $curso_estructura para el sidebar */
$curso_estructura = [];
foreach ($estructura_curso as $row) {
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

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/estudiante.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/curso-sidebar.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/modulo-contenido.css">


<style id="mc-override">
    /* Colores consistentes en el bloque de Progreso del Módulo */
    .contenido-principal .progreso-modulo .progreso-header h3 {
        color: #2c3e50 !important;
        /* Título */
    }

    .contenido-principal .progreso-modulo .progreso-info {
        color: #34495e !important;
        /* "0 de 0 lecciones completadas" */
        font-weight: 600;
    }

    .contenido-principal .progreso-modulo .progress-label {
        color: #34495e !important;
        /* "Completado" debajo del círculo */
    }

    /* Forzar estilos visibles del CONTENIDO (no sidebar) */
    .contenido-principal .contenido-modulo .seccion-titulo {
        color: #3498db !important;
        border-bottom: 2px solid #e3f2fd !important;
    }

    .contenido-principal .contenido-modulo .contenido-texto {
        background: transparent !important;
        border-left: none !important;
        padding: 18px !important;
        border-radius: 10px !important;
    }

    .contenido-principal .contenido-modulo .tema-card,
    .contenido-principal .contenido-modulo .leccion-item {
        border: none !important;
        border-radius: 10px !important;
        padding: 16px !important;
        background: #fff !important;
    }
</style>
<div class="contenido-con-sidebar" style="display:flex; gap:30px;">
    <?php
    $cursoTituloSidebar = $modulo['curso_titulo'];
    $moduloActualId     = (int)$modulo['id'];
    include __DIR__ . '/partials/curso_sidebar.php';
    ?>

    <div class="contenido-principal" style="flex:1;">
        <!-- Header del curso -->
        <div class="modulo-header">
            <?php
            $cursoIdLink   = (int)($modulo['curso_id'] ?? 0);
            $cursoTituloBn = htmlspecialchars($modulo['curso_titulo'] ?? 'Curso', ENT_QUOTES, 'UTF-8');
            $hrefCurso     = BASE_URL . '/estudiante/curso_contenido.php?id=' . $cursoIdLink;
            ?>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>/estudiante/cursos_disponibles.php">Mis Cursos</a> →
                <a href="<?= $hrefCurso ?>"><?= $cursoTituloBn ?></a> →
                Contenido del Módulo
            </div>

            <h1 class="modulo-titulo"><?= htmlspecialchars($modulo['titulo'] ?? 'Módulo') ?></h1>
            <?php if (!empty($modulo['descripcion'])): ?>
                <p class="modulo-descripcion"><?= htmlspecialchars($modulo['descripcion']) ?></p>
            <?php endif; ?>
        </div>

        <div class="contenido-modulo">
            <!-- Contenido textual del módulo -->
            <?php if (!empty($modulo['contenido'])): ?>
                <div class="contenido-modulo-section">
                    <h2 class="seccion-titulo"><i class="icon-file-text"></i> Contenido del Módulo</h2>
                    <div class="contenido-texto">
                        <?= nl2br(htmlspecialchars($modulo['contenido'])) ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Temas -->
            <?php if (!empty($temas)): ?>
                <h2 class="seccion-titulo"><i class="icon-book"></i> Temas del Módulo</h2>
                <div class="temas-lista">
                    <?php foreach ($temas as $index => $tema): ?>
                        <div class="tema-card">
                            <div class="tema-header">
                                <div class="tema-numero"><?= (int)$index + 1 ?></div>
                                <a href="<?= BASE_URL ?>/estudiante/tema_contenido.php?id=<?= (int)$tema['id'] ?>" class="tema-titulo-link">
                                    <h3 class="tema-titulo"><?= htmlspecialchars($tema['titulo']) ?></h3>
                                </a>
                                <?php if ((int)$tema['total_subtemas'] > 0): ?>
                                    <span class="subtemas-count">
                                        <?= (int)$tema['total_subtemas'] ?> subtema<?= ((int)$tema['total_subtemas'] !== 1 ? 's' : '') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($tema['descripcion'])): ?>
                                <p class="tema-descripcion"><?= htmlspecialchars($tema['descripcion']) ?></p>
                            <?php endif; ?>
                            <div class="tema-acciones">
                                <?php if ((int)$tema['total_subtemas'] > 0): ?>
                                    <a class="btn-tema subtemas" href="<?= BASE_URL ?>/estudiante/subtemas.php?tema_id=<?= (int)$tema['id'] ?>">
                                        Ver Subtemas
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Lecciones directas -->
            <?php if (!empty($lecciones)): ?>
                <h2 class="seccion-titulo"><i class="icon-play"></i> Lecciones</h2>
                <div class="lecciones-lista">
                    <?php foreach ($lecciones as $leccion): ?>
                        <div class="leccion-item">
                            <div class="leccion-numero"><?= (int)$leccion['orden'] ?></div>
                            <div class="leccion-info">
                                <h4 class="leccion-titulo"><?= htmlspecialchars($leccion['titulo']) ?></h4>
                                <?php if (!empty($leccion['descripcion'])): ?>
                                    <p class="leccion-descripcion"><?= htmlspecialchars($leccion['descripcion']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="leccion-acciones">
                                <a class="btn-leccion" href="<?= BASE_URL ?>/estudiante/leccion.php?id=<?= (int)$leccion['id'] ?>">
                                    Estudiar
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Navegación al siguiente módulo / bloqueos / fin de curso -->
            <?php if ($modulo_completado && $siguiente_modulo): ?>
                <div class="navegacion-modulo">
                    <div class="modulo-completado">
                        <div class="completado-icon">✅</div>
                        <h3>¡Módulo Completado!</h3>
                        <p>Continúa con el siguiente módulo.</p>
                    </div>
                    <div class="siguiente-modulo">
                        <h4>Siguiente Módulo:</h4>
                        <div class="modulo-card">
                            <h5><?= htmlspecialchars($siguiente_modulo['titulo']) ?></h5>
                            <?php if (!empty($siguiente_modulo['descripcion'])): ?>
                                <p><?= htmlspecialchars(mb_substr((string)$siguiente_modulo['descripcion'], 0, 100)) ?>...</p>
                            <?php endif; ?>
                            <a class="btn-siguiente-modulo" href="<?= BASE_URL ?>/estudiante/modulo_contenido.php?id=<?= (int)$siguiente_modulo['id'] ?>">
                                Continuar →
                            </a>
                        </div>
                    </div>
                </div>
            <?php elseif ($siguiente_modulo && !$modulo_completado): ?>
                <div class="navegacion-bloqueada">
                    <div class="bloqueo-icon">🔒</div>
                    <h3>Siguiente Módulo Bloqueado</h3>
                    <p>Completa todas las lecciones de este módulo para desbloquear el siguiente.</p>
                    <div class="modulo-bloqueado">
                        <h5><?= htmlspecialchars($siguiente_modulo['titulo']) ?></h5>
                        <span class="btn-bloqueado">Módulo Bloqueado</span>
                    </div>
                </div>
            <?php elseif (!$siguiente_modulo && $modulo_completado): ?>
                <div class="curso-completado">
                    <div class="completado-icon">🎓</div>
                    <h3>¡Curso Completado!</h3>
                    <p>Has terminado exitosamente todos los módulos.</p>
                    <a class="btn-certificado" href="<?= BASE_URL ?>/estudiante/generar_certificado.php?curso_id=<?= (int)$modulo['curso_id'] ?>">
                        Obtener Certificado
                    </a>
                </div>
            <?php endif; ?>

            <!-- Estado vacío - solo mostrar si realmente no hay contenido -->
            <?php if (empty($temas) && empty($lecciones) && empty($modulo['contenido']) && empty(trim(strip_tags($modulo['contenido'] ?? '')))): ?>
                <div class="empty-content">
                    <i class="icon-info"></i>
                    <h3>Contenido en preparación</h3>
                    <p>El docente está preparando este módulo. Regresa pronto.</p>
                    <a class="btn-tema" style="margin-top: 12px;"
                        href="<?= BASE_URL ?>/estudiante/curso_contenido.php?id=<?= (int)$modulo['curso_id'] ?>">
                        ← Volver al Curso
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>