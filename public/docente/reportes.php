<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Reportes';

// Obtener estadísticas generales del docente
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT c.id) as total_cursos,
        COUNT(DISTINCT i.usuario_id) as total_estudiantes,
        AVG(i.progreso) as progreso_promedio,
        COUNT(CASE WHEN i.estado = 'completado' THEN 1 END) as cursos_completados
    FROM cursos c
    LEFT JOIN inscripciones i ON c.id = i.curso_id 
    WHERE c.creado_por = :docente_id OR c.asignado_a = :docente_id2
");
$stmt->execute([':docente_id' => $_SESSION['user_id'], ':docente_id2' => $_SESSION['user_id']]);
$estadisticas = $stmt->fetch();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<div class="contenido">
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; text-align: center;">
        <h1 style="font-size: 2rem; margin-bottom: 10px;">Reportes de Docente</h1>
        <p style="opacity: 0.9;">Estadísticas y análisis de tus cursos y estudiantes</p>
    </div>

    <!-- Estadísticas Principales -->
    <div class="form-container-body" style="margin-bottom: 20px;">
        <div class="div-fila" style="gap: 20px;">
            <div style="background: #3498db; color: white; padding: 25px; border-radius: 12px; text-align: center; flex: 1;">
                <div style="font-size: 2.5rem; font-weight: bold;"><?= $estadisticas['total_cursos'] ?: 0 ?></div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Cursos Creados</div>
            </div>
            <div style="background: #3498db; color: white; padding: 25px; border-radius: 12px; text-align: center; flex: 1;">
                <div style="font-size: 2.5rem; font-weight: bold;"><?= $estadisticas['total_estudiantes'] ?: 0 ?></div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Estudiantes Activos</div>
            </div>
            <div style="background: #3498db; color: white; padding: 25px; border-radius: 12px; text-align: center; flex: 1;">
                <div style="font-size: 2.5rem; font-weight: bold;"><?= number_format($estadisticas['progreso_promedio'] ?: 0, 1) ?>%</div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Progreso Promedio</div>
            </div>
            <div style="background: #3498db; color: white; padding: 25px; border-radius: 12px; text-align: center; flex: 1;">
                <div style="font-size: 2.5rem; font-weight: bold;"><?= $estadisticas['cursos_completados'] ?: 0 ?></div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Completados</div>
            </div>
        </div>
    </div>

    <!-- Acciones Rápidas -->
    <div class="form-container-body">
        <h2 style="color: #3498db; margin-bottom: 20px;">Opciones de Reportes</h2>
        <div class="div-fila" style="gap: 20px;">
            <div style="flex: 1; padding: 20px; border: 2px solid #e3f2fd; border-radius: 12px; background: white; text-align: center; cursor: pointer; transition: all 0.3s ease;"
                 onclick="window.location.href='<?= BASE_URL ?>/docente/admin_cursos.php'"
                 onmouseover="this.style.borderColor='#3498db'; this.style.transform='translateY(-2px)'"
                 onmouseout="this.style.borderColor='#e3f2fd'; this.style.transform='translateY(0)'">
                <img src="<?= BASE_URL ?>/styles/iconos/desk.png" style="width: 48px; height: 48px; filter: brightness(0) saturate(100%) invert(27%) sepia(51%) saturate(2878%) hue-rotate(210deg) brightness(104%) contrast(97%); margin-bottom: 15px;">
                <h4 style="color: #2c3e50; margin-bottom: 10px;">Ver Mis Cursos</h4>
                <p style="color: #7f8c8d; margin: 0;">Administrar y revisar todos tus cursos</p>
            </div>
            
            <div style="flex: 1; padding: 20px; border: 2px solid #e3f2fd; border-radius: 12px; background: white; text-align: center; cursor: pointer; transition: all 0.3s ease;"
                 onclick="window.location.href='<?= BASE_URL ?>/docente/resumen_general.php'"
                 onmouseover="this.style.borderColor='#3498db'; this.style.transform='translateY(-2px)'"
                 onmouseout="this.style.borderColor='#e3f2fd'; this.style.transform='translateY(0)'">
                <img src="<?= BASE_URL ?>/styles/iconos/detalles.png" style="width: 48px; height: 48px; filter: brightness(0) saturate(100%) invert(27%) sepia(51%) saturate(2878%) hue-rotate(210deg) brightness(104%) contrast(97%); margin-bottom: 15px;">
                <h4 style="color: #2c3e50; margin-bottom: 10px;">Resumen General</h4>
                <p style="color: #7f8c8d; margin: 0;">Vista completa de cursos y estudiantes</p>
            </div>
            
            <div style="flex: 1; padding: 20px; border: 2px solid #e3f2fd; border-radius: 12px; background: white; text-align: center; cursor: pointer; transition: all 0.3s ease;"
                 onclick="window.location.href='<?= BASE_URL ?>/docente/dashboard.php'"
                 onmouseover="this.style.borderColor='#3498db'; this.style.transform='translateY(-2px)'"
                 onmouseout="this.style.borderColor='#e3f2fd'; this.style.transform='translateY(0)'">
                <img src="<?= BASE_URL ?>/styles/iconos/home.png" style="width: 48px; height: 48px; filter: brightness(0) saturate(100%) invert(27%) sepia(51%) saturate(2878%) hue-rotate(210deg) brightness(104%) contrast(97%); margin-bottom: 15px;">
                <h4 style="color: #2c3e50; margin-bottom: 10px;">Dashboard</h4>
                <p style="color: #7f8c8d; margin: 0;">Volver al panel principal</p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
