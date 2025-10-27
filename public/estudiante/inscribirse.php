<?php
// Vista Estudiante – Inscripción a curso
declare(strict_types=1);

require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Estudiante – Inscripción a Curso';

$curso_id      = (int)($_GET['curso_id'] ?? 0);
$estudiante_id = (int)($_SESSION['user_id'] ?? 0);

if ($curso_id === 0) {
    header('Location: ' . BASE_URL . '/estudiante/catalogo.php?error=curso_no_especificado');
    exit;
}
if ($estudiante_id === 0) {
    header('Location: ' . BASE_URL . '/login.php?m=auth');
    exit;
}

/**
 * Obtener información del curso.
 * Usamos COALESCE para tomar el nombre del asignado o, en su defecto, del creador.
 * Permitimos estado 'publicado' o 'activo' por si la tabla usa alguno de esos valores.
 */
$stmt = $conn->prepare("
    SELECT c.*,
           COALESCE(u_asignado.nombre, u_creador.nombre) AS docente_nombre
    FROM cursos c
    LEFT JOIN usuarios u_asignado ON u_asignado.id = c.asignado_a
    LEFT JOIN usuarios u_creador  ON u_creador.id  = c.creado_por
    WHERE c.id = :curso_id
      AND c.estado IN ('publicado','activo')
    LIMIT 1
");
$stmt->execute([':curso_id' => $curso_id]);
$curso = $stmt->fetch();

if (!$curso) {
    header('Location: ' . BASE_URL . '/estudiante/catalogo.php?error=curso_no_encontrado');
    exit;
}

/**
 * Si ya está inscrito, redirigimos directo al contenido.
 * (Opcional, porque abajo el INSERT es idempotente)
 */
$chk = $conn->prepare("
    SELECT id FROM inscripciones
    WHERE curso_id = :curso_id AND usuario_id = :uid
    LIMIT 1
");
$chk->execute([':curso_id' => $curso_id, ':uid' => $estudiante_id]);
if ($chk->fetch()) {
    header('Location: ' . BASE_URL . '/estudiante/curso_contenido.php?id=' . $curso_id);
    exit;
}

/**
 * Procesar la inscripción: idempotente.
 * Requiere UNIQUE KEY (curso_id, usuario_id) en `inscripciones`.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_inscripcion'])) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO inscripciones (curso_id, usuario_id, estado, progreso)
            VALUES (:curso_id, :uid, 'activo', 0)
            ON DUPLICATE KEY UPDATE id = id
        ");
        $stmt->execute([':curso_id' => $curso_id, ':uid' => $estudiante_id]);

        header('Location: ' . BASE_URL . '/estudiante/curso_contenido.php?id=' . $curso_id . '&success=inscripcion_exitosa');
        exit;
    } catch (Throwable $e) {
        // Puedes loguear $e->getMessage() si deseas
        $error_message = "Error al procesar la inscripción. Inténtalo de nuevo.";
    }
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/estudiante.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/inscripcion.css">

<div class="contenido">
    <div class="inscripcion-container">
        <div class="curso-preview">
            <div class="curso-header">
                <h1 class="curso-titulo"><?= htmlspecialchars($curso['titulo'] ?? '') ?></h1>
                <?php if (!empty($curso['docente_nombre'])): ?>
                    <p class="curso-docente">Por <?= htmlspecialchars($curso['docente_nombre']) ?></p>
                <?php endif; ?>
            </div>

            <div class="curso-info">
                <?php if (!empty($curso['categoria'])): ?>
                    <div class="info-item">
                        <div class="info-label">Categoría</div>
                        <div class="info-value"><?= htmlspecialchars($curso['categoria']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($curso['duracion'])): ?>
                    <div class="info-item">
                        <div class="info-label">Duración</div>
                        <div class="info-value"><?= htmlspecialchars($curso['duracion']) ?></div>
                    </div>
                <?php endif; ?>

                <div class="info-item">
                    <div class="info-label">Estado</div>
                    <div class="info-value activo">Activo</div>
                </div>
            </div>

            <?php if (!empty($curso['descripcion'])): ?>
                <div class="curso-seccion">
                    <h3 class="seccion-titulo">Descripción</h3>
                    <p class="seccion-contenido"><?= htmlspecialchars($curso['descripcion']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($curso['objetivo_general'])): ?>
                <div class="curso-seccion">
                    <h3 class="seccion-titulo">Objetivo General</h3>
                    <p class="seccion-contenido"><?= htmlspecialchars($curso['objetivo_general']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($curso['dirigido_a'])): ?>
                <div class="curso-seccion">
                    <h3 class="seccion-titulo">Dirigido a</h3>
                    <p class="seccion-contenido"><?= htmlspecialchars($curso['dirigido_a']) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert-error">
                <?= htmlspecialchars($error_message) ?>
                <?php if (isset($_GET['debug'])): ?>
                    <div class="debug-info">
                        <strong>Debug:</strong><br>
                        Curso ID: <?= (int)$curso_id ?><br>
                        Usuario ID: <?= (int)$estudiante_id ?><br>
                        Método: <?= htmlspecialchars($_SERVER['REQUEST_METHOD']) ?><br>
                        POST: <pre><?= htmlspecialchars(print_r($_POST, true)) ?></pre>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="confirmacion-box">
            <h2 class="confirmacion-title">¿Deseas inscribirte en este curso?</h2>
            <p class="confirmacion-text">
                Al inscribirte tendrás acceso completo al contenido del curso, podrás seguir tu progreso
                y obtener un certificado al completarlo exitosamente.
            </p>

            <form method="POST" onsubmit="return confirmarInscripcion()">
                <input type="hidden" name="confirmar_inscripcion" value="1">
                <button type="submit" class="btn-inscribirse" id="btnConfirmar">
                    ✓ Confirmar Inscripción
                </button>
                <a href="<?= BASE_URL ?>/estudiante/catalogo.php" class="btn-cancelar">
                    ← Volver al Catálogo
                </a>
            </form>
        </div>
    </div>
</div>

<script>
function confirmarInscripcion() {
    const btn = document.getElementById('btnConfirmar');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Procesando...';
    }
    return true;
}
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
