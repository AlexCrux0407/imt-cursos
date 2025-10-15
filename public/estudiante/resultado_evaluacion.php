<?php

declare(strict_types=1);

/* Asegurar sesión y BASE_URL antes de usarla en redirects/rutas */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('BASE_URL')) {
    // Ajusta este valor a tu entorno. Para Laragon típico:
    define('BASE_URL', '/imt-cursos/public');
}

require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

$page_title = 'Estudiante – Resultado de Evaluación';

$intento_id = (int)($_GET['intento_id'] ?? 0);
$evaluacion_id = (int)($_GET['evaluacion_id'] ?? 0);
$mensaje = $_GET['mensaje'] ?? '';
$tipo = $_GET['tipo'] ?? 'info';
$usuario_id = (int)($_SESSION['user_id'] ?? 0);

// Debug: Log para verificar parámetros recibidos
error_log("resultado_evaluacion.php - intento_id: $intento_id, evaluacion_id: $evaluacion_id, usuario_id: $usuario_id, mensaje: $mensaje, tipo: $tipo");

// Si no se proporciona intento_id pero sí evaluacion_id, buscar el último intento
if ($intento_id <= 0 && $evaluacion_id > 0) {
    $stmt = $conn->prepare("
        SELECT id FROM intentos_evaluacion 
        WHERE evaluacion_id = :evaluacion_id AND usuario_id = :usuario_id 
        ORDER BY fecha_inicio DESC LIMIT 1
    ");
    $stmt->execute([':evaluacion_id' => $evaluacion_id, ':usuario_id' => $usuario_id]);
    $ultimo_intento = $stmt->fetch();
    
    if ($ultimo_intento) {
        $intento_id = $ultimo_intento['id'];
    }
}

if ($intento_id <= 0) {
    header('Location: ' . BASE_URL . '/estudiante/dashboard.php?error=intento_no_valido');
    exit;
}

// Obtener información del intento
$stmt = $conn->prepare("
    SELECT ie.*, e.titulo as evaluacion_titulo, e.puntaje_maximo, e.puntaje_minimo_aprobacion,
           m.titulo as modulo_titulo, c.titulo as curso_titulo, c.id as curso_id
    FROM intentos_evaluacion ie
    INNER JOIN evaluaciones_modulo e ON ie.evaluacion_id = e.id
    INNER JOIN modulos m ON e.modulo_id = m.id
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE ie.id = :intento_id AND ie.usuario_id = :usuario_id
");
$stmt->execute([':intento_id' => $intento_id, ':usuario_id' => $usuario_id]);
$intento = $stmt->fetch();

if (!$intento) {
    header('Location: ' . BASE_URL . '/estudiante/dashboard.php');
    exit;
}

// Obtener información adicional de la evaluación
$stmt = $conn->prepare("
    SELECT * FROM evaluaciones_modulo WHERE id = :evaluacion_id
");
$stmt->execute([':evaluacion_id' => $intento['evaluacion_id']]);
$evaluacion = $stmt->fetch();

// Obtener respuestas del estudiante
$stmt = $conn->prepare("
    SELECT re.*, pe.pregunta, pe.tipo, pe.opciones, pe.respuesta_correcta
    FROM respuestas_estudiante re
    INNER JOIN preguntas_evaluacion pe ON re.pregunta_id = pe.id
    WHERE re.intento_id = :intento_id
    ORDER BY pe.orden ASC
");
$stmt->execute([':intento_id' => $intento_id]);
$respuestas = $stmt->fetchAll();

// Calcular estadísticas
$total_preguntas = count($respuestas);
$respuestas_correctas = 0;
$respuestas_incorrectas = 0;
$respuestas_pendientes = 0;

foreach ($respuestas as $respuesta) {
    if ($respuesta['es_correcta'] === null) {
        $respuestas_pendientes++;
    } elseif ($respuesta['es_correcta'] == 1) {
        $respuestas_correctas++;
    } else {
        $respuestas_incorrectas++;
    }
}

$porcentaje_correcto = $total_preguntas > 0 ? ($respuestas_correctas / $total_preguntas) * 100 : 0;

require __DIR__ . '/../partials/header.php';
require __DIR__ . '/../partials/nav.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/styles/css/estudiante.css">

<style>
    .resultado-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .resultado-header {
        background: #fff;
        border-radius: 10px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border-left: 4px solid #3498db;
    }
    
    .resultado-card {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
        overflow: hidden;
    }
    
    .card-header-custom {
        background: #f8f9fa;
        padding: 1.5rem;
        border-bottom: 1px solid #dee2e6;
        font-weight: 600;
        color: #2c3e50;
    }
    
    .estadistica-item {
        text-align: center;
        padding: 1.5rem;
        border-radius: 10px;
        margin-bottom: 1rem;
        color: white;
        font-weight: 600;
    }
    
    .estadistica-correcta {
        background: linear-gradient(135deg, #27ae60, #2ecc71);
    }
    
    .estadistica-incorrecta {
        background: linear-gradient(135deg, #e74c3c, #c0392b);
    }
    
    .estadistica-pendiente {
        background: linear-gradient(135deg, #f39c12, #e67e22);
    }
    
    .pregunta-item {
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        background: white;
    }
    
    .pregunta-correcta {
        border-left: 4px solid #27ae60;
        background: #f8fff9;
    }
    
    .pregunta-incorrecta {
        border-left: 4px solid #e74c3c;
        background: #fff8f8;
    }
    
    .pregunta-pendiente {
        border-left: 4px solid #f39c12;
        background: #fffdf5;
    }
    
    .respuesta-correcta {
        color: #27ae60;
        font-weight: bold;
    }
    
    .respuesta-incorrecta {
        color: #e74c3c;
        font-weight: bold;
    }
    
    .respuesta-pendiente {
        color: #f39c12;
        font-weight: bold;
    }
    
    .btn-volver {
        background: #3498db;
        border: none;
        border-radius: 8px;
        padding: 0.75rem 2rem;
        color: white;
        font-weight: 500;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-volver:hover {
        background: #2980b9;
        color: white;
        transform: translateY(-1px);
    }
    
    .alert-custom {
        border: none;
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 2rem;
    }
    
    .alert-success-custom {
        background: #d4edda;
        color: #155724;
        border-left: 4px solid #27ae60;
    }
    
    .alert-warning-custom {
        background: #fff3cd;
        color: #856404;
        border-left: 4px solid #f39c12;
    }
    
    .alert-info-custom {
        background: #d1ecf1;
        color: #0c5460;
        border-left: 4px solid #3498db;
    }
</style>

<div class="resultado-container">
    <div class="resultado-header">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h1 class="mb-2 d-flex align-items-center" style="color: #2c3e50;">
                    <img src="../styles/iconos/tablefullb.png" alt="Evaluación" style="width: 24px; height: 24px; margin-right: 12px;">
                    Resultado de Evaluación
                </h1>
                <p class="mb-0" style="color: #7f8c8d;">
                    <?= htmlspecialchars($intento['curso_titulo']) ?> > <?= htmlspecialchars($intento['modulo_titulo']) ?>
                </p>
            </div>
            <div class="text-end">
                <div class="fs-5 d-flex align-items-center justify-content-end" style="color: #7f8c8d;">
                    <img src="../styles/iconos/entrada.png" alt="Fecha" style="width: 20px; height: 20px; margin-right: 8px;">
                    <span><?php 
                    $fecha_mostrar = $intento['fecha_completado'] ?? $intento['fecha_intento'] ?? null;
                    if ($fecha_mostrar) {
                        echo date('d/m/Y H:i', strtotime($fecha_mostrar));
                    } else {
                        echo date('d/m/Y H:i'); // Fecha actual si no hay fecha disponible
                    }
                    ?></span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipo === 'success' ? 'success' : ($tipo === 'warning' ? 'warning' : 'info') ?>-custom alert-custom">
            <div class="d-flex align-items-center">
                <?php 
                $icon_src = $tipo === 'success' ? 'showgreen.png' : ($tipo === 'warning' ? 'hidenred.png' : 'show.png');
                $icon_alt = $tipo === 'success' ? 'Éxito' : ($tipo === 'warning' ? 'Advertencia' : 'Información');
                ?>
                <img src="../styles/iconos/<?= $icon_src ?>" alt="<?= $icon_alt ?>" style="width: 24px; height: 24px; margin-right: 12px; vertical-align: middle;">
                <div><?= htmlspecialchars($mensaje) ?></div>
            </div>
        </div>
    <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="resultado-card">
                    <div class="card-header-custom">
                        <h5 class="mb-0">
                            <img src="../styles/iconos/tablefullb.png" alt="Resumen" style="width: 20px; height: 20px; margin-right: 8px; vertical-align: middle;">
                            Resumen de Resultados
                        </h5>
                    </div>
                    <div class="p-3">
                        <div class="estadistica-item estadistica-correcta">
                            <div class="fs-2 fw-bold"><?= $respuestas_correctas ?></div>
                            <div>Respuestas Correctas</div>
                        </div>
                        
                        <div class="estadistica-item estadistica-incorrecta">
                            <div class="fs-2 fw-bold"><?= $respuestas_incorrectas ?></div>
                            <div>Respuestas Incorrectas</div>
                        </div>
                        
                        <?php if ($respuestas_pendientes > 0): ?>
                            <div class="estadistica-item estadistica-pendiente">
                                <div class="fs-2 fw-bold"><?= $respuestas_pendientes ?></div>
                                <div>Pendientes de Revisión</div>
                            </div>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <div class="text-center">
                            <div class="fs-4 fw-bold mb-2" style="color: #2c3e50;">
                                <?php if ($intento['puntaje_obtenido'] !== null): ?>
                                    <?= number_format((float)$intento['puntaje_obtenido'], 1) ?>%
                                <?php else: ?>
                                    Pendiente
                                <?php endif; ?>
                            </div>
                            <div class="text-muted">
                                Puntaje <?= $intento['puntaje_obtenido'] !== null ? 'Obtenido' : 'Pendiente' ?>
                            </div>
                            <?php if ($intento['puntaje_obtenido'] !== null): ?>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        Mínimo para aprobar: <?= $intento['puntaje_minimo_aprobacion'] ?>%
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="resultado-card">
                    <div class="card-header-custom">
                        <h5 class="mb-0">
                            <img src="../styles/iconos/detalles_bl.png" alt="Detalle" style="width: 20px; height: 20px; margin-right: 8px; vertical-align: middle;">
                            Detalle de Respuestas
                        </h5>
                    </div>
                    <div class="p-3">
                        <?php foreach ($respuestas as $index => $respuesta): ?>
                            <div class="pregunta-item <?= $respuesta['es_correcta'] === null ? 'pregunta-pendiente' : ($respuesta['es_correcta'] ? 'pregunta-correcta' : 'pregunta-incorrecta') ?>">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="mb-0">Pregunta <?= $index + 1 ?></h6>
                                    <span class="badge <?= $respuesta['es_correcta'] === null ? 'text-dark' : '' ?>" 
                                          <?php if ($respuesta['es_correcta'] === null): ?>
                                              style="background: #ffc107 !important; color: #212529 !important;"
                                          <?php elseif ($respuesta['es_correcta']): ?>
                                              style="background: #28a745 !important; color: white !important;"
                                          <?php else: ?>
                                              style="background: #dc3545 !important; color: white !important;"
                                          <?php endif; ?>>
                                        <?= $respuesta['es_correcta'] === null ? 'Pendiente' : ($respuesta['es_correcta'] ? 'Correcta' : 'Incorrecta') ?>
                                    </span>
                                </div>
                                
                                <p class="mb-3"><?= htmlspecialchars($respuesta['pregunta']) ?></p>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Tu respuesta:</strong>
                                        <div class="respuesta-<?= $respuesta['es_correcta'] === null ? 'pendiente' : ($respuesta['es_correcta'] ? 'correcta' : 'incorrecta') ?>">
                                            <?php
                                            $tipo = $respuesta['tipo'];
                                            $resp = $respuesta['respuesta'];
                                            $opciones = json_decode($respuesta['opciones'] ?? '[]', true) ?: [];
                                            if ($tipo === 'multiple_choice') {
                                                echo htmlspecialchars(($opciones[$resp] ?? $resp));
                                            } elseif ($tipo === 'seleccion_multiple') {
                                                $indices = json_decode($resp ?? '[]', true) ?: [];
                                                $labels = [];
                                                foreach ($indices as $i) { $labels[] = $opciones[$i] ?? $i; }
                                                echo htmlspecialchars(implode(', ', $labels));
                                            } elseif ($tipo === 'verdadero_falso') {
                                                echo $resp == '1' ? 'Verdadero' : 'Falso';
                                            } elseif ($tipo === 'emparejar_columnas') {
                                                $pairs = $opciones['pairs'] ?? [];
                                                $respMap = json_decode($resp ?? '{}', true) ?: [];
                                                echo '<ul style="padding-left:18px;margin:0">';
                                                foreach ($pairs as $idx => $pair) {
                                                    $sel = $respMap[$idx] ?? '';
                                                    echo '<li>' . htmlspecialchars($pair['left']) . ' → ' . htmlspecialchars($sel) . '</li>';
                                                }
                                                echo '</ul>';
                                            } elseif ($tipo === 'completar_espacios') {
                                                $blancos = json_decode($resp ?? '[]', true) ?: [];
                                                echo htmlspecialchars(implode(' | ', $blancos));
                                            } else {
                                                echo htmlspecialchars($resp);
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($respuesta['es_correcta'] !== null && !$respuesta['es_correcta'] && !in_array($respuesta['tipo'], ['texto_corto','texto_largo'])): ?>
                                        <div class="col-md-6">
                                            <strong>Respuesta correcta:</strong>
                                            <div class="respuesta-correcta">
                                                <?php
                                                $tipo = $respuesta['tipo'];
                                                $opciones = json_decode($respuesta['opciones'] ?? '[]', true) ?: [];
                                                $corr = $respuesta['respuesta_correcta'];
                                                if ($tipo === 'multiple_choice') {
                                                    echo htmlspecialchars($opciones[$corr] ?? $corr);
                                                } elseif ($tipo === 'seleccion_multiple') {
                                                    $indices = json_decode($corr ?? '[]', true) ?: [];
                                                    $labels = [];
                                                    foreach ($indices as $i) { $labels[] = $opciones[$i] ?? $i; }
                                                    echo htmlspecialchars(implode(', ', $labels));
                                                } elseif ($tipo === 'verdadero_falso') {
                                                    echo $corr == '1' ? 'Verdadero' : 'Falso';
                                                } elseif ($tipo === 'emparejar_columnas') {
                                                    $pairs = $opciones['pairs'] ?? [];
                                                    $derecha = json_decode($corr, true) ?: [];
                                                    echo '<ul style="padding-left:18px;margin:0">';
                                                    foreach ($pairs as $idx => $pair) {
                                                        $sel = $derecha[$idx] ?? '';
                                                        echo '<li>' . htmlspecialchars($pair['left']) . ' → ' . htmlspecialchars($sel) . '</li>';
                                                    }
                                                    echo '</ul>';
                                                } elseif ($tipo === 'completar_espacios') {
                                                    $vals = json_decode($corr, true) ?: [];
                                                    echo htmlspecialchars(implode(' | ', $vals));
                                                } else {
                                                    echo htmlspecialchars($corr);
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Sección de retroalimentación mejorada -->
                                <?php if ($respuesta['es_correcta'] !== null): ?>
                                    <div class="mt-3 p-3 rounded" style="background: <?= $respuesta['es_correcta'] ? '#e8f5e8' : '#ffeaea' ?>; border-left: 4px solid <?= $respuesta['es_correcta'] ? '#28a745' : '#dc3545' ?>;">
                                        <div class="d-flex align-items-center mb-2">
                                            <span style="font-size: 1.2em; margin-right: 8px;">
                                                <?= $respuesta['es_correcta'] ? '✅' : '❌' ?>
                                            </span>
                                            <strong style="color: <?= $respuesta['es_correcta'] ? '#155724' : '#721c24' ?>;">
                                                <?= $respuesta['es_correcta'] ? '¡Excelente!' : 'Revisa esta respuesta' ?>
                                            </strong>
                                        </div>
                                        <div style="color: <?= $respuesta['es_correcta'] ? '#155724' : '#721c24' ?>; font-size: 0.9em;">
                                            <?php if ($respuesta['es_correcta']): ?>
                                                Has respondido correctamente. ¡Sigue así!
                                            <?php else: ?>
                                                <?php
                                                // Generar retroalimentación específica por tipo de pregunta
                                                $tipo = $respuesta['tipo'];
                                                if ($tipo === 'multiple_choice') {
                                                    echo "Recuerda revisar todas las opciones cuidadosamente antes de seleccionar tu respuesta.";
                                                } elseif ($tipo === 'verdadero_falso') {
                                                    echo "Analiza la afirmación palabra por palabra para determinar si es completamente verdadera o falsa.";
                                                } elseif ($tipo === 'seleccion_multiple') {
                                                    echo "En este tipo de pregunta, puede haber más de una respuesta correcta. Revisa todas las opciones.";
                                                } elseif ($tipo === 'emparejar_columnas') {
                                                    echo "Revisa las relaciones entre los elementos de ambas columnas.";
                                                } elseif ($tipo === 'completar_espacios') {
                                                    echo "Considera el contexto de la oración para completar los espacios en blanco.";
                                                } else {
                                                    echo "Revisa el material del curso relacionado con este tema.";
                                                }
                                                ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="mt-3 p-3 rounded" style="background: #fff3cd; border-left: 4px solid #ffc107;">
                                        <div class="d-flex align-items-center mb-2">
                                            <span style="font-size: 1.2em; margin-right: 8px;">⏳</span>
                                            <strong style="color: #856404;">Pendiente de revisión</strong>
                                        </div>
                                        <div style="color: #856404; font-size: 0.9em;">
                                            Tu respuesta está siendo revisada por el instructor. Recibirás la calificación pronto.
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-3 mb-3">
            <!-- Sección de resumen final mejorada -->
            <div class="card resultado-card mb-3">
                <div class="card-header py-2">
                    <h6 class="mb-0">
                        <img src="../styles/iconos/showgreen.png" alt="Resumen" style="width: 18px; height: 18px; margin-right: 6px; vertical-align: middle;">
                        Resumen de tu Desempeño
                    </h6>
                </div>
                <div class="card-body py-3">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-2">
                                <h6 class="text-muted">Tu Puntaje</h6>
                                <div class="fs-1 fw-bold <?= ($intento['puntaje_obtenido'] ?? 0) >= $intento['puntaje_minimo_aprobacion'] ? 'text-success' : 'text-warning' ?>">
                                    <?php if ($intento['puntaje_obtenido'] !== null): ?>
                                        <?= number_format((float)$intento['puntaje_obtenido'], 1) ?>%
                                    <?php else: ?>
                                        Pendiente
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2">
                                <h6 class="text-muted">Estado</h6>
                                <div class="fs-4">
                                    <?php if ($intento['puntaje_obtenido'] !== null): ?>
                                        <?php if ($intento['puntaje_obtenido'] >= $intento['puntaje_minimo_aprobacion']): ?>
                                            <span class="badge bg-success fs-6 py-2 px-3">✅ APROBADO</span>
                                        <?php else: ?>
                                            <span class="badge fs-6 py-2 px-3 text-white" style="background: #dc3545 !important; color: white !important;">⚠️ NO APROBADO</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge fs-6 py-2 px-3 text-white" style="background: #17a2b8 !important; color: white !important;">⏳ EN REVISIÓN</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($intento['puntaje_obtenido'] !== null): ?>
                        <hr class="my-2">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="text-success fs-4 fw-bold"><?= $respuestas_correctas ?></div>
                                <small class="text-muted">Correctas</small>
                            </div>
                            <div class="col-4">
                                <div class="text-danger fs-4 fw-bold"><?= $respuestas_incorrectas ?></div>
                                <small class="text-muted">Incorrectas</small>
                            </div>
                            <div class="col-4">
                                <div class="text-warning fs-4 fw-bold"><?= $respuestas_pendientes ?></div>
                                <small class="text-muted">Pendientes</small>
                            </div>
                        </div>
                        
                        <?php if ($intento['puntaje_obtenido'] >= $intento['puntaje_minimo_aprobacion']): ?>
                            <div class="mt-3 p-3 rounded" style="background: #e8f5e8; border: 2px solid #28a745;">
                                <div class="text-success">
                                    <strong>🎉 ¡Felicitaciones!</strong><br>
                                    Has aprobado esta evaluación. Puedes continuar con el siguiente módulo del curso.
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="mt-3 p-3 rounded" style="background: #fff3cd; border: 2px solid #ffc107;">
                                <div class="text-warning">
                                    <strong>📚 Sigue practicando</strong><br>
                                    Te recomendamos revisar el material del módulo y volver a intentar la evaluación.
                                    <?php if ($evaluacion['intentos_permitidos'] > 0): ?>
                                        <br><small>Intentos restantes: <?= max(0, $evaluacion['intentos_permitidos'] - ($intento['numero_intento'] ?? 1)) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="mt-3 p-3 rounded" style="background: #e7f3ff; border: 2px solid #007bff;">
                            <div class="text-info">
                                <strong>⏳ Evaluación en proceso</strong><br>
                                Tu evaluación contiene preguntas que requieren revisión manual. Te notificaremos cuando esté lista la calificación final.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mt-4 text-center">
                <a href="curso_contenido.php?id=<?= $intento['curso_id'] ?>" class="btn-volver">
                    <img src="../styles/iconos/back.png" alt="Volver" style="width: 16px; height: 16px; margin-right: 8px; vertical-align: middle;">
                    Volver al Curso
                </a>
            </div>
        </div>
    </div>

<?php require __DIR__ . '/../partials/footer.php'; ?>