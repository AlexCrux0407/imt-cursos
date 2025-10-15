<?php

abstract class Controller
{
    /**
     * Render a view with layout
     */
    protected function view($viewName, $data = [], $layout = 'layout') {
        // Extract data to make variables available in the view
        extract($data);
        
        // Capture the view content
        $viewPath = PUBLIC_PATH . "/views/{$viewName}.php";
        if (!file_exists($viewPath)) {
            throw new Exception("View not found: {$viewName}");
        }
        
        ob_start();
        include $viewPath;
        $content = ob_get_clean();
        
        // Include the layout
        $layoutPath = PUBLIC_PATH . "/views/{$layout}.php";
        if (file_exists($layoutPath)) {
            include $layoutPath;
        } else {
            echo $content; // Fallback to content only if no layout
        }
    }

    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    protected function requireAuth(): void
    {
        if (!is_logged_in()) {
            $this->redirect('/login');
        }
    }

    protected function requireRole(string $role): void
    {
        $this->requireAuth();
        
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
            $this->redirect('/login');
        }
    }

    protected function getParam(string $key, $default = null)
    {
        return $_GET[$key] ?? $_POST[$key] ?? $default;
    }

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