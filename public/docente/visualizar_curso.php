<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Visualización de curso';

$curso_id = $_GET['id'] ?? 0;

// Verificar que el curso pertenece al docente o está asignado a él
$stmt = $conn->prepare("
    SELECT c.*, u.nombre as creador_nombre
    FROM cursos c
    LEFT JOIN usuarios u ON c.creado_por = u.id
    WHERE c.id = :curso_id 
    AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
");
$stmt->execute([
    ':curso_id' => $curso_id, 
    ':docente_id' => $_SESSION['user_id'],
    ':docente_id2' => $_SESSION['user_id']
]);
$curso = $stmt->fetch();

if (!$curso) {
    header('Location: /imt-cursos/public/docente/admin_cursos.php?error=curso_no_encontrado');
    exit;
}

// Obtener estadísticas del curso
$stmt = $conn->prepare("
    SELECT 
        COUNT(i.id) as total_inscritos,
        COUNT(CASE WHEN i.estado = 'completado' THEN 1 END) as completados,
        AVG(COALESCE(i.progreso, 0)) as progreso_promedio
    FROM inscripciones i
    WHERE i.curso_id = :curso_id
");
$stmt->execute([':curso_id' => $curso_id]);
$stats = $stmt->fetch();

// Obtener estudiantes inscritos con su progreso
$stmt = $conn->prepare("
    SELECT u.nombre, u.email, i.progreso, i.fecha_inscripcion, i.fecha_completado, i.estado
    FROM inscripciones i
    INNER JOIN usuarios u ON i.usuario_id = u.id
    WHERE i.curso_id = :curso_id
    ORDER BY i.fecha_inscripcion DESC
");
$stmt->execute([':curso_id' => $curso_id]);
$estudiantes = $stmt->fetchAll();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="/imt-cursos/public/styles/css/docente.css">

<div class="contenido">
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white;">
        <div class="div-fila-alt-start">
            <div>
                <h1 style="font-size: 2rem; margin-bottom: 10px;">Visualización de Curso</h1>
                <p style="opacity: 0.9;"><?= htmlspecialchars($curso['titulo']) ?></p>
            </div>
            <a href="/imt-cursos/public/docente/admin_cursos.php" class="btn" 
               style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white; padding: 12px 20px; border-radius: 8px; text-decoration: none;">
                ← Volver
            </a>
        </div>
    </div>

    <!-- Estadísticas del curso -->
    <div class="course-stats-grid">
        <div class="course-stat-card">
            <div class="course-stat-value blue">
                <?= $stats['total_inscritos'] ?>
            </div>
            <div class="course-stat-label">Estudiantes Inscritos</div>
        </div>
        
        <div class="course-stat-card">
            <div class="course-stat-value green">
                <?= $stats['completados'] ?>
            </div>
            <div class="course-stat-label">Completados</div>
        </div>
        
        <div class="course-stat-card">
            <div class="course-stat-value orange">
                <?= number_format($stats['progreso_promedio'], 1) ?>%
            </div>
            <div class="course-stat-label">Progreso Promedio</div>
        </div>
    </div>

    <!-- Lista de estudiantes -->
    <div class="form-container-body">
        <h2 style="color: #3498db; margin-bottom: 25px;">Estudiantes Inscritos</h2>
        
        <?php if (empty($estudiantes)): ?>
            <div class="empty-state">
                <img src="/imt-cursos/public/styles/iconos/addicon.png" style="width: 64px; height: 64px; opacity: 0.5; margin-bottom: 20px;">
                <h3>No hay estudiantes inscritos</h3>
                <p>Cuando los estudiantes se inscriban aparecerán aquí</p>
            </div>
        <?php else: ?>
            <div class="students-table-container">
                <table class="students-table">
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th class="center">Progreso</th>
                            <th class="center">Estado</th>
                            <th class="center">Fecha Inscripción</th>
                            <th class="center">Fecha Completado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estudiantes as $estudiante): ?>
                            <tr>
                                <td>
                                    <div class="student-info">
                                        <div class="student-name"><?= htmlspecialchars($estudiante['nombre']) ?></div>
                                        <div class="student-email"><?= htmlspecialchars($estudiante['email']) ?></div>
                                    </div>
                                </td>
                                <td class="center">
                                    <div class="progress-container">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?= $estudiante['progreso'] ?>%;"></div>
                                        </div>
                                        <span class="progress-text"><?= number_format($estudiante['progreso'], 1) ?>%</span>
                                    </div>
                                </td>
                                <td class="center">
                                    <span class="student-status <?= $estudiante['estado'] ?>">
                                        <?= ucfirst($estudiante['estado']) ?>
                                    </span>
                                </td>
                                <td class="center" style="color: #7f8c8d;">
                                    <?= date('d/m/Y', strtotime($estudiante['fecha_inscripcion'])) ?>
                                </td>
                                <td class="center" style="color: #7f8c8d;">
                                    <?= $estudiante['fecha_completado'] ? date('d/m/Y', strtotime($estudiante['fecha_completado'])) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>