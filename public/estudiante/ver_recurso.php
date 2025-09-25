<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Estudiante – Ver Recurso';

$recurso_url = $_GET['url'] ?? '';
$tipo = $_GET['tipo'] ?? 'documento';
$titulo = $_GET['titulo'] ?? 'Recurso del Curso';
$leccion_id = $_GET['leccion_id'] ?? 0;

if (empty($recurso_url)) {
    header('Location: /imt-cursos/public/estudiante/dashboard.php?error=recurso_no_encontrado');
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
        header('Location: /imt-cursos/public/estudiante/dashboard.php?error=acceso_denegado');
        exit;
    }
    
    $titulo = $leccion['titulo'];
}

$extension = pathinfo($recurso_url, PATHINFO_EXTENSION);
$es_archivo_local = strpos($recurso_url, '/imt-cursos/uploads/') === 0;

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="/imt-cursos/public/styles/css/estudiante.css">
<link rel="stylesheet" href="/imt-cursos/public/styles/css/resource-viewer.css">

<div class="resource-viewer">
    <div class="resource-header">
        <h1 class="resource-title"><?= htmlspecialchars($titulo) ?></h1>
        <div class="resource-controls">
            <?php if ($es_archivo_local): ?>
                <a href="<?= htmlspecialchars($recurso_url) ?>" download class="btn-download">
                    📥 Descargar
                </a>
            <?php endif; ?>
            <a href="javascript:history.back()" class="btn-close">
                ✕ Cerrar
            </a>
        </div>
    </div>
    
    <div class="resource-content">
        <?php if (in_array(strtolower($extension), ['pdf'])): ?>
            <!-- PDF Viewer -->
            <iframe class="resource-frame" 
                    src="<?= htmlspecialchars($recurso_url) ?>#toolbar=1&navpanes=1&scrollbar=1&view=FitH" 
                    title="Visualizador de PDF">
            </iframe>
            
        <?php elseif (in_array(strtolower($extension), ['mp4', 'avi', 'mov', 'webm'])): ?>
            <!-- Video Player -->
            <video class="resource-frame" controls preload="metadata" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                <source src="<?= htmlspecialchars($recurso_url) ?>" type="video/<?= $extension ?>">
                Tu navegador no soporta la reproducción de video.
            </video>
            
        <?php elseif (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
            <!-- Image Viewer -->
            <img class="resource-frame" 
                 src="<?= htmlspecialchars($recurso_url) ?>" 
                 alt="<?= htmlspecialchars($titulo) ?>"
                 style="max-width: 100%; max-height: 100%; object-fit: contain;">
            
        <?php elseif (filter_var($recurso_url, FILTER_VALIDATE_URL)): ?>
            <!-- External URL -->
            <div class="loading-spinner"></div>
            <iframe class="resource-frame" 
                    src="<?= htmlspecialchars($recurso_url) ?>" 
                    title="<?= htmlspecialchars($titulo) ?>"
                    allowfullscreen="allowfullscreen" 
                    webkitallowfullscreen="webkitallowfullscreen" 
                    mozallowfullscreen="mozallowfullscreen"
                    allow="autoplay *; fullscreen *">
            </iframe>
            
        <?php else: ?>
            <!-- Unsupported file type -->
            <div class="resource-message">
                <h2>Vista previa no disponible</h2>
                <p>Este tipo de archivo no se puede visualizar en línea.</p>
                <p>Archivo: <?= htmlspecialchars(basename($recurso_url)) ?></p>
                <?php if ($es_archivo_local): ?>
                    <br>
                    <a href="<?= htmlspecialchars($recurso_url) ?>" download class="btn-download" style="display: inline-block; margin-top: 20px;">
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

<?php require __DIR__ . '/../partials/footer.php'; ?>
            <?php if ($es_archivo_local): ?>
                <a href="<?= htmlspecialchars($recurso_url) ?>" download class="btn-download">
                    📥 Descargar
                </a>
            <?php endif; ?>
            <a href="javascript:history.back()" class="btn-close">
                ✕ Cerrar
            </a>
        </div>
    </div>
    
    <div class="resource-content">
        <?php if (in_array(strtolower($extension), ['pdf'])): ?>
            <!-- PDF Viewer -->
            <iframe class="resource-frame" 
                    src="<?= htmlspecialchars($recurso_url) ?>#toolbar=1&navpanes=1&scrollbar=1&view=FitH" 
                    title="Visualizador de PDF">
            </iframe>
            
        <?php elseif (in_array(strtolower($extension), ['mp4', 'avi', 'mov', 'webm'])): ?>
            <!-- Video Player -->
            <video class="resource-frame" controls preload="metadata" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                <source src="<?= htmlspecialchars($recurso_url) ?>" type="video/<?= $extension ?>">
                Tu navegador no soporta la reproducción de video.
            </video>
            
        <?php elseif (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
            <!-- Image Viewer -->
            <img class="resource-frame" 
                 src="<?= htmlspecialchars($recurso_url) ?>" 
                 alt="<?= htmlspecialchars($titulo) ?>"
                 style="max-width: 100%; max-height: 100%; object-fit: contain;">
            
        <?php elseif (filter_var($recurso_url, FILTER_VALIDATE_URL)): ?>
            <!-- External URL -->
            <div class="loading-spinner"></div>
            <iframe class="resource-frame" 
                    src="<?= htmlspecialchars($recurso_url) ?>" 
                    title="<?= htmlspecialchars($titulo) ?>"
                    allowfullscreen="allowfullscreen" 
                    webkitallowfullscreen="webkitallowfullscreen" 
                    mozallowfullscreen="mozallowfullscreen"
                    allow="autoplay *; fullscreen *">
            </iframe>
            
        <?php else: ?>
            <!-- Unsupported file type -->
            <div class="resource-message">
                <h2>Vista previa no disponible</h2>
                <p>Este tipo de archivo no se puede visualizar en línea.</p>
                <p>Archivo: <?= htmlspecialchars(basename($recurso_url)) ?></p>
                <?php if ($es_archivo_local): ?>
                    <br>
                    <a href="<?= htmlspecialchars($recurso_url) ?>" download class="btn-download" style="display: inline-block; margin-top: 20px;">
                        📥 Descargar Archivo
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Ocultar el contenido del body para mostrar solo el visor
document.addEventListener('DOMContentLoaded', function() {
    // Ocultar navegación y otros elementos
    const nav = document.querySelector('nav');
    const contenido = document.querySelector('.contenido');
    
    if (nav) nav.style.display = 'none';
    if (contenido) contenido.style.display = 'none';
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

<?php require __DIR__ . '/../partials/footer.php'; ?>
