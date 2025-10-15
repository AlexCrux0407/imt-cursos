<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="/estudiante/dashboard">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/estudiante/catalogo">
                            <i class="fas fa-book"></i> Catálogo
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/estudiante/mis-cursos">
                            <i class="fas fa-graduation-cap"></i> Mis Cursos
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
                <h1 class="h2">Dashboard - Estudiante</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <span class="text-muted">Bienvenido, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuario') ?></span>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?= $totalCursos ?? 0 ?></h4>
                                    <p class="card-text">Cursos Inscritos</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-book fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?= $cursosCompletados ?? 0 ?></h4>
                                    <p class="card-text">Cursos Completados</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-trophy fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4><?= ($totalCursos ?? 0) - ($cursosCompletados ?? 0) ?></h4>
                                    <p class="card-text">En Progreso</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cursos Recientes -->
            <div class="row">
                <div class="col-12">
                    <h3>Mis Cursos Recientes</h3>
                    <?php if (!empty($cursosInscritos)): ?>
                        <div class="row">
                            <?php foreach (array_slice($cursosInscritos, 0, 3) as $curso): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title"><?= htmlspecialchars($curso['titulo']) ?></h5>
                                            <p class="card-text"><?= htmlspecialchars(substr($curso['descripcion'], 0, 100)) ?>...</p>
                                            <div class="progress mb-2">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?= $curso['progreso'] ?>%" 
                                                     aria-valuenow="<?= $curso['progreso'] ?>" 
                                                     aria-valuemin="0" aria-valuemax="100">
                                                    <?= $curso['progreso'] ?>%
                                                </div>
                                            </div>
                                            <a href="/estudiante/curso/<?= $curso['id'] ?>" class="btn btn-primary btn-sm">
                                                Continuar
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="/estudiante/mis-cursos" class="btn btn-outline-primary">Ver Todos Mis Cursos</a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <h5>¡Comienza tu aprendizaje!</h5>
                            <p>Aún no tienes cursos inscritos. Explora nuestro catálogo y encuentra cursos interesantes.</p>
                            <a href="/estudiante/catalogo" class="btn btn-primary">Explorar Catálogo</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>