<?php
/**
 * Script para corregir los errores de sintaxis SQL restantes
 * Corrige las consultas con "AND (AND ( AND (" malformadas
 */

$files_to_fix = [
    'public/docente/eliminar_tema.php',
    'public/docente/procesar_subtema.php',
    'public/docente/actualizar_modulo.php',
    'public/docente/actualizar_tema.php',
    'public/docente/procesar_leccion.php',
    'public/docente/procesar_tema.php',
    'public/docente/lecciones_modulo.php',
    'public/docente/actualizar_leccion.php'
];

$fixed_count = 0;

foreach ($files_to_fix as $file) {
    if (!file_exists($file)) {
        echo "❌ Archivo no encontrado: $file\n";
        continue;
    }
    
    $content = file_get_contents($file);
    $original_content = $content;
    
    // Corregir sintaxis SQL malformada: "AND (AND ( AND (" -> "AND ("
    $content = preg_replace('/AND \(AND \( AND \(/', 'AND (', $content);
    
    // Asegurar que :docente_id2 esté en el execute si no está presente
    if (strpos($content, ':docente_id2') !== false && strpos($content, "':docente_id2'") === false) {
        // Buscar el patrón de execute con :docente_id pero sin :docente_id2
        $content = preg_replace_callback(
            '/(\$stmt->execute\(\[\s*[^}]*\':docente_id\'\s*=>\s*\$_SESSION\[\'user_id\'\])([^}]*)\]\);/',
            function($matches) {
                if (strpos($matches[0], ':docente_id2') === false) {
                    return str_replace(']]);', ", ':docente_id2' => \$_SESSION['user_id']]);", $matches[0]);
                }
                return $matches[0];
            },
            $content
        );
    }
    
    if ($content !== $original_content) {
        file_put_contents($file, $content);
        echo "✅ Corregido: $file\n";
        $fixed_count++;
    } else {
        echo "ℹ️  Sin cambios: $file\n";
    }
}

echo "\n🎉 Proceso completado. Archivos corregidos: $fixed_count\n";
?>