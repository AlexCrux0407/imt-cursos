<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Estudiante – Catálogo de Cursos';

$estudiante_id = $_SESSION['user_id'];

// Filtros de búsqueda
$categoria_filtro = $_GET['categoria'] ?? '';
$buscar = trim($_GET['buscar'] ?? '');
$ordenar = $_GET['ordenar'] ?? 'recientes';

// Construir consulta con filtros
$where_conditions = ["c.estado = 'activo'"];
$params = [];

if (!empty($categoria_filtro)) {
    $where_conditions[] = "c.categoria = :categoria";
    $params[':categoria'] = $categoria_filtro;
}

if (!empty($buscar)) {
    $where_conditions[] = "(c.titulo LIKE :buscar OR c.descripcion LIKE :buscar OR c.dirigido_a LIKE :buscar)";
    $params[':buscar'] = "%$buscar%";
}

$where_clause = implode(' AND ', $where_conditions);

// Determinar ordenamiento
$order_clause = match($ordenar) {
    'alfabetico' => 'c.titulo ASC',
    'populares' => 'total_inscritos DESC',
    'recientes' => 'c.created_at DESC',
    default => 'c.created_at DESC'
};

// Obtener cursos disponibles
$stmt = $conn->prepare("
    SELECT c.*, 
           u.nombre as docente_nombre,
           COUNT(DISTINCT i.id) as total_inscritos,
           COUNT(DISTINCT m.id) as total_modulos,
           MAX(CASE WHEN ie.id IS NOT NULL THEN 1 ELSE 0 END) as ya_inscrito
    FROM cursos c
    INNER JOIN usuarios u ON c.creado_por = u.id
    LEFT JOIN inscripciones i ON c.id = i.curso_id
    LEFT JOIN inscripciones ie ON c.id = ie.curso_id AND ie.usuario_id = :estudiante_id
    LEFT JOIN modulos m ON c.id = m.curso_id
    WHERE $where_clause
    GROUP BY c.id, c.titulo, c.descripcion, c.objetivo_general, c.objetivos_especificos, 
             c.duracion, c.categoria, c.dirigido_a, c.estado, c.creado_por, c.created_at, c.updated_at,
             u.nombre
    ORDER BY $order_clause
");

$params[':estudiante_id'] = $estudiante_id;
$stmt->execute($params);
$cursos = $stmt->fetchAll();

// Obtener categorías disponibles
$stmt = $conn->prepare("
    SELECT DISTINCT categoria 
    FROM cursos 
    WHERE estado = 'activo' AND categoria IS NOT NULL AND categoria != ''
    ORDER BY categoria
");
$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_COLUMN);

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="/imt-cursos/public/styles/css/catalogo.css">

<div class="contenido">
    <div class="catalogo-header">
        <div class="header-content">
            <h1 class="catalogo-title">Catálogo de Cursos</h1>
            <p class="catalogo-subtitle">Descubre nuevas oportunidades de aprendizaje</p>
        </div>
    </div>

    <!-- Filtros y búsqueda -->
    <div class="filtros-container">
        <form method="GET" class="filtros-form">
            <div class="filtro-grupo">
                <label class="filtro-label">Buscar:</label>
                <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>" 
                       placeholder="Título, descripción o dirigido a..." class="filtro-input">
            </div>
            
            <div class="filtro-grupo">
                <label class="filtro-label">Categoría:</label>
                <select name="categoria" class="filtro-select">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categorias as $categoria): ?>
                        <option value="<?= htmlspecialchars($categoria) ?>" 
                                <?= $categoria_filtro === $categoria ? 'selected' : '' ?>>
                            <?= htmlspecialchars($categoria) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filtro-grupo">
                <label class="filtro-label">Ordenar:</label>
                <select name="ordenar" class="filtro-select">
                    <option value="recientes" <?= $ordenar === 'recientes' ? 'selected' : '' ?>>Más recientes</option>
                    <option value="alfabetico" <?= $ordenar === 'alfabetico' ? 'selected' : '' ?>>A-Z</option>
                    <option value="populares" <?= $ordenar === 'populares' ? 'selected' : '' ?>>Más populares</option>
                </select>
            </div>
            
            <div class="filtro-grupo">
                <button type="submit" class="btn-filtrar">Filtrar</button>
                <a href="/imt-cursos/public/estudiante/catalogo.php" class="btn-limpiar">Limpiar</a>
            </div>
        </form>
    </div>

    <!-- Resultados -->
    <div class="resultados-container">
        <div class="resultados-header">
            <h2 class="resultados-titulo">
                <?= count($cursos) ?> curso<?= count($cursos) !== 1 ? 's' : '' ?> encontrado<?= count($cursos) !== 1 ? 's' : '' ?>
            </h2>
        </div>

        <?php if (empty($cursos)): ?>
            <div class="sin-resultados">
                <img src="/imt-cursos/public/styles/iconos/desk.png" class="sin-resultados-icon">
                <h3>No se encontraron cursos</h3>
                <p>Intenta modificar tus filtros de búsqueda</p>
                <a href="/imt-cursos/public/estudiante/catalogo.php" class="btn-primary">Ver todos los cursos</a>
            </div>
        <?php else: ?>
            <div class="cursos-grid">
                <?php foreach ($cursos as $curso): ?>
                    <div class="curso-card">
                        <div class="curso-header">
                            <div class="curso-categoria">
                                <?= htmlspecialchars($curso['categoria'] ?: 'General') ?>
                            </div>
                            <?php if ($curso['ya_inscrito']): ?>
                                <div class="curso-badge inscrito">Inscrito</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="curso-body">
                            <h3 class="curso-titulo"><?= htmlspecialchars($curso['titulo']) ?></h3>
                            <p class="curso-docente">Por <?= htmlspecialchars($curso['docente_nombre']) ?></p>
                            
                            <div class="curso-descripcion">
                                <?= htmlspecialchars(substr($curso['descripcion'], 0, 120)) ?><?= strlen($curso['descripcion']) > 120 ? '...' : '' ?>
                            </div>
                            
                            <?php if (!empty($curso['objetivo_general'])): ?>
                                <div class="curso-objetivo">
                                    <strong>Objetivo:</strong> 
                                    <?= htmlspecialchars(substr($curso['objetivo_general'], 0, 100)) ?><?= strlen($curso['objetivo_general']) > 100 ? '...' : '' ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="curso-detalles">
                                <div class="detalle-item">
                                    <span class="detalle-label">📚</span>
                                    <span class="detalle-valor"><?= $curso['total_modulos'] ?> módulo<?= $curso['total_modulos'] !== 1 ? 's' : '' ?></span>
                                </div>
                                <div class="detalle-item">
                                    <span class="detalle-label">👥</span>
                                    <span class="detalle-valor"><?= $curso['total_inscritos'] ?> estudiante<?= $curso['total_inscritos'] !== 1 ? 's' : '' ?></span>
                                </div>
                                <?php if (!empty($curso['duracion'])): ?>
                                    <div class="detalle-item">
                                        <span class="detalle-label">⏱️</span>
                                        <span class="detalle-valor"><?= htmlspecialchars($curso['duracion']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($curso['dirigido_a'])): ?>
                                <div class="curso-dirigido">
                                    <strong>Dirigido a:</strong> <?= htmlspecialchars($curso['dirigido_a']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="curso-footer">
                            <?php if ($curso['ya_inscrito']): ?>
                                <a href="/imt-cursos/public/estudiante/curso_contenido.php?id=<?= $curso['id'] ?>" 
                                   class="btn-curso inscrito">
                                    Continuar Curso
                                </a>
                            <?php else: ?>
                                <button onclick="mostrarDetallesCurso(<?= $curso['id'] ?>)" 
                                        class="btn-curso ver-detalles">
                                    Ver Detalles
                                </button>
                                <button onclick="inscribirseCurso(<?= $curso['id'] ?>)" 
                                        class="btn-curso inscribirse">
                                    Inscribirse
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de detalles del curso -->
<div id="modalDetallesCurso" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-titulo" class="modal-title"></h2>
            <button onclick="cerrarModal()" class="modal-close">&times;</button>
        </div>
        <div id="modal-body" class="modal-body">
            <!-- Contenido cargado dinámicamente -->
        </div>
        <div class="modal-footer">
            <button onclick="cerrarModal()" class="btn-cancelar">Cerrar</button>
            <button id="btn-inscribirse-modal" onclick="inscribirseDesdModal()" class="btn-inscribirse">
                Inscribirse
            </button>
        </div>
    </div>
</div>

<script>
let cursoSeleccionado = null;

function mostrarDetallesCurso(cursoId) {
    fetch(`/imt-cursos/public/estudiante/detalle_curso.php?id=${cursoId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                cursoSeleccionado = cursoId;
                document.getElementById('modal-titulo').textContent = data.curso.titulo;
                document.getElementById('modal-body').innerHTML = data.html;
                document.getElementById('modalDetallesCurso').style.display = 'flex';
            } else {
                alert('Error al cargar los detalles del curso');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar los detalles del curso');
        });
}

function inscribirseCurso(cursoId) {
    if (confirm('¿Estás seguro de que deseas inscribirte en este curso?')) {
        window.location.href = `/imt-cursos/public/estudiante/inscribirse.php?curso_id=${cursoId}`;
    }
}

function inscribirseDesdModal() {
    if (cursoSeleccionado) {
        inscribirseCurso(cursoSeleccionado);
    }
}

function cerrarModal() {
    document.getElementById('modalDetallesCurso').style.display = 'none';
    cursoSeleccionado = null;
}

// Cerrar modal al hacer clic fuera
document.getElementById('modalDetallesCurso').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModal();
    }
});

// Auto-submit de filtros al cambiar
document.querySelectorAll('.filtro-select').forEach(select => {
    select.addEventListener('change', function() {
        this.form.submit();
    });
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
