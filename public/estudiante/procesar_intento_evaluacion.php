<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('estudiante');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/estudiante/dashboard.php');
    exit;
}

$evaluacion_id = (int)($_POST['evaluacion_id'] ?? 0);
$usuario_id = (int)($_SESSION['user_id'] ?? 0);

if ($evaluacion_id <= 0 || $usuario_id <= 0) {
    header('Location: ' . BASE_URL . '/estudiante/dashboard.php');
    exit;
}



try {
    // Debug: Log para verificar que se está procesando
    error_log("Procesando evaluación - ID: $evaluacion_id, Usuario: $usuario_id");
    
    $conn->beginTransaction();
    
    // Asegurar existencia de la tabla de respuestas del estudiante
    $conn->exec("CREATE TABLE IF NOT EXISTS respuestas_estudiante (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        intento_id INT UNSIGNED NOT NULL,
        pregunta_id INT UNSIGNED NOT NULL,
        respuesta TEXT NULL,
        es_correcta TINYINT(1) NULL,
        requiere_revision TINYINT(1) NOT NULL DEFAULT 0,
        fecha_respuesta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (intento_id) REFERENCES intentos_evaluacion(id) ON DELETE CASCADE,
        FOREIGN KEY (pregunta_id) REFERENCES preguntas_evaluacion(id) ON DELETE CASCADE,
        INDEX idx_intento (intento_id),
        INDEX idx_pregunta (pregunta_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    
    // Obtener información de la evaluación
    $stmt = $conn->prepare("
        SELECT e.*, m.curso_id
        FROM evaluaciones_modulo e
        INNER JOIN modulos m ON e.modulo_id = m.id
        WHERE e.id = :evaluacion_id AND e.activo = 1
    ");
    $stmt->execute([':evaluacion_id' => $evaluacion_id]);
    $evaluacion = $stmt->fetch();
    
    if (!$evaluacion) {
        throw new Exception('Evaluación no encontrada');
    }
    
    // Verificar que el estudiante esté inscrito en el curso
    $stmt = $conn->prepare("
        SELECT id FROM inscripciones 
        WHERE usuario_id = :usuario_id AND curso_id = :curso_id AND estado = 'activo'
    ");
    $stmt->execute([':usuario_id' => $usuario_id, ':curso_id' => $evaluacion['curso_id']]);
    if (!$stmt->fetch()) {
        throw new Exception('No tienes acceso a esta evaluación');
    }
    
    // Verificar si ya completó la evaluación con 100%
    $stmt = $conn->prepare("
        SELECT MAX(puntaje_obtenido) as mejor_puntaje
        FROM intentos_evaluacion
        WHERE usuario_id = :usuario_id AND evaluacion_id = :evaluacion_id
    ");
    $stmt->execute([':usuario_id' => $usuario_id, ':evaluacion_id' => $evaluacion_id]);
    $resultado_puntaje = $stmt->fetch();
    if ($resultado_puntaje && $resultado_puntaje['mejor_puntaje'] >= 100.0) {
        throw new Exception('Ya has completado esta evaluación con un puntaje perfecto de 100%');
    }
    
    // Contar intentos previos
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_intentos
        FROM intentos_evaluacion
        WHERE usuario_id = :usuario_id AND evaluacion_id = :evaluacion_id
    ");
    $stmt->execute([':usuario_id' => $usuario_id, ':evaluacion_id' => $evaluacion_id]);
    $intentos_previos = $stmt->fetch()['total_intentos'];
    
    if ($intentos_previos >= $evaluacion['intentos_permitidos']) {
        throw new Exception('Has agotado el número máximo de intentos para esta evaluación');
    }
    
    // Obtener preguntas de la evaluación
    $stmt = $conn->prepare("
        SELECT * FROM preguntas_evaluacion
        WHERE evaluacion_id = :evaluacion_id
        ORDER BY orden ASC
    ");
    $stmt->execute([':evaluacion_id' => $evaluacion_id]);
    $preguntas = $stmt->fetchAll();
    
    if (empty($preguntas)) {
        throw new Exception('La evaluación no tiene preguntas configuradas');
    }
    
    // Crear nuevo intento de evaluación
    $numero_intento = $intentos_previos + 1;
    $stmt = $conn->prepare("
        INSERT INTO intentos_evaluacion (
            evaluacion_id, 
            usuario_id, 
            numero_intento, 
            puntaje_maximo, 
            estado
        ) VALUES (
            :evaluacion_id, 
            :usuario_id, 
            :numero_intento, 
            :puntaje_maximo, 
            'en_progreso'
        )
    ");
    $stmt->execute([
        ':evaluacion_id' => $evaluacion_id,
        ':usuario_id' => $usuario_id,
        ':numero_intento' => $numero_intento,
        ':puntaje_maximo' => $evaluacion['puntaje_maximo']
    ]);
    
    $intento_id = $conn->lastInsertId();
    
    // Commit de la primera transacción
    if ($conn->inTransaction()) {
        $conn->commit();
    } else {
        error_log("ADVERTENCIA: No hay transacción activa para hacer commit en línea 129");
    }
    
    // Segunda transacción: Procesar respuestas y calcular puntaje
    $conn->beginTransaction();
    try {
        // Obtener preguntas de la evaluación
        $stmt = $conn->prepare("
            SELECT * FROM preguntas_evaluacion 
            WHERE evaluacion_id = :evaluacion_id
            ORDER BY orden ASC
        ");
        $stmt->execute([':evaluacion_id' => $evaluacion_id]);
        $preguntas = $stmt->fetchAll();
        
        if (empty($preguntas)) {
            throw new Exception('No se encontraron preguntas para esta evaluación');
        }
        
        // Procesar respuestas y calcular puntaje
        $respuestas_correctas = 0;
        $total_respuestas = 0; // Cambiar de total_preguntas a total_respuestas
        $respuestas_procesadas = [];
        
        error_log("=== DEBUG CÁLCULO PUNTAJE ===");
        error_log("DEBUG - Total preguntas originales: " . count($preguntas));
        
        foreach ($preguntas as $pregunta) {
            $respuesta_estudiante = $_POST['respuesta_' . $pregunta['id']] ?? '';
            $es_correcta = false;
            
            // Evaluar respuesta según el tipo de pregunta
            switch ($pregunta['tipo']) {
                case 'multiple_choice':
                    $opciones = json_decode($pregunta['opciones'], true);
                    $respuesta_correcta = $pregunta['respuesta_correcta'];
                    $es_correcta = ($respuesta_estudiante === $respuesta_correcta);
                    break;
                    
                case 'verdadero_falso':
                    $respuesta_correcta = $pregunta['respuesta_correcta'];
                    $es_correcta = ($respuesta_estudiante === $respuesta_correcta);
                    break;
                    
                case 'texto_corto':
                case 'texto_largo':
                    // Para respuestas cortas, marcar como pendiente de revisión manual
                    $es_correcta = null; // null indica que requiere revisión manual
                    break;

                case 'seleccion_multiple':
                    $opciones = json_decode($pregunta['opciones'], true) ?: [];
                    $correctas = json_decode($pregunta['respuesta_correcta'], true) ?: [];
                    // respuesta_estudiante puede ser string o array, normalizar a array de índices
                    $resp = $_POST['respuesta_' . $pregunta['id']] ?? [];
                    if (!is_array($resp)) $resp = [$resp];
                    sort($resp);
                    sort($correctas);
                    $es_correcta = ($resp == $correctas);
                    $respuesta_estudiante = json_encode($resp);
                    break;

                 case 'emparejar_columnas':
                 case 'relacionar_pares':
                     // respuesta es un array mapping índice -> valor derecha seleccionado
                     $resp_raw = $_POST['respuesta_' . $pregunta['id']] ?? [];
                     
                     // Si la respuesta viene como string JSON, decodificarla
                     if (is_string($resp_raw)) {
                         $resp = json_decode($resp_raw, true) ?: [];
                     } else {
                         $resp = $resp_raw;
                     }
                     
                     $correctas = json_decode($pregunta['respuesta_correcta'], true) ?: [];
                     
                     // Debug logging detallado
                     error_log("=== DEBUG EMPAREJAR/RELACIONAR ===");
                     error_log("DEBUG - Pregunta ID: " . $pregunta['id']);
                     error_log("DEBUG - Tipo: " . $pregunta['tipo']);
                     error_log("DEBUG - Pregunta texto: " . $pregunta['pregunta']);
                     error_log("DEBUG - POST data RAW: " . json_encode($resp_raw));
                     error_log("DEBUG - POST data PROCESSED: " . json_encode($resp));
                     error_log("DEBUG - Respuesta correcta RAW: " . $pregunta['respuesta_correcta']);
                     error_log("DEBUG - Respuesta correcta PARSED: " . json_encode($correctas));
                     error_log("DEBUG - Tipo de respuesta estudiante RAW: " . gettype($resp_raw));
                     error_log("DEBUG - Tipo de respuesta estudiante PROCESSED: " . gettype($resp));
                     
                     // Tanto emparejar_columnas como relacionar_pares se evalúan por pares individuales
                     $total_pares = count($correctas);
                     $pares_correctos = 0;
                     
                     error_log("DEBUG - Total de pares esperados: " . $total_pares);
                     
                     // Insertar una respuesta por cada par
                     foreach ($correctas as $indice => $valor_correcto) {
                         $respuesta_estudiante_par = $resp[$indice] ?? null;
                         $par_correcto = isset($resp[$indice]) && $resp[$indice] == $valor_correcto;
                         
                         error_log("DEBUG - Par $indice:");
                         error_log("  - Respuesta estudiante: " . json_encode($respuesta_estudiante_par));
                         error_log("  - Respuesta correcta: " . json_encode($valor_correcto));
                         error_log("  - Comparación (==): " . ($resp[$indice] == $valor_correcto ? 'true' : 'false'));
                         error_log("  - Comparación (===): " . ($resp[$indice] === $valor_correcto ? 'true' : 'false'));
                         error_log("  - Par correcto: " . ($par_correcto ? 'true' : 'false'));
                         
                         if ($par_correcto) {
                             $pares_correctos++;
                         }
                         
                         // Crear respuesta individual para cada par
                         $respuesta_par = json_encode([
                             'par_indice' => $indice,
                             'respuesta_estudiante' => $respuesta_estudiante_par,
                             'respuesta_correcta' => $valor_correcto
                         ]);
                         
                         // Insertar respuesta individual para este par
                         $stmt_par = $conn->prepare("
                             INSERT INTO respuestas_estudiante (intento_id, pregunta_id, respuesta, es_correcta, requiere_revision)
                             VALUES (:intento_id, :pregunta_id, :respuesta, :es_correcta, :requiere_revision)
                         ");
                         
                         // Crear respuesta JSON con información del par
                         $respuesta_par_json = json_encode([
                             'par_indice' => $indice,
                             'respuesta_estudiante' => $respuesta_estudiante_par,
                             'respuesta_correcta' => $valor_correcto,
                             'par_correcto' => $par_correcto
                         ]);
                         
                         $stmt_par->execute([
                             ':intento_id' => $intento_id,
                             ':pregunta_id' => $pregunta['id'], // Solo el ID numérico de la pregunta
                             ':respuesta' => $respuesta_par_json,
                             ':es_correcta' => $par_correcto ? 1 : 0,
                             ':requiere_revision' => 0
                         ]);
                         
                         error_log("DEBUG - Insertado par $indice con es_correcta: " . ($par_correcto ? 1 : 0));
                         
                         if ($par_correcto) {
                             $respuestas_correctas++;
                         }
                         $total_respuestas++; // Incrementar por cada par
                     }
                     
                     error_log("DEBUG - Pares correctos: $pares_correctos de $total_pares");
                     error_log("DEBUG - Respuestas correctas totales hasta ahora: $respuestas_correctas");
                     error_log("DEBUG - Total respuestas hasta ahora: $total_respuestas");
                     error_log("=== FIN DEBUG EMPAREJAR/RELACIONAR ===");
                     
                     // No insertar respuesta general ya que cada par se maneja individualmente
                     continue 2; // Saltar al siguiente pregunta en el bucle principal
                     
                     $respuesta_estudiante = json_encode($resp);
                     break;

                 case 'completar_espacios':
                     $resp = $_POST['respuesta_' . $pregunta['id']] ?? [];
                     $correctas = json_decode($pregunta['respuesta_correcta'], true) ?: [];
                     $es_correcta = is_array($resp) && count($resp) === count($correctas) && array_map('strtolower', array_map('trim', $resp)) === array_map('strtolower', array_map('trim', $correctas));
                     $respuesta_estudiante = json_encode($resp);
                     break;
             }
             
             // Guardar respuesta del estudiante
             $stmt = $conn->prepare("
                 INSERT INTO respuestas_estudiante (intento_id, pregunta_id, respuesta, es_correcta, requiere_revision)
                 VALUES (:intento_id, :pregunta_id, :respuesta, :es_correcta, :requiere_revision)
             ");
             
             // Convertir es_correcta a valor entero apropiado para la base de datos
             $es_correcta_db = null;
             if ($es_correcta === true) {
                 $es_correcta_db = 1;
             } elseif ($es_correcta === false) {
                 $es_correcta_db = 0;
             } // Si es null, se mantiene como null
             
             $stmt->execute([
                 ':intento_id' => $intento_id,
                 ':pregunta_id' => $pregunta['id'],
                 ':respuesta' => $respuesta_estudiante,
                 ':es_correcta' => $es_correcta_db,
                 ':requiere_revision' => ($es_correcta === null) ? 1 : 0
             ]);
             
             if ($es_correcta === true) {
                 $respuestas_correctas++;
             }
             
             $total_respuestas++; // Incrementar por cada pregunta normal
             
             $respuestas_procesadas[] = [
                 'pregunta_id' => $pregunta['id'],
                 'respuesta' => $respuesta_estudiante,
                 'es_correcta' => $es_correcta,
                 'tipo' => $pregunta['tipo']
             ];
         }
         
         error_log("DEBUG - Respuestas correctas finales: $respuestas_correctas");
         error_log("DEBUG - Total respuestas finales: $total_respuestas");
         error_log("=== FIN DEBUG CÁLCULO PUNTAJE ===");
         
         // Calcular puntaje
         $preguntas_automaticas = array_filter($respuestas_procesadas, function($r) {
             return $r['es_correcta'] !== null;
         });
         
         $preguntas_manuales = array_filter($respuestas_procesadas, function($r) {
             return $r['es_correcta'] === null;
         });
         
         if (count($preguntas_manuales) > 0) {
             // Hay preguntas que requieren revisión manual
             $puntaje_obtenido = null;
             $estado_intento = 'completado';
         } else {
             // Todas las preguntas son automáticas: calcular como porcentaje (0-100)
             $puntaje_obtenido = ($total_respuestas > 0)
                 ? (($respuestas_correctas / $total_respuestas) * 100.0)
                 : 0.0;
             $estado_intento = 'completado';
             
             error_log("DEBUG - Cálculo final del puntaje:");
             error_log("  - Respuestas correctas: $respuestas_correctas");
             error_log("  - Total respuestas: $total_respuestas");
             error_log("  - Puntaje calculado: $puntaje_obtenido");
         }
         
         // Actualizar intento con el resultado
         $stmt = $conn->prepare("
             UPDATE intentos_evaluacion 
             SET estado = :estado, puntaje_obtenido = :puntaje, fecha_fin = NOW()
             WHERE id = :intento_id
         ");
         $stmt->execute([
             ':estado' => $estado_intento,
             ':puntaje' => $puntaje_obtenido,
             ':intento_id' => $intento_id
         ]);
         
         $resultado_segunda = [
             'preguntas_manuales' => $preguntas_manuales,
             'puntaje_obtenido' => $puntaje_obtenido,
             'respuestas_correctas' => $respuestas_correctas,
             'total_preguntas' => $total_preguntas
         ];
         
         if ($conn->inTransaction()) {
             $conn->commit();
         } else {
             error_log("ADVERTENCIA: No hay transacción activa para hacer commit en segunda transacción");
         }
     } catch (Exception $e) {
         if ($conn->inTransaction()) {
             $conn->rollBack();
         }
         throw $e;
     }
     
     // Tercera transacción: Operaciones posteriores según el resultado
     $conn->beginTransaction();
     try {
         $preguntas_manuales = $resultado_segunda['preguntas_manuales'];
         $puntaje_obtenido = $resultado_segunda['puntaje_obtenido'];
         
         if (count($preguntas_manuales) > 0) {
             $mensaje = 'Tu evaluación ha sido enviada y está pendiente de revisión por el docente.';
             $tipo = 'info';
             return ['mensaje' => $mensaje, 'tipo' => $tipo];
         } else {
             $aprobado = $puntaje_obtenido >= $evaluacion['puntaje_minimo_aprobacion'];
             if ($aprobado) {
                 // Verificar si todas las evaluaciones del módulo están aprobadas
                 $stmt = $conn->prepare("
                     SELECT COUNT(*) as total_evaluaciones,
                            SUM(CASE WHEN ie.puntaje_obtenido >= e.puntaje_minimo_aprobacion THEN 1 ELSE 0 END) as evaluaciones_aprobadas
                     FROM evaluaciones_modulo e
                     LEFT JOIN (
                         SELECT evaluacion_id, MAX(puntaje_obtenido) as puntaje_obtenido
                         FROM intentos_evaluacion 
                         WHERE usuario_id = :usuario_id
                         GROUP BY evaluacion_id
                     ) ie ON e.id = ie.evaluacion_id
                     WHERE e.modulo_id = :modulo_id AND e.activo = 1
                 ");
                 $stmt->execute([
                     ':usuario_id' => $usuario_id,
                     ':modulo_id' => $evaluacion['modulo_id']
                 ]);
                 $resultado_evaluaciones = $stmt->fetch();
                 
                 // Solo marcar el módulo como completado si TODAS las evaluaciones están aprobadas
                 $todas_aprobadas = ($resultado_evaluaciones['total_evaluaciones'] > 0 && 
                                   $resultado_evaluaciones['evaluaciones_aprobadas'] >= $resultado_evaluaciones['total_evaluaciones']);
                 
                 if ($todas_aprobadas) {
                     // Marcar progreso del módulo como completado
                     $stmt = $conn->prepare("
                         INSERT INTO progreso_modulos (usuario_id, modulo_id, completado, fecha_completado, evaluacion_completada, fecha_evaluacion_completada, puntaje_evaluacion)
                         VALUES (:usuario_id, :modulo_id, 1, NOW(), 1, NOW(), :puntaje)
                         ON DUPLICATE KEY UPDATE 
                             completado = 1, 
                             fecha_completado = NOW(), 
                             evaluacion_completada = 1, 
                             fecha_evaluacion_completada = NOW(), 
                             puntaje_evaluacion = :puntaje_update
                     ");
                     $stmt->execute([
                         ':usuario_id' => $usuario_id,
                         ':modulo_id' => $evaluacion['modulo_id'],
                         ':puntaje' => $puntaje_obtenido,
                         ':puntaje_update' => $puntaje_obtenido
                     ]);
                 }
                 
                 // Actualizar progreso del curso
                 $stmt = $conn->prepare("
                     SELECT m.curso_id, COUNT(m.id) AS total_modulos,
                            SUM(CASE WHEN pm.evaluacion_completada = 1 THEN 1 ELSE 0 END) AS modulos_completados
                     FROM modulos m
                     LEFT JOIN progreso_modulos pm ON m.id = pm.modulo_id AND pm.usuario_id = :usuario_id
                     WHERE m.curso_id = :curso_id
                     GROUP BY m.curso_id
                 ");
                 $stmt->execute([
                     ':usuario_id' => $usuario_id,
                     ':curso_id' => $evaluacion['curso_id']
                 ]);
                 $curso_info = $stmt->fetch();
                 
                 if ($curso_info) {
                      $progreso_porcentaje = ($curso_info['modulos_completados'] / $curso_info['total_modulos']) * 100;
                      $estado_curso = ($progreso_porcentaje >= 100) ? 'completado' : 'activo';
                      
                      $stmt = $conn->prepare("
                          UPDATE inscripciones
                          SET progreso = :progreso, estado = :estado" . 
                          ($estado_curso === 'completado' ? ', fecha_completado = NOW()' : '') . "
                          WHERE usuario_id = :usuario_id AND curso_id = :curso_id
                      ");
                      $stmt->execute([
                          ':progreso' => $progreso_porcentaje,
                          ':estado' => $estado_curso,
                          ':usuario_id' => $usuario_id,
                          ':curso_id' => $evaluacion['curso_id']
                      ]);
                  }
                  
                  $mensaje = '¡Felicitaciones! Has aprobado la evaluación con un puntaje de ' . number_format($puntaje_obtenido, 1) . '%. El módulo ha sido marcado como completado.';
                  $tipo = 'success';
              } else {
                  $mensaje = 'Has obtenido un puntaje de ' . number_format($puntaje_obtenido, 1) . '%. Necesitas al menos ' . $evaluacion['puntaje_minimo_aprobacion'] . '% para aprobar.';
                  $tipo = 'warning';
              }
              
              $resultado_tercera = ['mensaje' => $mensaje, 'tipo' => $tipo];
          }
          
          if ($conn->inTransaction()) {
              $conn->commit();
          } else {
              error_log("ADVERTENCIA: No hay transacción activa para hacer commit en tercera transacción");
          }
      } catch (Exception $e) {
          if ($conn->inTransaction()) {
              $conn->rollBack();
          }
          throw $e;
      }
      
      // Redirigir con mensaje
      $mensaje = $resultado_tercera['mensaje'];
      $tipo = $resultado_tercera['tipo'];
      error_log("Redirigiendo a resultado_evaluacion.php con intento_id: $intento_id");
      $redirect_url = BASE_URL . '/estudiante/resultado_evaluacion.php?intento_id=' . $intento_id . '&mensaje=' . urlencode($mensaje) . '&tipo=' . $tipo;
      header('Location: ' . $redirect_url);
      exit;
      
  } catch (Exception $e) {
      // Debug: Log del error con más detalles
      error_log("=== ERROR EN PROCESAR_INTENTO_EVALUACION ===");
      error_log("Error: " . $e->getMessage());
      error_log("Archivo: " . $e->getFile());
      error_log("Línea: " . $e->getLine());
      error_log("Stack trace: " . $e->getTraceAsString());
      error_log("POST data: " . print_r($_POST, true));
      error_log("SESSION data: " . print_r($_SESSION, true));
      error_log("=== FIN ERROR ===");
      
      // Asegurar que solo se haga rollback si hay una transacción activa
      if (isset($conn)) {
          try {
              // Verificar si hay una transacción activa antes de hacer rollback
              if ($conn->inTransaction()) {
                  $conn->rollBack();
                  error_log("Rollback ejecutado correctamente");
              } else {
                  error_log("No hay transacción activa para hacer rollback");
              }
          } catch (Exception $rollbackException) {
              error_log("Error durante rollback: " . $rollbackException->getMessage());
          }
      }
      $error_message = 'Error al procesar la evaluación: ' . $e->getMessage();
      $curso_id = isset($evaluacion['curso_id']) ? $evaluacion['curso_id'] : null;
      if (!$curso_id && !empty($evaluacion_id)) {
          try {
              $stmt = $conn->prepare("SELECT m.curso_id FROM evaluaciones_modulo e INNER JOIN modulos m ON e.modulo_id = m.id WHERE e.id = :evaluacion_id");
              $stmt->execute([':evaluacion_id' => $evaluacion_id]);
              $row = $stmt->fetch();
              if ($row && isset($row['curso_id'])) {
                  $curso_id = $row['curso_id'];
              }
          } catch (Exception $ignored) {
              // Ignorar errores al intentar obtener curso_id en el manejo de errores
          }
      }
      $redirect_url = $curso_id
          ? BASE_URL . '/estudiante/curso_contenido.php?id=' . $curso_id . '&error=' . urlencode($error_message)
        : BASE_URL . '/estudiante/dashboard.php?error=' . urlencode($error_message);
    error_log("Redirigiendo por error a: " . $redirect_url);
    header('Location: ' . $redirect_url);
    exit;
}
?>