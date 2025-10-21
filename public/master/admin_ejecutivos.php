<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('master');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Master – Administración de Ejecutivos';

// Obtener filtros
$filtro_estado = $_GET['estado'] ?? '';
$filtro_busqueda = $_GET['busqueda'] ?? '';
$filtro_fecha = $_GET['fecha'] ?? '';

// Construir consulta base
$where_conditions = ["u.role = 'ejecutivo'"];
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

// Obtener ejecutivos
$sql = "SELECT u.*, 
               COUNT(DISTINCT c.id) as cursos_asignados,
               COUNT(DISTINCT CASE WHEN c.estado = 'activo' THEN c.id END) as cursos_activos,
               COUNT(DISTINCT i.usuario_id) as total_estudiantes
        FROM usuarios u
        LEFT JOIN cursos c ON u.id = c.asignado_a
        LEFT JOIN inscripciones i ON c.id = i.curso_id
        WHERE $where_clause
        GROUP BY u.id
        ORDER BY u.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$ejecutivos = $stmt->fetchAll();

// Obtener estadísticas
$stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) as activos,
                SUM(CASE WHEN estado = 'inactivo' THEN 1 ELSE 0 END) as inactivos
              FROM usuarios WHERE role = 'ejecutivo'";
$stats = $conn->query($stats_sql)->fetch();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/master.css">

