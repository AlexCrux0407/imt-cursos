<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('master');
$page_title = 'Master – Dashboard Administrativo';
require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="/imt-cursos/public/styles/css/master.css">

<div class="contenido">
    <!-- Header Principal -->
    <div class="form-container-head" style="background: linear-gradient(135deg, #0066cc, #004d99); color: white; text-align: center;">
        <h1 style="font-size: 2.5rem; margin-bottom: 10px; font-weight: 600;">Panel de Administración</h1>
        <p style="font-size: 1.1rem; opacity: 0.9;">Sistema de gestión integral IMT Cursos</p>
    </div>

    <!-- Métricas Principales -->
    <div class="form-container-body" style="margin-bottom: 20px;">
        <div class="div-fila" style="gap: 20px;">
            <div style="background: linear-gradient(135deg, #0066cc, #004d99); color: white; padding: 25px; border-radius: 12px; text-align: center; flex: 1;">
                <div style="font-size: 2.5rem; font-weight: bold; margin-bottom: 5px;">156</div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Total Usuarios</div>
            </div>
            <div style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 25px; border-radius: 12px; text-align: center; flex: 1;">
                <div style="font-size: 2.5rem; font-weight: bold; margin-bottom: 5px;">24</div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Docentes Activos</div>
            </div>
            <div style="background: linear-gradient(135deg, #5dade2, #3498db); color: white; padding: 25px; border-radius: 12px; text-align: center; flex: 1;">
                <div style="font-size: 2.5rem; font-weight: bold; margin-bottom: 5px;">132</div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Estudiantes</div>
            </div>
            <div style="background: linear-gradient(135deg, #85c1e9, #5dade2); color: white; padding: 25px; border-radius: 12px; text-align: center; flex: 1;">
                <div style="font-size: 2.5rem; font-weight: bold; margin-bottom: 5px;">45</div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Cursos Activos</div>
            </div>
        </div>
    </div>

    <!-- Módulos de Administración -->
    <div class="div-fila" style="gap: 25px; margin-bottom: 30px;">
        <!-- Gestión de Estudiantes -->
        <div class="form-container-body" style="flex: 1; transition: all 0.3s ease; cursor: pointer;" 
             onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 25px rgba(0,102,204,0.15)'"
             onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='4px 4px 10px rgba(0, 0, 0, 0.3)'">
            <div class="div-fila-alt-start" style="margin-bottom: 20px;">
                <div style="width: 50px; height: 50px; background: #0066cc; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <img src="/imt-cursos/public/styles/iconos/addicon.png" alt="Estudiantes" style="width: 24px; height: 24px; filter: brightness(0) invert(1);">
                </div>
                <div>
                    <h3 style="color: #0066cc; font-size: 1.3rem; margin-bottom: 5px;">Gestión de Estudiantes</h3>
                    <p style="color: #7f8c8d; font-size: 0.9rem;">Administrar inscripciones y progreso</p>
                </div>
            </div>
            <p style="color: #5a5c69; margin-bottom: 20px; line-height: 1.5;">
                Gestiona estudiantes registrados, revisa progreso académico y administra inscripciones a cursos.
            </p>
            <div class="div-fila-alt">
                <a href="/imt-cursos/public/master/admin_estudiantes.php" 
                   style="background: #0066cc; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease;"
                   onmouseover="this.style.background='#004d99'"
                   onmouseout="this.style.background='#0066cc'">
                    Administrar →
                </a>
            </div>
        </div>

        <!-- Gestión de Docentes -->
        <div class="form-container-body" style="flex: 1; transition: all 0.3s ease; cursor: pointer;"
             onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 25px rgba(52,152,219,0.15)'"
             onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='4px 4px 10px rgba(0, 0, 0, 0.3)'">
            <div class="div-fila-alt-start" style="margin-bottom: 20px;">
                <div style="width: 50px; height: 50px; background: #3498db; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <img src="/imt-cursos/public/styles/iconos/edit.png" alt="Docentes" style="width: 24px; height: 24px; filter: brightness(0) invert(1);">
                </div>
                <div>
                    <h3 style="color: #3498db; font-size: 1.3rem; margin-bottom: 5px;">Gestión de Docentes</h3>
                    <p style="color: #7f8c8d; font-size: 0.9rem;">Administrar personal educativo</p>
                </div>
            </div>
            <p style="color: #5a5c69; margin-bottom: 20px; line-height: 1.5;">
                Administra personal docente, asigna cursos y supervisa la actividad educativa del instituto.
            </p>
            <div class="div-fila-alt">
                <a href="/imt-cursos/public/master/admin_docentes.php" 
                   style="background: #3498db; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease;"
                   onmouseover="this.style.background='#2980b9'"
                   onmouseout="this.style.background='#3498db'">
                    Administrar →
                </a>
            </div>
        </div>
    </div>

    <div class="div-fila" style="gap: 25px; margin-bottom: 30px;">
        <!-- Gestión de Cursos -->
        <div class="form-container-body" style="flex: 1; transition: all 0.3s ease; cursor: pointer;"
             onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 25px rgba(93,173,226,0.15)'"
             onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='4px 4px 10px rgba(0, 0, 0, 0.3)'">
            <div class="div-fila-alt-start" style="margin-bottom: 20px;">
                <div style="width: 50px; height: 50px; background: #5dade2; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <img src="/imt-cursos/public/styles/iconos/desk.png" alt="Cursos" style="width: 24px; height: 24px; filter: brightness(0) invert(1);">
                </div>
                <div>
                    <h3 style="color: #5dade2; font-size: 1.3rem; margin-bottom: 5px;">Gestión de Cursos</h3>
                    <p style="color: #7f8c8d; font-size: 0.9rem;">Catálogo y contenido educativo</p>
                </div>
            </div>
            <p style="color: #5a5c69; margin-bottom: 20px; line-height: 1.5;">
                Controla el catálogo de cursos, aprueba contenido y gestiona certificaciones académicas.
            </p>
            <div class="div-fila-alt">
                <a href="/imt-cursos/public/master/admin_cursos.php" 
                   style="background: #5dade2; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease;"
                   onmouseover="this.style.background='#3498db'"
                   onmouseout="this.style.background='#5dade2'">
                    Administrar →
                </a>
            </div>
        </div>

        <!-- Configuración del Sistema -->
        <div class="form-container-body" style="flex: 1; transition: all 0.3s ease; cursor: pointer;"
             onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 25px rgba(133,193,233,0.15)'"
             onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='4px 4px 10px rgba(0, 0, 0, 0.3)'">
            <div class="div-fila-alt-start" style="margin-bottom: 20px;">
                <div style="width: 50px; height: 50px; background: #85c1e9; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                    <img src="/imt-cursos/public/styles/iconos/config.png" alt="Sistema" style="width: 24px; height: 24px; filter: brightness(0) invert(1);">
                </div>
                <div>
                    <h3 style="color: #85c1e9; font-size: 1.3rem; margin-bottom: 5px;">Configuración</h3>
                    <p style="color: #7f8c8d; font-size: 0.9rem;">Parámetros del sistema</p>
                </div>
            </div>
            <p style="color: #5a5c69; margin-bottom: 20px; line-height: 1.5;">
                Administra configuraciones globales, parámetros del sistema y mantenimiento general.
            </p>
            <div class="div-fila-alt">
                <a href="/imt-cursos/public/master/admin_plataforma.php" 
                   style="background: #85c1e9; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease;"
                   onmouseover="this.style.background='#5dade2'"
                   onmouseout="this.style.background='#85c1e9'">
                    Configurar →
                </a>
            </div>
        </div>
    </div>

    <!-- Panel de Estado del Sistema -->
    <div class="form-container-body">
        <h2 style="color: #0066cc; font-size: 1.5rem; margin-bottom: 25px; border-bottom: 2px solid #e8ecef; padding-bottom: 15px;">
            Estado del Sistema
        </h2>
        <div class="div-fila" style="gap: 20px;">
            <div style="flex: 1; padding: 20px; border: 2px solid #e3f2fd; border-radius: 10px; background: #fafbfc; transition: all 0.3s ease;"
                 onmouseover="this.style.borderColor='#0066cc'; this.style.background='white'"
                 onmouseout="this.style.borderColor='#e3f2fd'; this.style.background='#fafbfc'">
                <h4 style="color: #0066cc; margin-bottom: 8px;">Solicitudes Pendientes</h4>
                <p style="color: #7f8c8d; margin-bottom: 10px; font-size: 0.9rem;">3 registros esperando aprobación</p>
                <small style="color: #e74c3c; font-weight: 500;">Requiere atención</small>
            </div>
            <div style="flex: 1; padding: 20px; border: 2px solid #e3f2fd; border-radius: 10px; background: #fafbfc; transition: all 0.3s ease;"
                 onmouseover="this.style.borderColor='#3498db'; this.style.background='white'"
                 onmouseout="this.style.borderColor='#e3f2fd'; this.style.background='#fafbfc'">
                <h4 style="color: #3498db; margin-bottom: 8px;">Cursos en Revisión</h4>
                <p style="color: #7f8c8d; margin-bottom: 10px; font-size: 0.9rem;">2 cursos pendientes de publicación</p>
                <small style="color: #f39c12; font-weight: 500;">En proceso</small>
            </div>
            <div style="flex: 1; padding: 20px; border: 2px solid #e3f2fd; border-radius: 10px; background: #fafbfc; transition: all 0.3s ease;"
                 onmouseover="this.style.borderColor='#5dade2'; this.style.background='white'"
                 onmouseout="this.style.borderColor='#e3f2fd'; this.style.background='#fafbfc'">
                <h4 style="color: #5dade2; margin-bottom: 8px;">Último Respaldo</h4>
                <p style="color: #7f8c8d; margin-bottom: 10px; font-size: 0.9rem;">Realizado hace 2 días</p>
                <small style="color: #27ae60; font-weight: 500;">Actualizado</small>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div style="margin-top: 30px; padding-top: 25px; border-top: 2px solid #e8ecef;">
            <h3 style="color: #0066cc; margin-bottom: 20px;">Acciones Rápidas</h3>
            <div class="div-fila-alt-start" style="gap: 15px; flex-wrap: wrap;">
                <button style="background: #0066cc; color: white; padding: 12px 20px; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px;"
                        onmouseover="this.style.background='#004d99'; this.style.transform='translateY(-2px)'"
                        onmouseout="this.style.background='#0066cc'; this.style.transform='translateY(0)'">
                    <img src="/imt-cursos/public/styles/iconos/addicon.png" style="width: 16px; height: 16px; filter: brightness(0) invert(1);">
                    Crear Usuario
                </button>
                <button style="background: #3498db; color: white; padding: 12px 20px; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px;"
                        onmouseover="this.style.background='#2980b9'; this.style.transform='translateY(-2px)'"
                        onmouseout="this.style.background='#3498db'; this.style.transform='translateY(0)'">
                    <img src="/imt-cursos/public/styles/iconos/detalles.png" style="width: 16px; height: 16px; filter: brightness(0) invert(1);">
                    Ver Reportes
                </button>
                <button style="background: #5dade2; color: white; padding: 12px 20px; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px;"
                        onmouseover="this.style.background='#3498db'; this.style.transform='translateY(-2px)'"
                        onmouseout="this.style.background='#5dade2'; this.style.transform='translateY(0)'">
                    <img src="/imt-cursos/public/styles/iconos/config.png" style="width: 16px; height: 16px; filter: brightness(0) invert(1);">
                    Configurar
                </button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
