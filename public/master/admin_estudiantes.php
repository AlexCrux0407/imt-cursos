<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('master');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Master – Administración de Estudiantes';

// Obtener filtros
$filtro_estado = $_GET['estado'] ?? '';
$filtro_busqueda = $_GET['busqueda'] ?? '';
$filtro_fecha = $_GET['fecha'] ?? '';

// Construir consulta base
$where_conditions = ["u.role = 'estudiante'"];
$params = [];

if ($filtro_estado) {
    $where_conditions[] = "u.estado = :estado";
    $params[':estado'] = $filtro_estado;
}

if ($filtro_busqueda) {
    $where_conditions[] = "(u.nombre LIKE :busqueda OR u.email LIKE :busqueda OR u.usuario LIKE :busqueda)";
    $params[':busqueda'] = '%' . $filtro_busqueda . '%';
}

if ($filtro_fecha) {
    $where_conditions[] = "DATE(u.created_at) = :fecha";
    $params[':fecha'] = $filtro_fecha;
}

$where_clause = implode(' AND ', $where_conditions);

// Obtener estudiantes con estadísticas
$stmt = $conn->prepare("
    SELECT 
        u.id,
        u.nombre,
        u.email,
        u.usuario,
        u.estado,
        u.created_at,
        COUNT(DISTINCT i.curso_id) as cursos_inscritos,
        COUNT(DISTINCT CASE WHEN i.estado = 'completado' THEN i.curso_id END) as cursos_completados,
        AVG(COALESCE(i.progreso, 0)) as progreso_promedio,
        MAX(i.fecha_inscripcion) as ultima_actividad
    FROM usuarios u
    LEFT JOIN inscripciones i ON u.id = i.usuario_id
    WHERE $where_clause
    GROUP BY u.id, u.nombre, u.email, u.usuario, u.estado, u.created_at
    ORDER BY u.created_at DESC
");
$stmt->execute($params);
$estudiantes = $stmt->fetchAll();

// Obtener estadísticas generales
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_estudiantes,
        COUNT(CASE WHEN estado = 'activo' THEN 1 END) as estudiantes_activos,
        COUNT(CASE WHEN estado = 'inactivo' THEN 1 END) as estudiantes_inactivos,
        COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as nuevos_mes
    FROM usuarios 
    WHERE role = 'estudiante'
");
$stmt->execute();
$estadisticas = $stmt->fetch();

// Obtener estudiantes más activos
$stmt = $conn->prepare("
    SELECT 
        u.nombre,
        u.email,
        COUNT(DISTINCT i.curso_id) as cursos_inscritos,
        COUNT(DISTINCT CASE WHEN i.estado = 'completado' THEN i.curso_id END) as cursos_completados,
        AVG(COALESCE(i.progreso, 0)) as progreso_promedio
    FROM usuarios u
    INNER JOIN inscripciones i ON u.id = i.usuario_id
    WHERE u.role = 'estudiante'
    GROUP BY u.id, u.nombre, u.email
    HAVING cursos_inscritos > 0
    ORDER BY cursos_completados DESC, progreso_promedio DESC
    LIMIT 10
");
$stmt->execute();
$estudiantes_activos = $stmt->fetchAll();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/master.css">

<style>
.students-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-value {
    font-size: 2.5rem;
    font-weight: bold;
    color: var(--master-primary);
    margin-bottom: 5px;
}

.stat-label {
    color: #7f8c8d;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filters-section {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    margin-bottom: 5px;
    font-weight: 500;
    color: #2c3e50;
}

.filter-group input,
.filter-group select {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 0.9rem;
}

.students-table {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.students-table table {
    width: 100%;
    border-collapse: collapse;
}

.students-table th {
    background: var(--master-primary);
    color: white;
    padding: 15px;
    text-align: left;
    font-weight: 500;
}

.students-table td {
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.students-table tr:hover {
    background: #f8f9fa;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-activo {
    background: #d4edda;
    color: #155724;
}

.status-inactivo {
    background: #f8d7da;
    color: #721c24;
}

.progress-bar {
    width: 100px;
    height: 8px;
    background: #eee;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: var(--master-primary);
    transition: width 0.3s ease;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

.btn-action {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    font-size: 0.8rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-view {
    background: #007bff;
    color: white;
}

.btn-edit {
    background: #f39c12;
    color: white;
}

.btn-toggle {
    background: #e74c3c;
    color: white;
}

.top-students {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.top-students h3 {
    color: var(--master-primary);
    margin-bottom: 15px;
}

.student-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}

.student-info {
    flex: 1;
}

.student-stats {
    display: flex;
    gap: 15px;
    font-size: 0.9rem;
    color: #7f8c8d;
}
</style>

<div class="students-container">
    <!-- Header -->
    <div class="form-container-head" style="background: linear-gradient(#3498db, #3498db); color: white;">
        <div class="div-fila-alt-start">
            <div>
                <h1 style="font-size: 2rem; margin-bottom: 10px;">Administración de estudiantes</h1>
                <p style="opacity: 0.9;">Gestión completa de estudiantes registrados en la plataforma</p>
            </div>
            <a href="<?= BASE_URL ?>/master/dashboard.php" class="btn" 
               style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white; padding: 12px 20px; border-radius: 8px; text-decoration: none;">
                ← Dashboard
            </a>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= $estadisticas['total_estudiantes'] ?: 0 ?></div>
            <div class="stat-label">Total Estudiantes</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $estadisticas['estudiantes_activos'] ?: 0 ?></div>
            <div class="stat-label">Estudiantes Activos</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $estadisticas['estudiantes_inactivos'] ?: 0 ?></div>
            <div class="stat-label">Estudiantes Inactivos</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $estadisticas['nuevos_mes'] ?: 0 ?></div>
            <div class="stat-label">Nuevos este Mes</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-section">
        <h3 style="color: var(--master-primary); margin-bottom: 15px;">Filtros de Búsqueda</h3>
        <form method="GET" class="filters-grid">
            <div class="filter-group">
                <label>Buscar por nombre/email:</label>
                <input type="text" name="busqueda" value="<?= htmlspecialchars($filtro_busqueda) ?>" placeholder="Nombre, email o usuario...">
            </div>
            <div class="filter-group">
                <label>Estado:</label>
                <select name="estado">
                    <option value="">Todos los estados</option>
                    <option value="activo" <?= $filtro_estado === 'activo' ? 'selected' : '' ?>>Activo</option>
                    <option value="inactivo" <?= $filtro_estado === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Fecha de registro:</label>
                <input type="date" name="fecha" value="<?= htmlspecialchars($filtro_fecha) ?>">
            </div>
            <div class="filter-group">
                <button type="submit" class="btn" style="background: var(--master-primary); color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer;">
                    Filtrar
                </button>
            </div>
        </form>
    </div>

    <!-- Tabla de Estudiantes -->
    <div class="students-table">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">
            <h3 style="color: var(--master-primary); margin: 0; font-size: 1.3rem;">
                <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="" style="width: 20px; height: 20px; margin-right: 8px; vertical-align: middle;">
                Lista de Estudiantes (<?= count($estudiantes) ?>)
            </h3>
            <button onclick="mostrarFormularioCrearEstudiante()" 
                    style="background: var(--master-primary); color: white; padding: 10px 20px; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; font-weight: 500;">
                <img src="<?= BASE_URL ?>/styles/iconos/addicon.png" alt="" style="width: 16px; height: 16px; margin-right: 8px; vertical-align: middle;">
                Crear Nuevo Estudiante
            </button>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Estudiante</th>
                    <th>Email</th>
                    <th>Estado</th>
                    <th>Cursos</th>
                    <th>Progreso</th>
                    <th>Registro</th>
                    <th>Última Actividad</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($estudiantes)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #7f8c8d;">
                            No se encontraron estudiantes con los filtros aplicados
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($estudiantes as $estudiante): ?>
                        <tr>
                            <td>
                                <div>
                                    <strong><?= htmlspecialchars($estudiante['nombre']) ?></strong><br>
                                    <small style="color: #7f8c8d;">@<?= htmlspecialchars($estudiante['usuario']) ?></small>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($estudiante['email']) ?></td>
                            <td>
                                <span class="status-badge status-<?= $estudiante['estado'] ?>">
                                    <?= ucfirst($estudiante['estado']) ?>
                                </span>
                            </td>
                            <td>
                                <div>
                                    <strong><?= $estudiante['cursos_inscritos'] ?></strong> inscritos<br>
                                    <small style="color: #27ae60;"><?= $estudiante['cursos_completados'] ?> completados</small>
                                </div>
                            </td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= number_format($estudiante['progreso_promedio'] ?: 0, 1) ?>%"></div>
                                </div>
                                <small><?= number_format($estudiante['progreso_promedio'] ?: 0, 1) ?>%</small>
                            </td>
                            <td><?= date('d/m/Y', strtotime($estudiante['created_at'])) ?></td>
                            <td>
                                <?php if ($estudiante['ultima_actividad']): ?>
                                    <?= date('d/m/Y', strtotime($estudiante['ultima_actividad'])) ?>
                                <?php else: ?>
                                    <span style="color: #7f8c8d;">Sin actividad</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="<?= BASE_URL ?>/master/ver_estudiante.php?id=<?= $estudiante['id'] ?>" class="btn-action btn-view" title="Ver detalles">
                                        👁️
                                    </a>
                                    <a href="<?= BASE_URL ?>/master/editar_estudiante.php?id=<?= $estudiante['id'] ?>" class="btn-action btn-edit" title="Editar">
                                        ✏️
                                    </a>
                                    <button onclick="toggleEstudiante(<?= $estudiante['id'] ?>, '<?= $estudiante['estado'] ?>')" 
                                            class="btn-action btn-toggle" title="Cambiar estado">
                                        <?= $estudiante['estado'] === 'activo' ? '🔒' : '🔓' ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Estudiantes Más Activos -->
    <?php if (!empty($estudiantes_activos)): ?>
    <div class="top-students">
        <h3>Estudiantes Más Activos</h3>
        <p style="color: #7f8c8d; margin-bottom: 20px;">Los 10 estudiantes con mejor rendimiento académico</p>
        
        <?php foreach ($estudiantes_activos as $index => $estudiante): ?>
            <div class="student-item">
                <div class="student-info">
                    <strong><?= htmlspecialchars($estudiante['nombre']) ?></strong><br>
                    <small style="color: #7f8c8d;"><?= htmlspecialchars($estudiante['email']) ?></small>
                </div>
                <div class="student-stats">
                    <span><strong><?= $estudiante['cursos_inscritos'] ?></strong> cursos</span>
                    <span><strong><?= $estudiante['cursos_completados'] ?></strong> completados</span>
                    <span><strong><?= number_format($estudiante['progreso_promedio'], 1) ?>%</strong> progreso</span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// Modal para crear estudiante
function mostrarFormularioCrearEstudiante() {
    const modal = document.createElement('div');
    modal.id = 'modalCrearEstudiante';
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(0,0,0,0.5); display: flex; justify-content: center; 
        align-items: center; z-index: 1000;
    `;
    
    modal.innerHTML = `
        <div style="background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h3 style="margin: 0; color: var(--master-primary); font-size: 1.4rem;">Crear Nuevo Estudiante</h3>
                <button onclick="cerrarModalEstudiante()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #999;">&times;</button>
            </div>
            
            <form id="formCrearEstudiante" onsubmit="crearEstudiante(event)">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; color: #2c3e50; font-weight: 500;">Nombre Completo *</label>
                    <input type="text" name="nombre" required 
                           style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 1rem;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; color: #2c3e50; font-weight: 500;">Email *</label>
                    <input type="email" name="email" required 
                           style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 1rem;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; color: #2c3e50; font-weight: 500;">Usuario *</label>
                    <input type="text" name="usuario" required 
                           style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 1rem;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; color: #2c3e50; font-weight: 500;">Contraseña *</label>
                    <input type="password" name="password" required 
                           style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 1rem;">
                </div>
                
                <div style="margin-bottom: 25px;">
                    <label style="display: block; margin-bottom: 5px; color: #2c3e50; font-weight: 500;">Estado</label>
                    <select name="estado" style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 1rem;">
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end;">
                    <button type="button" onclick="cerrarModalEstudiante()" 
                            style="background: #6c757d; color: white; padding: 12px 24px; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer;">
                        Cancelar
                    </button>
                    <button type="submit" 
                            style="background: var(--master-primary); color: white; padding: 12px 24px; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer;">
                        Crear Estudiante
                    </button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function cerrarModalEstudiante() {
    const modal = document.getElementById('modalCrearEstudiante');
    if (modal) {
        modal.remove();
    }
}

function crearEstudiante(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const data = {
        nombre: formData.get('nombre'),
        email: formData.get('email'),
        usuario: formData.get('usuario'),
        password: formData.get('password'),
        estado: formData.get('estado'),
        role: 'estudiante'
    };
    
    fetch('<?= BASE_URL ?>/master/crear_usuario.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Estudiante creado exitosamente');
            cerrarModalEstudiante();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al crear el estudiante');
    });
}

function toggleEstudiante(id, estadoActual) {
    const nuevoEstado = estadoActual === 'activo' ? 'inactivo' : 'activo';
    const accion = nuevoEstado === 'activo' ? 'activar' : 'desactivar';
    
    if (confirm(`¿Estás seguro de que deseas ${accion} este estudiante?`)) {
        // Aquí iría la llamada AJAX para cambiar el estado
        fetch('<?= BASE_URL ?>/master/toggle_estudiante.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: id,
                estado: nuevoEstado
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error al cambiar el estado del estudiante');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al procesar la solicitud');
        });
    }
}
</script>
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al procesar la solicitud');
        });
    }
}
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>