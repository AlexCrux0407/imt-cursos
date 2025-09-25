<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Estudiante – Contenido del Curso';

$curso_id = $_GET['id'] ?? 0;

// Verificar que el curso pertenece al estudiante
$stmt = $conn->prepare("
    SELECT c.*
    FROM cursos c
    INNER JOIN inscripciones i ON c.id = i.curso_id
    WHERE c.id = :curso_id AND i.estudiante_id = :estudiante_id
");
$stmt->execute([':curso_id' => $curso_id, ':estudiante_id' => $_SESSION['user_id']]);
$curso = $stmt->fetch();

if (!$curso) {
    header('Location: /imt-cursos/public/estudiante/mis_cursos.php?error=curso_no_encontrado');
    exit;
}

// Obtener módulos del curso
$stmt = $conn->prepare("
    SELECT m.*
    FROM modulos m
    WHERE m.curso_id = :curso_id
    ORDER BY m.orden ASC
");
$stmt->execute([':curso_id' => $curso_id]);
$modulos = $stmt->fetchAll();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="/imt-cursos/public/styles/css/curso_contenido.css">

<div class="contenido">
    <div class="curso-header">
        <h1 class="curso-title"><?= htmlspecialchars($curso['titulo']) ?></h1>
        <p class="curso-desc"><?= htmlspecialchars($curso['descripcion']) ?></p>
    </div>

    <!-- Navegación -->
    <div class="curso-breadcrumb">
        <div class="div-fila-alt-start" style="gap: 10px;">
            <a href="/imt-cursos/public/estudiante/mis_cursos.php" class="breadcrumb-link">Mis Cursos</a>
            <span class="breadcrumb-separator"> > </span>
            <span class="breadcrumb-current"><?= htmlspecialchars($curso['titulo']) ?></span>
        </div>
    </div>

    <!-- Contenido del Curso -->
    <div class="curso-contenido">
        <h2 class="contenido-title">Contenido del Curso</h2>

        <?php foreach ($modulos as $modulo): ?>
            <div class="modulo-card">
                <div class="modulo-header">
                    <h3 class="modulo-title"><?= htmlspecialchars($modulo['titulo']) ?></h3>
                </div>

                <div class="modulo-temas">
                    <?php
                    // Obtener temas del módulo
                    $stmt = $conn->prepare("
                        SELECT t.*
                        FROM temas t
                        WHERE t.modulo_id = :modulo_id
                        ORDER BY t.orden ASC
                    ");
                    $stmt->execute([':modulo_id' => $modulo['id']]);
                    $temas = $stmt->fetchAll();

                    foreach ($temas as $tema):
                    ?>
                        <div class="tema-card">
                            <div class="tema-info">
                                <h4 class="tema-title"><?= htmlspecialchars($tema['titulo']) ?></h4>
                                <p class="tema-desc">
                                    <?= htmlspecialchars(substr($tema['descripcion'], 0, 100)) ?><?= strlen($tema['descripcion']) > 100 ? '...' : '' ?>
                                </p>
                            </div>

                            <div class="tema-lecciones">
                                <?php
                                // Obtener lecciones del tema
                                $stmt = $conn->prepare("
                                    SELECT l.*
                                    FROM lecciones l
                                    WHERE l.tema_id = :tema_id
                                    ORDER BY l.orden ASC
                                ");
                                $stmt->execute([':tema_id' => $tema['id']]);
                                $lecciones = $stmt->fetchAll();

                                foreach ($lecciones as $leccion):
                                ?>
                                    <div class="leccion-card">
                                        <div class="leccion-info">
                                            <span class="leccion-orden"><?= $leccion['orden'] ?></span>
                                            <h5 class="leccion-title"><?= htmlspecialchars($leccion['titulo']) ?></h5>
                                        </div>

                                        <div class="leccion-actions">
                                            <a href="/imt-cursos/public/estudiante/ver_leccion.php?id=<?= $leccion['id'] ?>&curso_id=<?= $curso_id ?>" class="btn-ver-leccion">
                                                Ver Lección
                                            </a>
                                        </div>
                                    </div>

                                    <?php if ($leccion['recurso_url']): ?>
                                        <div class="leccion-resource">
                                            <a href="/imt-cursos/public/estudiante/ver_recurso.php?url=<?= urlencode($leccion['recurso_url']) ?>&tipo=<?= urlencode($leccion['tipo']) ?>&titulo=<?= urlencode($leccion['titulo']) ?>&leccion_id=<?= $leccion['id'] ?>" 
                                               class="resource-link" target="_blank">
                                                <div class="resource-icon">
                                                    <?php
                                                    $ext = strtolower(pathinfo($leccion['recurso_url'], PATHINFO_EXTENSION));
                                                    if (in_array($ext, ['pdf'])) {
                                                        echo '📄';
                                                    } elseif (in_array($ext, ['mp4', 'avi', 'mov'])) {
                                                        echo '🎥';
                                                    } elseif (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                                                        echo '🖼️';
                                                    } else {
                                                        echo '📎';
                                                    }
                                                    ?>
                                                </div>
                                                <div class="resource-info">
                                                    <span class="resource-title">Ver Recurso</span>
                                                    <small class="resource-type"><?= ucfirst($leccion['tipo']) ?></small>
                                                </div>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>