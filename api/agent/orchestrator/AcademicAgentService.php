<?php
declare(strict_types=1);

final class TekgAcademicAgentService
{
    private TekgAgentEntityNormalizer $normalizer;
    private TekgAgentLlmClient $llm;
    private array $plugins;

    public function __construct(private readonly array $config)
    {
        $neo4j = new TekgAgentNeo4jClient($config);
        $this->normalizer = new TekgAgentEntityNormalizer();
        $this->llm = new TekgAgentLlmClient($config);
        $this->plugins = [
            'Graph Plugin' => new TekgAgentGraphPlugin($neo4j),
            'Literature Plugin' => new TekgAgentLiteraturePlugin($neo4j, $config),
            'Tree Plugin' => new TekgAgentTreePlugin(),
            'Expression Plugin' => new TekgAgentExpressionPlugin(),
            'Genome Plugin' => new TekgAgentGenomePlugin(),
        ];
    }

    public function handle(array $payload): array
    {
        $question = trim((string)($payload['question'] ?? ''));
        if ($question === '') {
            throw new InvalidArgumentException('Question is required.');
        }
        $language = tekg_agent_detect_language($question, trim((string)($payload['language'] ?? 'en')));
        $model = trim((string)($payload['model'] ?? ($this->config['deepseek_model'] ?? 'deepseek-chat')));
        $sessionId = trim((string)($payload['session_id'] ?? ''));
        if ($sessionId === '') {
            $sessionId = tekg_agent_make_session_id();
        }

        $analysis = $this->normalizer->analyze($question, $language);
        $planning = $this->buildPlan($question, $analysis);
        $selectedPlugins = $this->selectPlugins($analysis);
        $pluginResults = [];
        $pluginCalls = [];
        $reasoningTrace = [[
            'step' => 'planning',
            'title' => 'Planned the academic search route',
            'status' => 'done',
            'details' => $planning['summary'],
        ]];

        foreach ($selectedPlugins as $pluginName) {
            $plugin = $this->plugins[$pluginName] ?? null;
            if (!$plugin instanceof TekgAgentPluginInterface) {
                continue;
            }
            $result = $plugin->run([
                'question' => $question,
                'analysis' => $analysis,
                'plugin_results' => $pluginResults,
                'planning' => $planning,
                'config' => $this->config,
            ]);
            $pluginResults[$pluginName] = $result;
            $pluginCalls[] = $result;
            $reasoningTrace[] = [
                'step' => 'querying_plugins',
                'title' => $pluginName,
                'status' => $result['status'] ?? 'ok',
                'details' => $this->pluginTraceDetails($result),
            ];
        }

        $evidence = $this->aggregateEvidence($pluginResults);
        $allCitations = [];
        foreach ($pluginResults as $result) {
            foreach ((array)($result['citations'] ?? []) as $citation) {
                $allCitations[] = $citation;
            }
        }
        $citations = $this->dedupeCitations($allCitations);
        $limits = [];
        foreach ($pluginResults as $result) {
            foreach ((array)($result['errors'] ?? []) as $error) {
                $limits[] = (string)$error;
            }
        }
        if ($evidence === []) {
            $limits[] = $language === 'zh'
                ? '当前没有足够的本地或外部证据来直接支持该问题。'
                : 'The current local and external evidence was not sufficient to directly support this question.';
        }
        $confidence = $this->inferConfidence($pluginResults, $evidence, $citations);
        try {
            $llm = $this->llm->complete($model, $question, $language, $planning, $pluginCalls, $evidence, $citations, $confidence, $limits);
        } catch (Throwable $error) {
            $llm = [
                'ok' => false,
                'provider' => $this->inferProvider($model),
                'model' => $model,
                'content' => '',
                'error' => $error->getMessage(),
            ];
            $limits[] = $language === 'zh'
                ? '模型服务当前不可用，已切换到结构化后备回答。'
                : 'The model service is currently unavailable, so the response fell back to a deterministic structured summary.';
        }
        $answer = ($llm['ok'] ?? false) ? (string)($llm['content'] ?? '') : $this->fallbackAnswer($question, $language, $evidence, $citations, $limits);

        $reasoningTrace[] = [
            'step' => 'synthesizing',
            'title' => 'Synthesized the final answer',
            'status' => ($llm['ok'] ?? false) ? 'done' : 'fallback',
            'details' => ($llm['ok'] ?? false)
                ? ($language === 'zh' ? '使用结构化插件证据组织最终回答。' : 'Used the structured plugin evidence to compose the final answer.')
                : ($language === 'zh' ? '模型不可用，使用确定性后备总结。' : 'The model was unavailable, so a deterministic fallback summary was used.'),
        ];

        return [
            'question' => $question,
            'mode' => trim((string)($payload['mode'] ?? 'academic')) ?: 'academic',
            'language' => $language,
            'session_id' => $sessionId,
            'model' => $model,
            'model_provider' => $llm['provider'] ?? $this->inferProvider($model),
            'answer' => $answer,
            'reasoning_trace' => $reasoningTrace,
            'used_plugins' => $selectedPlugins,
            'plugin_calls' => $pluginCalls,
            'evidence' => $evidence,
            'citations' => $citations,
            'confidence' => $confidence,
            'limits' => array_values(array_unique($limits)),
            'planning' => $planning,
        ];
    }

