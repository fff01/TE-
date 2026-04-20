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

    public function narrateEvent(string $model, string $language, array $event): ?string
    {
        $provider = $this->inferProvider($model);
        if (!$this->canCallModel($provider)) {
            return null;
        }

        $messages = [
            ['role' => 'system', 'content' => $this->narratorSystemPrompt('english')],
            ['role' => 'user', 'content' => $this->buildNarratorPrompt($event)],
        ];

        try {
            if (!empty($this->config['llm_relay_url'])) {
                $response = $this->callRelay($provider, $model, $messages, false, (int)($this->config['llm_narrator_timeout'] ?? 8), 'narrator');
            } else {
                $response = $this->callProvider($provider, $model, $messages, false, (int)($this->config['llm_narrator_timeout'] ?? 8), 'narrator');
            }
        } catch (Throwable) {
            return null;
        }

        $content = trim((string)($response['content'] ?? ''));
        return $content !== '' ? $content : null;
    }

    public function generateJson(string $model, string $instruction, array $payload, ?int $timeout = null, string $stage = 'json'): ?array
    {
        $provider = $this->inferProvider($model);
        if (!$this->canCallModel($provider)) {
            return null;
        }

        $messages = [
            [
                'role' => 'system',
                'content' => 'Return only valid JSON. Do not use Markdown fences. Do not add explanatory text outside the JSON object.',
            ],
            [
                'role' => 'user',
                'content' => $instruction . "\n\n" . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            ],
        ];

        try {
            $effectiveTimeout = $timeout ?? (int)($this->config['llm_json_timeout'] ?? 20);
            $response = !empty($this->config['llm_relay_url'])
                ? $this->callRelay($provider, $model, $messages, false, $effectiveTimeout, $stage)
                : $this->callProvider($provider, $model, $messages, false, $effectiveTimeout, $stage);
        } catch (Throwable) {
            return null;
        }

        $content = trim((string)($response['content'] ?? ''));
        if ($content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\}|\[.*\])\s*```/si', $content, $matches) === 1) {
            $decoded = json_decode((string)$matches[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (preg_match('/(\{.*\}|\[.*\])/si', $content, $matches) === 1) {
            $decoded = json_decode((string)$matches[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    public function assessSufficiency(string $model, array $payload, ?int $timeout = null): ?array
    {
        return $this->generateJson(
            $model,
            'Assess whether the currently collected evidence is sufficient to answer the question. ' .
            'Return JSON with keys is_sufficient (boolean), reason (string), missing_dimensions (array of strings), recommended_next_experts (array of strings). ' .
            'Do not recommend experts that already ran successfully unless the payload explicitly indicates that their result was empty or weak.',
            $payload,
            $timeout,
            'sufficiency'
        );
    }

    public function generateAnswerStructure(string $model, array $payload, ?int $timeout = null): ?array
    {
        return $this->generateJson(
            $model,
            'Build an answer_structure JSON object for a TE-KG academic answer. ' .
            'Return JSON with keys response_mode, opening_claim, section_plan, claim_order, citation_policy, uncertainty_notes. ' .
            'section_plan and claim_order must be arrays of strings. uncertainty_notes must be an array of strings.',
            $payload,
            $timeout,
            'answer_structure'
        );
    }

    public function writeStructuredAnswer(
        string $model,
        string $language,
        string $question,
        array $analysis,
        array $answerStructure,
        array $supportedClaims,
        array $conflictingClaims,
        array $missingEvidence,
        array $citations,
        string $confidence,
        array $limits,
        ?int $timeout = null
    ): array {
        $provider = $this->inferProvider($model);
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($language)],
            ['role' => 'user', 'content' => $this->buildStructuredAnswerPrompt(
                $question,
                $analysis,
                $answerStructure,
                $supportedClaims,
                $conflictingClaims,
                $missingEvidence,
                $citations,
                $confidence,
                $limits
            )],
        ];

        $effectiveTimeout = $timeout ?? (int)($this->config['llm_answer_timeout'] ?? 40);
        if (!empty($this->config['llm_relay_url'])) {
            return $this->callRelay($provider, $model, $messages, true, $effectiveTimeout, 'answer');
        }
        return $this->callProvider($provider, $model, $messages, true, $effectiveTimeout, 'answer');
    }

    public function writeDirectAnswer(
        string $model,
        string $language,
        string $question,
        array $analysis,
        array $supportedClaims,
        array $conflictingClaims,
        array $missingEvidence,
        array $citations,
        string $confidence,
        array $limits,
        ?int $timeout = null
    ): array {
        $provider = $this->inferProvider($model);
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($language)],
            ['role' => 'user', 'content' => $this->buildDirectAnswerPrompt(
                $question,
                $analysis,
                $supportedClaims,
                $conflictingClaims,
                $missingEvidence,
                $citations,
                $confidence,
                $limits
            )],
        ];

        $effectiveTimeout = $timeout ?? (int)($this->config['llm_answer_timeout'] ?? 40);
        if (!empty($this->config['llm_relay_url'])) {
            return $this->callRelay($provider, $model, $messages, true, $effectiveTimeout, 'answer');
        }
        return $this->callProvider($provider, $model, $messages, true, $effectiveTimeout, 'answer');
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

    private function narratorSystemPrompt(string $language): string
    {
        if ($language === 'chinese') {
            return '你是 TE-KG Agent 的过程叙述器。你只能基于提供的真实事件对象写 1 到 2 句简短过程说明。只能描述已经真实发生的事情，不能补脑，不能编造额外步骤，不能暴露原始 chain-of-thought。语气自然、克制、研究型。';
        }

        return 'You are the TE-KG Agent process narrator. Write 1 to 2 short sentences that describe only the real execution event that is provided. Do not invent extra steps, do not speculate, and do not reveal raw chain-of-thought. Keep the tone concise, natural, and research-oriented.';
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

    private function buildNarratorPrompt(array $event): string
    {
        return "Summarize this execution event for the user in 1 to 2 short sentences.\n" .
            "Only describe what really happened in this event.\n\n" .
            json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function buildStructuredAnswerPrompt(
        string $question,
        array $analysis,
        array $answerStructure,
        array $supportedClaims,
        array $conflictingClaims,
        array $missingEvidence,
        array $citations,
        string $confidence,
        array $limits
    ): string {
        $payload = [
            'question' => $question,
            'analysis' => $analysis,
            'answer_structure' => $answerStructure,
            'supported_claims' => $supportedClaims,
            'conflicting_claims' => $conflictingClaims,
            'missing_evidence' => $missingEvidence,
            'citations' => $citations,
            'confidence' => $confidence,
            'limits' => $limits,
        ];

        return "Write the final answer only from the structured answer plan and evidence below.\n" .
            "Follow answer_structure strictly. Do not improvise extra sections outside section_plan unless needed for one short limitation note.\n" .
            "Do not restate raw JSON. Convert the plan into a natural academic answer.\n\n" .
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function buildDirectAnswerPrompt(
        string $question,
        array $analysis,
        array $supportedClaims,
        array $conflictingClaims,
        array $missingEvidence,
        array $citations,
        string $confidence,
        array $limits
    ): string {
        $payload = [
            'question' => $question,
            'analysis' => $analysis,
            'supported_claims' => $supportedClaims,
            'conflicting_claims' => $conflictingClaims,
            'missing_evidence' => $missingEvidence,
            'citations' => $citations,
            'confidence' => $confidence,
            'limits' => $limits,
        ];

        return "Write the final answer directly from the evidence below.\n" .
            "Start with the main conclusion, then add the most important supporting facts.\n" .
            "Keep the answer concise for simple factual questions, and only mention uncertainty if the evidence is incomplete or conflicting.\n" .
            "Do not invent unsupported details and do not restate raw JSON.\n\n" .
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function callRelay(
        string $provider,
        string $model,
        array $messages,
        bool $enableThinking = true,
        int $timeout = 90,
        string $stage = 'llm'
    ): array
    {
        $payload = [
            'provider' => $provider,
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.2,
            'enable_thinking' => $enableThinking,
        ];
        $decoded = $this->httpJson((string)$this->config['llm_relay_url'], $payload, [], $timeout, $stage);
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

    private function callProvider(
        string $provider,
        string $model,
        array $messages,
        bool $enableThinking = true,
        int $timeout = 90,
        string $stage = 'llm'
    ): array
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
            'enable_thinking' => $enableThinking,
        ], [
            'Authorization: Bearer ' . $key,
        ], $timeout, $stage);

        $content = (string)($decoded['choices'][0]['message']['content'] ?? '');
        return [
            'ok' => $content !== '',
            'provider' => $provider,
            'model' => $model,
            'content' => trim($content),
            'error' => $content !== '' ? null : 'Provider returned an empty response.',
        ];
    }

    private function canCallModel(string $provider): bool
    {
        if (!empty($this->config['llm_relay_url'])) {
            return true;
        }

        $url = $provider === 'qwen' ? (string)($this->config['dashscope_url'] ?? '') : (string)($this->config['deepseek_url'] ?? '');
        $key = $provider === 'qwen' ? (string)($this->config['dashscope_key'] ?? '') : (string)($this->config['deepseek_key'] ?? '');
        return $url !== '' && $key !== '';
    }

    private function httpJson(string $url, array $payload, array $headers, int $timeout = 90, string $stage = 'llm'): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $allHeaders = array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers);
        $response = tekg_agent_http_request(
            $url,
            'POST',
            $allHeaders,
            $body,
            $timeout,
            (bool)($this->config['ssl_verify'] ?? false),
            trim((string)($this->config['request_id'] ?? '')) !== '' ? (string)$this->config['request_id'] : null,
            'llm_' . $stage
        );
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
