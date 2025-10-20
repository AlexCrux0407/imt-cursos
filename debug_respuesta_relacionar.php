<?php
require_once '../config/database.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Buscar respuestas de tipo relacionar_pares
    $stmt = $pdo->prepare("
        SELECT r.*, p.pregunta, p.opciones, p.respuesta_correcta 
        FROM respuestas_estudiante r 
        JOIN preguntas p ON r.pregunta_id = p.id 
        WHERE p.tipo = 'relacionar_pares' 
        ORDER BY r.id DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $respuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Debug: Respuestas de Relacionar Pares</h2>";
    
    foreach ($respuestas as $respuesta) {
        echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
        echo "<h3>Respuesta ID: " . $respuesta['id'] . "</h3>";
        echo "<p><strong>Pregunta:</strong> " . htmlspecialchars($respuesta['pregunta']) . "</p>";
        echo "<p><strong>Respuesta del estudiante (raw):</strong> " . htmlspecialchars($respuesta['respuesta']) . "</p>";
        
        // Decodificar la respuesta
        $respDecodificada = json_decode($respuesta['respuesta'], true);
        echo "<p><strong>Respuesta decodificada:</strong> ";
        var_dump($respDecodificada);
        echo "</p>";
        
        // Decodificar las opciones
        $opciones = json_decode($respuesta['opciones'], true);
        echo "<p><strong>Opciones disponibles:</strong> ";
        var_dump($opciones);
        echo "</p>";
        
        // Decodificar respuesta correcta
        $correcta = json_decode($respuesta['respuesta_correcta'], true);
        echo "<p><strong>Respuesta correcta:</strong> ";
        var_dump($correcta);
        echo "</p>";
        
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>