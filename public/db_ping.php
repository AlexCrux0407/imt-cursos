<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

echo "OK DB → host={$host}; port={$port}; db={$db}; user={$user}";
