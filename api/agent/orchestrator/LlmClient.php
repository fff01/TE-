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
            return '你是 TE-KG Academic Agent。你必须只基于给出的结构化插件结果和可追溯引用作答。不要编造不存在的机制，不要输出原始 chain-of-thought。请像研究助手一样自然组织答案：先给核心判断，再展开机制链或证据链；必要时可以分段或编号，但不要强制使用固定标题。对于证据较弱的部分，请明确说明。使用 Markdown。';
        }
        return 'You are TE-KG Academic Agent. Answer only from the structured plugin results and traceable citations that are provided. Do not invent unsupported mechanisms and do not reveal raw chain-of-thought. Write like a research assistant: give the main judgment first, then develop the mechanism or evidence chain in natural paragraphs. You may use numbering when helpful, but do not force fixed section headings. Clearly signal where the evidence is weak. Use Markdown.';
    }

    private function buildUserPrompt(string $question, array $planning, array $pluginCalls, array $evidence, array $citations, string $confidence, array $limits): string
    {
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
            "Prefer a natural, evidence-backed explanation instead of fixed section headings.\n\n" .
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
