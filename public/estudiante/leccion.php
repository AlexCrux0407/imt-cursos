<?php
// Vista Estudiante – Lección: contenido y progreso
require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Estudiante – Lección';

$leccion_id = (int)($_GET['id'] ?? 0);
$estudiante_id = (int)($_SESSION['user_id'] ?? 0);

if ($leccion_id === 0) {
    header('Location: ' . BASE_URL . '/estudiante/dashboard.php?error=leccion_no_especificada');
    exit;
}

// Verificar acceso: lección válida y estudiante inscrito en el curso
$stmt = $conn->prepare("
    SELECT l.*, 
           st.titulo as subtema_titulo, st.id as subtema_id,
           t.titulo as tema_titulo, t.id as tema_id,
           m.titulo as modulo_titulo, m.id as modulo_id,
           c.titulo as curso_titulo, c.id as curso_id
    FROM lecciones l
    LEFT JOIN subtemas st ON l.subtema_id = st.id
    LEFT JOIN temas t ON (l.tema_id = t.id OR st.tema_id = t.id)
    LEFT JOIN modulos m ON (l.modulo_id = m.id OR t.modulo_id = m.id)
    INNER JOIN cursos c ON m.curso_id = c.id
    INNER JOIN inscripciones i ON c.id = i.curso_id
    WHERE l.id = :leccion_id AND i.usuario_id = :estudiante_id
    LIMIT 1
");
$stmt->execute([':leccion_id' => $leccion_id, ':estudiante_id' => $estudiante_id]);
$leccion = $stmt->fetch();

// DEBUG: Verificar contenido obtenido
error_log("DEBUG LECCION - ID: " . $leccion_id);
error_log("DEBUG LECCION - Contenido: " . ($leccion['contenido'] ?? 'NULL'));
error_log("DEBUG LECCION - Titulo: " . ($leccion['titulo'] ?? 'NULL'));

if (!$leccion) {
    header('Location: ' . BASE_URL . '/estudiante/dashboard.php?error=acceso_denegado');
    exit;
}
$leccion_recurso_url_public = '';
$leccion_recurso_extension = '';
if (!empty($leccion['recurso_url'])) {
    $leccion_recurso_extension = strtolower(pathinfo($leccion['recurso_url'], PATHINFO_EXTENSION));
    $leccion_recurso_url_public = $leccion['recurso_url'];
    if (!filter_var($leccion_recurso_url_public, FILTER_VALIDATE_URL)) {
        $baseUrl = rtrim(BASE_URL, '/');
        if ($baseUrl !== '' && strpos($leccion_recurso_url_public, $baseUrl . '/') === 0) {
            $leccion_recurso_url_public = $leccion_recurso_url_public;
        } elseif (strpos($leccion_recurso_url_public, '/') === 0) {
            $leccion_recurso_url_public = $baseUrl . $leccion_recurso_url_public;
        } else {
            $leccion_recurso_url_public = $baseUrl . '/' . $leccion_recurso_url_public;
        }
    }
    $path_en_url = parse_url($leccion_recurso_url_public, PHP_URL_PATH) ?: '';
    $host_en_url = parse_url($leccion_recurso_url_public, PHP_URL_HOST) ?: '';
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
            $leccion_recurso_url_public = rtrim(BASE_URL, '/') . '/serve_uploads.php?path=' . rawurlencode($rel);
        }
    }
}
$contenido_embebido = null;
if (is_array($leccion) && array_key_exists('contenido_embebido', $leccion)) {
    $contenido_embebido = $leccion['contenido_embebido'] === null ? null : (int)$leccion['contenido_embebido'];
}
$tiene_embebido = $contenido_embebido === 1;

