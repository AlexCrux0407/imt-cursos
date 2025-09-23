<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
$page_title = 'Estudiante – Dashboard';
require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<div class="contenido">
    <!-- Header Principal -->
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; text-align: center;">
        <h1 style="font-size: 2.2rem; margin-bottom: 10px; font-weight: 600;">¡Bienvenido Estudiante!</h1>
        <p style="font-size: 1.1rem; opacity: 0.9;">Continúa tu aprendizaje y alcanza tus objetivos académicos</p>
    </div>

    <!-- Métricas de Progreso -->
    <div class="form-container-body" style="margin-bottom: 20px;">
        <div class="div-fila" style="gap: 20px;">
            <div style="background: #3498db; color: white; padding: 25px; border-radius: 12px; text-align: center; flex: 1;">
                <div style="font-size: 2.5rem; font-weight: bold; margin-bottom: 5px;">3</div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Cursos Activos</div>
            </div>
            <div style="background: #3498db; color: white; padding: 25px; border-radius: 12px; text-align: center; flex: 1;">
                <div style="font-size: 2.5rem; font-weight: bold; margin-bottom: 5px;">12</div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Completados</div>
            </div>
            <div style="background: #3498db; color: white; padding: 25px; border-radius: 12px; text-align: center; flex: 1;">
                <div style="font-size: 2.5rem; font-weight: bold; margin-bottom: 5px;">78%</div>
                <div style="font-size: 0.9rem; opacity: 0.9;">Progreso General</div>
            </div>
        </div>
    </div>

    <!-- Cursos en Progreso -->
    <div class="form-container-body" style="margin-bottom: 20px;">
        <h2 style="color: #3498db; font-size: 1.5rem; margin-bottom: 25px; border-bottom: 2px solid #e8ecef; padding-bottom: 15px;">
            Mis Cursos Activos
        </h2>
        <div class="div-fila" style="gap: 25px;">
            <div style="flex: 1; padding: 20px; border: 2px solid #e3f2fd; border-radius: 12px; background: white; transition: all 0.3s ease;"
                 onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 25px rgba(52,152,219,0.15)'"
                 onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <div class="div-fila-alt-start" style="margin-bottom: 15px;">
                    <div style="width: 40px; height: 40px; background: #3498db; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <img src="/imt-cursos/public/styles/iconos/desk.png" alt="Curso" style="width: 20px; height: 20px; filter: brightness(0) invert(1);">
                    </div>
                    <div>
                        <h3 style="color: #2c3e50; font-size: 1.2rem; margin-bottom: 5px;">Programación Básica</h3>
                        <p style="color: #7f8c8d; font-size: 0.9rem;">Progreso: 60%</p>
                    </div>
                </div>
                <p style="color: #5a5c69; margin-bottom: 15px;">
                    Aprende los fundamentos de la programación con ejercicios prácticos.
                </p>
                <div style="width: 100%; height: 6px; background: #e3f2fd; border-radius: 3px; margin-bottom: 15px;">
                    <div style="width: 60%; height: 100%; background: #3498db; border-radius: 3px;"></div>
                </div>
                <div class="div-fila-alt">
                    <a href="#" style="background: #3498db; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 0.9rem; font-weight: 500;"
                       onmouseover="this.style.background='#2980b9'"
                       onmouseout="this.style.background='#3498db'">
                        Continuar →
                    </a>
                </div>
            </div>

            <div style="flex: 1; padding: 20px; border: 2px solid #e3f2fd; border-radius: 12px; background: white; transition: all 0.3s ease;"
                 onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 25px rgba(52,152,219,0.15)'"
                 onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <div class="div-fila-alt-start" style="margin-bottom: 15px;">
                    <div style="width: 40px; height: 40px; background: #3498db; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <img src="/imt-cursos/public/styles/iconos/config.png" alt="Curso" style="width: 20px; height: 20px; filter: brightness(0) invert(1);">
                    </div>
                    <div>
                        <h3 style="color: #2c3e50; font-size: 1.2rem; margin-bottom: 5px;">Base de Datos</h3>
                        <p style="color: #7f8c8d; font-size: 0.9rem;">Progreso: 30%</p>
                    </div>
                </div>
                <p style="color: #5a5c69; margin-bottom: 15px;">
                    Diseño y gestión de bases de datos relacionales.
                </p>
                <div style="width: 100%; height: 6px; background: #e3f2fd; border-radius: 3px; margin-bottom: 15px;">
                    <div style="width: 30%; height: 100%; background: #3498db; border-radius: 3px;"></div>
                </div>
                <div class="div-fila-alt">
                    <a href="#" style="background: #3498db; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 0.9rem; font-weight: 500;"
                       onmouseover="this.style.background='#2980b9'"
                       onmouseout="this.style.background='#3498db'">
                        Continuar →
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Cursos Disponibles -->
    <div class="form-container-body" style="margin-bottom: 20px;">
        <h2 style="color: #3498db; font-size: 1.5rem; margin-bottom: 25px; border-bottom: 2px solid #e8ecef; padding-bottom: 15px;">
            Cursos Disponibles para Inscripción
        </h2>
        <div class="div-fila" style="gap: 20px;">
            <div style="flex: 1; padding: 20px; border: 2px solid #e8ecef; border-radius: 12px; background: #fafbfc; transition: all 0.3s ease;"
                 onmouseover="this.style.borderColor='#3498db'; this.style.background='white'"
                 onmouseout="this.style.borderColor='#e8ecef'; this.style.background='#fafbfc'">
                <h4 style="color: #2c3e50; margin-bottom: 10px;">Desarrollo Web Avanzado</h4>
                <p style="color: #7f8c8d; margin-bottom: 15px; font-size: 0.9rem;">Aprende frameworks modernos y mejores prácticas</p>
                <small style="color: #3498db; font-weight: 500;">Disponible para inscripción</small>
            </div>
            <div style="flex: 1; padding: 20px; border: 2px solid #e8ecef; border-radius: 12px; background: #fafbfc; transition: all 0.3s ease;"
                 onmouseover="this.style.borderColor='#3498db'; this.style.background='white'"
                 onmouseout="this.style.borderColor='#e8ecef'; this.style.background='#fafbfc'">
                <h4 style="color: #2c3e50; margin-bottom: 10px;">Inteligencia Artificial</h4>
                <p style="color: #7f8c8d; margin-bottom: 15px; font-size: 0.9rem;">Introducción al machine learning y redes neuronales</p>
                <small style="color: #3498db; font-weight: 500;">Próximamente</small>
            </div>
            <div style="flex: 1; padding: 20px; border: 2px solid #e8ecef; border-radius: 12px; background: #fafbfc; transition: all 0.3s ease;"
                 onmouseover="this.style.borderColor='#3498db'; this.style.background='white'"
                 onmouseout="this.style.borderColor='#e8ecef'; this.style.background='#fafbfc'">
                <h4 style="color: #2c3e50; margin-bottom: 10px;">Ciberseguridad</h4>
                <p style="color: #7f8c8d; margin-bottom: 15px; font-size: 0.9rem;">Protección de sistemas y análisis de vulnerabilidades</p>
                <small style="color: #3498db; font-weight: 500;">Inscripciones abiertas</small>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div style="margin-top: 30px; padding-top: 25px; border-top: 2px solid #e8ecef;">
            <h3 style="color: #3498db; margin-bottom: 20px;">Acciones Rápidas</h3>
            <div class="div-fila-alt-start" style="gap: 15px; flex-wrap: wrap;">
                <button style="background: #3498db; color: white; padding: 12px 20px; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px;"
                        onmouseover="this.style.background='#2980b9'; this.style.transform='translateY(-2px)'"
                        onmouseout="this.style.background='#3498db'; this.style.transform='translateY(0)'">
                    <img src="/imt-cursos/public/styles/iconos/desk.png" style="width: 16px; height: 16px; filter: brightness(0) invert(1);">
                    Ver Todos los Cursos
                </button>
                <button style="background: #3498db; color: white; padding: 12px 20px; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px;"
                        onmouseover="this.style.background='#2980b9'; this.style.transform='translateY(-2px)'"
                        onmouseout="this.style.background='#3498db'; this.style.transform='translateY(0)'">
                    <img src="/imt-cursos/public/styles/iconos/detalles.png" style="width: 16px; height: 16px; filter: brightness(0) invert(1);">
                    Mis Certificados
                </button>
                <button style="background: #3498db; color: white; padding: 12px 20px; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px;"
                        onmouseover="this.style.background='#2980b9'; this.style.transform='translateY(-2px)'"
                        onmouseout="this.style.background='#3498db'; this.style.transform='translateY(0)'">
                    <img src="/imt-cursos/public/styles/iconos/entrada.png" style="width: 16px; height: 16px; filter: brightness(0) invert(1);">
                    Mi Perfil
                </button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
