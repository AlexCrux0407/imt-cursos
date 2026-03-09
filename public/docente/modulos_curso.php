<?php
require_once __DIR__ . '/../../app/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Docente – Módulos del Curso';

$curso_id = $_GET['id'] ?? 0;
$role = strtolower($_SESSION['role'] ?? '');
$is_master = $role === 'master';
$is_docente = $role === 'docente';
if (!$is_master && !$is_docente) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}
$back_url = $is_master ? BASE_URL . '/master/admin_cursos.php' : BASE_URL . '/docente/admin_cursos.php';

// Verificar que el curso pertenece al docente y obtener información
// Verificar si las nuevas columnas existen
$stmt = $conn->prepare("SHOW COLUMNS FROM cursos LIKE 'asignado_a'");
$stmt->execute();
$nuevas_columnas_existen = $stmt->fetch();

if ($is_master) {
    $stmt = $conn->prepare("
        SELECT * FROM cursos 
        WHERE id = :id
    ");
} elseif ($nuevas_columnas_existen) {
    // Sistema nuevo: verificar por asignación
    $stmt = $conn->prepare("
        SELECT * FROM cursos 
        WHERE id = :id AND asignado_a = :docente_id
    ");
} else {
    // Sistema anterior: verificar por creador
    $stmt = $conn->prepare("
        SELECT * FROM cursos 
        WHERE id = :id AND creado_por = :docente_id
    ");
}
if ($is_master) {
    $stmt->execute([':id' => $curso_id]);
} else {
    $stmt->execute([':id' => $curso_id, ':docente_id' => $_SESSION['user_id']]);
}
$curso = $stmt->fetch();

if (!$curso) {
    header('Location: ' . $back_url . '?error=curso_no_encontrado');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'actualizar_html') {
    require_once __DIR__ . '/../../app/curso_scanner.php';
    $folder = sanitizeFileName($curso['titulo']);
    $public_dir = rtrim(PUBLIC_PATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'cursos' . DIRECTORY_SEPARATOR . $folder;
    $root_dir = rtrim(UPLOADS_PATH, '/\\') . DIRECTORY_SEPARATOR . 'cursos' . DIRECTORY_SEPARATOR . $folder;
    $curso_dir = is_dir($public_dir) ? $public_dir : (is_dir($root_dir) ? $root_dir : null);

    if ($curso_dir === null) {
        header('Location: ' . BASE_URL . '/docente/modulos_curso.php?id=' . $curso_id . '&error=contenido_no_encontrado');
        exit;
    }

    $contenido_escaneado = scanImportedCourse($curso_dir);
    $hayContenido = !empty($contenido_escaneado['lecciones'])
        || !empty($contenido_escaneado['modulos_detectados'])
        || !empty($contenido_escaneado['temas_detectados'])
        || !empty($contenido_escaneado['subtemas_detectados']);
    if (!$hayContenido) {
        header('Location: ' . BASE_URL . '/docente/modulos_curso.php?id=' . $curso_id . '&error=contenido_invalido');
        exit;
    }

    try {
        $conn->beginTransaction();
        $modulos_creados = [];
        $temas_creados = [];
        $subtemas_creados = [];

        $modulos_detectados = $contenido_escaneado['modulos_detectados'] ?? [];
        $temas_detectados = $contenido_escaneado['temas_detectados'] ?? [];
        $subtemas_detectados = $contenido_escaneado['subtemas_detectados'] ?? [];

        foreach ($modulos_detectados as $modulo_data) {
            $modulo_orden = (int)$modulo_data['modulo_orden'];
            $modulo_key = $modulo_orden;
            if (isset($modulos_creados[$modulo_key])) {
                continue;
            }
            $stmt = $conn->prepare("
                INSERT INTO modulos (curso_id, titulo, descripcion, orden, created_at) 
                VALUES (:curso_id, :titulo, :descripcion, :orden, NOW())
                ON DUPLICATE KEY UPDATE 
                    titulo = VALUES(titulo),
                    descripcion = VALUES(descripcion),
                    orden = VALUES(orden)
            ");
            $stmt->execute([
                ':curso_id' => $curso_id,
                ':titulo' => 'Módulo ' . str_pad($modulo_orden, 2, '0', STR_PAD_LEFT),
                ':descripcion' => 'Módulo ' . $modulo_orden . ' actualizado desde HTML',
                ':orden' => $modulo_orden
            ]);
            $modulos_creados[$modulo_key] = $conn->lastInsertId() ?: getExistingModuloId($conn, $curso_id, $modulo_orden);
        }

        foreach ($temas_detectados as $tema_data) {
            $modulo_orden = (int)$tema_data['modulo_orden'];
            $tema_orden = (int)$tema_data['tema_orden'];
            $modulo_key = $modulo_orden;
            $tema_key = $modulo_orden . '_' . $tema_orden;

            if (!isset($modulos_creados[$modulo_key])) {
                $stmt = $conn->prepare("
                    INSERT INTO modulos (curso_id, titulo, descripcion, orden, created_at) 
                    VALUES (:curso_id, :titulo, :descripcion, :orden, NOW())
                    ON DUPLICATE KEY UPDATE 
                        titulo = VALUES(titulo),
                        descripcion = VALUES(descripcion),
                        orden = VALUES(orden)
                ");
                $stmt->execute([
                    ':curso_id' => $curso_id,
                    ':titulo' => 'Módulo ' . str_pad($modulo_orden, 2, '0', STR_PAD_LEFT),
                    ':descripcion' => 'Módulo ' . $modulo_orden . ' actualizado desde HTML',
                    ':orden' => $modulo_orden
                ]);
                $modulos_creados[$modulo_key] = $conn->lastInsertId() ?: getExistingModuloId($conn, $curso_id, $modulo_orden);
            }

            if (isset($temas_creados[$tema_key])) {
                continue;
            }

            $stmt = $conn->prepare("
                INSERT INTO temas (modulo_id, titulo, descripcion, orden, created_at) 
                VALUES (:modulo_id, :titulo, :descripcion, :orden, NOW())
                ON DUPLICATE KEY UPDATE 
                    titulo = VALUES(titulo),
                    descripcion = VALUES(descripcion),
                    orden = VALUES(orden)
            ");
            $stmt->execute([
                ':modulo_id' => $modulos_creados[$modulo_key],
                ':titulo' => 'Tema ' . str_pad($tema_orden, 2, '0', STR_PAD_LEFT),
                ':descripcion' => 'Tema ' . $tema_orden . ' del módulo ' . $modulo_orden,
                ':orden' => $tema_orden
            ]);
            $temas_creados[$tema_key] = $conn->lastInsertId() ?: getExistingTemaId($conn, $modulos_creados[$modulo_key], $tema_orden);
        }

        foreach ($subtemas_detectados as $subtema_data) {
            $modulo_orden = (int)$subtema_data['modulo_orden'];
            $tema_orden = (int)$subtema_data['tema_orden'];
            $subtema_orden = (int)$subtema_data['subtema_orden'];
            $modulo_key = $modulo_orden;
            $tema_key = $modulo_orden . '_' . $tema_orden;
            $subtema_key = $tema_key . '_' . $subtema_orden;

            if (!isset($modulos_creados[$modulo_key])) {
                $stmt = $conn->prepare("
                    INSERT INTO modulos (curso_id, titulo, descripcion, orden, created_at) 
                    VALUES (:curso_id, :titulo, :descripcion, :orden, NOW())
                    ON DUPLICATE KEY UPDATE 
                        titulo = VALUES(titulo),
                        descripcion = VALUES(descripcion),
                        orden = VALUES(orden)
                ");
                $stmt->execute([
                    ':curso_id' => $curso_id,
                    ':titulo' => 'Módulo ' . str_pad($modulo_orden, 2, '0', STR_PAD_LEFT),
                    ':descripcion' => 'Módulo ' . $modulo_orden . ' actualizado desde HTML',
                    ':orden' => $modulo_orden
                ]);
                $modulos_creados[$modulo_key] = $conn->lastInsertId() ?: getExistingModuloId($conn, $curso_id, $modulo_orden);
            }

            if (!isset($temas_creados[$tema_key])) {
                $stmt = $conn->prepare("
                    INSERT INTO temas (modulo_id, titulo, descripcion, orden, created_at) 
                    VALUES (:modulo_id, :titulo, :descripcion, :orden, NOW())
                    ON DUPLICATE KEY UPDATE 
                        titulo = VALUES(titulo),
                        descripcion = VALUES(descripcion),
                        orden = VALUES(orden)
                ");
                $stmt->execute([
                    ':modulo_id' => $modulos_creados[$modulo_key],
                    ':titulo' => 'Tema ' . str_pad($tema_orden, 2, '0', STR_PAD_LEFT),
                    ':descripcion' => 'Tema ' . $tema_orden . ' del módulo ' . $modulo_orden,
                    ':orden' => $tema_orden
                ]);
                $temas_creados[$tema_key] = $conn->lastInsertId() ?: getExistingTemaId($conn, $modulos_creados[$modulo_key], $tema_orden);
            }

            if (isset($subtemas_creados[$subtema_key])) {
                continue;
            }

            $stmt = $conn->prepare("
                INSERT INTO subtemas (tema_id, titulo, descripcion, orden, created_at) 
                VALUES (:tema_id, :titulo, :descripcion, :orden, NOW())
                ON DUPLICATE KEY UPDATE 
                    titulo = VALUES(titulo),
                    descripcion = VALUES(descripcion),
                    orden = VALUES(orden)
            ");
            $stmt->execute([
                ':tema_id' => $temas_creados[$tema_key],
                ':titulo' => 'Subtema ' . str_pad($subtema_orden, 2, '0', STR_PAD_LEFT),
                ':descripcion' => 'Subtema ' . $subtema_orden . ' del tema ' . $tema_orden,
                ':orden' => $subtema_orden
            ]);
            $subtemas_creados[$subtema_key] = $conn->lastInsertId() ?: getExistingSubtemaId($conn, $temas_creados[$tema_key], $subtema_orden);
        }

        foreach ($contenido_escaneado['lecciones'] ?? [] as $leccion_data) {
            $modulo_orden = $leccion_data['modulo_orden'];
            $tema_orden = $leccion_data['tema_orden'];
            $subtema_orden = $leccion_data['subtema_orden'];
            $leccion_orden = $leccion_data['leccion_orden'];

            $modulo_key = $modulo_orden;
            if (!isset($modulos_creados[$modulo_key])) {
                $stmt = $conn->prepare("
                    INSERT INTO modulos (curso_id, titulo, descripcion, orden, created_at) 
                    VALUES (:curso_id, :titulo, :descripcion, :orden, NOW())
                    ON DUPLICATE KEY UPDATE 
                        titulo = VALUES(titulo),
                        descripcion = VALUES(descripcion),
                        orden = VALUES(orden)
                ");
                $stmt->execute([
                    ':curso_id' => $curso_id,
                    ':titulo' => 'Módulo ' . str_pad($modulo_orden, 2, '0', STR_PAD_LEFT),
                    ':descripcion' => 'Módulo ' . $modulo_orden . ' actualizado desde HTML',
                    ':orden' => $modulo_orden
                ]);
                $modulos_creados[$modulo_key] = $conn->lastInsertId() ?: getExistingModuloId($conn, $curso_id, $modulo_orden);
            }

            $tema_key = $modulo_orden . '_' . $tema_orden;
            if (!isset($temas_creados[$tema_key])) {
                $stmt = $conn->prepare("
                    INSERT INTO temas (modulo_id, titulo, descripcion, orden, created_at) 
                    VALUES (:modulo_id, :titulo, :descripcion, :orden, NOW())
                    ON DUPLICATE KEY UPDATE 
                        titulo = VALUES(titulo),
                        descripcion = VALUES(descripcion),
                        orden = VALUES(orden)
                ");
                $stmt->execute([
                    ':modulo_id' => $modulos_creados[$modulo_key],
                    ':titulo' => 'Tema ' . str_pad($tema_orden, 2, '0', STR_PAD_LEFT),
                    ':descripcion' => 'Tema ' . $tema_orden . ' del módulo ' . $modulo_orden,
                    ':orden' => $tema_orden
                ]);
                $temas_creados[$tema_key] = $conn->lastInsertId() ?: getExistingTemaId($conn, $modulos_creados[$modulo_key], $tema_orden);
            }

            $subtema_key = $modulo_orden . '_' . $tema_orden . '_' . $subtema_orden;
            if (!isset($subtemas_creados[$subtema_key])) {
                $stmt = $conn->prepare("
                    INSERT INTO subtemas (tema_id, titulo, descripcion, orden, created_at) 
                    VALUES (:tema_id, :titulo, :descripcion, :orden, NOW())
                    ON DUPLICATE KEY UPDATE 
                        titulo = VALUES(titulo),
                        descripcion = VALUES(descripcion),
                        orden = VALUES(orden)
                ");
                $stmt->execute([
                    ':tema_id' => $temas_creados[$tema_key],
                    ':titulo' => 'Subtema ' . str_pad($subtema_orden, 2, '0', STR_PAD_LEFT),
                    ':descripcion' => 'Subtema ' . $subtema_orden . ' del tema ' . $tema_orden,
                    ':orden' => $subtema_orden
                ]);
                $subtemas_creados[$subtema_key] = $conn->lastInsertId() ?: getExistingSubtemaId($conn, $temas_creados[$tema_key], $subtema_orden);
            }

            $archivo_leccion = $curso_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $leccion_data['path']);
            $contenido_html = file_exists($archivo_leccion) ? file_get_contents($archivo_leccion) : '';
            $existing_leccion_id = getExistingLeccionId($conn, $subtemas_creados[$subtema_key], $leccion_orden);
            if ($existing_leccion_id) {
                $stmt = $conn->prepare("
                    UPDATE lecciones 
                    SET modulo_id = :modulo_id, subtema_id = :subtema_id, tema_id = :tema_id, titulo = :titulo, contenido = :contenido, tipo = 'documento', orden = :orden
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':modulo_id' => $modulos_creados[$modulo_key],
                    ':subtema_id' => $subtemas_creados[$subtema_key],
                    ':tema_id' => $temas_creados[$tema_key],
                    ':titulo' => $leccion_data['titulo'],
                    ':contenido' => $contenido_html,
                    ':orden' => $leccion_orden,
                    ':id' => $existing_leccion_id
                ]);
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO lecciones (modulo_id, subtema_id, tema_id, titulo, contenido, tipo, orden, created_at) 
                    VALUES (:modulo_id, :subtema_id, :tema_id, :titulo, :contenido, 'documento', :orden, NOW())
                ");
                $stmt->execute([
                    ':modulo_id' => $modulos_creados[$modulo_key],
                    ':subtema_id' => $subtemas_creados[$subtema_key],
                    ':tema_id' => $temas_creados[$tema_key],
                    ':titulo' => $leccion_data['titulo'],
                    ':contenido' => $contenido_html,
                    ':orden' => $leccion_orden
                ]);
            }
        }

        $conn->commit();
        header('Location: ' . BASE_URL . '/docente/modulos_curso.php?id=' . $curso_id . '&success=contenido_actualizado');
        exit;
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        header('Location: ' . BASE_URL . '/docente/modulos_curso.php?id=' . $curso_id . '&error=actualizar_fallido&details=' . urlencode($e->getMessage()));
        exit;
    }
}

// Obtener módulos del curso
$stmt = $conn->prepare("
    SELECT m.*, COUNT(l.id) as total_lecciones
    FROM modulos m
    LEFT JOIN lecciones l ON m.id = l.modulo_id
    WHERE m.curso_id = :curso_id
    GROUP BY m.id
    ORDER BY m.orden ASC
");
$stmt->execute([':curso_id' => $curso_id]);
$modulos = $stmt->fetchAll();

$editar_curso_url = $is_master ? BASE_URL . '/master/editar_curso.php?id=' . $curso['id'] : BASE_URL . '/docente/editar_curso.php?id=' . $curso['id'];

function sanitizeFileName($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '-', $filename);
    $filename = preg_replace('/-+/', '-', $filename);
    return trim($filename, '-');
}

function getExistingModuloId($conn, $curso_id, $orden) {
    $stmt = $conn->prepare("SELECT id FROM modulos WHERE curso_id = :curso_id AND orden = :orden");
    $stmt->execute([':curso_id' => $curso_id, ':orden' => $orden]);
    $row = $stmt->fetch();
    return $row ? $row['id'] : null;
}

function getExistingTemaId($conn, $modulo_id, $orden) {
    $stmt = $conn->prepare("SELECT id FROM temas WHERE modulo_id = :modulo_id AND orden = :orden");
    $stmt->execute([':modulo_id' => $modulo_id, ':orden' => $orden]);
    $row = $stmt->fetch();
    return $row ? $row['id'] : null;
}

function getExistingSubtemaId($conn, $tema_id, $orden) {
    $stmt = $conn->prepare("SELECT id FROM subtemas WHERE tema_id = :tema_id AND orden = :orden");
    $stmt->execute([':tema_id' => $tema_id, ':orden' => $orden]);
    $row = $stmt->fetch();
    return $row ? $row['id'] : null;
}

function getExistingLeccionId($conn, $subtema_id, $orden) {
    $stmt = $conn->prepare("SELECT id FROM lecciones WHERE subtema_id = :subtema_id AND orden = :orden");
    $stmt->execute([':subtema_id' => $subtema_id, ':orden' => $orden]);
    $row = $stmt->fetch();
    return $row ? $row['id'] : null;
}

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<div class="contenido">
    <!-- Mensajes de éxito o error -->
    <?php if (isset($_GET['success']) || isset($_GET['error'])): ?>
        <div style="margin-bottom: 20px;">
            <?php if (isset($_GET['success'])): ?>
                <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; border: 1px solid #c3e6cb;">
                    <?php
                    switch($_GET['success']) {
                        case 'modulo_eliminado':
                            echo 'Módulo eliminado exitosamente.';
                            break;
                        case 'modulo_creado':
                            echo 'Módulo creado exitosamente.';
                            break;
                        case 'modulo_actualizado':
                            echo 'Módulo actualizado exitosamente.';
                            break;
                        case 'contenido_actualizado':
                            echo 'Contenido actualizado desde HTML.';
                            break;
                        default:
                            echo 'Operación completada exitosamente.';
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; border: 1px solid #f5c6cb;">
                    <?php
                    switch($_GET['error']) {
                         case 'error_eliminar':
                             echo 'Error al eliminar el módulo. Por favor, inténtelo de nuevo.';
                             if (isset($_GET['details'])) {
                                 echo '<br><small>Detalles: ' . htmlspecialchars($_GET['details']) . '</small>';
                             }
                             break;
                        case 'error_crear':
                            echo 'Error al crear el módulo. Por favor, inténtelo de nuevo.';
                            break;
                        case 'contenido_no_encontrado':
                            echo 'No se encontró el contenido del curso en uploads.';
                            break;
                        case 'contenido_invalido':
                            echo 'No se encontraron lecciones válidas en el contenido.';
                            break;
                        case 'actualizar_fallido':
                            echo 'Error al actualizar el contenido.';
                            if (isset($_GET['details'])) {
                                echo '<br><small>Detalles: ' . htmlspecialchars($_GET['details']) . '</small>';
                            }
                            break;
                        case 'datos_invalidos':
                            echo 'Los datos proporcionados no son válidos.';
                            break;
                        case 'curso_no_encontrado':
                            echo 'El curso especificado no fue encontrado.';
                            break;
                        default:
                            echo 'Ha ocurrido un error. Por favor, inténtelo de nuevo.';
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="form-container-head" style="background: linear-gradient(135deg, #3498db, #3498db); color: white;">
        <div class="div-fila-alt-start">
            <div>
                <h1 style="font-size: 2rem; margin-bottom: 10px;">Módulos del Curso</h1>
                <p style="opacity: 0.9;"><?= htmlspecialchars($curso['titulo']) ?></p>
            </div>
            <div class="div-fila-alt" style="gap: 10px;">
                <button onclick="mostrarFormularioNuevoModulo()" 
                        style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    + Nuevo Módulo
                </button>
                <?php if ($is_master): ?>
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="accion" value="actualizar_html">
                        <button type="submit"
                                onclick="return confirm('¿Actualizar contenido desde los HTML en uploads?')"
                                style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                            Actualizar HTML
                        </button>
                    </form>
                <?php endif; ?>
                <a href="<?= $editar_curso_url ?>"
                   style="background: transparent; color: white; border: 2px solid white; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: 500; text-decoration: none;">
                    Editar Curso
                </a>
                <a href="<?= BASE_URL ?>/docente/configuracion_calificacion.php?curso_id=<?= $curso_id ?>"
                   style="background: transparent; color: #f8f9fa; border: 2px solid #f8f9fa; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: 500; text-decoration: none;">
                    ⚖️ Calificación
                </a>
            </div>
        </div>
    </div>

    <!-- Información del Curso -->
    <div class="form-container-body" style="margin-bottom: 20px;">
        <div class="div-fila" style="gap: 20px; align-items: center;">
            <div style="flex: 1;">
                <h3 style="color: #2c3e50; margin-bottom: 10px;">Información del Curso</h3>
                <p style="color: #7f8c8d; margin-bottom: 5px;"><?= htmlspecialchars($curso['descripcion'] ?? '') ?></p>
                <div class="div-fila-alt-start" style="gap: 15px;">
                    <span style="background: <?= $curso['estado'] === 'activo' ? '#27ae60' : ($curso['estado'] === 'borrador' ? '#f39c12' : '#e74c3c') ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">
                        <?= ucfirst($curso['estado']) ?>
                    </span>
                    <span style="color: #7f8c8d;"><?= count($modulos) ?> módulos</span>
                </div>
            </div>
            <div>
                <a href="<?= $back_url ?>" class="btn-volver">← Volver</a>
            </div>
        </div>
    </div>

    <!-- Lista de Módulos -->
    <div class="form-container-body">
        <h2 style="color: #3498db; margin-bottom: 20px;">Módulos</h2>
        
        <?php if (empty($modulos)): ?>
            <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                <img src="<?= BASE_URL ?>/styles/iconos/config.png" style="width: 64px; height: 64px; opacity: 0.5; margin-bottom: 20px; filter: brightness(0) saturate(100%) invert(50%);">
                <h3>No hay módulos creados</h3>
                <p>Comienza agregando el primer módulo a tu curso</p>
                <button onclick="mostrarFormularioNuevoModulo()" 
                        style="background: #3498db; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; margin-top: 15px;">
                    Crear Primer Módulo
                </button>
            </div>
        <?php else: ?>
            <div style="display: grid; gap: 15px;">
                <?php foreach ($modulos as $modulo): ?>
                    <div style="border: 2px solid #e8ecef; border-radius: 12px; padding: 20px; background: white; transition: all 0.3s ease;"
                         onmouseover="this.style.borderColor='#3498db'"
                         onmouseout="this.style.borderColor='#e8ecef'">
                        
                        <div class="div-fila" style="gap: 20px; align-items: center;">
                            <div style="width: 40px; height: 40px; background: #3498db; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                <?= $modulo['orden'] ?>
                            </div>
                            
                            <div style="flex: 1;">
                                <h4 style="color: #2c3e50; margin-bottom: 8px;"><?= htmlspecialchars($modulo['titulo']) ?></h4>
                                <p style="color: #7f8c8d; margin-bottom: 10px; font-size: 0.9rem;">
                                    <?= htmlspecialchars(substr($modulo['descripcion'] ?? '', 0, 100)) ?><?= strlen($modulo['descripcion'] ?? '') > 100 ? '...' : '' ?>
                                </p>
                                <div class="div-fila-alt-start" style="gap: 15px;">
                                    <span style="color: #3498db; font-weight: 500;"><?= $modulo['total_lecciones'] ?> lecciones</span>
                                    <span style="color: #7f8c8d; font-size: 0.8rem;">Creado: <?= date('d/m/Y', strtotime($modulo['created_at'])) ?></span>
                                </div>
                            </div>
                            
                            <div class="div-fila-alt-start" style="gap: 10px;">
                                <button onclick="gestionarTemas(<?= $modulo['id'] ?>)" 
                                style="background: #27ae60; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 14px;">
                            📚 Temas
                        </button>
                        <button onclick="gestionarEvaluaciones(<?= $modulo['id'] ?>)" 
                                style="background: #8e44ad; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 14px;">
                            📝 Evaluaciones
                        </button>
                                <button onclick="editarModulo(<?= $modulo['id'] ?>)" 
                                        style="background: transparent; color: #3498db; border: 2px solid #3498db; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.9rem;">
                                    Editar
                                </button>
                                <button onclick="confirmarEliminarModulo(<?= $modulo['id'] ?>, '<?= addslashes($modulo['titulo']) ?>')" 
                                        style="background: transparent; color: #e74c3c; border: 2px solid #e74c3c; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.9rem;">
                                    Eliminar
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para Nuevo Módulo -->
<div id="modalNuevoModulo" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 30px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <div class="div-fila" style="justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="color: #3498db; margin: 0;">Nuevo Módulo</h2>
            <button onclick="cerrarModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #7f8c8d;">&times;</button>
        </div>
        
        <form method="POST" action="<?= BASE_URL ?>/docente/procesar_modulo.php" enctype="multipart/form-data">
            <input type="hidden" name="curso_id" value="<?= $curso_id ?>">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Título del Módulo</label>
                <input type="text" name="titulo" required 
                       style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Descripción</label>
                <textarea name="descripcion" rows="3" 
                          style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; resize: vertical;"></textarea>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Contenido del Módulo</label>
                <textarea name="contenido" rows="5" 
                          style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px; resize: vertical;"
                          placeholder="Desarrolla el contenido principal de este módulo..."></textarea>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">URL del Recurso (opcional)</label>
                <input type="text" name="recurso_url" 
                       style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;"
                       placeholder="https://... (opcional)">
            </div>

            <div style="margin-bottom: 20px; padding: 20px; border: 2px dashed #e8ecef; border-radius: 8px; background: #fafbfc;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Subir Archivo (opcional)</label>
                <input type="file" name="archivo" accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4,.avi,.mov,.jpg,.jpeg,.png"
                       style="width: 100%; padding: 8px; border: 1px solid #e8ecef; border-radius: 6px;">
                <small style="color: #7f8c8d; display: block; margin-top: 8px;">
                    Formatos permitidos: PDF, DOC, DOCX, PPT, PPTX, MP4, AVI, MOV, JPG, PNG (Max: 50MB)
                </small>
            </div>
            
            <div style="margin-bottom: 30px;">
                <label style="display: block; color: #2c3e50; margin-bottom: 8px; font-weight: 500;">Orden</label>
                <input type="number" name="orden" value="<?= count($modulos) + 1 ?>" min="1" required
                       style="width: 100%; padding: 12px; border: 2px solid #e8ecef; border-radius: 8px; font-size: 16px;">
            </div>
            
            <div class="div-fila-alt" style="gap: 15px;">
                <button type="button" onclick="cerrarModal()" 
                        style="background: #e8ecef; color: #5a5c69; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    Cancelar
                </button>
                <button type="submit" 
                        style="background: #3498db; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 500;">
                    Crear Módulo
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function mostrarFormularioNuevoModulo() {
    document.getElementById('modalNuevoModulo').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('modalNuevoModulo').style.display = 'none';
}

function editarModulo(id) {
    window.location.href = `<?= BASE_URL ?>/docente/editar_modulo.php?id=${id}&curso_id=<?= $curso_id ?>`;
}

function gestionarTemas(id) {
    window.location.href = `<?= BASE_URL ?>/docente/temas_modulo.php?id=${id}&curso_id=<?= $curso_id ?>`;
}

function gestionarEvaluaciones(id) {
    window.location.href = `<?= BASE_URL ?>/docente/evaluaciones_modulo.php?id=${id}&curso_id=<?= $curso_id ?>`;
}

function confirmarEliminarModulo(id, titulo) {
    if (confirm(`¿Estás seguro de que deseas eliminar el módulo "${titulo}"?\n\nEsta acción eliminará permanentemente:\n- El módulo y su contenido\n- Todos los temas y subtemas\n- Todas las lecciones asociadas\n- Los archivos asociados\n\nEsta acción NO se puede deshacer.`)) {
        window.location.href = `<?= BASE_URL ?>/docente/eliminar_modulo.php?id=${id}&curso_id=<?= $curso_id ?>`;
    }
}

document.getElementById('modalNuevoModulo').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModal();
    }
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
