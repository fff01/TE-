<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/agent/bootstrap.php';
require_once __DIR__ . '/../api/agent/plugin_registry.php';
tekg_agent_require_academic_agent_service();

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "Assertion failed: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function assert_contains(string $needle, string $haystack, string $message): void
{
    assert_true(str_contains($haystack, $needle), $message . " missing {$needle}");
}

function assert_call_uses_arguments(string $source, string $callName, array $needles, string $message): void
{
    $position = strpos($source, $callName);
    assert_true($position !== false, $message . " missing {$callName}");
    $snippet = substr($source, $position, 900);
    foreach ($needles as $needle) {
        assert_true(str_contains($snippet, $needle), $message . " missing {$needle}");
    }
}

$serviceSource = (string)file_get_contents(__DIR__ . '/../api/agent/orchestrator/AcademicAgentService.php');
foreach ([
    'runUnderstandingNode',
    'runPlanningNode',
    'runCollectingNode',
    'runExecutingReviewNode',
    'runIntegratingNode',
    'runWritingDecisionNode',
] as $methodName) {
    assert_contains($methodName, $serviceSource, "AcademicAgentService calls {$methodName}");
}
assert_contains("'node_llm_result'", $serviceSource, 'AcademicAgentService emits node_llm_result events');
assert_contains("'node_llm_error'", $serviceSource, 'AcademicAgentService emits node_llm_error events');
assert_contains("'six_stage_artifacts'", $serviceSource, 'AcademicAgentService response exposes six_stage_artifacts');
assert_contains('tool_execution_review.v1', $serviceSource, 'AcademicAgentService requires executing review artifact');
assert_contains('pluginResultForLlmReview', $serviceSource, 'AcademicAgentService sends compact plugin review payloads');
assert_contains('$claimEvidenceMap = (array)$integratingNode->parsed_json;', $serviceSource, 'AcademicAgentService stores claimEvidenceMap from integrating node');
assert_contains('$writingDecision = (array)$writingDecisionNode->parsed_json;', $serviceSource, 'AcademicAgentService stores writingDecision from writing node');
assert_contains('fallbackUnderstandingNodeResult(', $serviceSource, 'AcademicAgentService can fallback from failed Understanding LLM');
assert_contains('fallbackPlanningNodeResult(', $serviceSource, 'AcademicAgentService can fallback from failed Planning LLM');
assert_call_uses_arguments($serviceSource, '->writeEvidenceWalkDraft(', ['$claimEvidenceMap', '$writingDecision'], 'Draft writer call receives claim map and writing decision');
assert_call_uses_arguments($serviceSource, '->polishEvidenceWalkAnswer(', ['$claimEvidenceMap', '$writingDecision'], 'Polisher call receives claim map and writing decision');

$badUnderstandingFixture = [
    'schema_version' => 'understanding_result.v1',
    'stage' => 'understanding',
    'language' => 'en',
    'question_summary' => 'Rank TE-disease associations by graph evidence.',
    'entities' => [['name' => 'LINE-1', 'type' => 'transposable_element']],
    'ambiguities' => [],
    'mode_boundary' => 'agent_research',
    'required_evidence' => ['graph'],
    'warnings' => [],
];

$service = new TekgAcademicAgentService(['agent_test_mode' => true]);
$reflection = new ReflectionClass($service);
assert_true($reflection->hasMethod('fallbackUnderstandingNodeResult'), 'AcademicAgentService exposes Understanding fallback builder');
assert_true($reflection->hasMethod('fallbackPlanningNodeResult'), 'AcademicAgentService exposes Planning fallback builder');

$understandingFallbackMethod = $reflection->getMethod('fallbackUnderstandingNodeResult');
$planningFallbackMethod = $reflection->getMethod('fallbackPlanningNodeResult');

$failedUnderstanding = new NodeLlmResult(
    'understanding',
    '',
    null,
    false,
    ['llm: stage=understanding provider=deepseek model=deepseek-v4-pro: Relay timed out.'],
    'understanding_result.v1'
);
$deterministicAnalysis = [
    'intent' => 'mechanism',
    'answer_language' => 'english',
    'normalized_entities' => [
        ['canonical_label' => 'LINE-1', 'type' => 'transposable_element'],
    ],
];
$understandingFallback = $understandingFallbackMethod->invoke(
    $service,
    'How can LINE-1 contribute to disease?',
    'english',
    $deterministicAnalysis,
    $failedUnderstanding
);
assert_true($understandingFallback instanceof NodeLlmResult, 'Understanding fallback returns NodeLlmResult');
assert_same(true, $understandingFallback->ok, 'Understanding fallback is accepted as conservative success');
assert_same('understanding_result.v1', $understandingFallback->schema_version, 'Understanding fallback keeps schema contract');
assert_same('mechanism', (string)($understandingFallback->parsed_json['intent'] ?? ''), 'Understanding fallback preserves normalizer intent');
assert_true(in_array('llm_unavailable_conservative_fallback', (array)($understandingFallback->parsed_json['warnings'] ?? []), true), 'Understanding fallback records warning');

$failedPlanning = new NodeLlmResult(
    'planning',
    '',
    null,
    false,
    ['llm: stage=planning provider=deepseek model=deepseek-v4-pro: Relay returned an empty response.'],
    'research_plan.v1'
);
$deterministicPlan = [
    'summary' => 'Question: How can LINE-1 contribute to disease?; intent=mechanism',
    'required_evidence' => ['structured relations', 'mechanism literature'],
    'knowledge_gaps' => [],
];
$planningFallback = $planningFallbackMethod->invoke(
    $service,
    $deterministicPlan,
    ['Entity Resolver', 'Graph Plugin', 'Literature Plugin'],
    $failedPlanning
);
assert_true($planningFallback instanceof NodeLlmResult, 'Planning fallback returns NodeLlmResult');
assert_same(true, $planningFallback->ok, 'Planning fallback is accepted as conservative success');
assert_same('research_plan.v1', $planningFallback->schema_version, 'Planning fallback keeps schema contract');
assert_true(in_array('llm_unavailable_conservative_fallback', (array)($planningFallback->parsed_json['risks'] ?? []), true), 'Planning fallback records risk');

echo "Six-stage runtime tests passed.\n";
