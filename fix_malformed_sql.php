<?php


$files_to_fix = [
    'public/docente/eliminar_modulo.php',
    'public/docente/eliminar_leccion.php', 
    'public/docente/eliminar_pregunta.php',
    'public/docente/lecciones_modulo.php',
    'public/docente/procesar_pregunta.php',
    'public/docente/editar_subtema.php',
    'public/docente/editar_leccion.php',
    'public/docente/eliminar_tema.php',
    'public/docente/procesar_leccion.php',
    'public/docente/cambiar_estado_evaluacion.php',
    'public/docente/actualizar_modulo.php',
    'public/docente/evaluaciones_modulo.php',
    'public/docente/actualizar_leccion.php',
    'public/docente/mover_pregunta.php',
    'public/docente/procesar_tema.php',
    'public/docente/editar_tema.php',
    'public/docente/procesar_subtema.php',
    'public/docente/eliminar_subtema.php',
    'public/docente/validar_orden.php',
    'public/docente/preguntas_evaluacion.php',
    'public/docente/editar_modulo.php',
    'public/docente/actualizar_tema.php',
    'public/docente/procesar_evaluacion.php',
    'public/docente/actualizar_subtema.php'
];

$fixed_count = 0;

foreach ($files_to_fix as $file) {
    $file_path = __DIR__ . '/' . $file;
    
    if (!file_exists($file_path)) {
        echo "❌ Archivo no encontrado: $file\n";
        continue;
    }
    
    $content = file_get_contents($file_path);
    $original_content = $content;
    
    // 1. Limpiar múltiples "AND (" repetidos - patrón más específico
    $content = preg_replace('/AND\s+\(\s*AND\s+\(\s*AND\s+\(/', 'AND (', $content);
    
    // 2. Limpiar paréntesis sin cerrar correctamente
    $content = preg_replace('/WHERE\s+([^=]+)\s*=\s*:([^)]+)\s+AND\s+\(\s*AND\s+\(\s*AND\s+\(([^)]+)\)/', 'WHERE $1 = :$2 AND ($3)', $content);
    
    // 3. Patrón específico para el error encontrado
    $content = preg_replace('/WHERE\s+([^=]+)\s*=\s*:([^)]+)\s+AND\s+\(AND\s+\(\s*AND\s+\(([^)]+)\)/', 'WHERE $1 = :$2 AND ($3)', $content);
    
    // 4. Corregir execute() que no tenga :docente_id2 cuando se necesite
    if (strpos($content, ':docente_id2') !== false) {
        // Buscar execute() que solo tenga algunos parámetros
        $content = preg_replace_callback(
            '/(\$stmt->execute\(\[[^\]]*?)(\]\);)/s',
            function($matches) {
                $execute_content = $matches[1];
                // Si tiene :docente_id2 en la consulta pero no en execute()
                if (strpos($execute_content, ':docente_id2') === false) {
                    // Agregar :docente_id2
                    return $execute_content . ", ':docente_id2' => \$_SESSION['user_id']" . $matches[2];
                }
                return $matches[0];
            },
            $content
        );
    }
    
    if ($content !== $original_content) {
        file_put_contents($file_path, $content);
        echo "✅ Corregido: $file\n";
        $fixed_count++;
    } else {
        echo "ℹ️  Sin cambios: $file\n";
    }
}

echo "\n🎉 Proceso completado. Archivos corregidos: $fixed_count\n";
?>