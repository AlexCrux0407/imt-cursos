<?php
// Directorio raíz del proyecto
$rootDir = __DIR__;

// Función para procesar archivos PHP
function processFile($filePath) {
    // Leer el contenido del archivo
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    // Realizar el reemplazo para header con comillas simples
    $content = preg_replace(
        "/header\('Location:\s*<\?=\s*BASE_URL\s*\?>(.*?)'\);/",
        "header('Location: ' . BASE_URL . '\\1');",
        $content
    );
    
    // Realizar el reemplazo para header con comillas dobles
    $content = preg_replace(
        '/header\("Location:\s*<\?=\s*BASE_URL\s*\?>(.*?)"\);/',
        'header("Location: " . BASE_URL . "\\1");',
        $content
    );
    
    // Realizar el reemplazo para include/require con comillas simples
    $content = preg_replace(
        "/(include|require|include_once|require_once)\('.*?<\?=\s*BASE_URL\s*\?>(.*?)'\);/",
        "\\1('' . BASE_URL . '\\2');",
        $content
    );
    
    // Realizar el reemplazo para include/require con comillas dobles
    $content = preg_replace(
        '/(include|require|include_once|require_once)\(".*?<\?=\s*BASE_URL\s*\?>(.*?)"\);/',
        '\\1("" . BASE_URL . "\\2");',
        $content
    );
    
    // Si hubo cambios, guardar el archivo
    if ($content !== $originalContent) {
        echo "Modificando: $filePath\n";
        file_put_contents($filePath, $content);
        return true;
    }
    
    return false;
}

// Función recursiva para recorrer directorios
function processDirectory($dir) {
    $count = 0;
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        
        if (is_dir($path)) {
            $count += processDirectory($path);
        } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            if (processFile($path)) {
                $count++;
            }
        }
    }
    
    return $count;
}

// Ejecutar el procesamiento
echo "Iniciando reemplazo de sintaxis BASE_URL incorrecta...\n";
$filesModified = processDirectory($rootDir);
echo "Proceso completado. Se modificaron $filesModified archivos.\n";