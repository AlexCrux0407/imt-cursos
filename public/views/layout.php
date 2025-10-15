<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Sistema de Gestión de Cursos IMT' ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/styles/main.css">
    
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?= $css ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
        <!-- Navigation Bar for logged in users -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container-fluid">
                <a class="navbar-brand" href="/">
                    <i class="fas fa-graduation-cap"></i> IMT Cursos
                </a>
                
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <?php if ($_SESSION['user_role'] === 'estudiante'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/estudiante/dashboard">Dashboard</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/estudiante/catalogo">Catálogo</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/estudiante/mis-cursos">Mis Cursos</a>
                            </li>
                        <?php elseif ($_SESSION['user_role'] === 'docente'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/docente/dashboard">Dashboard</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/docente/admin-cursos">Mis Cursos</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/docente/reportes">Reportes</a>
                            </li>
                        <?php elseif ($_SESSION['user_role'] === 'master'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/master/dashboard">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/master/admin-cursos">
                                    <i class="fas fa-book"></i> Administrar Cursos
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/master/asignar-cursos">
                                    <i class="fas fa-user-plus"></i> Asignar Cursos
                                </a>
                            </li>
                        <?php elseif ($_SESSION['user_role'] === 'ejecutivo'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/ejecutivo/dashboard">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/ejecutivo/reportes">
                                    <i class="fas fa-chart-bar"></i> Reportes
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="/ejecutivo/analytics">
                                    <i class="fas fa-analytics"></i> Analytics
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuario') ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="/perfil"><i class="fas fa-user-edit"></i> Mi Perfil</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/logout"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="<?= isset($_SESSION['user_id']) ? 'py-4' : 'min-vh-100 d-flex align-items-center' ?>">
        <?= $content ?>
    </main>

    <!-- Footer -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <footer class="bg-light text-center text-muted py-3 mt-5">
            <div class="container">
                <p>&copy; <?= date('Y') ?> Sistema de Gestión de Cursos IMT. Todos los derechos reservados.</p>
            </div>
        </footer>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if (isset($additionalJS)): ?>
        <?php foreach ($additionalJS as $js): ?>
            <script src="<?= $js ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>