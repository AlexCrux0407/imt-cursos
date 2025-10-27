<?php
require_once __DIR__ . '/../../app/auth.php';
require_role('master');
require_once __DIR__ . '/../../config/database.php';
header('Content-Type: text/plain; charset=UTF-8');
try {
  $stmt = $conn->query("DESCRIBE certificados_config");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo "DESCRIBE certificados_config\n";
  foreach ($rows as $r) {
    printf("%-24s | %-18s | Null:%-3s | Default:%s\n", $r['Field'], $r['Type'], $r['Null'], var_export($r['Default'], true));
  }
} catch (Throwable $e) {
  echo "Error: ".$e->getMessage()."\n";
}