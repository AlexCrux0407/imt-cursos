<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('master');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Master – Administración de Cursos';

// Generar token CSRF si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener todos los cursos del sistema
$stmt = $conn->prepare("
    SELECT c.*, 
           u_creador.nombre as creador_nombre,
           u_asignado.nombre as docente_asignado,
           COUNT(DISTINCT i.id) as total_inscritos,
           COUNT(DISTINCT m.id) as total_modulos
    FROM cursos c
    LEFT JOIN usuarios u_creador ON c.creado_por = u_creador.id
    LEFT JOIN usuarios u_asignado ON c.asignado_a = u_asignado.id
    LEFT JOIN inscripciones i ON c.id = i.curso_id
    LEFT JOIN modulos m ON c.id = m.curso_id
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$stmt->execute();
$cursos = $stmt->fetchAll();

// Obtener lista de docentes para asignación
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
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #3498db); color: white; text-align: center;">
        <h1 style="margin: 0; font-size: 2rem; font-weight: 600;">Administración de Cursos</h1>
        <p style="margin: 10px 0 0 0; opacity: 0.9;">Gestiona el catálogo completo de cursos de la plataforma</p>
    </div>

    <!-- Mensajes de estado -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
            <?php
            switch ($_GET['success']) {
                case 'curso_creado':
                    echo 'Curso creado exitosamente.';
                    break;
                case 'curso_actualizado':
                    echo 'Curso actualizado exitosamente.';
                    break;
                case 'curso_eliminado':
                    echo 'Curso eliminado exitosamente.';
                    break;
                default:
                    echo 'Operación completada exitosamente.';
            }
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
            <?php
            switch ($_GET['error']) {
                case 'titulo_requerido':
                    echo 'El título del curso es obligatorio.';
                    break;
                case 'database_error':
                    echo 'Error en la base de datos: ' . htmlspecialchars($_GET['details'] ?? '');
                    break;
                case 'acceso_denegado':
                    echo 'No tienes permisos para realizar esta acción.';
                    break;
                default:
                    echo 'Ha ocurrido un error. Inténtalo nuevamente.';
            }
            ?>
        </div>
    <?php endif; ?>

    <!-- Botón para crear nuevo curso -->
    <div style="margin-bottom: 30px; text-align: right;">
        <button onclick="mostrarFormularioCrear()" 
                style="background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; transition: all 0.3s ease;"
                onmouseover="this.style.background='#218838'"
                onmouseout="this.style.background='#28a745'">
            <img src="<?= BASE_URL ?>/styles/iconos/addicon.png" alt="" style="width: 16px; height: 16px; margin-right: 8px; filter: brightness(0) invert(1);">
            Crear Nuevo Curso
        </button>
    </div>

    <!-- Formulario de creación de curso (oculto inicialmente) -->
    <div id="formulario-crear" class="form-container-body" style="display: none; margin-bottom: 30px;">
        <h3 style="color: #0066cc; margin-bottom: 20px; border-bottom: 2px solid #e8ecef; padding-bottom: 10px;">
            Crear Nuevo Curso
        </h3>
        
        <form action="<?= BASE_URL ?>/master/procesar_curso.php" method="POST">
            <!-- Token CSRF para seguridad -->
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            
            <div class="div-fila" style="gap: 20px; margin-bottom: 20px;">
                <div style="flex: 2;">
                    <label for="titulo" style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">
                        Título del Curso *
                    </label>
                    <input type="text" id="titulo" name="titulo" required maxlength="255"
                           pattern="[a-zA-Z0-9\s\-\.\,\:\;\!\?\(\)áéíóúÁÉÍÓÚñÑüÜ]+"
                           title="Solo se permiten letras, números, espacios y signos de puntuación básicos"
                           style="width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem;"
                           placeholder="Ingresa el título del curso">
                    <small style="color: #6c757d; font-size: 0.85rem;">Máximo 255 caracteres</small>
                </div>
                <div style="flex: 1;">
                    <label for="categoria" style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">
                        Categoría *
                    </label>
                    <input type="text" id="categoria" name="categoria" required maxlength="100"
                           style="width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem;"
                           placeholder="Ej: Tecnología, Negocios, Diseño, etc.">
                    <small style="color: #6c757d; font-size: 0.85rem;">Máximo 100 caracteres</small>
                </div>
            </div>

            <div class="div-fila" style="gap: 20px; margin-bottom: 20px;">
                <div style="flex: 1;">
                    <label for="dirigido_a" style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">
                        Dirigido a *
                    </label>
                    <input type="text" id="dirigido_a" name="dirigido_a" required maxlength="200"
                           style="width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem;"
                           placeholder="Ej: Principiantes, Profesionales, Estudiantes universitarios, etc.">
                    <small style="color: #6c757d; font-size: 0.85rem;">Máximo 200 caracteres</small>
                </div>
                <div style="flex: 1;">
                    <label for="estado" style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">
                        Estado inicial
                    </label>
                    <select id="estado" name="estado"
                            style="width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem;">
                        <option value="borrador">Borrador</option>
                        <option value="revision">En Revisión</option>
                        <option value="activo" title="Solo usar si el curso está completamente listo">Activo</option>
                    </select>
                    <small style="color: #6c757d; font-size: 0.85rem;">Recomendado: Borrador para cursos nuevos</small>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="descripcion" style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">
                    Descripción del Curso
                </label>
                <textarea id="descripcion" name="descripcion" rows="4" maxlength="1000"
                          style="width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem; resize: vertical;"
                          placeholder="Describe brevemente el contenido y objetivos del curso"></textarea>
                <small style="color: #6c757d; font-size: 0.85rem;">Máximo 1000 caracteres</small>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="asignado_a" style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">
                    Asignar a docente (opcional)
                </label>
                <select id="asignado_a" name="asignado_a"
                        style="width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem;">
                    <option value="">Sin asignar</option>
                    <?php foreach ($docentes as $docente): ?>
                        <option value="<?= $docente['id'] ?>"><?= htmlspecialchars($docente['nombre']) ?> (<?= htmlspecialchars($docente['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #6c757d; font-size: 0.85rem;">El docente podrá desarrollar el contenido del curso</small>
            </div>

            <div class="div-fila-alt" style="gap: 15px;">
                <button type="submit" onclick="return validarFormulario()"
                        style="background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer;">
                    Crear Curso
                </button>
                <button type="button" onclick="ocultarFormularioCrear()"
                        style="background: #6c757d; color: white; padding: 12px 24px; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer;">
                    Cancelar
                </button>
            </div>
        </form>
    </div>

    <!-- Lista de cursos existentes -->
    <div class="form-container-body">
        <h3 style="color: #0066cc; margin-bottom: 20px; border-bottom: 2px solid #e8ecef; padding-bottom: 10px;">
            Cursos Existentes (<?= count($cursos) ?>)
        </h3>

        <?php if (empty($cursos)): ?>
            <div style="text-align: center; padding: 40px; color: #6c757d;">
                <img src="<?= BASE_URL ?>/styles/iconos/desk.png" alt="" style="width: 64px; height: 64px; opacity: 0.5; margin-bottom: 20px;">
                <h4>No hay cursos registrados</h4>
                <p>Comienza creando tu primer curso usando el botón "Crear Nuevo Curso"</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <thead>
                        <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                            <th style="padding: 15px; text-align: left; font-weight: 600; color: #495057;">Curso</th>
                            <th style="padding: 15px; text-align: left; font-weight: 600; color: #495057;">Categoría</th>
                            <th style="padding: 15px; text-align: left; font-weight: 600; color: #495057;">Estado</th>
                            <th style="padding: 15px; text-align: left; font-weight: 600; color: #495057;">Creado por</th>
                            <th style="padding: 15px; text-align: left; font-weight: 600; color: #495057;">Asignado a</th>
                            <th style="padding: 15px; text-align: center; font-weight: 600; color: #495057;">Inscritos</th>
                            <th style="padding: 15px; text-align: center; font-weight: 600; color: #495057;">Módulos</th>
                            <th style="padding: 15px; text-align: center; font-weight: 600; color: #495057;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cursos as $curso): ?>
                            <tr style="border-bottom: 1px solid #dee2e6; transition: background-color 0.2s ease;"
                                onmouseover="this.style.backgroundColor='#f8f9fa'"
                                onmouseout="this.style.backgroundColor='white'">
                                <td style="padding: 15px;">
                                    <div>
                                        <strong style="color: #0066cc; font-size: 1.1rem;">
                                            <?= htmlspecialchars($curso['titulo']) ?>
                                        </strong>
                                        <?php if ($curso['descripcion']): ?>
                                            <p style="margin: 5px 0 0 0; color: #6c757d; font-size: 0.9rem;">
                                                <?= htmlspecialchars(substr($curso['descripcion'] ?? '', 0, 100)) ?>
                                    <?= strlen($curso['descripcion'] ?? '') > 100 ? '...' : '' ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="padding: 15px;">
                                    <?php if ($curso['categoria']): ?>
                                        <span style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem;">
                                            <?= htmlspecialchars($curso['categoria']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #6c757d; font-style: italic;">Sin categoría</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 15px;">
                                    <?php
                                    // Compatibilidad PHP < 8: reemplazo de match por mapeo
                                    $estado_map = [
                                        'activo' => '#28a745',
                                        'borrador' => '#ffc107',
                                        'revision' => '#17a2b8',
                                        'inactivo' => '#dc3545'
                                    ];
                                    $estado_color = isset($estado_map[$curso['estado']]) ? $estado_map[$curso['estado']] : '#6c757d';
                                    ?>
                                    <span style="background: <?= $estado_color ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; font-weight: 500;">
                                        <?= ucfirst($curso['estado']) ?>
                                    </span>
                                </td>
                                <td style="padding: 15px;">
                                    <span style="color: #495057;">
                                        <?= htmlspecialchars($curso['creador_nombre'] ?? 'Master') ?>
                                    </span>
                                </td>
                                <td style="padding: 15px;">
                                    <?php if ($curso['docente_asignado']): ?>
                                        <span style="color: #28a745; font-weight: 500;">
                                            <?= htmlspecialchars($curso['docente_asignado']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #dc3545; font-style: italic;">Sin asignar</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 15px; text-align: center;">
                                    <span style="background: #f8f9fa; color: #495057; padding: 4px 8px; border-radius: 4px; font-weight: 500;">
                                        <?= $curso['total_inscritos'] ?>
                                    </span>
                                </td>
                                <td style="padding: 15px; text-align: center;">
                                    <span style="background: #f8f9fa; color: #495057; padding: 4px 8px; border-radius: 4px; font-weight: 500;">
                                        <?= $curso['total_modulos'] ?>
                                    </span>
                                </td>
                                <td style="padding: 15px; text-align: center;">
                                    <div class="div-fila-alt" style="gap: 8px; justify-content: center;">
                                        <a href="<?= BASE_URL ?>/master/editar_curso.php?id=<?= $curso['id'] ?>" 
                                           style="background: #007bff; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.85rem;"
                                           title="Editar curso">
                                            Editar
                                        </a>
                                        <?php if (!$curso['docente_asignado']): ?>
                                            <button onclick="asignarCurso(<?= $curso['id'] ?>, '<?= htmlspecialchars($curso['titulo']) ?>')"
                                                    style="background: #28a745; color: white; padding: 6px 12px; border: none; border-radius: 4px; font-size: 0.85rem; cursor: pointer;"
                                                    title="Asignar a docente">
                                                Asignar
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="eliminarCurso(<?= $curso['id'] ?>, '<?= htmlspecialchars($curso['titulo']) ?>')"
                                                style="background: #dc3545; color: white; padding: 6px 12px; border: none; border-radius: 4px; font-size: 0.85rem; cursor: pointer;"
                                                title="Eliminar curso">
                                            Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para asignar curso -->
<div id="modal-asignar" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 500px;">
        <h3 style="margin: 0 0 20px 0; color: #0066cc;">Asignar Curso a Docente</h3>
        
        <form id="form-asignar" action="<?= BASE_URL ?>/master/procesar_asignacion.php" method="POST">
            <input type="hidden" id="curso_id_asignar" name="curso_id">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500;">Curso:</label>
                <p id="curso_titulo_asignar" style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin: 0;"></p>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label for="docente_id_asignar" style="display: block; margin-bottom: 8px; font-weight: 500;">Docente:</label>
                <select id="docente_id_asignar" name="docente_id" required
                        style="width: 100%; padding: 10px; border: 2px solid #e1e5e9; border-radius: 4px;">
                    <option value="">Seleccionar docente</option>
                    <?php foreach ($docentes as $docente): ?>
                        <option value="<?= $docente['id'] ?>">
                            <?= htmlspecialchars($docente['nombre']) ?> (<?= htmlspecialchars($docente['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label for="instrucciones_asignar" style="display: block; margin-bottom: 8px; font-weight: 500;">Instrucciones (opcional):</label>
                <textarea id="instrucciones_asignar" name="instrucciones" rows="3"
                          style="width: 100%; padding: 10px; border: 2px solid #e1e5e9; border-radius: 4px; resize: vertical;"
                          placeholder="Instrucciones especiales para el docente"></textarea>
            </div>
            
            <div class="div-fila-alt" style="gap: 15px;">
                <button type="submit" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
                    Asignar Curso
                </button>
                <button type="button" onclick="cerrarModalAsignar()" style="background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function mostrarFormularioCrear() {
    document.getElementById('formulario-crear').style.display = 'block';
    document.getElementById('titulo').focus();
}

function ocultarFormularioCrear() {
    document.getElementById('formulario-crear').style.display = 'none';
    // Limpiar formulario
    document.querySelector('#formulario-crear form').reset();
}

function validarFormulario() {
    const titulo = document.getElementById('titulo').value.trim();
    const categoria = document.getElementById('categoria').value.trim();
    const dirigido_a = document.getElementById('dirigido_a').value.trim();
    
    if (!titulo) {
        alert('El título del curso es obligatorio');
        return false;
    }
    
    if (titulo.length > 255) {
        alert('El título no puede exceder 255 caracteres');
        return false;
    }
    
    if (!categoria) {
        alert('La categoría es obligatoria');
        return false;
    }
    
    if (categoria.length > 100) {
        alert('La categoría no puede exceder 100 caracteres');
        return false;
    }
    
    if (!dirigido_a) {
        alert('Debe especificar a quién está dirigido el curso');
        return false;
    }
    
    if (dirigido_a.length > 200) {
        alert('El campo "Dirigido a" no puede exceder 200 caracteres');
        return false;
    }
    
    const descripcion = document.getElementById('descripcion').value;
    if (descripcion.length > 1000) {
        alert('La descripción no puede exceder 1000 caracteres');
        return false;
    }
    
    return confirm('¿Está seguro de crear este curso?');
}

function asignarCurso(cursoId, cursoTitulo) {
    document.getElementById('curso_id_asignar').value = cursoId;
    document.getElementById('curso_titulo_asignar').textContent = cursoTitulo;
    document.getElementById('modal-asignar').style.display = 'block';
}

function cerrarModalAsignar() {
    document.getElementById('modal-asignar').style.display = 'none';
}

function eliminarCurso(cursoId, cursoTitulo) {
    if (confirm('¿Está COMPLETAMENTE SEGURO de eliminar el curso "' + cursoTitulo + '"?\n\nEsta acción NO se puede deshacer y eliminará:\n- El curso y toda su información\n- Todos los módulos, temas y lecciones\n- Todos los archivos asociados\n\nEscriba "ELIMINAR" para confirmar:')) {
        const confirmacion = prompt('Para confirmar, escriba exactamente: ELIMINAR');
        if (confirmacion === 'ELIMINAR') {
            window.location.href = '<?= BASE_URL ?>/master/eliminar_curso.php?id=' + cursoId;
        } else {
            alert('Eliminación cancelada. No se escribió "ELIMINAR" correctamente.');
        }
    }
}

// Cerrar modal al hacer clic fuera
document.getElementById('modal-asignar').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalAsignar();
    }
});

</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>