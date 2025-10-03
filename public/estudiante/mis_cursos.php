<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Estudiante – Mis Cursos';

$estudiante_id = $_SESSION['user_id'];

// Filtros de búsqueda
$estado_filtro = $_GET['estado'] ?? '';
$buscar = trim($_GET['buscar'] ?? '');
$ordenar = $_GET['ordenar'] ?? 'recientes';

// Construir consulta con filtros
$where_conditions = ["i.usuario_id = :estudiante_id"];
$params = [':estudiante_id' => $estudiante_id];

if (!empty($estado_filtro)) {
    if ($estado_filtro === 'completado') {
        $where_conditions[] = "i.progreso = 100";
    } elseif ($estado_filtro === 'en_progreso') {
        $where_conditions[] = "i.progreso > 0 AND i.progreso < 100";
    } elseif ($estado_filtro === 'sin_iniciar') {
        $where_conditions[] = "i.progreso = 0";
    }
}

if (!empty($buscar)) {
    $where_conditions[] = "(c.titulo LIKE :buscar OR c.descripcion LIKE :buscar OR c.categoria LIKE :buscar)";
    $params[':buscar'] = "%$buscar%";
}

$where_clause = implode(' AND ', $where_conditions);

// Determinar ordenamiento
$order_clause = match($ordenar) {
    'alfabetico' => 'c.titulo ASC',
    'progreso_desc' => 'i.progreso DESC',
    'progreso_asc' => 'i.progreso ASC',
    'recientes' => 'i.fecha_inscripcion DESC',
    default => 'i.fecha_inscripcion DESC'
};

