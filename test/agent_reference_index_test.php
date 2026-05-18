<?php
declare(strict_types=1);

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$indexPath = $root . '/reference/agent_theory_high_quality/README.md';
$oldExternalPath = $root . '/reference/external_examples/agent_theory_high_quality/README.md';

assert_true(is_file($indexPath), 'Agent theory index should live directly under reference/.');
assert_true(!is_file($oldExternalPath), 'Agent theory index should not live under reference/external_examples/.');

$content = (string)file_get_contents($indexPath);
foreach ([
    'ReAct',
    'Anthropic',
    'GraphRAG',
    'Text2Cypher',
    'Temporal',
    'Model Context Protocol',
    'OpenTelemetry',
    'BFCL',
    'RAGAS',
] as $needle) {
    assert_true(str_contains($content, $needle), "Agent theory index should include {$needle}.");
}

assert_true(str_contains($content, 'Core papers have been downloaded'), 'Index should state that core papers are downloaded.');
assert_true(str_contains($content, 'not been read in depth'), 'Index should state that sources have not been read in depth.');

echo "Agent reference index tests passed.\n";
