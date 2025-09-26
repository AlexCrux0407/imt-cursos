<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

// Procesar solicitudes POST para marcar progreso
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => 'Error desconocido'];
    
    $tipo = $_POST['tipo'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $usuario_id = (int)($_SESSION['user_id'] ?? 0);
    
    if ($id <= 0 || $usuario_id <= 0) {
        $response['message'] = 'Parámetros inválidos';
        echo json_encode($response);
        exit;
    }
    
    try {
        $conn->beginTransaction();
        
        switch ($tipo) {
            case 'leccion':
                // Marcar lección como completada
                $stmt = $conn->prepare("
                    INSERT INTO progreso_lecciones (usuario_id, leccion_id, completado, fecha_completado)
                    VALUES (:usuario_id, :leccion_id, 1, NOW())
                    ON DUPLICATE KEY UPDATE completado = 1, fecha_completado = NOW()
                ");
                $stmt->execute([':usuario_id' => $usuario_id, ':leccion_id' => $id]);
                
                // Verificar si todas las lecciones del subtema están completadas
                $stmt = $conn->prepare("
                    SELECT l.subtema_id, COUNT(l.id) AS total_lecciones,
                           SUM(CASE WHEN pl.completado = 1 THEN 1 ELSE 0 END) AS lecciones_completadas
                    FROM lecciones l
                    LEFT JOIN progreso_lecciones pl ON l.id = pl.leccion_id AND pl.usuario_id = :usuario_id
                    WHERE l.id = :leccion_id
                    GROUP BY l.subtema_id
                ");
                $stmt->execute([':usuario_id' => $usuario_id, ':leccion_id' => $id]);
                $subtema_info = $stmt->fetch();
                
                if ($subtema_info && $subtema_info['total_lecciones'] == $subtema_info['lecciones_completadas']) {
                    // Todas las lecciones del subtema están completadas, marcar tema como completado
                    $stmt = $conn->prepare("
                        SELECT tema_id FROM subtemas WHERE id = :subtema_id
                    ");
                    $stmt->execute([':subtema_id' => $subtema_info['subtema_id']]);
                    $tema_id = $stmt->fetchColumn();
                    
                    if ($tema_id) {
                        // Marcar tema como completado
                        $stmt = $conn->prepare("
                            INSERT INTO progreso_temas (usuario_id, tema_id, completado, fecha_completado)
                            VALUES (:usuario_id, :tema_id, 1, NOW())
                            ON DUPLICATE KEY UPDATE completado = 1, fecha_completado = NOW()
                        ");
                        $stmt->execute([':usuario_id' => $usuario_id, ':tema_id' => $tema_id]);
                        
                        // Verificar si todos los temas del módulo están completados
                        $stmt = $conn->prepare("
                            SELECT t.modulo_id, COUNT(t.id) AS total_temas,
                                   SUM(CASE WHEN pt.completado = 1 THEN 1 ELSE 0 END) AS temas_completados
                            FROM temas t
                            LEFT JOIN progreso_temas pt ON t.id = pt.tema_id AND pt.usuario_id = :usuario_id
                            WHERE t.id = :tema_id
                            GROUP BY t.modulo_id
                        ");
                        $stmt->execute([':usuario_id' => $usuario_id, ':tema_id' => $tema_id]);
                        $modulo_info = $stmt->fetch();
                        
                        if ($modulo_info && $modulo_info['total_temas'] == $modulo_info['temas_completados']) {
                            // Todos los temas del módulo están completados, marcar módulo como completado
                            $stmt = $conn->prepare("
                                INSERT INTO progreso_modulos (usuario_id, modulo_id, completado, fecha_completado)
                                VALUES (:usuario_id, :modulo_id, 1, NOW())
                                ON DUPLICATE KEY UPDATE completado = 1, fecha_completado = NOW()
                            ");
                            $stmt->execute([':usuario_id' => $usuario_id, ':modulo_id' => $modulo_info['modulo_id']]);
                            
                            // Verificar si todos los módulos del curso están completados
                            $stmt = $conn->prepare("
                                SELECT m.curso_id, COUNT(m.id) AS total_modulos,
                                       SUM(CASE WHEN pm.completado = 1 THEN 1 ELSE 0 END) AS modulos_completados
                                FROM modulos m
                                LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = :usuario_id
                                WHERE m.id = :modulo_id
                                GROUP BY m.curso_id
                            ");
                            $stmt->execute([':usuario_id' => $usuario_id, ':modulo_id' => $modulo_info['modulo_id']]);
                            $curso_info = $stmt->fetch();
                            
                            if ($curso_info && $curso_info['total_modulos'] == $curso_info['modulos_completados']) {
                                // Todos los módulos del curso están completados, marcar curso como completado
                                $stmt = $conn->prepare("
                                    UPDATE inscripciones
                                    SET progreso = 100, fecha_completado = NOW()
                                    WHERE usuario_id = :usuario_id AND curso_id = :curso_id
                                ");
                                $stmt->execute([':usuario_id' => $usuario_id, ':curso_id' => $curso_info['curso_id']]);
                            }
                        }
                    }
                }
                break;
                
            case 'tema':
                // Marcar tema como completado
                $stmt = $conn->prepare("
                    INSERT INTO progreso_temas (usuario_id, tema_id, completado, fecha_completado)
                    VALUES (:usuario_id, :tema_id, 1, NOW())
                    ON DUPLICATE KEY UPDATE completado = 1, fecha_completado = NOW()
                ");
                $stmt->execute([':usuario_id' => $usuario_id, ':tema_id' => $id]);
                
                // Verificar si todos los temas del módulo están completados
                $stmt = $conn->prepare("
                    SELECT t.modulo_id, COUNT(t.id) AS total_temas,
                           SUM(CASE WHEN pt.completado = 1 THEN 1 ELSE 0 END) AS temas_completados
                    FROM temas t
                    LEFT JOIN progreso_temas pt ON t.id = pt.tema_id AND pt.usuario_id = :usuario_id
                    WHERE t.modulo_id = (SELECT modulo_id FROM temas WHERE id = :tema_id)
                    GROUP BY t.modulo_id
                ");
                $stmt->execute([':usuario_id' => $usuario_id, ':tema_id' => $id]);
                $modulo_info = $stmt->fetch();
                
                if ($modulo_info && $modulo_info['total_temas'] == $modulo_info['temas_completados']) {
                    // Todos los temas del módulo están completados, marcar módulo como completado
                    $stmt = $conn->prepare("
                        INSERT INTO progreso_modulos (usuario_id, modulo_id, completado, fecha_completado)
                        VALUES (:usuario_id, :modulo_id, 1, NOW())
                        ON DUPLICATE KEY UPDATE completado = 1, fecha_completado = NOW()
                    ");
                    $stmt->execute([':usuario_id' => $usuario_id, ':modulo_id' => $modulo_info['modulo_id']]);
                    
                    // Verificar si todos los módulos del curso están completados
                    $stmt = $conn->prepare("
                        SELECT m.curso_id, COUNT(m.id) AS total_modulos,
                               SUM(CASE WHEN pm.completado = 1 THEN 1 ELSE 0 END) AS modulos_completados
                        FROM modulos m
                        LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = :usuario_id
                        WHERE m.curso_id = (SELECT curso_id FROM modulos WHERE id = :modulo_id)
                        GROUP BY m.curso_id
                    ");
                    $stmt->execute([':usuario_id' => $usuario_id, ':modulo_id' => $modulo_info['modulo_id']]);
                    $curso_info = $stmt->fetch();
                    
                    if ($curso_info && $curso_info['total_modulos'] == $curso_info['modulos_completados']) {
                        // Todos los módulos del curso están completados, marcar curso como completado
                        $stmt = $conn->prepare("
                            UPDATE inscripciones
                            SET progreso = 100, fecha_completado = NOW()
                            WHERE usuario_id = :usuario_id AND curso_id = :curso_id
                        ");
                        $stmt->execute([':usuario_id' => $usuario_id, ':curso_id' => $curso_info['curso_id']]);
                    }
                }
                break;
                
            case 'modulo':
                // Marcar módulo como completado
                $stmt = $conn->prepare("
                    INSERT INTO progreso_modulos (usuario_id, modulo_id, completado, fecha_completado)
                    VALUES (:usuario_id, :modulo_id, 1, NOW())
                    ON DUPLICATE KEY UPDATE completado = 1, fecha_completado = NOW()
                ");
                $stmt->execute([':usuario_id' => $usuario_id, ':modulo_id' => $id]);
                
                // Verificar si todos los módulos del curso están completados
                $stmt = $conn->prepare("
                    SELECT m.curso_id, COUNT(m.id) AS total_modulos,
                           SUM(CASE WHEN pm.completado = 1 THEN 1 ELSE 0 END) AS modulos_completados
                    FROM modulos m
                    LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = :usuario_id
                    WHERE m.curso_id = (SELECT curso_id FROM modulos WHERE id = :modulo_id)
                    GROUP BY m.curso_id
                ");
                $stmt->execute([':usuario_id' => $usuario_id, ':modulo_id' => $id]);
                $curso_info = $stmt->fetch();
                
                if ($curso_info && $curso_info['total_modulos'] == $curso_info['modulos_completados']) {
                    // Todos los módulos del curso están completados, marcar curso como completado
                    $stmt = $conn->prepare("
                        UPDATE inscripciones
                        SET progreso = 100, fecha_completado = NOW()
                        WHERE usuario_id = :usuario_id AND curso_id = :curso_id
                    ");
                    $stmt->execute([':usuario_id' => $usuario_id, ':curso_id' => $curso_info['curso_id']]);
                }
                break;
                
            default:
                $response['message'] = 'Tipo de contenido no válido';
                echo json_encode($response);
                $conn->rollBack();
                exit;
        }
        
        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Progreso actualizado correctamente';
    } catch (PDOException $e) {
        $conn->rollBack();
        $response['message'] = 'Error en la base de datos: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}
?>

// Función para marcar como completado un módulo, tema o lección
function marcarComoCompletado(tipo, id) {
    fetch('marcar_progreso.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `tipo=${tipo}&id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recargar la página para mostrar los cambios
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al procesar la solicitud');
    });
}
