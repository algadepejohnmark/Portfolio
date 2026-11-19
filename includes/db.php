<?php
declare(strict_types=1);

$localConfig = __DIR__ . '/db.local.php';
$productionConfig = __DIR__ . '/db.infinityfree.php';
$productionMarker = __DIR__ . '/db.infinityfree.enabled';
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$host = preg_replace('/:\\d+$/', '', $host) ?? $host;
$isLocalHost = $host === ''
    || in_array($host, ['localhost', '127.0.0.1', '::1'], true)
    || str_ends_with($host, '.localhost')
    || str_ends_with($host, '.test');
$useProduction = is_file($productionMarker)
    || (!$isLocalHost && PHP_SAPI !== 'cli')
    || !is_file($localConfig);

if ($useProduction) {
    require $productionConfig;
} elseif (is_file($localConfig)) {
    require $localConfig;
} else {
    error_log('No portfolio database configuration file is installed.');
    http_response_code(500);
    exit('Database configuration is unavailable.');
}
