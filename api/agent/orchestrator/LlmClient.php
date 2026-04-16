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
        return $language === 'zh'
            ? '你是 TE-KG Academic Agent。只基于提供的结构化插件结果和引用写答案。不要编造不存在的事实，不要暴露原始长链推理。使用 Markdown，并固定输出：Conclusion、Evidence Summary、References、Limits。'
            : 'You are TE-KG Academic Agent. Answer only from the structured plugin results and citations provided. Do not invent unsupported facts and do not reveal raw chain-of-thought. Use Markdown with the fixed sections: Conclusion, Evidence Summary, References, Limits.';
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
        return "Use the following structured evidence to answer the question.\n\n" .
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
        return ['ok' => $content !== '', 'provider' => $provider, 'model' => $model, 'content' => trim($content), 'error' => $content !== '' ? null : 'Relay returned an empty response.'];
    }

    private function callProvider(string $provider, string $model, array $messages): array
    {
        $url = $provider === 'qwen' ? (string)($this->config['dashscope_url'] ?? '') : (string)($this->config['deepseek_url'] ?? '');
        $key = $provider === 'qwen' ? (string)($this->config['dashscope_key'] ?? '') : (string)($this->config['deepseek_key'] ?? '');
        if ($url === '' || $key === '') {
            return ['ok' => false, 'provider' => $provider, 'model' => $model, 'content' => '', 'error' => 'Provider credentials are missing.'];
        }
        $decoded = $this->httpJson($url, ['model' => $model, 'messages' => $messages, 'temperature' => 0.2, 'enable_thinking' => true], [
            'Authorization: Bearer ' . $key,
        ]);
        $content = (string)($decoded['choices'][0]['message']['content'] ?? '');
        return ['ok' => $content !== '', 'provider' => $provider, 'model' => $model, 'content' => trim($content), 'error' => $content !== '' ? null : 'Provider returned an empty response.'];
    }

    private function httpJson(string $url, array $payload, array $headers): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ch = curl_init($url);
        $allHeaders = array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_POSTFIELDS => $body,
        ]);
        if (($this->config['ssl_verify'] ?? false) !== true) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch) ?: 'Unknown LLM cURL failure';
            curl_close($ch);
            throw new RuntimeException('LLM request failed: ' . $error);
        }
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('LLM provider returned invalid JSON.');
        }
        if ($status >= 400) {
            throw new RuntimeException('LLM provider returned HTTP ' . $status);
        }
        return $decoded;
    }
}
