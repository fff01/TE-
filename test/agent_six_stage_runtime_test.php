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

$service = new TekgAcademicAgentService([
    'agent_test_mode' => true,
    'deepseek_model' => 'fixture-model',
    'deepseek_reasoner_model' => 'fixture-model',
    'agent_writing_model' => 'fixture-model',
    'six_stage_node_fixtures' => [
        'understanding' => $badUnderstandingFixture,
    ],
]);

$events = [];
$response = $service->stream([
    'question' => 'Please produce a graph ranking of transposable element disease associations and explain the evidence.',
    'language' => 'english',
    'request_id' => 'six-stage-runtime-bad-fixture',
    'session_id' => 'six-stage-runtime-bad-fixture-session',
], static function (array $event) use (&$events): void {
    $events[] = $event;
});

assert_same(true, (bool)($response['writing_failed'] ?? false), 'Bad required understanding artifact fails the run');
assert_same('Understanding', (string)($response['failure_stage'] ?? ''), 'Failure stage identifies Understanding');
assert_true(isset($response['six_stage_artifacts']['understanding']), 'Response records understanding artifact state');
assert_same(false, (bool)($response['six_stage_artifacts']['understanding']['ok'] ?? true), 'Bad understanding artifact is recorded as ok=false');
assert_true((array)($response['six_stage_artifacts']['understanding']['errors'] ?? []) !== [], 'Bad understanding artifact exposes validation errors');

$eventTypes = array_map(static fn(array $event): string => (string)($event['type'] ?? ''), $events);
assert_true(in_array('node_llm_error', $eventTypes, true), 'Bad required artifact emits node_llm_error');
assert_true(!in_array('tool_selected', $eventTypes, true), 'Run stops before plugin execution after required node failure');

echo "Six-stage runtime tests passed.\n";
