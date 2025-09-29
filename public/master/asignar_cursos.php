<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('master');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Master – Asignación de Cursos';

// Obtener lista de cursos sin asignar
$stmt = $conn->prepare("
    SELECT id, titulo, categoria, created_at, estado
    FROM cursos
    WHERE (asignado_a IS NULL OR asignado_a = 0)
    ORDER BY created_at DESC
");
$stmt->execute();
$cursos_sin_asignar = $stmt->fetchAll();

// Obtener lista de cursos asignados
$stmt = $conn->prepare("
    SELECT c.id, c.titulo, c.categoria, c.created_at, c.estado, 
           u.nombre as docente_nombre, u.id as docente_id, c.fecha_asignacion
    FROM cursos c
    JOIN usuarios u ON c.asignado_a = u.id
    WHERE c.asignado_a IS NOT NULL AND c.asignado_a > 0
    ORDER BY c.fecha_asignacion DESC
");
$stmt->execute();
$cursos_asignados = $stmt->fetchAll();

// Obtener lista de docentes
$stmt = $conn->prepare("
    SELECT id, nombre, email
    FROM usuarios
    WHERE role = 'docente'
    ORDER BY nombre ASC
");
$stmt->execute();
$docentes = $stmt->fetchAll();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/master.css">

<div class="contenido">
    <!-- Header Principal -->
    <div class="form-container-head" style="background: linear-gradient(135deg, #0066cc, #004d99); color: white; text-align: center;">
        <h1 style="font-size: 2.5rem; margin-bottom: 10px; font-weight: 600;">Asignación de Cursos</h1>
        <p style="font-size: 1.1rem; opacity: 0.9;">Asigne cursos a los docentes para su gestión</p>
    </div>

    <!-- Sección de Cursos Sin Asignar -->
    <div class="form-container-body" style="margin-bottom: 20px;">
        <h2 style="font-size: 1.5rem; margin-bottom: 15px; color: #333;">Cursos Sin Asignar</h2>
        
        <?php if (empty($cursos_sin_asignar)): ?>
            <div class="alert alert-info">No hay cursos pendientes de asignación.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Categoría</th>
                            <th>Fecha Creación</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cursos_sin_asignar as $curso): ?>
                            <tr>
                                <td><?= htmlspecialchars($curso['titulo']) ?></td>
                                <td><?= htmlspecialchars($curso['categoria']) ?></td>
                                <td><?= date('d/m/Y', strtotime($curso['created_at'])) ?></td>
                                <td>
                                    <span class="badge <?= $curso['estado'] === 'activo' ? 'bg-success' : 'bg-warning' ?>">
                                        <?= ucfirst($curso['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-primary btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#asignarModal" 
                                            data-curso-id="<?= $curso['id'] ?>"
                                            data-curso-titulo="<?= htmlspecialchars($curso['titulo']) ?>">
                                        Asignar a Docente
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sección de Cursos Asignados -->
    <div class="form-container-body">
        <h2 style="font-size: 1.5rem; margin-bottom: 15px; color: #333;">Cursos Asignados</h2>
        
        <?php if (empty($cursos_asignados)): ?>
            <div class="alert alert-info">No hay cursos asignados.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Categoría</th>
                            <th>Docente Asignado</th>
                            <th>Fecha Asignación</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cursos_asignados as $curso): ?>
                            <tr>
                                <td><?= htmlspecialchars($curso['titulo']) ?></td>
                                <td><?= htmlspecialchars($curso['categoria']) ?></td>
                                <td><?= htmlspecialchars($curso['docente_nombre']) ?></td>
                                <td><?= date('d/m/Y', strtotime($curso['fecha_asignacion'])) ?></td>
                                <td>
                                    <span class="badge <?= $curso['estado'] === 'activo' ? 'bg-success' : 'bg-warning' ?>">
                                        <?= ucfirst($curso['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-warning btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#asignarModal" 
                                            data-curso-id="<?= $curso['id'] ?>"
                                            data-curso-titulo="<?= htmlspecialchars($curso['titulo']) ?>"
                                            data-docente-id="<?= $curso['docente_id'] ?>">
                                        Reasignar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para Asignar Curso -->
<div class="modal fade" id="asignarModal" tabindex="-1" aria-labelledby="asignarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="asignarModalLabel"><img src="<?= BASE_URL ?>/styles/iconos/user_icon.png" alt="Asignar" style="width: 20px; height: 20px; margin-right: 8px; vertical-align: middle;">Asignar Curso</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formAsignarCurso" class="needs-validation" novalidate>
                    <input type="hidden" id="curso_id" name="curso_id">
                    
                    <div class="mb-4">
                        <label for="curso_titulo" class="form-label fw-bold"><img src="<?= BASE_URL ?>/styles/iconos/desk.png" alt="Curso" style="width: 16px; height: 16px; margin-right: 8px; vertical-align: middle;">Curso</label>
                        <input type="text" class="form-control form-control-lg bg-light" id="curso_titulo" readonly>
                    </div>
                    
                    <div class="mb-4">
                        <label for="docente_id" class="form-label fw-bold"><img src="<?= BASE_URL ?>/styles/iconos/user_icon.png" alt="Docente" style="width: 16px; height: 16px; margin-right: 8px; vertical-align: middle;">Seleccionar Docente</label>
                        <select class="form-select form-select-lg" id="docente_id" name="docente_id" required>
                            <option value="">-- Seleccione un docente --</option>
                            <?php foreach ($docentes as $docente): ?>
                                <option value="<?= $docente['id'] ?>">
                                    <?= htmlspecialchars($docente['nombre']) ?> (<?= htmlspecialchars($docente['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">
                            Por favor seleccione un docente
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="instrucciones" class="form-label fw-bold"><img src="<?= BASE_URL ?>/styles/iconos/detalles.png" alt="Instrucciones" style="width: 16px; height: 16px; margin-right: 8px; vertical-align: middle;">Instrucciones (opcional)</label>
                        <textarea class="form-control" id="instrucciones" name="instrucciones" rows="4" 
                                  placeholder="Instrucciones específicas para el docente..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <img src="<?= BASE_URL ?>/styles/iconos/cancel.png" alt="Cancelar" style="width: 16px; height: 16px; margin-right: 4px; vertical-align: middle;">Cancelar
                </button>
                <button type="button" class="btn btn-primary btn-lg" id="btnAsignarCurso">
                    <img src="<?= BASE_URL ?>/styles/iconos/showgreen.png" alt="Asignar" style="width: 16px; height: 16px; margin-right: 4px; vertical-align: middle;">Asignar Curso
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configurar modal cuando se abre
    const asignarModal = document.getElementById('asignarModal');
    asignarModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const cursoId = button.getAttribute('data-curso-id');
        const cursoTitulo = button.getAttribute('data-curso-titulo');
        const docenteId = button.getAttribute('data-docente-id');
        
        document.getElementById('curso_id').value = cursoId;
        document.getElementById('curso_titulo').value = cursoTitulo;
        
        if (docenteId) {
            document.getElementById('docente_id').value = docenteId;
        } else {
            document.getElementById('docente_id').value = '';
        }
    });
    
    // Manejar envío del formulario
    document.getElementById('btnAsignarCurso').addEventListener('click', function() {
        const formData = new FormData(document.getElementById('formAsignarCurso'));
        
        fetch('<?= BASE_URL ?>/master/procesar_asignacion.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Curso asignado correctamente');
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Ocurrió un error al procesar la solicitud');
        });
    });
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>