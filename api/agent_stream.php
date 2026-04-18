<?php
declare(strict_types=1);

require_once __DIR__ . '/agent/bootstrap.php';
require_once __DIR__ . '/agent/orchestrator/Neo4jClient.php';
require_once __DIR__ . '/agent/orchestrator/LlmClient.php';
require_once __DIR__ . '/agent/orchestrator/CitationResolver.php';
require_once __DIR__ . '/agent/orchestrator/EntityNormalizer.php';
require_once __DIR__ . '/agent/plugins/EntityResolverPlugin.php';
require_once __DIR__ . '/agent/plugins/GraphPlugin.php';
require_once __DIR__ . '/agent/plugins/LiteraturePlugin.php';
require_once __DIR__ . '/agent/plugins/TreePlugin.php';
require_once __DIR__ . '/agent/plugins/ExpressionPlugin.php';
require_once __DIR__ . '/agent/plugins/GenomePlugin.php';
require_once __DIR__ . '/agent/plugins/SequencePlugin.php';
require_once __DIR__ . '/agent/plugins/CitationResolverPlugin.php';
require_once __DIR__ . '/agent/orchestrator/AcademicAgentService.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed. Use POST.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');
header('Content-Encoding: none');

@ini_set('output_buffering', '0');
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}

while (ob_get_level() > 0) {
    @ob_end_flush();
}
ob_implicit_flush(true);

$emit = static function (array $event): void {
    echo 'data: ' . json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    @ob_flush();
    @flush();
};

echo ':' . str_repeat(' ', 2048) . "\n\n";
@ob_flush();
@flush();

try {
    $raw = file_get_contents('php://input');
    $payload = json_decode((string)$raw, true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Invalid JSON body.');
    }

    $service = new TekgAcademicAgentService(tekg_agent_config());
    $service->stream($payload, $emit);
} catch (Throwable $error) {
    $emit([
        'type' => 'error',
        'message' => $error->getMessage(),
    ]);
    $emit([
        'type' => 'done',
        'payload' => ['failed' => true],
    ]);
}
