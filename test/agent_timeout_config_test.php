<?php
declare(strict_types=1);

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

$bootstrapSource = (string)file_get_contents(__DIR__ . '/../api/agent/bootstrap.php');
$runStoreSource = (string)file_get_contents(__DIR__ . '/../api/agent/bootstrap/run_store.php');
$localConfig = require __DIR__ . '/../api/config.local.php';

foreach ([
    "'TEKG_AGENT_EXECUTION_TIMEOUT'], '900'" => 'default Agent execution timeout is 15 minutes',
    "'TEKG_AGENT_LLM_SIX_STAGE_NODE_TIMEOUT'], '90'" => 'default six-stage node timeout is 90 seconds',
    "'TEKG_AGENT_LLM_ANSWER_CHAT_TIMEOUT'], '120'" => 'default answer chat timeout is 120 seconds',
    "'TEKG_AGENT_LLM_ANSWER_REASONER_TIMEOUT'], '180'" => 'default answer reasoner timeout is 180 seconds',
] as $needle => $message) {
    assert_true(str_contains($bootstrapSource, $needle), $message);
}

assert_same(900, (int)($localConfig['agent_execution_timeout'] ?? 0), 'local Agent execution timeout is 15 minutes');
assert_same(90, (int)($localConfig['llm_six_stage_node_timeout'] ?? 0), 'local six-stage node timeout is 90 seconds');
assert_same(120, (int)($localConfig['llm_answer_chat_timeout'] ?? 0), 'local answer chat timeout is 120 seconds');
assert_same(180, (int)($localConfig['llm_answer_reasoner_timeout'] ?? 0), 'local answer reasoner timeout is 180 seconds');

assert_true(
    preg_match('/timeoutSeconds\s*=\s*max\(\s*90\s*,\s*\(int\)\(\$config\[[^\]]+agent_execution_timeout[^\]]+\]\s*\?\?\s*900\)\)\s*\+\s*30\s*;/s', $runStoreSource) === 1,
    'stale timeout remains configured Agent execution timeout plus 30 seconds'
);

echo "Agent timeout config tests passed.\n";
