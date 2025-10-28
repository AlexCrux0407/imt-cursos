<?php
// Vista Estudiante – Cursos Completados: estadísticas y tarjetas de cursos finalizados
require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Estudiante – Cursos Completados';

$estudiante_id = $_SESSION['user_id'];

// Obtener cursos completados con información detallada
$stmt = $conn->prepare("
    SELECT c.*, i.progreso, i.fecha_inscripcion, i.fecha_completado,
           u.nombre as docente_nombre,
           COUNT(DISTINCT m.id) as total_modulos,
           AVG(CASE WHEN ie.puntaje_obtenido IS NOT NULL THEN ie.puntaje_obtenido ELSE 0 END) as promedio_evaluaciones
    FROM inscripciones i
    INNER JOIN cursos c ON i.curso_id = c.id
    LEFT JOIN usuarios u ON c.creado_por = u.id
    LEFT JOIN modulos m ON c.id = m.curso_id
    LEFT JOIN evaluaciones_modulo em ON m.id = em.modulo_id
    LEFT JOIN intentos_evaluacion ie ON em.id = ie.evaluacion_id AND ie.usuario_id = i.usuario_id
    WHERE i.usuario_id = :estudiante_id AND i.estado = 'completado'
    GROUP BY c.id, i.progreso, i.fecha_inscripcion, i.fecha_completado, u.nombre
    ORDER BY i.fecha_completado DESC
");
$stmt->execute([':estudiante_id' => $estudiante_id]);
$cursos_completados = $stmt->fetchAll();

// Obtener estadísticas generales
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_completados,
        AVG(DATEDIFF(fecha_completado, fecha_inscripcion)) as promedio_dias_completar
    FROM inscripciones 
    WHERE usuario_id = :estudiante_id AND estado = 'completado'
");
$stmt->execute([':estudiante_id' => $estudiante_id]);
$estadisticas = $stmt->fetch();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/estudiante.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/catalogo.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/mis-cursos.css">

<style>
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes slideInRight {
    from { opacity: 0; transform: translateX(20px); }
    to { opacity: 1; transform: translateX(0); }
}

.contenido {
    animation: fadeInUp 0.8s ease-out;
}

.catalogo-header {
    background: linear-gradient(135deg, #3498db, #2980b9);
    color: white;
    padding: 40px 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    text-align: center;
}

.catalogo-title {
    font-size: 2.2rem;
    font-weight: 600;
    margin-bottom: 10px;
}

.catalogo-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
}

.estadisticas-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.estadistica-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    text-align: center;
    border-left: 4px solid #3498db;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    animation: fadeInUp 0.6s ease-out both;
}

.estadistica-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}

.estadistica-card:nth-child(2) { animation-delay: 0.1s; }

.estadistica-numero {
    font-size: 2.5rem;
    font-weight: 700;
    color: #3498db;
    margin-bottom: 8px;
}

.estadistica-label {
    font-size: 0.9rem;
    color: #7f8c8d;
    font-weight: 500;
}

.cursos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.curso-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    padding: 25px;
    transition: all 0.3s ease;
    border: 1px solid #f8f9fa;
    animation: fadeInUp 0.6s ease-out both;
    position: relative;
    overflow: hidden;
}

.curso-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3498db, #2980b9);
}

.curso-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
}

/* Escalonar la entrada de las tarjetas */
.curso-card:nth-child(2) { animation-delay: 0.05s; }
.curso-card:nth-child(3) { animation-delay: 0.1s; }
.curso-card:nth-child(4) { animation-delay: 0.15s; }
.curso-card:nth-child(5) { animation-delay: 0.2s; }
.curso-card:nth-child(6) { animation-delay: 0.25s; }

.curso-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.curso-estado {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: #d4edda;
    color: #155724;
}

.curso-titulo {
    font-size: 1.3rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 10px;
    line-height: 1.3;
}

.curso-descripcion {
    color: #5a5c69;
    margin-bottom: 15px;
    line-height: 1.5;
    font-size: 0.95rem;
}

.curso-instructor {
    margin: 15px 0;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
    font-size: 0.9rem;
    color: #495057;
}

.curso-completado-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
    margin: 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 10px;
}

.info-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 5px;
}

.info-icono {
    font-size: 1.2rem;
}

.info-valor {
    font-weight: 600;
    color: #2c3e50;
    font-size: 0.9rem;
}

.info-label {
    font-size: 0.8rem;
    color: #7f8c8d;
}

