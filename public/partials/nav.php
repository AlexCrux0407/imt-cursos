<?php
// Parcial de navegación: cabecera y enlaces según rol, con activo por ruta
$role = $_SESSION['role'] ?? null;
$nombre = $_SESSION['nombre'] ?? 'Usuario';

$current_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

// Clase 'active' si href coincide con ruta actual
function nav_active_class(string $href, string $current_path): string {
    return ($current_path === $href) ? ' active' : '';
}
?>
<header class="responsive-header">
    <div class="header-icon left-icon">
        <a href="<?= BASE_URL ?>/">
            <img src="<?= BASE_URL ?>/styles/iconos/Logo_IMT.png" alt="IMT Logo">
        </a>
    </div>
    
    <h1 class="header-title">IMT Cursos</h1>
    
    <div class="user-title" style="font-size: 0.9rem; color: #5a5c69;">
        <img src="<?= BASE_URL ?>/styles/iconos/entrada.png" alt="Usuario" style="width: 16px; height: 16px; margin-right: 5px; vertical-align: middle;">
        <?= htmlspecialchars($nombre) ?>
    </div>
    
    <div class="space-title"></div>
    
    <div class="header-icon right-icon">
        <a href="<?= BASE_URL ?>/logout.php" style="color: #5a5c69; text-decoration: none;" title="Cerrar sesión">
            <img src="<?= BASE_URL ?>/styles/iconos/logout.png" alt="Salir" style="width: 24px; height: 24px;">
        </a>
    </div>
</header>

