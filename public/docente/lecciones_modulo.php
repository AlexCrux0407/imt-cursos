<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Lecciones del Módulo';

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

// Obtener lecciones del módulo
$stmt = $conn->prepare("
    SELECT * FROM lecciones 
    WHERE modulo_id = :modulo_id 
    ORDER BY orden ASC
");
$stmt->execute([':modulo_id' => $modulo_id]);
$lecciones = $stmt->fetchAll();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<div class="contenido">
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #3498db); color: white;">
        <div class="div-fila-alt-start">
            <div>
                <h1 style="font-size: 2rem; margin-bottom: 10px;">Lecciones del Módulo</h1>
                <p style="opacity: 0.9;"><?= htmlspecialchars($modulo['titulo']) ?> - <?= htmlspecialchars($modulo['curso_titulo']) ?></p>
            </div>
            <button onclick="mostrarFormularioNuevaLeccion()" 
                    style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                + Nueva Lección
            </button>
        </div>
    </div>

    <!-- Navegación -->
    <div class="form-container-body" style="margin-bottom: 20px;">
        <div class="div-fila-alt-start" style="gap: 10px;">
            <a href="<?= BASE_URL ?>/docente/admin_cursos.php" style="color: #7f8c8d; text-decoration: none;">Mis Cursos</a>
            <span style="color: #7f8c8d;"> > </span>
            <a href="<?= BASE_URL ?>/docente/modulos_curso.php?id=<?= $curso_id ?>" style="color: #7f8c8d; text-decoration: none;">Módulos</a>
            <span style="color: #7f8c8d;"> > </span>
            <span style="color: #3498db; font-weight: 500;">Lecciones</span>
        </div>
    </div>

    <!-- Información del Módulo -->
    <div class="form-container-body" style="margin-bottom: 20px;">
        <div class="div-fila" style="gap: 20px; align-items: center;">
            <div style="flex: 1;">
                <h3 style="color: #2c3e50; margin-bottom: 10px;"><?= htmlspecialchars($modulo['titulo']) ?></h3>
                <p style="color: #7f8c8d; margin-bottom: 5px;"><?= htmlspecialchars($modulo['descripcion']) ?></p>
                <span style="color: #3498db; font-weight: 500;"><?= count($lecciones) ?> lecciones</span>
            </div>
            <div>
                <button onclick="window.history.back()" 
                        style="background: #e8ecef; color: #5a5c69; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer;">
                    ← Volver a Módulos
                </button>
            </div>
        </div>
    </div>

    <!-- Lista de Lecciones -->
    <div class="form-container-body">
        <h2 style="color: #3498db; margin-bottom: 20px;">Lecciones</h2>
        
        <?php if (empty($lecciones)): ?>
            <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                <img src="<?= BASE_URL ?>/styles/iconos/detalles.png" style="width: 64px; height: 64px; opacity: 0.5; margin-bottom: 20px; filter: brightness(0) saturate(100%) invert(50%);">
                <h3>No hay lecciones creadas</h3>
                <p>Comienza agregando la primera lección a este módulo</p>
                <button onclick="mostrarFormularioNuevaLeccion()" 
                        style="background: #3498db; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; margin-top: 15px;">
                    Crear Primera Lección
                </button>
            </div>
        <?php else: ?>
            <div style="display: grid; gap: 15px;">
                <?php foreach ($lecciones as $leccion): ?>
                    <div style="border: 2px solid #e8ecef; border-radius: 12px; padding: 20px; background: white; transition: all 0.3s ease;"
                         onmouseover="this.style.borderColor='#3498db'"
                         onmouseout="this.style.borderColor='#e8ecef'">
                        
                        <div class="div-fila" style="gap: 20px; align-items: center;">
                            <div style="width: 40px; height: 40px; background: #3498db; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                <?= $leccion['orden'] ?>
                            </div>
                            
                            <div style="width: 50px; height: 50px; background: <?= getTipoColor($leccion['tipo']) ?>; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <img src="<?= BASE_URL ?>/styles/iconos/<?= getTipoIcono($leccion['tipo'], $leccion['recurso_url']) ?>" style="width: 24px; height: 24px; filter: brightness(0) invert(1);">
                            </div>
                            
                            <div style="flex: 1;">
                                <div class="div-fila-alt-start" style="gap: 10px; margin-bottom: 8px;">
                                    <h4 style="color: #2c3e50; margin: 0;"><?= htmlspecialchars($leccion['titulo']) ?></h4>
                                    <span style="background: <?= getTipoColor($leccion['tipo']) ?>; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.7rem;">
                                        <?= ucfirst($leccion['tipo']) ?>
                                    </span>
                                </div>
                                <p style="color: #7f8c8d; margin-bottom: 10px; font-size: 0.9rem;">
                                    <?= htmlspecialchars(substr($leccion['contenido'] ?? '', 0, 100)) ?><?= strlen($leccion['contenido'] ?? '') > 100 ? '...' : '' ?>
                                </p>
                                <?php if ($leccion['recurso_url']): ?>
                                    <div style="margin-bottom: 5px;">
                                        <small style="color: #3498db;">
                                            📎 <?= getRecursoTipo($leccion['recurso_url']) ?>
                                            <a href="<?= htmlspecialchars($leccion['recurso_url']) ?>" target="_blank" style="color: #3498db; text-decoration: none;">Ver recurso</a>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                <span style="color: #7f8c8d; font-size: 0.8rem;">Creado: <?= date('d/m/Y H:i', strtotime($leccion['created_at'])) ?></span>
                            </div>
                            
                            <div class="div-fila-alt-start" style="gap: 10px;">
                                <button onclick="editarLeccion(<?= $leccion['id'] ?>)" 
                                        style="background: #3498db; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 0.9rem;">
                                    Editar
                                </button>
                                <button onclick="eliminarLeccion(<?= $leccion['id'] ?>)" 
                                        style="background: transparent; color: #e74c3c; border: 2px solid #e74c3c; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.9rem;">
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

<!-- Modal para Nueva Lección -->
<div id="modalNuevaLeccion" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 30px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <div class="div-fila" style="justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="color: #3498db; margin: 0;">Nueva Lección</h2>
            <button onclick="cerrarModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #7f8c8d;">&times;</button>
        </div>
        
        <form method="POST" action="<?= BASE_URL ?>/docente/procesar_leccion.php" enctype="multipart/form-data">
            <input type="hidden" name="modulo_id" value="<?= $modulo_id ?>">
            <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Título de la Lección</label>
                <input type="text" name="titulo" required 
                       style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
            </div>
            
            <div class="div-fila" style="gap: 20px; margin-bottom: 20px;">
                <div style="flex: 1;">
                    <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Tipo</label>
                    <select name="tipo" 
                            style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
                        <option value="documento">Documento</option>
                        <option value="video">Video</option>
                        <option value="quiz">Quiz</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Orden</label>
                    <input type="number" name="orden" value="<?= count($lecciones) + 1 ?>" min="1" required
                           style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Contenido</label>
                <textarea name="contenido" rows="5" 
                          style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; resize: vertical;"></textarea>
            </div>
            
            <div style="margin-bottom: 20px; padding: 20px; border: 2px dashed #e8ecef; border-radius: 8px; background: #fafbfc;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Subir Archivo (opcional)</label>
                <input type="file" name="archivo" accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4,.avi,.mov,.jpg,.jpeg,.png"
                       style="width: 100%; padding: 8px; border: 1px solid #e8ecef; border-radius: 6px;">
                <small style="color: #7f8c8d; display: block; margin-top: 8px;">
                    Formatos permitidos: PDF, DOC, DOCX, PPT, PPTX, MP4, AVI, MOV, JPG, PNG (Max: 50MB)
                </small>
            </div>
            
            <div style="margin-bottom: 30px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">URL del Recurso (opcional)</label>
                <input type="url" name="recurso_url" 
                       style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;"
                       placeholder="https://...">
            </div>
            
            <div class="div-fila-alt" style="gap: 15px;">
                <button type="button" onclick="cerrarModal()" 
                        style="background: #e8ecef; color: #5a5c69; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    Cancelar
                </button>
                <button type="submit" 
                        style="background: #3498db; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    Crear Lección
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function mostrarFormularioNuevaLeccion() {
    document.getElementById('modalNuevaLeccion').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalNuevaLeccion').style.display = 'none';
}

function editarLeccion(id) {
    window.location.href = `<?= BASE_URL ?>/docente/editar_leccion.php?id=${id}&modulo_id=<?= $modulo_id ?>&curso_id=<?= $curso_id ?>`;
}

function eliminarLeccion(id) {
    if (confirm('¿Estás seguro de que deseas eliminar esta lección?')) {
        window.location.href = `<?= BASE_URL ?>/docente/eliminar_leccion.php?id=${id}&modulo_id=<?= $modulo_id ?>&curso_id=<?= $curso_id ?>`;
    }
}

document.getElementById('modalNuevaLeccion').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModal();
    }
});
</script>

