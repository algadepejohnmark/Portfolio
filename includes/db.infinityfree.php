<?php
$credentialsFile = __DIR__ . '/db.infinityfree.credentials.php';

if (!is_file($credentialsFile)) {
    error_log('InfinityFree database credentials file is missing.');
    http_response_code(500);
    exit('Database configuration is unavailable.');
}

$credentials = require $credentialsFile;
$requiredKeys = ['host', 'database', 'username', 'password'];
if (!is_array($credentials)) {
    error_log('InfinityFree database credentials file must return an array.');
    http_response_code(500);
    exit('Database configuration is invalid.');
}

foreach ($requiredKeys as $key) {
    if (!isset($credentials[$key]) || !is_string($credentials[$key]) || trim($credentials[$key]) === '') {
        error_log('InfinityFree database configuration is missing a required value.');
        http_response_code(500);
        exit('Database configuration is incomplete.');
    }
}

if ($credentials['password'] === '__REPLACE_WITH_INFINITYFREE_PASSWORD__') {
    error_log('InfinityFree database password placeholder has not been replaced.');
    http_response_code(500);
    exit('Database configuration is incomplete.');
}

if (strtolower($credentials['host']) === 'localhost' || str_contains($credentials['host'], ';')) {
    error_log('InfinityFree database host is invalid.');
    http_response_code(500);
    exit('Database configuration is invalid.');
}

try {
    $port = isset($credentials['port']) ? (int) $credentials['port'] : 3306;
    if ($port < 1 || $port > 65535) {
        throw new RuntimeException('Invalid database port.');
    }

    $pdo = new PDO(
        'mysql:host=' . $credentials['host'] . ';port=' . $port . ';dbname=' . $credentials['database'] . ';charset=utf8mb4',
        $credentials['username'],
        $credentials['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (Throwable $exception) {
    error_log('InfinityFree database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please try again later.');
}
