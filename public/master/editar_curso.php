<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('master');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Master – Editar Curso';

$curso_id = (int)($_GET['id'] ?? 0);

if ($curso_id === 0) {
    header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=curso_no_especificado');
    exit;
}

// Obtener información del curso
$stmt = $conn->prepare("
    SELECT c.*, 
           u_creador.nombre as creador_nombre,
           u_asignado.nombre as docente_asignado
    FROM cursos c
    LEFT JOIN usuarios u_creador ON c.creado_por = u_creador.id
    LEFT JOIN usuarios u_asignado ON c.asignado_a = u_asignado.id
    WHERE c.id = :curso_id
");
$stmt->execute([':curso_id' => $curso_id]);
$curso = $stmt->fetch();

if (!$curso) {
    header('Location: ' . BASE_URL . '/master/admin_cursos.php?error=curso_no_encontrado');
    exit;
}

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
    <div class="form-container-head" style="background: linear-gradient(135deg, #0066cc, #004d99); color: white; text-align: center;">
        <h1 style="margin: 0; font-size: 2rem; font-weight: 600;">Editar Curso</h1>
        <p style="margin: 10px 0 0 0; opacity: 0.9;">Modifica la información del curso</p>
    </div>

    <!-- Mensajes de estado -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
            Curso actualizado exitosamente.
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
                default:
                    echo 'Ha ocurrido un error. Inténtalo nuevamente.';
            }
            ?>
        </div>
    <?php endif; ?>

    <!-- Información del curso -->
    <div class="form-container-body" style="margin-bottom: 20px;">
        <div class="div-fila" style="gap: 20px; align-items: center;">
            <div style="flex: 1;">
                <h3 style="color: #0066cc; margin: 0;">
                    <?= htmlspecialchars($curso['titulo']) ?>
                </h3>
                <p style="margin: 5px 0 0 0; color: #6c757d;">
                    Creado por: <?= htmlspecialchars($curso['creador_nombre'] ?? 'Master') ?> • 
                    Fecha: <?= date('d/m/Y', strtotime($curso['created_at'])) ?>
                </p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/master/admin_cursos.php" 
                   style="background: #6c757d; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 500;">
                    ← Volver a la lista
                </a>
            </div>
        </div>
    </div>

    <!-- Formulario de edición -->
    <div class="form-container-body">
        <form action="<?= BASE_URL ?>/master/actualizar_curso.php" method="POST">
            <input type="hidden" name="curso_id" value="<?= $curso['id'] ?>">
            
            <div class="div-fila" style="gap: 20px; margin-bottom: 20px;">
                <div style="flex: 2;">
                    <label for="titulo" style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">
                        Título del Curso *
                    </label>
                    <input type="text" id="titulo" name="titulo" required
                           value="<?= htmlspecialchars($curso['titulo']) ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem;">
                </div>
                <div style="flex: 1;">
                    <label for="categoria" style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">
                        Categoría *
                    </label>
                    <input type="text" id="categoria" name="categoria" required maxlength="100"
                           value="<?= htmlspecialchars($curso['categoria'] ?? '') ?>"
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
                           value="<?= htmlspecialchars($curso['dirigido_a'] ?? '') ?>"
                           style="width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem;"
                           placeholder="Ej: Principiantes, Profesionales, Estudiantes universitarios, etc.">
                    <small style="color: #6c757d; font-size: 0.85rem;">Máximo 200 caracteres</small>
                </div>
                <div style="flex: 1;">
                    <label for="estado" style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">
                        Estado del curso
                    </label>
                    <select id="estado" name="estado"
                            style="width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem;">
                        <option value="borrador" <?= $curso['estado'] === 'borrador' ? 'selected' : '' ?>>Borrador</option>
                        <option value="activo" <?= $curso['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="revision" <?= $curso['estado'] === 'revision' ? 'selected' : '' ?>>En Revisión</option>
                        <option value="inactivo" <?= $curso['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="descripcion" style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">
                    Descripción del Curso
                </label>
                <textarea id="descripcion" name="descripcion" rows="4"
                          style="width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem; resize: vertical;"
                          placeholder="Describe el contenido y objetivos del curso"><?= htmlspecialchars($curso['descripcion'] ?? '') ?></textarea>
            </div>

            <!-- Asignación de docente -->
            <div style="margin-bottom: 20px;">
                <label for="asignado_a" style="display: block; margin-bottom: 8px; font-weight: 500; color: #333;">
                    Asignar a docente
                </label>
                <select id="asignado_a" name="asignado_a"
                        style="width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem;">
                    <option value="">Sin asignar</option>
                    <?php foreach ($docentes as $docente): ?>
                        <option value="<?= $docente['id'] ?>" <?= $curso['asignado_a'] == $docente['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($docente['nombre']) ?> (<?= htmlspecialchars($docente['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($curso['docente_asignado']): ?>
                    <p style="margin: 5px 0 0 0; color: #28a745; font-size: 0.9rem;">
                        Actualmente asignado a: <strong><?= htmlspecialchars($curso['docente_asignado']) ?></strong>
                    </p>
                <?php endif; ?>
            </div>

            <div class="div-fila-alt" style="gap: 15px;">
                <button type="submit" 
                        style="background: #28a745; color: white; padding: 12px 24px; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer;">
                    Actualizar Curso
                </button>
                <a href="<?= BASE_URL ?>/master/admin_cursos.php"
                   style="background: #6c757d; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-size: 1rem; font-weight: 500;">
                    Cancelar
                </a>
            </div>
        </form>
    </div>

    <!-- Información adicional del curso -->
    <div class="form-container-body">
        <h4 style="color: #0066cc; margin-bottom: 15px;">Información del Curso</h4>
        
        <div class="div-fila" style="gap: 20px;">
            <div style="flex: 1; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <h5 style="margin: 0 0 10px 0; color: #495057;">Estado Actual</h5>
                <?php
                $estado_color = match($curso['estado']) {
                    'activo' => '#28a745',
                    'borrador' => '#ffc107',
                    'revision' => '#17a2b8',
                    'inactivo' => '#dc3545',
                    default => '#6c757d'
                };
                ?>
                <span style="background: <?= $estado_color ?>; color: white; padding: 6px 12px; border-radius: 4px; font-weight: 500;">
                    <?= ucfirst($curso['estado']) ?>
                </span>
            </div>
            
            <div style="flex: 1; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <h5 style="margin: 0 0 10px 0; color: #495057;">Fecha de Creación</h5>
                <p style="margin: 0; color: #6c757d;">
                    <?= date('d/m/Y H:i', strtotime($curso['created_at'])) ?>
                </p>
            </div>
            
            <?php if ($curso['fecha_asignacion']): ?>
            <div style="flex: 1; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <h5 style="margin: 0 0 10px 0; color: #495057;">Fecha de Asignación</h5>
                <p style="margin: 0; color: #6c757d;">
                    <?= date('d/m/Y H:i', strtotime($curso['fecha_asignacion'])) ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>