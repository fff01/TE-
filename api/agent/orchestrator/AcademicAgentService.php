<?php
declare(strict_types=1);

final class TekgAcademicAgentService
{
    private TekgAgentEntityNormalizer $normalizer;
    private TekgAgentLlmClient $llm;
    /** @var array<string,TekgAgentPluginInterface> */
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
        return $this->execute($payload, null);
    }

    public function stream(array $payload, callable $emit): array
    {
        return $this->execute($payload, $emit);
    }

    private function execute(array $payload, ?callable $emit): array
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
        $pluginQueue = $this->initialPluginQueue($analysis);
        $pluginResults = [];
        $pluginCalls = [];
        $reasoningTrace = [];
        $detailCounter = 0;

        $this->emit($emit, [
            'type' => 'planning',
            'session_id' => $sessionId,
            'message' => $planning['narrative'],
            'payload' => $planning,
        ]);

        $reasoningTrace[] = [
            'step' => 'planning',
            'title' => 'Planning',
            'status' => 'done',
            'details' => $planning['narrative'],
        ];

        for ($index = 0; $index < count($pluginQueue); $index++) {
            $pluginName = $pluginQueue[$index];
            $plugin = $this->plugins[$pluginName] ?? null;
            if (!$plugin instanceof TekgAgentPluginInterface) {
                continue;
            }

            $this->emit($emit, [
                'type' => 'tool_start',
                'session_id' => $sessionId,
                'plugin_name' => $pluginName,
                'message' => $this->toolStartMessage($pluginName, $analysis),
            ]);

            $result = $plugin->run([
                'question' => $question,
                'analysis' => $analysis,
                'plugin_results' => $pluginResults,
                'planning' => $planning,
                'config' => $this->config,
            ]);

            $pluginResults[$pluginName] = $result;
            $pluginCalls[] = $result;

            $detailId = 'tool-' . (++$detailCounter);
            $payloadForUi = $this->toolPayloadForUi($result);

            $this->emit($emit, [
                'type' => 'tool_progress',
                'session_id' => $sessionId,
                'plugin_name' => $pluginName,
                'message' => (string)($result['display_summary'] ?? $result['query_summary'] ?? ''),
            ]);

            $this->emit($emit, [
                'type' => 'tool_result',
                'session_id' => $sessionId,
                'plugin_name' => $pluginName,
                'display_label' => (string)($result['display_label'] ?? $pluginName),
                'summary' => (string)($result['display_summary'] ?? $result['query_summary'] ?? ''),
                'message' => (string)(($result['display_details']['result_message'] ?? '') ?: ''),
                'detail_payload_id' => $detailId,
                'payload' => $payloadForUi,
            ]);

            $reasoningTrace[] = [
                'step' => 'querying_plugins',
                'title' => $pluginName,
                'status' => (string)($result['status'] ?? 'ok'),
                'details' => (string)($result['display_summary'] ?? $result['query_summary'] ?? ''),
            ];

            foreach ($this->maybeAppendPlugins($analysis, $pluginName, $result, $pluginQueue) as $additionalPlugin) {
                $pluginQueue[] = $additionalPlugin;
            }
        }

        $evidence = $this->aggregateEvidence($pluginResults);
        $citations = $this->aggregateCitations($pluginResults);
        $limits = $this->aggregateLimits($pluginResults, $analysis);
        $confidence = $this->inferConfidence($pluginResults, $evidence, $citations);

        $this->emit($emit, [
            'type' => 'synthesizing',
            'session_id' => $sessionId,
            'message' => $this->synthesizingMessage($language, $analysis, $pluginResults),
        ]);

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

        $answer = ($llm['ok'] ?? false)
            ? (string)($llm['content'] ?? '')
            : $this->fallbackAnswer($question, $analysis, $pluginResults, $evidence, $citations, $limits);

        $reasoningTrace[] = [
            'step' => 'synthesizing',
            'title' => 'Synthesis',
            'status' => ($llm['ok'] ?? false) ? 'done' : 'fallback',
            'details' => $this->synthesizingMessage($language, $analysis, $pluginResults),
        ];

        $response = [
            'question' => $question,
            'mode' => trim((string)($payload['mode'] ?? 'academic')) ?: 'academic',
            'language' => $language,
            'session_id' => $sessionId,
            'model' => $model,
            'model_provider' => $llm['provider'] ?? $this->inferProvider($model),
            'answer' => $answer,
            'reasoning_trace' => $reasoningTrace,
            'used_plugins' => array_map(static fn(array $call): string => (string)($call['plugin_name'] ?? ''), $pluginCalls),
            'plugin_calls' => $pluginCalls,
            'evidence' => $evidence,
            'citations' => $citations,
            'confidence' => $confidence,
            'limits' => array_values(array_unique($limits)),
            'planning' => $planning,
        ];

        $this->emit($emit, [
            'type' => 'answer',
            'session_id' => $sessionId,
            'language' => $language,
            'message' => $answer,
        ]);
        $this->emit($emit, [
            'type' => 'done',
            'session_id' => $sessionId,
            'payload' => [
                'confidence' => $confidence,
                'used_plugins' => $response['used_plugins'],
            ],
        ]);

        return $response;
    }

    private function buildPlan(string $question, array $analysis): array
    {
        $entities = array_map(
            static fn(array $entity): string => (string)$entity['label'] . ' (' . (string)$entity['type'] . ')',
            (array)($analysis['normalized_entities'] ?? [])
        );
        $needs = $this->knowledgeNeeds($analysis);

        return [
            'question_type' => (string)($analysis['intent'] ?? 'relationship'),
            'key_entities' => $entities,
            'requested_target_types' => (array)($analysis['requested_target_types'] ?? []),
            'required_evidence' => $needs,
            'summary' => 'Question: ' . $question . '; intent=' . (string)($analysis['intent'] ?? 'relationship') . '; entities=' . ($entities === [] ? 'none recognized' : implode(', ', $entities)),
            'narrative' => $this->planningNarrative($question, $analysis, $entities, $needs),
        ];
    }

    private function initialPluginQueue(array $analysis): array
    {
        $queue = [];
        $intent = (string)($analysis['intent'] ?? 'relationship');

        if (in_array($intent, ['mechanism', 'relationship', 'comparison', 'literature'], true)) {
            $queue[] = 'Graph Plugin';
        }

        if (($analysis['asks_for_papers'] ?? false) || ($analysis['needs_external_literature'] ?? false) || in_array($intent, ['mechanism', 'literature', 'comparison'], true)) {
            $queue[] = 'Literature Plugin';
        }

        if (($analysis['asks_for_classification'] ?? false) || $intent === 'classification') {
            $queue[] = 'Tree Plugin';
        }

        if (($analysis['asks_for_expression'] ?? false)) {
            $queue[] = 'Expression Plugin';
        }

        if (($analysis['asks_for_genome'] ?? false) || $intent === 'genome') {
            $queue[] = 'Genome Plugin';
        }

        if ($queue === []) {
            $queue[] = 'Graph Plugin';
        }

        return array_values(array_unique($queue));
    }

    private function maybeAppendPlugins(array $analysis, string $pluginName, array $result, array $queue): array
    {
        $append = [];
        $status = (string)($result['status'] ?? 'ok');
        $intent = (string)($analysis['intent'] ?? 'relationship');

        if ($pluginName === 'Graph Plugin') {
            $relationCount = (int)(($result['result_counts']['relations'] ?? 0));
            if ($relationCount === 0 && !in_array('Literature Plugin', $queue, true)) {
                $append[] = 'Literature Plugin';
            }
            if (($analysis['asks_for_classification'] ?? false) && !in_array('Tree Plugin', $queue, true)) {
                $append[] = 'Tree Plugin';
            }
            if (($analysis['asks_for_expression'] ?? false) && !in_array('Expression Plugin', $queue, true)) {
                $append[] = 'Expression Plugin';
            }
            if (($analysis['asks_for_genome'] ?? false) && !in_array('Genome Plugin', $queue, true)) {
                $append[] = 'Genome Plugin';
            }
            if ($intent === 'mechanism' && $relationCount < 4 && !in_array('Literature Plugin', $queue, true)) {
                $append[] = 'Literature Plugin';
            }
        }

        if ($pluginName === 'Literature Plugin' && $status === 'empty' && !in_array('Tree Plugin', $queue, true) && ($analysis['asks_for_classification'] ?? false)) {
            $append[] = 'Tree Plugin';
        }

        return array_values(array_unique($append));
    }

    private function planningNarrative(string $question, array $analysis, array $entities, array $needs): string
    {
        $intent = (string)($analysis['intent'] ?? 'relationship');
        $entityText = $entities === [] ? ($analysis['language'] === 'zh' ? '当前还没有稳定识别到明确实体。' : 'No stable named entities were recognized yet.') : implode(', ', $entities);

        if (($analysis['language'] ?? 'en') === 'zh') {
            $lead = match ($intent) {
                'mechanism' => '这个问题是在追问一个机制链，我需要先判断缺的是哪类机制证据，再决定先查图谱还是先补文献。',
                'literature' => '这个问题明确要证据和文献支持，所以我会先确认本地图谱里有什么，再决定是否补查 PubMed。',
                'classification' => '这个问题偏分类和谱系，我会先确认树图上下文，再看是否还需要结构化关系补充。',
                'expression' => '这个问题偏表达背景，我会先看表达数据，再决定是否需要别的证据。',
                default => '我会先确认本地图谱里是否已经有直接关系，再判断还缺什么知识。'
            };
            return $lead . "\n\n" .
                '识别到的关键实体：' . $entityText . "\n" .
                '当前优先需要补的知识：' . implode('、', $needs) . '。';
        }

        $lead = match ($intent) {
            'mechanism' => 'This is a mechanism-style question, so I first need to see which kinds of causal evidence are missing before deciding whether to start with graph relations or literature.',
            'literature' => 'This question explicitly asks for evidence and papers, so I will first check the local graph and then decide whether PubMed is needed.',
            'classification' => 'This question is about classification and lineage, so I will resolve the tree context first and then decide whether more relation evidence is needed.',
            'expression' => 'This question is mainly about expression context, so I will start with the expression layer and then decide whether more evidence is needed.',
            default => 'I will first see whether the local graph already contains direct relations, and then decide what knowledge is still missing.'
        };
        return $lead . "\n\n" .
            'Recognized entities: ' . $entityText . "\n" .
            'Current knowledge gaps: ' . implode(', ', $needs) . '.';
    }

    private function toolStartMessage(string $pluginName, array $analysis): string
    {
        $language = (string)($analysis['language'] ?? 'en');
        return match ($pluginName) {
            'Graph Plugin' => $language === 'zh'
                ? '我先从本地图谱里查结构化关系，看看哪些实体类型最值得拿来构建回答。'
                : 'I will start with the local graph to see which relation types are most useful for the answer.',
            'Literature Plugin' => $language === 'zh'
                ? '接下来我会补查本地图谱文献和 PubMed，看看还缺哪些机制或证据。'
                : 'Next I will add local literature and PubMed evidence to see which mechanisms still need support.',
            'Tree Plugin' => $language === 'zh'
                ? '我再补一下分类和谱系上下文，确认这些实体在树上的位置。'
                : 'I will also resolve the tree context so the entities can be placed in their lineage.',
            'Expression Plugin' => $language === 'zh'
                ? '我会补看表达数据，确认这些实体是否有值得纳入答案的表达背景。'
                : 'I will inspect the expression layer to see whether it adds useful biological context.',
            'Genome Plugin' => $language === 'zh'
                ? '我再检查一下基因组位点和浏览器入口，看看是否需要位点级背景。'
                : 'I will also inspect genomic loci and browser entry points in case locus-level context is useful.',
            default => $language === 'zh' ? '正在调用工具。' : 'Calling a tool.'
        };
    }

    private function synthesizingMessage(string $language, array $analysis, array $pluginResults): string
    {
        $used = implode(', ', array_keys($pluginResults));
        if ($language === 'zh') {
            return '现在我开始把前面的结构化关系、文献和补充上下文重新整理成一段完整回答。已使用的工具：' . $used . '。';
        }
        return 'I am now synthesizing the structured relations, literature, and supporting context into a coherent answer. Tools used: ' . $used . '.';
    }

    private function knowledgeNeeds(array $analysis): array
    {
        $needs = [];
        $intent = (string)($analysis['intent'] ?? 'relationship');
        if ($intent === 'mechanism') {
            $needs[] = ($analysis['language'] ?? 'en') === 'zh' ? '结构化关系' : 'structured relations';
            $needs[] = ($analysis['language'] ?? 'en') === 'zh' ? '机制文献' : 'mechanism literature';
        }
        if (($analysis['asks_for_papers'] ?? false)) {
            $needs[] = ($analysis['language'] ?? 'en') === 'zh' ? '文献支持' : 'literature evidence';
        }
        if (($analysis['asks_for_classification'] ?? false)) {
            $needs[] = ($analysis['language'] ?? 'en') === 'zh' ? '分类上下文' : 'classification context';
        }
        if (($analysis['asks_for_expression'] ?? false)) {
            $needs[] = ($analysis['language'] ?? 'en') === 'zh' ? '表达背景' : 'expression context';
        }
        if (($analysis['asks_for_genome'] ?? false)) {
            $needs[] = ($analysis['language'] ?? 'en') === 'zh' ? '基因组位点' : 'genomic loci';
        }
        if ($needs === []) {
            $needs[] = ($analysis['language'] ?? 'en') === 'zh' ? '结构化关系' : 'structured relations';
        }
        return array_values(array_unique($needs));
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

    private function aggregateCitations(array $pluginResults): array
    {
        $all = [];
        foreach ($pluginResults as $result) {
            foreach ((array)($result['citations'] ?? []) as $citation) {
                $all[] = $citation;
            }
        }
        return $this->dedupeCitations($all);
    }

    private function aggregateLimits(array $pluginResults, array $analysis): array
    {
        $limits = [];
        foreach ($pluginResults as $result) {
            foreach ((array)($result['errors'] ?? []) as $error) {
                $limits[] = (string)$error;
            }
        }
        if ($this->aggregateEvidence($pluginResults) === []) {
            $limits[] = ($analysis['language'] ?? 'en') === 'zh'
                ? '当前还没有拿到足够直接的结构化或外部证据。'
                : 'There was not enough direct structured or external evidence for a stronger answer.';
        }
        return array_values(array_unique($limits));
    }

    private function inferConfidence(array $pluginResults, array $evidence, array $citations): string
    {
        $okPlugins = 0;
        foreach ($pluginResults as $result) {
            if (in_array((string)($result['status'] ?? ''), ['ok', 'partial'], true)) {
                $okPlugins++;
            }
        }
        if ($okPlugins >= 2 && count($evidence) >= 4 && count($citations) >= 3) {
            return 'high';
        }
        if ($okPlugins >= 1 && (count($evidence) >= 2 || count($citations) >= 1)) {
            return 'medium';
        }
        return 'low';
    }

    private function fallbackAnswer(string $question, array $analysis, array $pluginResults, array $evidence, array $citations, array $limits): string
    {
        $language = (string)($analysis['language'] ?? 'en');
        $intent = (string)($analysis['intent'] ?? 'relationship');

        if ($language === 'zh') {
            $paragraphs = [];
            if ($intent === 'mechanism') {
                $paragraphs[] = '基于当前图谱和文献证据，**' . $this->firstEntityLabel($analysis) . '** 更可能是通过多条并行机制影响疾病或肿瘤进程，而不是通过单一事件起作用。';
            } else {
                $paragraphs[] = '基于当前拿到的结构化关系和文献证据，我先给出一版可追溯的总结。';
            }

            $graph = $pluginResults['Graph Plugin']['results']['rows'] ?? [];
            if (is_array($graph) && $graph !== []) {
                $top = [];
                foreach (array_slice($graph, 0, 6) as $row) {
                    $source = trim((string)($row['source_name'] ?? ''));
                    $relation = trim((string)($row['relation_type'] ?? 'related_to'));
                    $target = trim((string)($row['target_name'] ?? ''));
                    if ($source !== '' && $target !== '') {
                        $top[] = $source . ' ' . $relation . ' ' . $target;
                    }
                }
                if ($top !== []) {
                    $paragraphs[] = '从结构化关系看，比较值得纳入主线的线索包括：' . implode('；', $top) . '。';
                }
            }

            if ($citations !== []) {
                $paragraphs[] = '文献层面，我优先参考了这些记录：' . implode('；', array_map(
                    static fn(array $citation): string => trim((string)($citation['title'] ?? 'Untitled')) . (trim((string)($citation['pmid'] ?? '')) !== '' ? '（PMID: ' . trim((string)$citation['pmid']) . '）' : ''),
                    array_slice($citations, 0, 5)
                )) . '。';
            }

            if ($limits !== []) {
                $paragraphs[] = '需要说明的是：' . implode('；', $limits);
            }

            return implode("\n\n", $paragraphs);
        }

        $paragraphs = [];
        if ($intent === 'mechanism') {
            $paragraphs[] = 'Based on the current graph and literature evidence, **' . $this->firstEntityLabel($analysis) . '** is more likely to contribute through multiple parallel mechanisms rather than through a single isolated event.';
        } else {
            $paragraphs[] = 'Based on the current structured relations and literature evidence, here is a traceable summary.';
        }

        $graph = $pluginResults['Graph Plugin']['results']['rows'] ?? [];
        if (is_array($graph) && $graph !== []) {
            $top = [];
            foreach (array_slice($graph, 0, 6) as $row) {
                $source = trim((string)($row['source_name'] ?? ''));
                $relation = trim((string)($row['relation_type'] ?? 'related_to'));
                $target = trim((string)($row['target_name'] ?? ''));
                if ($source !== '' && $target !== '') {
                    $top[] = $source . ' ' . $relation . ' ' . $target;
                }
            }
            if ($top !== []) {
                $paragraphs[] = 'The most useful structured lines of evidence in this round were: ' . implode('; ', $top) . '.';
            }
        }

        if ($citations !== []) {
            $paragraphs[] = 'I prioritized these supporting records: ' . implode('; ', array_map(
                static fn(array $citation): string => trim((string)($citation['title'] ?? 'Untitled')) . (trim((string)($citation['pmid'] ?? '')) !== '' ? ' (PMID: ' . trim((string)$citation['pmid']) . ')' : ''),
                array_slice($citations, 0, 5)
            )) . '.';
        }

        if ($limits !== []) {
            $paragraphs[] = 'The main limits of this answer are: ' . implode('; ', $limits);
        }

        return implode("\n\n", $paragraphs);
    }

    private function toolPayloadForUi(array $result): array
    {
        $details = is_array($result['display_details'] ?? null) ? $result['display_details'] : [];
        return [
            'result_counts' => (array)($result['result_counts'] ?? []),
            'evidence_items' => (array)($details['evidence_items'] ?? $result['evidence_items'] ?? []),
            'citations' => (array)($details['citations'] ?? $result['citations'] ?? []),
            'preview_items' => (array)($details['preview_items'] ?? []),
            'raw_preview' => $details['raw_preview'] ?? ($result['results'] ?? null),
            'errors' => (array)($result['errors'] ?? []),
        ];
    }

    private function dedupeCitations(array $citations): array
    {
        $seen = [];
        $unique = [];
        foreach ($citations as $citation) {
            $key = trim((string)($citation['pmid'] ?? ''));
            if ($key === '') {
                $key = tekg_agent_lower(trim((string)($citation['title'] ?? '')));
            }
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $citation;
        }
        return $unique;
    }

    private function firstEntityLabel(array $analysis): string
    {
        $entities = (array)($analysis['normalized_entities'] ?? []);
        if ($entities !== []) {
            return (string)($entities[0]['label'] ?? 'The queried entity');
        }
        return ($analysis['language'] ?? 'en') === 'zh' ? '当前对象' : 'the queried entity';
    }

    private function inferProvider(string $model): string
    {
        return str_contains(tekg_agent_lower($model), 'qwen') ? 'qwen' : 'deepseek';
    }

    private function emit(?callable $emit, array $event): void
    {
        if ($emit !== null) {
            $emit($event);
        }
    }
}
