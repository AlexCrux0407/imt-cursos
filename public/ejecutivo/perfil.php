<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('ejecutivo');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Ejecutivo – Perfil';

// Obtener información del usuario ejecutivo
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$usuario = $stmt->fetch();

// Procesar actualización del perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    $errors = [];
    
    // Validaciones
    if (empty($nombre)) {
        $errors[] = 'El nombre es obligatorio';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El email es obligatorio y debe ser válido';
    }
    
    // Verificar si el email ya existe (excepto el actual)
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = :email AND id != :id");
    $stmt->execute([':email' => $email, ':id' => $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        $errors[] = 'Este email ya está en uso por otro usuario';
    }
    
    if (empty($errors)) {
        try {
            if (!empty($password)) {
                // Actualizar con nueva contraseña
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("
                    UPDATE usuarios 
                    SET nombre = :nombre, email = :email, telefono = :telefono, password = :password 
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':nombre' => $nombre,
                    ':email' => $email,
                    ':telefono' => $telefono,
                    ':password' => $hashed_password,
                    ':id' => $_SESSION['user_id']
                ]);
            } else {
                // Actualizar sin cambiar contraseña
                $stmt = $conn->prepare("
                    UPDATE usuarios 
                    SET nombre = :nombre, email = :email, telefono = :telefono 
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':nombre' => $nombre,
                    ':email' => $email,
                    ':telefono' => $telefono,
                    ':id' => $_SESSION['user_id']
                ]);
            }
            
            // Actualizar sesión
            $_SESSION['nombre'] = $nombre;
            $_SESSION['email'] = $email;
            
            $success = 'Perfil actualizado correctamente';
            
            // Recargar datos del usuario
            $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = :id");
            $stmt->execute([':id' => $_SESSION['user_id']]);
            $usuario = $stmt->fetch();
            
        } catch (Exception $e) {
            $errors[] = 'Error al actualizar el perfil: ' . $e->getMessage();
        }
    }
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/ejecutivo.css">

<div class="exec-dashboard">
    <div class="exec-header">
        <h1 class="exec-title">Mi Perfil</h1>
        <p class="exec-subtitle">Gestiona tu información personal y configuración de cuenta</p>
    </div>

    <div class="profile-container">
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <img src="<?= BASE_URL ?>/styles/iconos/entrada.png" alt="Avatar" class="avatar-img">
                </div>
                <div class="profile-info">
                    <h3><?= htmlspecialchars($usuario['nombre']) ?></h3>
                    <p class="profile-role">Ejecutivo</p>
                    <p class="profile-email"><?= htmlspecialchars($usuario['email']) ?></p>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <img src="<?= BASE_URL ?>/styles/iconos/check.png" alt="Éxito" class="alert-icon">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <img src="<?= BASE_URL ?>/styles/iconos/error.png" alt="Error" class="alert-icon">
                    <ul class="error-list">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="profile-form">
                <div class="form-section">
                    <h4 class="section-title">Información Personal</h4>
                    
                    <div class="form-group">
                        <label for="nombre" class="form-label">Nombre Completo</label>
                        <input type="text" id="nombre" name="nombre" class="form-input" 
                               value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-input" 
                               value="<?= htmlspecialchars($usuario['email']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="tel" id="telefono" name="telefono" class="form-input" 
                               value="<?= htmlspecialchars($usuario['telefono'] ?? '') ?>" 
                               placeholder="Opcional">
                    </div>
                </div>

                <div class="form-section">
                    <h4 class="section-title">Cambiar Contraseña</h4>
                    <p class="section-description">Deja en blanco si no deseas cambiar la contraseña</p>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Nueva Contraseña</label>
                        <input type="password" id="password" name="password" class="form-input" 
                               placeholder="Dejar en blanco para mantener la actual">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <img src="<?= BASE_URL ?>/styles/iconos/check.png" alt="Guardar" class="btn-icon">
                        Actualizar Perfil
                    </button>
                    <a href="<?= BASE_URL ?>/ejecutivo/dashboard.php" class="btn-secondary">
                        <img src="<?= BASE_URL ?>/styles/iconos/back.png" alt="Cancelar" class="btn-icon">
                        Cancelar
                    </a>
                </div>
            </form>
        </div>

        <div class="profile-stats">
            <h4>Información de la Cuenta</h4>
            <div class="stat-item">
                <span class="stat-label">Rol:</span>
                <span class="stat-value">Ejecutivo</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Estado:</span>
                <span class="stat-value status-active">Activo</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Registro:</span>
                <span class="stat-value"><?= date('d/m/Y', strtotime($usuario['created_at'])) ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Última actualización:</span>
                <span class="stat-value"><?= date('d/m/Y H:i', strtotime($usuario['updated_at'] ?? $usuario['created_at'])) ?></span>
            </div>
        </div>
    </div>
</div>

<style>
.profile-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    margin-top: 30px;
}

.profile-card {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.profile-header {
    display: flex;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.profile-avatar {
    margin-right: 20px;
}

.avatar-img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #f8f9fa;
    padding: 15px;
}

.profile-info h3 {
    margin: 0 0 5px 0;
    color: #2c3e50;
    font-size: 1.5rem;
}

.profile-role {
    color: #3498db;
    font-weight: 600;
    margin: 0 0 5px 0;
}

.profile-email {
    color: #7f8c8d;
    margin: 0;
}

.form-section {
    margin-bottom: 30px;
}

.section-title {
    color: #2c3e50;
    margin-bottom: 15px;
    font-size: 1.2rem;
}

.section-description {
    color: #7f8c8d;
    font-size: 0.9rem;
    margin-bottom: 15px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    color: #2c3e50;
    font-weight: 500;
}

.form-input {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s;
}

.form-input:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.form-actions {
    display: flex;
    gap: 15px;
    margin-top: 30px;
}

.btn-primary, .btn-secondary {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
}

.btn-secondary {
    background: #95a5a6;
    color: white;
}

.btn-secondary:hover {
    background: #7f8c8d;
}

.btn-icon {
    width: 16px;
    height: 16px;
}

.profile-stats {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    height: fit-content;
}

.profile-stats h4 {
    margin-bottom: 20px;
    color: #2c3e50;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f8f9fa;
}

.stat-label {
    color: #7f8c8d;
    font-weight: 500;
}

.stat-value {
    color: #2c3e50;
    font-weight: 600;
}

.status-active {
    color: #27ae60;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.alert-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.alert-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.alert-icon {
    width: 20px;
    height: 20px;
    margin-top: 2px;
}

.error-list {
    margin: 0;
    padding-left: 20px;
}

@media (max-width: 768px) {
    .profile-container {
        grid-template-columns: 1fr;
    }
    
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-avatar {
        margin-right: 0;
        margin-bottom: 15px;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<?php require __DIR__ . '/../partials/footer.php'; ?>