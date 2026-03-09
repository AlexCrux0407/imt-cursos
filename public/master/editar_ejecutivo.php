<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('master');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Master – Editar Ejecutivo';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . BASE_URL . '/master/admin_ejecutivos.php?error=id_invalido');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = :id AND role = 'ejecutivo' LIMIT 1");
$stmt->execute([':id' => $id]);
$usuario = $stmt->fetch();
if (!$usuario) {
    header('Location: ' . BASE_URL . '/master/admin_ejecutivos.php?error=ejecutivo_no_encontrado');
    exit;
}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombres = trim($_POST['nombres'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    if ($nombre === '') {
        $nombre = trim($nombres . ' ' . $apellidos);
    }
    $email = trim($_POST['email'] ?? '');
    // Removed telefono (no requerido)
    $estado = $_POST['estado'] ?? 'activo';
    $password = trim($_POST['password'] ?? '');

    $errors = [];
    if ($nombre === '') $errors[] = 'El nombre es obligatorio';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'El email es obligatorio y debe ser válido';
    if (!in_array($estado, ['activo','inactivo'], true)) $errors[] = 'Estado inválido';

    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = :email AND id != :id");
    $stmt->execute([':email' => $email, ':id' => $id]);
    if ($stmt->fetch()) $errors[] = 'Ya existe otro usuario con este email';

    if (!empty($password) && strlen($password) < 6) $errors[] = 'La contraseña debe tener al menos 6 caracteres';

    if (empty($errors)) {
        try {
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE usuarios SET nombre = :nombre, email = :email, estado = :estado, password = :password, updated_at = NOW() WHERE id = :id AND role = 'ejecutivo'");
                $stmt->execute([
                    ':nombre' => $nombre,
                    ':email' => $email,
                    ':estado' => $estado,
                    ':password' => $hashed,
                    ':id' => $id
                ]);
            } else {
                $stmt = $conn->prepare("UPDATE usuarios SET nombre = :nombre, email = :email, estado = :estado, updated_at = NOW() WHERE id = :id AND role = 'ejecutivo'");
                $stmt->execute([
                    ':nombre' => $nombre,
                    ':email' => $email,
                    ':estado' => $estado,
                    ':id' => $id
                ]);
            }
            header('Location: ' . BASE_URL . '/master/editar_ejecutivo.php?id=' . $id . '&success=perfil_actualizado');
            exit;
        } catch (Throwable $e) {
            header('Location: ' . BASE_URL . '/master/editar_ejecutivo.php?id=' . $id . '&error=database_error&detalle=' . urlencode($e->getMessage()));
            exit;
        }
    } else {
        header('Location: ' . BASE_URL . '/master/editar_ejecutivo.php?id=' . $id . '&error=validacion&detalle=' . urlencode(implode('\n', $errors)));
        exit;
    }
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/master.css">

<div class="contenido">
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #3498db); color: white; text-align: center;">
        <h1 style="margin: 0; font-size: 2rem; font-weight: 600;">Editar Ejecutivo</h1>
        <p style="margin: 5px 0 0 0; opacity: 0.9;">Actualiza la información del perfil del ejecutivo</p>
    </div>

    <div class="form-container-body" style="max-width: 800px; margin: 0 auto;">
        <?php if (!empty($success)): ?>
            <div style="background: #d4edda; color: #155724; padding: 12px 16px; border-radius: 8px; margin-bottom: 15px;">
                Perfil actualizado correctamente.
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 12px 16px; border-radius: 8px; margin-bottom: 15px;">
                Error: <?= htmlspecialchars($error) ?>
                <?php if (!empty($_GET['detalle'])): ?>
                    <br><small><?= nl2br(htmlspecialchars($_GET['detalle'])) ?></small>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php list($nombres_actual, $apellidos_actual) = split_nombre_apellidos($usuario['nombre'] ?? ''); ?>
        <form method="POST" class="div-fila" style="flex-direction: column; gap: 16px;">
            <div class="div-fila" style="gap: 16px;">
                <div style="flex: 1;">
                    <label style="display:block; margin-bottom:6px; color:#2c3e50; font-weight:500;">Nombres *</label>
                    <input type="text" name="nombres" value="<?= htmlspecialchars($nombres_actual) ?>" 
                           style="width: 100%; padding: 10px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 1rem;" required>
                </div>
                <div style="flex: 1;">
                    <label style="display:block; margin-bottom:6px; color:#2c3e50; font-weight:500;">Apellidos *</label>
                    <input type="text" name="apellidos" value="<?= htmlspecialchars($apellidos_actual) ?>" 
                           style="width: 100%; padding: 10px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 1rem;" required>
                </div>
            </div>
            <div class="div-fila" style="gap: 16px;">
                <div style="flex: 1;">
                    <label style="display:block; margin-bottom:6px; color:#2c3e50; font-weight:500;">Email *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" 
                           style="width: 100%; padding: 10px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 1rem;" required>
                </div>
                <!-- Teléfono eliminado por no ser necesario -->
            </div>
            <div class="div-fila" style="gap: 16px;">
                <div style="flex: 1;">
                    <label style="display:block; margin-bottom:6px; color:#2c3e50; font-weight:500;">Estado *</label>
                    <select name="estado" style="width: 100%; padding: 10px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 1rem;">
                        <option value="activo" <?= $usuario['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="inactivo" <?= $usuario['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label style="display:block; margin-bottom:6px; color:#2c3e50; font-weight:500;">Nueva Contraseña</label>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="password" id="passwordEditarEjecutivo" name="password" placeholder="Dejar en blanco para no cambiar" 
                               style="flex: 1; padding: 10px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 1rem;">
                        <button type="button" onclick="togglePasswordVisibility('passwordEditarEjecutivo', this)"
                                style="padding: 8px 12px; border: 1px solid #dfe6ee; border-radius: 10px; background: linear-gradient(180deg,#ffffff,#f2f5f9); color: #2c3e50; font-weight: 600; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.04); width: 36px; font-size: 1rem; line-height: 1;">
                            👁
                        </button>
                        <button type="button" onclick="generarPasswordTemporal('passwordEditarEjecutivo', this)"
                                style="padding: 8px 12px; border: 1px solid #dfe6ee; border-radius: 10px; background: linear-gradient(180deg,#eaf4ff,#f6f9ff); color: #2c3e50; font-weight: 600; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                            Generar
                        </button>
                    </div>
                    <div style="margin-top: 6px; color: #7f8c8d; font-size: 0.9rem;">Al guardar se reemplaza la contraseña del usuario.</div>
                </div>
            </div>

            <div class="div-fila" style="gap: 12px; justify-content: flex-end;">
                <a href="<?= BASE_URL ?>/master/admin_ejecutivos.php" 
                   style="background:#6c757d; color:white; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:500;">
                    Cancelar
                </a>
                <button type="submit" 
                        style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 10px 20px; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; font-weight: 600;">
                    Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePasswordVisibility(inputId, button) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
}

function generarPasswordTemporal(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const caracteres = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let password = '';
    for (let i = 0; i < 8; i++) {
        password += caracteres.charAt(Math.floor(Math.random() * caracteres.length));
    }
    input.value = password;
}
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
