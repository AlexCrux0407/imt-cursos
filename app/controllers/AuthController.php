<?php

require_once __DIR__ . '/../Controller.php';

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (is_logged_in()) {
            $this->redirectToDashboard();
            return;
        }

        $error = $_GET['error'] ?? '';
        $url = (defined('BASE_URL') ? BASE_URL : '') . '/login.php';
        if (!empty($error)) {
            $url .= '?error=' . urlencode($error);
        }
        $this->redirect($url);
    }

    public function login(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $this->redirect('/login.php?error=' . urlencode('Por favor, complete todos los campos'));
            return;
        }

        try {
            require_once __DIR__ . '/../../config/database.php';
            global $pdo;
            
            $stmt = $pdo->prepare("SELECT id, nombre, email, password, role, estado FROM usuarios WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $user['estado'] === 'activo' && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nombre'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                $this->redirectToDashboard();
            } else {
                $this->redirect('/login.php?error=' . urlencode('Credenciales incorrectas'));
            }
        } catch (Exception $e) {
            error_log("Error en login: " . $e->getMessage());
            $this->redirect('/login.php?error=' . urlencode('Error del sistema. Intente nuevamente.'));
        }
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        $this->redirect('/login.php?message=' . urlencode('Sesión cerrada correctamente'));
    }

    private function redirectToDashboard(): void
    {
        $role = $_SESSION['role'] ?? '';
        
        switch ($role) {
            case 'estudiante':
                $this->redirect('/estudiante/dashboard');
                break;
            case 'docente':
                $this->redirect('/docente/dashboard');
                break;
            case 'master':
                $this->redirect('/master/dashboard');
                break;
            case 'ejecutivo':
                $this->redirect('/ejecutivo/dashboard');
                break;
            default:
                $this->redirect('/login.php?error=' . urlencode('Rol no válido'));
        }
    }
}