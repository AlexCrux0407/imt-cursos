<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Editar Tema';

$tema_id = $_GET['id'] ?? 0;
$modulo_id = $_GET['modulo_id'] ?? 0;
$curso_id = $_GET['curso_id'] ?? 0;

// Verificar que el tema pertenece a un módulo de un curso del docente
$stmt = $conn->prepare("
    SELECT t.*, m.titulo as modulo_titulo, c.titulo as curso_titulo
    FROM temas t
    INNER JOIN modulos m ON t.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE t.id = ? AND (c.creado_por = ? OR c.asignado_a = ?)
");
$stmt->execute([$tema_id, $_SESSION['user_id'], $_SESSION['user_id']]);
$tema = $stmt->fetch();

if (!$tema) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=tema_no_encontrado');
    exit;
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<div class="contenido">
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #6eb4e3ff); color: white;">
        <div class="div-fila-alt-start">
            <div>
                <h1 style="font-size: 2rem; margin-bottom: 10px;">Editar Tema</h1>
                <p style="opacity: 0.9;"><?= htmlspecialchars($tema['modulo_titulo']) ?> - <?= htmlspecialchars($tema['curso_titulo']) ?></p>
            </div>
            <button onclick="window.history.back()" 
                    style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                ← Volver
            </button>
        </div>
    </div>

    <div class="form-container-body">
        <form method="POST" action="<?= BASE_URL ?>/docente/actualizar_tema.php" enctype="multipart/form-data">
            <input type="hidden" name="tema_id" value="<?= $tema['id'] ?>">
            <input type="hidden" name="modulo_id" value="<?= $modulo_id ?>">
            <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Título del Tema</label>
                <input type="text" name="titulo" value="<?= htmlspecialchars($tema['titulo']) ?>" required 
                       style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Descripción</label>
                <textarea name="descripcion" rows="3" 
                          style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; resize: vertical;"><?= htmlspecialchars($tema['descripcion'] ?? '') ?></textarea>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Contenido del Tema</label>
                <textarea name="contenido" rows="6" 
                          style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; resize: vertical;"><?= htmlspecialchars($tema['contenido'] ?? '') ?></textarea>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">URL del Recurso (opcional)</label>
                <input type="text" name="recurso_url" value="<?= htmlspecialchars($tema['recurso_url'] ?? '') ?>"
                       style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
                <?php if (!empty($tema['recurso_url'])): ?>
                    <small style="color: #27ae60;">Recurso actual: <a href="<?= htmlspecialchars($tema['recurso_url']) ?>" target="_blank">Ver recurso</a></small>
                <?php endif; ?>
            </div>
            
            <div style="margin-bottom: 20px; padding: 20px; border: 2px dashed #e8ecef; border-radius: 8px; background: #fafbfc;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Subir Nuevo Archivo (opcional)</label>
                <input type="file" name="archivo" accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4,.avi,.mov,.jpg,.jpeg,.png"
                       style="width: 100%; padding: 8px; border: 1px solid #e8ecef; border-radius: 6px;">
            </div>

            <div style="margin-bottom: 30px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Orden</label>
                <input type="number" name="orden" value="<?= $tema['orden'] ?>" min="1" required
                       style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
            </div>
            
            <div class="div-fila-alt" style="gap: 15px;">
                <button type="button" onclick="window.history.back()" 
                        style="background: #e8ecef; color: #5a5c69; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    Cancelar
                </button>
                <button type="submit" 
                        style="background: #27ae60; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    Actualizar Tema
                </button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
