<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/agent/contracts/EvidencePackage.php';

$schema = require __DIR__ . '/../api/agent/config/evidence_package_schema.php';

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

function assert_has_keys(array $required, array $actual, string $caseName): void
{
    foreach ($required as $key) {
        assert_true(array_key_exists($key, $actual), "{$caseName}: missing {$key}");
    }
}

assert_same(
    ['schema_version', 'question', 'generated_at', 'claims', 'evidence_items', 'citation_map', 'route_map', 'metrics', 'limits', 'errors'],
    $schema['required'],
    'schema required keys'
);

$graphEnvelope = [
    'plugin' => 'Graph Plugin',
    'status' => 'ok',
    'legacy_status' => 'ok',
    'intent' => 'relationship',
    'summary' => 'Found L1HS disease relationships.',
    'raw' => [],
    'evidence_items' => [
        ['claim' => 'L1HS is associated with cancer.', 'support_strength' => 'medium'],
    ],
    'citations' => [],
    'routes' => [],
    'metrics' => ['duration_ms' => 12, 'result_count' => 1, 'confidence' => null],
    'errors' => [],
];
$graphPackage = EvidencePackage::fromPluginResults('How is L1HS related to cancer?', ['intent' => 'relationship'], [
    ['result_envelope' => $graphEnvelope],
]);
assert_has_keys($schema['required'], $graphPackage, 'graph package');
assert_same('evidence_package.v1', $graphPackage['schema_version'], 'schema version');
assert_same('How is L1HS related to cancer?', $graphPackage['question'], 'question preserved');
assert_same('claim_1', $graphPackage['claims'][0]['id'], 'deterministic claim id');
assert_same('evidence_1', $graphPackage['evidence_items'][0]['id'], 'deterministic evidence id');
assert_same(['evidence_1'], $graphPackage['claims'][0]['evidence_ids'], 'claim evidence mapping');
assert_same('Graph Plugin', $graphPackage['evidence_items'][0]['plugin'], 'evidence plugin');
assert_same(1, $graphPackage['metrics']['plugin_count'], 'graph plugin count');

$literatureRaw = [
    'status' => 'partial',
    'query_summary' => 'Found literature support.',
    'results' => [
        'matched_records' => [
            ['pmid' => '12345', 'title' => 'LINE-1 and disease'],
        ],
    ],
    'evidence_items' => [
        ['claim' => 'LINE-1 activation is associated with disease.', 'support_strength' => 'high'],
    ],
    'citations' => [
        ['pmid' => '12345', 'title' => 'LINE-1 and disease'],
    ],
    'confidence' => 0.9,
];
$literaturePackage = EvidencePackage::fromPluginResults('What papers support LINE-1 disease links?', ['intent' => 'literature'], [
    ['plugin' => 'Literature Plugin', 'result' => $literatureRaw],
]);
assert_same('claim_1', $literaturePackage['claims'][0]['id'], 'literature claim id');
assert_same('citation_1', $literaturePackage['citation_map'][0]['id'], 'deterministic citation id');
assert_same(['citation_1'], $literaturePackage['claims'][0]['citation_ids'], 'claim citation mapping');
assert_same('12345', $literaturePackage['citation_map'][0]['citation']['pmid'], 'citation payload');
assert_same('partial', $literaturePackage['metrics']['statuses']['Literature Plugin'], 'normalized raw status');

$siteEnvelope = [
    'plugin' => 'Site Navigator Plugin',
    'status' => 'ok',
    'legacy_status' => 'ok',
    'intent' => 'navigation',
    'summary' => 'Open the sequence panel for L1HS.',
    'raw' => [],
    'evidence_items' => [],
    'citations' => [],
    'routes' => [
        ['label' => 'Sequence', 'url' => '/TE-/search.php?q=L1HS#search-sequence-panel'],
    ],
    'metrics' => ['duration_ms' => 5, 'result_count' => 1, 'confidence' => null],
    'errors' => [],
];
$sitePackage = EvidencePackage::fromPluginResults('Where can I inspect L1HS sequence?', ['intent' => 'navigation'], [
    ['result_envelope' => $siteEnvelope],
]);
assert_same('Open the sequence panel for L1HS.', $sitePackage['claims'][0]['text'], 'summary creates claim');
assert_same('route_1', $sitePackage['route_map'][0]['id'], 'deterministic route id');
assert_same(['route_1'], $sitePackage['claims'][0]['route_ids'], 'claim route mapping');

