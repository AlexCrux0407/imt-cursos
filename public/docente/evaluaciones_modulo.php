<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Evaluaciones del Módulo';

$modulo_id = $_GET['id'] ?? 0;
$curso_id = $_GET['curso_id'] ?? 0;

// Verificar que el módulo pertenece a un curso del docente
$stmt = $conn->prepare("
    SELECT m.*, c.titulo as curso_titulo
    FROM modulos m
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE m.id = :modulo_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
");
$stmt->execute([
    ':modulo_id' => $modulo_id, 
    ':docente_id' => $_SESSION['user_id'],
    ':docente_id2' => $_SESSION['user_id']
]);
$modulo = $stmt->fetch();

if (!$modulo) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=modulo_no_encontrado');
    exit;
}

// Obtener evaluaciones del módulo
$stmt = $conn->prepare("
    SELECT e.*, 
           COUNT(p.id) as total_preguntas,
           COUNT(DISTINCT i.usuario_id) as estudiantes_intentaron,
           AVG(i.puntaje_obtenido) as promedio_puntaje
    FROM evaluaciones_modulo e
    LEFT JOIN preguntas_evaluacion p ON e.id = p.evaluacion_id
    LEFT JOIN intentos_evaluacion i ON e.id = i.evaluacion_id AND i.estado = 'completado'
    WHERE e.modulo_id = :modulo_id
    GROUP BY e.id
    ORDER BY e.orden ASC, e.fecha_creacion DESC
");
$stmt->execute([':modulo_id' => $modulo_id]);
$evaluaciones = $stmt->fetchAll();

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/docente.css">

<style>
.evaluacion-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-left: 4px solid #3498db;
    transition: all 0.3s ease;
}

.evaluacion-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.evaluacion-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.evaluacion-info h3 {
    color: #2c3e50;
    margin: 0 0 8px 0;
    font-size: 1.3rem;
}

.evaluacion-tipo {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    text-transform: uppercase;
}

