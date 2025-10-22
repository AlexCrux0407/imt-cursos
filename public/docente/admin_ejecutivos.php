<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Gestión de Ejecutivos';

// Procesar acciones CRUD
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$message = '';
$error = '';

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'list';
    
    if ($action === 'create') {
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validaciones
        if (empty($nombre) || empty($email) || empty($password)) {
            $error = 'Todos los campos obligatorios deben ser completados.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'El email no tiene un formato válido.';
        } elseif (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } else {
            // Verificar si el email ya existe
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = :email");
            $stmt->execute([':email' => $email]);
            
            if ($stmt->fetch()) {
                $error = 'Ya existe un usuario con este email.';
            } else {
                // Crear nuevo ejecutivo
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    INSERT INTO usuarios (nombre, email, telefono, password, role, estado, created_at) 
                    VALUES (:nombre, :email, :telefono, :password, 'ejecutivo', 'activo', NOW())
                ");
                
                if ($stmt->execute([
                    ':nombre' => $nombre,
                    ':email' => $email,
                    ':telefono' => $telefono,
                    ':password' => $hashed_password
                ])) {
                    $message = 'Ejecutivo creado exitosamente.';
                    $action = 'list';
                } else {
                    $error = 'Error al crear el ejecutivo.';
                }
            }
        }
    } elseif ($action === 'update') {
        $id = $_POST['id'] ?? null;
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $password = $_POST['password'] ?? '';
        $estado = $_POST['estado'] ?? 'activo';
        
        // Validaciones
        if (empty($nombre) || empty($email) || empty($id)) {
            $error = 'Todos los campos obligatorios deben ser completados.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'El email no tiene un formato válido.';
        } else {
            // Verificar si el email ya existe (excluyendo el usuario actual)
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = :email AND id != :id");
            $stmt->execute([':email' => $email, ':id' => $id]);
            
            if ($stmt->fetch()) {
                $error = 'Ya existe otro usuario con este email.';
            } else {
                // Actualizar ejecutivo
                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        $error = 'La contraseña debe tener al menos 6 caracteres.';
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("
                            UPDATE usuarios 
                            SET nombre = :nombre, email = :email, telefono = :telefono, 
                                password = :password, estado = :estado, updated_at = NOW()
                            WHERE id = :id AND role = 'ejecutivo'
                        ");
                        
                        $result = $stmt->execute([
                            ':nombre' => $nombre,
                            ':email' => $email,
                            ':telefono' => $telefono,
                            ':password' => $hashed_password,
                            ':estado' => $estado,
                            ':id' => $id
                        ]);
                    }
                } else {
                    $stmt = $conn->prepare("
                        UPDATE usuarios 
                        SET nombre = :nombre, email = :email, telefono = :telefono, 
                            estado = :estado, updated_at = NOW()
                        WHERE id = :id AND role = 'ejecutivo'
                    ");
                    
                    $result = $stmt->execute([
                        ':nombre' => $nombre,
                        ':email' => $email,
                        ':telefono' => $telefono,
                        ':estado' => $estado,
                        ':id' => $id
                    ]);
                }
                
                if (isset($result) && $result) {
                    $message = 'Ejecutivo actualizado exitosamente.';
                    $action = 'list';
                } elseif (!isset($error) || empty($error)) {
                    $error = 'Error al actualizar el ejecutivo.';
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        
        if ($id) {
            // Cambiar estado a eliminado en lugar de borrar físicamente
            $stmt = $conn->prepare("
                UPDATE usuarios 
                SET estado = 'eliminado', updated_at = NOW() 
                WHERE id = :id AND role = 'ejecutivo'
            ");
            
            if ($stmt->execute([':id' => $id])) {
                $message = 'Ejecutivo eliminado exitosamente.';
            } else {
                $error = 'Error al eliminar el ejecutivo.';
            }
        }
        $action = 'list';
    }
}

// Obtener datos para edición
$ejecutivo_data = null;
if ($action === 'edit' && $id) {
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = :id AND role = 'ejecutivo' AND estado != 'eliminado'");
    $stmt->execute([':id' => $id]);
    $ejecutivo_data = $stmt->fetch();
    
    if (!$ejecutivo_data) {
        $error = 'Ejecutivo no encontrado.';
        $action = 'list';
    }
}

// Obtener lista de ejecutivos
if ($action === 'list') {
    $search = $_GET['search'] ?? '';
    $estado_filter = $_GET['estado'] ?? '';
    
    $where_conditions = ["role = 'ejecutivo'", "estado != 'eliminado'"];
    $params = [];
    
    if (!empty($search)) {
        $where_conditions[] = "(nombre LIKE :search OR email LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if (!empty($estado_filter)) {
        $where_conditions[] = "estado = :estado";
        $params[':estado'] = $estado_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $stmt = $conn->prepare("
        SELECT id, nombre, email, telefono, estado, created_at, updated_at
        FROM usuarios 
        WHERE $where_clause
        ORDER BY nombre ASC
    ");
    $stmt->execute($params);
    $ejecutivos = $stmt->fetchAll();
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/docente.css">

<div class="docente-dashboard">
    <div class="docente-header">
        <h1 class="docente-title">Gestión de Ejecutivos</h1>
        <p class="docente-subtitle">Administra los usuarios ejecutivos del sistema</p>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($action === 'list'): ?>
        <!-- Lista de Ejecutivos -->
        <div class="docente-actions">
            <a href="?action=create" class="btn-primary">
                <img src="<?= BASE_URL ?>/styles/iconos/addicon.png" alt="Crear">
                Crear Nuevo Ejecutivo
            </a>
        </div>

        <!-- Filtros -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <input type="hidden" name="action" value="list">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Buscar por nombre o email..." 
                           value="<?= htmlspecialchars($search ?? '') ?>" class="filter-input">
                </div>
                <div class="filter-group">
                    <select name="estado" class="filter-select">
                        <option value="">Todos los estados</option>
                        <option value="activo" <?= ($estado_filter ?? '') === 'activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="inactivo" <?= ($estado_filter ?? '') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>
                <button type="submit" class="btn-filter">Filtrar</button>
                <a href="?action=list" class="btn-clear">Limpiar</a>
            </form>
        </div>

        <!-- Tabla de Ejecutivos -->
        <div class="table-container">
            <?php if (empty($ejecutivos)): ?>
                <div class="no-results">
                    <p>No se encontraron ejecutivos.</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Estado</th>
                            <th>Fecha Creación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ejecutivos as $ejecutivo): ?>
                            <tr>
                                <td><?= htmlspecialchars($ejecutivo['nombre']) ?></td>
                                <td><?= htmlspecialchars($ejecutivo['email']) ?></td>
                                <td><?= htmlspecialchars($ejecutivo['telefono'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge status-<?= $ejecutivo['estado'] ?>">
                                        <?= ucfirst($ejecutivo['estado']) ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($ejecutivo['created_at'])) ?></td>
                                <td class="actions-cell">
                                    <a href="?action=edit&id=<?= $ejecutivo['id'] ?>" class="btn-edit" title="Editar">
                                        <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="Editar">
                                    </a>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('¿Estás seguro de que deseas eliminar este ejecutivo?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $ejecutivo['id'] ?>">
                                        <button type="submit" class="btn-delete" title="Eliminar">
                                            <img src="<?= BASE_URL ?>/styles/iconos/delete.png" alt="Eliminar">
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php elseif ($action === 'create'): ?>
        <!-- Formulario de Creación -->
        <div class="form-container">
            <div class="form-header">
                <h2>Crear Nuevo Ejecutivo</h2>
                <a href="?action=list" class="btn-secondary">Volver a la Lista</a>
            </div>
            
            <form method="POST" class="executive-form">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label for="nombre">Nombre Completo *</label>
                    <input type="text" id="nombre" name="nombre" required 
                           value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="telefono">Teléfono</label>
                    <input type="text" id="telefono" name="telefono" 
                           value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña *</label>
                    <input type="password" id="password" name="password" required minlength="6">
                    <small>Mínimo 6 caracteres</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Crear Ejecutivo</button>
                    <a href="?action=list" class="btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>

    <?php elseif ($action === 'edit' && $ejecutivo_data): ?>
        <!-- Formulario de Edición -->
        <div class="form-container">
            <div class="form-header">
                <h2>Editar Ejecutivo</h2>
                <a href="?action=list" class="btn-secondary">Volver a la Lista</a>
            </div>
            
            <form method="POST" class="executive-form">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= $ejecutivo_data['id'] ?>">
                
                <div class="form-group">
                    <label for="nombre">Nombre Completo *</label>
                    <input type="text" id="nombre" name="nombre" required 
                           value="<?= htmlspecialchars($_POST['nombre'] ?? $ejecutivo_data['nombre']) ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required 
                           value="<?= htmlspecialchars($_POST['email'] ?? $ejecutivo_data['email']) ?>">
                </div>
                
                <div class="form-group">
                    <label for="telefono">Teléfono</label>
                    <input type="text" id="telefono" name="telefono" 
                           value="<?= htmlspecialchars($_POST['telefono'] ?? $ejecutivo_data['telefono']) ?>">
                </div>
                
                <div class="form-group">
                    <label for="estado">Estado</label>
                    <select id="estado" name="estado">
                        <option value="activo" <?= ($ejecutivo_data['estado'] === 'activo') ? 'selected' : '' ?>>Activo</option>
                        <option value="inactivo" <?= ($ejecutivo_data['estado'] === 'inactivo') ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="password">Nueva Contraseña</label>
                    <input type="password" id="password" name="password" minlength="6">
                    <small>Dejar en blanco para mantener la contraseña actual</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Actualizar Ejecutivo</button>
                    <a href="?action=list" class="btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<style>
.alert {
    padding: 12px 16px;
    margin: 16px 0;
    border-radius: 4px;
    font-weight: 500;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.filters-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}

.filters-form {
    display: flex;
    gap: 15px;
    align-items: end;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    min-width: 200px;
}

.filter-input, .filter-select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.btn-filter, .btn-clear {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
}

.btn-filter {
    background-color: #3498db;
    color: white;
}

.btn-clear {
    background-color: #6c757d;
    color: white;
}

.table-container {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #dee2e6;
}

.data-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-activo {
    background-color: #d4edda;
    color: #155724;
}

.status-inactivo {
    background-color: #f8d7da;
    color: #721c24;
}

.actions-cell {
    white-space: nowrap;
}

.btn-edit, .btn-delete {
    padding: 6px;
    margin: 0 2px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    background: transparent;
}

.btn-edit:hover {
    background-color: #e3f2fd;
}

.btn-delete:hover {
    background-color: #ffebee;
}

.form-container {
    background: white;
    border-radius: 8px;
    padding: 24px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #dee2e6;
}

.executive-form {
    max-width: 600px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #495057;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
}

.form-group small {
    color: #6c757d;
    font-size: 12px;
    margin-top: 4px;
    display: block;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}

.no-results {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

@media (max-width: 768px) {
    .filters-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group {
        min-width: auto;
    }
    
    .form-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<?php require __DIR__ . '/../partials/footer.php'; ?>