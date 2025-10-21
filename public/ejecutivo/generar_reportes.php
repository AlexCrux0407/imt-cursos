<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('ejecutivo');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Ejecutivo – Generar Reportes';

// Obtener parámetros
$tipo = $_GET['tipo'] ?? '';
$id = $_GET['id'] ?? '';
$formato = $_GET['formato'] ?? '';

// Si se solicita generar un reporte específico
if ($formato && $tipo) {
    if ($formato === 'pdf') {
        header('Location: ' . BASE_URL . '/ejecutivo/exportar_pdf.php?tipo=' . $tipo . ($id ? '&id=' . $id : ''));
        exit;
    } elseif ($formato === 'excel') {
        header('Location: ' . BASE_URL . '/ejecutivo/exportar_excel.php?tipo=' . $tipo . ($id ? '&id=' . $id : ''));
        exit;
    }
}

// Obtener estadísticas generales para mostrar en la página
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT CASE WHEN u.role = 'estudiante' AND u.estado = 'activo' THEN u.id END) as total_estudiantes,
        COUNT(DISTINCT CASE WHEN c.estado = 'activo' THEN c.id END) as total_cursos,
        COUNT(DISTINCT i.id) as total_inscripciones,
        AVG(COALESCE(i.progreso, 0)) as promedio_progreso
    FROM usuarios u
    LEFT JOIN cursos c ON 1=1
    LEFT JOIN inscripciones i ON u.id = i.usuario_id
    WHERE (u.role = 'estudiante' OR u.role IS NULL)
");
$stmt->execute();
$estadisticas = $stmt->fetch();

