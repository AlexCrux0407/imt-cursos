<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Resultados de Evaluación';

$evaluacion_id = $_GET['id'] ?? 0;
$modulo_id = $_GET['modulo_id'] ?? 0;
$curso_id = $_GET['curso_id'] ?? 0;

// Verificar que la evaluación pertenece a un módulo de un curso del docente
$stmt = $conn->prepare("
    SELECT e.*, m.titulo as modulo_titulo, c.titulo as curso_titulo
    FROM evaluaciones_modulo e
    INNER JOIN modulos m ON e.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE e.id = :evaluacion_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
");
$stmt->execute([
    ':evaluacion_id' => $evaluacion_id, 
    ':docente_id' => $_SESSION['user_id'],
    ':docente_id2' => $_SESSION['user_id']
]);
$evaluacion = $stmt->fetch();

if (!$evaluacion) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=evaluacion_no_encontrada');
    exit;
}

// Obtener resultados de estudiantes con sus intentos
$stmt = $conn->prepare("
    SELECT 
        u.id as usuario_id,
        u.nombre as estudiante_nombre,
        u.email as estudiante_email,
        COUNT(i.id) as total_intentos,
        MAX(i.puntaje_obtenido) as mejor_puntaje,
        MIN(i.puntaje_obtenido) as peor_puntaje,
        AVG(i.puntaje_obtenido) as promedio_puntaje,
        MAX(i.fecha_fin) as ultimo_intento,
        MIN(i.fecha_inicio) as primer_intento,
        CASE 
            WHEN MAX(i.puntaje_obtenido) >= :puntaje_minimo THEN 'Aprobado'
            ELSE 'Reprobado'
        END as estado_final
    FROM usuarios u
    INNER JOIN inscripciones ins ON u.id = ins.usuario_id
    INNER JOIN intentos_evaluacion i ON u.id = i.usuario_id
    WHERE ins.curso_id = :curso_id 
    AND i.evaluacion_id = :evaluacion_id 
    AND i.estado = 'completado'
    AND i.puntaje_obtenido IS NOT NULL
    GROUP BY u.id, u.nombre, u.email
    ORDER BY mejor_puntaje DESC, estudiante_nombre ASC
");
$stmt->execute([
    ':curso_id' => $curso_id,
    ':evaluacion_id' => $evaluacion_id,
    ':puntaje_minimo' => $evaluacion['puntaje_minimo_aprobacion']
]);
$resultados = $stmt->fetchAll();

// Obtener estadísticas generales
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT u.id) as total_estudiantes_inscritos,
        COUNT(DISTINCT i.usuario_id) as estudiantes_realizaron,
        COUNT(i.id) as total_intentos_realizados,
        AVG(i.puntaje_obtenido) as promedio_general,
        MAX(i.puntaje_obtenido) as puntaje_maximo_obtenido,
        MIN(i.puntaje_obtenido) as puntaje_minimo_obtenido,
        COUNT(CASE WHEN i.puntaje_obtenido >= :puntaje_minimo THEN 1 END) as intentos_aprobados,
        COUNT(CASE WHEN i.puntaje_obtenido < :puntaje_minimo2 THEN 1 END) as intentos_reprobados
    FROM usuarios u
    INNER JOIN inscripciones ins ON u.id = ins.usuario_id
    LEFT JOIN intentos_evaluacion i ON u.id = i.usuario_id AND i.evaluacion_id = :evaluacion_id AND i.estado = 'completado' AND i.puntaje_obtenido IS NOT NULL
    WHERE ins.curso_id = :curso_id
");
$stmt->execute([
    ':curso_id' => $curso_id,
    ':evaluacion_id' => $evaluacion_id,
    ':puntaje_minimo' => $evaluacion['puntaje_minimo_aprobacion'],
    ':puntaje_minimo2' => $evaluacion['puntaje_minimo_aprobacion']
]);
$estadisticas = $stmt->fetch();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/docente.css">

<style>
.resultados-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 24px;
}

.resultados-title {
    font-size: 1.8rem;
    margin: 0 0 8px 0;
    font-weight: 600;
}

.resultados-subtitle {
    opacity: 0.9;
    margin: 0;
}

.estadisticas-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.estadistica-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
    border-left: 4px solid #3498db;
}

.estadistica-valor {
    font-size: 2rem;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 8px;
}

.estadistica-label {
    color: #7f8c8d;
    font-size: 0.9rem;
}

.resultados-table {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.table-header {
    background: #f8f9fa;
    padding: 16px 20px;
    border-bottom: 1px solid #dee2e6;
}

.table-title {
    margin: 0;
    color: #2c3e50;
    font-size: 1.2rem;
}

.estudiante-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr 150px;
    gap: 16px;
    padding: 16px 20px;
    border-bottom: 1px solid #f1f3f4;
    align-items: center;
}

.estudiante-row:hover {
    background: #f8f9fa;
}

.estudiante-info h4 {
    margin: 0 0 4px 0;
    color: #2c3e50;
    font-size: 1rem;
}

.estudiante-email {
    color: #7f8c8d;
    font-size: 0.85rem;
}

