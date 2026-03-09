<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Configuración de Calificación';

$curso_id = (int)($_GET['curso_id'] ?? $_POST['curso_id'] ?? 0);
if ($curso_id === 0) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=curso_no_especificado');
    exit;
}

$stmt = $conn->prepare("SHOW COLUMNS FROM cursos LIKE 'asignado_a'");
$stmt->execute();
$nuevas_columnas_existen = (bool)$stmt->fetch();

if ($nuevas_columnas_existen) {
    $stmt = $conn->prepare("SELECT * FROM cursos WHERE id = :id AND (creado_por = :docente_id OR asignado_a = :docente_id2) LIMIT 1");
    $stmt->execute([':id' => $curso_id, ':docente_id' => $_SESSION['user_id'], ':docente_id2' => $_SESSION['user_id']]);
} else {
    $stmt = $conn->prepare("SELECT * FROM cursos WHERE id = :id AND creado_por = :docente_id LIMIT 1");
    $stmt->execute([':id' => $curso_id, ':docente_id' => $_SESSION['user_id']]);
}
$curso = $stmt->fetch();
if (!$curso) {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=curso_no_encontrado');
    exit;
}

$stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'curso_calificacion_config'");
$stmt->execute();
$tabla_config_existe = (int)$stmt->fetchColumn() > 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'evaluacion_peso_config'");
$stmt->execute();
$tabla_peso_existe = (int)$stmt->fetchColumn() > 0;
$tablas_listas = $tabla_config_existe && $tabla_peso_existe;

$stmt = $conn->prepare("
    SELECT e.id, e.titulo, e.activo, m.titulo as modulo_titulo, COALESCE(e.orden, e.id) as orden_eval, COALESCE(m.orden, m.id) as orden_modulo
    FROM evaluaciones_modulo e
    INNER JOIN modulos m ON e.modulo_id = m.id
    WHERE m.curso_id = :curso_id
    ORDER BY orden_modulo ASC, orden_eval ASC, e.id ASC
");
$stmt->execute([':curso_id' => $curso_id]);
$evaluaciones = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$tablas_listas) {
        header('Location: ' . BASE_URL . '/docente/configuracion_calificacion.php?curso_id=' . $curso_id . '&error=tablas_faltantes');
        exit;
    }

    $activo = isset($_POST['activo']) ? 1 : 0;
    $escala = $_POST['escala'] ?? '100';
    if (!in_array($escala, ['100', '10'], true)) {
        $escala = '100';
    }

    $pesos_post = $_POST['peso'] ?? [];
    $pesos = [];
    $suma = 0.0;
    foreach ($evaluaciones as $evaluacion) {
        $raw = $pesos_post[$evaluacion['id']] ?? '';
        $raw = str_replace(',', '.', $raw);
        $peso = is_numeric($raw) ? (float)$raw : 0.0;
        if ($peso < 0) {
            $peso = 0.0;
        }
        $pesos[$evaluacion['id']] = $peso;
        $suma += $peso;
    }

    if ($activo === 1) {
        if (empty($evaluaciones)) {
            header('Location: ' . BASE_URL . '/docente/configuracion_calificacion.php?curso_id=' . $curso_id . '&error=sin_evaluaciones');
            exit;
        }
        if (abs($suma - 100.0) > 0.01) {
            header('Location: ' . BASE_URL . '/docente/configuracion_calificacion.php?curso_id=' . $curso_id . '&error=suma_invalida');
            exit;
        }
    }

    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("INSERT INTO curso_calificacion_config (curso_id, activo, escala) VALUES (:curso_id, :activo, :escala) ON DUPLICATE KEY UPDATE activo = VALUES(activo), escala = VALUES(escala), updated_at = CURRENT_TIMESTAMP");
        $stmt->execute([':curso_id' => $curso_id, ':activo' => $activo, ':escala' => $escala]);

        $stmt_upsert = $conn->prepare("INSERT INTO evaluacion_peso_config (evaluacion_id, curso_id, peso_porcentual) VALUES (:evaluacion_id, :curso_id, :peso) ON DUPLICATE KEY UPDATE peso_porcentual = VALUES(peso_porcentual), curso_id = VALUES(curso_id), updated_at = CURRENT_TIMESTAMP");
        foreach ($pesos as $evaluacion_id => $peso) {
            $stmt_upsert->execute([':evaluacion_id' => $evaluacion_id, ':curso_id' => $curso_id, ':peso' => $peso]);
        }

        if (!empty($pesos)) {
            $ids = array_keys($pesos);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("DELETE FROM evaluacion_peso_config WHERE curso_id = ? AND evaluacion_id NOT IN ($placeholders)");
            $stmt->execute(array_merge([$curso_id], $ids));
        } else {
            $stmt = $conn->prepare("DELETE FROM evaluacion_peso_config WHERE curso_id = ?");
            $stmt->execute([$curso_id]);
        }

        $conn->commit();
        header('Location: ' . BASE_URL . '/docente/configuracion_calificacion.php?curso_id=' . $curso_id . '&success=guardado');
        exit;
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        header('Location: ' . BASE_URL . '/docente/configuracion_calificacion.php?curso_id=' . $curso_id . '&error=guardar_error');
        exit;
    }
}