// Obtener cursos para el selector
$stmt = $conn->prepare("
    SELECT c.id, c.titulo, COUNT(DISTINCT i.id) as estudiantes_inscritos
    FROM cursos c
    LEFT JOIN inscripciones i ON c.id = i.curso_id
    WHERE c.estado = 'activo'
    GROUP BY c.id
    ORDER BY c.titulo
");
$stmt->execute();
$cursos_disponibles = $stmt->fetchAll();

// Obtener estudiantes para el selector
$stmt = $conn->prepare("
    SELECT u.id, u.nombre, u.email, COUNT(DISTINCT i.id) as cursos_inscritos
    FROM usuarios u
    LEFT JOIN inscripciones i ON u.id = i.usuario_id
    WHERE u.role = 'estudiante' AND u.estado = 'activo'
    GROUP BY u.id
    ORDER BY u.nombre
");
$stmt->execute();
$estudiantes_disponibles = $stmt->fetchAll();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/ejecutivo.css">

<div class="exec-dashboard">
    <div class="exec-header">
        <h1 class="exec-title">Generar Reportes</h1>
        <p class="exec-subtitle">Exporta datos detallados en formato PDF o Excel</p>
    </div>

    <!-- Estadísticas Generales -->
    <div class="exec-stats">
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= number_format($estadisticas['total_estudiantes']) ?></span>
            <span class="exec-stat-description">Estudiantes Activos</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= number_format($estadisticas['total_cursos']) ?></span>
            <span class="exec-stat-description">Cursos Activos</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= number_format($estadisticas['total_inscripciones']) ?></span>
            <span class="exec-stat-description">Total Inscripciones</span>
        </div>
        <div class="exec-stat-item">
            <span class="exec-stat-value"><?= number_format($estadisticas['promedio_progreso'], 1) ?>%</span>
            <span class="exec-stat-description">Progreso Promedio</span>
        </div>
    </div>

    <!-- Opciones de Reportes -->
    <div class="reports-options">
        
        <!-- Reportes Generales -->
        <div class="report-section">
            <h3>Reportes Generales</h3>
            <div class="report-cards">
                
                <div class="report-card">
                    <div class="report-icon">
                        <img src="<?= BASE_URL ?>/styles/iconos/desk.png" alt="Cursos">
                    </div>
                    <div class="report-content">
                        <h4>Reporte de Todos los Cursos</h4>
                        <p>Información completa de todos los cursos, incluyendo estadísticas de inscripciones y progreso.</p>
                        <div class="report-stats">
                            <span><?= $estadisticas['total_cursos'] ?> cursos disponibles</span>
                        </div>
                    </div>
                    <div class="report-actions">
                        <a href="exportar_pdf.php?tipo=cursos" class="btn-export-pdf" target="_blank">
                            <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="PDF">
                            PDF
                        </a>
                        <a href="exportar_excel.php?tipo=cursos" class="btn-export-excel" target="_blank">
                            <img src="<?= BASE_URL ?>/styles/iconos/addicon.png" alt="Excel">
                            Excel
                        </a>
                    </div>
                </div>

                <div class="report-card">
                    <div class="report-icon">
                        <img src="<?= BASE_URL ?>/styles/iconos/addicon.png" alt="Estudiantes">
                    </div>
                    <div class="report-content">
                        <h4>Reporte de Todos los Estudiantes</h4>
                        <p>Información completa de todos los estudiantes, incluyendo progreso y rendimiento académico.</p>
                        <div class="report-stats">
                            <span><?= $estadisticas['total_estudiantes'] ?> estudiantes activos</span>
                        </div>
                    </div>
                    <div class="report-actions">
                        <a href="exportar_pdf.php?tipo=estudiantes" class="btn-export-pdf" target="_blank">
                            <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="PDF">
                            PDF
                        </a>
                        <a href="exportar_excel.php?tipo=estudiantes" class="btn-export-excel" target="_blank">
                            <img src="<?= BASE_URL ?>/styles/iconos/addicon.png" alt="Excel">
                            Excel
                        </a>
                    </div>
                </div>

                <div class="report-card">
                    <div class="report-icon">
                        <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="Resumen">
                    </div>
                    <div class="report-content">
                        <h4>Reporte Ejecutivo General</h4>
                        <p>Resumen ejecutivo con métricas clave, estadísticas generales y análisis de rendimiento.</p>
                        <div class="report-stats">
                            <span>Resumen completo de la plataforma</span>
                        </div>
                    </div>
                    <div class="report-actions">
                        <a href="exportar_pdf.php?tipo=resumen" class="btn-export-pdf" target="_blank">
                            <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="PDF">
                            PDF
                        </a>
                        <a href="exportar_excel.php?tipo=resumen" class="btn-export-excel" target="_blank">
                            <img src="<?= BASE_URL ?>/styles/iconos/addicon.png" alt="Excel">
                            Excel
                        </a>
                    </div>
                </div>

            </div>
        </div>

        <!-- Reportes por Curso Específico -->
        <div class="report-section">
            <h3>Reportes por Curso Específico</h3>
            <div class="specific-reports">
                
                <div class="selector-section">
                    <label for="curso-selector">Seleccionar Curso:</label>
                    <select id="curso-selector" class="report-selector">
                        <option value="">-- Selecciona un curso --</option>
                        <?php foreach ($cursos_disponibles as $curso): ?>
                            <option value="<?= $curso['id'] ?>" <?= $id == $curso['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($curso['titulo']) ?> (<?= $curso['estudiantes_inscritos'] ?> estudiantes)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="selector-actions" id="curso-actions" style="display: <?= $tipo === 'curso' && $id ? 'block' : 'none' ?>;">
                    <div class="action-description">
                        <h4>Reporte Detallado del Curso</h4>
                        <p>Información completa del curso seleccionado, incluyendo todos los estudiantes inscritos, su progreso individual y estadísticas detalladas.</p>
                    </div>
                    <div class="action-buttons">
                        <a href="#" class="btn-export-pdf curso-pdf-link" target="_blank">
                            <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="PDF">
                            Generar PDF
                        </a>
                        <a href="#" class="btn-export-excel curso-excel-link" target="_blank">
                            <img src="<?= BASE_URL ?>/styles/iconos/addicon.png" alt="Excel">
                            Generar Excel
                        </a>
                    </div>
                </div>

            </div>
        </div>

        <!-- Reportes por Estudiante Específico -->
        <div class="report-section">
            <h3>Reportes por Estudiante Específico</h3>
            <div class="specific-reports">
                
                <div class="selector-section">
                    <label for="estudiante-selector">Seleccionar Estudiante:</label>
                    <select id="estudiante-selector" class="report-selector">
                        <option value="">-- Selecciona un estudiante --</option>
                        <?php foreach ($estudiantes_disponibles as $estudiante): ?>
                            <option value="<?= $estudiante['id'] ?>" <?= $id == $estudiante['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($estudiante['nombre']) ?> - <?= htmlspecialchars($estudiante['email']) ?> (<?= $estudiante['cursos_inscritos'] ?> cursos)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="selector-actions" id="estudiante-actions" style="display: <?= $tipo === 'estudiante' && $id ? 'block' : 'none' ?>;">
                    <div class="action-description">
                        <h4>Reporte Detallado del Estudiante</h4>
                        <p>Información completa del estudiante seleccionado, incluyendo todos sus cursos, progreso individual, calificaciones y actividad académica.</p>
                    </div>
                    <div class="action-buttons">
                        <a href="#" class="btn-export-pdf estudiante-pdf-link" target="_blank">
                            <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="PDF">
                            Generar PDF
                        </a>
                        <a href="#" class="btn-export-excel estudiante-excel-link" target="_blank">
                            <img src="<?= BASE_URL ?>/styles/iconos/addicon.png" alt="Excel">
                            Generar Excel
                        </a>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- Información Adicional -->
    <div class="report-info">
        <h3>Información sobre los Reportes</h3>
        <div class="info-grid">
            <div class="info-item">
                <h4>Formato PDF</h4>
                <p>Reportes profesionales con gráficos y tablas, ideales para presentaciones y documentación oficial.</p>
            </div>
            <div class="info-item">
                <h4>Formato Excel</h4>
                <p>Datos estructurados en hojas de cálculo, perfectos para análisis adicional y manipulación de datos.</p>
            </div>
            <div class="info-item">
                <h4>Datos en Tiempo Real</h4>
                <p>Todos los reportes se generan con la información más actualizada disponible en la plataforma.</p>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cursoSelector = document.getElementById('curso-selector');
    const estudianteSelector = document.getElementById('estudiante-selector');
    const cursoActions = document.getElementById('curso-actions');
    const estudianteActions = document.getElementById('estudiante-actions');
    const cursoPdfLink = document.querySelector('.curso-pdf-link');
    const cursoExcelLink = document.querySelector('.curso-excel-link');
    const estudiantePdfLink = document.querySelector('.estudiante-pdf-link');
    const estudianteExcelLink = document.querySelector('.estudiante-excel-link');

    // Manejar selección de curso
    cursoSelector.addEventListener('change', function() {
        const cursoId = this.value;
        if (cursoId) {
            cursoActions.style.display = 'block';
            cursoPdfLink.href = `exportar_pdf.php?tipo=curso&id=${cursoId}`;
            cursoExcelLink.href = `exportar_excel.php?tipo=curso&id=${cursoId}`;
        } else {
            cursoActions.style.display = 'none';
        }
    });

    // Manejar selección de estudiante
    estudianteSelector.addEventListener('change', function() {
        const estudianteId = this.value;
        if (estudianteId) {
            estudianteActions.style.display = 'block';
            estudiantePdfLink.href = `exportar_pdf.php?tipo=estudiante&id=${estudianteId}`;
            estudianteExcelLink.href = `exportar_excel.php?tipo=estudiante&id=${estudianteId}`;
        } else {
            estudianteActions.style.display = 'none';
        }
    });

    // Inicializar enlaces si hay valores preseleccionados
    if (cursoSelector.value) {
        cursoActions.style.display = 'block';
        cursoPdfLink.href = `exportar_pdf.php?tipo=curso&id=${cursoSelector.value}`;
        cursoExcelLink.href = `exportar_excel.php?tipo=curso&id=${cursoSelector.value}`;
    }

    if (estudianteSelector.value) {
        estudianteActions.style.display = 'block';
        estudiantePdfLink.href = `exportar_pdf.php?tipo=estudiante&id=${estudianteSelector.value}`;
        estudianteExcelLink.href = `exportar_excel.php?tipo=estudiante&id=${estudianteSelector.value}`;
    }
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>