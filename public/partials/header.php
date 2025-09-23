<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../app/auth.php';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'IMT Cursos') ?></title>
    <link rel="stylesheet" href="/imt-cursos/public/styles/css/estiloformularios.css">
    <link rel="icon" href="/imt-cursos/public/styles/iconos/Logo_IMT.png" type="image/png">
</head>

<body class="bg-light">