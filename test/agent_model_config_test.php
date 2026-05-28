<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/agent/bootstrap.php';
require_once __DIR__ . '/../api/agent/plugin_registry.php';
require_once __DIR__ . '/../api/agent/orchestrator/DeepThinkService.php';
tekg_agent_require_academic_agent_service();

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "Assertion failed: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function call_private_method(object $service, string $methodName, array $args): mixed
{
    $method = new ReflectionMethod($service, $methodName);
    return $method->invokeArgs($service, $args);
}

$agentService = new TekgAcademicAgentService([
    'deepseek_model' => 'deepseek-v4-flash',
    'deepseek_reasoner_model' => 'deepseek-v4-flash',
    'agent_core_model' => 'deepseek-v4-pro',
    'agent_sufficiency_model' => 'deepseek-v4-pro',
    'agent_expert_model' => 'deepseek-v4-pro',
    'agent_narrator_model' => 'deepseek-v4-pro',
    'agent_answer_structure_model' => 'deepseek-v4-pro',
    'agent_writing_model' => 'deepseek-v4-pro',
    'agent_polisher_model' => 'deepseek-v4-pro',
]);

assert_same('deepseek-v4-pro', call_private_method($agentService, 'resolveCoreModel', [[]]), 'Agent core model uses agent_core_model.');
assert_same('deepseek-v4-pro', call_private_method($agentService, 'resolveCoreModel', [['model' => 'deepseek-v4-flash']]), 'Agent core model ignores generic frontend model payload.');
assert_same('deepseek-v4-pro', call_private_method($agentService, 'resolveSufficiencyModel', [[]]), 'Agent sufficiency model uses agent_sufficiency_model.');
assert_same('deepseek-v4-pro', call_private_method($agentService, 'resolveExpertModel', [[]]), 'Agent expert model uses agent_expert_model.');
assert_same('deepseek-v4-pro', call_private_method($agentService, 'resolveNarratorModel', [[]]), 'Agent narrator model uses agent_narrator_model.');
assert_same('deepseek-v4-pro', call_private_method($agentService, 'resolveAnswerStructureModel', [[]]), 'Agent answer_structure model uses agent_answer_structure_model.');
assert_same('deepseek-v4-pro', call_private_method($agentService, 'resolveWritingModel', [['intent' => 'sequence'], [], []]), 'Agent writing model uses agent_writing_model.');
assert_same('deepseek-v4-pro', call_private_method($agentService, 'resolvePolisherModel', [[], 'deepseek-v4-pro']), 'Agent polisher model uses agent_polisher_model.');

$deepThinkService = new TekgDeepThinkService([
    'deepseek_model' => 'deepseek-v4-flash',
    'deepseek_reasoner_model' => 'deepseek-v4-flash',
    'agent_core_model' => 'deepseek-v4-pro',
    'agent_expert_model' => 'deepseek-v4-pro',
    'agent_writing_model' => 'deepseek-v4-pro',
]);

assert_same('deepseek-v4-flash', call_private_method($deepThinkService, 'resolveModel', [[]]), 'DeepThink core model remains flash.');
assert_same(
    'deepseek-v4-flash',
    call_private_method($deepThinkService, 'resolveWritingModel', [[], ['intent' => 'sequence']]),
    'DeepThink simple writing model remains flash.'
);
assert_same(
    'deepseek-v4-flash',
    call_private_method($deepThinkService, 'resolveAnswerStructureModel', [[], ['intent' => 'sequence']]),
    'DeepThink simple answer_structure model remains flash.'
);

$runtimeConfig = tekg_agent_config();
foreach ([
    'deepseek_model',
    'deepseek_reasoner_model',
] as $key) {
    assert_same('deepseek-v4-flash', $runtimeConfig[$key] ?? null, "Runtime {$key} remains flash for DeepThink.");
}
foreach ([
    'agent_core_model',
    'agent_sufficiency_model',
    'agent_expert_model',
    'agent_narrator_model',
    'agent_answer_structure_model',
    'agent_writing_model',
    'agent_polisher_model',
] as $key) {
    assert_same('deepseek-v4-pro', $runtimeConfig[$key] ?? null, "Runtime {$key} defaults Agent to pro.");
}

echo "Agent model config tests passed.\n";
