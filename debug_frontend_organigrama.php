<?php
echo "=== DEBUG FRONTEND ORGANIGRAMA ===\n";
echo "Datos POST recibidos:\n";
print_r($_POST);

echo "\nDatos específicos del organigrama:\n";
foreach ($_POST as $key => $value) {
    if (strpos($key, 'respuesta_') === 0) {
        echo "Campo: $key\n";
        echo "Valor RAW: " . var_export($value, true) . "\n";
        echo "Tipo: " . gettype($value) . "\n";
        if (is_string($value)) {
            echo "Longitud: " . strlen($value) . "\n";
            $decoded = json_decode($value, true);
            if ($decoded !== null) {
                echo "JSON decodificado:\n";
                print_r($decoded);
            } else {
                echo "No es JSON válido\n";
            }
        }
        echo "---\n";
    }
}
?>