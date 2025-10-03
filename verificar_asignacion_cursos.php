<?php
require_once 'config/database.php';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Verificación de Asignación de Cursos</h2>";
    
    // Verificar si las columnas de asignación existen
    echo "<h3>Verificación de Columnas en tabla 'cursos':</h3>";
    $stmt = $conn->query("SHOW COLUMNS FROM cursos");
    $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $tiene_asignado_a = false;
    $tiene_fecha_asignacion = false;
    $tiene_estado_asignacion = false;
    
    foreach ($columnas as $columna) {
        if ($columna['Field'] == 'asignado_a') $tiene_asignado_a = true;
        if ($columna['Field'] == 'fecha_asignacion') $tiene_fecha_asignacion = true;
        if ($columna['Field'] == 'estado_asignacion') $tiene_estado_asignacion = true;
        echo "<p>Columna: {$columna['Field']}, Tipo: {$columna['Type']}, Null: {$columna['Null']}, Default: {$columna['Default']}</p>";
    }
    
    echo "<h3>Estado de Columnas de Asignación:</h3>";
    echo "<p>asignado_a: " . ($tiene_asignado_a ? "✅ EXISTE" : "❌ NO EXISTE") . "</p>";
    echo "<p>fecha_asignacion: " . ($tiene_fecha_asignacion ? "✅ EXISTE" : "❌ NO EXISTE") . "</p>";
    echo "<p>estado_asignacion: " . ($tiene_estado_asignacion ? "✅ EXISTE" : "❌ NO EXISTE") . "</p>";
    
    // Verificar usuarios docentes
    echo "<h3>Usuarios con rol 'docente':</h3>";
    $stmt = $conn->query("SELECT id, nombre, email, role FROM usuarios WHERE role = 'docente'");
    $docentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($docentes)) {
        echo "<p>❌ NO HAY USUARIOS CON ROL 'docente'</p>";
    } else {
        foreach ($docentes as $docente) {
            echo "<p>ID: {$docente['id']}, Nombre: {$docente['nombre']}, Email: {$docente['email']}</p>";
        }
    }
    
    // Verificar cursos disponibles para asignar
    echo "<h3>Cursos Disponibles:</h3>";
    if ($tiene_asignado_a) {
        $stmt = $conn->query("SELECT id, titulo, creado_por, asignado_a, estado_asignacion FROM cursos ORDER BY id DESC LIMIT 10");
    } else {
        $stmt = $conn->query("SELECT id, titulo, creado_por FROM cursos ORDER BY id DESC LIMIT 10");
    }
    $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($cursos as $curso) {
        echo "<p>ID: {$curso['id']}, Título: {$curso['titulo']}, Creado por: {$curso['creado_por']}";
        if (isset($curso['asignado_a'])) {
            echo ", Asignado a: " . ($curso['asignado_a'] ?: 'Sin asignar');
            echo ", Estado: " . ($curso['estado_asignacion'] ?: 'pendiente');
        }
        echo "</p>";
    }
    
    // Verificar usuarios master
    echo "<h3>Usuarios con rol 'master':</h3>";
    $stmt = $conn->query("SELECT id, nombre, email, role FROM usuarios WHERE role = 'master'");
    $masters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($masters)) {
        echo "<p>❌ NO HAY USUARIOS CON ROL 'master'</p>";
    } else {
        foreach ($masters as $master) {
            echo "<p>ID: {$master['id']}, Nombre: {$master['nombre']}, Email: {$master['email']}</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>