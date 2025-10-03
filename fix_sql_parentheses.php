<?php
/**
 * Script para corregir la sintaxis SQL agregando paréntesis faltantes
 * en las consultas que usan OR con creado_por y asignado_a
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
    
    // Patrón para encontrar consultas SQL sin paréntesis correctos
    $patterns = [
        // Patrón principal: AND algo = :docente_id OR algo = :docente_id
        '/(\s+AND\s+[^=]+\s*=\s*:docente_id)\s+OR\s+([^=]+\s*=\s*:docente_id)(?!\))/',
        // Patrón para WHERE directo
        '/(\s+WHERE\s+[^=]+\s*=\s*:docente_id)\s+OR\s+([^=]+\s*=\s*:docente_id)(?!\))/'
    ];
    
    foreach ($patterns as $pattern) {
        $content = preg_replace($pattern, '$1 OR $2)', $content);
        $content = preg_replace('/(\s+WHERE\s+[^=]+\s*=\s*:docente_id\s+OR\s+[^=]+\s*=\s*:docente_id\))/', ' WHERE ($1', $content);
        $content = preg_replace('/(\s+AND\s+[^=]+\s*=\s*:docente_id\s+OR\s+[^=]+\s*=\s*:docente_id\))/', ' AND ($1', $content);
    }
    
    // Corrección más específica para los patrones encontrados
    $content = preg_replace(
        '/(\s+AND\s+c\.creado_por\s*=\s*:docente_id)\s+OR\s+(c\.asignado_a\s*=\s*:docente_id)(?!\))/',
        '$1 OR $2)',
        $content
    );
    
    $content = preg_replace(
        '/(\s+WHERE\s+c\.creado_por\s*=\s*:docente_id)\s+OR\s+(c\.asignado_a\s*=\s*:docente_id)(?!\))/',
        ' WHERE ($1 OR $2)',
        $content
    );
    
    // Agregar paréntesis de apertura donde falte
    $content = preg_replace(
        '/(\s+AND\s+)\(?(c\.creado_por\s*=\s*:docente_id\s+OR\s+c\.asignado_a\s*=\s*:docente_id)\)/',
        '$1($2)',
        $content
    );
    
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