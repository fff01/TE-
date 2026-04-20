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
