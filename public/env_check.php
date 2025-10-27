<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
