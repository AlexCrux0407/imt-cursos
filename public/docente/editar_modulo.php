<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Editar Módulo';

$modulo_id = $_GET['id'] ?? 0;
$curso_id = $_GET['curso_id'] ?? 0;

// Verificar que el módulo pertenece a un curso del docente
$stmt = $conn->prepare("
    SELECT m.*, c.titulo as curso_titulo
    FROM modulos m
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE m.id = :modulo_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
");
$stmt->execute([
    ':modulo_id' => $modulo_id, 
    ':docente_id' => $_SESSION['user_id'],
    ':docente_id2' => $_SESSION['user_id']
]);
$modulo = $stmt->fetch();

if (!$modulo) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=modulo_no_encontrado');
    exit;
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<div class="contenido">
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white;">
        <div class="div-fila-alt-start">
            <div>
                <h1 style="font-size: 2rem; margin-bottom: 10px;">Editar Módulo</h1>
                <p style="opacity: 0.9;"><?= htmlspecialchars($modulo['curso_titulo']) ?></p>
            </div>
            <button onclick="window.history.back()" 
                    style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                ← Volver
            </button>
        </div>
    </div>

    <div class="form-container-body">
        <form method="POST" action="<?= BASE_URL ?>/docente/actualizar_modulo.php" enctype="multipart/form-data">
            <input type="hidden" name="modulo_id" value="<?= $modulo['id'] ?>">
            <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Título del Módulo</label>
                <input type="text" name="titulo" value="<?= htmlspecialchars($modulo['titulo']) ?>" required 
                       style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Descripción</label>
                <textarea name="descripcion" rows="3" 
                          style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; resize: vertical;"><?= htmlspecialchars($modulo['descripcion'] ?? '') ?></textarea>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Contenido del Módulo</label>
                <textarea name="contenido" rows="6" 
                          style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; resize: vertical;"><?= htmlspecialchars($modulo['contenido'] ?? '') ?></textarea>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">URL del Recurso (opcional)</label>
                <input type="text" name="recurso_url" value="<?= htmlspecialchars($modulo['recurso_url'] ?? '') ?>"
                       style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
                <?php if (!empty($modulo['recurso_url'])): ?>
                    <small style="color: #3498db;">Recurso actual: <a href="<?= htmlspecialchars($modulo['recurso_url']) ?>" target="_blank">Ver recurso</a></small>
                <?php endif; ?>
            </div>
            
            <div style="margin-bottom: 20px; padding: 20px; border: 2px dashed #e8ecef; border-radius: 8px; background: #fafbfc;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Subir Nuevo Archivo (opcional)</label>
                <input type="file" name="archivo" accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4,.avi,.mov,.jpg,.jpeg,.png"
                       style="width: 100%; padding: 8px; border: 1px solid #e8ecef; border-radius: 6px;">
            </div>

            <div style="margin-bottom: 30px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Orden</label>
                <input type="number" name="orden" value="<?= $modulo['orden'] ?>" min="1" required
                       style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
            </div>
            
            <div class="div-fila-alt" style="gap: 15px;">
                <button type="button" onclick="window.history.back()" 
                        style="background: #e8ecef; color: #5a5c69; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    Cancelar
                </button>
                <button type="submit" 
                        style="background: #3498db; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    Actualizar Módulo
                </button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
