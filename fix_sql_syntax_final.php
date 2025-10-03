<?php
/**
 * Script final para corregir completamente la sintaxis SQL
 * Limpia errores de sintaxis y corrige parámetros duplicados
 */

$files_to_fix = [
    'public/docente/eliminar_modulo.php',
    'public/docente/eliminar_leccion.php', 
    'public/docente/eliminar_pregunta.php',
    'public/docente/lecciones_modulo.php',
    'public/docente/procesar_pregunta.php',
    'public/docente/editar_subtema.php',
    'public/docente/subtemas_tema.php',
    'public/docente/editar_leccion.php',
    'public/docente/eliminar_tema.php',
    'public/docente/procesar_leccion.php',
    'public/docente/reportes.php',
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
    'public/docente/actualizar_subtema.php',
    'public/docente/modulos_curso.php',
    'public/docente/eliminar_curso.php',
    'public/docente/actualizar_curso.php',
    'public/docente/procesar_modulo.php',
    'public/docente/editar_curso.php'
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
    
    // 1. Limpiar múltiples "AND (" repetidos
    $content = preg_replace('/(\s+AND\s+\(\s*)+/', ' AND (', $content);
    
    // 2. Corregir parámetros duplicados :docente_id en la misma consulta
    // Buscar patrón: (creado_por = :docente_id OR asignado_a = :docente_id)
    $content = preg_replace(
        '/\(([^=]+creado_por\s*=\s*):docente_id(\s+OR\s+[^=]+asignado_a\s*=\s*):docente_id\)/',
        '($1:docente_id$2:docente_id2)',
        $content
    );
    
    // 3. Actualizar los arrays de execute() para incluir :docente_id2
    if (strpos($content, ':docente_id2') !== false) {
        // Buscar execute() que solo tenga :docente_id
        $content = preg_replace_callback(
            '/(\$stmt->execute\(\[[^\]]*:docente_id[^\]]*?)(\]\);)/s',
            function($matches) {
                $execute_content = $matches[1];
                if (strpos($execute_content, ':docente_id2') === false) {
                    // Agregar :docente_id2
                    return $execute_content . ", ':docente_id2' => \$_SESSION['user_id']" . $matches[2];
                }
                return $matches[0];
            },
            $content
        );
    }
    
    // 4. Limpiar sintaxis SQL malformada específica
    $content = preg_replace('/WHERE\s+c\.creado_por\s*=\s*:docente_id\s+OR\s+c\.asignado_a\s*=\s*:docente_id\)/', 
                           'WHERE (c.creado_por = :docente_id OR c.asignado_a = :docente_id2)', $content);
    
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