<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('master');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Master – Administración de Docentes';

// Obtener filtros
$filtro_estado = $_GET['estado'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// Construir consulta con filtros
$where_conditions = ["u.role = 'docente'"];
$params = [];

if ($filtro_estado && $filtro_estado !== 'todos') {
    $where_conditions[] = "u.estado = :estado";
    $params[':estado'] = $filtro_estado;
}

if ($busqueda) {
    $where_conditions[] = "(u.nombre LIKE :busqueda OR u.email LIKE :busqueda OR u.usuario LIKE :busqueda)";
    $params[':busqueda'] = '%' . $busqueda . '%';
}

if ($fecha_desde) {
    $where_conditions[] = "DATE(u.created_at) >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
}

if ($fecha_hasta) {
    $where_conditions[] = "DATE(u.created_at) <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

$where_clause = implode(' AND ', $where_conditions);

// Obtener estadísticas de docentes
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_docentes,
        COUNT(CASE WHEN estado = 'activo' THEN 1 END) as docentes_activos,
        COUNT(CASE WHEN estado = 'inactivo' THEN 1 END) as docentes_inactivos,
        COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as nuevos_mes
    FROM usuarios 
    WHERE role = 'docente'
");
$stmt->execute();
$stats = $stmt->fetch();

// Obtener lista de docentes con información de cursos asignados
$stmt = $conn->prepare("
    SELECT u.*, 
           COUNT(DISTINCT c.id) as cursos_asignados,
           COUNT(DISTINCT CASE WHEN c.estado = 'activo' THEN c.id END) as cursos_activos,
           COUNT(DISTINCT i.usuario_id) as total_estudiantes,
           MAX(c.fecha_asignacion) as ultima_asignacion
    FROM usuarios u
    LEFT JOIN cursos c ON u.id = c.asignado_a
    LEFT JOIN inscripciones i ON c.id = i.curso_id
    WHERE $where_clause
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$stmt->execute($params);
$docentes = $stmt->fetchAll();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/master.css">

<div class="contenido">
    <!-- Header Principal -->
    <div class="form-container-head" style="background: linear-gradient(135deg, #0066cc, #004d99); color: white; text-align: center;">
        <h1 style="margin: 0; font-size: 2rem; font-weight: 600;">Administración de Docentes</h1>
        <p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 1.1rem;">Gestiona el personal docente del instituto</p>
    </div>

    <!-- Estadísticas -->
    <div class="form-container-body" style="margin-bottom: 25px;">
        <div class="div-fila" style="gap: 20px;">
            <div style="flex: 1; text-align: center; padding: 20px; background: linear-gradient(135deg, #3498db, #2980b9); color: white; border-radius: 12px;">
                <h3 style="margin: 0 0 10px 0; font-size: 2rem; font-weight: 700;"><?= $stats['total_docentes'] ?></h3>
                <p style="margin: 0; opacity: 0.9; font-size: 1rem;">Total Docentes</p>
            </div>
            <div style="flex: 1; text-align: center; padding: 20px; background: linear-gradient(135deg, #27ae60, #229954); color: white; border-radius: 12px;">
                <h3 style="margin: 0 0 10px 0; font-size: 2rem; font-weight: 700;"><?= $stats['docentes_activos'] ?></h3>
                <p style="margin: 0; opacity: 0.9; font-size: 1rem;">Activos</p>
            </div>
            <div style="flex: 1; text-align: center; padding: 20px; background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; border-radius: 12px;">
                <h3 style="margin: 0 0 10px 0; font-size: 2rem; font-weight: 700;"><?= $stats['docentes_inactivos'] ?></h3>
                <p style="margin: 0; opacity: 0.9; font-size: 1rem;">Inactivos</p>
            </div>
            <div style="flex: 1; text-align: center; padding: 20px; background: linear-gradient(135deg, #f39c12, #e67e22); color: white; border-radius: 12px;">
                <h3 style="margin: 0 0 10px 0; font-size: 2rem; font-weight: 700;"><?= $stats['nuevos_mes'] ?></h3>
                <p style="margin: 0; opacity: 0.9; font-size: 1rem;">Nuevos (30 días)</p>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="form-container-body" style="margin-bottom: 25px;">
        <h3 style="color: var(--master-primary); margin-bottom: 20px; font-size: 1.3rem;">
            <img src="<?= BASE_URL ?>/styles/iconos/search.png" alt="" style="width: 20px; height: 20px; margin-right: 8px; vertical-align: middle;">
            Filtros de Búsqueda
        </h3>
        
        <form method="GET" class="div-fila" style="gap: 15px; align-items: end;">
            <div style="flex: 1;">
                <label style="display: block; margin-bottom: 5px; color: #2c3e50; font-weight: 500;">Estado:</label>
                <select name="estado" style="width: 100%; padding: 10px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 1rem;">
                    <option value="">Todos los estados</option>
                    <option value="activo" <?= $filtro_estado === 'activo' ? 'selected' : '' ?>>Activos</option>
                    <option value="inactivo" <?= $filtro_estado === 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
                </select>
            </div>
            
            <div style="flex: 2;">
                <label style="display: block; margin-bottom: 5px; color: #2c3e50; font-weight: 500;">Buscar:</label>
                <input type="text" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>" 
                       placeholder="Nombre, email o usuario..." 
                       style="width: 100%; padding: 10px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 1rem;">
            </div>
            
            <div style="flex: 1;">
                <label style="display: block; margin-bottom: 5px; color: #2c3e50; font-weight: 500;">Desde:</label>
                <input type="date" name="fecha_desde" value="<?= htmlspecialchars($fecha_desde) ?>" 
                       style="width: 100%; padding: 10px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 1rem;">
            </div>
            
            <div style="flex: 1;">
                <label style="display: block; margin-bottom: 5px; color: #2c3e50; font-weight: 500;">Hasta:</label>
                <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fecha_hasta) ?>" 
                       style="width: 100%; padding: 10px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 1rem;">
            </div>
            
            <div>
                <button type="submit" 
                        style="background: var(--master-primary); color: white; padding: 10px 20px; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; font-weight: 500;">
                    Filtrar
                </button>
            </div>
            
            <div>
                <a href="<?= BASE_URL ?>/master/admin_docentes.php" 
                   style="background: #6c757d; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 1rem; font-weight: 500;">
                    Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- Lista de Docentes -->
    <div class="form-container-body">
        <div class="div-fila-alt" style="margin-bottom: 20px;">
            <h3 style="color: var(--master-primary); margin: 0; font-size: 1.3rem;">
                <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="" style="width: 20px; height: 20px; margin-right: 8px; vertical-align: middle;">
                Lista de Docentes (<?= count($docentes) ?>)
            </h3>
        </div>

        <?php if (empty($docentes)): ?>
            <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                <img src="<?= BASE_URL ?>/styles/iconos/search.png" alt="" style="width: 48px; height: 48px; opacity: 0.5; margin-bottom: 15px;">
                <p style="font-size: 1.1rem; margin: 0;">No se encontraron docentes con los filtros aplicados</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <thead style="background: #f8f9fa;">
                        <tr>
                            <th style="padding: 15px; text-align: left; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #e8ecef;">Docente</th>
                            <th style="padding: 15px; text-align: center; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #e8ecef;">Estado</th>
                            <th style="padding: 15px; text-align: center; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #e8ecef;">Cursos Asignados</th>
                            <th style="padding: 15px; text-align: center; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #e8ecef;">Estudiantes</th>
                            <th style="padding: 15px; text-align: center; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #e8ecef;">Fecha Registro</th>
                            <th style="padding: 15px; text-align: center; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #e8ecef;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($docentes as $docente): ?>
                            <tr style="border-bottom: 1px solid #e8ecef;" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor='white'">
                                <td style="padding: 15px; vertical-align: middle;">
                                    <div>
                                        <div style="color: #2c3e50; font-weight: 600; margin-bottom: 3px;">
                                            <?= htmlspecialchars($docente['nombre']) ?>
                                        </div>
                                        <div style="color: #7f8c8d; font-size: 0.9rem;">
                                            <?= htmlspecialchars($docente['email']) ?>
                                        </div>
                                        <div style="color: #95a5a6; font-size: 0.85rem;">
                                            Usuario: <?= htmlspecialchars($docente['usuario']) ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 15px; text-align: center; vertical-align: middle;">
                                    <span style="padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 500; 
                                                 background: <?= $docente['estado'] === 'activo' ? '#d4edda' : '#f8d7da' ?>; 
                                                 color: <?= $docente['estado'] === 'activo' ? '#155724' : '#721c24' ?>;">
                                        <?= ucfirst($docente['estado']) ?>
                                    </span>
                                </td>
                                <td style="padding: 15px; text-align: center; vertical-align: middle;">
                                    <div style="color: #2c3e50; font-weight: 600; margin-bottom: 3px;">
                                        <?= $docente['cursos_asignados'] ?>
                                    </div>
                                    <div style="color: #7f8c8d; font-size: 0.85rem;">
                                        <?= $docente['cursos_activos'] ?> activos
                                    </div>
                                </td>
                                <td style="padding: 15px; text-align: center; vertical-align: middle;">
                                    <span style="color: #2c3e50; font-weight: 600; font-size: 1.1rem;">
                                        <?= $docente['total_estudiantes'] ?>
                                    </span>
                                </td>
                                <td style="padding: 15px; text-align: center; vertical-align: middle;">
                                    <div style="color: #2c3e50; font-size: 0.9rem;">
                                        <?= date('d/m/Y', strtotime($docente['created_at'])) ?>
                                    </div>
                                    <?php if ($docente['ultima_asignacion']): ?>
                                        <div style="color: #7f8c8d; font-size: 0.8rem;">
                                            Últ. asignación: <?= date('d/m/Y', strtotime($docente['ultima_asignacion'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 15px; text-align: center; vertical-align: middle;">
                                    <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                        <button onclick="toggleEstadoDocente(<?= $docente['id'] ?>, '<?= $docente['estado'] ?>', '<?= htmlspecialchars($docente['nombre']) ?>')"
                                                style="background: <?= $docente['estado'] === 'activo' ? '#dc3545' : '#28a745' ?>; 
                                                       color: white; padding: 6px 12px; border: none; border-radius: 4px; 
                                                       font-size: 0.85rem; cursor: pointer;"
                                                title="<?= $docente['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?> docente">
                                            <?= $docente['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                        <a href="<?= BASE_URL ?>/master/asignar_cursos.php?docente_id=<?= $docente['id'] ?>"
                                           style="background: #007bff; color: white; padding: 6px 12px; border-radius: 4px; 
                                                  text-decoration: none; font-size: 0.85rem;"
                                           title="Asignar cursos">
                                            Asignar Cursos
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleEstadoDocente(docenteId, estadoActual, nombreDocente) {
    const nuevoEstado = estadoActual === 'activo' ? 'inactivo' : 'activo';
    const accion = nuevoEstado === 'activo' ? 'activar' : 'desactivar';
    
    if (confirm(`¿Estás seguro de que deseas ${accion} al docente "${nombreDocente}"?`)) {
        fetch('<?= BASE_URL ?>/master/toggle_docente.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: docenteId,
                estado: nuevoEstado
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
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