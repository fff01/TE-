<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/agent/contracts/PluginResultEnvelope.php';

$schema = require __DIR__ . '/../api/agent/config/plugin_result_envelope_schema.php';

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

function assert_schema_types_are_valid(array $schema, string $path = '$'): void
{
    $allowed = ['object', 'array', 'string', 'number', 'integer', 'boolean', 'null'];
    if (array_key_exists('type', $schema)) {
        $types = is_array($schema['type']) ? $schema['type'] : [$schema['type']];
        foreach ($types as $type) {
            assert_true(is_string($type) && in_array($type, $allowed, true), "{$path}.type contains invalid JSON Schema type " . var_export($type, true));
        }
    }
    foreach ((array)($schema['properties'] ?? []) as $key => $child) {
        if (is_array($child)) {
            assert_schema_types_are_valid($child, "{$path}.properties.{$key}");
        }
    }
}

function assert_envelope_schema(array $schema, array $envelope, array $raw, string $caseName): void
{
    foreach ((array)($schema['required'] ?? []) as $key) {
        assert_true(array_key_exists($key, $envelope), "{$caseName}: missing {$key}");
    }

    assert_true(in_array($envelope['status'], (array)$schema['properties']['status']['enum'], true), "{$caseName}: invalid status");
    assert_true(is_array($envelope['evidence_items']), "{$caseName}: evidence_items must be array");
    assert_true(is_array($envelope['citations']), "{$caseName}: citations must be array");
    assert_true(is_array($envelope['routes']), "{$caseName}: routes must be array");
    assert_true(is_array($envelope['errors']), "{$caseName}: errors must be array");
    assert_true(is_array($envelope['metrics']), "{$caseName}: metrics must be array");
    assert_true(is_numeric($envelope['metrics']['result_count']), "{$caseName}: result_count must be numeric");
    assert_true(array_key_exists('duration_ms', $envelope['metrics']), "{$caseName}: missing duration_ms");
    assert_true(array_key_exists('confidence', $envelope['metrics']), "{$caseName}: missing confidence");
    assert_true(is_array($envelope['raw']), "{$caseName}: raw must be a summary array");
    assert_same(array_keys($raw), $envelope['raw']['keys'], "{$caseName}: raw summary keys");
    assert_same($raw['status'] ?? null, $envelope['raw']['status'], "{$caseName}: raw summary status");
    assert_same((bool)isset($raw['results']), $envelope['raw']['has_results'], "{$caseName}: raw summary has_results");
    assert_same((bool)isset($raw['citations']), $envelope['raw']['has_citations'], "{$caseName}: raw summary has_citations");
    assert_same($envelope['metrics']['result_count'], $envelope['raw']['result_count'], "{$caseName}: raw summary result_count");
    assert_true(!array_key_exists('results', $envelope['raw']), "{$caseName}: raw summary must not include full results payload");
    assert_true(!array_key_exists('citations', $envelope['raw']), "{$caseName}: raw summary must not include full citations payload");
}

assert_schema_types_are_valid($schema);

$graphRaw = [
    'status' => 'ok',
    'intent' => 'relationship',
    'display_summary' => 'Found 2 graph relationships for L1HS.',
    'result_counts' => ['relations' => 2],
    'results' => [
        'rows' => [
            ['source_name' => 'L1HS', 'target_name' => 'Cancer'],
            ['source_name' => 'L1HS', 'target_name' => 'TP53'],
        ],
    ],
    'evidence_items' => [
        ['claim' => 'L1HS is connected to Cancer.', 'support_strength' => 'medium'],
    ],
    'latency_ms' => 17,
];
$graph = PluginResultEnvelope::fromPluginResult('Graph Plugin', $graphRaw, ['intent' => 'relationship']);
assert_envelope_schema($schema, $graph, $graphRaw, 'graph-like');
assert_same('ok', $graph['status'], 'graph status');
assert_same('ok', $graph['legacy_status'], 'graph legacy status');
assert_same('relationship', $graph['intent'], 'graph intent');
assert_same(2, $graph['metrics']['result_count'], 'graph result_count from result_counts');
assert_same(17, $graph['metrics']['duration_ms'], 'graph duration');

$emptyRaw = [
    'status' => 'empty',
    'query_summary' => 'No sequence matches were found.',
    'results' => ['rows' => []],
];
$empty = PluginResultEnvelope::fromPluginResult('Sequence Plugin', $emptyRaw, ['intent' => 'sequence']);
assert_envelope_schema($schema, $empty, $emptyRaw, 'empty');
assert_same('empty', $empty['status'], 'empty status');
assert_same(0, $empty['metrics']['result_count'], 'empty count');
assert_same('No sequence matches were found.', $empty['summary'], 'empty summary from query_summary');

$errorRaw = [
    'status' => 'error',
    'display_summary' => 'Graph plugin failed.',
    'errors' => ['Neo4j timeout'],
];
$failed = PluginResultEnvelope::fromPluginResult('Graph Plugin', $errorRaw);
assert_envelope_schema($schema, $failed, $errorRaw, 'legacy-error');
assert_same('failed', $failed['status'], 'legacy error maps to failed');
assert_same('error', $failed['legacy_status'], 'legacy error is preserved');
assert_same(['Neo4j timeout'], $failed['errors'], 'legacy errors preserved');

$routeRaw = [
    'status' => 'ok',
    'results' => [
        'answer_markdown' => '[Sequence](http://localhost/TE-/search.php?q=L1HS#search-sequence-panel)',
        'primary_route' => [
            'label' => 'Sequence',
            'url' => 'http://localhost/TE-/search.php?q=L1HS#search-sequence-panel',
        ],
        'candidate_routes' => [
            ['label' => 'Sequence', 'url' => 'http://localhost/TE-/search.php?q=L1HS#search-sequence-panel'],
            ['label' => 'Genome', 'url' => 'http://localhost/TE-/search.php?q=L1HS#search-jbrowse-panel'],
        ],
    ],
];
$route = PluginResultEnvelope::fromPluginResult('Site Navigator Plugin', $routeRaw, ['intent' => 'navigation']);
assert_envelope_schema($schema, $route, $routeRaw, 'site-navigator');
assert_same('navigation', $route['intent'], 'route intent');
assert_same(2, $route['metrics']['result_count'], 'route count from candidate_routes');
assert_same(2, count($route['routes']), 'routes extracted');
assert_same($routeRaw['results']['answer_markdown'], $route['summary'], 'route summary from answer_markdown');

$literatureRaw = [
    'status' => 'partial',
    'query_summary' => 'Found 1 citation and 1 evidence item.',
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
    'confidence' => 0.72,
];
$literature = PluginResultEnvelope::fromPluginResult('Literature Plugin', $literatureRaw, ['intent' => 'literature']);
assert_envelope_schema($schema, $literature, $literatureRaw, 'literature');
assert_same('partial', $literature['status'], 'literature status');
assert_same(1, $literature['metrics']['result_count'], 'literature count from matched_records');
assert_same(0.72, $literature['metrics']['confidence'], 'literature confidence');
assert_same(1, count($literature['citations']), 'literature citations');

echo "PluginResultEnvelope tests passed.\n";
