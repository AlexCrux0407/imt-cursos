<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Subtemas del Tema';

$tema_id = $_GET['id'] ?? 0;
$modulo_id = $_GET['modulo_id'] ?? 0;
$curso_id = $_GET['curso_id'] ?? 0;

// Verificar que el tema pertenece a un módulo de un curso del docente
$stmt = $conn->prepare("
    SELECT t.*, m.titulo as modulo_titulo, c.titulo as curso_titulo
    FROM temas t
    INNER JOIN modulos m ON t.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE t.id = :tema_id AND c.creado_por = :docente_id
");
$stmt->execute([':tema_id' => $tema_id, ':docente_id' => $_SESSION['user_id']]);
$tema = $stmt->fetch();

if (!$tema) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=tema_no_encontrado');
    exit;
}

// Obtener subtemas del tema
$stmt = $conn->prepare("
    SELECT s.*, COUNT(l.id) as total_lecciones
    FROM subtemas s
    LEFT JOIN lecciones l ON s.id = l.subtema_id
    WHERE s.tema_id = :tema_id
    GROUP BY s.id
    ORDER BY s.orden ASC
");
$stmt->execute([':tema_id' => $tema_id]);
$subtemas = $stmt->fetchAll();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/subtemas.css">

<div class="contenido">
    <div class="subtemas-header">
        <div class="div-fila-alt-start">
            <div>
                <h1 class="subtemas-title">Subtemas del Tema</h1>
                <p class="subtemas-subtitle"><?= htmlspecialchars($tema['titulo']) ?> - <?= htmlspecialchars($tema['modulo_titulo']) ?></p>
            </div>
            <button onclick="mostrarFormularioNuevoSubtema()" class="subtemas-btn-nuevo">
                + Nuevo Subtema
            </button>
        </div>
    </div>

    <!-- Navegación -->
    <div class="subtemas-breadcrumb">
        <div class="div-fila-alt-start" style="gap: 10px;">
            <a href="<?= BASE_URL ?>/docente/admin_cursos.php" class="breadcrumb-link">Mis Cursos</a>
            <span class="breadcrumb-separator"> > </span>
            <a href="<?= BASE_URL ?>/docente/modulos_curso.php?id=<?= $curso_id ?>" class="breadcrumb-link">Módulos</a>
            <span class="breadcrumb-separator"> > </span>
            <a href="<?= BASE_URL ?>/docente/temas_modulo.php?id=<?= $modulo_id ?>&curso_id=<?= $curso_id ?>" class="breadcrumb-link">Temas</a>
            <span class="breadcrumb-separator"> > </span>
            <span class="breadcrumb-current">Subtemas</span>
        </div>
    </div>

    <!-- Lista de Subtemas -->
    <div class="subtemas-container">
        <h2 class="subtemas-section-title">Subtemas</h2>
        
        <?php if (empty($subtemas)): ?>
            <div class="subtemas-empty">
                <img src="<?= BASE_URL ?>/styles/iconos/config.png" class="empty-icon">
                <h3>No hay subtemas creados</h3>
                <p>Comienza agregando el primer subtema a este tema</p>
                <button onclick="mostrarFormularioNuevoSubtema()" class="btn-crear-primero">
                    Crear Primer Subtema
                </button>
            </div>
        <?php else: ?>
            <div class="subtemas-grid">
                <?php foreach ($subtemas as $subtema): ?>
                    <div class="subtema-card">
                        <div class="div-fila" style="gap: 20px; align-items: center;">
                            <div class="subtema-orden"><?= $subtema['orden'] ?></div>
                            
                            <div style="flex: 1;">
                                <h4 class="subtema-title"><?= htmlspecialchars($subtema['titulo']) ?></h4>
                                <p class="subtema-desc">
                                    <?= htmlspecialchars(substr($subtema['descripcion'], 0, 100)) ?><?= strlen($subtema['descripcion']) > 100 ? '...' : '' ?>
                                </p>
                                <div class="div-fila-alt-start" style="gap: 15px;">
                                    <span class="subtema-lecciones"><?= $subtema['total_lecciones'] ?> lecciones</span>
                                    <span class="subtema-fecha">Creado: <?= date('d/m/Y', strtotime($subtema['created_at'])) ?></span>
                                </div>
                            </div>
                            
                            <div class="subtema-actions">
                                <button onclick="gestionarLecciones(<?= $subtema['id'] ?>)" class="btn-lecciones">
                                    Lecciones
                                </button>
                                <button onclick="editarSubtema(<?= $subtema['id'] ?>)" class="btn-editar">
                                    Editar
                                </button>
                                <button onclick="confirmarEliminarSubtema(<?= $subtema['id'] ?>, '<?= addslashes($subtema['titulo']) ?>')" class="btn-eliminar">
                                    Eliminar
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para Nuevo Subtema -->
<div id="modalNuevoSubtema" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Nuevo Subtema</h2>
            <button onclick="cerrarModal()" class="modal-close">&times;</button>
        </div>
        
        <form method="POST" action="<?= BASE_URL ?>/docente/procesar_subtema.php">
            <input type="hidden" name="tema_id" value="<?= $tema_id ?>">
            <input type="hidden" name="modulo_id" value="<?= $modulo_id ?>">
            <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
            
            <div class="form-group">
                <label class="form-label">Título del Subtema</label>
                <input type="text" name="titulo" required class="form-input">
            </div>
            
            <div class="form-group">
                <label class="form-label">Descripción</label>
                <textarea name="descripcion" rows="3" class="form-textarea"></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Orden</label>
                <input type="number" name="orden" value="<?= count($subtemas) + 1 ?>" min="1" required class="form-input">
            </div>
            
            <div class="modal-actions">
                <button type="button" onclick="cerrarModal()" class="btn-cancelar">Cancelar</button>
                <button type="submit" class="btn-crear">Crear Subtema</button>
            </div>
        </form>
    </div>
</div>

<script>
function mostrarFormularioNuevoSubtema() {
    document.getElementById('modalNuevoSubtema').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalNuevoSubtema').style.display = 'none';
}

function editarSubtema(id) {
    window.location.href = `<?= BASE_URL ?>/docente/editar_subtema.php?id=${id}&tema_id=<?= $tema_id ?>&modulo_id=<?= $modulo_id ?>&curso_id=<?= $curso_id ?>`;
}

function gestionarLecciones(id) {
    window.location.href = `<?= BASE_URL ?>/docente/lecciones_subtema.php?id=${id}&tema_id=<?= $tema_id ?>&modulo_id=<?= $modulo_id ?>&curso_id=<?= $curso_id ?>`;
}

function confirmarEliminarSubtema(id, titulo) {
    if (confirm(`¿Estás seguro de que deseas eliminar el subtema "${titulo}"?\n\nEsta acción eliminará permanentemente:\n- El subtema y su contenido\n- Todas las lecciones asociadas\n- Los archivos asociados\n\nEsta acción NO se puede deshacer.`)) {
        window.location.href = `<?= BASE_URL ?>/docente/eliminar_subtema.php?id=${id}&tema_id=<?= $tema_id ?>&modulo_id=<?= $modulo_id ?>&curso_id=<?= $curso_id ?>`;
    }
}

document.getElementById('modalNuevoSubtema').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModal();
    }
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
