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
    tekg_agent_json_response(200, ['ok' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tekg_agent_json_response(405, ['ok' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    $raw = file_get_contents('php://input');
    $payload = json_decode((string)$raw, true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Invalid JSON body.');
    }
    $service = new TekgAcademicAgentService(tekg_agent_config());
    $response = $service->handle($payload);
    tekg_agent_json_response(200, ['ok' => true, 'data' => $response]);
} catch (Throwable $error) {
    tekg_agent_json_response(500, ['ok' => false, 'error' => $error->getMessage()]);
}
