<?php

abstract class Controller
{
    /**
     * Render a view with layout or fallback to public flat page
     */
    protected function view($viewName, $data = [], $layout = 'layout') {
        // Make variables available in the included file
        extract($data);
        
        // Preferred: flat page under public/<viewName>.php
        $publicPath = PUBLIC_PATH . "/{$viewName}.php";
        if (file_exists($publicPath)) {
            include $publicPath;
            return;
        }
        
        // Legacy MVC: public/views/<viewName>.php with optional layout
        $viewPath = PUBLIC_PATH . "/views/{$viewName}.php";
        if (file_exists($viewPath)) {
            ob_start();
            include $viewPath;
            $content = ob_get_clean();
            
            $layoutPath = PUBLIC_PATH . "/views/{$layout}.php";
            if (file_exists($layoutPath)) {
                include $layoutPath;
            } else {
                echo $content; // Fallback to content only if no layout
            }
            return;
        }
        
        throw new Exception("View not found: {$viewName}");
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
            $this->redirect('/login.php');
        }
    }

    protected function requireRole(string $role): void
    {
        $this->requireAuth();
        
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
            $this->redirect('/login.php');
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