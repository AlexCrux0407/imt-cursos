<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Editar Lección';

$leccion_id = $_GET['id'] ?? 0;
$modulo_id = $_GET['modulo_id'] ?? 0;
$curso_id = $_GET['curso_id'] ?? 0;

// Verificar que la lección pertenece a un módulo de un curso del docente
$stmt = $conn->prepare("
    SELECT l.*, m.titulo as modulo_titulo, c.titulo as curso_titulo
    FROM lecciones l
    INNER JOIN modulos m ON l.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE l.id = :leccion_id AND l.modulo_id = :modulo_id AND c.creado_por = :docente_id
");
$stmt->execute([
    ':leccion_id' => $leccion_id,
    ':modulo_id' => $modulo_id,
    ':docente_id' => $_SESSION['user_id']
]);
$leccion = $stmt->fetch();

if (!$leccion) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=leccion_no_encontrada');
    exit;
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<div class="contenido">
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white;">
        <div class="div-fila-alt-start">
            <div>
                <h1 style="font-size: 2rem; margin-bottom: 10px;">Editar Lección</h1>
                <p style="opacity: 0.9;"><?= htmlspecialchars($leccion['modulo_titulo']) ?> - <?= htmlspecialchars($leccion['curso_titulo']) ?></p>
            </div>
            <button onclick="window.history.back()" 
                    style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                ← Volver
            </button>
        </div>
    </div>

    <div class="form-container-body">
        <?php if (isset($_GET['error'])): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
                <?php
                switch($_GET['error']) {
                    case 'orden_duplicado':
                        echo 'Ya existe una lección con ese número de orden en este módulo.';
                        break;
                    case 'error_actualizar':
                        echo 'Error al actualizar la lección. Inténtalo nuevamente.';
                        break;
                    default:
                        echo 'Error desconocido.';
                }
                ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>/docente/actualizar_leccion.php" enctype="multipart/form-data">
            <input type="hidden" name="leccion_id" value="<?= $leccion['id'] ?>">
            <input type="hidden" name="modulo_id" value="<?= $modulo_id ?>">
            <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Título de la Lección</label>
                <input type="text" name="titulo" value="<?= htmlspecialchars($leccion['titulo']) ?>" required 
                       style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
            </div>
            
            <div class="div-fila" style="gap: 20px; margin-bottom: 20px;">
                <div style="flex: 1;">
                    <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Tipo</label>
                    <select name="tipo" 
                            style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
                        <option value="documento" <?= $leccion['tipo'] === 'documento' ? 'selected' : '' ?>>Documento</option>
                        <option value="video" <?= $leccion['tipo'] === 'video' ? 'selected' : '' ?>>Video</option>
                        <option value="quiz" <?= $leccion['tipo'] === 'quiz' ? 'selected' : '' ?>>Quiz</option>
                        <option value="otro" <?= $leccion['tipo'] === 'otro' ? 'selected' : '' ?>>Otro</option>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Orden</label>
                    <input type="number" name="orden" value="<?= $leccion['orden'] ?>" min="1" max="99" required
                           style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;"
                           onchange="validarOrden(this.value)">
                    <small style="color: #7f8c8d;">Cada lección debe tener un orden único en el módulo</small>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Contenido</label>
                <textarea name="contenido" rows="8" 
                          style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; resize: vertical;"><?= htmlspecialchars($leccion['contenido']) ?></textarea>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">URL del Recurso (opcional)</label>
                <input type="text" name="recurso_url" value="<?= htmlspecialchars($leccion['recurso_url']) ?>"
                       style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;"
                       placeholder="https://... (opcional)">
                <?php if ($leccion['recurso_url']): ?>
                    <small style="color: #3498db;">Recurso actual: <a href="<?= htmlspecialchars($leccion['recurso_url']) ?>" target="_blank">Ver recurso</a></small>
                <?php endif; ?>
            </div>
            
            <div style="margin-bottom: 20px; padding: 20px; border: 2px dashed #e8ecef; border-radius: 8px; background: #fafbfc;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Subir Archivo (opcional)</label>
                <input type="file" name="archivo" accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4,.avi,.mov,.jpg,.jpeg,.png"
                       style="width: 100%; padding: 8px; border: 1px solid #e8ecef; border-radius: 6px;">
                <small style="color: #7f8c8d; display: block; margin-top: 8px;">
                    Formatos permitidos: PDF, DOC, DOCX, PPT, PPTX, MP4, AVI, MOV, JPG, PNG (Max: 50MB)
                </small>
            </div>
            
            <div class="div-fila-alt" style="gap: 15px;">
                <button type="button" onclick="window.history.back()" 
                        style="background: #e8ecef; color: #5a5c69; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    Cancelar
                </button>
                <button type="submit" 
                        style="background: #3498db; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    Actualizar Lección
                </button>
            </div>
        </form>
    </div>
</div>

<script>
async function validarOrden(orden) {
    if (!orden) return;
    
    try {
        const response = await fetch('<?= BASE_URL ?>/docente/validar_orden.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `modulo_id=<?= $modulo_id ?>&orden=${orden}&leccion_id=<?= $leccion_id ?>`
        });
        
        const result = await response.json();
        const input = document.querySelector('input[name="orden"]');
        
        if (!result.disponible) {
            input.style.borderColor = '#e74c3c';
            input.style.backgroundColor = '#fdf2f2';
            alert('Ya existe una lección con ese orden en este módulo.');
        } else {
            input.style.borderColor = '#e8ecef';
            input.style.backgroundColor = 'white';
        }
    } catch (error) {
        console.log('Error validando orden:', error);
    }
}
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
