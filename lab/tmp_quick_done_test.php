<?php
declare(strict_types=1);
require dirname(__DIR__) . '/api/agent/bootstrap.php';
require dirname(__DIR__) . '/api/agent/orchestrator/Neo4jClient.php';
require dirname(__DIR__) . '/api/agent/orchestrator/LlmClient.php';
require dirname(__DIR__) . '/api/agent/orchestrator/CitationResolver.php';
require dirname(__DIR__) . '/api/agent/orchestrator/EntityNormalizer.php';
require dirname(__DIR__) . '/api/agent/plugins/EntityResolverPlugin.php';
require dirname(__DIR__) . '/api/agent/plugins/GraphPlugin.php';
require dirname(__DIR__) . '/api/agent/plugins/GraphAnalyticsPlugin.php';
require dirname(__DIR__) . '/api/agent/plugins/CypherExplorerPlugin.php';
require dirname(__DIR__) . '/api/agent/plugins/LiteraturePlugin.php';
require dirname(__DIR__) . '/api/agent/plugins/LiteratureReadingPlugin.php';
require dirname(__DIR__) . '/api/agent/plugins/TreePlugin.php';
require dirname(__DIR__) . '/api/agent/plugins/ExpressionPlugin.php';
require dirname(__DIR__) . '/api/agent/plugins/GenomePlugin.php';
require dirname(__DIR__) . '/api/agent/plugins/SequencePlugin.php';
require dirname(__DIR__) . '/api/agent/plugins/CitationResolverPlugin.php';
require dirname(__DIR__) . '/api/agent/orchestrator/AcademicAgentService.php';
$service = new TekgAcademicAgentService(tekg_agent_config());
$done = [];
$service->stream(['question' => 'What is the sequence of L1HS?', 'mode' => 'academic'], function(array $event) use (&$done): void {
  if (($event['type'] ?? '') === 'done') { $done = (array)($event['payload'] ?? []); }
});
echo json_encode([
  'has_answer' => isset($done['answer']) && trim((string)$done['answer']) !== '',
  'has_workflow_state' => isset($done['workflow_state']) && is_array($done['workflow_state']),
  'writing_status' => $done['workflow_state']['stage_statuses']['Writing'] ?? null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
