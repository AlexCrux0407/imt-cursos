<?php
$role = $_SESSION['role'] ?? null;
$nombre = $_SESSION['nombre'] ?? 'Usuario';
?>
<header class="responsive-header">
    <div class="header-icon left-icon">
        <a href="/imt-cursos/public/">
            <img src="/imt-cursos/public/styles/iconos/Logo_IMT.png" alt="IMT Logo">
        </a>
    </div>
    
    <h1 class="header-title">IMT Cursos</h1>
    
    <div class="user-title" style="font-size: 0.9rem; color: #5a5c69;">
        <img src="/imt-cursos/public/styles/iconos/entrada.png" alt="Usuario" style="width: 16px; height: 16px; margin-right: 5px; vertical-align: middle;">
        <?= htmlspecialchars($nombre) ?>
    </div>
    
    <div class="space-title"></div>
    
    <div class="header-icon right-icon">
        <a href="/imt-cursos/public/logout.php" style="color: #5a5c69; text-decoration: none;" title="Cerrar sesión">
            <img src="/imt-cursos/public/styles/iconos/logout.png" alt="Salir" style="width: 24px; height: 24px;">
        </a>
    </div>
</header>

<nav style="margin-top: 80px; background: #ffffff; border-bottom: 1px solid #ddd; padding: 10px 0;">
    <div class="contenido" style="padding: 10px 50px; margin-top: 0;">
        <div class="div-fila-alt-start" style="flex-wrap: wrap;">
            <?php if ($role === 'estudiante'): ?>
                <a href="/imt-cursos/public/estudiante/dashboard.php" class="nav-link-custom">
                    <img src="/imt-cursos/public/styles/iconos/home.png" alt="" class="nav-icon">
                    Dashboard
                </a>
                <a href="/imt-cursos/public/estudiante/cursos_disponibles.php" class="nav-link-custom">
                    <img src="/imt-cursos/public/styles/iconos/desk.png" alt="" class="nav-icon">
                    Cursos disponibles
                </a>
                <a href="/imt-cursos/public/estudiante/cursos_completados.php" class="nav-link-custom">
                    <img src="/imt-cursos/public/styles/iconos/detalles.png" alt="" class="nav-icon">
                    Cursos completados
                </a>
                <a href="/imt-cursos/public/estudiante/perfil.php" class="nav-link-custom">
                    <img src="/imt-cursos/public/styles/iconos/entrada.png" alt="" class="nav-icon">
                    Perfil
                </a>
            <?php elseif ($role === 'docente'): ?>
                <a href="/imt-cursos/public/docente/dashboard.php" class="nav-link-custom">
                    <img src="/imt-cursos/public/styles/iconos/home.png" alt="" class="nav-icon">
                    Dashboard
                </a>
                <a href="/imt-cursos/public/docente/admin_cursos.php" class="nav-link-custom">
                    <img src="/imt-cursos/public/styles/iconos/config.png" alt="" class="nav-icon">
                    Administración de cursos
                </a>
                <a href="/imt-cursos/public/docente/visualizar_curso.php" class="nav-link-custom">
                    <img src="/imt-cursos/public/styles/iconos/detalles.png" alt="" class="nav-icon">
                    Visualización de curso
                </a>
            <?php elseif ($role === 'ejecutivo'): ?>
                <a href="/imt-cursos/public/ejecutivo/dashboard.php" class="nav-link-custom">
                    <img src="/imt-cursos/public/styles/iconos/home.png" alt="" class="nav-icon">
                    Dashboard
                </a>
                <a href="/imt-cursos/public/ejecutivo/reportes_estudiantes.php" class="nav-link-custom">
                    <img src="/imt-cursos/public/styles/iconos/addicon.png" alt="" class="nav-icon">
                    Reportes Estudiantes
                </a>
                <a href="/imt-cursos/public/ejecutivo/reportes_docentes.php" class="nav-link-custom">
                    <img src="/imt-cursos/public/styles/iconos/edit.png" alt="" class="nav-icon">
                    Reportes Docentes
                </a>
                <a href="/imt-cursos/public/ejecutivo/reportes_cursos.php" class="nav-link-custom">
                    <img src="/imt-cursos/public/styles/iconos/desk.png" alt="" class="nav-icon">
                    Reportes Cursos
                </a>
            <?php elseif ($role === 'master'): ?>
                <a href="/imt-cursos/public/master/dashboard.php" class="nav-link-custom">
                    <img src="/imt-cursos/public/styles/iconos/home.png" alt="" class="nav-icon">
                    Dashboard
                </a>
                <a href="/imt-cursos/public/master/admin_estudiantes.php" class="nav-link-custom">
                    <img src="/imt-cursos/public/styles/iconos/addicon.png" alt="" class="nav-icon">
                    Admin Estudiantes
                </a>
                <a href="/imt-cursos/public/master/admin_docentes.php" class="nav-link-custom">
                    <img src="/imt-cursos/public/styles/iconos/edit.png" alt="" class="nav-icon">
                    Admin Docentes
                </a>
                <a href="/imt-cursos/public/master/admin_cursos.php" class="nav-link-custom">
                    <img src="/imt-cursos/public/styles/iconos/desk.png" alt="" class="nav-icon">
                    Admin Cursos
                </a>
                <a href="/imt-cursos/public/master/admin_plataforma.php" class="nav-link-custom">
                    <img src="/imt-cursos/public/styles/iconos/config.png" alt="" class="nav-icon">
                    Admin plataforma
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<style>
.responsive-header {
    background-color: #3498db !important;
}

.header-title {
    color: white !important;
}

.user-title {
    color: white !important;
}

.header-icon a {
    color: white !important;
}

.nav-link-custom {
    color: #5a5c69;
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 5px;
    transition: background-color 0.3s ease;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}

.nav-icon {
    width: 16px;
    height: 16px;
    opacity: 0.7;
    transition: opacity 0.3s ease;
    /* Cambiar iconos blancos a color oscuro */
    filter: brightness(0) saturate(100%) invert(26%) sepia(15%) saturate(1487%) hue-rotate(190deg) brightness(95%) contrast(90%);
}

.header-icon img {
    /* Aplicar filtro también a los iconos del header para hacerlos blancos */
    filter: brightness(0) invert(1) !important;
}

.user-title img {
    /* Aplicar filtro al icono de usuario para hacerlo blanco */
    filter: brightness(0) invert(1) !important;
}

.nav-link-custom:hover {
    background-color: #3498db;
    color: white;
}

.nav-link-custom:hover .nav-icon {
    opacity: 1;
    /* Cambiar a blanco en hover */
    filter: brightness(0) invert(1);
}

.nav-link-custom.active {
    background-color: #3498db;
    color: white;
    font-weight: 600;
}

.nav-link-custom.active .nav-icon {
    opacity: 1;
    /* Blanco para el estado activo */
    filter: brightness(0) invert(1);
}

@media (max-width: 600px) {
    .div-fila-alt-start {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .nav-link-custom {
        padding: 6px 12px;
        font-size: 0.9rem;
    }
    
    .nav-icon {
        width: 14px;
        height: 14px;
    }
}
</style>