.puntaje-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 500;
    font-size: 0.9rem;
}

.puntaje-aprobado {
    background: #d4edda;
    color: #155724;
}

.puntaje-reprobado {
    background: #f8d7da;
    color: #721c24;
}

.intentos-badge {
    background: #e3f2fd;
    color: #1565c0;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}

.fecha-texto {
    font-size: 0.85rem;
    color: #6c757d;
}

.btn-volver {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-volver:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    color: white;
    text-decoration: none;
}

.no-resultados {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.no-resultados img {
    width: 64px;
    height: 64px;
    opacity: 0.5;
    margin-bottom: 16px;
}
</style>

<div class="contenido">
    <div class="resultados-header">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <h1 class="resultados-title">Resultados de Evaluación</h1>
                <p class="resultados-subtitle">
                    <?= htmlspecialchars($evaluacion['titulo']) ?> • 
                    <?= htmlspecialchars($evaluacion['modulo_titulo']) ?> • 
                    <?= htmlspecialchars($evaluacion['curso_titulo']) ?>
                </p>
            </div>
            <a href="<?= BASE_URL ?>/docente/evaluaciones_modulo.php?id=<?= $modulo_id ?>&curso_id=<?= $curso_id ?>" class="btn-volver">
                ← Volver a Evaluaciones
            </a>
        </div>
    </div>

    <!-- Estadísticas Generales -->
    <div class="estadisticas-grid">
        <div class="estadistica-card">
            <div class="estadistica-valor"><?= $estadisticas['total_estudiantes_inscritos'] ?></div>
            <div class="estadistica-label">Estudiantes Inscritos</div>
        </div>
        <div class="estadistica-card">
            <div class="estadistica-valor"><?= $estadisticas['estudiantes_realizaron'] ?></div>
            <div class="estadistica-label">Realizaron Evaluación</div>
        </div>
        <div class="estadistica-card">
            <div class="estadistica-valor"><?= $estadisticas['total_intentos_realizados'] ?></div>
            <div class="estadistica-label">Total de Intentos</div>
        </div>
        <div class="estadistica-card">
            <div class="estadistica-valor"><?= $estadisticas['promedio_general'] ? number_format($estadisticas['promedio_general'], 1) : '0' ?>pts</div>
            <div class="estadistica-label">Promedio General</div>
        </div>
        <div class="estadistica-card">
            <div class="estadistica-valor"><?= $estadisticas['intentos_aprobados'] ?></div>
            <div class="estadistica-label">Intentos Aprobados</div>
        </div>
        <div class="estadistica-card">
            <div class="estadistica-valor"><?= $estadisticas['intentos_reprobados'] ?></div>
            <div class="estadistica-label">Intentos Reprobados</div>
        </div>
    </div>

    <!-- Tabla de Resultados -->
    <div class="resultados-table">
        <div class="table-header">
            <h3 class="table-title">Resultados por Estudiante</h3>
        </div>

        <?php if (empty($resultados)): ?>
            <div class="no-resultados">
                <img src="<?= BASE_URL ?>/styles/iconos/desk.png" alt="Sin resultados">
                <h4>No hay resultados disponibles</h4>
                <p>Ningún estudiante ha completado esta evaluación aún.</p>
            </div>
        <?php else: ?>
            <!-- Encabezados de tabla -->
            <div class="estudiante-row" style="background: #f8f9fa; font-weight: 600; color: #495057;">
                <div>Estudiante</div>
                <div>Intentos</div>
                <div>Mejor Puntaje</div>
                <div>Promedio</div>
                <div>Estado</div>
                <div>Primer Intento</div>
                <div>Último Intento</div>
            </div>

            <?php foreach ($resultados as $resultado): ?>
                <div class="estudiante-row">
                    <div class="estudiante-info">
                        <h4><?= htmlspecialchars($resultado['estudiante_nombre']) ?></h4>
                        <div class="estudiante-email"><?= htmlspecialchars($resultado['estudiante_email']) ?></div>
                    </div>
                    <div>
                        <span class="intentos-badge"><?= $resultado['total_intentos'] ?> intento<?= $resultado['total_intentos'] != 1 ? 's' : '' ?></span>
                    </div>
                    <div>
                        <span class="puntaje-badge <?= $resultado['mejor_puntaje'] >= $evaluacion['puntaje_minimo_aprobacion'] ? 'puntaje-aprobado' : 'puntaje-reprobado' ?>">
                            <?= number_format($resultado['mejor_puntaje'], 1) ?>pts
                        </span>
                    </div>
                    <div>
                        <?= number_format($resultado['promedio_puntaje'], 1) ?>pts
                    </div>
                    <div>
                        <span class="puntaje-badge <?= $resultado['estado_final'] == 'Aprobado' ? 'puntaje-aprobado' : 'puntaje-reprobado' ?>">
                            <?= $resultado['estado_final'] ?>
                        </span>
                    </div>
                    <div class="fecha-texto">
                        <?= date('d/m/Y H:i', strtotime($resultado['primer_intento'])) ?>
                    </div>
                    <div class="fecha-texto">
                        <?= date('d/m/Y H:i', strtotime($resultado['ultimo_intento'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>