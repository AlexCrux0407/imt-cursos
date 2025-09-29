<?php
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
    INNER JOIN subtemas st ON l.subtema_id = st.id
    INNER JOIN temas t ON st.tema_id = t.id
    INNER JOIN modulos m ON t.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    INNER JOIN inscripciones i ON c.id = i.curso_id
    WHERE l.id = :leccion_id AND i.usuario_id = :estudiante_id
    LIMIT 1
");
$stmt->execute([':leccion_id' => $leccion_id, ':estudiante_id' => $estudiante_id]);
$leccion = $stmt->fetch();

if (!$leccion) {
    header('Location: ' . BASE_URL . '/estudiante/dashboard.php?error=acceso_denegado');
    exit;
}

// Obtener lecciones del mismo subtema para navegación
$stmt = $conn->prepare("
    SELECT id, titulo, orden 
    FROM lecciones 
    WHERE subtema_id = :subtema_id 
    ORDER BY orden ASC
");
$stmt->execute([':subtema_id' => $leccion['subtema_id']]);
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

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/estudiante.css">

<div class="contenido">
    <!-- Breadcrumb -->
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

    <!-- Header de la lección -->
    <div class="leccion-header">
        <div class="leccion-info">
            <h1 class="leccion-titulo"><?= htmlspecialchars($leccion['titulo']) ?></h1>
            <div class="leccion-meta">
                <span class="leccion-tipo <?= $leccion['tipo'] ?>"><?= ucfirst($leccion['tipo']) ?></span>
                <span class="leccion-orden">Lección <?= $leccion['orden'] ?></span>
            </div>
        </div>
    </div>

    <!-- Contenido de la lección -->
    <div class="leccion-contenido">
        <div class="contenido-texto">
            <?= nl2br(htmlspecialchars($leccion['contenido'])) ?>
        </div>

        <!-- Recurso adjunto -->
        <?php if (!empty($leccion['recurso_url'])): ?>
            <div class="leccion-resource">
                <?php
                $extension = strtolower(pathinfo($leccion['recurso_url'], PATHINFO_EXTENSION));
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
</div>

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

.leccion-navegacion {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.btn-navegacion {
    flex: 1;
    min-width: 200px;
    padding: 15px 20px;
    background: white;
    border: 2px solid #e8ecef;
    border-radius: 8px;
    text-decoration: none;
    color: #2c3e50;
    transition: all 0.3s ease;
    text-align: center;
}

.btn-navegacion:hover {
    border-color: var(--estudiante-primary);
    background: var(--estudiante-light);
    transform: translateY(-2px);
}

.btn-navegacion.anterior {
    text-align: left;
}

.btn-navegacion.siguiente {
    text-align: right;
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