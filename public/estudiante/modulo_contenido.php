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

if (!$modulo) {
    header('Location: ' . BASE_URL . '/estudiante/catalogo.php?error=acceso_denegado');
    exit;
}

/** Control de acceso: solo permitir si el módulo anterior tiene evaluación aprobada */
$stmt = $conn->prepare("\n    SELECT m.id, m.orden,\n           IF(pm.evaluacion_completada = 1, 1, 0) AS evaluacion_completada\n    FROM modulos m\n    LEFT JOIN progreso_modulos pm\n      ON pm.modulo_id = m.id AND pm.usuario_id = :uid\n    WHERE m.curso_id = :curso_id\n    ORDER BY m.orden ASC\n");
$stmt->execute([':curso_id' => $modulo['curso_id'], ':uid' => $estudiante_id]);
$modsAcceso = $stmt->fetchAll();

$accesoPermitido = true;
$idxActual = -1;
foreach ($modsAcceso as $i => $mrow) {
    if ((int)$mrow['id'] === (int)$modulo['id']) { $idxActual = $i; break; }
}
if ($idxActual > 0) {
    $prevEvalCompletada = (bool)$modsAcceso[$idxActual - 1]['evaluacion_completada'];
    $accesoPermitido = $prevEvalCompletada;
}
if (!$accesoPermitido) {
    header('Location: ' . BASE_URL . '/estudiante/curso_contenido.php?id=' . (int)$modulo['curso_id'] . '&error=modulo_bloqueado');
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

// Si la evaluación del módulo fue aprobada, considerar el módulo como completado
$stmt = $conn->prepare("
    SELECT evaluacion_completada, completado
    FROM progreso_modulos
    WHERE usuario_id = :uid AND modulo_id = :modulo_id
");
$stmt->execute([':uid' => $estudiante_id, ':modulo_id' => $modulo_id]);
$pm = $stmt->fetch();
// Determinar si el módulo tiene evaluaciones activas
$stmt = $conn->prepare("\n    SELECT COUNT(*) AS total\n    FROM evaluaciones_modulo\n    WHERE modulo_id = :modulo_id AND activo = 1\n");
$stmt->execute([':modulo_id' => $modulo_id]);
$tiene_eval = ((int)($stmt->fetch()['total'] ?? 0)) > 0;

// Regla: si el módulo tiene evaluaciones, solo se considera completado cuando TODAS están aprobadas
if ($tiene_eval) {
    $modulo_completado = ($pm && (int)$pm['evaluacion_completada'] === 1);
    if ($modulo_completado) {
        $porcentaje_completado = 100.0;
    }
} else {
    // Sin evaluaciones: se puede usar el progreso de lecciones o flag de completado
    if ($pm && (int)$pm['completado'] === 1) {
        $modulo_completado = true;
        $porcentaje_completado = 100.0;
    }
}

/** Evaluaciones del módulo actual */
$stmt = $conn->prepare("
    SELECT e.*, 
           COUNT(ie.id) as intentos_realizados,
           MAX(ie.puntaje_obtenido) as mejor_calificacion,
           CASE 
               WHEN MAX(ie.puntaje_obtenido) >= e.puntaje_minimo_aprobacion THEN 1 
               ELSE 0 
           END as aprobada,
           CASE 
               WHEN e.intentos_permitidos > 0 AND COUNT(ie.id) >= e.intentos_permitidos 
                    AND (MAX(ie.puntaje_obtenido) < e.puntaje_minimo_aprobacion OR MAX(ie.puntaje_obtenido) IS NULL) THEN 1
               ELSE 0 
           END as sin_intentos
    FROM evaluaciones_modulo e
    LEFT JOIN intentos_evaluacion ie ON e.id = ie.evaluacion_id AND ie.usuario_id = :estudiante_id
    WHERE e.modulo_id = :modulo_id AND e.activo = 1
    GROUP BY e.id
    ORDER BY e.orden
");
$stmt->execute([':modulo_id' => $modulo_id, ':estudiante_id' => $estudiante_id]);
$evaluaciones_modulo = $stmt->fetchAll();

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

/** Obtener información de progreso de módulos para el sidebar */
$stmt = $conn->prepare("
    SELECT m.id, 
           IF(pm.evaluacion_completada = 1, 1, 0) AS evaluacion_completada
    FROM modulos m
    LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = :uid
    WHERE m.curso_id = :curso_id
");
$stmt->execute([':curso_id' => $modulo['curso_id'], ':uid' => $estudiante_id]);
$progreso_modulos_info = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Convertir a array asociativo para fácil acceso
$progreso_modulos_map = [];
foreach ($progreso_modulos_info as $mod_id => $eval_completada) {
    $progreso_modulos_map[$mod_id] = $eval_completada;
}

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
            'lecciones_completadas' => 0,
            'evaluacion_completada' => isset($progreso_modulos_map[$mid]) ? (bool)$progreso_modulos_map[$mid] : false
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

    /* Corregir cursor en header principal - no debe ser clickeable */
    .contenido-principal .modulo-header {
        cursor: default !important;
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

    /* Estilos específicos para evaluaciones */
    .evaluacion-card {
        border-left: 4px solid #e74c3c !important;
        transition: all 0.3s ease;
    }

    .evaluacion-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
    }

    .evaluacion-numero {
        background: linear-gradient(135deg, #e74c3c, #c0392b) !important;
        color: white !important;
    }

    .evaluacion-titulo {
        color: #2c3e50 !important;
        font-weight: 600 !important;
    }

    .evaluacion-stats {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 8px;
    }

    .stat-item {
        background: #f8f9fa;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.85em;
        font-weight: 500;
        color: #6c757d;
    }

    .stat-item.intentos {
        background: #f3e5f5;
        color: #7b1fa2;
    }

    .stat-item.score.aprobada {
        background: #e8f5e8;
        color: #2e7d32;
        font-weight: 600;
    }

    .stat-item.score.reprobada {
        background: #ffebee;
        color: #c62828;
        font-weight: 600;
    }

    /* === ESTILOS MODERNOS PARA EVALUACIONES === */
    .evaluacion-card-moderna {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        border: 1px solid #e8ecf4;
        margin-bottom: 24px;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .evaluacion-card-moderna:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    }

    .evaluacion-header {
        display: flex;
        align-items: flex-start;
        gap: 20px;
        padding: 24px;
        background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        border-bottom: 1px solid #e2e8f0;
    }

    .evaluacion-badge {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        color: white;
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.1rem;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        flex-shrink: 0;
    }

    .evaluacion-info {
        flex: 1;
        min-width: 0;
    }

    .evaluacion-titulo-moderno {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 8px 0;
        line-height: 1.3;
    }

    .evaluacion-descripcion-moderna {
        color: #64748b;
        font-size: 0.95rem;
        line-height: 1.5;
        margin: 0;
    }

    .evaluacion-estado {
        flex-shrink: 0;
    }

    .estado-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .estado-badge.aprobada {
        background: linear-gradient(135deg, #dcfce7, #bbf7d0);
        color: #166534;
        border: 1px solid #86efac;
    }

    .estado-badge.sin-intentos {
        background: linear-gradient(135deg, #fef2f2, #fecaca);
        color: #991b1b;
        border: 1px solid #fca5a5;
    }

    .estado-badge.disponible {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: #92400e;
        border: 1px solid #fcd34d;
    }

    .evaluacion-stats-moderna {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        padding: 20px 24px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .stat-moderna {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background: white;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
    }

    .stat-moderna i {
        font-size: 1.2rem;
        color: #3b82f6;
        width: 20px;
        text-align: center;
    }

    .stat-content {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .stat-label {
        font-size: 0.75rem;
        color: #64748b;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-value {
        font-size: 0.95rem;
        font-weight: 600;
        color: #1e293b;
    }

    .stat-value.aprobada {
        color: #059669;
    }

    .stat-value.reprobada {
        color: #dc2626;
    }

    .evaluacion-acciones-moderna {
        padding: 20px 24px;
        background: white;
    }

    .btn-evaluacion {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 14px 24px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.95rem;
        text-decoration: none;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        width: 100%;
        justify-content: center;
        text-align: center;
    }

    .btn-evaluacion.aprobada {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        cursor: default;
    }

    .btn-evaluacion.sin-intentos {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        cursor: default;
    }

    .btn-evaluacion.disponible {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }

    .btn-evaluacion.disponible:hover {
        background: linear-gradient(135deg, #d97706, #b45309);
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
    }

    .btn-evaluacion i {
        font-size: 1.1rem;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .evaluacion-header {
            flex-direction: column;
            gap: 16px;
        }
        
        .evaluacion-stats-moderna {
            grid-template-columns: 1fr;
        }
        
        .evaluacion-badge {
            align-self: flex-start;
        }
    }

    /* Estilos antiguos para compatibilidad */
    .btn-tema.evaluacion {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        border-radius: 8px;
        padding: 10px 16px;
    }

    .btn-tema.evaluacion.aprobada {
        background: linear-gradient(135deg, #27ae60, #2ecc71) !important;
        color: white !important;
        cursor: default;
        pointer-events: none;
    }

    .btn-tema.evaluacion.sin-intentos {
        background: linear-gradient(135deg, #e74c3c, #c0392b) !important;
        color: white !important;
        cursor: default;
        pointer-events: none;
    }

    .btn-tema.evaluacion.disponible {
        background: linear-gradient(135deg, #f39c12, #e67e22) !important;
        color: white !important;
    }

    .btn-tema.evaluacion.disponible:hover {
        background: linear-gradient(135deg, #e67e22, #d35400) !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }

    .tema-titulo-container {
        flex: 1;
        margin-left: 12px;
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
                        <?= $modulo['contenido'] ?>
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

            <!-- Evaluaciones del módulo -->
            <?php if (!empty($evaluaciones_modulo)): ?>
                <h2 class="seccion-titulo"><i class="icon-clipboard"></i> Evaluaciones del Módulo</h2>
                <div class="evaluaciones-lista">
                    <?php foreach ($evaluaciones_modulo as $index => $evaluacion): ?>
                        <div class="evaluacion-card-moderna">
                            <div class="evaluacion-header">
                                <div class="evaluacion-badge">
                                    <span class="evaluacion-numero-moderno"><?= (int)$index + 1 ?></span>
                                </div>
                                <div class="evaluacion-info">
                                    <h3 class="evaluacion-titulo-moderno">
                                        <?php 
                                        $titulo_evaluacion = htmlspecialchars($evaluacion['titulo']);
                                        // Mejorar nombres genéricos
                                        if (strtolower($titulo_evaluacion) === 'examen prueba' || strtolower($titulo_evaluacion) === 'prueba') {
                                            $titulo_evaluacion = "Evaluación " . ((int)$index + 1) . " - " . htmlspecialchars($modulo['titulo']);
                                        } elseif (preg_match('/^prueba\s*\d*$/i', $titulo_evaluacion)) {
                                            $titulo_evaluacion = "Evaluación " . ((int)$index + 1) . " del Módulo";
                                        }
                                        echo $titulo_evaluacion;
                                        ?>
                                    </h3>
                                    <?php if (!empty($evaluacion['descripcion'])): ?>
                                        <p class="evaluacion-descripcion-moderna"><?= htmlspecialchars($evaluacion['descripcion']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="evaluacion-estado">
                                    <?php if ((int)$evaluacion['aprobada'] === 1): ?>
                                        <div class="estado-badge aprobada">
                                            <i class="icon-check-circle"></i>
                                            <span>Aprobada</span>
                                        </div>
                                    <?php elseif ((int)$evaluacion['sin_intentos'] === 1): ?>
                                        <div class="estado-badge sin-intentos">
                                            <i class="icon-x-circle"></i>
                                            <span>Sin Intentos</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="estado-badge disponible">
                                            <i class="icon-clock"></i>
                                            <span>Disponible</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="evaluacion-stats-moderna">
                                <div class="stat-moderna intentos">
                                    <i class="icon-repeat"></i>
                                    <div class="stat-content">
                                        <span class="stat-label">Intentos</span>
                                        <span class="stat-value"><?= (int)$evaluacion['intentos_realizados'] ?> de <?= (int)$evaluacion['intentos_permitidos'] > 0 ? (int)$evaluacion['intentos_permitidos'] : '∞' ?></span>
                                    </div>
                                </div>
                                <?php if ($evaluacion['mejor_calificacion'] !== null): ?>
                                    <div class="stat-moderna calificacion">
                                        <i class="icon-award"></i>
                                        <div class="stat-content">
                                            <span class="stat-label">Mejor Calificación</span>
                                            <span class="stat-value <?= ((int)$evaluacion['aprobada'] === 1) ? 'aprobada' : 'reprobada' ?>">
                                                <?= number_format((float)$evaluacion['mejor_calificacion'], 1) ?>%
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="evaluacion-acciones-moderna">
                                <?php if ((int)$evaluacion['aprobada'] === 1): ?>
                                    <button class="btn-evaluacion aprobada" disabled>
                                        <i class="icon-check"></i>
                                        <span>Evaluación Completada</span>
                                    </button>
                                <?php elseif ((int)$evaluacion['sin_intentos'] === 1): ?>
                                    <button class="btn-evaluacion sin-intentos" disabled>
                                        <i class="icon-x"></i>
                                        <span>Sin Intentos Restantes</span>
                                    </button>
                                <?php else: ?>
                                    <a href="<?= BASE_URL ?>/estudiante/tomar_evaluacion.php?id=<?= (int)$evaluacion['id'] ?>"
                                       class="btn-evaluacion disponible">
                                        <i class="icon-play"></i>
                                        <span><?= ((int)$evaluacion['intentos_realizados'] > 0) ? 'Reintentar Evaluación' : 'Iniciar Evaluación' ?></span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Navegación (bloqueos) / fin de curso -->
            <?php if ($siguiente_modulo && !$modulo_completado): ?>
                <div class="navegacion-bloqueada">
                    <div class="bloqueo-icon">🔒</div>
                    <h3>Siguiente Módulo Bloqueado</h3>
                    <p>
                        <?php if (!empty($tiene_eval) && $tiene_eval): ?>
                            Aprueba todas las evaluaciones de este módulo para desbloquear el siguiente.
                        <?php else: ?>
                            Completa todas las lecciones de este módulo para desbloquear el siguiente.
                        <?php endif; ?>
                    </p>
                    <div class="modulo-bloqueado">
                        <h5><?= htmlspecialchars($siguiente_modulo['titulo']) ?></h5>
                        <span class="btn-bloqueado">Módulo Bloqueado</span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$siguiente_modulo && $modulo_completado): ?>
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