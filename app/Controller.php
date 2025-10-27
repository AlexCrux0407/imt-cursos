<?php

/**
 * Controlador base: utilidades para vistas, JSON, redirecciones y validación.
 */
abstract class Controller
{
    /**
     * Renderiza una vista. Prioriza `public/<viewName>.php`; si no existe,
     * usa `public/views/<viewName>.php` con layout opcional.
     */
    protected function view($viewName, $data = [], $layout = 'layout') {
        extract($data);

        $publicPath = PUBLIC_PATH . "/{$viewName}.php";
        if (file_exists($publicPath)) {
            include $publicPath;
            return;
        }

        $viewPath = PUBLIC_PATH . "/views/{$viewName}.php";
        if (file_exists($viewPath)) {
            ob_start();
            include $viewPath;
            $content = ob_get_clean();

            $layoutPath = PUBLIC_PATH . "/views/{$layout}.php";
            if (file_exists($layoutPath)) {
                include $layoutPath;
            } else {
                echo $content;
            }
            return;
        }

        throw new Exception("View not found: {$viewName}");
    }

    /**
     * Responde JSON con código de estado.
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Redirige a una URL.
     */
    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    /**
     * Exige sesión iniciada; redirige al login si no.
     */
    protected function requireAuth(): void
    {
        if (!is_logged_in()) {
            $this->redirect('/login.php');
        }
    }

    /**
     * Exige rol específico; redirige si no coincide.
     */
    protected function requireRole(string $role): void
    {
        $this->requireAuth();

        if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
            $this->redirect('/login.php');
        }
    }

    /**
     * Obtiene parámetro de `$_GET` o `$_POST` con valor por defecto.
     */
    protected function getParam(string $key, $default = null)
    {
        return $_GET[$key] ?? $_POST[$key] ?? $default;
    }

    /**
     * Valida campos según reglas simples (required, email, min:N).
     */
    protected function validate(array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $this->getParam($field);

            if (strpos($rule, 'required') !== false && empty($value)) {
                $errors[$field] = "El campo {$field} es requerido";
            }

            if (strpos($rule, 'email') !== false && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "El campo {$field} debe ser un email válido";
            }

            if (preg_match('/min:(\d+)/', $rule, $matches) && strlen($value) < $matches[1]) {
                $errors[$field] = "El campo {$field} debe tener al menos {$matches[1]} caracteres";
            }
        }

        return $errors;
    }
}