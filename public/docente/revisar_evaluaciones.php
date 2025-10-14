<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Revisar Evaluaciones';

// Obtener evaluaciones pendientes de revisión para cursos del docente
$stmt = $conn->prepare("
    SELECT 
        i.id as intento_id,
        i.usuario_id,
        i.evaluacion_id,
        i.numero_intento,
        i.fecha_inicio,
        i.fecha_fin,
        i.puntaje_obtenido,
        u.nombre as estudiante_nombre,
        u.email as estudiante_email,
        e.titulo as evaluacion_titulo,
        e.puntaje_maximo,
        e.puntaje_minimo_aprobacion,
        m.titulo as modulo_titulo,
        c.titulo as curso_titulo,
        c.id as curso_id,
        COUNT(r.id) as total_respuestas,
        COUNT(CASE WHEN r.requiere_revision = 1 THEN 1 END) as respuestas_pendientes
    FROM intentos_evaluacion i
    INNER JOIN usuarios u ON i.usuario_id = u.id
    INNER JOIN evaluaciones_modulo e ON i.evaluacion_id = e.id
    INNER JOIN modulos m ON e.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    LEFT JOIN respuestas_estudiante r ON i.id = r.intento_id
    WHERE i.estado = 'completado' 
    AND i.puntaje_obtenido IS NULL
    AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id)
    GROUP BY i.id
    HAVING respuestas_pendientes > 0
    ORDER BY i.fecha_fin DESC
");
$stmt->execute([':docente_id' => $_SESSION['user_id']]);
$intentos_pendientes = $stmt->fetchAll();

require __DIR__ . '/../partials/header.php';
?>

<div class="container">
    <div class="page-header">
        <div class="header-content">
            <h1 class="page-title">
                <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="Revisar" class="page-icon">
                Revisar Evaluaciones
            </h1>
            <p class="page-description">Califica las respuestas de texto de tus estudiantes</p>
        </div>
    </div>

    <?php if (empty($intentos_pendientes)): ?>
        <div class="alert alert-info">
            <h4>✅ Todo al día</h4>
            <p>No hay evaluaciones pendientes de revisión en este momento.</p>
            <a href="<?= BASE_URL ?>/docente/admin_cursos.php" class="btn-primary">Volver a Mis Cursos</a>
        </div>
    <?php else: ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= count($intentos_pendientes) ?></div>
                <div class="stat-label">Evaluaciones Pendientes</div>
            </div>
        </div>

        <div class="evaluaciones-pendientes">
            <?php foreach ($intentos_pendientes as $intento): ?>
                <div class="evaluacion-card">
                    <div class="evaluacion-header">
                        <div class="evaluacion-info">
                            <h3 class="evaluacion-titulo"><?= htmlspecialchars($intento['evaluacion_titulo']) ?></h3>
                            <div class="evaluacion-meta">
                                <span class="curso-info"><?= htmlspecialchars($intento['curso_titulo']) ?> → <?= htmlspecialchars($intento['modulo_titulo']) ?></span>
                            </div>
                        </div>
                        <div class="evaluacion-status">
                            <span class="status-badge status-pending">Pendiente</span>
                        </div>
                    </div>

                    <div class="estudiante-info">
                        <div class="estudiante-avatar">
                            <img src="<?= BASE_URL ?>/styles/iconos/user.png" alt="Estudiante">
                        </div>
                        <div class="estudiante-datos">
                            <div class="estudiante-nombre"><?= htmlspecialchars($intento['estudiante_nombre']) ?></div>
                            <div class="estudiante-email"><?= htmlspecialchars($intento['estudiante_email']) ?></div>
                        </div>
                    </div>

                    <div class="evaluacion-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?= $intento['respuestas_pendientes'] ?></span>
                            <div class="stat-label">Respuestas por Revisar</div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?= $intento['numero_intento'] ?></span>
                            <div class="stat-label">Intento #</div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?= date('d/m/Y H:i', strtotime($intento['fecha_fin'])) ?></span>
                            <div class="stat-label">Enviado</div>
                        </div>
                    </div>

                    <div class="evaluacion-actions">
                        <a href="<?= BASE_URL ?>/docente/calificar_intento.php?id=<?= $intento['intento_id'] ?>" 
                           class="btn-primary">
                            <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="Calificar">
                            Calificar Evaluación
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-value {
    font-size: 2.5rem;
    font-weight: bold;
    color: #e74c3c;
    display: block;
}

.stat-label {
    color: #666;
    font-size: 0.9rem;
    margin-top: 5px;
}

.evaluaciones-pendientes {
    display: grid;
    gap: 20px;
}

.evaluacion-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 20px;
    border-left: 4px solid #e74c3c;
}

.evaluacion-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.evaluacion-titulo {
    margin: 0 0 5px 0;
    color: #2c3e50;
    font-size: 1.2rem;
}

.evaluacion-meta {
    color: #666;
    font-size: 0.9rem;
}

.curso-info {
    background: #f8f9fa;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.estudiante-info {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
}

.estudiante-avatar img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
}

.estudiante-nombre {
    font-weight: 500;
    color: #2c3e50;
}

.estudiante-email {
    font-size: 0.9rem;
    color: #666;
}

.evaluacion-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.evaluacion-stats .stat-item {
    text-align: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
}

.evaluacion-stats .stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: #3498db;
}

.evaluacion-stats .stat-label {
    font-size: 0.8rem;
    color: #666;
    margin-top: 2px;
}

.evaluacion-actions {
    text-align: right;
}

.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #3498db;
    color: white;
    padding: 10px 20px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    transition: background-color 0.3s;
}

.btn-primary:hover {
    background: #2980b9;
    color: white;
    text-decoration: none;
}

.btn-primary img {
    width: 16px;
    height: 16px;
}

.alert {
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-info {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}

.alert h4 {
    margin: 0 0 10px 0;
}

.alert p {
    margin: 0 0 15px 0;
}
</style>

<?php require __DIR__ . '/../partials/footer.php'; ?>