// Obtener lecciones del mismo nivel para navegación
$stmt = $conn->prepare("
    SELECT id, titulo, orden 
    FROM lecciones 
    WHERE (subtema_id = :subtema_id OR (subtema_id IS NULL AND tema_id = :tema_id) OR (subtema_id IS NULL AND tema_id IS NULL AND modulo_id = :modulo_id))
    ORDER BY orden ASC
");
$stmt->execute([
    ':subtema_id' => $leccion['subtema_id'], 
    ':tema_id' => $leccion['tema_id'],
    ':modulo_id' => $leccion['modulo_id']
]);
$lecciones_subtema = $stmt->fetchAll();

// Encontrar lección anterior y siguiente
$leccion_anterior = null;
$leccion_siguiente = null;
$leccion_actual_index = null;

foreach ($lecciones_subtema as $index => $l) {
    if ($l['id'] == $leccion_id) {
        $leccion_actual_index = $index;
        if ($index > 0) {
            $leccion_anterior = $lecciones_subtema[$index - 1];
        }
        if ($index < count($lecciones_subtema) - 1) {
            $leccion_siguiente = $lecciones_subtema[$index + 1];
        }
        break;
    }
}

// Obtener estructura del curso para la sidebar
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
$stmt->execute([':curso_id' => $leccion['curso_id'], ':uid' => $estudiante_id]);
$rows = $stmt->fetchAll();

/** Obtener información de progreso de módulos para el sidebar */
$stmt = $conn->prepare("
    SELECT m.id, 
           IF(pm.evaluacion_completada = 1, 1, 0) AS evaluacion_completada
    FROM modulos m
    LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = :uid
    WHERE m.curso_id = :curso_id
");
$stmt->execute([':curso_id' => $leccion['curso_id'], ':uid' => $estudiante_id]);
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

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/estudiante.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/integrated-resource-viewer.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/curso-sidebar.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/modulo-contenido.css">

<div class="contenido-con-sidebar" style="display:flex; gap:30px;">
    <?php
    $cursoTituloSidebar = $leccion['curso_titulo'];
    $moduloActualId     = (int)$leccion['modulo_id'];
    include __DIR__ . '/partials/curso_sidebar.php';
    ?>

    <div class="contenido-principal" style="flex:1;">
        
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>/estudiante/curso_contenido.php?id=<?= $leccion['curso_id'] ?>">
                <?= htmlspecialchars($leccion['curso_titulo']) ?>
            </a>
            <span>›</span>
            <a href="<?= BASE_URL ?>/estudiante/modulo_contenido.php?id=<?= $leccion['modulo_id'] ?>">
                <?= htmlspecialchars($leccion['modulo_titulo']) ?>
            </a>
            <span>›</span>
            <a href="<?= BASE_URL ?>/estudiante/tema_contenido.php?id=<?= $leccion['tema_id'] ?>">
                <?= htmlspecialchars($leccion['tema_titulo']) ?>
            </a>
            <span>›</span>
            <span><?= htmlspecialchars($leccion['titulo']) ?></span>
        </div>

    
    <div class="leccion-header">
        <div class="leccion-info">
            <h1 class="leccion-titulo"><?= htmlspecialchars($leccion['titulo']) ?></h1>
            <div class="leccion-meta">
                <span class="leccion-tipo <?= $leccion['tipo'] ?>"><?= ucfirst($leccion['tipo']) ?></span>
                <span class="leccion-orden">Lección <?= $leccion['orden'] ?></span>
            </div>
        </div>
    </div>

    
    <div class="leccion-contenido">
        <?php 
        $contenido = $leccion['contenido'] ?? '';
        if (strpos($contenido, '<html') !== false && strpos($contenido, '<body') !== false) {
            preg_match('/<body[^>]*>(.*?)<\/body>/is', $contenido, $matches);
            if (!empty($matches[1])) {
                $contenido = $matches[1];
            }
        }
        $contenido = str_replace(
            [
                '<p class="modulo-descripcion"></p>',
                '<h1 class="modulo-titulo"></h1>',
                '&lt;p class=&quot;modulo-descripcion&quot;&gt;&lt;/p&gt;',
                '&lt;h1 class=&quot;modulo-titulo&quot;&gt;&lt;/h1&gt;'
            ],
            '',
            $contenido
        );
        echo $contenido;
        ?>

        
        <?php if ($tiene_embebido && !empty($leccion_recurso_url_public)): ?>
            <?php
            $leccion_recurso_url = $leccion_recurso_url_public;
            $leccion_recurso_es_imagen = preg_match('/\.(jpe?g|png|gif|webp)(\?.*)?$/i', $leccion_recurso_url)
                && (strpos($leccion_recurso_url, '/uploads/') !== false || strpos($leccion_recurso_url, 'serve_uploads.php?') !== false);
            ?>
            <div class="contenido-modulo-section">
                <h2 class="seccion-titulo"><i class="icon-file-text"></i> Contenido de la Lección</h2>
                <div style="width: 100%; height: 70vh; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;">
                    <?php if ($leccion_recurso_es_imagen): ?>
                        <img src="<?= htmlspecialchars($leccion_recurso_url_public) ?>" style="width: 100%; height: auto; border: 0;" alt="<?= htmlspecialchars($leccion['titulo'] ?? 'Contenido de la Lección', ENT_QUOTES, 'UTF-8') ?>">
                    <?php else: ?>
                        <iframe src="<?= htmlspecialchars($leccion_recurso_url_public) ?>" style="width: 100%; height: 100%; border: 0;" title="<?= htmlspecialchars($leccion['titulo'] ?? 'Contenido de la Lección', ENT_QUOTES, 'UTF-8') ?>"></iframe>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$tiene_embebido && !empty($leccion['recurso_url']) && in_array($leccion_recurso_extension, ['html', 'htm'], true)): ?>
            <div class="contenido-modulo-section">
                <h2 class="seccion-titulo"><i class="icon-file-text"></i> Contenido de la Lección</h2>
                <div style="width: 100%; height: 70vh; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;">
                    <iframe src="<?= htmlspecialchars($leccion_recurso_url_public) ?>" style="width: 100%; height: 100%; border: 0;" title="<?= htmlspecialchars($leccion['titulo'] ?? 'Contenido de la Lección', ENT_QUOTES, 'UTF-8') ?>"></iframe>
                </div>
            </div>
        <?php elseif (!$tiene_embebido && !empty($leccion['recurso_url'])): ?>
            <div class="leccion-resource">
                <?php
                $extension = $leccion_recurso_extension;
                $es_archivo_local = strpos($leccion['recurso_url'], '/imt-cursos/uploads/') === 0;
                $es_url_externa = filter_var($leccion['recurso_url'], FILTER_VALIDATE_URL);
                
                // Determinar el tipo de recurso y el icono
                $tipo_recurso = 'Archivo';
                $icono = '📎';
                
                if ($es_url_externa) {
                    $tipo_recurso = 'Enlace externo';
                    $icono = '🔗';
                } else {
                    switch($extension) {
                        case 'pdf':
                            $tipo_recurso = 'Documento PDF';
                            $icono = '📄';
                            break;
                        case 'doc':
                        case 'docx':
                            $tipo_recurso = 'Documento Word';
                            $icono = '📝';
                            break;
                        case 'ppt':
                        case 'pptx':
                            $tipo_recurso = 'Presentación';
                            $icono = '📊';
                            break;
                        case 'mp4':
                        case 'avi':
                        case 'mov':
                        case 'webm':
                            $tipo_recurso = 'Video';
                            $icono = '🎥';
                            break;
                        case 'jpg':
                        case 'jpeg':
                        case 'png':
                        case 'gif':
                        case 'webp':
                            $tipo_recurso = 'Imagen';
                            $icono = '🖼️';
                            break;
                    }
                }
                ?>
                
                <a href="<?= BASE_URL ?>/estudiante/ver_recurso.php?url=<?= urlencode($leccion['recurso_url']) ?>&titulo=<?= urlencode($leccion['titulo']) ?>&leccion_id=<?= $leccion_id ?>" 
                   class="resource-link" target="_blank">
                    <div class="resource-icon"><?= $icono ?></div>
                    <div class="resource-info">
                        <span class="resource-title">Ver recurso adjunto</span>
                        <span class="resource-type"><?= $tipo_recurso ?></span>
                    </div>
                </a>
            </div>
        <?php endif; ?>

        <?php if ($tiene_embebido && !empty($leccion['recurso_url']) && !in_array($leccion_recurso_extension, ['html', 'htm'], true)): ?>
            <div class="leccion-resource">
                <?php
                $extension = $leccion_recurso_extension;
                $es_archivo_local = strpos($leccion['recurso_url'], '/imt-cursos/uploads/') === 0;
                $es_url_externa = filter_var($leccion['recurso_url'], FILTER_VALIDATE_URL);
                
                $tipo_recurso = 'Archivo';
                $icono = '📎';
                
                if ($es_url_externa) {
                    $tipo_recurso = 'Enlace externo';
                    $icono = '🔗';
                } else {
                    switch($extension) {
                        case 'pdf':
                            $tipo_recurso = 'Documento PDF';
                            $icono = '📄';
                            break;
                        case 'doc':
                        case 'docx':
                            $tipo_recurso = 'Documento Word';
                            $icono = '📝';
                            break;
                        case 'ppt':
                        case 'pptx':
                            $tipo_recurso = 'Presentación';
                            $icono = '📊';
                            break;
                        case 'mp4':
                        case 'avi':
                        case 'mov':
                        case 'webm':
                            $tipo_recurso = 'Video';
                            $icono = '🎥';
                            break;
                        case 'jpg':
                        case 'jpeg':
                        case 'png':
                        case 'gif':
                        case 'webp':
                            $tipo_recurso = 'Imagen';
                            $icono = '🖼️';
                            break;
                    }
                }
                ?>
                
                <a href="<?= BASE_URL ?>/estudiante/ver_recurso.php?url=<?= urlencode($leccion['recurso_url']) ?>&titulo=<?= urlencode($leccion['titulo']) ?>&leccion_id=<?= $leccion_id ?>" 
                   class="resource-link" target="_blank">
                    <div class="resource-icon"><?= $icono ?></div>
                    <div class="resource-info">
                        <span class="resource-title">Ver recurso adjunto</span>
                        <span class="resource-type"><?= $tipo_recurso ?></span>
                    </div>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Navegación entre lecciones -->
    <div class="leccion-navegacion">
        <?php if ($leccion_anterior): ?>
            <a href="<?= BASE_URL ?>/estudiante/leccion.php?id=<?= $leccion_anterior['id'] ?>" 
               class="btn-navegacion anterior">
                ← Lección anterior: <?= htmlspecialchars($leccion_anterior['titulo']) ?>
            </a>
        <?php endif; ?>
        
        <?php if ($leccion_siguiente): ?>
            <a href="<?= BASE_URL ?>/estudiante/leccion.php?id=<?= $leccion_siguiente['id'] ?>" 
               class="btn-navegacion siguiente">
                Lección siguiente: <?= htmlspecialchars($leccion_siguiente['titulo']) ?> →
            </a>
        <?php endif; ?>
    </div>

    <!-- Botón para volver al subtema -->
    <div class="leccion-acciones">
        <a href="<?= BASE_URL ?>/estudiante/subtema_contenido.php?id=<?= $leccion['subtema_id'] ?>" 
           class="btn-volver">
            ← Volver al subtema
        </a>
    </div>
    </div> <!-- /.contenido-principal -->
</div> <!-- /.contenido-con-sidebar -->

<style>
.breadcrumb {
    margin-bottom: 20px;
    padding: 10px 0;
    color: #7f8c8d;
    font-size: 0.9rem;
}

.breadcrumb a {
    color: var(--estudiante-primary);
    text-decoration: none;
}

.breadcrumb a:hover {
    text-decoration: underline;
}

.breadcrumb span {
    margin: 0 8px;
}

.leccion-header {
    background: linear-gradient(135deg, var(--estudiante-primary), var(--estudiante-secondary));
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
}

.leccion-titulo {
    font-size: 2rem;
    margin-bottom: 15px;
    font-weight: 600;
    color: white;
}

.leccion-meta {
    display: flex;
    gap: 15px;
    align-items: center;
}

.leccion-tipo {
    background: rgba(255, 255, 255, 0.2);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.leccion-orden {
    font-size: 0.9rem;
    opacity: 0.9;
}

.leccion-contenido {
    margin-bottom: 40px;
}

.leccion-contenido {
    display: flow-root;
    overflow: hidden;
}

.leccion-contenido * {
    max-width: 100%;
    box-sizing: border-box;
}

.leccion-contenido img,
.leccion-contenido video,
.leccion-contenido iframe,
.leccion-contenido table {
    max-width: 100%;
    height: auto;
}

.leccion-contenido table {
    width: 100%;
}

.leccion-contenido pre,
.leccion-contenido code {
    white-space: pre-wrap;
    word-break: break-word;
}

.leccion-navegacion {
    display: flex;
    gap: 20px;
    margin: 30px 0;
    flex-wrap: wrap;
    justify-content: space-between;
}

.btn-navegacion {
    flex: 1;
    min-width: 250px;
    max-width: 45%;
    padding: 15px 20px;
    background: white;
    border: 2px solid #e8ecef;
    border-radius: 8px;
    text-decoration: none;
    color: #2c3e50;
    transition: all 0.3s ease;
    display: block;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.btn-navegacion:hover {
    border-color: var(--estudiante-primary);
    background: var(--estudiante-light);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.2);
}

.btn-navegacion.anterior {
    text-align: left;
}

.btn-navegacion.siguiente {
    text-align: right;
    margin-left: auto;
}

/* Si solo hay un botón, centrarlo */
.leccion-navegacion:has(.btn-navegacion:only-child) {
    justify-content: center;
}

.leccion-navegacion .btn-navegacion:only-child {
    max-width: 400px;
}

.leccion-acciones {
    text-align: center;
    padding: 20px 0;
}

.btn-volver {
    display: inline-block;
    padding: 12px 24px;
    background: #6c757d;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.btn-volver:hover {
    background: #5a6268;
    transform: translateY(-2px);
}
</style>

<?php require __DIR__ . '/../partials/footer.php'; ?>
