<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/paths.php';

/*
 Utilidades de Autenticación
 - Verifica sesión y roles, protege rutas.
 - Redirige al login y cierra sesión con limpieza de cookies.
 */

/**
 * Indica si hay sesión iniciada con rol.
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['role']);
}

/**
 * Redirige al login si no hay sesión.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php?m=auth');
        exit;
    }
}

/**
 * Exige un rol específico o redirige al login.
 */
function require_role($required_role)
{
    if (!isset($_SESSION['role'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }

    $user_role = strtolower($_SESSION['role']);
    $required_role = strtolower($required_role);
    if ($required_role === 'estudiante' && $user_role === 'estudiante') {
        return;
    }

    if ($user_role !== $required_role) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Cierra sesión y redirige al login.
 */
function logout_and_redirect(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php?m=logout');
    exit;
}

function split_nombre_apellidos(string $full): array
{
    $clean = trim(preg_replace('/\s+/', ' ', $full));
    if ($clean === '') {
        return ['', ''];
    }
    $raw_parts = preg_split('/\s+/', $clean);

    $connectors = ['de', 'del', 'la', 'las', 'los', 'y', 'san', 'santa', 'mac', 'mc', 'van', 'von', 'da', 'di', 'do', 'dos'];
    $parts = [];
    $buffer = [];

    foreach ($raw_parts as $word) {
        $lower_word = function_exists('mb_strtolower') ? mb_strtolower($word, 'UTF-8') : strtolower($word);
        if (in_array($lower_word, $connectors, true)) {
            $buffer[] = $word;
        } else {
            if (!empty($buffer)) {
                $parts[] = implode(' ', $buffer) . ' ' . $word;
                $buffer = [];
            } else {
                $parts[] = $word;
            }
        }
    }

    if (!empty($buffer)) {
        if (!empty($parts)) {
            $parts[count($parts) - 1] .= ' ' . implode(' ', $buffer);
        } else {
            $parts[] = implode(' ', $buffer);
        }
    }

    $count = count($parts);
    if ($count <= 1) {
        return [$clean, ''];
    }
    if ($count === 2) {
        return [$parts[0], $parts[1]];
    }

    $toLower = function (string $value): string {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    };
    $lower = array_map($toLower, $parts);
    $second_names = [
        'jose',
        'maría',
        'maria',
        'juan',
        'carlos',
        'luis',
        'ana',
        'luisa',
        'jesus',
        'jesús',
        'miguel',
        'angel',
        'ángel',
        'javier',
        'francisco',
        'antonio',
        'pedro',
        'andrea',
        'paula',
        'camila',
        'sofia',
        'sofía',
        'lucia',
        'lucía',
        'david',
        'daniel',
        'fernando',
        'jorge',
        'alejandro',
        'mariana'
    ];
    if ($count === 3) {
        $segunda_palabra = explode(' ', $lower[1])[0];
        if (in_array($segunda_palabra, $second_names, true) || in_array($lower[1], $second_names, true)) {
            return [$parts[0] . ' ' . $parts[1], $parts[2]];
        }
        return [$parts[0], $parts[1] . ' ' . $parts[2]];
    }
    $apellidos = implode(' ', array_slice($parts, -2));
    $nombres = implode(' ', array_slice($parts, 0, -2));
    return [$nombres, $apellidos];
}

function format_nombre(string $full, string $orden = 'apellidos_nombres'): string
{
    $clean = trim($full);
    if ($clean === '') {
        return $clean;
    }
    list($nombres, $apellidos) = split_nombre_apellidos($clean);
    if ($apellidos === '') {
        return $nombres;
    }
    if ($orden === 'nombres_apellidos') {
        return trim($nombres . ' ' . $apellidos);
    }
    return trim($apellidos . ' ' . $nombres);
}

function calcularCalificacionFinal($cursoId, $usuarioId)
{
    global $conn;
    $cursoId = (int) $cursoId;
    $usuarioId = (int) $usuarioId;
    if (!$conn || $cursoId <= 0 || $usuarioId <= 0) {
        return null;
    }

    $calcularPromedioActual = function () use ($conn, $cursoId, $usuarioId) {
        $stmt = $conn->prepare("SELECT AVG(CASE WHEN ie.puntaje_obtenido IS NOT NULL THEN ie.puntaje_obtenido ELSE NULL END) as promedio FROM modulos m LEFT JOIN evaluaciones_modulo em ON m.id = em.modulo_id LEFT JOIN intentos_evaluacion ie ON em.id = ie.evaluacion_id AND ie.usuario_id = :uid WHERE m.curso_id = :cid");
        $stmt->execute([':uid' => $usuarioId, ':cid' => $cursoId]);
        $row = $stmt->fetch();
        if ($row && $row['promedio'] !== null) {
            return round((float) $row['promedio'], 1);
        }
        return null;
    };

    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('curso_calificacion_config','evaluacion_peso_config')");
        $stmt->execute();
        $tablesCount = (int) $stmt->fetchColumn();
        if ($tablesCount < 2) {
            return $calcularPromedioActual();
        }
    } catch (Throwable $e) {
        return $calcularPromedioActual();
    }

    $stmt = $conn->prepare("SELECT activo, escala FROM curso_calificacion_config WHERE curso_id = :cid LIMIT 1");
    $stmt->execute([':cid' => $cursoId]);
    $config = $stmt->fetch();
    if (!$config || (int) $config['activo'] !== 1) {
        return $calcularPromedioActual();
    }

    $stmt = $conn->prepare("
        SELECT e.id as evaluacion_id, COALESCE(epc.peso_porcentual, 0) as peso_porcentual
        FROM evaluaciones_modulo e
        INNER JOIN modulos m ON e.modulo_id = m.id
        LEFT JOIN evaluacion_peso_config epc ON epc.evaluacion_id = e.id
        WHERE m.curso_id = :cid
    ");
    $stmt->execute([':cid' => $cursoId]);
    $pesos = $stmt->fetchAll();
    if (!$pesos) {
        return null;
    }

    $suma = 0.0;
    $evalIds = [];
    foreach ($pesos as $peso) {
        $suma += (float) $peso['peso_porcentual'];
        $evalIds[] = (int) $peso['evaluacion_id'];
    }
    if (abs($suma - 100.0) > 0.01) {
        return null;
    }

    $placeholders = implode(',', array_fill(0, count($evalIds), '?'));
    $stmt = $conn->prepare("SELECT evaluacion_id, MAX(puntaje_obtenido) as mejor FROM intentos_evaluacion WHERE usuario_id = ? AND evaluacion_id IN ($placeholders) GROUP BY evaluacion_id");
    $params = array_merge([$usuarioId], $evalIds);
    $stmt->execute($params);
    $mejores = [];
    foreach ($stmt->fetchAll() as $row) {
        $mejores[(int) $row['evaluacion_id']] = $row['mejor'] !== null ? (float) $row['mejor'] : null;
    }

    $total = 0.0;
    $tieneIntentos = false;
    foreach ($pesos as $peso) {
        $evalId = (int) $peso['evaluacion_id'];
        $puntaje = $mejores[$evalId] ?? null;
        if ($puntaje !== null) {
            $tieneIntentos = true;
        }
        $total += ((float) ($puntaje ?? 0)) * ((float) $peso['peso_porcentual'] / 100.0);
    }

    if (!$tieneIntentos) {
        return null;
    }

    if ((string) ($config['escala'] ?? '100') === '10') {
        $total = $total / 10.0;
    }

    return round($total, 1);
}
