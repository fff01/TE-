<?php
declare(strict_types=1);

require_once __DIR__ . '/runtime_config.php';
require_once __DIR__ . '/path_finder_service.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $localConfig = tekg_runtime_load_local_config();
    $config = tekg_runtime_neo4j_config($localConfig);
    if (trim((string)($config['neo4j_password'] ?? '')) === '') {
        throw new RuntimeException('Neo4j password is not configured');
    }

    $source = trim((string)($_GET['source'] ?? ''));
    $target = trim((string)($_GET['target'] ?? ''));
    $maxDepth = (int)($_GET['max_depth'] ?? 3);

    $service = new PathFinderService($config);
    $payload = $service->find($source, $target, $maxDepth);
    if (($payload['ok'] ?? false) !== true) {
        http_response_code(404);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
