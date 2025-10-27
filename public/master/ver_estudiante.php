<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('master');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Master – Ver Estudiante';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/master/admin_estudiantes.php?error=id_invalido');
    exit;
}

$stmt = $conn->prepare("SELECT id, nombre, email, usuario, estado, created_at, updated_at FROM usuarios WHERE id = :id AND role = 'estudiante' LIMIT 1");
$stmt->execute([':id' => $id]);
$estudiante = $stmt->fetch();
if (!$estudiante) {
    header('Location: ' . BASE_URL . '/master/admin_estudiantes.php?error=estudiante_no_encontrado');
    exit;
}

// Estadísticas básicas del estudiante
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT i.curso_id) as cursos_inscritos,
        COUNT(DISTINCT CASE WHEN i.estado = 'completado' THEN i.curso_id END) as cursos_completados,
        ROUND(AVG(COALESCE(i.progreso, 0)), 0) as progreso_promedio,
        MAX(i.fecha_inscripcion) as ultima_actividad
    FROM inscripciones i
    WHERE i.usuario_id = :usuario_id
");
$stmt->execute([':usuario_id' => $id]);
$stats = $stmt->fetch();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/master.css">

<div class="contenido">
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #3498db); color: white; text-align: center;">
        <h2 style="margin: 0; font-size: 1.8rem; font-weight: 600;">Detalle del Estudiante</h2>
        <p style="margin: 5px 0 0 0; opacity: 0.9;">Perfil y estadísticas</p>
    </div>

    <div class="form-container-body" style="margin-bottom: 20px;">
        <a href="<?= BASE_URL ?>/master/admin_estudiantes.php" 
           style="background: #6c757d; color: white; padding: 10px 16px; border-radius: 8px; text-decoration: none;">← Volver a la lista</a>
    </div>

    <div class="form-container-body">
        <h3 style="color: var(--master-primary); margin-bottom: 15px; font-size: 1.3rem;">Información del Perfil</h3>
        <div class="div-fila" style="gap: 20px;">
            <div style="flex: 1; background: #f8f9fa; padding: 16px; border-radius: 8px;">
                <div style="font-weight: 600; color: #2c3e50;">Nombre</div>
                <div style="color: #34495e;"><?= htmlspecialchars($estudiante['nombre']) ?></div>
            </div>
            <div style="flex: 1; background: #f8f9fa; padding: 16px; border-radius: 8px;">
                <div style="font-weight: 600; color: #2c3e50;">Email</div>
                <div style="color: #34495e;"><?= htmlspecialchars($estudiante['email']) ?></div>
            </div>
            <div style="flex: 1; background: #f8f9fa; padding: 16px; border-radius: 8px;">
                <div style="font-weight: 600; color: #2c3e50;">Usuario</div>
                <div style="color: #34495e;"><?= htmlspecialchars($estudiante['usuario']) ?></div>
            </div>
        </div>
        <div class="div-fila" style="gap: 20px; margin-top: 15px;">
            <div style="flex: 1; background: #f8f9fa; padding: 16px; border-radius: 8px;">
                <div style="font-weight: 600; color: #2c3e50;">Estado</div>
                <div>
                    <span style="padding: 6px 12px; border-radius: 20px; font-size: 0.9rem; font-weight: 500; background: <?= $estudiante['estado'] === 'activo' ? '#d4edda' : '#f8d7da' ?>; color: <?= $estudiante['estado'] === 'activo' ? '#155724' : '#721c24' ?>;">
                        <?= ucfirst($estudiante['estado']) ?>
                    </span>
                </div>
            </div>
            <div style="flex: 1; background: #f8f9fa; padding: 16px; border-radius: 8px;">
                <div style="font-weight: 600; color: #2c3e50;">Registro</div>
                <div style="color: #34495e;"><?= date('d/m/Y', strtotime($estudiante['created_at'])) ?></div>
            </div>
        </div>
    </div>

    <div class="form-container-body">
        <h3 style="color: var(--master-primary); margin-bottom: 15px; font-size: 1.3rem;">Estadísticas</h3>
        <div class="div-fila" style="gap: 20px;">
            <div style="flex: 1; text-align: center; padding: 20px; background: linear-gradient(135deg, #3498db, #2980b9); color: white; border-radius: 12px;">
                <h3 style="margin: 0; font-size: 2rem;"><?= (int)($stats['cursos_inscritos'] ?? 0) ?></h3>
                <p style="margin: 5px 0 0 0; font-size: 0.9rem;">Cursos Inscritos</p>
            </div>
            <div style="flex: 1; text-align: center; padding: 20px; background: linear-gradient(135deg, #28a745, #1e7e34); color: white; border-radius: 12px;">
                <h3 style="margin: 0; font-size: 2rem;"><?= (int)($stats['cursos_completados'] ?? 0) ?></h3>
                <p style="margin: 5px 0 0 0; font-size: 0.9rem;">Cursos Completados</p>
            </div>
            <div style="flex: 1; text-align: center; padding: 20px; background: linear-gradient(135deg, #ffc107, #e0a800); color: white; border-radius: 12px;">
                <h3 style="margin: 0; font-size: 2rem;"><?= (int)($stats['progreso_promedio'] ?? 0) ?>%</h3>
                <p style="margin: 5px 0 0 0; font-size: 0.9rem;">Progreso Promedio</p>
            </div>
        </div>
        <div style="margin-top: 15px; color: #7f8c8d;">Última actividad: <?= !empty($stats['ultima_actividad']) ? date('d/m/Y', strtotime($stats['ultima_actividad'])) : '—' ?></div>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>