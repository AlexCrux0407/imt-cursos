<?php
// Parcial de encabezado: inicia sesión, carga paths/auth y cabecera HTML base
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../app/auth.php';
if (!function_exists('format_nombre')) {
    function format_nombre(string $full, string $orden = 'apellidos_nombres'): string
    {
        $clean = trim($full);
        return $clean;
    }
}
if (!function_exists('split_nombre_apellidos')) {
    function split_nombre_apellidos(string $full): array
    {
        $clean = trim(preg_replace('/\s+/', ' ', $full));
        if ($clean === '') {
            return ['', ''];
        }
        $parts = preg_split('/\s+/', $clean);
        $count = count($parts);
        if ($count === 1) {
            return [$parts[0], ''];
        }
        if ($count === 2) {
            return [$parts[0], $parts[1]];
        }
        $apellidos = implode(' ', array_slice($parts, -2));
        $nombres = implode(' ', array_slice($parts, 0, -2));
        return [$nombres, $apellidos];
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'IMT') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/estiloformularios.css">
    <!-- Tipografía global de encabezados de usuario -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/typography.css">
    <!-- Estilos específicos de la página de Login (scope .login-*) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/login.css">
    <link rel="icon" href="<?= BASE_URL ?>/styles/iconos/Logo_IMT.png" type="image/png">
    
</head>
 
<?php
  $role_class = '';
  if (isset($_SESSION['role'])) {
      $role = strtolower($_SESSION['role']);
      // role-estudiante, role-docente, role-ejecutivo, role-master
      $role_class = ' role-' . preg_replace('/[^a-z0-9_-]/', '', $role);
  }
?>
<body class="bg-light<?= htmlspecialchars($role_class, ENT_QUOTES) ?>">
