<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/agent/orchestrator/LlmClient.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function assert_contains(string $needle, string $haystack, string $message): void
{
    assert_true(str_contains($haystack, $needle), $message . "\nMissing: {$needle}");
}

function assert_not_contains(string $needle, string $haystack, string $message): void
{
    assert_true(!str_contains($haystack, $needle), $message . "\nForbidden: {$needle}");
}

$client = new TekgAgentLlmClient([]);
$reflection = new ReflectionClass($client);

assert_true($reflection->hasMethod('writeEvidenceWalkDraft'), 'writer public method exists');
assert_true($reflection->getMethod('writeEvidenceWalkDraft')->isPublic(), 'writer method is public');
assert_true($reflection->hasMethod('polishEvidenceWalkAnswer'), 'polisher public method exists');
assert_true($reflection->getMethod('polishEvidenceWalkAnswer')->isPublic(), 'polisher method is public');

$question = 'How does LINE-1 promote cancer?';
$analysis = ['intent' => 'mechanism', 'task_complexity' => 'complex'];
$evidencePackage = [
    'schema_version' => 'evidence_package.v1',
    'claims' => [
        [
            'id' => 'claim_1',
            'text' => 'LINE-1 activity is associated with cancer.',
            'citation_ids' => ['citation_1'],
        ],
    ],
    'evidence_items' => [
        ['id' => 'evidence_1', 'claim_id' => 'claim_1', 'text' => 'Evidence summary.'],
    ],
    'citation_map' => [
        ['id' => 'citation_1', 'citation' => ['pmid' => '12345', 'url' => 'https://pubmed.ncbi.nlm.nih.gov/12345/']],
    ],
    'plugin_results' => ['must_not_reach_prompt' => true],
    'raw_result' => ['must_not_reach_prompt' => true],
    'display_details' => ['must_not_reach_prompt' => true],
];
$evidenceWalk = [
    'schema_version' => 'evidence_walk.v1',
    'walk_steps' => [
        ['id' => 'walk_step_1', 'claim_node_id' => 'claim_node_1', 'evidence_refs' => ['evidence_1'], 'citation_refs' => ['citation_1']],
    ],
    'gaps' => [['id' => 'gap_1', 'type' => 'missing_direct_mechanism']],
];
$reportPlan = [
    'schema_version' => 'report_plan.v1',
    'report_type' => 'mechanism_review',
    'sections' => [
        ['key' => 'mechanism_chain', 'title' => 'Mechanism chain'],
        ['key' => 'limitations', 'title' => 'Limitations'],
    ],
    'claim_sequence' => [['claim_node_id' => 'claim_node_1']],
];
$limits = ['max_words' => 900];
$integrityReport = [
    'ok' => false,
    'errors' => ['Unsupported strong conclusion without evidence.'],
];

$writerPromptMethod = $reflection->getMethod('buildEvidenceWalkDraftPrompt');
$writerPrompt = $writerPromptMethod->invoke(
    $client,
    $question,
    $analysis,
    $evidencePackage,
    $evidenceWalk,
    $reportPlan,
    'medium',
    $limits
);

assert_contains('"evidence_package"', $writerPrompt, 'writer prompt includes evidence_package');
assert_contains('"evidence_walk"', $writerPrompt, 'writer prompt includes evidence_walk');
assert_contains('"report_plan"', $writerPrompt, 'writer prompt includes report_plan');
assert_contains('evidence first', $writerPrompt, 'writer prompt uses nature-writing evidence-first policy');
assert_contains('Build the argument before prose', $writerPrompt, 'writer prompt requires argument before prose');
assert_contains('claim-evidence map', $writerPrompt, 'writer prompt requires claim-evidence map');
assert_contains('bounded claims', $writerPrompt, 'writer prompt requires bounded claims');
assert_contains('missing inputs and evidence gaps', $writerPrompt, 'writer prompt requires gap handling');
assert_contains('Do not add evidence', $writerPrompt, 'writer prompt forbids new evidence');

foreach (['raw_result', 'display_details', 'full plugin_results', '"plugin_results"'] as $forbidden) {
    assert_not_contains($forbidden, $writerPrompt, "writer prompt excludes {$forbidden}");
}

$polisherPromptMethod = $reflection->getMethod('buildEvidenceWalkPolishPrompt');
$polisherPrompt = $polisherPromptMethod->invoke(
    $client,
    'Draft answer with PMID 12345 and [paper](https://pubmed.ncbi.nlm.nih.gov/12345/).',
    $analysis,
    $evidencePackage,
    $evidenceWalk,
    $reportPlan,
    $integrityReport
);

assert_contains('"evidence_package"', $polisherPrompt, 'polisher prompt includes evidence_package');
assert_contains('"evidence_walk"', $polisherPrompt, 'polisher prompt includes evidence_walk');
assert_contains('"report_plan"', $polisherPrompt, 'polisher prompt includes report_plan');
assert_contains('no new claims', $polisherPrompt, 'polisher prompt forbids new claims');
assert_contains('no new PMID', $polisherPrompt, 'polisher prompt forbids new PMID');
assert_contains('no new URLs', $polisherPrompt, 'polisher prompt forbids new URLs');
assert_contains('no new citations', $polisherPrompt, 'polisher prompt forbids new citations');
assert_contains('preserve links and citations', $polisherPrompt, 'polisher prompt preserves links and citations');
assert_contains('downgrade unsupported claims', $polisherPrompt, 'polisher prompt downgrades unsupported claims');
assert_contains('Return only the final polished report text', $polisherPrompt, 'polisher prompt returns answer text only');
assert_not_contains('revision notes if possible', $polisherPrompt, 'polisher prompt does not ask for revision notes in final answer');

foreach (['raw_result', 'display_details', 'full plugin_results', '"plugin_results"'] as $forbidden) {
    assert_not_contains($forbidden, $polisherPrompt, "polisher prompt excludes {$forbidden}");
}

echo "Agent research report prompt tests passed.\n";
