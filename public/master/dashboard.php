<?php
// Dashboard de Master: métricas globales y accesos a administración del sistema
require_once __DIR__ . '/../../app/auth.php';
require_role('master');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Master – Dashboard Administrativo';

// Obtener estadísticas del sistema
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT CASE WHEN role = 'estudiante' THEN id END) as total_estudiantes,
        COUNT(DISTINCT CASE WHEN role = 'docente' THEN id END) as total_docentes,
        COUNT(DISTINCT CASE WHEN role IN ('estudiante', 'docente') THEN id END) as total_usuarios,
        COUNT(DISTINCT CASE WHEN role = 'docente' AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN id END) as docentes_activos
    FROM usuarios
");
$stmt->execute();
$stats_usuarios = $stmt->fetch();

$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_cursos,
        COUNT(CASE WHEN estado = 'activo' THEN 1 END) as cursos_activos,
        COUNT(CASE WHEN estado = 'borrador' THEN 1 END) as cursos_borrador
    FROM cursos
");
$stmt->execute();
$stats_cursos = $stmt->fetch();

// Obtener solicitudes pendientes (usuarios sin verificar o cursos pendientes)
$stmt = $conn->prepare("
    SELECT COUNT(*) as solicitudes_pendientes
    FROM usuarios 
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmt->execute();
$solicitudes = $stmt->fetch();

// Obtener cursos en revisión
$stmt = $conn->prepare("
    SELECT COUNT(*) as cursos_revision
    FROM cursos 
    WHERE estado = 'borrador' AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->execute();
$revision = $stmt->fetch();

// Obtener actividad reciente
$stmt = $conn->prepare("
    SELECT 'usuario' as tipo, nombre, email, created_at as fecha
    FROM usuarios 
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute();
$actividad_reciente = $stmt->fetchAll();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/master.css">

<div class="contenido">
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #3498db); color: white; text-align: center;">
        <h1 style="font-size: 2.5rem; margin-bottom: 10px; font-weight: 600;">Panel de Administración</h1>
        <p style="font-size: 1.1rem; opacity: 0.9;">Sistema de gestión integral IMT Cursos</p>
        <small style="opacity: 0.8;">Último acceso: <?= date('d/m/Y H:i') ?></small>
    </div>

    <div class="form-container-body" style="margin-bottom: 20px;">
        <div class="div-fila" style="gap: 20px;">
            <div style="background: linear-gradient(135deg,#3498db, #3498db); color: white; padding: 25px; border-radius: 12px; text-align: center; flex: 1;">
                <div style="font-size: 2.5rem; font-weight: bold; margin-bottom: 5px;"><?= $stats_usuarios['total_usuarios'] ?: 0 ?></div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Total Usuarios</div>
                <small style="opacity: 0.7; font-size: 0.8rem;">Estudiantes y Docentes</small>
            </div>
            <div style="background: linear-gradient(135deg,#3498db, #3498db); color: white; padding: 25px; border-radius: 12px; text-align: center; flex: 1;">
                <div style="font-size: 2.5rem; font-weight: bold; margin-bottom: 5px;"><?= $stats_usuarios['total_docentes'] ?: 0 ?></div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Docentes</div>
                <small style="opacity: 0.7; font-size: 0.8rem;"><?= $stats_usuarios['docentes_activos'] ?: 0 ?> activos este mes</small>
            </div>
            <div style="background: linear-gradient(135deg,#3498db, #3498db); color: white; padding: 25px; border-radius: 12px; text-align: center; flex: 1;">
                <div style="font-size: 2.5rem; font-weight: bold; margin-bottom: 5px;"><?= $stats_usuarios['total_estudiantes'] ?: 0 ?></div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Estudiantes</div>
                <small style="opacity: 0.7; font-size: 0.8rem;">Registrados</small>
            </div>
            <div style="background: linear-gradient(135deg,#3498db, #3498db); color: white; padding: 25px; border-radius: 12px; text-align: center; flex: 1;">
                <div style="font-size: 2.5rem; font-weight: bold; margin-bottom: 5px;"><?= $stats_cursos['cursos_activos'] ?: 0 ?></div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Cursos Activos</div>
                <small style="opacity: 0.7; font-size: 0.8rem;"><?= $stats_cursos['cursos_borrador'] ?: 0 ?> en borrador</small>
            </div>
        </div>
    </div>

    <div class="div-fila" style="gap: 25px; margin-bottom: 30px;">
        <div class="form-container-body" style="flex: 1; transition: all 0.3s ease; cursor: pointer;" 
             onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 25px rgba(0,102,204,0.15)'"
             onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='4px 4px 10px rgba(0, 0, 0, 0.3)'"
             onclick="window.location.href='<?= BASE_URL ?>/master/admin_estudiantes.php'">
            <div class="div-fila-alt-start" style="margin-bottom: 20px;">
                <div style="width: 50px; height: 50px; background: var(--master-primary); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <img src="<?= BASE_URL ?>/styles/iconos/addicon.png" alt="Estudiantes" style="width: 24px; height: 24px; filter: brightness(0) invert(1);">
                </div>
                <div>
                    <h3 style="color: var(--master-primary); font-size: 1.3rem; margin-bottom: 5px;">Gestión de Estudiantes</h3>
                    <p style="color: #7f8c8d; font-size: 0.9rem;"><?= $stats_usuarios['total_estudiantes'] ?> estudiantes registrados</p>
                </div>
            </div>
            <p style="color: #5a5c69; margin-bottom: 20px; line-height: 1.5;">
                Gestiona estudiantes registrados, revisa progreso académico y administra inscripciones a cursos.
            </p>
            <div class="div-fila-alt">
                <a href="<?= BASE_URL ?>/master/admin_estudiantes.php" 
                   style="background: var(--master-primary); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease;"
                   onmouseover="this.style.background='var(--master-secondary)'"
                   onmouseout="this.style.background='var(--master-primary)'">
                    Administrar →
                </a>
            </div>
        </div>

        <div class="form-container-body" style="flex: 1; transition: all 0.3s ease; cursor: pointer;"
             onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 25px rgba(52,152,219,0.15)'"
             onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='4px 4px 10px rgba(0, 0, 0, 0.3)'"
             onclick="window.location.href='<?= BASE_URL ?>/master/admin_docentes.php'">
            <div class="div-fila-alt-start" style="margin-bottom: 20px;">
                <div style="width: 50px; height: 50px; background: var(--master-primary); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <img src="<?= BASE_URL ?>/styles/iconos/edit.png" alt="Docentes" style="width: 24px; height: 24px; filter: brightness(0) invert(1);">
                </div>
                <div>
                    <h3 style="color: var(--master-primary); font-size: 1.3rem; margin-bottom: 5px;">Gestión de Docentes</h3>
                    <p style="color: #7f8c8d; font-size: 0.9rem;"><?= $stats_usuarios['total_docentes'] ?> docentes activos</p>
                </div>
            </div>
            <p style="color: #5a5c69; margin-bottom: 20px; line-height: 1.5;">
                Administra personal docente, asigna cursos y supervisa la actividad educativa del instituto.
            </p>
            <div class="div-fila-alt">
                <a href="<?= BASE_URL ?>/master/admin_docentes.php" 
                   style="background: var(--master-primary); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease;"
                   onmouseover="this.style.background='var(--master-secondary)'"
                   onmouseout="this.style.background='var(--master-primary)'">
                    Administrar →
                </a>
            </div>
        </div>
    </div>

    <div class="div-fila" style="gap: 25px; margin-bottom: 30px;">
        <div class="form-container-body" style="flex: 1; transition: all 0.3s ease; cursor: pointer;"
             onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 25px rgba(93,173,226,0.15)'"
             onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='4px 4px 10px rgba(0, 0, 0, 0.3)'"
             onclick="window.location.href='<?= BASE_URL ?>/master/admin_cursos.php'">
            <div class="div-fila-alt-start" style="margin-bottom: 20px;">
                <div style="width: 50px; height: 50px; background: var(--master-primary); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <img src="<?= BASE_URL ?>/styles/iconos/desk.png" alt="Cursos" style="width: 24px; height: 24px; filter: brightness(0) invert(1);">
                </div>
                <div>
                    <h3 style="color: var(--master-primary); font-size: 1.3rem; margin-bottom: 5px;">Gestión de Cursos</h3>
                    <p style="color: #7f8c8d; font-size: 0.9rem;"><?= $stats_cursos['total_cursos'] ?> cursos en el catálogo</p>
                </div>
            </div>
            <p style="color: #5a5c69; margin-bottom: 20px; line-height: 1.5;">
                Controla el catálogo de cursos, aprueba contenido y gestiona certificaciones académicas.
            </p>
            <div class="div-fila-alt">
                <a href="<?= BASE_URL ?>/master/admin_cursos.php" 
                   style="background: var(--master-primary); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease;"
                   onmouseover="this.style.background='var(--master-secondary)'"
                   onmouseout="this.style.background='var(--master-primary)'">
                    Administrar →
                </a>
            </div>
        </div>

        <div class="form-container-body" style="flex: 1; transition: all 0.3s ease; cursor: pointer;"
             onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 25px rgba(46,134,193,0.15)'"
             onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='4px 4px 10px rgba(0, 0, 0, 0.3)'"
             onclick="window.location.href='<?= BASE_URL ?>/master/admin_ejecutivos.php'">
            <div class="div-fila-alt-start" style="margin-bottom: 20px;">
                <div style="width: 50px; height: 50px; background: var(--master-primary); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <img src="<?= BASE_URL ?>/styles/iconos/addicon.png" alt="Ejecutivos" style="width: 24px; height: 24px; filter: brightness(0) invert(1);">
                </div>
                <div>
                    <h3 style="color: var(--master-primary); font-size: 1.3rem; margin-bottom: 5px;">Gestión de Ejecutivos</h3>
                    <p style="color: #7f8c8d; font-size: 0.9rem;">Administrar usuarios ejecutivos</p>
                </div>
            </div>
            <p style="color: #5a5c69; margin-bottom: 20px; line-height: 1.5;">
                Gestiona usuarios ejecutivos del sistema, crea nuevos perfiles y administra permisos de acceso.
            </p>
            <div class="div-fila-alt">
                <a href="<?= BASE_URL ?>/master/admin_ejecutivos.php" 
                   style="background: var(--master-primary); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease;"
                   onmouseover="this.style.background='var(--master-secondary)'"
                   onmouseout="this.style.background='var(--master-primary)'">
                    Gestionar →
                </a>
            </div>
        </div>
    </div>

    <div class="div-fila" style="gap: 25px; margin-bottom: 30px;">
        <div class="form-container-body" style="flex: 1; transition: all 0.3s ease; cursor: pointer;"
             onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 25px rgba(133,193,233,0.15)'"
             onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='4px 4px 10px rgba(0, 0, 0, 0.3)'"
             onclick="window.location.href='<?= BASE_URL ?>/master/admin_plataforma.php'">
            <div class="div-fila-alt-start" style="margin-bottom: 20px;">
                <div style="width: 50px; height: 50px; background: var(--master-primary); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <img src="<?= BASE_URL ?>/styles/iconos/config.png" alt="Sistema" style="width: 24px; height: 24px; filter: brightness(0) invert(1);">
                </div>
                <div>
                    <h3 style="color: var(--master-primary); font-size: 1.3rem; margin-bottom: 5px;">Configuración</h3>
                    <p style="color: #7f8c8d; font-size: 0.9rem;">Parámetros del sistema</p>
                </div>
            </div>
            <p style="color: #5a5c69; margin-bottom: 20px; line-height: 1.5;">
                Administra configuraciones globales, parámetros del sistema y mantenimiento general.
            </p>
            <div class="div-fila-alt">
                <a href="<?= BASE_URL ?>/master/admin_plataforma.php" 
                   style="background: var(--master-primary); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease;"
                   onmouseover="this.style.background='var(--master-secondary)'"
                   onmouseout="this.style.background='var(--master-primary)'">
                    Configurar →
                </a>
            </div>
        </div>
    </div>

    <div class="form-container-body">
        <h2 style="color: var(--master-primary); font-size: 1.5rem; margin-bottom: 25px; border-bottom: 2px solid #e8ecef; padding-bottom: 15px;">
            Estado del Sistema
        </h2>
        <div class="div-fila" style="gap: 20px;">
            <div style="flex: 1; padding: 20px; border: 2px solid #e3f2fd; border-radius: 10px; background: linear-gradient(135deg, #f8f9fa, #ffffff); box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <div style="display: flex; align-items: center; margin-bottom: 12px;">
                    <div style="width: 12px; height: 12px; background: #3498db; border-radius: 50%; margin-right: 10px;"></div>
                    <h4 style="color: var(--master-primary); margin: 0; font-size: 1.1rem;">Registros Recientes</h4>
                </div>
                <p style="color: #7f8c8d; margin-bottom: 10px; font-size: 0.9rem;"><?= $solicitudes['solicitudes_pendientes'] ?> nuevos usuarios esta semana</p>
                <div style="display: flex; align-items: center;">
                    <span style="width: 8px; height: 8px; background: <?= $solicitudes['solicitudes_pendientes'] > 0 ? '#e74c3c' : '#27ae60' ?>; border-radius: 50%; margin-right: 8px;"></span>
                    <small style="color: <?= $solicitudes['solicitudes_pendientes'] > 0 ? '#e74c3c' : '#27ae60' ?>; font-weight: 500;">
                        <?= $solicitudes['solicitudes_pendientes'] > 0 ? 'Requiere revisión' : 'Todo al día' ?>
                    </small>
                </div>
            </div>
            <div style="flex: 1; padding: 20px; border: 2px solid #e3f2fd; border-radius: 10px; background: linear-gradient(135deg, #f8f9fa, #ffffff); box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <div style="display: flex; align-items: center; margin-bottom: 12px;">
                    <div style="width: 12px; height: 12px; background: #f39c12; border-radius: 50%; margin-right: 10px;"></div>
                    <h4 style="color: var(--master-primary); margin: 0; font-size: 1.1rem;">Cursos en Revisión</h4>
                </div>
                <p style="color: #7f8c8d; margin-bottom: 10px; font-size: 0.9rem;"><?= $revision['cursos_revision'] ?> cursos pendientes de publicación</p>
                <div style="display: flex; align-items: center;">
                    <span style="width: 8px; height: 8px; background: <?= $revision['cursos_revision'] > 0 ? '#f39c12' : '#27ae60' ?>; border-radius: 50%; margin-right: 8px;"></span>
                    <small style="color: <?= $revision['cursos_revision'] > 0 ? '#f39c12' : '#27ae60' ?>; font-weight: 500;">
                        <?= $revision['cursos_revision'] > 0 ? 'En proceso' : 'Todo publicado' ?>
                    </small>
                </div>
            </div>
            <div style="flex: 1; padding: 20px; border: 2px solid #e3f2fd; border-radius: 10px; background: linear-gradient(135deg, #f8f9fa, #ffffff); box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <div style="display: flex; align-items: center; margin-bottom: 12px;">
                    <div style="width: 12px; height: 12px; background: #27ae60; border-radius: 50%; margin-right: 10px;"></div>
                    <h4 style="color: var(--master-primary); margin: 0; font-size: 1.1rem;">Estado del Sistema</h4>
                </div>
                <p style="color: #7f8c8d; margin-bottom: 10px; font-size: 0.9rem;">Funcionando correctamente</p>
                <div style="display: flex; align-items: center;">
                    <span style="width: 8px; height: 8px; background: #27ae60; border-radius: 50%; margin-right: 8px;"></span>
                    <small style="color: #27ae60; font-weight: 500;">Operativo</small>
                </div>
            </div>
        </div>

        <!-- Actividad Reciente -->
        <?php if (!empty($actividad_reciente)): ?>
        <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e8ecef;">
            <h3 style="color: var(--master-primary); margin-bottom: 15px;">Actividad Reciente</h3>
            <div style="display: grid; gap: 10px;">
                <?php foreach ($actividad_reciente as $actividad): ?>
                <div style="display: flex; align-items: center; gap: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                    <div style="width: 8px; height: 8px; background: #3498db; border-radius: 50%;"></div>
                    <div style="flex: 1;">
                        <strong><?= htmlspecialchars($actividad['nombre']) ?></strong> se registró en el sistema
                        <small style="color: #7f8c8d; margin-left: 10px;"><?= date('d/m/Y H:i', strtotime($actividad['fecha'])) ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
