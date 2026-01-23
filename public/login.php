<?php
/*
 Página de inicio de sesión con estilos alineados y logo
 - Usa Bootstrap 5.3.3 y parciales compartidos
 - Aplica estilos dedicados desde styles/css/login.css
*/
session_start();

// Primero paths para definir correctamente BASE_URL en subcarpetas
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/database.php';

// Si no hay conexión, intentar configuración local
if (!isset($conn) || !$conn instanceof PDO) {
    require_once __DIR__ . '/../config/local_config.php';
}

$error = '';
$email_value = ''; // Variable para almacenar el valor del email de forma segura

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $email_value = $email; // Guardar para el formulario

    if ($email === '' || $password === '') {
        $error = 'Por favor, complete todos los campos';
    } elseif (!isset($conn) || !$conn instanceof PDO) {
        $error = 'Error de conexión a la base de datos. Por favor, contacte al administrador.';
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
            } elseif (strtolower(trim($row['estado'] ?? '')) !== 'activo') {
                $error = 'Tu cuenta no está activa. Contacta al administrador.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $row['id'];
                $_SESSION['nombre'] = $row['nombre'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['usuario'] = $row['usuario'];
                $role = strtolower(trim($row['role'] ?? ''));
                $_SESSION['role'] = $role;

                $upd = $conn->prepare("UPDATE usuarios SET last_login_at = NOW() WHERE id = :id");
                $upd->execute([':id' => $row['id']]);

                switch ($role) {
                    case 'master':
                        header('Location: ' . BASE_URL .'/public/master/dashboard.php');
                        break;
                    case 'docente':
                        header('Location: '  . BASE_URL . '/public/docente/dashboard.php');
                        break;
                    case 'ejecutivo':
                        header('Location: '  . BASE_URL . '/public/ejecutivo/dashboard.php');
                        break;
                    case 'estudiante':
                    default:
                        header('Location: ' . BASE_URL .'/public/estudiante/dashboard.php');
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
include __DIR__ . '/partials/header.php';
?>

<div class="login-wrapper">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-4">
        <div class="login-card">
          <div class="login-header">
            <img src="<?= BASE_URL ?>/styles/iconos/Logo_IMT.png" alt="Logo IMT" class="login-logo" />
            <h1 class="h4 login-title">Iniciar Sesión</h1>
          </div>
          <div class="login-body">
            <?php if ($error): ?>
              <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" novalidate>
              <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email_value) ?>" required>
              </div>
              <div class="mb-3">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" required>
              </div>
              <div class="login-actions">
                <button type="submit" class="btn btn-primary w-100">Iniciar Sesión</button>
              </div>
            </form>
          </div>
        </div>
        <div class="login-footer-space"></div>
      </div>
    </div>
  </div>
  
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
