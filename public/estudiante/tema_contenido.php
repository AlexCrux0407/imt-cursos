<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Estudiante – Contenido del Tema';

$tema_id       = (int)($_GET['id'] ?? 0);
$estudiante_id = (int)($_SESSION['user_id'] ?? 0);

if ($tema_id === 0) {
    header('Location: ' . BASE_URL . '/estudiante/mis_cursos.php?error=tema_no_especificado');
    exit;
}
if ($estudiante_id === 0) {
    header('Location: ' . BASE_URL . '/login.php?m=auth');
    exit;
}

/* 1) Cargar el TEMA y validar acceso (inscripción en su curso) */
$stmt = $conn->prepare("
    SELECT t.*,
           m.id   AS modulo_id,   m.titulo AS modulo_titulo,   m.orden AS modulo_orden,
           c.id   AS curso_id,    c.titulo AS curso_titulo
    FROM temas t
    INNER JOIN modulos m ON t.modulo_id = m.id
    INNER JOIN cursos  c ON m.curso_id  = c.id
    INNER JOIN inscripciones i ON i.curso_id = c.id AND i.usuario_id = :uid
    WHERE t.id = :tema_id
    LIMIT 1
");
$stmt->execute([':tema_id' => $tema_id, ':uid' => $estudiante_id]);
$tema = $stmt->fetch();

if (!$tema) {
    header('Location: ' . BASE_URL . '/estudiante/catalogo.php?error=acceso_denegado');
    exit;
}

/* 2) Subtemas del tema */
$stmt = $conn->prepare("
    SELECT st.*
    FROM subtemas st
    WHERE st.tema_id = :tema_id
    ORDER BY st.orden
");
$stmt->execute([':tema_id' => $tema_id]);
$subtemas = $stmt->fetchAll();

/* 3) Lecciones del tema (directas por tema_id) */
$stmt = $conn->prepare("
    SELECT l.*, IF(pl.id IS NULL, 0, 1) AS completado
    FROM lecciones l
    LEFT JOIN progreso_lecciones pl
           ON pl.leccion_id = l.id AND pl.usuario_id = :uid
    WHERE l.tema_id = :tema_id
    ORDER BY l.orden
");
$stmt->execute([':tema_id' => $tema_id, ':uid' => $estudiante_id]);
$lecciones = $stmt->fetchAll();

/* 4) Estructura del curso para la sidebar (lecciones cuelgan de TEMA) */
$stmt = $conn->prepare("
    SELECT m.id  AS modulo_id, m.titulo AS modulo_titulo, m.orden AS modulo_orden,
           t.id  AS tema_id,   t.titulo AS tema_titulo,   t.orden AS tema_orden,
           l.id  AS leccion_id, l.titulo AS leccion_titulo, l.orden AS leccion_orden,
           IF(pl.id IS NULL, 0, 1) AS leccion_completada
    FROM modulos m
    LEFT JOIN temas t     ON m.id = t.modulo_id
    LEFT JOIN lecciones l ON t.id = l.tema_id
    LEFT JOIN progreso_lecciones pl
           ON l.id = pl.leccion_id AND pl.usuario_id = :uid
    WHERE m.curso_id = :curso_id
    ORDER BY m.orden, t.orden, l.orden
");
$stmt->execute([':curso_id' => $tema['curso_id'], ':uid' => $estudiante_id]);
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
                // AQUÍ: soportamos lecciones a nivel de tema
                'lecciones' => []
            ];
        }
        if (!empty($r['leccion_id'])) {
            $curso_estructura[$mid]['temas'][$tid]['lecciones'][] = [
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


/* 5) Header + Nav */
require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/estudiante.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/curso-sidebar.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/modulo-contenido.css">

<div class="contenido-con-sidebar" style="display:flex; gap:30px;">
    <?php
    $cursoTituloSidebar = $tema['curso_titulo'];
    $moduloActualId     = (int)$tema['modulo_id'];
    include __DIR__ . '/partials/curso_sidebar.php';
    ?>

    <div class="contenido-principal" style="flex:1;">
        <!-- Breadcrumb con fondo azul -->
        <div class="modulo-header">
            <?php
            $cursoIdLink   = (int)($tema['curso_id'] ?? 0);
            $cursoTituloBn = htmlspecialchars($tema['curso_titulo'] ?? 'Curso', ENT_QUOTES, 'UTF-8');
            $hrefCurso     = BASE_URL . '/estudiante/curso_contenido.php?id=' . $cursoIdLink;
            $moduloIdLink  = (int)($tema['modulo_id'] ?? 0);
            $moduloTituloBn = htmlspecialchars($tema['modulo_titulo'] ?? 'Módulo', ENT_QUOTES, 'UTF-8');
            $hrefModulo    = BASE_URL . '/estudiante/modulo_contenido.php?id=' . $moduloIdLink;
            ?>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>/estudiante/cursos_disponibles.php">Mis Cursos</a> →
                <a href="<?= $hrefCurso ?>"><?= $cursoTituloBn ?></a> →
                <a href="<?= $hrefModulo ?>"><?= $moduloTituloBn ?></a> →
                Tema
            </div>

            <h1 class="page-title">
                <?= htmlspecialchars($tema['titulo'] ?? 'Contenido del Tema', ENT_QUOTES, 'UTF-8') ?>
            </h1>
        </div>

        <?php if (!empty($tema['contenido'])): ?>
            <div class="contenido-modulo-section">
                <h2 class="seccion-titulo"><i class="icon-file-text"></i> Contenido del Tema</h2>
                <div class="contenido-texto">
                    <?= nl2br(htmlspecialchars($tema['contenido'])) ?>
                </div>
            </div>
        <?php endif; ?>


        <!-- Título/descr. del tema -->
        <h1 class="modulo-titulo"><?= htmlspecialchars($tema['titulo'], ENT_QUOTES, 'UTF-8') ?></h1>
        <?php if (!empty($tema['descripcion'])): ?>
            <p class="modulo-descripcion"><?= htmlspecialchars($tema['descripcion'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <!-- Subtemas -->
        <?php if (!empty($subtemas)): ?>
            <h2 class="seccion-titulo"><i class="icon-book"></i> Subtemas</h2>
            <div class="temas-lista">
                <?php foreach ($subtemas as $st): ?>
                    <div class="tema-card">
                        <div class="tema-header">
                            <h3 class="tema-titulo"><?= htmlspecialchars($st['titulo'], ENT_QUOTES, 'UTF-8') ?></h3>
                        </div>
                        <?php if (!empty($st['descripcion'])): ?>
                            <p class="tema-descripcion"><?= htmlspecialchars($st['descripcion'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <div class="tema-acciones">
                            <a class="btn-tema" href="<?= BASE_URL ?>/estudiante/subtema_contenido.php?id=<?= (int)$st['id'] ?>">
                                Ver Subtema
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Lecciones del tema -->
        <?php if (!empty($lecciones)): ?>
            <h2 class="seccion-titulo"><i class="icon-play"></i> Lecciones del Tema</h2>
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
        <?php if (empty($subtemas) && empty($lecciones) && empty($tema['contenido'] ?? '')): ?>
            <div class="empty-content">
                <i class="icon-info"></i>
                <h3>Contenido en preparación</h3>
                <p>Este tema aún no tiene contenido publicado.</p>
            </div>
        <?php endif; ?>
    </div> <!-- /.contenido-principal -->
</div> <!-- /.contenido-con-sidebar -->

<?php require __DIR__ . '/../partials/footer.php'; ?>