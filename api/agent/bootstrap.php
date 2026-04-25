<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/expression_data.php';
require_once dirname(__DIR__, 2) . '/path_config.php';
require_once dirname(__DIR__, 2) . '/site_i18n.php';

function tekg_agent_env_value(array $names, ?string $default = null): ?string
{
    foreach ($names as $name) {
        $value = getenv($name);
        if ($value !== false && trim((string)$value) !== '') {
            return trim((string)$value);
        }
    }
    return $default;
}

function tekg_agent_local_config(): array
{
    static $local = null;
    if (is_array($local)) {
        return $local;
    }
    $path = dirname(__DIR__) . '/config.local.php';
    if (is_file($path)) {
        $loaded = require $path;
        if (is_array($loaded)) {
            $local = $loaded;
            return $local;
        }
    }
    $local = [];
    return $local;
}

function tekg_agent_ensure_dir(string $path): string
{
    if (!is_dir($path)) {
        @mkdir($path, 0777, true);
    }
    return $path;
}

function tekg_agent_pubmed_cache_dir(): string
{
    return tekg_agent_ensure_dir(TEKG_DATA_FS_DIR . '/cache/agent/pubmed');
}

function tekg_agent_session_cache_dir(): string
{
    return tekg_agent_ensure_dir(TEKG_DATA_FS_DIR . '/cache/agent/sessions');
}

function tekg_agent_diagnostics_dir(): string
{
    return tekg_agent_ensure_dir(TEKG_DATA_FS_DIR . '/cache/agent/diagnostics');
}

function tekg_agent_runs_dir(): string
{
    return tekg_agent_ensure_dir(TEKG_DATA_FS_DIR . '/cache/agent/runs');
}

function tekg_agent_make_run_id(): string
{
    try {
        return 'run_' . bin2hex(random_bytes(8));
    } catch (Throwable $_) {
        return 'run_' . md5((string)microtime(true) . '::' . (string)mt_rand());
    }
}

function tekg_agent_run_dir(string $runId): string
{
    $safe = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', $runId);
    return tekg_agent_ensure_dir(rtrim(tekg_agent_runs_dir(), '/\\') . '/' . $safe);
}

function tekg_agent_run_state_file(string $runId): string
{
    return tekg_agent_run_dir($runId) . '/state.json';
}

function tekg_agent_run_events_file(string $runId): string
{
    return tekg_agent_run_dir($runId) . '/events.jsonl';
}

function tekg_agent_run_payload_file(string $runId): string
{
    return tekg_agent_run_dir($runId) . '/payload.json';
}

function tekg_agent_default_workflow_state(): array
{
    return [
        'current_stage' => '',
        'stage_statuses' => [
            'Understanding' => 'pending',
            'Planning' => 'pending',
            'Collecting' => 'pending',
            'Executing' => 'pending',
            'Integrating' => 'pending',
            'Writing' => 'pending',
        ],
        'traversed_edges' => [],
        'complete' => false,
    ];
}

function tekg_agent_create_run_state(string $runId, array $payload): array
{
    return [
        'run_id' => $runId,
        'request_id' => (string)($payload['request_id'] ?? ''),
        'session_id' => (string)($payload['session_id'] ?? ''),
        'question' => (string)($payload['question'] ?? ''),
        'mode' => trim((string)($payload['mode'] ?? 'academic')) ?: 'academic',
        'status' => 'pending',
        'created_at' => gmdate('c'),
        'updated_at' => gmdate('c'),
        'started_at' => null,
        'finished_at' => null,
        'current_stage' => '',
        'workflow_state' => tekg_agent_default_workflow_state(),
        'events_count' => 0,
        'last_sequence' => 0,
        'answer' => '',
        'language' => '',
        'writing_failed' => false,
        'failure_stage' => '',
        'failure_reason' => '',
        'error' => '',
        'used_plugins' => [],
    ];
}

