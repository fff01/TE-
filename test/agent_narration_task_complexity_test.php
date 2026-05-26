<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/agent/orchestrator/traits/AcademicAgentNarrationTrait.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

final class TekgAgentNarrationTaskComplexityHarness
{
    use TekgAcademicAgentNarrationTrait {
        emitAnalysisThoughtFlow as public captureAnalysisThoughtFlow;
    }

    public function capture(array $analysis): array
    {
        $events = [];
        $sequence = 0;
        $this->captureAnalysisThoughtFlow(
            static function (array $event) use (&$events): void {
                $events[] = $event;
            },
            'test-session',
            'test-model',
            'english',
            $analysis,
            $sequence
        );
        return $events;
    }

    private function emitEvent(?callable $emit, int &$eventSequence, array $event): void
    {
        $event['sequence'] = ++$eventSequence;
        if ($emit !== null) {
            $emit($event);
        }
    }
}

$harness = new TekgAgentNarrationTaskComplexityHarness();
$events = $harness->capture([
    'intent' => 'sequence',
    'complexity' => 'single_hop_reasoning',
    'task_complexity' => 'simple_lookup',
    'recommended_mode' => 'deepthink',
    'task_complexity_reason' => 'Direct lookup covered by Deep Think.',
    'normalized_entities' => [[
        'type' => 'TE',
        'canonical_label' => 'L1HS',
        'matched_alias' => 'L1HS',
    ]],
]);

$suggestionEvents = array_values(array_filter($events, static function (array $event): bool {
    return ($event['payload']['recommended_mode'] ?? null) === 'deepthink'
        && ($event['payload']['task_complexity'] ?? null) === 'simple_lookup';
}));

assert_true($suggestionEvents !== [], 'Deep Think recommendation should be present in analysis payload.');

$suggestionText = implode("\n", array_map(static fn(array $event): string => (string)($event['message'] ?? ''), $suggestionEvents));
assert_true(str_contains($suggestionText, 'Deep Think'), 'Deep Think recommendation should be visible to the user.');
assert_true(str_contains($suggestionText, 'Agent'), 'Recommendation should state Agent can continue.');
assert_true(str_contains($suggestionText, 'task_complexity') === false, 'User-facing recommendation should not expose internal field names.');
assert_true(str_contains($suggestionText, 'recommended_mode') === false, 'User-facing recommendation should not expose internal field names.');
assert_true(
    ($suggestionEvents[0]['payload']['task_complexity_reason'] ?? null) === 'Direct lookup covered by Deep Think.',
    'Task complexity reason should be preserved in payload.'
);
assert_true(
    ($suggestionEvents[0]['payload']['complexity'] ?? null) === 'single_hop_reasoning',
    'Legacy complexity should remain in payload.'
);

echo "Agent narration task complexity tests passed.\n";
