<?php

class AuthMiddleware
{
    public function handle(): void
    {
        if (!is_logged_in()) {
            header('Location: /login');
            exit;
        }
    }
}

class RoleMiddleware
{
    private string $requiredRole;

    public function __construct(string $role = '')
    {
        $this->requiredRole = $role;
    }

    public function handle(): void
    {
        if (!is_logged_in()) {
            header('Location: /login');
            exit;
        }

        if ($this->requiredRole && (!isset($_SESSION['role']) || $_SESSION['role'] !== $this->requiredRole)) {
            header('Location: /login');
            exit;
        }
    }
}