$config = ['activo' => 0, 'escala' => '100'];
$pesos_guardados = [];
if ($tablas_listas) {
    $stmt = $conn->prepare("SELECT * FROM curso_calificacion_config WHERE curso_id = :curso_id LIMIT 1");
    $stmt->execute([':curso_id' => $curso_id]);
    $row = $stmt->fetch();
    if ($row) {
        $config = $row;
    }
    $stmt = $conn->prepare("SELECT evaluacion_id, peso_porcentual FROM evaluacion_peso_config WHERE curso_id = :curso_id");
    $stmt->execute([':curso_id' => $curso_id]);
    foreach ($stmt->fetchAll() as $peso) {
        $pesos_guardados[(int)$peso['evaluacion_id']] = (float)$peso['peso_porcentual'];
    }
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/docente.css">

<div class="contenido">
    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white;">
        <div class="div-fila-alt-start">
            <div>
                <h1 style="font-size: 2rem; margin-bottom: 10px;">Configuración de Calificación</h1>
                <p style="opacity: 0.9;"><?= htmlspecialchars($curso['titulo']) ?></p>
            </div>
            <a href="<?= BASE_URL ?>/docente/admin_cursos.php" class="btn-volver">← Volver</a>
        </div>
    </div>

    <?php if (isset($_GET['success']) || isset($_GET['error'])): ?>
        <div style="margin: 20px 0;">
            <?php if (isset($_GET['success'])): ?>
                <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; border: 1px solid #c3e6cb;">
                    ✅ Configuración guardada correctamente.
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; border: 1px solid #f5c6cb;">
                    <?php
                        switch ($_GET['error']) {
                            case 'suma_invalida':
                                echo 'La suma de los porcentajes debe ser 100%.';
                                break;
                            case 'sin_evaluaciones':
                                echo 'El curso no tiene evaluaciones registradas.';
                                break;
                            case 'tablas_faltantes':
                                echo 'Faltan tablas de configuración. Ejecuta el SQL proporcionado antes de guardar.';
                                break;
                            default:
                                echo 'No se pudo guardar la configuración.';
                        }
                    ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="form-container-body" style="background: #fff; padding: 24px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <?php if (!$tablas_listas): ?>
            <div style="background: #fff3cd; color: #856404; padding: 12px; border-radius: 8px; border: 1px solid #ffeeba; margin-bottom: 20px;">
                Ejecuta los comandos SQL de configuración antes de guardar.
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>/docente/configuracion_calificacion.php" id="formCalificacion">
            <input type="hidden" name="curso_id" value="<?= $curso_id ?>">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 10px; background: #f8f9fa; padding: 12px 16px; border-radius: 8px;">
                    <input type="checkbox" name="activo" id="activoToggle" value="1" <?= (int)($config['activo'] ?? 0) === 1 ? 'checked' : '' ?> <?= !$tablas_listas ? 'disabled' : '' ?>>
                    <span style="font-weight: 600; color: #2c3e50;">Activar ponderación</span>
                </label>

                <div style="background: #f8f9fa; padding: 12px 16px; border-radius: 8px;">
                    <label style="display: block; font-weight: 600; color: #2c3e50; margin-bottom: 6px;">Escala final</label>
                    <select name="escala" id="escalaSelect" <?= !$tablas_listas ? 'disabled' : '' ?> style="width: 100%; padding: 8px 10px; border: 1px solid #e1e5e9; border-radius: 6px;">
                        <option value="100" <?= ($config['escala'] ?? '100') === '100' ? 'selected' : '' ?>>Sobre 100</option>
                        <option value="10" <?= ($config['escala'] ?? '100') === '10' ? 'selected' : '' ?>>Sobre 10</option>
                    </select>
                </div>

                <div style="background: #f8f9fa; padding: 12px 16px; border-radius: 8px;">
                    <label style="display: block; font-weight: 600; color: #2c3e50; margin-bottom: 6px;">Total de porcentajes</label>
                    <div id="totalPeso" style="font-size: 1.2rem; font-weight: 700; color: #3498db;">0%</div>
                    <div id="estadoTotal" style="font-size: 0.85rem; color: #6c757d;"></div>
                </div>
            </div>

            <div style="border: 1px solid #e9ecef; border-radius: 10px; overflow: hidden;">
                <div style="background: #f0f7ff; padding: 12px 16px; font-weight: 600; color: #2c3e50;">Evaluaciones del curso</div>
                <?php if (empty($evaluaciones)): ?>
                    <div style="padding: 16px; color: #6c757d;">No hay evaluaciones registradas en este curso.</div>
                <?php else: ?>
                    <div style="display: grid; grid-template-columns: 1fr 140px; gap: 0; border-top: 1px solid #e9ecef;">
                        <?php foreach ($evaluaciones as $evaluacion): ?>
                            <div style="padding: 14px 16px; border-bottom: 1px solid #e9ecef;">
                                <div style="font-weight: 600; color: #2c3e50;"><?= htmlspecialchars($evaluacion['titulo']) ?></div>
                                <div style="font-size: 0.85rem; color: #7f8c8d;"><?= htmlspecialchars($evaluacion['modulo_titulo']) ?><?= (int)$evaluacion['activo'] === 1 ? '' : ' · Inactiva' ?></div>
                            </div>
                            <div style="padding: 12px 16px; border-bottom: 1px solid #e9ecef; display: flex; align-items: center; justify-content: center;">
                                <input type="number" step="0.01" min="0" max="100" name="peso[<?= (int)$evaluacion['id'] ?>]" class="peso-input"
                                       value="<?= htmlspecialchars(number_format($pesos_guardados[(int)$evaluacion['id']] ?? 0, 2, '.', '')) ?>"
                                       <?= !$tablas_listas ? 'disabled' : '' ?>
                                       style="width: 100%; padding: 8px 10px; border: 1px solid #e1e5e9; border-radius: 6px; text-align: center;">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 20px;">
                <button type="submit" id="guardarConfigBtn"
                        style="background: #3498db; color: white; border: none; padding: 12px 22px; border-radius: 8px; cursor: pointer; font-weight: 600;"
                        <?= !$tablas_listas ? 'disabled' : '' ?>>
                    Guardar Configuración
                </button>
                <a href="<?= BASE_URL ?>/docente/admin_cursos.php" class="btn-volver">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
const totalPeso = document.getElementById('totalPeso');
const estadoTotal = document.getElementById('estadoTotal');
const inputs = Array.from(document.querySelectorAll('.peso-input'));
const guardarBtn = document.getElementById('guardarConfigBtn');
const activoToggle = document.getElementById('activoToggle');
const tablasListas = <?= $tablas_listas ? 'true' : 'false' ?>;

function calcularTotal() {
    let total = 0;
    inputs.forEach(input => {
        const value = parseFloat((input.value || '0').replace(',', '.'));
        if (!Number.isNaN(value)) {
            total += value;
        }
    });
    total = Math.round(total * 100) / 100;
    totalPeso.textContent = total.toFixed(2) + '%';

    const activo = activoToggle && activoToggle.checked;
    if (!tablasListas || !activo) {
        estadoTotal.textContent = activo ? '' : 'Configuración inactiva';
        if (guardarBtn) guardarBtn.disabled = !tablasListas;
        totalPeso.style.color = '#3498db';
        return;
    }

    if (Math.abs(total - 100) > 0.01) {
        estadoTotal.textContent = 'La suma debe ser 100%';
        totalPeso.style.color = '#e74c3c';
        if (guardarBtn) guardarBtn.disabled = true;
    } else {
        estadoTotal.textContent = 'Suma correcta';
        totalPeso.style.color = '#27ae60';
        if (guardarBtn) guardarBtn.disabled = false;
    }
}

inputs.forEach(input => {
    input.addEventListener('input', calcularTotal);
});

if (activoToggle) {
    activoToggle.addEventListener('change', calcularTotal);
}

calcularTotal();
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
