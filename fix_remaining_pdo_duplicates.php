<?php
// Script para corregir parámetros PDO duplicados restantes

$files_to_fix = [
    'actualizar_leccion.php',
    'eliminar_leccion.php', 
    'editar_leccion.php',
    'eliminar_modulo.php',
    'validar_orden.php',
    'procesar_modulo.php',
    'eliminar_curso.php',
    'procesar_tema.php',
    'cambiar_estado_evaluacion.php',
    'eliminar_tema.php',
    'reportes.php',
    'procesar_leccion.php',
    'actualizar_modulo.php',
    'procesar_calificacion.php',
    'eliminar_subtema.php',
    'actualizar_subtema.php',
    'procesar_subtema.php'
];

$docente_dir = __DIR__ . '/public/docente/';

foreach ($files_to_fix as $file) {
    $filepath = $docente_dir . $file;
    
    if (!file_exists($filepath)) {
        echo "Archivo no encontrado: $file\n";
        continue;
    }
    
    $content = file_get_contents($filepath);
    $original_content = $content;
    
    // Patrón para encontrar consultas con :docente_id duplicado
    $pattern1 = '/(\(c\.creado_por = :docente_id OR c\.asignado_a = ):docente_id(\))/';
    $replacement1 = '$1:docente_id2$2';
    
    // Patrón para encontrar execute con solo un :docente_id
    $pattern2 = '/(\$stmt->execute\(\[.*?):docente_id(.*?\]\);)/s';
    
    // Aplicar primera corrección
    $content = preg_replace($pattern1, $replacement1, $content);
    
    // Buscar y corregir execute statements
    if (preg_match('/\(c\.creado_por = :docente_id OR c\.asignado_a = :docente_id2\)/', $content)) {
        // Si ya tiene :docente_id2 en la consulta, necesitamos actualizar el execute
        $content = preg_replace_callback(
            '/(\$stmt->execute\(\[)(.*?)(\]\);)/s',
            function($matches) {
                $params = $matches[2];
                // Si solo tiene un :docente_id, agregar :docente_id2
                if (strpos($params, ':docente_id') !== false && strpos($params, ':docente_id2') === false) {
                    // Agregar :docente_id2 con el mismo valor
                    $params = rtrim($params, ' ');
                    if (substr($params, -1) !== ',') {
                        $params .= ',';
                    }
                    $params .= "\n        ':docente_id2' => \$_SESSION['user_id']";
                }
                return $matches[1] . $params . $matches[3];
            },
            $content
        );
    }
    
    // Solo escribir si hubo cambios
    if ($content !== $original_content) {
        file_put_contents($filepath, $content);
        echo "Corregido: $file\n";
    } else {
        echo "Sin cambios: $file\n";
    }
}

echo "Corrección completada.\n";
?>