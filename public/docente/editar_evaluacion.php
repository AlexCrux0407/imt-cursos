<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Editar Evaluación';

$evaluacion_id = (int)($_GET['id'] ?? 0);
$modulo_id = (int)($_GET['modulo_id'] ?? 0);
$curso_id = (int)($_GET['curso_id'] ?? 0);

if ($evaluacion_id === 0) {
    header('Location: ' . BASE_URL . '/docente/evaluaciones_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=evaluacion_invalida');
    exit;
}

// Verificar que la evaluación pertenece a un módulo del docente y obtener datos
$stmt = $conn->prepare("
    SELECT e.*, m.titulo AS modulo_titulo, c.titulo AS curso_titulo
    FROM evaluaciones_modulo e
    INNER JOIN modulos m ON e.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE e.id = :evaluacion_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
");
$stmt->execute([':evaluacion_id' => $evaluacion_id, ':docente_id' => $_SESSION['user_id'], ':docente_id2' => $_SESSION['user_id']]);
$evaluacion = $stmt->fetch();

if (!$evaluacion) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=evaluacion_no_encontrada');
    exit;
}

// Preparar valores para datetime-local
$fecha_inicio_val = !empty($evaluacion['fecha_inicio']) ? date('Y-m-d\\TH:i', strtotime($evaluacion['fecha_inicio'])) : '';
$fecha_fin_val = !empty($evaluacion['fecha_fin']) ? date('Y-m-d\\TH:i', strtotime($evaluacion['fecha_fin'])) : '';

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/docente.css">

<div class="contenido">
    <div class="form-container">
        <div class="form-container-header" style="background: linear-gradient(90deg, #f39c12, #e67e22); color: white; padding: 16px; border-radius: 8px;">
            <div>
                <h2 style="margin: 0;">Editar Evaluación</h2>
                <p style="opacity: 0.9;">Módulo: <?= htmlspecialchars($evaluacion['modulo_titulo']) ?> · Curso: <?= htmlspecialchars($evaluacion['curso_titulo']) ?></p>
            </div>
            <div style="display: flex; gap: 12px;">
                <button onclick="window.location.href='<?= BASE_URL ?>/docente/evaluaciones_modulo.php?id=<?= $evaluacion['modulo_id'] ?>&curso_id=<?= $curso_id ?>'" 
                        style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    ← Volver a Evaluaciones
                </button>
            </div>
        </div>

        <div class="form-container-body" style="padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <form action="<?= BASE_URL ?>/docente/actualizar_evaluacion.php" method="POST" novalidate>
                <input type="hidden" name="evaluacion_id" value="<?= $evaluacion_id ?>">
                <input type="hidden" name="modulo_id" value="<?= (int)$evaluacion['modulo_id'] ?>">
                <input type="hidden" name="curso_id" value="<?= $curso_id ?>">

                <div class="form-group">
                    <label for="titulo">Título *</label>
                    <input type="text" id="titulo" name="titulo" class="form-control" required value="<?= htmlspecialchars($evaluacion['titulo']) ?>">
                </div>

                <div class="form-group">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($evaluacion['descripcion'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="tipo">Tipo de Evaluación *</label>
                    <select id="tipo" name="tipo" class="form-control" required>
                        <?php 
                        $tipos = ['examen' => 'Examen', 'tarea' => 'Tarea', 'proyecto' => 'Proyecto', 'quiz' => 'Quiz'];
                        foreach ($tipos as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $evaluacion['tipo'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <div class="form-group">
                        <label for="puntaje_maximo">Puntaje Máximo *</label>
                        <input type="number" step="0.01" id="puntaje_maximo" name="puntaje_maximo" class="form-control" required value="<?= (float)$evaluacion['puntaje_maximo'] ?>">
                    </div>
                    <div class="form-group">
                        <label for="puntaje_minimo_aprobacion">Puntaje Mínimo de Aprobación *</label>
                        <input type="number" step="0.01" id="puntaje_minimo_aprobacion" name="puntaje_minimo_aprobacion" class="form-control" required value="<?= (float)$evaluacion['puntaje_minimo_aprobacion'] ?>">
                    </div>
                    <div class="form-group">
                        <label for="tiempo_limite">Tiempo Límite (minutos)</label>
                        <input type="number" id="tiempo_limite" name="tiempo_limite" class="form-control" value="<?= (int)($evaluacion['tiempo_limite'] ?? 0) ?>">
                    </div>
                    <div class="form-group">
                        <label for="intentos_permitidos">Intentos Permitidos *</label>
                        <input type="number" id="intentos_permitidos" name="intentos_permitidos" class="form-control" required value="<?= (int)$evaluacion['intentos_permitidos'] ?>">
                    </div>
                    <div class="form-group">
                        <label for="orden">Orden *</label>
                        <input type="number" id="orden" name="orden" class="form-control" required value="<?= (int)$evaluacion['orden'] ?>">
                    </div>
                </div>

                <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-top: 10px;">
                    <div class="form-group">
                        <label for="fecha_inicio">Fecha Inicio</label>
                        <input type="datetime-local" id="fecha_inicio" name="fecha_inicio" class="form-control" value="<?= $fecha_inicio_val ?>">
                    </div>
                    <div class="form-group">
                        <label for="fecha_fin">Fecha Fin</label>
                        <input type="datetime-local" id="fecha_fin" name="fecha_fin" class="form-control" value="<?= $fecha_fin_val ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 10px;">
                    <label>
                        <input type="checkbox" name="obligatorio" value="1" <?= (int)$evaluacion['obligatorio'] === 1 ? 'checked' : '' ?>>
                        Obligatorio para completar el módulo
                    </label>
                </div>

                <div class="form-group">
                    <label for="instrucciones">Instrucciones</label>
                    <textarea id="instrucciones" name="instrucciones" class="form-control" rows="4"><?= htmlspecialchars($evaluacion['instrucciones'] ?? '') ?></textarea>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 16px;">
                    <button type="submit" class="btn-action btn-warning">✅ Guardar Cambios</button>
                    <a href="<?= BASE_URL ?>/docente/evaluaciones_modulo.php?id=<?= $evaluacion['modulo_id'] ?>&curso_id=<?= $curso_id ?>" class="btn-action btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>