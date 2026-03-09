<?php
declare(strict_types=1);
// Vista Estudiante – Contenido del subtema

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

$subtema_recurso_url_public = '';
if (!empty($subtema['recurso_url'])) {
    $subtema_recurso_url_public = $subtema['recurso_url'];
    if (!filter_var($subtema_recurso_url_public, FILTER_VALIDATE_URL)) {
        if (strpos($subtema_recurso_url_public, '/') === 0) {
            $subtema_recurso_url_public = rtrim(BASE_URL, '/') . $subtema_recurso_url_public;
        } else {
            $subtema_recurso_url_public = rtrim(BASE_URL, '/') . '/' . $subtema_recurso_url_public;
        }
    }
    $path_en_url = parse_url($subtema_recurso_url_public, PHP_URL_PATH) ?: '';
    $host_en_url = parse_url($subtema_recurso_url_public, PHP_URL_HOST) ?: '';
    $host_base = parse_url(BASE_URL, PHP_URL_HOST) ?: '';
    $es_archivo_local = (($host_en_url === $host_base) || $host_en_url === '' || $host_en_url === 'localhost' || $host_en_url === '127.0.0.1')
        && (strpos($path_en_url, '/uploads/') !== false);
    if (!$es_archivo_local && strpos($path_en_url, '/uploads/') !== false) {
        $es_archivo_local = true;
    }
    if ($es_archivo_local) {
        $pos = strpos($path_en_url, '/uploads/');
        $rel = substr($path_en_url, $pos + strlen('/uploads/'));
        if (strpos($rel, 'cursos/') === 0) {
            $subtema_recurso_url_public = rtrim(BASE_URL, '/') . '/serve_uploads.php?path=' . rawurlencode($rel);
        }
    }
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

/** Obtener información de progreso de módulos para el sidebar */
$stmt = $conn->prepare("
    SELECT m.id, 
           IF(pm.evaluacion_completada = 1, 1, 0) AS evaluacion_completada
    FROM modulos m
    LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = :uid
    WHERE m.curso_id = :curso_id
");
$stmt->execute([':curso_id' => $subtema['curso_id'], ':uid' => $estudiante_id]);
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
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/tema-contenido.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/integrated-resource-viewer.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/curso-sidebar.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/modulo-contenido.css">

<div class="contenido-con-sidebar" style="display:flex; gap:30px;">
    <?php
    $cursoTituloSidebar = $subtema['curso_titulo'];
    $moduloActualId     = (int)$subtema['modulo_id'];
    include __DIR__ . '/partials/curso_sidebar.php';
    ?>

    <div class="contenido-principal" style="flex:1;">
        
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
                <a href="<?= BASE_URL ?>/estudiante/catalogo.php">Mis Cursos</a> →
                <a href="<?= $hrefCurso ?>"><?= $cursoTituloBn ?></a> →
                <a href="<?= $hrefModulo ?>"><?= $moduloTituloBn ?></a> →
                <a href="<?= $hrefTema ?>"><?= $temaTituloBn ?></a> →
                Subtema
            </div>

            <h1 class="page-title">
                <?= htmlspecialchars($subtema['titulo'] ?? 'Contenido del Subtema', ENT_QUOTES, 'UTF-8') ?>
            </h1>
        </div>

        <div class="contenido-modulo">
        <?php if (!empty($subtema_recurso_url_public)): ?>
            <?php
            $subtema_recurso_url = $subtema_recurso_url_public;
            $subtema_recurso_es_imagen = preg_match('/\.(jpe?g|png|gif|webp)(\?.*)?$/i', $subtema_recurso_url)
                && (strpos($subtema_recurso_url, '/uploads/') !== false || strpos($subtema_recurso_url, 'serve_uploads.php?') !== false);
            ?>
            <div class="contenido-modulo-section">
                <h2 class="seccion-titulo"><i class="icon-file-text"></i> Contenido del Subtema</h2>
                <div style="width: 100%; height: 70vh; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;">
                    <?php if ($subtema_recurso_es_imagen): ?>
                        <img src="<?= htmlspecialchars($subtema_recurso_url_public) ?>" style="width: 100%; height: auto; border: 0;" alt="<?= htmlspecialchars($subtema['titulo'] ?? 'Contenido del Subtema', ENT_QUOTES, 'UTF-8') ?>">
                    <?php else: ?>
                        <iframe src="<?= htmlspecialchars($subtema_recurso_url_public) ?>" style="width: 100%; height: 100%; border: 0;" title="<?= htmlspecialchars($subtema['titulo'] ?? 'Contenido del Subtema', ENT_QUOTES, 'UTF-8') ?>"></iframe>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php if (!empty($subtema['contenido'])): ?>
                <?php
                $contenido_subtema = $subtema['contenido'];
                $contenido_subtema = str_replace(
                    [
                        '<p class="modulo-descripcion"></p>',
                        '<h1 class="modulo-titulo"></h1>',
                        '&lt;p class=&quot;modulo-descripcion&quot;&gt;&lt;/p&gt;',
                        '&lt;h1 class=&quot;modulo-titulo&quot;&gt;&lt;/h1&gt;'
                    ],
                    '',
                    $contenido_subtema
                );
                $baseUrl = rtrim(BASE_URL, '/');
                $contenido_subtema = preg_replace_callback(
                    '/\b(src|href)\s*=\s*(["\'])([^"\']+)\2/i',
                    function ($matches) use ($baseUrl) {
                        $attr = $matches[1];
                        $quote = $matches[2];
                        $url = $matches[3];
                        if (stripos($url, 'serve_uploads.php?path=') !== false) {
                            return $matches[0];
                        }
                        $rel = null;
                        $posUploads = strpos($url, '/uploads/');
                        if ($posUploads !== false) {
                            $rel = substr($url, $posUploads + strlen('/uploads/'));
                        } elseif (stripos($url, 'uploads/') === 0) {
                            $rel = substr($url, strlen('uploads/'));
                        }
                        if ($rel && stripos($rel, 'cursos/') === 0) {
                            $newUrl = $baseUrl . '/serve_uploads.php?path=' . rawurlencode($rel);
                            return $attr . '=' . $quote . $newUrl . $quote;
                        }
                        return $matches[0];
                    },
                    $contenido_subtema
                );
                ?>
                <div class="contenido-modulo-section">
                    <h2 class="seccion-titulo"><i class="icon-file-text"></i> Contenido del Subtema</h2>
                    <div class="contenido-texto">
                        <?= $contenido_subtema ?>
                    </div>
                </div>
            <?php endif; ?>

            <h1 class="modulo-titulo"><?= htmlspecialchars($subtema['titulo'], ENT_QUOTES, 'UTF-8') ?></h1>
            <?php if (!empty($subtema['descripcion'])): ?>
                <p class="modulo-descripcion"><?= htmlspecialchars($subtema['descripcion'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

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

            <?php if (empty($lecciones) && empty($subtema['contenido'] ?? '') && empty($subtema['recurso_url']) && empty($subtema['descripcion'])): ?>
                <div class="empty-content">
                    <i class="icon-info"></i>
                    <h3>Contenido en preparación</h3>
                    <p>Este subtema aún no tiene contenido publicado.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        </div><!-- /.contenido-modulo -->

        <!-- Botón para volver al tema -->
        <div class="navegacion-tema" style="margin-top: 30px;">
            <a href="<?= BASE_URL ?>/estudiante/tema_contenido.php?id=<?= (int)$subtema['tema_id'] ?>" 
               class="btn-volver">
                ← Volver al tema
            </a>
        </div>
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
            <!-- El contenido del recurso se cargará aquí -->
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
// Si es recurso local bajo /uploads, redirigir al proxy para evitar 404 en despliegues con subcarpetas
try {
    const baseUrl = new URL(BASE, window.location.origin);
    const absUrl = new URL(normalizedUrl, window.location.origin);
    if (absUrl.host === window.location.host) {
        const path = absUrl.pathname;
        const idx = path.indexOf('/uploads/');
        if (idx >= 0) {
            const rel = path.substring(idx + '/uploads/'.length);
            if (rel.startsWith('cursos/')) {
                const basePath = baseUrl.pathname === '/' ? '' : baseUrl.pathname;
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
