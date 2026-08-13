<?php
header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'php' => PHP_VERSION,
    'pdo_mysql' => extension_loaded('pdo_mysql') ? 'yes' : 'no',
    'mysqli' => extension_loaded('mysqli') ? 'yes' : 'no',
    'port' => getenv('PORT') ?: 'not-set',
    'time' => date('c'),
]);
