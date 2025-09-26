<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Editar Subtema';

$subtema_id = $_GET['id'] ?? 0;
$tema_id = $_GET['tema_id'] ?? 0;
$modulo_id = $_GET['modulo_id'] ?? 0;
$curso_id = $_GET['curso_id'] ?? 0;

// Verificar que el subtema pertenece a un tema de un módulo de un curso del docente
$stmt = $conn->prepare("
    SELECT s.*, t.titulo as tema_titulo, m.titulo as modulo_titulo, c.titulo as curso_titulo
    FROM subtemas s
    INNER JOIN temas t ON s.tema_id = t.id
    INNER JOIN modulos m ON t.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE s.id = :subtema_id AND c.creado_por = :docente_id
");
$stmt->execute([':subtema_id' => $subtema_id, ':docente_id' => $_SESSION['user_id']]);
$subtema = $stmt->fetch();

if (!$subtema) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=subtema_no_encontrado');
    exit;
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/subtemas.css">

<div class="contenido">
    <div class="subtemas-header">
        <div class="div-fila-alt-start">
            <div>
                <h1 class="subtemas-title">Editar Subtema</h1>
                <p class="subtemas-subtitle"><?= htmlspecialchars($subtema['tema_titulo']) ?> - <?= htmlspecialchars($subtema['modulo_titulo']) ?></p>
            </div>
            <button onclick="window.history.back()" class="btn-volver">
                ← Volver
            </button>
        </div>
    </div>

    <div class="subtemas-container">
        <form method="POST" action="<?= BASE_URL ?>/docente/actualizar_subtema.php" enctype="multipart/form-data">
            <input type="hidden" name="subtema_id" value="<?= $subtema['id'] ?>">
            <input type="hidden" name="tema_id" value="<?= $tema_id ?>">
            <input type="hidden" name="modulo_id" value="<?= $modulo_id ?>">
            <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
            
            <div class="form-group">
                <label class="form-label">Título del Subtema</label>
                <input type="text" name="titulo" value="<?= htmlspecialchars($subtema['titulo']) ?>" required class="form-input">
            </div>
            
            <div class="form-group">
                <label class="form-label">Descripción</label>
                <textarea name="descripcion" rows="3" class="form-textarea"><?= htmlspecialchars($subtema['descripcion'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Orden</label>
                <input type="number" name="orden" value="<?= $subtema['orden'] ?>" min="1" required class="form-input">
            </div>
            
            <div class="modal-actions">
                <button type="button" onclick="window.history.back()" class="btn-cancelar">Cancelar</button>
                <button type="submit" class="btn-crear">Actualizar Subtema</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
