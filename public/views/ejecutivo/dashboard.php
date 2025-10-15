<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="/ejecutivo/dashboard">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/ejecutivo/reportes">
                            <i class="fas fa-chart-bar"></i>
                            Reportes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/ejecutivo/analytics">
                            <i class="fas fa-analytics"></i>
                            Analytics
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard Ejecutivo</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="/ejecutivo/reportes" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-download"></i> Generar Reporte
                        </a>
                    </div>
                </div>
            </div>

            <!-- Estadísticas principales -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Cursos
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= $estadisticas['total_cursos'] ?? 0 ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-book fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Total Usuarios
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= $estadisticas['total_usuarios'] ?? 0 ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Inscripciones Activas
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?= $estadisticas['total_inscripciones'] ?? 0 ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Tasa de Finalización
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php 
                                        $tasa = $estadisticas['total_inscripciones'] > 0 
                                            ? ($estadisticas['cursos_completados'] / $estadisticas['total_inscripciones']) * 100 
                                            : 0;
                                        echo number_format($tasa, 1) . '%';
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-percentage fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráficos y análisis -->
            <div class="row mb-4">
                <!-- Gráfico de inscripciones -->
                <div class="col-xl-8 col-lg-7">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Inscripciones por Mes</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-area">
                                <canvas id="inscripcionesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cursos más populares -->
                <div class="col-xl-4 col-lg-5">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Cursos Más Populares</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($cursosPopulares)): ?>
                                <p class="text-muted">No hay datos disponibles.</p>
                            <?php else: ?>
                                <?php foreach ($cursosPopulares as $curso): ?>
                                    <div class="mb-3">
                                        <div class="small text-gray-500"><?= htmlspecialchars($curso['titulo']) ?></div>
                                        <div class="font-weight-bold"><?= $curso['total_inscripciones'] ?> inscripciones</div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acciones rápidas -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Acciones Rápidas</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <a href="/ejecutivo/reportes" class="btn btn-primary btn-block">
                                        <i class="fas fa-chart-bar"></i> Ver Reportes
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="/ejecutivo/analytics" class="btn btn-success btn-block">
                                        <i class="fas fa-analytics"></i> Analytics
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="/ejecutivo/exportar-reporte?tipo=inscripciones&formato=csv" class="btn btn-info btn-block">
                                        <i class="fas fa-download"></i> Exportar CSV
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <button class="btn btn-warning btn-block" onclick="window.print()">
                                        <i class="fas fa-print"></i> Imprimir
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}

.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}

.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}

.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}

.text-gray-800 {
    color: #5a5c69 !important;
}

.text-gray-300 {
    color: #dddfeb !important;
}

.text-gray-500 {
    color: #858796 !important;
}

.sidebar {
    position: fixed;
    top: 0;
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
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Gráfico de inscripciones
const ctx = document.getElementById('inscripcionesChart').getContext('2d');
const inscripcionesChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: [
            <?php foreach ($datosGraficos as $dato): ?>
                '<?= $dato['mes'] ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            label: 'Inscripciones',
            data: [
                <?php foreach ($datosGraficos as $dato): ?>
                    <?= $dato['inscripciones'] ?>,
                <?php endforeach; ?>
            ],
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Tendencia de Inscripciones'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>