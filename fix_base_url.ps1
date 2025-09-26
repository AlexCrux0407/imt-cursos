# Script para reemplazar la sintaxis incorrecta de BASE_URL en archivos PHP
$rootDir = "c:\laragon\www\imt-cursos"
$count = 0

# Función para procesar un archivo
function Process-File {
    param (
        [string]$filePath
    )
    
    $content = Get-Content -Path $filePath -Raw
    $originalContent = $content
    
    # Reemplazar header con comillas simples
    $content = $content -replace "header\('Location:\s*<\?=\s*BASE_URL\s*\?>(.*?)'\);", "header('Location: ' . BASE_URL . '`$1');"
    
    # Reemplazar header con comillas dobles
    $content = $content -replace 'header\("Location:\s*<\?=\s*BASE_URL\s*\?>(.*?)"\);', 'header("Location: " . BASE_URL . "$1");'
    
    # Reemplazar include/require con comillas simples
    $content = $content -replace "(include|require|include_once|require_once)\('.*?<\?=\s*BASE_URL\s*\?>(.*?)'\);", "$1('' . BASE_URL . '$2');"
    
    # Reemplazar include/require con comillas dobles
    $content = $content -replace '(include|require|include_once|require_once)\(".*?<\?=\s*BASE_URL\s*\?>(.*?)"\);', '$1("" . BASE_URL . "$2");'
    
    # Si hubo cambios, guardar el archivo
    if ($content -ne $originalContent) {
        Write-Host "Modificando: $filePath"
        Set-Content -Path $filePath -Value $content -NoNewline
        return $true
    }
    
    return $false
}

# Función recursiva para procesar directorios
function Process-Directory {
    param (
        [string]$dir
    )
    
    $localCount = 0
    $files = Get-ChildItem -Path $dir
    
    foreach ($file in $files) {
        if ($file.PSIsContainer) {
            # Es un directorio, procesarlo recursivamente
            $localCount += Process-Directory -dir $file.FullName
        } elseif ($file.Extension -eq ".php") {
            # Es un archivo PHP, procesarlo
            if (Process-File -filePath $file.FullName) {
                $localCount++
            }
        }
    }
    
    return $localCount
}

Write-Host "Iniciando reemplazo de sintaxis BASE_URL incorrecta..."
$count = Process-Directory -dir $rootDir
Write-Host "Proceso completado. Se modificaron $count archivos."