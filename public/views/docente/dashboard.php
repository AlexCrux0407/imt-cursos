<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="/docente/dashboard">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/docente/admin-cursos">
                            <i class="fas fa-book"></i> Mis Cursos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/docente/reportes">
                            <i class="fas fa-chart-bar"></i> Reportes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/docente/perfil">
                            <i class="fas fa-user"></i> Mi Perfil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/logout">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard - Docente</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <span class="text-muted">Bienvenido, <?= htmlspecialchars($_SESSION['nombre'] ?? 'Docente') ?></span>
                </div>
            </div>

            <!-- Estadísticas principales -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?= $estadisticas['cursos_activos'] ?: 0 ?></h4>
                                    <p class="card-text">Cursos Activos</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-book fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?= $estadisticas['total_estudiantes'] ?: 0 ?></h4>
                                    <p class="card-text">Estudiantes</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?= number_format($estadisticas['promedio_avance'] ?: 0, 1) ?>%</h4>
                                    <p class="card-text">Progreso Promedio</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-chart-line fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?= $estadisticas['certificados_emitidos'] ?: 0 ?></h4>
                                    <p class="card-text">Certificados</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-certificate fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acciones rápidas -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Acciones Rápidas</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-2">
                                    <a href="/docente/crear-curso" class="btn btn-primary btn-block">
                                        <i class="fas fa-plus"></i> Crear Curso
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="/docente/admin-cursos" class="btn btn-outline-primary btn-block">
                                        <i class="fas fa-edit"></i> Gestionar Cursos
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="/docente/reportes" class="btn btn-outline-info btn-block">
                                        <i class="fas fa-chart-bar"></i> Ver Reportes
                                    </a>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <a href="/docente/revisar-evaluaciones" class="btn btn-outline-warning btn-block">
                                        <i class="fas fa-clipboard-check"></i> Revisar Evaluaciones
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cursos recientes -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Mis Cursos Recientes</h5>
                            <a href="/docente/admin-cursos" class="btn btn-sm btn-outline-primary">Ver Todos</a>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($cursosRecientes)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Curso</th>
                                                <th>Estado</th>
                                                <th>Estudiantes</th>
                                                <th>Progreso Promedio</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cursosRecientes as $curso): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($curso['titulo']) ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?= htmlspecialchars(substr($curso['descripcion'], 0, 50)) ?>...</small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $curso['estado'] === 'activo' ? 'success' : 'secondary' ?>">
                                                            <?= ucfirst($curso['estado']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= $curso['total_inscritos'] ?: 0 ?></td>
                                                    <td>
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar" role="progressbar" 
                                                                 style="width: <?= $curso['progreso_promedio'] ?: 0 ?>%" 
                                                                 aria-valuenow="<?= $curso['progreso_promedio'] ?: 0 ?>" 
                                                                 aria-valuemin="0" aria-valuemax="100">
                                                                <?= number_format($curso['progreso_promedio'] ?: 0, 1) ?>%
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <a href="/docente/editar-curso/<?= $curso['id'] ?>" class="btn btn-outline-primary">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="/docente/modulos-curso/<?= $curso['id'] ?>" class="btn btn-outline-info">
                                                                <i class="fas fa-list"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <h5>¡Comienza a crear contenido!</h5>
                                    <p>Aún no tienes cursos creados. Crea tu primer curso para comenzar a enseñar.</p>
                                    <a href="/docente/crear-curso" class="btn btn-primary">Crear Mi Primer Curso</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
.sidebar {
    position: fixed;
    top: 56px;
    bottom: 0;
    left: 0;
    z-index: 100;
    padding: 48px 0 0;
    box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
}

.sidebar .nav-link {
    font-weight: 500;
    color: #333;
}

.sidebar .nav-link.active {
    color: #007bff;
}

.sidebar .nav-link:hover {
    color: #007bff;
}

@media (max-width: 767.98px) {
    .sidebar {
        top: 5rem;
    }
}
</style>