<?php
declare(strict_types=1);

final class TekgAgentLlmClient
{
    public function __construct(private readonly array $config)
    {
    }

    public function complete(
        string $model,
        string $question,
        string $language,
        array $planning,
        array $pluginCalls,
        array $evidence,
        array $citations,
        string $confidence,
        array $limits
    ): array {
        $provider = $this->inferProvider($model);
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($language)],
            ['role' => 'user', 'content' => $this->buildUserPrompt($question, $planning, $pluginCalls, $evidence, $citations, $confidence, $limits)],
        ];

        if (!empty($this->config['llm_relay_url'])) {
            return $this->callRelay($provider, $model, $messages);
        }
        return $this->callProvider($provider, $model, $messages);
    }

    private function inferProvider(string $model): string
    {
        $value = strtolower(trim($model));
        if (str_contains($value, 'qwen')) {
            return 'qwen';
        }
        return 'deepseek';
    }

    private function systemPrompt(string $language): string
    {
        if ($language === 'chinese') {
            return '你是 TE-KG Academic Agent。你只能基于已经提供的结构化插件结果、标准化证据对象和可追溯引用来作答。不要编造不存在的关系、机制或结论，也不要输出原始 chain-of-thought。请像研究助理一样自然组织回答：先给核心判断，再展开证据链或机制链；可以分段或编号，但不要强制使用固定标题。必须明确区分强证据、弱证据和证据空缺；不能把“没有查到”写成否定结论。正文引用尽量使用 PMID 风格。使用 Markdown。';
        }

        return 'You are TE-KG Academic Agent. Answer only from the structured plugin results, standardized evidence objects, and traceable citations that are provided. Do not invent unsupported relations, mechanisms, or conclusions, and do not reveal raw chain-of-thought. Write like a research assistant: give the main judgment first, then develop the mechanism or evidence chain in natural paragraphs. You may use numbering when helpful, but do not force fixed section headings. Explicitly distinguish strong evidence, weak evidence, and evidence gaps. Never turn "no result" into a negative scientific conclusion. Prefer PMID-style in-text citations when available. Use Markdown.';
    }

    private function buildUserPrompt(
        string $question,
        array $planning,
        array $pluginCalls,
        array $evidence,
        array $citations,
        string $confidence,
        array $limits
    ): string {
        $payload = [
            'question' => $question,
            'planning' => $planning,
            'plugin_calls' => $pluginCalls,
            'evidence' => $evidence,
            'citations' => $citations,
            'confidence' => $confidence,
            'limits' => $limits,
        ];

        return "Use the following structured evidence to answer the research question.\n" .
            "Write a natural academic explanation rather than a fixed report template.\n" .
            "If the question asks for mechanism, prefer a causal chain. If it asks for comparison, prefer a contrastive structure. If the evidence is weak or incomplete, say so explicitly.\n\n" .
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function callRelay(string $provider, string $model, array $messages): array
    {
        $payload = [
            'provider' => $provider,
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.2,
            'enable_thinking' => true,
        ];
        $decoded = $this->httpJson((string)$this->config['llm_relay_url'], $payload, []);
        $response = $decoded['response'] ?? [];
        $content = (string)($response['choices'][0]['message']['content'] ?? '');
        return [
            'ok' => $content !== '',
            'provider' => $provider,
            'model' => $model,
            'content' => trim($content),
            'error' => $content !== '' ? null : 'Relay returned an empty response.',
        ];
    }

    private function callProvider(string $provider, string $model, array $messages): array
    {
        $url = $provider === 'qwen' ? (string)($this->config['dashscope_url'] ?? '') : (string)($this->config['deepseek_url'] ?? '');
        $key = $provider === 'qwen' ? (string)($this->config['dashscope_key'] ?? '') : (string)($this->config['deepseek_key'] ?? '');
        if ($url === '' || $key === '') {
            return ['ok' => false, 'provider' => $provider, 'model' => $model, 'content' => '', 'error' => 'Provider credentials are missing.'];
        }

        $decoded = $this->httpJson($url, [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.2,
            'enable_thinking' => true,
        ], [
            'Authorization: Bearer ' . $key,
        ]);

        $content = (string)($decoded['choices'][0]['message']['content'] ?? '');
        return [
            'ok' => $content !== '',
            'provider' => $provider,
            'model' => $model,
            'content' => trim($content),
            'error' => $content !== '' ? null : 'Provider returned an empty response.',
        ];
    }

    private function httpJson(string $url, array $payload, array $headers): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $allHeaders = array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers);
        $response = tekg_agent_http_request($url, 'POST', $allHeaders, $body, 90, (bool)($this->config['ssl_verify'] ?? false));
        $decoded = json_decode((string)$response['body'], true);
        if (!is_array($decoded)) {
            throw new RuntimeException('LLM provider returned invalid JSON.');
        }
        if ((int)$response['status'] >= 400) {
            throw new RuntimeException('LLM provider returned HTTP ' . (int)$response['status']);
        }
        return $decoded;
    }
}
