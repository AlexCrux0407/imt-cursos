<?php
// Vista Estudiante – Visor de recursos (PDF, video, imagen, URL)
require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Estudiante – Ver Recurso';

$recurso_url = $_GET['url'] ?? '';
$tipo = $_GET['tipo'] ?? 'documento';
$titulo = $_GET['titulo'] ?? 'Recurso del Curso';
$leccion_id = $_GET['leccion_id'] ?? 0;

if (empty($recurso_url)) {
    header('Location: ' . BASE_URL . '/estudiante/dashboard.php?error=recurso_no_encontrado');
    exit;
}

// Verificar que el estudiante tiene acceso a este recurso
if ($leccion_id) {
    $stmt = $conn->prepare("
        SELECT l.titulo, c.titulo as curso_titulo
        FROM lecciones l
        INNER JOIN subtemas st ON l.subtema_id = st.id
        INNER JOIN temas t ON st.tema_id = t.id
        INNER JOIN modulos m ON t.modulo_id = m.id
        INNER JOIN cursos c ON m.curso_id = c.id
        INNER JOIN inscripciones i ON c.id = i.curso_id
        WHERE l.id = :leccion_id AND i.usuario_id = :estudiante_id
    ");
    $stmt->execute([':leccion_id' => $leccion_id, ':estudiante_id' => $_SESSION['user_id']]);
    $leccion = $stmt->fetch();
    
    if (!$leccion) {
        header('Location: ' . BASE_URL . '/estudiante/dashboard.php?error=acceso_denegado');
        exit;
    }
    
    $titulo = $leccion['titulo'];
}

$extension = strtolower(pathinfo($recurso_url, PATHINFO_EXTENSION));

// Normalizar URL del recurso a absoluta basada en BASE_URL cuando sea relativa
$recurso_url_public = $recurso_url;
if (!empty($recurso_url) && !filter_var($recurso_url, FILTER_VALIDATE_URL)) {
    if (strpos($recurso_url, '/') === 0) {
        // Ruta absoluta relativa al dominio
        $recurso_url_public = rtrim(BASE_URL, '/') . $recurso_url;
    } else {
        // Ruta relativa (ej: uploads/cursos/...)
        $recurso_url_public = rtrim(BASE_URL, '/') . '/' . $recurso_url;
    }
}

// Detectar si es un archivo local en /uploads
$path_en_url = parse_url($recurso_url_public, PHP_URL_PATH) ?: '';
$host_en_url = parse_url($recurso_url_public, PHP_URL_HOST) ?: '';
$host_base = parse_url(BASE_URL, PHP_URL_HOST) ?: '';
// Considerar recursos locales aunque BASE_URL tenga prefijo de subcarpeta (ej: /imt-cursos)
$es_archivo_local = ($host_en_url === $host_base) && (strpos($path_en_url, '/uploads/') !== false);

// Si es un archivo local bajo /uploads, usar el proxy seguro para servirlo
if ($es_archivo_local) {
    $pos = strpos($path_en_url, '/uploads/');
    $rel = substr($path_en_url, $pos + strlen('/uploads/'));
    if (strpos($rel, 'cursos/') === 0) {
        $recurso_url_public = rtrim(BASE_URL, '/') . '/serve_uploads.php?path=' . rawurlencode($rel);
    }
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/estudiante.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/resource-viewer.css">

<div class="resource-viewer">
    <div class="resource-header">
        <div class="resource-header-left">
            <img src="<?= BASE_URL ?>/styles/logos/Logo_blanco.png" alt="IMT Logo" class="resource-logo">
            <h1 class="resource-title"><?= htmlspecialchars($titulo) ?></h1>
        </div>
        <div class="resource-controls">
            <?php if ($es_archivo_local): ?>
                <a href="<?= htmlspecialchars($recurso_url_public) ?>" download class="btn-download">
                    📥 Descargar
                </a>
            <?php endif; ?>
            <button onclick="window.close(); if(!window.closed) history.back();" class="btn-close">
                <span>✕</span> Cerrar
            </button>
        </div>
    </div>
    
    <div class="resource-content">
            <?php if (in_array($extension, ['pdf'])): ?>
                <iframe class="resource-frame" 
                    src="<?= htmlspecialchars($recurso_url_public) ?>#toolbar=1&navpanes=1&scrollbar=1&view=FitH" 
                    title="Visualizador de PDF">
                </iframe>
            
        <?php elseif (in_array($extension, ['mp4', 'avi', 'mov', 'webm', 'mkv'])): ?>
            <?php 
            $mime_map = [
                'mp4' => 'video/mp4',
                'webm' => 'video/webm',
                'mov' => 'video/quicktime',
                'avi' => 'video/x-msvideo',
                'mkv' => 'video/x-matroska',
            ];
            $video_mime = $mime_map[$extension] ?? 'video/mp4';
            ?>
            <video class="resource-frame" controls preload="metadata" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                <source src="<?= htmlspecialchars($recurso_url_public) ?>" type="<?= $video_mime ?>">
                Tu navegador no soporta la reproducción de video.
            </video>
            
        <?php elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
            <img class="resource-frame" 
                 src="<?= htmlspecialchars($recurso_url_public) ?>" 
                 alt="<?= htmlspecialchars($titulo) ?>"
                 style="max-width: 100%; max-height: 100%; object-fit: contain;">
            
        <?php elseif (filter_var($recurso_url_public, FILTER_VALIDATE_URL)): ?>
            <div class="loading-spinner"></div>
            <iframe class="resource-frame" 
                    src="<?= htmlspecialchars($recurso_url_public) ?>" 
                    title="<?= htmlspecialchars($titulo) ?>"
                    allowfullscreen="allowfullscreen" 
                    webkitallowfullscreen="webkitallowfullscreen" 
                    mozallowfullscreen="mozallowfullscreen"
                    allow="autoplay *; fullscreen *">
            </iframe>
            
        <?php else: ?>
            <div class="resource-message">
                <h2>Vista previa no disponible</h2>
                <p>Este tipo de archivo no se puede visualizar en línea.</p>
                <p>Archivo: <?= htmlspecialchars(basename($path_en_url)) ?></p>
                <?php if ($es_archivo_local): ?>
                    <br>
                    <a href="<?= htmlspecialchars($recurso_url_public) ?>" download class="btn-download" style="display: inline-block; margin-top: 20px;">
                        📥 Descargar Archivo
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Aplicar clase especial al body para el visor
document.addEventListener('DOMContentLoaded', function() {
    // Agregar clase especial al body
    document.body.classList.add('resource-viewer-active');
    
    // Asegurar estilos del visor
    const resourceViewer = document.querySelector('.resource-viewer');
    if (resourceViewer) {
        resourceViewer.style.position = 'fixed';
        resourceViewer.style.top = '0';
        resourceViewer.style.left = '0';
        resourceViewer.style.width = '100vw';
        resourceViewer.style.height = '100vh';
        resourceViewer.style.zIndex = '9999';
    }
});

// Limpiar al cerrar
window.addEventListener('beforeunload', function() {
    document.body.classList.remove('resource-viewer-active');
});

// Manejar tecla ESC para cerrar
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        history.back();
    }
});

