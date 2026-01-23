<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('docente');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $modulo_id = (int)($_POST['modulo_id'] ?? 0);
    $curso_id = (int)($_POST['curso_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo = $_POST['tipo'] ?? 'examen';
    $puntaje_maximo = (float)($_POST['puntaje_maximo'] ?? 100);
    $puntaje_minimo_aprobacion = (float)($_POST['puntaje_minimo_aprobacion'] ?? 70);
    $tiempo_limite = !empty($_POST['tiempo_limite']) ? (int)$_POST['tiempo_limite'] : null;
    $intentos_permitidos = (int)($_POST['intentos_permitidos'] ?? 1);
    $instrucciones = trim($_POST['instrucciones'] ?? '');
    $obligatorio = isset($_POST['obligatorio']) ? 1 : 0;
    $orden = (int)($_POST['orden'] ?? 1);
    
    if (empty($titulo) || $modulo_id === 0) {
        header('Location: ' . BASE_URL . '/docente/evaluaciones_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=datos_invalidos');
        exit;
    }
    
    // Verificar que el módulo pertenece a un curso del docente
    $stmt = $conn->prepare("
        SELECT m.id FROM modulos m
        INNER JOIN cursos c ON m.curso_id = c.id
        WHERE m.id = :modulo_id AND (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)
    ");
    $stmt->execute([
        ':modulo_id' => $modulo_id, 
        ':docente_id' => $_SESSION['user_id'],
        ':docente_id2' => $_SESSION['user_id']
    ]);
    
    if (!$stmt->fetch()) {
        header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=acceso_denegado');
        exit;
    }
    
    // Validar que el puntaje mínimo no sea mayor al máximo
    if ($puntaje_minimo_aprobacion > $puntaje_maximo) {
        header('Location: ' . BASE_URL . '/docente/evaluaciones_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=puntaje_invalido');
        exit;
    }
    // Asegurar columnas de fechas permiten NULL y sanear valores cero
    try {
        $infoInicio = $conn->prepare("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evaluaciones_modulo' AND COLUMN_NAME = 'fecha_inicio'");
        $infoInicio->execute();
        $colInicio = $infoInicio->fetch();
        if ($colInicio && strtoupper($colInicio['IS_NULLABLE']) !== 'YES') {
            $conn->exec("ALTER TABLE evaluaciones_modulo MODIFY COLUMN fecha_inicio DATETIME NULL DEFAULT NULL");
        }

        $infoFin = $conn->prepare("SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evaluaciones_modulo' AND COLUMN_NAME = 'fecha_fin'");
        $infoFin->execute();
        $colFin = $infoFin->fetch();
        if ($colFin && strtoupper($colFin['IS_NULLABLE']) !== 'YES') {
            $conn->exec("ALTER TABLE evaluaciones_modulo MODIFY COLUMN fecha_fin DATETIME NULL DEFAULT NULL");
        }

        $stmtSanear = $conn->prepare("UPDATE evaluaciones_modulo SET fecha_inicio = CASE WHEN fecha_inicio = '0000-00-00 00:00:00' THEN NULL ELSE fecha_inicio END, fecha_fin = CASE WHEN fecha_fin = '0000-00-00 00:00:00' THEN NULL ELSE fecha_fin END WHERE modulo_id = :modulo_id");
        $stmtSanear->execute([':modulo_id' => $modulo_id]);
    } catch (Exception $e) {
        // No interrumpir creación por fallos de migración/saneamiento
    }
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO evaluaciones_modulo (
                modulo_id, titulo, descripcion, tipo, puntaje_maximo, 
                puntaje_minimo_aprobacion, tiempo_limite, intentos_permitidos, 
                obligatorio, orden, instrucciones, activo
            ) VALUES (
                :modulo_id, :titulo, :descripcion, :tipo, :puntaje_maximo,
                :puntaje_minimo_aprobacion, :tiempo_limite, :intentos_permitidos,
                :obligatorio, :orden, :instrucciones, 1
            )
        ");
        
        $stmt->execute([
            ':modulo_id' => $modulo_id,
            ':titulo' => $titulo,
            ':descripcion' => $descripcion,
            ':tipo' => $tipo,
            ':puntaje_maximo' => $puntaje_maximo,
            ':puntaje_minimo_aprobacion' => $puntaje_minimo_aprobacion,
            ':tiempo_limite' => $tiempo_limite,
            ':intentos_permitidos' => $intentos_permitidos,
            ':obligatorio' => $obligatorio,
            ':orden' => $orden,
            ':instrucciones' => $instrucciones
        ]);
        
        $evaluacion_id = $conn->lastInsertId();

        // Establecer explícitamente NULL en fechas para evitar defaults de fecha cero
        try {
            $stmtFechas = $conn->prepare("UPDATE evaluaciones_modulo SET fecha_inicio = NULL, fecha_fin = NULL WHERE id = :id");
            $stmtFechas->execute([':id' => $evaluacion_id]);
        } catch (Exception $e) {
            // Continuar incluso si no se pueden actualizar fechas
        }
        
        header('Location: ' . BASE_URL . '/docente/preguntas_evaluacion.php?id=' . $evaluacion_id . '&modulo_id=' . $modulo_id . '&curso_id=' . $curso_id . '&success=evaluacion_creada');
        exit;
        
    } catch (Exception $e) {
        error_log("Error creando evaluación: " . $e->getMessage());
        header('Location: ' . BASE_URL . '/docente/evaluaciones_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=error_crear');
        exit;
    }
} else {
    header('Location: ' . BASE_URL . '/docente/admin_cursos.php');
    exit;
}
?>