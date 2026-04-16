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
        'llm_relay_url' => trim((string)($local['llm_relay_url'] ?? tekg_agent_env_value(['BIOLOGY_LLM_RELAY_URL', 'LLM_RELAY_URL'], ''))),
        'ssl_verify' => (bool)($local['ssl_verify'] ?? false),
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
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = preg_replace('/[\s\-_]+/u', '', $value) ?? $value;
    return trim($value);
}

function tekg_agent_detect_language(string $question, string $fallback = 'en'): string
{
    if (preg_match('/[\x{4e00}-\x{9fff}]/u', $question)) {
        return 'zh';
    }
    return in_array($fallback, ['zh', 'en'], true) ? $fallback : 'en';
}

function tekg_agent_make_session_id(): string
{
    try {
        return bin2hex(random_bytes(16));
    } catch (Throwable $_) {
        return md5((string)microtime(true) . '::' . (string)mt_rand());
    }
}

function tekg_agent_json_response(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

interface TekgAgentPluginInterface
{
    public function getName(): string;
    public function run(array $context): array;
}
