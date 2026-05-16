<?php
declare(strict_types=1);

require_once __DIR__ . '/taxonomy_lib.php';

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
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$view = trim((string)($_GET['view'] ?? 'items'));
$names = tekg_taxonomy_parse_names((string)($_GET['names'] ?? ''));
$config = tekg_taxonomy_config();

try {
    $items = tekg_taxonomy_fetch_items($names, $config);
    $payload = [
        'ok' => true,
        'source' => 'neo4j',
        'database' => tekg_taxonomy_database_name($config),
    ];

    if ($view === 'tree') {
        $payload += tekg_taxonomy_tree_payload($items);
    } elseif ($view === 'summary') {
        $payload['summary'] = tekg_taxonomy_summary_payload($items);
    } else {
        $payload['items'] = $items;
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