// Obtener cursos del estudiante
$stmt = $conn->prepare("
    SELECT c.*, i.progreso, i.fecha_inscripcion, i.estado as estado_inscripcion,
           u.nombre as docente_nombre,
           COUNT(DISTINCT m.id) as total_modulos,
           COUNT(DISTINCT CASE WHEN pm.completado = 1 THEN m.id END) as modulos_completados,
           COUNT(DISTINCT e.id) as total_evaluaciones,
           COUNT(DISTINCT CASE WHEN pm.evaluacion_completada = 1 THEN e.id END) as evaluaciones_completadas
    FROM inscripciones i
    INNER JOIN cursos c ON i.curso_id = c.id
    LEFT JOIN usuarios u ON c.creado_por = u.id
    LEFT JOIN modulos m ON c.id = m.curso_id
    LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = i.usuario_id
    LEFT JOIN evaluaciones_modulo e ON m.id = e.modulo_id AND e.activo = 1
    WHERE $where_clause
    GROUP BY c.id, i.progreso, i.fecha_inscripcion, i.estado, u.nombre
    ORDER BY $order_clause
");

$stmt->execute($params);
$cursos = $stmt->fetchAll();

// Obtener estadísticas generales
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_cursos,
        COUNT(CASE WHEN progreso = 100 THEN 1 END) as cursos_completados,
        COUNT(CASE WHEN progreso > 0 AND progreso < 100 THEN 1 END) as cursos_en_progreso,
        COUNT(CASE WHEN progreso = 0 THEN 1 END) as cursos_sin_iniciar,
        AVG(progreso) as progreso_promedio
    FROM inscripciones 
    WHERE usuario_id = :estudiante_id
");
$stmt->execute([':estudiante_id' => $estudiante_id]);
$estadisticas = $stmt->fetch();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/catalogo.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/mis-cursos.css">

<div class="contenido">
    <div class="catalogo-header">
        <div class="header-content">
            <h1 class="catalogo-title">Mis Cursos</h1>
            <p class="catalogo-subtitle">Gestiona tu progreso de aprendizaje</p>
        </div>
    </div>

    <!-- Estadísticas generales -->
    <div class="estadisticas-container">
        <div class="estadistica-card">
            <div class="estadistica-numero"><?= $estadisticas['total_cursos'] ?></div>
            <div class="estadistica-label">Total de Cursos</div>
        </div>
        <div class="estadistica-card completados">
            <div class="estadistica-numero"><?= $estadisticas['cursos_completados'] ?></div>
            <div class="estadistica-label">Completados</div>
        </div>
        <div class="estadistica-card en-progreso">
            <div class="estadistica-numero"><?= $estadisticas['cursos_en_progreso'] ?></div>
            <div class="estadistica-label">Sin Completar</div>
        </div>
        <div class="estadistica-card sin-iniciar">
            <div class="estadistica-numero"><?= $estadisticas['cursos_sin_iniciar'] ?></div>
            <div class="estadistica-label">Sin Iniciar</div>
        </div>
        <div class="estadistica-card promedio">
            <div class="estadistica-numero"><?= number_format($estadisticas['progreso_promedio'], 1) ?>%</div>
            <div class="estadistica-label">Progreso Promedio</div>
        </div>
    </div>

    <!-- Filtros y búsqueda -->
    <div class="filtros-container">
        <form method="GET" class="filtros-form">
            <div class="filtro-grupo">
                <label class="filtro-label">Buscar:</label>
                <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>" 
                       placeholder="Título, descripción o categoría..." class="filtro-input">
            </div>
            
            <div class="filtro-grupo">
                <label class="filtro-label">Estado:</label>
                <select name="estado" class="filtro-select">
                    <option value="">Todos los estados</option>
                    <option value="completado" <?= $estado_filtro === 'completado' ? 'selected' : '' ?>>Completados</option>
                    <option value="en_progreso" <?= $estado_filtro === 'en_progreso' ? 'selected' : '' ?>>En Progreso</option>
                    <option value="sin_iniciar" <?= $estado_filtro === 'sin_iniciar' ? 'selected' : '' ?>>Sin Iniciar</option>
                </select>
            </div>
            
            <div class="filtro-grupo">
                <label class="filtro-label">Ordenar por:</label>
                <select name="ordenar" class="filtro-select">
                    <option value="recientes" <?= $ordenar === 'recientes' ? 'selected' : '' ?>>Más recientes</option>
                    <option value="alfabetico" <?= $ordenar === 'alfabetico' ? 'selected' : '' ?>>Alfabético</option>
                    <option value="progreso_desc" <?= $ordenar === 'progreso_desc' ? 'selected' : '' ?>>Mayor progreso</option>
                    <option value="progreso_asc" <?= $ordenar === 'progreso_asc' ? 'selected' : '' ?>>Menor progreso</option>
                </select>
            </div>
            
            <button type="submit" class="filtro-boton">Aplicar Filtros</button>
            <a href="<?= BASE_URL ?>/estudiante/mis_cursos.php" class="filtro-reset">Limpiar</a>
        </form>
    </div>

    <!-- Resultados -->
    <div class="resultados-info">
        <p>Tienes <strong><?= count($cursos) ?></strong> curso<?= count($cursos) !== 1 ? 's' : '' ?> inscrito<?= count($cursos) !== 1 ? 's' : '' ?></p>
    </div>

    <!-- Listado de cursos -->
    <div class="cursos-grid">
        <?php if (empty($cursos)): ?>
            <div class="no-cursos">
                <img src="<?= BASE_URL ?>/styles/iconos/desk.png" class="no-cursos-icon">
                <h3>No tienes cursos inscritos</h3>
                <p>Explora nuestro catálogo y comienza tu aprendizaje</p>
                <a href="<?= BASE_URL ?>/estudiante/catalogo.php" class="btn-primario">Explorar Cursos</a>
            </div>
        <?php else: ?>
            <?php foreach ($cursos as $curso): ?>
                <div class="curso-card mis-cursos">
                    <div class="curso-header">
                        <div class="curso-categoria"><?= htmlspecialchars($curso['categoria'] ?: 'General') ?></div>
                        <div class="curso-estado <?= $curso['progreso'] == 100 ? 'completado' : ($curso['progreso'] > 0 ? 'en-progreso' : 'sin-iniciar') ?>">
                            <?php if ($curso['progreso'] == 100): ?>
                                ✓ Completado
                            <?php elseif ($curso['progreso'] > 0): ?>
                                📚 Sin Completar
                            <?php else: ?>
                                ⏳ Sin Iniciar
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h3 class="curso-titulo"><?= htmlspecialchars($curso['titulo']) ?></h3>
                    <p class="curso-descripcion"><?= htmlspecialchars(substr($curso['descripcion'], 0, 100)) ?>...</p>
                    
                    <?php if (!empty($curso['docente_nombre'])): ?>
                        <div class="curso-instructor">
                            <strong>Instructor:</strong> <?= htmlspecialchars($curso['docente_nombre']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Barra de progreso -->
                    <div class="progreso-container">
                        <div class="progreso-info">
                            <span class="progreso-texto">Progreso: <?= number_format($curso['progreso'], 1) ?>%</span>
                            <span class="progreso-detalles">
                                <?= $curso['modulos_completados'] ?>/<?= $curso['total_modulos'] ?> módulos
                            </span>
                        </div>
                        <div class="progreso-barra">
                            <div class="progreso-fill" style="width: <?= $curso['progreso'] ?>%"></div>
                        </div>
                    </div>
                    
                    <!-- Estadísticas del curso -->
                    <div class="curso-estadisticas">
                        <div class="estadistica-item">
                            <span class="estadistica-icono">📚</span>
                            <span class="estadistica-valor"><?= $curso['total_modulos'] ?> módulos</span>
                        </div>
                        <div class="estadistica-item">
                            <span class="estadistica-icono">📝</span>
                            <span class="estadistica-valor"><?= $curso['evaluaciones_completadas'] ?>/<?= $curso['total_evaluaciones'] ?> evaluaciones</span>
                        </div>
                        <div class="estadistica-item">
                            <span class="estadistica-icono">📅</span>
                            <span class="estadistica-valor">Inscrito: <?= date('d/m/Y', strtotime($curso['fecha_inscripcion'])) ?></span>
                        </div>
                    </div>
                    
                    <div class="curso-acciones">
                        <a href="<?= BASE_URL ?>/estudiante/curso_contenido.php?id=<?= $curso['id'] ?>" class="btn-continuar">
                            <?php if ($curso['progreso'] == 0): ?>
                                Comenzar Curso
                            <?php elseif ($curso['progreso'] == 100): ?>
                                Revisar Curso
                            <?php else: ?>
                                Continuar Curso
                            <?php endif; ?>
                        </a>
                        <?php if ($curso['progreso'] == 100): ?>
                            <a href="<?= BASE_URL ?>/estudiante/certificado.php?curso_id=<?= $curso['id'] ?>" class="btn-certificado">
                                Ver Certificado
                            </a>
                        <?php endif; ?>
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
        <a href="<?= BASE_URL ?>/estudiante/catalogo.php" class="enlace-nav">
            Explorar Más Cursos <i class="icono-nav">→</i>
        </a>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>