$emptyPackage = EvidencePackage::fromPluginResults('No matches?', [], [
    [
        'result_envelope' => [
            'plugin' => 'Sequence Plugin',
            'status' => 'empty',
            'legacy_status' => 'empty',
            'intent' => 'sequence',
            'summary' => 'No sequence matches were found.',
            'raw' => [],
            'evidence_items' => [],
            'citations' => [],
            'routes' => [],
            'metrics' => ['duration_ms' => null, 'result_count' => 0, 'confidence' => null],
            'errors' => [],
        ],
    ],
]);
assert_same([], $emptyPackage['claims'], 'empty result has no claims');
assert_same(1, $emptyPackage['metrics']['empty_plugin_count'], 'empty plugin counted');

$errorPackage = EvidencePackage::fromPluginResults('Will this fail?', [], [
    [
        'result_envelope' => [
            'plugin' => 'Graph Plugin',
            'status' => 'failed',
            'legacy_status' => 'error',
            'intent' => 'relationship',
            'summary' => 'Graph failed.',
            'raw' => [],
            'evidence_items' => [],
            'citations' => [],
            'routes' => [],
            'metrics' => ['duration_ms' => 1000, 'result_count' => 0, 'confidence' => null],
            'errors' => ['Neo4j timeout'],
        ],
    ],
]);
assert_same('Neo4j timeout', $errorPackage['errors'][0]['message'], 'plugin error captured');
assert_same(1, $errorPackage['metrics']['failed_plugin_count'], 'failed plugin counted');

$longSummary = str_repeat('A', 900);
$truncatedPackage = EvidencePackage::fromPluginResults('Summarize long result.', [], [
    [
        'result_envelope' => [
            'plugin' => 'Literature Plugin',
            'status' => 'ok',
            'legacy_status' => 'ok',
            'intent' => 'literature',
            'summary' => $longSummary,
            'raw' => [],
            'evidence_items' => [],
            'citations' => [],
            'routes' => [],
            'metrics' => ['duration_ms' => null, 'result_count' => 1, 'confidence' => null],
            'errors' => [],
        ],
    ],
], ['summary_max_chars' => 120]);
assert_same(120, strlen($truncatedPackage['claims'][0]['text']), 'summary is truncated');
assert_same(1, $truncatedPackage['limits']['truncation_count'], 'truncation counted');
assert_same('claim_1', $truncatedPackage['limits']['truncated_summaries'][0]['claim_id'], 'truncation references claim');

$validation = EvidencePackage::validate($literaturePackage);
assert_same(true, $validation['ok'], 'valid package passes validation');
assert_same([], $validation['errors'], 'valid package has no validation errors');

$invalid = $literaturePackage;
unset($invalid['claims'][0]['id']);
$invalidValidation = EvidencePackage::validate($invalid);
assert_same(false, $invalidValidation['ok'], 'invalid package fails validation');
assert_true(in_array('claims[0].id is required', $invalidValidation['errors'], true), 'validation reports missing claim id');

$invalidMetrics = $literaturePackage;
$invalidMetrics['metrics'] = [];
$invalidMetrics['limits'] = [];
$invalidMetricsValidation = EvidencePackage::validate($invalidMetrics);
assert_same(false, $invalidMetricsValidation['ok'], 'empty metrics and limits fail validation');
assert_true(in_array('metrics.plugin_count must be an integer', $invalidMetricsValidation['errors'], true), 'validation reports missing metric');
assert_true(in_array('limits.summary_max_chars must be an integer', $invalidMetricsValidation['errors'], true), 'validation reports missing limit');

echo "EvidencePackage tests passed.\n";
