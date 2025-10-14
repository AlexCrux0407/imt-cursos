<?php
/**
 * Script para corregir todos los errores de parámetros PDO duplicados
 * Corrige :docente_id2 por :docente_id en consultas SQL y arrays de parámetros
 */

$directorio = __DIR__ . '/public/docente/';
$archivos_corregidos = 0;
$errores = [];

// Lista de archivos que necesitan corrección basada en la búsqueda
$archivos_afectados = [
    'temas_modulo.php',
    'procesar_modulo.php', 
    'preguntas_evaluacion.php',
    'cambiar_estado_evaluacion.php',
    'procesar_zip_curso.php',
    'procesar_tema.php',
    'eliminar_pregunta.php',
    'actualizar_leccion.php',
    'actualizar_evaluacion.php',
    'actualizar_subtema.php',
    'procesar_subtema.php',
    'validar_orden.php',
    'visualizar_curso.php',
    'evaluaciones_modulo.php',
    'revisar_evaluaciones.php',
    'eliminar_leccion.php',
    'eliminar_tema.php',
    'subtemas_tema.php',
    'editar_tema.php',
    'procesar_evaluacion.php',
    'procesar_pregunta.php',
    'lecciones_modulo.php',
    'actualizar_modulo.php',
    'mover_pregunta.php',
    'editar_evaluacion.php',
    'eliminar_modulo.php',
    'editar_subtema.php',
    'actualizar_tema.php',
    'editar_leccion.php',
    'procesar_leccion.php',
    'procesar_calificacion.php',
    'calificar_intento.php',
    'reportes.php',
    'eliminar_subtema.php'
];

echo "🔧 Iniciando corrección de parámetros PDO duplicados...\n\n";

foreach ($archivos_afectados as $archivo) {
    $ruta_archivo = $directorio . $archivo;
    
    if (!file_exists($ruta_archivo)) {
        $errores[] = "❌ Archivo no encontrado: $archivo";
        continue;
    }
    
    $contenido = file_get_contents($ruta_archivo);
    $contenido_original = $contenido;
    
    // Corrección 1: Reemplazar :docente_id2 por :docente_id en consultas SQL
    $contenido = preg_replace('/c\.asignado_a\s*=\s*:docente_id2/', 'c.asignado_a = :docente_id', $contenido);
    $contenido = preg_replace('/asignado_a\s*=\s*:docente_id2/', 'asignado_a = :docente_id', $contenido);
    
    // Corrección 2: Eliminar parámetros :docente_id2 de arrays de ejecución
    $contenido = preg_replace("/,\s*':docente_id2'\s*=>\s*\\\$_SESSION\['user_id'\]/", '', $contenido);
    
    // Corrección 3: Casos especiales donde :docente_id2 aparece solo
    $contenido = preg_replace("/':docente_id2'\s*=>\s*\\\$_SESSION\['user_id'\]/", "':docente_id' => \$_SESSION['user_id']", $contenido);
    
    // Corrección 4: Casos donde aparece en condiciones WHERE sin c.
    $contenido = preg_replace('/creado_por\s*=\s*:docente_id2/', 'creado_por = :docente_id', $contenido);
    
    if ($contenido !== $contenido_original) {
        if (file_put_contents($ruta_archivo, $contenido)) {
            echo "✅ Corregido: $archivo\n";
            $archivos_corregidos++;
        } else {
            $errores[] = "❌ Error al escribir: $archivo";
        }
    } else {
        echo "ℹ️  Sin cambios: $archivo\n";
    }
}

echo "\n📊 RESUMEN:\n";
echo "✅ Archivos corregidos: $archivos_corregidos\n";
echo "📁 Total archivos procesados: " . count($archivos_afectados) . "\n";

if (!empty($errores)) {
    echo "\n❌ ERRORES:\n";
    foreach ($errores as $error) {
        echo "$error\n";
    }
}

echo "\n🎉 Corrección completada!\n";
echo "💡 Recomendación: Probar la aplicación para verificar que todo funciona correctamente.\n";
?>