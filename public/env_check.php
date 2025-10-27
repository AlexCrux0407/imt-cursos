<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$publicPath = null;
try {
  require_once __DIR__ . '/../config/paths.php';
  $publicPath = defined('PUBLIC_PATH') ? PUBLIC_PATH : null;
} catch (Throwable $e) {
  $publicPath = null;
}

$keys = ['MYSQLHOST','MYSQLPORT','MYSQLDATABASE','MYSQLUSER', 'BASE_URL', 'TZ'];
$out = [];
foreach ($keys as $k) {
  $out[$k] = getenv($k) !== false ? getenv($k) : '(no-set)';
}

header('Content-Type: text/plain; charset=utf-8');
echo "ENV VALUES\n";
foreach ($out as $k => $v) {
  echo "$k = $v\n";
}

echo "\nDIRECTORY CHECKS\n";
$dirPublic = $publicPath ?: __DIR__;
$mediaDir = rtrim($dirPublic, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'media';
echo "PUBLIC_PATH = $dirPublic\n";
echo "MEDIA_DIR = $mediaDir\n";
echo "MEDIA_DIR_EXISTS = " . (is_dir($mediaDir) ? 'yes' : 'no') . "\n";
echo "MEDIA_DIR_WRITABLE = " . (is_writable($mediaDir) ? 'yes' : 'no') . "\n";
if (is_dir($mediaDir)) {
  $perms = substr(sprintf('%o', fileperms($mediaDir)), -4);
  echo "MEDIA_DIR_PERMS = $perms\n";
}