    private function buildPlan(string $question, array $analysis): array
    {
        $entities = array_map(static fn(array $entity): string => (string)$entity['label'] . ' (' . (string)$entity['type'] . ')', (array)($analysis['normalized_entities'] ?? []));
        $needs = [];
        if (($analysis['asks_for_papers'] ?? false)) $needs[] = 'literature evidence';
        if (($analysis['asks_for_expression'] ?? false)) $needs[] = 'expression summaries';
        if (($analysis['asks_for_genome'] ?? false)) $needs[] = 'genome loci';
        if (($analysis['asks_for_classification'] ?? false)) $needs[] = 'tree classification';
        if ($needs === []) $needs[] = 'structured relationship evidence';
        return [
            'question_type' => (string)($analysis['intent'] ?? 'relationship'),
            'key_entities' => $entities,
            'required_evidence' => $needs,
            'summary' => 'Question: ' . $question . '; intent=' . (string)($analysis['intent'] ?? 'relationship') . '; entities=' . ($entities === [] ? 'none recognized' : implode(', ', $entities)),
        ];
    }

    private function selectPlugins(array $analysis): array
    {
        $selected = [];
        $entities = (array)($analysis['normalized_entities'] ?? []);
        $hasTeOrDisease = false;
        foreach ($entities as $entity) {
            if (in_array((string)($entity['type'] ?? ''), ['TE', 'Disease'], true)) {
                $hasTeOrDisease = true;
                break;
            }
        }
        if ($hasTeOrDisease || in_array((string)($analysis['intent'] ?? ''), ['relationship', 'comparison', 'literature'], true)) {
            $selected[] = 'Graph Plugin';
        }
        if (($analysis['asks_for_papers'] ?? false) || ($analysis['compare_mode'] ?? false)) {
            $selected[] = 'Literature Plugin';
        }
        if (($analysis['asks_for_classification'] ?? false) || in_array((string)($analysis['intent'] ?? ''), ['classification'], true)) {
            $selected[] = 'Tree Plugin';
        }
        if (($analysis['asks_for_expression'] ?? false) || $this->containsKeyword($analysis, 'cancer')) {
            $selected[] = 'Expression Plugin';
        }
        if (($analysis['asks_for_genome'] ?? false)) {
            $selected[] = 'Genome Plugin';
        }
        if ($selected === []) {
            $selected[] = 'Graph Plugin';
        }
        return array_values(array_unique($selected));
    }

    private function pluginTraceDetails(array $result): string
    {
        $summary = trim((string)($result['query_summary'] ?? ''));
        $evidenceCount = count((array)($result['evidence_items'] ?? []));
        $citationCount = count((array)($result['citations'] ?? []));
        return trim($summary . ' Evidence items: ' . $evidenceCount . '. Citations: ' . $citationCount . '.');
    }

    private function aggregateEvidence(array $pluginResults): array
    {
        $all = [];
        foreach ($pluginResults as $result) {
            foreach ((array)($result['evidence_items'] ?? []) as $item) {
                $item = trim((string)$item);
                if ($item !== '') {
                    $all[] = $item;
                }
            }
        }
        return array_values(array_unique($all));
    }

    private function inferConfidence(array $pluginResults, array $evidence, array $citations): string
    {
        $okPlugins = 0;
        foreach ($pluginResults as $result) {
            if (($result['status'] ?? '') === 'ok') {
                $okPlugins++;
            }
        }
        if ($okPlugins >= 3 && count($evidence) >= 4 && count($citations) >= 3) {
            return 'high';
        }
        if ($okPlugins >= 1 && (count($evidence) >= 1 || count($citations) >= 1)) {
            return 'medium';
        }
        return 'low';
    }

    private function fallbackAnswer(string $question, string $language, array $evidence, array $citations, array $limits): string
    {
        $references = $citations === []
            ? ($language === 'zh' ? "无可引用文献。" : 'No references found.')
            : implode("\n", array_map(static function (array $citation): string {
                $title = trim((string)($citation['title'] ?? ''));
                $pmid = trim((string)($citation['pmid'] ?? ''));
                return '- ' . ($title !== '' ? $title : 'Untitled record') . ($pmid !== '' ? ' (PMID: ' . $pmid . ')' : '');
            }, $citations));
        return ($language === 'zh'
            ? "## Conclusion\n已根据结构化插件结果整理该问题的当前答案。\n\n## Evidence Summary\n"
            : "## Conclusion\nThe current answer was assembled from the structured plugin results.\n\n## Evidence Summary\n")
            . ($evidence === [] ? ($language === 'zh' ? "- 当前没有足够的结构化证据。\n" : "- There is not yet enough structured evidence.\n") : implode("\n", array_map(static fn(string $item): string => '- ' . $item, $evidence)) . "\n")
            . "\n## References\n" . $references
            . "\n\n## Limits\n" . (($limits === []) ? ($language === 'zh' ? "- 暂无额外限制说明。" : "- No additional limits reported.") : implode("\n", array_map(static fn(string $item): string => '- ' . $item, $limits)));
    }

    private function dedupeCitations(array $citations): array
    {
        $seen = [];
        $unique = [];
        foreach ($citations as $citation) {
            $key = trim((string)($citation['pmid'] ?? ''));
            if ($key === '') {
                $key = mb_strtolower(trim((string)($citation['title'] ?? '')), 'UTF-8');
            }
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $citation;
        }
        return $unique;
    }

    private function inferProvider(string $model): string
    {
        return str_contains(mb_strtolower($model, 'UTF-8'), 'qwen') ? 'qwen' : 'deepseek';
    }

    private function containsKeyword(array $analysis, string $keyword): bool
    {
        return in_array($keyword, (array)($analysis['question_keywords'] ?? []), true);
    }
}