.curso-acciones {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.btn-continuar, .btn-certificado {
    flex: 1;
    padding: 12px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    text-align: center;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.btn-continuar {
    background: #27ae60;
    color: white;
}

.btn-continuar:hover {
    background: #229954;
    transform: translateY(-1px);
}

.btn-certificado {
    background: #3498db;
    color: white;
}

.btn-certificado:hover {
    background: #2980b9;
    transform: translateY(-1px);
}

/* Estado vacío */
.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: #7f8c8d;
    animation: fadeInUp 0.8s ease-out;
}

.empty-state img {
    width: 64px;
    height: 64px;
    opacity: 0.3;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: #2c3e50;
    margin-bottom: 15px;
    font-size: 1.5rem;
}

.empty-state p {
    margin-bottom: 25px;
    font-size: 1.1rem;
}

.btn-primary {
    background: #3498db;
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-block;
}

.btn-primary:hover {
    background: #2980b9;
    transform: translateY(-1px);
}

/* Enlaces de navegación */
.navegacion-enlaces {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 40px;
    padding-top: 30px;
    border-top: 1px solid #e9ecef;
}

.enlace-nav {
    color: #3498db;
    text-decoration: none;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    animation: slideInRight 0.6s ease-out both;
}

.enlace-nav:hover {
    color: #2980b9;
    transform: translateX(5px);
}

.enlace-nav:nth-child(2) { animation-delay: 0.1s; }

.icono-nav {
    font-size: 1.2rem;
}

/* Responsive */
@media (max-width: 768px) {
    .catalogo-title {
        font-size: 1.8rem;
    }
    
    .estadisticas-container {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .cursos-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .curso-acciones {
        flex-direction: column;
    }
    
    .navegacion-enlaces {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
}
</style>

<div class="contenido">
    <div class="catalogo-header">
        <div class="header-content">
            <h1 class="catalogo-title">Cursos Completados</h1>
            <p class="catalogo-subtitle">¡Felicitaciones por tu dedicación y logros académicos!</p>
        </div>
    </div>

    <!-- Grid de cursos completados -->
    <div class="cursos-grid">
        <?php if (empty($cursos_completados)): ?>
            <div class="empty-state" style="grid-column: 1 / -1;">
                <img src="<?= BASE_URL ?>/styles/iconos/desk.png" alt="Sin cursos">
                <h3>¡Aún no has completado ningún curso!</h3>
                <p>Cuando completes tu primer curso, aparecerá aquí con toda la información de tu logro.</p>
                <a href="<?= BASE_URL ?>/estudiante/catalogo.php" class="btn-primary">Explorar Cursos</a>
            </div>
        <?php else: ?>
            <?php foreach ($cursos_completados as $curso): ?>
                <div class="curso-card mis-cursos">
                    <div class="curso-header">
                        <div class="curso-categoria"><?= htmlspecialchars($curso['categoria'] ?? 'General') ?></div>
                        <div class="curso-estado completado">✓ Completado</div>
                    </div>

                    <h3 class="curso-titulo"><?= htmlspecialchars($curso['titulo']) ?></h3>
                    <p class="curso-descripcion">
                        <?php 
                        $descripcion = $curso['descripcion'] ?? '';
                        $descripcion_corta = strlen($descripcion) > 120 ? substr($descripcion, 0, 120) . '...' : $descripcion;
                        echo htmlspecialchars($descripcion_corta);
                        ?>
                    </p>

                    <?php if (!empty($curso['docente_nombre'])): ?>
                        <div class="curso-instructor">
                            <strong>Instructor:</strong> <?= htmlspecialchars($curso['docente_nombre']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="progreso-container">
                        <div class="progreso-info">
                            <span class="progreso-texto">Progreso: 100%</span>
                            <span class="progreso-detalles"><?= $curso['total_modulos'] ?> módulos</span>
                        </div>
                        <div class="progreso-barra">
                            <div class="progreso-fill" style="width: 100%"></div>
                        </div>
                    </div>

                    <div class="curso-estadisticas">
                        <div class="estadistica-item">
                            <span class="estadistica-icono">📚</span>
                            <span class="estadistica-valor"><?= $curso['total_modulos'] ?> módulos</span>
                        </div>
                        <div class="estadistica-item">
                            <span class="estadistica-icono">📅</span>
                            <span class="estadistica-valor">Completado: <?= date('d/m/Y', strtotime($curso['fecha_completado'])) ?></span>
                        </div>
                    </div>

                    <div class="curso-acciones">
                        <a href="<?= BASE_URL ?>/estudiante/certificado.php?curso_id=<?= $curso['id'] ?>" class="btn-certificado">
                            Ver Certificado
                        </a>
                        <a href="<?= BASE_URL ?>/estudiante/curso_contenido.php?id=<?= $curso['id'] ?>" class="btn-continuar">
                            Revisar Curso
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Enlaces de navegación -->
    <div class="navegacion-enlaces">
        <a href="<?= BASE_URL ?>/estudiante/dashboard.php" class="enlace-nav">
            <i class="icono-nav">←</i> Volver al Dashboard
        </a>
        <a href="<?= BASE_URL ?>/estudiante/mis_cursos.php" class="enlace-nav">
            Ver Mis Cursos <i class="icono-nav">→</i>
        </a>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>