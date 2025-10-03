<?php
/**
 * Script para corregir las verificaciones de permisos en todos los archivos del docente
 * Cambia de verificar por creado_por a verificar por asignado_a cuando corresponde
 */

$archivos_a_corregir = [
    'temas_modulo.php',
    'lecciones_modulo.php', 
    'editar_modulo.php',
    'editar_curso.php',
    'eliminar_leccion.php',
    'reportes.php',
    'eliminar_modulo.php',
    'procesar_tema.php',
    'editar_tema.php',
    'procesar_leccion.php',
    'editar_leccion.php',
    'cambiar_estado_evaluacion.php',
    'procesar_evaluacion.php',
    'eliminar_subtema.php',
    'editar_subtema.php',
    'preguntas_evaluacion.php',
    'subtemas_tema.php',
    'actualizar_curso.php',
    'evaluaciones_modulo.php',
    'validar_orden.php',
    'actualizar_tema.php',
    'actualizar_modulo.php',
    'procesar_modulo.php',
    'eliminar_curso.php',
    'eliminar_tema.php',
    'actualizar_leccion.php',
    'eliminar_pregunta.php',
    'actualizar_subtema.php',
    'procesar_subtema.php',
    'mover_pregunta.php',
    'procesar_pregunta.php'
];

$directorio_docente = __DIR__ . '/public/docente/';

foreach ($archivos_a_corregir as $archivo) {
    $ruta_archivo = $directorio_docente . $archivo;
    
    if (!file_exists($ruta_archivo)) {
        echo "❌ Archivo no encontrado: $archivo\n";
        continue;
    }
    
    $contenido = file_get_contents($ruta_archivo);
    $contenido_original = $contenido;
    
    // Patrón para encontrar verificaciones de curso por creado_por
    $patron_curso_simple = '/(\$stmt = \$conn->prepare\("[\s\S]*?SELECT[\s\S]*?FROM cursos[\s\S]*?WHERE[\s\S]*?id = :id AND )creado_por = :docente_id/';
    
    // Reemplazo con verificación dual
    $reemplazo_curso = '$1(creado_por = :docente_id OR asignado_a = :docente_id)';
    
    $contenido = preg_replace($patron_curso_simple, $reemplazo_curso, $contenido);
    
    // Patrón más complejo para JOINs con cursos
    $patron_join = '/(WHERE[\s\S]*?c\.creado_por = :docente_id)(?!\s+OR)/';
    $reemplazo_join = '$1 OR c.asignado_a = :docente_id';
    
    $contenido = preg_replace($patron_join, $reemplazo_join, $contenido);
    
    // Verificar si hubo cambios
    if ($contenido !== $contenido_original) {
        file_put_contents($ruta_archivo, $contenido);
        echo "✅ Corregido: $archivo\n";
    } else {
        echo "⚪ Sin cambios: $archivo\n";
    }
}

echo "\n🎉 Proceso completado!\n";
?>