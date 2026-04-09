<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$local = [];
$path = __DIR__ . '/config.local.php';
if (is_file($path)) {
    $loaded = require $path;
    if (is_array($loaded)) {
        $local = $loaded;
    }
}

function pick(string $localKey, array $envNames, ?string $default = null): ?string
{
    global $local;
    if (isset($local[$localKey]) && trim((string)$local[$localKey]) !== '') {
        return trim((string)$local[$localKey]);
    }
    foreach ($envNames as $name) {
        $value = getenv($name);
        if ($value !== false && trim((string)$value) !== '') {
            return trim((string)$value);
        }
    }
    return $default;
}

$config = [
    'dashscope_key' => pick('dashscope_key', ['DASHSCOPE_API_KEY_BIOLOGY', 'DASHSCOPE_API_KEY']),
    'dashscope_model' => pick('dashscope_model', ['DASHSCOPE_MODEL_BIOLOGY', 'DASHSCOPE_MODEL'], 'qwen-plus'),
    'neo4j_url' => pick('neo4j_url', ['NEO4J_HTTP_URL_BIOLOGY', 'NEO4J_HTTP_URL'], 'http://127.0.0.1:7474/db/tekg2/tx/commit'),
    'neo4j_user' => pick('neo4j_user', ['NEO4J_USER_BIOLOGY', 'NEO4J_USER'], 'neo4j'),
    'neo4j_password' => pick('neo4j_password', ['NEO4J_PASSWORD_BIOLOGY', 'NEO4J_PASSWORD']),
];

$neo4jReachable = false;
$neo4jMessage = 'not tested';
if (function_exists('curl_init') && $config['neo4j_password']) {
    $ch = curl_init($config['neo4j_url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $config['neo4j_user'] . ':' . $config['neo4j_password'],
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => '{"statements":[{"statement":"RETURN 1 AS ok"}]}',
        CURLOPT_TIMEOUT => 10,
    ]);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw !== false && $status < 400) {
        $neo4jReachable = true;
        $neo4jMessage = 'ok';
    } else {
        $neo4jMessage = $error !== '' ? $error : ('HTTP ' . $status);
    }
}

echo json_encode([
    'ok' => true,
    'php_version' => PHP_VERSION,
    'curl_loaded' => function_exists('curl_init'),
    'dashscope_key_present' => $config['dashscope_key'] !== null && $config['dashscope_key'] !== '',
    'dashscope_model' => $config['dashscope_model'],
    'neo4j_url' => $config['neo4j_url'],
    'neo4j_user' => $config['neo4j_user'],
    'neo4j_password_present' => $config['neo4j_password'] !== null && $config['neo4j_password'] !== '',
    'neo4j_reachable' => $neo4jReachable,
    'neo4j_message' => $neo4jMessage,
    'using_local_config' => is_file($path),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