<nav style="margin-top: 80px; background: #ffffff; border-bottom: 1px solid #ddd; padding: 10px 0;">
    <div class="contenido" style="padding: 10px 50px; margin-top: 0;">
        <div class="div-fila-alt-start" style="flex-wrap: wrap;">
            <?php if ($role === 'estudiante'): ?>
                <a href="<?= BASE_URL ?>/estudiante/dashboard.php" class="nav-link-custom<?= nav_active_class(BASE_URL . '/estudiante/dashboard.php', $current_path) ?>">
                    <img src="<?= BASE_URL ?>/styles/iconos/home.png" alt="" class="nav-icon">
                    Tablero
                </a>
                <a href="<?= BASE_URL ?>/estudiante/catalogo.php" class="nav-link-custom<?= nav_active_class(BASE_URL . '/estudiante/catalogo.php', $current_path) ?>">
                    <img src="<?= BASE_URL ?>/styles/iconos/desk.png" alt="" class="nav-icon">
                    Cursos disponibles
                </a>
                <a href="<?= BASE_URL ?>/estudiante/mis_cursos.php" class="nav-link-custom<?= nav_active_class(BASE_URL . '/estudiante/mis_cursos.php', $current_path) ?>">
                    <img src="<?= BASE_URL ?>/styles/iconos/detalles.png" alt="" class="nav-icon">
                    Mis cursos
                </a>
                <a href="<?= BASE_URL ?>/estudiante/cursos_completados.php" class="nav-link-custom<?= nav_active_class(BASE_URL . '/estudiante/cursos_completados.php', $current_path) ?>">
                    <img src="<?= BASE_URL ?>/styles/iconos/config.png" alt="" class="nav-icon">
                    Cursos completados
                </a>
                <a href="<?= BASE_URL ?>/estudiante/perfil.php" class="nav-link-custom<?= nav_active_class(BASE_URL . '/estudiante/perfil.php', $current_path) ?>">
                    <img src="<?= BASE_URL ?>/styles/iconos/entrada.png" alt="" class="nav-icon">
                    Perfil
                </a>
            <?php elseif ($role === 'docente'): ?>
                <a href="<?= BASE_URL ?>/docente/dashboard.php" class="nav-link-custom<?= nav_active_class(BASE_URL . '/docente/dashboard.php', $current_path) ?>">
                    <img src="<?= BASE_URL ?>/styles/iconos/home.png" alt="" class="nav-icon">
                    Dashboard
                </a>
                <a href="<?= BASE_URL ?>/docente/admin_cursos.php" class="nav-link-custom<?= nav_active_class(BASE_URL . '/docente/admin_cursos.php', $current_path) ?>">
                    <img src="<?= BASE_URL ?>/styles/iconos/config.png" alt="" class="nav-icon">
                    Administración de cursos
                </a>
            <?php elseif ($role === 'ejecutivo'): ?>
                <a href="<?= BASE_URL ?>/ejecutivo/dashboard.php" class="nav-link-custom<?= nav_active_class(BASE_URL . '/ejecutivo/dashboard.php', $current_path) ?>">
                    <img src="<?= BASE_URL ?>/styles/iconos/home.png" alt="" class="nav-icon">
                    Dashboard
                </a>
                <a href="<?= BASE_URL ?>/ejecutivo/detalles_estudiantes.php" class="nav-link-custom<?= nav_active_class(BASE_URL . '/ejecutivo/detalles_estudiantes.php', $current_path) ?>">
                    <img src="<?= BASE_URL ?>/styles/iconos/addicon.png" alt="" class="nav-icon">
                    Detalles Estudiantes
                </a>
                <a href="<?= BASE_URL ?>/ejecutivo/detalles_cursos.php" class="nav-link-custom<?= nav_active_class(BASE_URL . '/ejecutivo/detalles_cursos.php', $current_path) ?>">
                    <img src="<?= BASE_URL ?>/styles/iconos/desk.png" alt="" class="nav-icon">
                    Detalles Cursos
                </a>
                <a href="<?= BASE_URL ?>/ejecutivo/generar_reportes.php" class="nav-link-custom<?= nav_active_class(BASE_URL . '/ejecutivo/generar_reportes.php', $current_path) ?>">
                    <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="" class="nav-icon">
                    Generar Reportes
                </a>
                <a href="<?= BASE_URL ?>/ejecutivo/perfil.php" class="nav-link-custom<?= nav_active_class(BASE_URL . '/ejecutivo/perfil.php', $current_path) ?>">
                    <img src="<?= BASE_URL ?>/styles/iconos/entrada.png" alt="" class="nav-icon">
                    Perfil
                </a>
            <?php elseif ($role === 'master'): ?>
                <a href="<?= BASE_URL ?>/master/dashboard.php" class="nav-link-custom<?= nav_active_class(BASE_URL . '/master/dashboard.php', $current_path) ?>">
                    <img src="<?= BASE_URL ?>/styles/iconos/home.png" alt="" class="nav-icon">
                    Tablero
                </a>
                <a href="<?= BASE_URL ?>/master/admin_estudiantes.php" class="nav-link-custom<?= nav_active_class(BASE_URL . '/master/admin_estudiantes.php', $current_path) ?>">
                    <img src="<?= BASE_URL ?>/styles/iconos/addicon.png" alt="" class="nav-icon">
                    Estudiantes
                </a>
                <a href="<?= BASE_URL ?>/master/admin_docentes.php" class="nav-link-custom<?= nav_active_class(BASE_URL . '/master/admin_docentes.php', $current_path) ?>">
                    <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="" class="nav-icon">
                    Docentes
                </a>
                <a href="<?= BASE_URL ?>/master/admin_ejecutivos.php" class="nav-link-custom<?= nav_active_class(BASE_URL . '/master/admin_ejecutivos.php', $current_path) ?>">
                    <img src="<?= BASE_URL ?>/styles/iconos/entrada.png" alt="" class="nav-icon">
                    Ejecutivos
                </a>
                <a href="<?= BASE_URL ?>/master/admin_cursos.php" class="nav-link-custom<?= nav_active_class(BASE_URL . '/master/admin_cursos.php', $current_path) ?>">
                    <img src="<?= BASE_URL ?>/styles/iconos/desk.png" alt="" class="nav-icon">
                    Cursos
                </a>
                <a href="<?= BASE_URL ?>/master/admin_plataforma.php" class="nav-link-custom<?= nav_active_class(BASE_URL . '/master/admin_plataforma.php', $current_path) ?>">
                    <img src="<?= BASE_URL ?>/styles/iconos/config.png" alt="" class="nav-icon">
                    Plataforma
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<style>
.responsive-header {
    background-color: #3498db !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    z-index: 10000 !important;
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
    filter: brightness(0) saturate(100%) invert(26%) sepia(15%) saturate(1487%) hue-rotate(190deg) brightness(95%) contrast(90%);
}

.header-icon img {
    filter: brightness(0) invert(1) !important;
}

.user-title img {
    filter: brightness(0) invert(1) !important;
}

.nav-link-custom:hover {
    background-color: #3498db;
    color: white;
}

.nav-link-custom:hover .nav-icon {
    opacity: 1;
    filter: brightness(0) invert(1);
}

.nav-link-custom.active {
    background-color: #3498db;
    color: white;
    font-weight: 600;
}

.nav-link-custom.active .nav-icon {
    opacity: 1;
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