<div class="contenido">
    <!-- Header Principal -->
    <div class="form-container-head" style="background: linear-gradient(135deg, #0066cc, #004d99); color: white; text-align: center;">
        <h1 style="margin: 0; font-size: 2rem; font-weight: 600;">Administración de Ejecutivos</h1>
        <p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 1.1rem;">Gestiona los usuarios ejecutivos del sistema</p>
    </div>

    <!-- Estadísticas -->
    <div class="form-container-body" style="margin-bottom: 25px;">
        <div class="div-fila" style="gap: 20px;">
            <div style="flex: 1; text-align: center; padding: 20px; background: linear-gradient(135deg, #3498db, #2980b9); color: white; border-radius: 12px;">
                <h3 style="margin: 0 0 10px 0; font-size: 2rem; font-weight: 700;"><?= $stats['total'] ?></h3>
                <p style="margin: 0; opacity: 0.9; font-size: 1rem;">Total Ejecutivos</p>
            </div>
            <div style="flex: 1; text-align: center; padding: 20px; background: linear-gradient(135deg, #27ae60, #229954); color: white; border-radius: 12px;">
                <h3 style="margin: 0 0 10px 0; font-size: 2rem; font-weight: 700;"><?= $stats['activos'] ?></h3>
                <p style="margin: 0; opacity: 0.9; font-size: 1rem;">Activos</p>
            </div>
            <div style="flex: 1; text-align: center; padding: 20px; background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; border-radius: 12px;">
                <h3 style="margin: 0 0 10px 0; font-size: 2rem; font-weight: 700;"><?= $stats['inactivos'] ?></h3>
                <p style="margin: 0; opacity: 0.9; font-size: 1rem;">Inactivos</p>
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
                <input type="text" name="busqueda" value="<?= htmlspecialchars($filtro_busqueda) ?>" 
                       placeholder="Nombre, email o usuario" 
                       style="width: 100%; padding: 10px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 1rem;">
            </div>
            <div style="flex: 1;">
                <label style="display: block; margin-bottom: 5px; color: #2c3e50; font-weight: 500;">Fecha de registro:</label>
                <input type="date" name="fecha" value="<?= htmlspecialchars($filtro_fecha) ?>"
                       style="width: 100%; padding: 10px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 1rem;">
            </div>
            <div>
                <button type="submit" style="padding: 10px 20px; background: linear-gradient(135deg, #0066cc, #004d99); color: white; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; margin-right: 10px;">
                    Filtrar
                </button>
                <a href="admin_ejecutivos.php" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 8px; font-size: 1rem;">
                    Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- Lista de Ejecutivos -->
    <div class="form-container-body">
        <div class="div-fila-alt" style="margin-bottom: 20px;">
            <h3 style="color: var(--master-primary); margin: 0; font-size: 1.3rem;">
                <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="" style="width: 20px; height: 20px; margin-right: 8px; vertical-align: middle;">
                Lista de Ejecutivos (<?= count($ejecutivos) ?>)
            </h3>
        </div>

        <?php if (empty($ejecutivos)): ?>
            <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                <img src="<?= BASE_URL ?>/styles/iconos/search.png" alt="" style="width: 48px; height: 48px; opacity: 0.5; margin-bottom: 15px;">
                <p style="font-size: 1.1rem; margin: 0;">No se encontraron ejecutivos con los filtros aplicados</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <thead style="background: #f8f9fa;">
                        <tr>
                            <th style="padding: 15px; text-align: left; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #e8ecef;">Ejecutivo</th>
                            <th style="padding: 15px; text-align: center; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #e8ecef;">Estado</th>
                            <th style="padding: 15px; text-align: center; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #e8ecef;">Cursos Asignados</th>
                            <th style="padding: 15px; text-align: center; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #e8ecef;">Estudiantes</th>
                            <th style="padding: 15px; text-align: center; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #e8ecef;">Fecha Registro</th>
                            <th style="padding: 15px; text-align: center; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #e8ecef;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ejecutivos as $ejecutivo): ?>
                            <tr style="border-bottom: 1px solid #e8ecef;" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor='white'">
                                <td style="padding: 15px; vertical-align: middle;">
                                    <div>
                                        <div style="color: #2c3e50; font-weight: 600; margin-bottom: 3px;">
                                            <?= htmlspecialchars($ejecutivo['nombre']) ?>
                                        </div>
                                        <div style="color: #7f8c8d; font-size: 0.9rem;">
                                            <?= htmlspecialchars($ejecutivo['email']) ?>
                                        </div>
                                        <div style="color: #95a5a6; font-size: 0.85rem;">
                                            Usuario: <?= htmlspecialchars($ejecutivo['usuario']) ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 15px; text-align: center; vertical-align: middle;">
                                    <span style="padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 500; 
                                                 background: <?= $ejecutivo['estado'] === 'activo' ? '#d4edda' : '#f8d7da' ?>; 
                                                 color: <?= $ejecutivo['estado'] === 'activo' ? '#155724' : '#721c24' ?>;">
                                        <?= ucfirst($ejecutivo['estado']) ?>
                                    </span>
                                </td>
                                <td style="padding: 15px; text-align: center; vertical-align: middle;">
                                    <div style="color: #2c3e50; font-weight: 600; margin-bottom: 3px;">
                                        <?= $ejecutivo['cursos_asignados'] ?>
                                    </div>
                                    <div style="color: #7f8c8d; font-size: 0.85rem;">
                                        <?= $ejecutivo['cursos_activos'] ?> activos
                                    </div>
                                </td>
                                <td style="padding: 15px; text-align: center; vertical-align: middle;">
                                    <span style="color: #2c3e50; font-weight: 600; font-size: 1.1rem;">
                                        <?= $ejecutivo['total_estudiantes'] ?>
                                    </span>
                                </td>
                                <td style="padding: 15px; text-align: center; vertical-align: middle;">
                                    <div style="color: #7f8c8d; font-size: 0.9rem;">
                                        <?= date('d/m/Y', strtotime($ejecutivo['created_at'])) ?>
                                    </div>
                                </td>
                                <td style="padding: 15px; text-align: center; vertical-align: middle;">
                                    <button onclick="toggleEjecutivo(<?= $ejecutivo['id'] ?>, '<?= $ejecutivo['estado'] ?>')" 
                                            style="padding: 8px 16px; border: none; border-radius: 6px; font-size: 0.9rem; cursor: pointer; font-weight: 500;
                                                   background: <?= $ejecutivo['estado'] === 'activo' ? 'linear-gradient(135deg, #e74c3c, #c0392b)' : 'linear-gradient(135deg, #27ae60, #229954)' ?>; 
                                                   color: white;">
                                        <?= $ejecutivo['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleEjecutivo(id, estadoActual) {
    const nuevoEstado = estadoActual === 'activo' ? 'inactivo' : 'activo';
    const accion = nuevoEstado === 'activo' ? 'activar' : 'desactivar';
    
    if (confirm(`¿Estás seguro de que quieres ${accion} este ejecutivo?`)) {
        fetch('toggle_ejecutivo.php', {
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

<?php include __DIR__ . '/../partials/footer.php'; ?>