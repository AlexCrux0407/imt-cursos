<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('master');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Master – Ver Docente';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/master/admin_docentes.php?error=id_invalido');
    exit;
}

$stmt = $conn->prepare("SELECT id, nombre, email, usuario, estado, created_at, updated_at FROM usuarios WHERE id = :id AND role = 'docente' LIMIT 1");
$stmt->execute([':id' => $id]);
$docente = $stmt->fetch();
if (!$docente) {
    header('Location: ' . BASE_URL . '/master/admin_docentes.php?error=docente_no_encontrado');
    exit;
}

// Estadísticas básicas del docente
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT c.id) as cursos_asignados,
        COUNT(DISTINCT CASE WHEN c.estado = 'activo' THEN c.id END) as cursos_activos,
        COUNT(DISTINCT i.usuario_id) as total_estudiantes,
        MAX(c.fecha_asignacion) as ultima_asignacion
    FROM cursos c
    LEFT JOIN inscripciones i ON c.id = i.curso_id
    WHERE c.asignado_a = :docente_id
");
$stmt->execute([':docente_id' => $id]);
$stats = $stmt->fetch();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/master.css">

<div class="contenido">
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #3498db); color: white; text-align: center;">
        <h2 style="margin: 0; font-size: 1.8rem; font-weight: 600;">Detalle del Docente</h2>
        <p style="margin: 5px 0 0 0; opacity: 0.9;">Perfil y estadísticas</p>
    </div>

    <div class="form-container-body" style="margin-bottom: 20px;">
        <a href="<?= BASE_URL ?>/master/admin_docentes.php" 
           style="background: #6c757d; color: white; padding: 10px 16px; border-radius: 8px; text-decoration: none;">← Volver a la lista</a>
    </div>

    <div class="form-container-body">
        <h3 style="color: var(--master-primary); margin-bottom: 15px; font-size: 1.3rem;">Información del Perfil</h3>
        <div class="div-fila" style="gap: 20px;">
            <div style="flex: 1; background: #f8f9fa; padding: 16px; border-radius: 8px;">
                <div style="font-weight: 600; color: #2c3e50;">Nombre</div>
                <div style="color: #34495e;"><?= htmlspecialchars($docente['nombre']) ?></div>
            </div>
            <div style="flex: 1; background: #f8f9fa; padding: 16px; border-radius: 8px;">
                <div style="font-weight: 600; color: #2c3e50;">Email</div>
                <div style="color: #34495e;"><?= htmlspecialchars($docente['email']) ?></div>
            </div>
            <div style="flex: 1; background: #f8f9fa; padding: 16px; border-radius: 8px;">
                <div style="font-weight: 600; color: #2c3e50;">Usuario</div>
                <div style="color: #34495e;"><?= htmlspecialchars($docente['usuario']) ?></div>
            </div>
        </div>
        <div class="div-fila" style="gap: 20px; margin-top: 15px;">
            <div style="flex: 1; background: #f8f9fa; padding: 16px; border-radius: 8px;">
                <div style="font-weight: 600; color: #2c3e50;">Estado</div>
                <div>
                    <span style="padding: 6px 12px; border-radius: 20px; font-size: 0.9rem; font-weight: 500; background: <?= $docente['estado'] === 'activo' ? '#d4edda' : '#f8d7da' ?>; color: <?= $docente['estado'] === 'activo' ? '#155724' : '#721c24' ?>;">
                        <?= ucfirst($docente['estado']) ?>
                    </span>
                </div>
            </div>
            <div style="flex: 1; background: #f8f9fa; padding: 16px; border-radius: 8px;">
                <div style="font-weight: 600; color: #2c3e50;">Registro</div>
                <div style="color: #34495e;"><?= date('d/m/Y', strtotime($docente['created_at'])) ?></div>
            </div>
        </div>
    </div>

    <div class="form-container-body">
        <h3 style="color: var(--master-primary); margin-bottom: 15px; font-size: 1.3rem;">Estadísticas</h3>
        <div class="div-fila" style="gap: 20px;">
            <div style="flex: 1; text-align: center; padding: 20px; background: linear-gradient(135deg, #3498db, #2980b9); color: white; border-radius: 12px;">
                <h3 style="margin: 0; font-size: 2rem;"><?= (int)($stats['cursos_asignados'] ?? 0) ?></h3>
                <p style="margin: 5px 0 0 0; font-size: 0.9rem;">Cursos Asignados</p>
            </div>
            <div style="flex: 1; text-align: center; padding: 20px; background: linear-gradient(135deg, #28a745, #1e7e34); color: white; border-radius: 12px;">
                <h3 style="margin: 0; font-size: 2rem;"><?= (int)($stats['cursos_activos'] ?? 0) ?></h3>
                <p style="margin: 5px 0 0 0; font-size: 0.9rem;">Cursos Activos</p>
            </div>
            <div style="flex: 1; text-align: center; padding: 20px; background: linear-gradient(135deg, #ffc107, #e0a800); color: white; border-radius: 12px;">
                <h3 style="margin: 0; font-size: 2rem;"><?= (int)($stats['total_estudiantes'] ?? 0) ?></h3>
                <p style="margin: 5px 0 0 0; font-size: 0.9rem;">Estudiantes Totales</p>
            </div>
        </div>
        <div style="margin-top: 15px; color: #7f8c8d;">Última asignación: <?= !empty($stats['ultima_asignacion']) ? date('d/m/Y', strtotime($stats['ultima_asignacion'])) : '—' ?></div>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>