<?php
declare(strict_types=1);
require 'D:/wamp64/www/TE-/api/agent/bootstrap.php';
require 'D:/wamp64/www/TE-/api/agent/orchestrator/Neo4jClient.php';
require 'D:/wamp64/www/TE-/api/agent/orchestrator/LlmClient.php';
require 'D:/wamp64/www/TE-/api/agent/orchestrator/CitationResolver.php';
require 'D:/wamp64/www/TE-/api/agent/orchestrator/EntityNormalizer.php';
require 'D:/wamp64/www/TE-/api/agent/plugins/EntityResolverPlugin.php';
require 'D:/wamp64/www/TE-/api/agent/plugins/GraphPlugin.php';
require 'D:/wamp64/www/TE-/api/agent/plugins/GraphAnalyticsPlugin.php';
require 'D:/wamp64/www/TE-/api/agent/plugins/CypherExplorerPlugin.php';
require 'D:/wamp64/www/TE-/api/agent/plugins/LiteraturePlugin.php';
require 'D:/wamp64/www/TE-/api/agent/plugins/LiteratureReadingPlugin.php';
require 'D:/wamp64/www/TE-/api/agent/plugins/TreePlugin.php';
require 'D:/wamp64/www/TE-/api/agent/plugins/ExpressionPlugin.php';
require 'D:/wamp64/www/TE-/api/agent/plugins/GenomePlugin.php';
require 'D:/wamp64/www/TE-/api/agent/plugins/SequencePlugin.php';
require 'D:/wamp64/www/TE-/api/agent/plugins/CitationResolverPlugin.php';
require 'D:/wamp64/www/TE-/api/agent/orchestrator/AcademicAgentService.php';
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