<?php 
function getTipoColor($tipo) {
    switch($tipo) {
        case 'video': return '#e74c3c';
        case 'documento': return '#27ae60';
        case 'quiz': return '#f39c12';
        default: return '#3498db';
    }
}

function getTipoIcono($tipo, $recurso_url = '') {
    // Si hay un recurso_url, detectar por extensión
    if ($recurso_url) {
        $extension = strtolower(pathinfo($recurso_url, PATHINFO_EXTENSION));
        switch($extension) {
            case 'pdf':
                return 'detalles.png'; // Para PDFs
            case 'doc':
            case 'docx':
                return 'edit.png'; // Para documentos Word
            case 'ppt':
            case 'pptx':
                return 'desk.png'; // Para presentaciones
            case 'mp4':
            case 'avi':
            case 'mov':
                return 'home.png'; // Para videos
            case 'jpg':
            case 'jpeg':
            case 'png':
                return 'entrada.png'; // Para imágenes
            default:
                return 'config.png'; // Archivo genérico
        }
    }
    
    // Si no hay archivo, usar el tipo de lección
    switch($tipo) {
        case 'video': return 'home.png';
        case 'documento': return 'detalles.png';
        case 'quiz': return 'config.png';
        default: return 'entrada.png';
    }
}

function getRecursoTipo($url) {
    if (strpos($url, 'http') === 0) {
        return 'Enlace externo -';
    }
    
    $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));
    switch($extension) {
        case 'pdf': return 'Documento PDF -';
        case 'doc':
        case 'docx': return 'Documento Word -';
        case 'ppt':
        case 'pptx': return 'Presentación -';
        case 'mp4':
        case 'avi':
        case 'mov': return 'Video -';
        case 'jpg':
        case 'jpeg':
        case 'png': return 'Imagen -';
        default: return 'Archivo -';
    }
}

require __DIR__ . '/../partials/footer.php'; 
?>
