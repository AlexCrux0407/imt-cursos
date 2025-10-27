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
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #3498db); color: white; text-align: center;">
        <h2 style="margin: 0; font-size: 1.8rem; font-weight: 600;">Administración de Docentes</h2>
        <p style="margin: 5px 0 0 0; opacity: 0.9;">Gestiona los docentes de la plataforma</p>
    </div>

    <!-- Estadísticas -->
    <div class="form-container-body" style="margin-bottom: 25px;">
        <div class="div-fila" style="gap: 20px;">
            <div style="flex: 1; text-align: center; padding: 20px; background: linear-gradient(135deg, #3498db, #3498db); color: white; border-radius: 12px;">
                <h3 style="margin: 0; font-size: 2rem;"><?= $stats['total_docentes'] ?></h3>
                <p style="margin: 5px 0 0 0; font-size: 0.9rem;">Total Docentes</p>
            </div>
            <div style="flex: 1; text-align: center; padding: 20px; background: linear-gradient(135deg, #28a745, #1e7e34); color: white; border-radius: 12px;">
                <h3 style="margin: 0; font-size: 2rem;"><?= $stats['docentes_activos'] ?></h3>
                <p style="margin: 5px 0 0 0; font-size: 0.9rem;">Activos</p>
            </div>
            <div style="flex: 1; text-align: center; padding: 20px; background: linear-gradient(135deg, #dc3545, #bd2130); color: white; border-radius: 12px;">
                <h3 style="margin: 0; font-size: 2rem;"><?= $stats['docentes_inactivos'] ?></h3>
                <p style="margin: 5px 0 0 0; font-size: 0.9rem;">Inactivos</p>
            </div>
            <div style="flex: 1; text-align: center; padding: 20px; background: linear-gradient(135deg, #ffc107, #e0a800); color: white; border-radius: 12px;">
                <h3 style="margin: 0; font-size: 2rem;"><?= $stats['nuevos_mes'] ?></h3>
                <p style="margin: 5px 0 0 0; font-size: 0.9rem;">Nuevos (30 días)</p>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="form-container-body" style="margin-bottom: 25px;">
        <h3 style="color: var(--master-primary); margin-bottom: 20px; font-size: 1.3rem;">
            <img src="<?= BASE_URL ?>/styles/iconos/tablefull.png" alt="" style="width: 20px; height: 20px; margin-right: 8px; vertical-align: middle;">
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

    <!-- Tabla de Docentes -->
    <div class="form-container-body" style="padding: 0; overflow: hidden;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-bottom: 2px solid #dee2e6;">
            <h3 style="color: var(--master-primary); margin: 0; font-size: 1.3rem; font-weight: 600;">
                <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="" style="width: 20px; height: 20px; margin-right: 8px; vertical-align: middle;">
                Lista de Docentes (<?= count($docentes) ?>)
            </h3>
            <button onclick="mostrarFormularioCrear()" 
                    style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 12px 24px; border: none; border-radius: 10px; font-size: 1rem; cursor: pointer; font-weight: 500; box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3); transition: all 0.3s ease;">
                <img src="<?= BASE_URL ?>/styles/iconos/addicon.png" alt="" style="width: 16px; height: 16px; margin-right: 8px; vertical-align: middle;">
                Crear Nuevo Docente
            </button>
        </div>
        
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <thead style="background: #f8f9fa;">
                    <tr>
                        <th style="padding: 15px; text-align: left; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #e8ecef;">Docente</th>
                        <th style="padding: 15px; text-align: center; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #e8ecef;">Email</th>
                        <th style="padding: 15px; text-align: center; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #e8ecef;">Estado</th>
                        <th style="padding: 15px; text-align: center; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #e8ecef;">Cursos</th>
                        <th style="padding: 15px; text-align: center; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #e8ecef;">Estudiantes</th>
                        <th style="padding: 15px; text-align: center; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #e8ecef;">Registro</th>
                        <th style="padding: 15px; text-align: center; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #e8ecef;">Última Asignación</th>
                        <th style="padding: 15px; text-align: center; color: #2c3e50; font-weight: 600; border-bottom: 2px solid #e8ecef;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($docentes as $docente): ?>
                        <tr style="border-bottom: 1px solid #e8ecef;" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor='white'">
                            <td style="padding: 15px; vertical-align: middle;">
                                <div style="color: #2c3e50; font-weight: 600; margin-bottom: 3px;">
                                    <?= htmlspecialchars($docente['nombre']) ?>
                                </div>
                                <div style="color: #95a5a6; font-size: 0.85rem;">
                                    Usuario: <?= htmlspecialchars($docente['usuario']) ?>
                                </div>
                            </td>
                            <td style="padding: 15px; text-align: center; vertical-align: middle;">
                                <div style="color: #7f8c8d; font-size: 0.9rem;">
                                    <?= htmlspecialchars($docente['email']) ?>
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
                                <div style="color: #7f8c8d; font-size: 0.9rem;">
                                    <?= date('d/m/Y', strtotime($docente['created_at'])) ?>
                                </div>
                            </td>
                            <td style="padding: 15px; text-align: center; vertical-align: middle;">
                                <div style="color: #7f8c8d; font-size: 0.9rem;">
                                    <?= $docente['ultima_asignacion'] ? date('d/m/Y', strtotime($docente['ultima_asignacion'])) : 'Sin asignaciones' ?>
                                </div>
                            </td>
                            <td style="padding: 15px; text-align: center; vertical-align: middle;">
                                <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                    <a href="<?= BASE_URL ?>/master/ver_docente.php?id=<?= $docente['id'] ?>"
                                       style="background: #6c757d; color: white; padding: 6px 12px; border-radius: 4px; font-size: 0.85rem; text-decoration: none; cursor: pointer;"
                                       title="Ver detalles">
                                        Ver
                                    </a>
                                    <a href="<?= BASE_URL ?>/master/editar_docente.php?id=<?= $docente['id'] ?>"
                                       style="background: #ffc107; color: white; padding: 6px 12px; border-radius: 4px; font-size: 0.85rem; text-decoration: none; cursor: pointer;"
                                       title="Editar perfil">
                                        Editar
                                    </a>
                                    <button onclick="asignarCurso(<?= $docente['id'] ?>, '<?= htmlspecialchars($docente['nombre']) ?>')"
                                            style="background: #3498db; color: white; padding: 6px 12px; border: none; border-radius: 4px; font-size: 0.85rem; cursor: pointer;"
                                            title="Asignar curso">
                                        Asignar
                                    </button>
                                    <button onclick="toggleEstadoDocente(<?= $docente['id'] ?>, '<?= $docente['estado'] ?>', '<?= htmlspecialchars($docente['nombre']) ?>')"
                                            style="background: <?= $docente['estado'] === 'activo' ? '#e74c3c' : '#27ae60' ?>; color: white; padding: 6px 12px; border: none; border-radius: 4px; font-size: 0.85rem; cursor: pointer;"
                                            title="<?= $docente['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?> docente">
                                        <?= $docente['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Modal para crear docente
function mostrarFormularioCrear() {
    const modal = document.createElement('div');
    modal.id = 'modalCrearDocente';
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(0,0,0,0.5); display: flex; justify-content: center; 
        align-items: center; z-index: 1000;
    `;
    
    modal.innerHTML = `
        <div style="background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h3 style="margin: 0; color: var(--master-primary); font-size: 1.4rem;">Crear Nuevo Docente</h3>
                <button onclick="cerrarModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #999;">&times;</button>
            </div>
            
            <form id="formCrearDocente" onsubmit="crearDocente(event)">
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
                    <button type="button" onclick="cerrarModal()" 
                            style="background: #6c757d; color: white; padding: 12px 24px; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer;">
                        Cancelar
                    </button>
                    <button type="submit" 
                            style="background: var(--master-primary); color: white; padding: 12px 24px; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer;">
                        Crear Docente
                    </button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function cerrarModal() {
    const modal = document.getElementById('modalCrearDocente');
    if (modal) {
        modal.remove();
    }
}

function crearDocente(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const data = {
        nombre: formData.get('nombre'),
        email: formData.get('email'),
        usuario: formData.get('usuario'),
        password: formData.get('password'),
        estado: formData.get('estado'),
        role: 'docente'
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
            alert('Docente creado exitosamente');
            cerrarModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al crear el docente');
    });
}

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