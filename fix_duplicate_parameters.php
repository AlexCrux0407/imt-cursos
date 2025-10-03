<?php
/**
 * Script para corregir parámetros PDO duplicados en consultas SQL
 * Cambia :docente_id duplicado por :docente_id y :docente_id2
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
    'public/docente/modulos_curso.php'
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
    
    // Buscar patrones con parámetros duplicados :docente_id
    $pattern = '/(\(c\.creado_por\s*=\s*:docente_id\s+OR\s+c\.asignado_a\s*=\s*):docente_id(\))/';
    $replacement = '$1:docente_id2$2';
    
    $content = preg_replace($pattern, $replacement, $content);
    
    // También buscar el patrón en execute()
    $execute_pattern = '/(\$stmt->execute\(\[\s*[^}]*:docente_id[^}]*)\]\);/s';
    
    if (preg_match($execute_pattern, $content, $matches)) {
        // Si encontramos execute con :docente_id, necesitamos agregar :docente_id2
        $execute_content = $matches[1];
        if (strpos($execute_content, ':docente_id2') === false && strpos($content, ':docente_id2') !== false) {
            // Agregar el parámetro :docente_id2
            $new_execute = $execute_content . ", ':docente_id2' => \$_SESSION['user_id']";
            $content = str_replace($matches[0], $new_execute . ']);', $content);
        }
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