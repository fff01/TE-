<?php
declare(strict_types=1);

require_once __DIR__ . '/agent/bootstrap.php';

function tekg_agent_execute_run(string $runId): int
{
    $state = tekg_agent_load_run_state($runId);
    $payload = tekg_agent_load_run_payload($runId);
    if (!is_array($state) || !is_array($payload)) {
        if (is_array($state)) {
            $state['status'] = 'failed';
            $state['error'] = 'Run payload or state is missing.';
            $state['failure_reason'] = 'Run payload or state is missing.';
            $state['finished_at'] = gmdate('c');
            tekg_agent_save_run_state($runId, $state);
        }
        return 1;
    }

    $status = (string)($state['status'] ?? '');
    if ($status === 'running' || $status === 'completed') {
        return 0;
    }

    $state['status'] = 'running';
    $state['started_at'] = gmdate('c');
    $state['error'] = '';
    $state['failure_reason'] = '';
    tekg_agent_save_run_state($runId, $state);

    $requestId = (string)($state['request_id'] ?? $payload['request_id'] ?? '');
    tekg_agent_append_diagnostic_log($requestId !== '' ? $requestId : tekg_agent_make_request_id(), 'agent_run_worker_started', [
        'run_id' => $runId,
        'started_at' => $state['started_at'],
        'sapi' => PHP_SAPI,
    ]);

    require_once __DIR__ . '/agent/orchestrator/Neo4jClient.php';
    require_once __DIR__ . '/agent/orchestrator/LlmClient.php';
    require_once __DIR__ . '/agent/orchestrator/CitationResolver.php';
    require_once __DIR__ . '/agent/orchestrator/EntityNormalizer.php';
    require_once __DIR__ . '/agent/plugins/EntityResolverPlugin.php';
    require_once __DIR__ . '/agent/plugins/GraphPlugin.php';
    require_once __DIR__ . '/agent/plugins/GraphAnalyticsPlugin.php';
    require_once __DIR__ . '/agent/plugins/CypherExplorerPlugin.php';
    require_once __DIR__ . '/agent/plugins/LiteraturePlugin.php';
    require_once __DIR__ . '/agent/plugins/LiteratureReadingPlugin.php';
    require_once __DIR__ . '/agent/plugins/TreePlugin.php';
    require_once __DIR__ . '/agent/plugins/ExpressionPlugin.php';
    require_once __DIR__ . '/agent/plugins/GenomePlugin.php';
    require_once __DIR__ . '/agent/plugins/SequencePlugin.php';
    require_once __DIR__ . '/agent/plugins/CitationResolverPlugin.php';
    require_once __DIR__ . '/agent/orchestrator/AcademicAgentService.php';

    $service = new TekgAcademicAgentService(tekg_agent_config());
    $doneEmitted = false;

    $emit = static function (array $event) use (&$state, $runId, &$doneEmitted): void {
        tekg_agent_append_run_event($runId, $event);
        $state = tekg_agent_update_run_state_for_event($state, $event);
        if ((string)($event['type'] ?? '') === 'done') {
            $doneEmitted = true;
        } elseif ($state['status'] !== 'completed' && $state['status'] !== 'failed') {
            $state['status'] = 'running';
        }
        tekg_agent_save_run_state($runId, $state);
    };

    try {
        $response = $service->stream($payload, $emit);
        $state['status'] = (bool)($response['writing_failed'] ?? false) ? 'failed' : 'completed';
        $state['answer'] = trim((string)($response['answer'] ?? ''));
        $state['language'] = (string)($response['language'] ?? $state['language'] ?? '');
        $state['writing_failed'] = (bool)($response['writing_failed'] ?? false);
        $state['failure_stage'] = (string)($response['failure_stage'] ?? '');
        $state['failure_reason'] = (string)($response['failure_reason'] ?? '');
        $state['used_plugins'] = array_values(array_map('strval', (array)($response['used_plugins'] ?? [])));
        $state['workflow_state'] = tekg_agent_json_safe((array)($response['workflow_state'] ?? $state['workflow_state'] ?? []));
        $state['current_stage'] = (string)($state['workflow_state']['current_stage'] ?? $state['current_stage'] ?? '');
        $state['finished_at'] = gmdate('c');
        tekg_agent_save_run_state($runId, $state);
        return 0;
    } catch (Throwable $error) {
        tekg_agent_append_diagnostic_log($requestId !== '' ? $requestId : tekg_agent_make_request_id(), 'agent_run_worker_exception', [
            'run_id' => $runId,
            'error' => $error->getMessage(),
            'sapi' => PHP_SAPI,
        ]);
        if (!$doneEmitted) {
            $errorEvent = [
                'type' => 'error',
                'request_id' => (string)($state['request_id'] ?? ''),
                'session_id' => (string)($state['session_id'] ?? ''),
                'message' => $error->getMessage(),
                'sequence' => (int)($state['last_sequence'] ?? 0) + 1,
            ];
            $emit($errorEvent);
            $doneEvent = [
                'type' => 'done',
                'request_id' => (string)($state['request_id'] ?? ''),
                'session_id' => (string)($state['session_id'] ?? ''),
                'payload' => [
                    'failed' => true,
                    'writing_failed' => true,
                    'failure_stage' => 'Worker',
                    'failure_reason' => $error->getMessage(),
                    'workflow_state' => $state['workflow_state'] ?? tekg_agent_default_workflow_state(),
                ],
                'sequence' => (int)($state['last_sequence'] ?? 0) + 2,
            ];
            $emit($doneEvent);
        }
        $state['status'] = 'failed';
        $state['error'] = $error->getMessage();
        $state['writing_failed'] = true;
        $state['failure_stage'] = 'Worker';
        $state['failure_reason'] = $error->getMessage();
        $state['finished_at'] = gmdate('c');
        tekg_agent_save_run_state($runId, $state);
        return 1;
    }
}
