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
        WHERE m.id = :modulo_id AND c.creado_por = :docente_id
    ");
    $stmt->execute([':modulo_id' => $modulo_id, ':docente_id' => $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        header('Location: ' . BASE_URL . '/docente/admin_cursos.php?error=acceso_denegado');
        exit;
    }
    
    // Validar que el puntaje mínimo no sea mayor al máximo
    if ($puntaje_minimo_aprobacion > $puntaje_maximo) {
        header('Location: ' . BASE_URL . '/docente/evaluaciones_modulo.php?id=' . $modulo_id . '&curso_id=' . $curso_id . '&error=puntaje_invalido');
        exit;
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