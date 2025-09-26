<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Definir BASE_URL si no está definido
if (!defined('BASE_URL')) {
    define('BASE_URL', '/imt-cursos/public');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Por favor, complete todos los campos';
    } else {
        try {
            $stmt = $conn->prepare("
                SELECT id, nombre, email, usuario, password, role, estado
                FROM usuarios
                WHERE email = :email
                LIMIT 1
            ");
            $stmt->execute([':email' => $email]);
            $row = $stmt->fetch();

            if (!$row) {
                $error = 'Credenciales incorrectas';
            } elseif (!password_verify($password, $row['password'])) {
                $error = 'Credenciales incorrectas';
            } elseif ($row['estado'] !== 'activo') {
                $error = 'Tu cuenta no está activa. Contacta al administrador.';
            } else {
                // Login exitoso
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $row['id'];
                $_SESSION['nombre'] = $row['nombre'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['usuario'] = $row['usuario'];
                $_SESSION['role'] = $row['role'];

                // Actualizar last_login_at
                $upd = $conn->prepare("UPDATE usuarios SET last_login_at = NOW() WHERE id = :id");
                $upd->execute([':id' => $row['id']]);

                // Redireccionar según el rol
                switch ($row['role']) {
                    case 'master':
                        header('Location:' . BASE_URL .'/master/dashboard.php');
                        break;
                    case 'docente':
                        header('Location:'  . BASE_URL . '/docente/dashboard.php');
                        break;
                    case 'ejecutivo':
                        header('Location:'  . BASE_URL . '/ejecutivo/dashboard.php');
                        break;
                    case 'estudiante':
                    default:
                        header('Location:' . BASE_URL .'/estudiante/dashboard.php');
                        break;
                }
                exit;
            }
        } catch (Throwable $e) {
            $error = 'Ocurrió un error. Inténtalo más tarde.';

        }
    }
}

$page_title = 'Login - IMT Cursos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card mt-5">
                    <div class="card-header">
                        <h3 class="text-center">Iniciar Sesión</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Contraseña</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Iniciar Sesión</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
