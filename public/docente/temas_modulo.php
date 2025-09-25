<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Temas del Módulo';

$modulo_id = $_GET['id'] ?? 0;
$curso_id = $_GET['curso_id'] ?? 0;

// Verificar que el módulo pertenece a un curso del docente
$stmt = $conn->prepare("
    SELECT m.*, c.titulo as curso_titulo
    FROM modulos m
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE m.id = :modulo_id AND c.creado_por = :docente_id
");
$stmt->execute([':modulo_id' => $modulo_id, ':docente_id' => $_SESSION['user_id']]);
$modulo = $stmt->fetch();

if (!$modulo) {
    header('Location: /imt-cursos/public/docente/admin_cursos.php?error=modulo_no_encontrado');
    exit;
}

// Obtener temas del módulo
$stmt = $conn->prepare("
    SELECT t.*, COUNT(s.id) as total_subtemas
    FROM temas t
    LEFT JOIN subtemas s ON t.id = s.tema_id
    WHERE t.modulo_id = :modulo_id
    GROUP BY t.id
    ORDER BY t.orden ASC
");
$stmt->execute([':modulo_id' => $modulo_id]);
$temas = $stmt->fetchAll();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="/imt-cursos/public/styles/css/temas.css">

<div class="contenido">
    <div class="temas-header">
        <div class="div-fila-alt-start">
            <div>
                <h1 class="temas-title">Temas del Módulo</h1>
                <p class="temas-subtitle"><?= htmlspecialchars($modulo['titulo']) ?> - <?= htmlspecialchars($modulo['curso_titulo']) ?></p>
            </div>
            <button onclick="mostrarFormularioNuevoTema()" class="temas-btn-nuevo">
                + Nuevo Tema
            </button>
        </div>
    </div>

    <!-- Navegación -->
    <div class="temas-breadcrumb">
        <div class="div-fila-alt-start" style="gap: 10px;">
            <a href="/imt-cursos/public/docente/admin_cursos.php" class="breadcrumb-link">Mis Cursos</a>
            <span class="breadcrumb-separator"> > </span>
            <a href="/imt-cursos/public/docente/modulos_curso.php?id=<?= $curso_id ?>" class="breadcrumb-link">Módulos</a>
            <span class="breadcrumb-separator"> > </span>
            <span class="breadcrumb-current">Temas</span>
        </div>
    </div>

    <!-- Lista de Temas -->
    <div class="temas-container">
        <h2 class="temas-section-title">Temas</h2>

        <?php if (empty($temas)): ?>
            <div class="temas-empty">
                <img src="/imt-cursos/public/styles/iconos/desk.png" class="empty-icon-temas">
                <h3>No hay temas creados</h3>
                <p>Comienza agregando el primer tema a este módulo</p>
                <button onclick="mostrarFormularioNuevoTema()" class="btn-crear-tema">
                    Crear Primer Tema
                </button>
            </div>
        <?php else: ?>
            <div class="temas-grid">
                <?php foreach ($temas as $tema): ?>
                    <div class="tema-card">
                        <div class="div-fila" style="gap: 20px; align-items: center;">
                            <div class="tema-orden"><?= $tema['orden'] ?></div>

                            <div style="flex: 1;">
                                <h4 class="tema-title"><?= htmlspecialchars($tema['titulo']) ?></h4>
                                <p class="tema-desc">
                                    <?= htmlspecialchars(substr($tema['descripcion'], 0, 100)) ?><?= strlen($tema['descripcion']) > 100 ? '...' : '' ?>
                                </p>
                                <div class="div-fila-alt-start" style="gap: 15px;">
                                    <span class="tema-subtemas"><?= $tema['total_subtemas'] ?> subtemas</span>
                                    <span class="tema-fecha">Creado: <?= date('d/m/Y', strtotime($tema['created_at'])) ?></span>
                                </div>
                            </div>

                            <div class="tema-actions">
                                <button onclick="gestionarSubtemas(<?= $tema['id'] ?>)" class="btn-subtemas">
                                    Subtemas
                                </button>
                                <button onclick="editarTema(<?= $tema['id'] ?>)" class="btn-editar-tema">
                                    Editar
                                </button>
                                <button onclick="confirmarEliminarTema(<?= $tema['id'] ?>, '<?= addslashes($tema['titulo']) ?>')" class="btn-eliminar-tema">
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

<!-- Modal para Nuevo Tema -->
<div id="modalNuevoTema" class="modal-overlay-temas">
    <div class="modal-content-temas">
        <div class="modal-header-temas">
            <h2 class="modal-title-temas">Nuevo Tema</h2>
            <button onclick="cerrarModal()" class="modal-close-temas">&times;</button>
        </div>

        <form method="POST" action="/imt-cursos/public/docente/procesar_tema.php" enctype="multipart/form-data">
            <input type="hidden" name="modulo_id" value="<?= $modulo_id ?>">
            <input type="hidden" name="curso_id" value="<?= $curso_id ?>">

            <div class="modal-body-temas">
                <div class="modal-field-temas">
                    <label class="modal-label-temas">Título del Tema</label>
                    <input type="text" name="titulo" required class="modal-input-temas">
                </div>

                <div class="modal-field-temas">
                    <label class="modal-label-temas">Descripción</label>
                    <textarea name="descripcion" rows="3" class="modal-textarea-temas"></textarea>
                </div>

                <div class="modal-field-temas">
                    <label class="modal-label-temas">Contenido del Tema</label>
                    <textarea name="contenido" rows="5" class="modal-textarea-temas" placeholder="Desarrolla el contenido de este tema..."></textarea>
                </div>

                <div class="modal-field-temas">
                    <label class="modal-label-temas">URL del Recurso (opcional)</label>
                    <input type="text" name="recurso_url" class="modal-input-temas" placeholder="https://... (opcional)">
                </div>

                <div class="modal-field-temas">
                    <label class="modal-label-temas">Subir Archivo (opcional)</label>
                    <input type="file" name="archivo" accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4,.avi,.mov,.jpg,.jpeg,.png" class="modal-file-temas">
                    <small class="modal-file-info-temas">
                        Formatos permitidos: PDF, DOC, DOCX, PPT, PPTX, MP4, AVI, MOV, JPG, PNG (Max: 50MB)
                    </small>
                </div>

                <div class="modal-field-temas">
                    <label class="modal-label-temas">Orden</label>
                    <input type="number" name="orden" value="<?= count($temas) + 1 ?>" min="1" required class="modal-input-temas">
                </div>
            </div>

            <div class="modal-actions-temas">
                <button type="button" onclick="cerrarModal()" class="btn-cancelar-temas">
                    Cancelar
                </button>
                <button type="submit" class="btn-crear-modal">
                    Crear Tema
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function mostrarFormularioNuevoTema() {
        document.getElementById('modalNuevoTema').style.display = 'flex';
    }

    function cerrarModal() {
        document.getElementById('modalNuevoTema').style.display = 'none';
    }

    function editarTema(id) {
        window.location.href = `/imt-cursos/public/docente/editar_tema.php?id=${id}&modulo_id=<?= $modulo_id ?>&curso_id=<?= $curso_id ?>`;
    }

    function gestionarSubtemas(id) {
        window.location.href = `/imt-cursos/public/docente/subtemas_tema.php?id=${id}&modulo_id=<?= $modulo_id ?>&curso_id=<?= $curso_id ?>`;
    }

    function confirmarEliminarTema(id, titulo) {
        if (confirm(`¿Estás seguro de que deseas eliminar el tema "${titulo}"?\n\nEsta acción eliminará permanentemente:\n- El tema y su contenido\n- Todos los subtemas asociados\n- Todas las lecciones asociadas\n- Los archivos asociados\n\nEsta acción NO se puede deshacer.`)) {
            window.location.href = `/imt-cursos/public/docente/eliminar_tema.php?id=${id}&modulo_id=<?= $modulo_id ?>&curso_id=<?= $curso_id ?>`;
        }
    }

    document.getElementById('modalNuevoTema').addEventListener('click', function(e) {
        if (e.target === this) {
            cerrarModal();
        }
    });
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>