function tekg_agent_save_run_state(string $runId, array $state): void
{
    $path = tekg_agent_run_state_file($runId);
    $state['updated_at'] = gmdate('c');
    file_put_contents($path, json_encode(tekg_agent_json_safe($state), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

function tekg_agent_load_run_state(string $runId): ?array
{
    $path = tekg_agent_run_state_file($runId);
    if (!is_file($path)) {
        return null;
    }
    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function tekg_agent_save_run_payload(string $runId, array $payload): void
{
    $path = tekg_agent_run_payload_file($runId);
    file_put_contents($path, json_encode(tekg_agent_json_safe($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

function tekg_agent_load_run_payload(string $runId): ?array
{
    $path = tekg_agent_run_payload_file($runId);
    if (!is_file($path)) {
        return null;
    }
    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function tekg_agent_append_run_event(string $runId, array $event): void
{
    $path = tekg_agent_run_events_file($runId);
    @file_put_contents($path, json_encode(tekg_agent_json_safe($event), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function tekg_agent_load_run_events(string $runId, int $afterSequence = 0): array
{
    $path = tekg_agent_run_events_file($runId);
    if (!is_file($path)) {
        return [];
    }
    $events = [];
    $handle = @fopen($path, 'rb');
    if (!is_resource($handle)) {
        return [];
    }
    try {
        while (($line = fgets($handle)) !== false) {
            $decoded = json_decode(trim($line), true);
            if (!is_array($decoded)) {
                continue;
            }
            if ((int)($decoded['sequence'] ?? 0) <= $afterSequence) {
                continue;
            }
            $events[] = $decoded;
        }
    } finally {
        fclose($handle);
    }
    return $events;
}

function tekg_agent_update_run_state_for_event(array $state, array $event): array
{
    $state['request_id'] = (string)($event['request_id'] ?? $state['request_id'] ?? '');
    $state['session_id'] = (string)($event['session_id'] ?? $state['session_id'] ?? '');
    $state['events_count'] = (int)($state['events_count'] ?? 0) + 1;
    $state['last_sequence'] = max((int)($state['last_sequence'] ?? 0), (int)($event['sequence'] ?? 0));

    $type = (string)($event['type'] ?? '');
    if ($type === 'stage_state' && is_array($event['payload'] ?? null)) {
        $state['workflow_state'] = tekg_agent_json_safe((array)$event['payload']);
        $state['current_stage'] = (string)($state['workflow_state']['current_stage'] ?? '');
    }
    if ($type === 'answer') {
        $state['answer'] = trim((string)($event['message'] ?? ''));
        $state['language'] = (string)($event['language'] ?? $state['language'] ?? '');
    }
    if ($type === 'error') {
        $state['error'] = trim((string)($event['message'] ?? ''));
        $payload = is_array($event['payload'] ?? null) ? (array)$event['payload'] : [];
        if ((bool)($payload['writing_failed'] ?? false)) {
            $state['writing_failed'] = true;
            $state['failure_stage'] = (string)($payload['failure_stage'] ?? 'Writing');
            $state['failure_reason'] = (string)($payload['failure_reason'] ?? $state['error']);
        }
    }
    if ($type === 'done' && is_array($event['payload'] ?? null)) {
        $payload = (array)$event['payload'];
        $state['answer'] = trim((string)($payload['answer'] ?? $state['answer'] ?? ''));
        $state['language'] = (string)($payload['language'] ?? $state['language'] ?? '');
        $state['used_plugins'] = array_values(array_map('strval', (array)($payload['used_plugins'] ?? $state['used_plugins'] ?? [])));
        $state['writing_failed'] = (bool)($payload['writing_failed'] ?? $state['writing_failed'] ?? false);
        $state['failure_stage'] = (string)($payload['failure_stage'] ?? $state['failure_stage'] ?? '');
        $state['failure_reason'] = (string)($payload['failure_reason'] ?? $state['failure_reason'] ?? '');
        if (is_array($payload['workflow_state'] ?? null)) {
            $state['workflow_state'] = tekg_agent_json_safe((array)$payload['workflow_state']);
            $state['current_stage'] = (string)($state['workflow_state']['current_stage'] ?? '');
        }
        $state['status'] = $state['writing_failed'] ? 'failed' : 'completed';
        $state['finished_at'] = gmdate('c');
    }

    return $state;
}

function tekg_agent_cli_php_binary(): string
{
    $candidates = array_values(array_filter([
        tekg_agent_env_value(['PHP_CLI_BINARY'], null),
        preg_replace('/php-cgi\.exe$/i', 'php.exe', PHP_BINARY),
        preg_replace('/php\.exe$/i', 'php.exe', PHP_BINARY),
        dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'php.exe',
        dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'php-cli.exe',
        'php',
    ], static fn($value): bool => is_string($value) && trim($value) !== ''));

    foreach ($candidates as $candidate) {
        if ($candidate === 'php') {
            return $candidate;
        }
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    return 'php';
}

function tekg_agent_spawn_background_php(array $arguments): bool
{
    $phpBinary = tekg_agent_cli_php_binary();
    $commandParts = array_merge([$phpBinary], $arguments);

    if (DIRECTORY_SEPARATOR === '\\') {
        $powerShell = getenv('SystemRoot') !== false
            ? rtrim((string)getenv('SystemRoot'), '/\\') . '\\System32\\WindowsPowerShell\\v1.0\\powershell.exe'
            : 'powershell.exe';
        if (!is_file($powerShell)) {
            $powerShell = 'powershell.exe';
        }
        $escapedExe = "'" . str_replace("'", "''", $phpBinary) . "'";
        $escapedArgs = array_map(static function (string $part): string {
            return "'" . str_replace("'", "''", $part) . "'";
        }, $arguments);
        $argumentList = '@(' . implode(', ', $escapedArgs) . ')';
        $psCommand = "Start-Process -WindowStyle Hidden -FilePath {$escapedExe} -ArgumentList {$argumentList}";
        $command = '"' . $powerShell . '" -NoProfile -ExecutionPolicy Bypass -Command "' . str_replace('"', '\"', $psCommand) . '"';
        @exec($command, $output, $code);
        if ((int)$code === 0) {
            return true;
        }
        return false;
    }

    $command = implode(' ', array_map('escapeshellarg', $commandParts)) . ' > /dev/null 2>&1 &';
    $process = @popen($command, 'r');
    if (is_resource($process)) {
        @pclose($process);
        return true;
    }
    @exec($command, $output, $code);
    return (int)$code === 0;
}

function tekg_agent_run_kickoff_path(string $runId): string
{
    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/TE-/api/agent_runs.php'));
    $apiDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/.');
    if ($apiDir === '') {
        $apiDir = '/api';
    }
    return $apiDir . '/agent_run_kickoff.php?run_id=' . rawurlencode($runId);
}

function tekg_agent_fire_and_forget_local_request(string $path): bool
{
    $hostHeader = trim((string)($_SERVER['HTTP_HOST'] ?? '127.0.0.1'));
    $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    $defaultPort = $https ? 443 : 80;
    $hostForHeader = $hostHeader !== '' ? $hostHeader : '127.0.0.1';
    $socketHost = '127.0.0.1';
    $port = $defaultPort;

    if (strpos($hostForHeader, ':') !== false) {
        [$headerHost, $headerPort] = explode(':', $hostForHeader, 2);
        $hostForHeader = trim($headerHost) !== '' ? trim($headerHost) : '127.0.0.1';
        $port = is_numeric($headerPort) ? (int)$headerPort : $defaultPort;
    }

    $transport = $https ? 'ssl://' : '';
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($transport . $socketHost, $port, $errno, $errstr, 1.5);
    if (!is_resource($fp)) {
        return false;
    }

    stream_set_blocking($fp, false);
    $request =
        "GET {$path} HTTP/1.1\r\n"
        . "Host: {$hostForHeader}" . ($port !== $defaultPort ? ':' . $port : '') . "\r\n"
        . "Connection: Close\r\n"
        . "User-Agent: TEKG-Agent-Kickoff/1.0\r\n\r\n";
    $written = @fwrite($fp, $request);
    @fclose($fp);
    return $written !== false;
}

function tekg_agent_wait_for_run_start(string $runId, int $timeoutMs = 2500): bool
{
    $deadline = microtime(true) + max(100, $timeoutMs) / 1000;
    while (microtime(true) < $deadline) {
        $state = tekg_agent_load_run_state($runId);
        if (is_array($state) && (
            (string)($state['status'] ?? '') === 'running'
            || (int)($state['events_count'] ?? 0) > 0
            || !empty($state['started_at'])
        )) {
            return true;
        }
        usleep(100000);
    }
    return false;
}

function tekg_agent_entity_alias_map(): array
{
    static $map = null;
    if (is_array($map)) {
        return $map;
    }
    $path = __DIR__ . '/config/entity_alias_map.php';
    $loaded = is_file($path) ? require $path : [];
    $map = is_array($loaded) ? $loaded : [];
    return $map;
}

function tekg_agent_routing_policy(): array
{
    static $policy = null;
    if (is_array($policy)) {
        return $policy;
    }
    $path = __DIR__ . '/config/agent_routing_policy.json';
    if (!is_file($path)) {
        $policy = [];
        return $policy;
    }
    $decoded = json_decode((string)file_get_contents($path), true);
    $policy = is_array($decoded) ? $decoded : [];
    return $policy;
}

function tekg_agent_config(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }
    $local = tekg_agent_local_config();
    $config = [
        'dashscope_url' => trim((string)($local['dashscope_url'] ?? tekg_agent_env_value(['DASHSCOPE_API_URL_BIOLOGY', 'DASHSCOPE_API_URL'], 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions'))),
        'dashscope_key' => trim((string)($local['dashscope_key'] ?? tekg_agent_env_value(['DASHSCOPE_API_KEY_BIOLOGY', 'DASHSCOPE_API_KEY'], ''))),
        'dashscope_model' => trim((string)($local['dashscope_model'] ?? tekg_agent_env_value(['DASHSCOPE_MODEL_BIOLOGY', 'DASHSCOPE_MODEL'], 'qwen3.5-35b-a3b'))),
        'agent_writing_model' => trim((string)($local['agent_writing_model'] ?? tekg_agent_env_value(['TEKG_AGENT_WRITING_MODEL'], ''))),
        'deepseek_url' => trim((string)($local['deepseek_url'] ?? tekg_agent_env_value(['DEEPSEEK_API_URL'], 'https://api.deepseek.com/v1/chat/completions'))),
        'deepseek_key' => trim((string)($local['deepseek_key'] ?? tekg_agent_env_value(['DEEPSEEK_API_KEY'], ''))),
        'deepseek_model' => trim((string)($local['deepseek_model'] ?? tekg_agent_env_value(['DEEPSEEK_MODEL'], 'deepseek-chat'))),
        'deepseek_reasoner_model' => trim((string)($local['deepseek_reasoner_model'] ?? tekg_agent_env_value(['DEEPSEEK_REASONER_MODEL'], 'deepseek-reasoner'))),
        'llm_relay_url' => trim((string)($local['llm_relay_url'] ?? tekg_agent_env_value(['BIOLOGY_LLM_RELAY_URL', 'LLM_RELAY_URL'], ''))),
        'ssl_verify' => (bool)($local['ssl_verify'] ?? false),
        'agent_execution_timeout' => (int)($local['agent_execution_timeout'] ?? tekg_agent_env_value(['TEKG_AGENT_EXECUTION_TIMEOUT'], '300')),
        'llm_narrator_timeout' => (int)($local['llm_narrator_timeout'] ?? tekg_agent_env_value(['TEKG_AGENT_LLM_NARRATOR_TIMEOUT'], '6')),
        'llm_json_timeout' => (int)($local['llm_json_timeout'] ?? tekg_agent_env_value(['TEKG_AGENT_LLM_JSON_TIMEOUT'], '15')),
        'llm_answer_timeout' => (int)($local['llm_answer_timeout'] ?? tekg_agent_env_value(['TEKG_AGENT_LLM_ANSWER_TIMEOUT'], '20')),
        'llm_answer_chat_timeout' => (int)($local['llm_answer_chat_timeout'] ?? tekg_agent_env_value(['TEKG_AGENT_LLM_ANSWER_CHAT_TIMEOUT'], '18')),
        'llm_answer_reasoner_timeout' => (int)($local['llm_answer_reasoner_timeout'] ?? tekg_agent_env_value(['TEKG_AGENT_LLM_ANSWER_REASONER_TIMEOUT'], '35')),
        'neo4j_url' => trim((string)($local['neo4j_url'] ?? tekg_agent_env_value(['NEO4J_HTTP_URL_BIOLOGY', 'NEO4J_HTTP_URL'], 'http://127.0.0.1:7474/db/tekg21/tx/commit'))),
        'neo4j_user' => trim((string)($local['neo4j_user'] ?? tekg_agent_env_value(['NEO4J_USER_BIOLOGY', 'NEO4J_USER'], 'neo4j'))),
        'neo4j_password' => trim((string)($local['neo4j_password'] ?? tekg_agent_env_value(['NEO4J_PASSWORD_BIOLOGY', 'NEO4J_PASSWORD'], ''))),
        'pubmed_tool' => trim((string)tekg_agent_env_value(['PUBMED_TOOL'], 'TEKGAcademicAgent')),
        'pubmed_email' => trim((string)tekg_agent_env_value(['PUBMED_EMAIL'], '')),
        'pubmed_cache_dir' => tekg_agent_pubmed_cache_dir(),
    ];
    return $config;
}

function tekg_agent_normalize_lookup_token(string $value): string
{
    $value = trim(tekg_agent_lower($value));
    $value = preg_replace('/[\s\-_]+/u', '', $value) ?? $value;
    return trim($value);
}

function tekg_agent_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function tekg_agent_strlen(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function tekg_agent_substr(string $value, int $start, ?int $length = null): string
{
    if (function_exists('mb_substr')) {
        return $length === null ? mb_substr($value, $start, null, 'UTF-8') : mb_substr($value, $start, $length, 'UTF-8');
    }
    return $length === null ? substr($value, $start) : substr($value, $start, $length);
}

function tekg_agent_detect_language(string $question, string $fallback = 'english'): string
{
    if (preg_match('/[\x{4e00}-\x{9fff}]/u', $question)) {
        return 'chinese';
    }
    return in_array($fallback, ['chinese', 'english'], true) ? $fallback : 'english';
}

function tekg_agent_make_session_id(): string
{
    try {
        return bin2hex(random_bytes(16));
    } catch (Throwable $_) {
        return md5((string)microtime(true) . '::' . (string)mt_rand());
    }
}

function tekg_agent_make_request_id(): string
{
    try {
        return 'req_' . bin2hex(random_bytes(8));
    } catch (Throwable $_) {
        return 'req_' . md5((string)microtime(true) . '::' . (string)mt_rand());
    }
}

function tekg_agent_session_file(string $sessionId): string
{
    return rtrim(tekg_agent_session_cache_dir(), '/\\') . '/' . preg_replace('/[^a-zA-Z0-9_\-]+/', '_', $sessionId) . '.json';
}

function tekg_agent_default_session_memory(): array
{
    return [
        'topic_entities' => [],
        'last_intent' => '',
        'confirmed_claims' => [],
        'citations' => [],
        'failed_aliases' => [],
        'tool_history' => [],
        'resolved_entities' => [],
        'active_gaps' => [],
        'closed_gaps' => [],
        'failed_queries' => [],
        'weak_claims' => [],
        'strong_claims' => [],
        'claim_status_by_source' => [],
        'expert_attempts' => [],
        'compression_notes' => [],
        'next_step_hints' => [],
        'session_snapshot' => [],
    ];
}

function tekg_agent_load_session_memory(string $sessionId): array
{
    $path = tekg_agent_session_file($sessionId);
    if (!is_file($path)) {
        return tekg_agent_default_session_memory();
    }
    $decoded = json_decode((string)file_get_contents($path), true);
    if (!is_array($decoded)) {
        return tekg_agent_default_session_memory();
    }
    return array_replace(tekg_agent_default_session_memory(), $decoded);
}

function tekg_agent_save_session_memory(string $sessionId, array $memory): void
{
    $path = tekg_agent_session_file($sessionId);
    file_put_contents($path, json_encode($memory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

function tekg_agent_json_response(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

function tekg_agent_append_diagnostic_log(string $requestId, string $event, array $payload = []): void
{
    $record = [
        'ts' => gmdate('c'),
        'request_id' => $requestId,
        'event' => $event,
        'payload' => tekg_agent_json_safe($payload),
    ];
    $path = rtrim(tekg_agent_diagnostics_dir(), '/\\') . '/answer-chain.jsonl';
    @file_put_contents($path, json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function tekg_agent_http_request(
    string $url,
    string $method = 'GET',
    array $headers = [],
    ?string $body = null,
    int $timeout = 45,
    bool $sslVerify = false,
    ?string $requestId = null,
    ?string $stage = null
): array
{
    $startedAt = microtime(true);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        if (!$sslVerify) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch) ?: 'Unknown HTTP transport error';
            curl_close($ch);
            if ($requestId !== null) {
                tekg_agent_append_diagnostic_log($requestId, 'http_request_error', [
                    'stage' => $stage,
                    'url' => $url,
                    'timeout' => $timeout,
                    'error' => $error,
                    'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
                ]);
            }
            throw new RuntimeException($error);
        }
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $result = ['status' => $status, 'body' => (string)$raw];
        if ($requestId !== null) {
            tekg_agent_append_diagnostic_log($requestId, 'http_request_complete', [
                'stage' => $stage,
                'url' => $url,
                'timeout' => $timeout,
                'status' => $status,
                'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
                'body_length' => strlen((string)$raw),
            ]);
        }
        return $result;
    }

    $context = stream_context_create([
        'http' => [
            'method' => strtoupper($method),
            'header' => implode("\r\n", $headers),
            'content' => $body ?? '',
            'timeout' => $timeout,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => $sslVerify,
            'verify_peer_name' => $sslVerify,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        if ($requestId !== null) {
            tekg_agent_append_diagnostic_log($requestId, 'http_request_error', [
                'stage' => $stage,
                'url' => $url,
                'timeout' => $timeout,
                'error' => 'HTTP request failed.',
                'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            ]);
        }
        throw new RuntimeException('HTTP request failed.');
    }
    $status = 200;
    foreach (($http_response_header ?? []) as $headerLine) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string)$headerLine, $matches)) {
            $status = (int)$matches[1];
            break;
        }
    }
    $result = ['status' => $status, 'body' => (string)$raw];
    if ($requestId !== null) {
        tekg_agent_append_diagnostic_log($requestId, 'http_request_complete', [
            'stage' => $stage,
            'url' => $url,
            'timeout' => $timeout,
            'status' => $status,
            'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            'body_length' => strlen((string)$raw),
        ]);
    }
    return $result;
}

function tekg_agent_entity_candidate_groups(array $entity): array
{
    $strict = [];
    $broad = [];

    foreach ([
        (string)($entity['matched_alias'] ?? ''),
        (string)($entity['canonical_label'] ?? $entity['label'] ?? ''),
        (string)($entity['label'] ?? ''),
    ] as $candidate) {
        $candidate = trim($candidate);
        if ($candidate !== '') {
            $strict[] = $candidate;
        }
    }

    foreach ((array)($entity['aliases'] ?? []) as $alias) {
        $value = trim((string)$alias);
        if ($value !== '') {
            $strict[] = $value;
        }
    }

    foreach ((array)($entity['broad_aliases'] ?? []) as $alias) {
        $value = trim((string)$alias);
        if ($value !== '') {
            $broad[] = $value;
        }
    }

    $strict = array_values(array_unique($strict));
    $broad = array_values(array_filter(array_unique($broad), static fn(string $value): bool => !in_array($value, $strict, true)));

    return [
        'strict' => $strict,
        'broad' => $broad,
    ];
}

function tekg_agent_support_strength(string $level): string
{
    $value = strtolower(trim($level));
    return in_array($value, ['high', 'medium', 'low'], true) ? $value : 'medium';
}

function tekg_agent_make_evidence_item(
    string $sourcePlugin,
    string $claim,
    string $entityScope = '',
    string $supportStrength = 'medium',
    array $rawSourceRef = [],
    array $display = []
): array {
    $title = trim((string)($display['title'] ?? ''));
    $meta = trim((string)($display['meta'] ?? ''));
    $body = trim((string)($display['body'] ?? ''));

    if ($title === '') {
        $title = $entityScope !== '' ? $entityScope : $sourcePlugin;
    }
    if ($body === '') {
        $body = $claim;
    }

    return [
        'source_plugin' => $sourcePlugin,
        'entity_scope' => trim($entityScope),
        'claim' => trim($claim),
        'support_strength' => tekg_agent_support_strength($supportStrength),
        'raw_source_ref' => $rawSourceRef,
        'title' => $title,
        'meta' => $meta,
        'body' => $body,
    ];
}

function tekg_agent_normalize_evidence_item(mixed $item, string $defaultPlugin = 'Unknown'): ?array
{
    if (is_string($item)) {
        $value = trim($item);
        if ($value === '') {
            return null;
        }
        return tekg_agent_make_evidence_item($defaultPlugin, $value);
    }

    if (!is_array($item)) {
        return null;
    }

    if (isset($item['claim']) || isset($item['source_plugin'])) {
        return tekg_agent_make_evidence_item(
            (string)($item['source_plugin'] ?? $defaultPlugin),
            (string)($item['claim'] ?? $item['body'] ?? $item['title'] ?? ''),
            (string)($item['entity_scope'] ?? ''),
            (string)($item['support_strength'] ?? 'medium'),
            (array)($item['raw_source_ref'] ?? []),
            [
                'title' => (string)($item['title'] ?? ''),
                'meta' => (string)($item['meta'] ?? ''),
                'body' => (string)($item['body'] ?? ''),
            ]
        );
    }

    $title = trim((string)($item['title'] ?? $item['label'] ?? $item['name'] ?? ''));
    $body = trim((string)($item['body'] ?? $item['summary'] ?? $item['text'] ?? ''));
    if ($title === '' && $body === '') {
        return null;
    }

    return tekg_agent_make_evidence_item(
        $defaultPlugin,
        $body !== '' ? $body : $title,
        '',
        'medium',
        [],
        [
            'title' => $title,
            'meta' => (string)($item['meta'] ?? ''),
            'body' => $body,
        ]
    );
}

function tekg_agent_json_safe(mixed $value): mixed
{
    if (is_array($value)) {
        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = tekg_agent_json_safe($item);
        }
        return $normalized;
    }

    if (is_object($value)) {
        return tekg_agent_json_safe(get_object_vars($value));
    }

    if (is_scalar($value) || $value === null) {
        return $value;
    }

    return (string)$value;
}

function tekg_agent_node_contracts(): array
{
    return [
        'Question Understanding Node' => [
            'input' => ['question'],
            'output' => ['analysis', 'entity_resolution'],
        ],
        'Planning Node' => [
            'input' => ['question', 'analysis', 'entity_resolution', 'session_context'],
            'output' => ['planning'],
        ],
        'Evidence Collection Node' => [
            'input' => ['question', 'analysis', 'planning', 'graph_result', 'analytics_result', 'cypher_result', 'literature_result', 'literature_synthesis', 'tree_result', 'expression_result', 'genome_result', 'sequence_result', 'citation_result', 'collected_results', 'evidence_bundle', 'citation_bundle'],
            'output' => ['collection_state', 'active_expert', 'sufficiency_decision', 'graph_result', 'analytics_result', 'cypher_result', 'literature_result', 'literature_synthesis', 'tree_result', 'expression_result', 'genome_result', 'sequence_result', 'citation_result', 'collected_results', 'evidence_bundle', 'citation_bundle', 'compressed_result'],
        ],
        'Evidence Synthesis Node' => [
            'input' => ['question', 'analysis', 'planning', 'graph_result', 'analytics_result', 'cypher_result', 'literature_result', 'literature_synthesis', 'tree_result', 'expression_result', 'genome_result', 'sequence_result', 'citation_result', 'collected_results', 'evidence_bundle', 'citation_bundle', 'compressed_result'],
            'output' => ['supported_claims', 'conflicting_claims', 'missing_evidence', 'claim_clusters'],
        ],
        'Answer Structuring Node' => [
            'input' => ['question', 'analysis', 'planning', 'collected_results', 'compressed_result', 'graph_result', 'analytics_result', 'cypher_result', 'literature_result', 'literature_synthesis', 'tree_result', 'expression_result', 'genome_result', 'sequence_result', 'citation_result', 'supported_claims', 'conflicting_claims', 'missing_evidence', 'claim_clusters'],
            'output' => ['answer_structure'],
        ],
        'Answer Writer Node' => [
            'input' => ['question', 'analysis', 'answer_structure', 'supported_claims', 'conflicting_claims', 'missing_evidence', 'citation_bundle'],
            'output' => ['answer'],
        ],
        'Process Narrator Node' => [
            'input' => ['event_stream', 'analysis', 'entity_resolution', 'planning', 'collection_state', 'active_expert', 'sufficiency_decision', 'graph_result', 'analytics_result', 'cypher_result', 'literature_result', 'literature_synthesis', 'tree_result', 'expression_result', 'genome_result', 'sequence_result', 'citation_result', 'supported_claims', 'conflicting_claims', 'missing_evidence', 'claim_clusters', 'answer_structure', 'answer'],
            'output' => ['trace_event'],
        ],
    ];
}

interface TekgAgentPluginInterface
{
    public function getName(): string;
    public function run(array $context): array;
}