// Mostrar mensaje de carga para iframes externos
document.querySelectorAll('iframe').forEach(iframe => {
    iframe.addEventListener('load', function() {
        const spinner = document.querySelector('.loading-spinner');
        if (spinner) {
            spinner.style.display = 'none';
        }
    });
});
</script>



<script>
// Aplicar clase especial al body para el visor
document.addEventListener('DOMContentLoaded', function() {
    // Agregar clase especial al body
    document.body.classList.add('resource-viewer-active');
    
    // Asegurar estilos del visor
    const resourceViewer = document.querySelector('.resource-viewer');
    if (resourceViewer) {
        resourceViewer.style.position = 'fixed';
        resourceViewer.style.top = '0';
        resourceViewer.style.left = '0';
        resourceViewer.style.width = '100vw';
        resourceViewer.style.height = '100vh';
        resourceViewer.style.zIndex = '9999';
        resourceViewer.style.backgroundColor = '#fff';
    }
    
    // Ocultar navegación y otros elementos
    const nav = document.querySelector('nav');
    const contenido = document.querySelector('.contenido');
    
    if (nav) nav.style.display = 'none';
    if (contenido) contenido.style.display = 'none';
});

// Limpiar al cerrar
window.addEventListener('beforeunload', function() {
    document.body.classList.remove('resource-viewer-active');
});

// Manejar tecla ESC para cerrar
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        history.back();
    }
});

// Mostrar mensaje de carga para iframes externos
document.querySelectorAll('iframe').forEach(iframe => {
    iframe.addEventListener('load', function() {
        const spinner = document.querySelector('.loading-spinner');
        if (spinner) {
            spinner.style.display = 'none';
        }
    });
    
    iframe.addEventListener('error', function() {
        console.error('Error cargando el recurso:', iframe.src);
        const resourceContent = document.querySelector('.resource-content');
        if (resourceContent) {
            resourceContent.innerHTML = `
                <div class="resource-message">
                    <h2>Error al cargar el recurso</h2>
                    <p>No se pudo cargar el archivo solicitado.</p>
                    <p>Verifique que el archivo existe y es accesible.</p>
                </div>
            `;
        }
    });
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
