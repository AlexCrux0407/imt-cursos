<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Administración de Cursos';

// Verificar si las nuevas columnas existen
$stmt = $conn->prepare("SHOW COLUMNS FROM cursos LIKE 'asignado_a'");
$stmt->execute();
$nuevas_columnas_existen = $stmt->fetch();

if ($nuevas_columnas_existen) {
    // Sistema nuevo: cursos asignados al docente
    $stmt = $conn->prepare("
        SELECT c.*, 
               u.nombre as creado_por_nombre,
               COUNT(i.id) as total_inscritos,
               AVG(COALESCE(i.progreso, 0)) as promedio_progreso
        FROM cursos c 
        INNER JOIN usuarios u ON c.creado_por = u.id
        LEFT JOIN inscripciones i ON c.id = i.curso_id 
        WHERE c.asignado_a = :docente_id 
        GROUP BY c.id 
        ORDER BY c.fecha_asignacion DESC
    ");
    $stmt->execute([':docente_id' => $_SESSION['user_id']]);
} else {
    // Sistema anterior: cursos creados por el docente
    $stmt = $conn->prepare("
        SELECT c.*, 
               COUNT(i.id) as total_inscritos,
               AVG(COALESCE(i.progreso, 0)) as promedio_progreso
        FROM cursos c 
        LEFT JOIN inscripciones i ON c.id = i.curso_id 
        WHERE c.creado_por = :docente_id 
        GROUP BY c.id 
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([':docente_id' => $_SESSION['user_id']]);
}

$cursos = $stmt->fetchAll();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/docente.css">

<style>
/* Animaciones de entrada para admin cursos */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Aplicar animaciones */
.form-container-head {
    animation: fadeInUp 0.8s ease-out;
}

.stats-overview {
    animation: fadeInUp 1s ease-out 0.2s both;
}

.form-container-body {
    animation: fadeInUp 1.2s ease-out 0.4s both;
}

.curso-card {
    animation: scaleIn 0.6s ease-out both;
}

.curso-card:nth-child(1) { animation-delay: 0.6s; }
.curso-card:nth-child(2) { animation-delay: 0.7s; }
.curso-card:nth-child(3) { animation-delay: 0.8s; }
.curso-card:nth-child(4) { animation-delay: 0.9s; }

.empty-state {
    animation: fadeInUp 1s ease-out 0.6s both;
}

/* Animación para las acciones de los cursos */
.curso-actions button {
    transition: all 0.3s ease;
}

.curso-actions button:hover {
    transform: translateY(-2px);
}
</style>

<div class="contenido">
    <?php if (isset($_GET['success']) || isset($_GET['error'])): ?>
        <div style="margin-bottom: 20px;">
            <?php if (isset($_GET['success'])): ?>
                <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; border: 1px solid #c3e6cb;">
                    <?php
                    switch($_GET['success']) {
                        case 'curso_creado':
                            echo '✅ Curso creado exitosamente.';
                            break;
                        case 'curso_eliminado':
                            echo '✅ Curso eliminado exitosamente.';
                            break;
                        default:
                            echo '✅ Operación realizada exitosamente.';
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; border: 1px solid #f5c6cb;">
                    <?php
                    $error = $_GET['error'];
                    switch($error) {
                        case 'curso_no_encontrado':
                            echo '❌ Curso no encontrado.';
                            break;
                        case 'acceso_denegado':
                            echo '❌ No tienes permisos para realizar esta acción.';
                            break;
                        case 'error_eliminar':
                            echo '❌ Error al eliminar el curso. Inténtalo nuevamente.';
                            break;
                        case 'error_crear':
                            echo '❌ Error al crear el curso. Inténtalo nuevamente.';
                            break;
                        case 'database_error':
                            echo '❌ Error de base de datos.';
                            break;
                        case 'execute_failed':
                            echo '❌ La consulta no se ejecutó correctamente.';
                            break;
                        case 'method_not_allowed':
                            echo '❌ Método de solicitud no permitido.';
                            break;
                        case 'titulo_requerido':
                            echo '❌ El título del curso es requerido.';
                            break;
                        default:
                            echo '❌ Error: ' . htmlspecialchars($error);
                    }
                    if (isset($_GET['details'])) {
                        echo '<br><small style="font-family: monospace; background: #f8f9fa; padding: 5px; border-radius: 3px;">Detalles: ' . htmlspecialchars($_GET['details']) . '</small>';
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white;">
        <div class="div-fila-alt-start">
            <div>
                <h1 style="font-size: 2rem; margin-bottom: 10px;">
                    <?= $nuevas_columnas_existen ? 'Cursos Asignados' : 'Administración de Cursos' ?>
                </h1>
                <p style="opacity: 0.9;">
                    <?= $nuevas_columnas_existen ? 'Gestiona el contenido de los cursos asignados por el master' : 'Gestiona tus cursos, contenido y seguimiento de estudiantes' ?>
                </p>
            </div>
            <?php if (empty($cursos)): ?>
                <div style="background: rgba(255,255,255,0.2); color: white; padding: 12px 20px; border-radius: 8px; font-size: 0.9rem;">
                    Sin cursos asignados
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lista de Cursos -->
    <div class="form-container-body">
        <h2 style="color: #3498db; margin-bottom: 20px; font-size: 1.5rem;">
            <?= $nuevas_columnas_existen ? 'Mis Cursos Asignados' : 'Mis Cursos' ?>
        </h2>
        
        <?php if (empty($cursos)): ?>
            <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                <img src="<?= BASE_URL ?>/styles/iconos/desk.png" style="width: 64px; height: 64px; opacity: 0.5; margin-bottom: 20px;">
                <h3><?= $nuevas_columnas_existen ? 'No tienes cursos asignados' : 'No tienes cursos creados' ?></h3>
                <p><?= $nuevas_columnas_existen ? 'El master te asignará cursos para que puedas desarrollar el contenido' : 'Comienza creando tu primer curso' ?></p>
                <?php if (!$nuevas_columnas_existen): ?>
                    <button onclick="mostrarFormularioNuevoCurso()" 
                            style="background: #3498db; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; margin-top: 15px;">
                        Crear Primer Curso
                    </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="display: grid; gap: 20px;">
                <?php foreach ($cursos as $curso): ?>
                    <div class="curso-card" style="border: 2px solid #e8ecef; border-radius: 12px; padding: 20px; background: white; transition: all 0.3s ease;">
                        
                        <div class="div-fila" style="gap: 20px;">
                            <!-- Información del Curso -->
                            <div style="flex: 2;">
                                <div class="div-fila-alt-start" style="margin-bottom: 15px;">
                                    <div style="width: 50px; height: 50px; background: #3498db; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                        <img src="<?= BASE_URL ?>/styles/iconos/desk.png" style="width: 24px; height: 24px; filter: brightness(0) invert(1);">
                                    </div>
                                    <div>
                                        <h3 style="color: #2c3e50; margin-bottom: 5px;"><?= htmlspecialchars($curso['titulo']) ?></h3>
                                        <div class="div-fila-alt-start" style="gap: 15px;">
                                            <?php if ($nuevas_columnas_existen && isset($curso['estado_asignacion'])): ?>
                                                <span style="background: 
                                                    <?php 
                                                    echo match($curso['estado_asignacion']) {
                                                        'pendiente' => '#f39c12',
                                                        'en_desarrollo' => '#3498db', 
                                                        'completado' => '#27ae60',
                                                        default => '#95a5a6'
                                                    };
                                                    ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">
                                                    <?= ucfirst(str_replace('_', ' ', $curso['estado_asignacion'])) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="background: <?= $curso['estado'] === 'activo' ? '#27ae60' : ($curso['estado'] === 'borrador' ? '#f39c12' : '#e74c3c') ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">
                                                    <?= ucfirst($curso['estado']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <span style="color: #7f8c8d; font-size: 0.9rem;">
                                                <?= $nuevas_columnas_existen && isset($curso['fecha_asignacion']) ? 
                                                    'Asignado: ' . date('d/m/Y', strtotime($curso['fecha_asignacion'])) :
                                                    'Creado: ' . date('d/m/Y', strtotime($curso['created_at'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <p style="color: #5a5c69; margin-bottom: 15px; line-height: 1.5;">
                                    <?= htmlspecialchars(substr($curso['descripcion'], 0, 150)) ?><?= strlen($curso['descripcion']) > 150 ? '...' : '' ?>
                                </p>
                                
                                <?php if ($nuevas_columnas_existen && isset($curso['creado_por_nombre'])): ?>
                                    <div style="color: #7f8c8d; margin-bottom: 10px; font-size: 0.9rem;">
                                        <strong>Creado por:</strong> <?= htmlspecialchars($curso['creado_por_nombre']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="div-fila-alt-start" style="gap: 20px;">
                                    <div>
                                        <strong style="color: #3498db;"><?= $curso['total_inscritos'] ?></strong>
                                        <small style="color: #7f8c8d;">Estudiantes</small>
                                    </div>
                                    <div>
                                        <strong style="color: #3498db;"><?= number_format($curso['promedio_progreso'] ?? 0, 1) ?>%</strong>
                                        <small style="color: #7f8c8d;">Progreso Prom.</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Acciones -->
                            <div style="flex: 1; display: flex; flex-direction: column; gap: 10px;">
                                <a href="<?= BASE_URL ?>/docente/visualizar_curso.php?id=<?= $curso['id'] ?>" 
                                   style="background: #3498db; color: white; padding: 10px 16px; border-radius: 6px; text-decoration: none; text-align: center; font-weight: 500;">
                                    Ver Detalles
                                </a>
                                
                                <?php if ($nuevas_columnas_existen): ?>
                                    <button onclick="gestionarModulos(<?= $curso['id'] ?>)" 
                                            style="background: transparent; color: #3498db; border: 2px solid #3498db; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 500;">
                                        Gestionar Contenido
                                    </button>
                                <?php else: ?>
                                    <button onclick="editarCurso(<?= $curso['id'] ?>)" 
                                            style="background: transparent; color: #3498db; border: 2px solid #3498db; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 500;">
                                        Editar
                                    </button>
                                    
                                    <button onclick="gestionarModulos(<?= $curso['id'] ?>)" 
                                            style="background: transparent; color: #7f8c8d; border: 2px solid #e8ecef; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 500;">
                                        Módulos
                                    </button>
                                    
                                    <button onclick="confirmarEliminar(<?= $curso['id'] ?>, '<?= addslashes($curso['titulo']) ?>')" 
                                            style="background: transparent; color: #e74c3c; border: 2px solid #e74c3c; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 500;">
                                        Eliminar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$nuevas_columnas_existen): ?>
    <!-- Modal para Nuevo Curso -->
    <div id="modalNuevoCurso" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; padding: 30px; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto;">
            <div class="div-fila" style="justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="color: #3498db; margin: 0;">Crear Nuevo Curso</h2>
                <button onclick="cerrarModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #7f8c8d;">&times;</button>
            </div>
            
            <form method="POST" action="<?= BASE_URL ?>/docente/procesar_curso.php">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Título del Curso</label>
                    <input type="text" name="titulo" required 
                           style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; transition: border-color 0.3s ease;"
                           onfocus="this.style.borderColor='#3498db'" 
                           onblur="this.style.borderColor='#e8ecef'">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Descripción</label>
                    <textarea name="descripcion" rows="3" 
                              style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; resize: vertical; transition: border-color 0.3s ease;"
                              onfocus="this.style.borderColor='#3498db'" 
                              onblur="this.style.borderColor='#e8ecef'"></textarea>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Objetivo General</label>
                    <textarea name="objetivo_general" rows="3" 
                              style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; resize: vertical; transition: border-color 0.3s ease;"
                              placeholder="¿Cuál es el objetivo principal que se espera lograr con este curso?"
                              onfocus="this.style.borderColor='#3498db'" 
                              onblur="this.style.borderColor='#e8ecef'"></textarea>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Objetivos Específicos</label>
                    <textarea name="objetivos_especificos" rows="4" 
                              style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; resize: vertical; transition: border-color 0.3s ease;"
                              placeholder="Lista los objetivos específicos que se alcanzarán (uno por línea)&#10;• Objetivo 1&#10;• Objetivo 2&#10;• Objetivo 3"
                              onfocus="this.style.borderColor='#3498db'" 
                              onblur="this.style.borderColor='#e8ecef'"></textarea>
                </div>
                
                <div class="div-fila" style="gap: 20px; margin-bottom: 20px;">
                    <div style="flex: 1;">
                        <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Duración</label>
                        <input type="text" name="duracion" 
                               style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; transition: border-color 0.3s ease;"
                               placeholder="Ej: 40 horas, 8 semanas, 3 meses"
                               onfocus="this.style.borderColor='#3498db'" 
                               onblur="this.style.borderColor='#e8ecef'">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Categoría</label>
                        <input type="text" name="categoria" 
                               style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; transition: border-color 0.3s ease;"
                               onfocus="this.style.borderColor='#3498db'" 
                               onblur="this.style.borderColor='#e8ecef'">
                    </div>
                </div>
                
                <div class="div-fila" style="gap: 20px; margin-bottom: 20px;">
                    <div style="flex: 1;">
                        <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Dirigido a</label>
                        <input type="text" name="dirigido_a" 
                               style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; transition: border-color 0.3s ease;"
                               placeholder="Ej: Personal de TI, practicantes"
                               onfocus="this.style.borderColor='#3498db'" 
                               onblur="this.style.borderColor='#e8ecef'">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Estado</label>
                        <select name="estado" 
                                style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; transition: border-color 0.3s ease;"
                                onfocus="this.style.borderColor='#3498db'" 
                                onblur="this.style.borderColor='#e8ecef'">
                            <option value="borrador">Borrador</option>
                            <option value="activo">Activo</option>
                        </select>
                    </div>
                </div>
                
                <div class="div-fila-alt" style="gap: 15px;">
                    <button type="button" onclick="cerrarModal()" 
                            style="background: #e8ecef; color: #5a5c69; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                        Cancelar
                    </button>
                    <button type="submit" 
                            style="background: #3498db; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 500; transition: all 0.3s ease;"
                            onmouseover="this.style.background='#2980b9'"
                            onmouseout="this.style.background='#3498db'">
                        Crear Curso
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
function mostrarFormularioNuevoCurso() {
    document.getElementById('modalNuevoCurso').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalNuevoCurso').style.display = 'none';
}

function editarCurso(id) {
    window.location.href = `<?= BASE_URL ?>/docente/editar_curso.php?id=${id}`;
}

function gestionarModulos(id) {
    window.location.href = `<?= BASE_URL ?>/docente/modulos_curso.php?id=${id}`;
}

function confirmarEliminar(id, titulo) {
    if (confirm(`¿Estás seguro de que deseas eliminar el curso "${titulo}"?\n\nEsta acción eliminará permanentemente:\n- El curso y toda su información\n- Todos los módulos y lecciones\n- Las inscripciones de estudiantes\n- Los archivos asociados\n\nEsta acción NO se puede deshacer.`)) {
        window.location.href = `<?= BASE_URL ?>/docente/eliminar_curso.php?id=${id}`;
    }
}

function marcarEnDesarrollo(id) {
    // Lógica para marcar el curso como "en desarrollo"
    fetch(`<?= BASE_URL ?>/docente/cambiar_estado_curso.php?id=${id}&estado=en_desarrollo`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar la interfaz
            location.reload();
        } else {
            alert('Error al cambiar el estado del curso. Inténtalo nuevamente.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al cambiar el estado del curso. Inténtalo nuevamente.');
    });
}

function marcarCompletado(id) {
    // Lógica para marcar el curso como "completado"
    fetch(`<?= BASE_URL ?>/docente/cambiar_estado_curso.php?id=${id}&estado=completado`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar la interfaz
            location.reload();
        } else {
            alert('Error al cambiar el estado del curso. Inténtalo nuevamente.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al cambiar el estado del curso. Inténtalo nuevamente.');
    });
}

// Cerrar modal al hacer clic fuera
document.getElementById('modalNuevoCurso').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModal();
    }
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
