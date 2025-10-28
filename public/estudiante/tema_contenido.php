<?php
// Vista Estudiante – Contenido del tema

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

/* 3) Lecciones del tema  */
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

/* 4) Estructura del curso para la sidebar */
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

/** Obtener información de progreso de módulos para el sidebar */
$stmt = $conn->prepare("
    SELECT m.id, 
           IF(pm.evaluacion_completada = 1, 1, 0) AS evaluacion_completada
    FROM modulos m
    LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = :uid
    WHERE m.curso_id = :curso_id
");
$stmt->execute([':curso_id' => $tema['curso_id'], ':uid' => $estudiante_id]);
$progreso_modulos_info = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

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
            'lecciones_completadas' => 0,
            'evaluacion_completada' => isset($progreso_modulos_info[$mid]) ? (bool)$progreso_modulos_info[$mid] : false
        ];
    }
    if (!empty($r['tema_id'])) {
        $tid = (int)$r['tema_id'];
        if (!isset($curso_estructura[$mid]['temas'][$tid])) {
            $curso_estructura[$mid]['temas'][$tid] = [
                'id' => $tid,
                'titulo' => $r['tema_titulo'],
                'orden' => (int)$r['tema_orden'],
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
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/integrated-resource-viewer.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/curso-sidebar.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/modulo-contenido.css">

<div class="contenido-con-sidebar" style="display:flex; gap:30px;">
    <?php
    $cursoTituloSidebar = $tema['curso_titulo'];
    $moduloActualId     = (int)$tema['modulo_id'];
    include __DIR__ . '/partials/curso_sidebar.php';
    ?>

    <div class="contenido-principal" style="flex:1;">
        
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
                    <?= $tema['contenido'] ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recursos del tema -->
        <?php if (!empty($tema['recurso_url'])): ?>
            <div class="contenido-modulo-section">
                <h2 class="seccion-titulo"><i class="icon-download"></i> Recursos del Tema</h2>
                <div class="recursos-lista">
                    <?php
                    $extension = strtolower(pathinfo($tema['recurso_url'], PATHINFO_EXTENSION));
                    $es_archivo_local = strpos($tema['recurso_url'], '/imt-cursos/uploads/') === 0;
                    $es_url_externa = filter_var($tema['recurso_url'], FILTER_VALIDATE_URL);
                    $nombre_archivo = basename($tema['recurso_url']);
                    
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
                                <a href="<?= htmlspecialchars($tema['recurso_url']) ?>" target="_blank" class="btn-recurso">
                                    <i class="icon-external-link"></i> Abrir enlace
                                </a>
                            <?php else: ?>
                                <button onclick="toggleRecursoViewer('<?= htmlspecialchars($tema['recurso_url']) ?>', '<?= htmlspecialchars($tema['titulo']) ?>', '<?= $extension ?>')" 
                                        class="btn-recurso">
                                    <i class="icon-eye"></i> Ver recurso
                                </button>
                                <?php if ($es_archivo_local): ?>
                                    <a href="<?= BASE_URL ?>/estudiante/ver_recurso.php?url=<?= urlencode($tema['recurso_url']) ?>&titulo=<?= urlencode($tema['titulo']) ?>" target="_blank" class="btn-recurso-download">
                                        <i class="icon-download"></i> Descargar
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
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

        <!-- Estado vacío  -->
        <?php if (empty($subtemas) && empty($lecciones) && empty($tema['contenido'] ?? '')): ?>
            <div class="empty-content">
                <i class="icon-info"></i>
                <h3>Contenido en preparación</h3>
                <p>Este tema aún no tiene contenido publicado.</p>
            </div>
        <?php endif; ?>
    </div> <!-- /.contenido-principal -->
</div> <!-- /.contenido-con-sidebar -->

<!-- Visor de recursos integrado -->
<div id="recurso-viewer-overlay" class="recurso-viewer-overlay" style="display: none;">
    <div class="recurso-viewer-container">
        <div class="recurso-viewer-header">
            <div class="recurso-viewer-header-left">
                <img src="<?= BASE_URL ?>/styles/logos/Logo_blanco.png" alt="IMT Logo" class="recurso-viewer-logo">
                <h3 id="recurso-viewer-title" class="recurso-viewer-title"></h3>
            </div>
            <div class="recurso-viewer-controls">
                <button onclick="closeRecursoViewer()" class="btn-close-viewer">
                    <span>✕</span> Cerrar
                </button>
            </div>
        </div>
        <div id="recurso-viewer-content" class="recurso-viewer-content">
            <!-- Contenido del recurso -->
        </div>
    </div>
</div>

<script>
function toggleRecursoViewer(url, titulo, extension) {
    const overlay = document.getElementById('recurso-viewer-overlay');
    const titleElement = document.getElementById('recurso-viewer-title');
    const contentElement = document.getElementById('recurso-viewer-content');
    
    titleElement.textContent = titulo;
    
    // Limpiar contenido anterior
    contentElement.innerHTML = '';
    
    // Determinar el tipo de contenido y crear el elemento apropiado
    const ext = extension.toLowerCase();
    // Normalizar URL relativa a absoluta basada en BASE_URL
    const BASE = '<?= rtrim(BASE_URL, '/') ?>';
    let normalizedUrl = url;
    try {
        const isAbsolute = /^https?:\/\//i.test(url);
        if (!isAbsolute) {
            if (url.startsWith('/')) {
                normalizedUrl = BASE + url;
            } else {
                normalizedUrl = BASE + '/' + url;
            }
        }
    } catch (e) { normalizedUrl = url; }
    // Si apunta a un recurso local bajo /uploads, redirigir al proxy para evitar 404
    try {
        const baseUrl = new URL(BASE, window.location.origin);
        const absUrl = new URL(normalizedUrl, window.location.origin);
        if (absUrl.host === window.location.host) {
            const path = absUrl.pathname; // puede incluir prefijo de subcarpeta
            const idx = path.indexOf('/uploads/');
            if (idx >= 0) {
                const rel = path.substring(idx + '/uploads/'.length);
                if (rel.startsWith('cursos/')) {
                    const basePath = baseUrl.pathname === '/' ? '' : baseUrl.pathname; // ej: '' o '/imt-cursos'
                    normalizedUrl = baseUrl.origin + basePath + '/serve_uploads.php?path=' + encodeURIComponent(rel);
                }
            }
        }
    } catch (e) {}
    
    if (ext === 'pdf') {
        contentElement.innerHTML = `
            <iframe src="${normalizedUrl}#toolbar=1&navpanes=1&scrollbar=1&view=FitH" 
                    style="width: 100%; height: 100%; border: none;"
                    title="Visualizador de PDF">
            </iframe>
        `;
    } else if (['mp4', 'avi', 'mov', 'webm'].includes(ext)) {
        const mimeMap = { mp4: 'video/mp4', webm: 'video/webm', mov: 'video/quicktime', avi: 'video/x-msvideo' };
        const mime = mimeMap[ext] || 'video/mp4';
        contentElement.innerHTML = `
            <video controls preload="metadata" style="width: 100%; height: 100%; object-fit: contain;">
                <source src="${normalizedUrl}" type="${mime}">
                Tu navegador no soporta la reproducción de video.
            </video>
        `;
    } else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
        contentElement.innerHTML = `
            <img src="${normalizedUrl}" alt="${titulo}" 
                 style="max-width: 100%; max-height: 100%; object-fit: contain; margin: auto; display: block;">
        `;
    } else {
        contentElement.innerHTML = `
            <div class="recurso-message">
                <h3>Vista previa no disponible</h3>
                <p>Este tipo de archivo no se puede visualizar en línea.</p>
                <p>Archivo: ${normalizedUrl.split('/').pop()}</p>
                <a href="${normalizedUrl}" download class="btn-download-inline">
                    📥 Descargar Archivo
                </a>
            </div>
        `;
    }
    
    // Mostrar el overlay
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeRecursoViewer() {
    const overlay = document.getElementById('recurso-viewer-overlay');
    overlay.style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Cerrar con tecla ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRecursoViewer();
    }
});

// Cerrar al hacer clic fuera del contenido
document.getElementById('recurso-viewer-overlay').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRecursoViewer();
    }
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>