<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Editar Curso';

$curso_id = $_GET['id'] ?? 0;

// Verificar que el curso pertenece al docente
$stmt = $conn->prepare("
    SELECT * FROM cursos 
    WHERE id = :id AND (creado_por = :docente_id OR asignado_a = :docente_id2)
");
$stmt->execute([
    ':id' => $curso_id, 
    ':docente_id' => $_SESSION['user_id'],
    ':docente_id2' => $_SESSION['user_id']
]);
$curso = $stmt->fetch();

if (!$curso) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=curso_no_encontrado');
    exit;
}

// Debug temporal valores del curso
//error_log("Curso cargado - Objetivo general: " . ($curso['objetivo_general'] ?? 'NULL'));
//error_log("Curso cargado - Objetivos específicos: " . ($curso['objetivos_especificos'] ?? 'NULL'));
//error_log("Curso cargado - Duración: " . ($curso['duracion'] ?? 'NULL'));

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<div class="contenido">
    <!--<div style="background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 0.9rem;">
        <strong>Debug:</strong> 
        Objetivo General: "<?= $curso['objetivo_general'] ?? 'vacío' ?>" | 
        Objetivos Específicos: "<?= $curso['objetivos_especificos'] ?? 'vacío' ?>" | 
        Duración: "<?= $curso['duracion'] ?? 'vacío' ?>"
    </div> -->

    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #3498db); color: white;">
        <div class="div-fila-alt-start">
            <div>
                <h1 style="font-size: 2rem; margin-bottom: 10px;">Editar Curso</h1>
                <p style="opacity: 0.9;">Modifica la información de tu curso</p>
            </div>
            <button onclick="window.history.back()" 
                    style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                ← Volver
            </button>
        </div>
    </div>

    <div class="form-container-body">
        <form method="POST" action="<?= BASE_URL ?>/docente/actualizar_curso.php">
            <input type="hidden" name="curso_id" value="<?= $curso['id'] ?>">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Título del Curso</label>
                <input type="text" name="titulo" value="<?= htmlspecialchars($curso['titulo']) ?>" required 
                       style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Descripción</label>
                <textarea name="descripcion" rows="4" 
                          style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; resize: vertical;"><?= htmlspecialchars($curso['descripcion'] ?? '') ?></textarea>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Objetivo General</label>
                <textarea name="objetivo_general" rows="3" 
                          style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; resize: vertical;"><?= htmlspecialchars($curso['objetivo_general'] ?? '') ?></textarea>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Objetivos Específicos</label>
                <textarea name="objetivos_especificos" rows="4" 
                          style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; resize: vertical;"><?= htmlspecialchars($curso['objetivos_especificos'] ?? '') ?></textarea>
            </div>
            
            <div class="div-fila" style="gap: 20px; margin-bottom: 20px;">
                <div style="flex: 1;">
                    <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Duración</label>
                    <input type="text" name="duracion" value="<?= htmlspecialchars($curso['duracion'] ?? '') ?>" 
                           style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Categoría</label>
                    <input type="text" name="categoria" value="<?= htmlspecialchars($curso['categoria'] ?? '') ?>" 
                           style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
                </div>
            </div>
            
            <div class="div-fila" style="gap: 20px; margin-bottom: 20px;">
                <div style="flex: 1;">
                    <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Dirigido a</label>
                    <input type="text" name="dirigido_a" value="<?= htmlspecialchars($curso['dirigido_a'] ?? '') ?>" 
                           style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Estado</label>
                    <select name="estado" 
                            style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
                        <option value="borrador" <?= $curso['estado'] === 'borrador' ? 'selected' : '' ?>>Borrador</option>
                        <option value="activo" <?= $curso['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="inactivo" <?= $curso['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>
            </div>
            
            <div class="div-fila-alt" style="gap: 15px;">
                <button type="button" onclick="window.history.back()" 
                        style="background: #e8ecef; color: #5a5c69; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    Cancelar
                </button>
                <button type="submit" 
                        style="background: #3498db; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    Actualizar Curso
                </button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
