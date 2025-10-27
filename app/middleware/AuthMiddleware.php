<?php

/**
 * Middleware de autenticación: exige sesión iniciada.
 */
class AuthMiddleware
{
    /**
     * Verifica sesión; redirige al login si no existe.
     */
    public function handle(): void
    {
        if (!is_logged_in()) {
            header('Location: /login.php');
            exit;
        }
    }
}

/**
 * Middleware de rol: exige rol específico en sesión.
 */
class RoleMiddleware
{
    private string $requiredRole;

    /**
     * Inicializa con rol requerido opcional.
     */
    public function __construct(string $role = '')
    {
        $this->requiredRole = $role;
    }

    /**
     * Verifica autenticación y rol; redirige si no cumple.
     */
    public function handle(): void
    {
        if (!is_logged_in()) {
            header('Location: /login.php');
            exit;
        }

        if ($this->requiredRole && (!isset($_SESSION['role']) || $_SESSION['role'] !== $this->requiredRole)) {
            header('Location: /login.php');
            exit;
        }
    }
}