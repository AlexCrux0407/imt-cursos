<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Estudiante – Contenido del Subtema';

$subtema_id    = (int)($_GET['id'] ?? 0);
$estudiante_id = (int)($_SESSION['user_id'] ?? 0);

if ($subtema_id === 0) {
    header('Location: ' . BASE_URL . '/estudiante/mis_cursos.php?error=subtema_no_especificado');
    exit;
}
if ($estudiante_id === 0) {
    header('Location: ' . BASE_URL . '/login.php?m=auth');
    exit;
}

/* 1) Cargar el SUBTEMA y validar acceso (inscripción en su curso) */
$stmt = $conn->prepare("
    SELECT st.*,
           t.id   AS tema_id,     t.titulo AS tema_titulo,     t.orden AS tema_orden,
           m.id   AS modulo_id,   m.titulo AS modulo_titulo,   m.orden AS modulo_orden,
           c.id   AS curso_id,    c.titulo AS curso_titulo
    FROM subtemas st
    INNER JOIN temas t   ON st.tema_id = t.id
    INNER JOIN modulos m ON t.modulo_id = m.id
    INNER JOIN cursos  c ON m.curso_id  = c.id
    INNER JOIN inscripciones i ON i.curso_id = c.id AND i.usuario_id = :uid
    WHERE st.id = :subtema_id
    LIMIT 1
");
$stmt->execute([':subtema_id' => $subtema_id, ':uid' => $estudiante_id]);
$subtema = $stmt->fetch();

if (!$subtema) {
    header('Location: ' . BASE_URL . '/estudiante/catalogo.php?error=acceso_denegado');
    exit;
}

/* 2) Lecciones del subtema */
$stmt = $conn->prepare("
    SELECT l.*, IF(pl.id IS NULL, 0, 1) AS completado
    FROM lecciones l
    LEFT JOIN progreso_lecciones pl
           ON pl.leccion_id = l.id AND pl.usuario_id = :uid
    WHERE l.subtema_id = :subtema_id
    ORDER BY l.orden
");
$stmt->execute([':subtema_id' => $subtema_id, ':uid' => $estudiante_id]);
$lecciones = $stmt->fetchAll();

/* 3) Estructura del curso para la sidebar */
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
    LEFT JOIN progreso_lecciones pl ON l.id = pl.leccion_id AND pl.usuario_id = :uid
    WHERE m.curso_id = :curso_id
    ORDER BY m.orden, t.orden, s.orden, l.orden
");
$stmt->execute([':curso_id' => $subtema['curso_id'], ':uid' => $estudiante_id]);
$rows = $stmt->fetchAll();

$curso_estructura = [];
foreach ($rows as $r) {
    $mid = (int)$r['modulo_id'];
    if (!isset($curso_estructura[$mid])) {
        $curso_estructura[$mid] = [
            'id' => $mid,
            'titulo' => $r['modulo_titulo'],
            'orden' => (int)$r['modulo_orden'],
            'temas' => [],
            'total_lecciones' => 0,
            'lecciones_completadas' => 0
        ];
    }
    if (!empty($r['tema_id'])) {
        $tid = (int)$r['tema_id'];
        if (!isset($curso_estructura[$mid]['temas'][$tid])) {
            $curso_estructura[$mid]['temas'][$tid] = [
                'id' => $tid,
                'titulo' => $r['tema_titulo'],
                'orden' => (int)$r['tema_orden'],
                'subtemas' => []
            ];
        }
        if (!empty($r['subtema_id'])) {
            $sid = (int)$r['subtema_id'];
            if (!isset($curso_estructura[$mid]['temas'][$tid]['subtemas'][$sid])) {
                $curso_estructura[$mid]['temas'][$tid]['subtemas'][$sid] = [
                    'id' => $sid,
                    'titulo' => $r['subtema_titulo'],
                    'orden' => (int)$r['subtema_orden'],
                    'lecciones' => []
                ];
            }
            if (!empty($r['leccion_id'])) {
                $curso_estructura[$mid]['temas'][$tid]['subtemas'][$sid]['lecciones'][] = [
                    'id' => (int)$r['leccion_id'],
                    'titulo' => $r['leccion_titulo'],
                    'orden' => (int)$r['leccion_orden'],
                    'completada' => (bool)$r['leccion_completada']
                ];
                $curso_estructura[$mid]['total_lecciones']++;
                if ($r['leccion_completada']) {
                    $curso_estructura[$mid]['lecciones_completadas']++;
                }
            }
        }
    }
}

/* 4) Header + Nav */
require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/estudiante.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/curso-sidebar.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/modulo-contenido.css">

<div class="contenido-con-sidebar" style="display:flex; gap:30px;">
    <?php
    $cursoTituloSidebar = $subtema['curso_titulo'];
    $moduloActualId     = (int)$subtema['modulo_id'];
    include __DIR__ . '/partials/curso_sidebar.php';
    ?>

    <div class="contenido-principal" style="flex:1;">
        <!-- Breadcrumb con fondo azul -->
        <div class="modulo-header">
            <?php
            $cursoIdLink   = (int)($subtema['curso_id'] ?? 0);
            $cursoTituloBn = htmlspecialchars($subtema['curso_titulo'] ?? 'Curso', ENT_QUOTES, 'UTF-8');
            $hrefCurso     = BASE_URL . '/estudiante/curso_contenido.php?id=' . $cursoIdLink;
            $moduloIdLink  = (int)($subtema['modulo_id'] ?? 0);
            $moduloTituloBn = htmlspecialchars($subtema['modulo_titulo'] ?? 'Módulo', ENT_QUOTES, 'UTF-8');
            $hrefModulo    = BASE_URL . '/estudiante/modulo_contenido.php?id=' . $moduloIdLink;
            $temaIdLink    = (int)($subtema['tema_id'] ?? 0);
            $temaTituloBn  = htmlspecialchars($subtema['tema_titulo'] ?? 'Tema', ENT_QUOTES, 'UTF-8');
            $hrefTema      = BASE_URL . '/estudiante/tema_contenido.php?id=' . $temaIdLink;
            ?>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>/estudiante/cursos_disponibles.php">Mis Cursos</a> →
                <a href="<?= $hrefCurso ?>"><?= $cursoTituloBn ?></a> →
                <a href="<?= $hrefModulo ?>"><?= $moduloTituloBn ?></a> →
                <a href="<?= $hrefTema ?>"><?= $temaTituloBn ?></a> →
                Subtema
            </div>

            <h1 class="page-title">
                <?= htmlspecialchars($subtema['titulo'] ?? 'Contenido del Subtema', ENT_QUOTES, 'UTF-8') ?>
            </h1>
        </div>

        <?php if (!empty($subtema['contenido'])): ?>
            <div class="contenido-modulo-section">
                <h2 class="seccion-titulo"><i class="icon-file-text"></i> Contenido del Subtema</h2>
                <div class="contenido-texto">
                    <?= nl2br(htmlspecialchars($subtema['contenido'])) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recursos del subtema -->
        <?php if (!empty($subtema['recurso_url'])): ?>
            <div class="contenido-modulo-section">
                <h2 class="seccion-titulo"><i class="icon-download"></i> Recursos del Subtema</h2>
                <div class="recursos-lista">
                    <?php
                    $extension = strtolower(pathinfo($subtema['recurso_url'], PATHINFO_EXTENSION));
                    $es_archivo_local = strpos($subtema['recurso_url'], '/imt-cursos/uploads/') === 0;
                    $es_url_externa = filter_var($subtema['recurso_url'], FILTER_VALIDATE_URL);
                    $nombre_archivo = basename($subtema['recurso_url']);
                    
                    // Determinar el tipo de recurso
                    $tipo_recurso = 'archivo';
                    $icono = 'icon-file';
                    
                    if (in_array($extension, ['pdf'])) {
                        $tipo_recurso = 'PDF';
                        $icono = 'icon-file-pdf';
                    } elseif (in_array($extension, ['mp4', 'avi', 'mov', 'wmv'])) {
                        $tipo_recurso = 'Video';
                        $icono = 'icon-play';
                    } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                        $tipo_recurso = 'Imagen';
                        $icono = 'icon-image';
                    } elseif (in_array($extension, ['doc', 'docx'])) {
                        $tipo_recurso = 'Documento Word';
                        $icono = 'icon-file-word';
                    } elseif (in_array($extension, ['ppt', 'pptx'])) {
                        $tipo_recurso = 'Presentación';
                        $icono = 'icon-file-powerpoint';
                    } elseif ($es_url_externa) {
                        $tipo_recurso = 'Enlace externo';
                        $icono = 'icon-link';
                    }
                    ?>
                    
                    <div class="recurso-item">
                        <div class="recurso-info">
                            <i class="<?= $icono ?>"></i>
                            <div class="recurso-detalles">
                                <h4 class="recurso-nombre"><?= htmlspecialchars($nombre_archivo) ?></h4>
                                <span class="recurso-tipo"><?= $tipo_recurso ?></span>
                            </div>
                        </div>
                        <div class="recurso-acciones">
                            <?php if ($es_url_externa): ?>
                                <a href="<?= htmlspecialchars($subtema['recurso_url']) ?>" target="_blank" class="btn-recurso">
                                    <i class="icon-external-link"></i> Abrir enlace
                                </a>
                            <?php else: ?>
                                <a href="<?= BASE_URL ?>/estudiante/ver_recurso.php?url=<?= urlencode($subtema['recurso_url']) ?>&titulo=<?= urlencode($subtema['titulo']) ?>" 
                                   class="btn-recurso" target="_blank">
                                    <i class="icon-eye"></i> Ver recurso
                                </a>
                                <?php if ($es_archivo_local): ?>
                                    <a href="<?= htmlspecialchars($subtema['recurso_url']) ?>" download class="btn-recurso-download">
                                        <i class="icon-download"></i> Descargar
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Título/descr. del subtema -->
        <h1 class="modulo-titulo"><?= htmlspecialchars($subtema['titulo'], ENT_QUOTES, 'UTF-8') ?></h1>
        <?php if (!empty($subtema['descripcion'])): ?>
            <p class="modulo-descripcion"><?= htmlspecialchars($subtema['descripcion'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <!-- Lecciones del subtema -->
        <?php if (!empty($lecciones)): ?>
            <h2 class="seccion-titulo"><i class="icon-play"></i> Lecciones del Subtema</h2>
            <div class="lecciones-lista">
                <?php foreach ($lecciones as $l): ?>
                    <div class="leccion-item">
                        <div class="leccion-numero"><?= (int)$l['orden'] ?></div>
                        <div class="leccion-info">
                            <h4 class="leccion-titulo"><?= htmlspecialchars($l['titulo'], ENT_QUOTES, 'UTF-8') ?></h4>
                            <?php if (!empty($l['descripcion'])): ?>
                                <p class="leccion-descripcion"><?= htmlspecialchars($l['descripcion'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="leccion-acciones">
                            <a class="btn-leccion" href="<?= BASE_URL ?>/estudiante/leccion.php?id=<?= (int)$l['id'] ?>">
                                <?= !empty($l['completado']) ? 'Revisar' : 'Estudiar' ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Estado vacío - solo mostrar si realmente no hay contenido -->
        <?php if (empty($lecciones) && empty($subtema['contenido'] ?? '') && empty($subtema['recurso_url'])): ?>
            <div class="empty-content">
                <i class="icon-info"></i>
                <h3>Contenido en preparación</h3>
                <p>Este subtema aún no tiene contenido publicado.</p>
            </div>
        <?php endif; ?>

        <!-- Botón para volver al tema -->
        <div class="navegacion-tema" style="margin-top: 30px;">
            <a href="<?= BASE_URL ?>/estudiante/tema_contenido.php?id=<?= (int)$subtema['tema_id'] ?>" 
               class="btn-volver">
                ← Volver al tema
            </a>
        </div>
    </div> <!-- /.contenido-principal -->
</div> <!-- /.contenido-con-sidebar -->

<?php require __DIR__ . '/../partials/footer.php'; ?>