.tipo-examen { background: #e8f4fd; color: #2980b9; }
.tipo-tarea { background: #e8f5e8; color: #27ae60; }
.tipo-proyecto { background: #fef9e7; color: #f39c12; }
.tipo-quiz { background: #fdeaea; color: #e74c3c; }

.evaluacion-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 16px;
    margin: 16px 0;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 8px;
}

.stat-item {
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: bold;
    color: #2c3e50;
}

.stat-label {
    font-size: 0.85rem;
    color: #7f8c8d;
    margin-top: 4px;
}

.evaluacion-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-action {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s ease;
}

.btn-primary { background: #3498db; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-warning { background: #f39c12; color: white; }
.btn-danger { background: #e74c3c; color: white; }
.btn-secondary { background: #95a5a6; color: white; }

.btn-action:hover {
    transform: translateY(-1px);
    opacity: 0.9;
}

.estado-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}

.estado-activo { background: #d4edda; color: #155724; }
.estado-inactivo { background: #f8d7da; color: #721c24; }

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #7f8c8d;
}

.empty-state img {
    width: 120px;
    opacity: 0.5;
    margin-bottom: 20px;
}
</style>

<div class="contenido">
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white;">
        <div class="div-fila-alt-start">
            <div>
                <h1 style="font-size: 2rem; margin-bottom: 10px;">Evaluaciones del Módulo</h1>
                <p style="opacity: 0.9;"><?= htmlspecialchars($modulo['titulo']) ?> - <?= htmlspecialchars($modulo['curso_titulo']) ?></p>
            </div>
            <div style="display: flex; gap: 12px;">
                <button onclick="window.location.href='<?= BASE_URL ?>/docente/modulos_curso.php?id=<?= $curso_id ?>'" 
                        style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    ← Volver a Módulos
                </button>
                <button onclick="mostrarFormularioNuevaEvaluacion()" 
                        style="background: white; color: #3498db; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    + Nueva Evaluación
                </button>
            </div>
        </div>
    </div>

    <div class="form-container-body">
        <?php if (empty($evaluaciones)): ?>
            <div class="empty-state">
                <img src="<?= BASE_URL ?>/styles/iconos/evaluacion.png" alt="Sin evaluaciones">
                <h3>No hay evaluaciones creadas</h3>
                <p>Crea la primera evaluación para este módulo para que los estudiantes puedan completar su progreso.</p>
                <button onclick="mostrarFormularioNuevaEvaluacion()" class="btn-action btn-primary" style="margin-top: 20px;">
                    Crear Primera Evaluación
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($evaluaciones as $evaluacion): ?>
                <div class="evaluacion-card">
                    <div class="evaluacion-header">
                        <div class="evaluacion-info">
                            <h3><?= htmlspecialchars($evaluacion['titulo']) ?></h3>
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                <span class="evaluacion-tipo tipo-<?= $evaluacion['tipo'] ?>">
                                    <?= ucfirst($evaluacion['tipo']) ?>
                                </span>
                                <span class="estado-badge <?= $evaluacion['activo'] ? 'estado-activo' : 'estado-inactivo' ?>">
                                    <?= $evaluacion['activo'] ? 'Activa' : 'Inactiva' ?>
                                </span>
                                <?php if ($evaluacion['obligatorio']): ?>
                                    <span style="color: #e74c3c; font-weight: 500; font-size: 0.9rem;">● Obligatoria</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($evaluacion['descripcion']): ?>
                                <p style="color: #7f8c8d; margin: 8px 0;"><?= htmlspecialchars($evaluacion['descripcion']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="evaluacion-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?= $evaluacion['total_preguntas'] ?></span>
                            <div class="stat-label">Preguntas</div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?= $evaluacion['puntaje_maximo'] ?>pts</span>
                            <div class="stat-label">Puntaje Máximo</div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?= $evaluacion['puntaje_minimo_aprobacion'] ?>pts</span>
                            <div class="stat-label">Mínimo Aprobación</div>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?= $evaluacion['estudiantes_intentaron'] ?></span>
                            <div class="stat-label">Estudiantes</div>
                        </div>
                        <?php if ($evaluacion['promedio_puntaje']): ?>
                        <div class="stat-item">
                            <span class="stat-value"><?= number_format($evaluacion['promedio_puntaje'], 1) ?>pts</span>
                            <div class="stat-label">Promedio</div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="evaluacion-actions">
                        <a href="<?= BASE_URL ?>/docente/preguntas_evaluacion.php?id=<?= $evaluacion['id'] ?>&modulo_id=<?= $modulo_id ?>&curso_id=<?= $curso_id ?>" 
                           class="btn-action btn-primary">
                            📝 Gestionar Preguntas
                        </a>
                        <a href="<?= BASE_URL ?>/docente/resultados_evaluacion.php?id=<?= $evaluacion['id'] ?>&modulo_id=<?= $modulo_id ?>&curso_id=<?= $curso_id ?>" 
                           class="btn-action btn-success">
                            📊 Ver Resultados
                        </a>
                        <a href="<?= BASE_URL ?>/docente/editar_evaluacion.php?id=<?= $evaluacion['id'] ?>&modulo_id=<?= $modulo_id ?>&curso_id=<?= $curso_id ?>" 
                           class="btn-action btn-warning">
                            ✏️ Editar
                        </a>
                        <?php if ($evaluacion['activo']): ?>
                            <button onclick="cambiarEstadoEvaluacion(<?= $evaluacion['id'] ?>, false)" class="btn-action btn-secondary">
                                ⏸️ Desactivar
                            </button>
                        <?php else: ?>
                            <button onclick="cambiarEstadoEvaluacion(<?= $evaluacion['id'] ?>, true)" class="btn-action btn-success">
                                ▶️ Activar
                            </button>
                        <?php endif; ?>
                        <button onclick="confirmarEliminarEvaluacion(<?= $evaluacion['id'] ?>, '<?= htmlspecialchars($evaluacion['titulo']) ?>')" 
                                class="btn-action btn-danger">
                            🗑️ Eliminar
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nueva Evaluación -->
<div id="modalNuevaEvaluacion" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <h2 style="color: #2c3e50; margin-bottom: 25px;">Nueva Evaluación</h2>
        
        <form action="<?= BASE_URL ?>/docente/procesar_evaluacion.php" method="POST">
            <input type="hidden" name="modulo_id" value="<?= $modulo_id ?>">
            <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Título *</label>
                <input type="text" name="titulo" required
                       style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Descripción</label>
                <textarea name="descripcion" rows="3"
                          style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; resize: vertical;"></textarea>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Tipo *</label>
                    <select name="tipo" required
                            style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
                        <option value="examen">Examen</option>
                        <option value="quiz">Quiz</option>
                        <option value="tarea">Tarea</option>
                        <option value="proyecto">Proyecto</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Orden</label>
                    <input type="number" name="orden" value="<?= count($evaluaciones) + 1 ?>" min="1"
                           style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Puntaje Máximo</label>
                    <input type="number" name="puntaje_maximo" value="100" min="1" step="0.01"
                           style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
                </div>
                <div>
                    <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Puntaje Mínimo (Aprobación)</label>
                    <input type="number" name="puntaje_minimo_aprobacion" value="70" min="1" step="0.01"
                           style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Tiempo Límite (minutos)</label>
                    <input type="number" name="tiempo_limite" min="1" placeholder="Sin límite"
                           style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
                </div>
                <div>
                    <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Intentos Permitidos</label>
                    <input type="number" name="intentos_permitidos" value="1" min="1"
                           style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Instrucciones</label>
                <textarea name="instrucciones" rows="4" placeholder="Instrucciones para el estudiante..."
                          style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; resize: vertical;"></textarea>
            </div>
            
            <div style="margin-bottom: 30px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="obligatorio" value="1" checked>
                    <span style="color: #2c3e50; font-weight: 500;">Evaluación obligatoria para completar el módulo</span>
                </label>
            </div>
            
            <div class="div-fila-alt" style="gap: 15px;">
                <button type="button" onclick="cerrarModal()" 
                        style="background: #e8ecef; color: #5a5c69; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    Cancelar
                </button>
                <button type="submit" 
                        style="background: #3498db; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    Crear Evaluación
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function mostrarFormularioNuevaEvaluacion() {
    document.getElementById('modalNuevaEvaluacion').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalNuevaEvaluacion').style.display = 'none';
}

function cambiarEstadoEvaluacion(id, activo) {
    const accion = activo ? 'activar' : 'desactivar';
    if (confirm(`¿Estás seguro de que deseas ${accion} esta evaluación?`)) {
        fetch(`<?= BASE_URL ?>/docente/cambiar_estado_evaluacion.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                evaluacion_id: id,
                activo: activo
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error al cambiar el estado de la evaluación');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cambiar el estado de la evaluación');
        });
    }
}

function confirmarEliminarEvaluacion(id, titulo) {
    if (confirm(`¿Estás seguro de que deseas eliminar la evaluación "${titulo}"?\n\nEsta acción eliminará:\n- La evaluación y todas sus preguntas\n- Todos los intentos de los estudiantes\n- Los resultados asociados\n\nEsta acción NO se puede deshacer.`)) {
        window.location.href = `<?= BASE_URL ?>/docente/eliminar_evaluacion.php?id=${id}&modulo_id=<?= $modulo_id ?>&curso_id=<?= $curso_id ?>`;
    }
}

document.getElementById('modalNuevaEvaluacion').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModal();
    }
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>