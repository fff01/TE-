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

        $answerLanguage = tekg_agent_detect_language($question, trim((string)($payload['language'] ?? 'english')));
        $model = trim((string)($payload['model'] ?? ($this->config['deepseek_model'] ?? 'deepseek-chat')));
        $sessionId = trim((string)($payload['session_id'] ?? ''));
        if ($sessionId === '') {
            $sessionId = tekg_agent_make_session_id();
        }

        $analysis = $this->normalizer->analyze($question, $answerLanguage);
        $analysis['answer_language'] = $answerLanguage;
        $analysis['language'] = 'english';

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
                'message' => $this->toolStartMessage($pluginName),
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
        $limits = $this->aggregateLimits($pluginResults);
        $confidence = $this->inferConfidence($pluginResults, $evidence, $citations);

        $synthesizingMessage = $this->synthesizingMessage($pluginResults);
        $this->emit($emit, [
            'type' => 'synthesizing',
            'session_id' => $sessionId,
            'message' => $synthesizingMessage,
        ]);

        try {
            $llm = $this->llm->complete($model, $question, $answerLanguage, $planning, $pluginCalls, $evidence, $citations, $confidence, $limits);
        } catch (Throwable $error) {
            $llm = [
                'ok' => false,
                'provider' => $this->inferProvider($model),
                'model' => $model,
                'content' => '',
                'error' => $error->getMessage(),
            ];
            $limits[] = 'The model service is currently unavailable, so the response fell back to a deterministic structured summary.';
        }

        $answer = ($llm['ok'] ?? false)
            ? (string)($llm['content'] ?? '')
            : $this->fallbackAnswer($analysis, $pluginResults, $citations, $limits);

        $reasoningTrace[] = [
            'step' => 'synthesizing',
            'title' => 'Synthesis',
            'status' => ($llm['ok'] ?? false) ? 'done' : 'fallback',
            'details' => $synthesizingMessage,
        ];

        $response = [
            'question' => $question,
            'mode' => trim((string)($payload['mode'] ?? 'academic')) ?: 'academic',
            'language' => $answerLanguage,
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
            'language' => $answerLanguage,
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
            'narrative' => $this->planningNarrative($analysis, $entities, $needs),
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
            $relationCount = (int)($result['result_counts']['relations'] ?? 0);
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

    private function planningNarrative(array $analysis, array $entities, array $needs): string
    {
        $intent = (string)($analysis['intent'] ?? 'relationship');
        $entityText = $entities === [] ? 'No stable named entities were recognized yet.' : implode(', ', $entities);

        $lead = match ($intent) {
            'mechanism' => 'This is a mechanism-style question, so I first need to see which kinds of causal evidence are missing before deciding whether to start with graph relations or literature.',
            'literature' => 'This question explicitly asks for evidence and papers, so I will first check the local graph and then decide whether PubMed is needed.',
            'classification' => 'This question is about classification and lineage, so I will resolve the tree context first and then decide whether more relation evidence is needed.',
            'expression' => 'This question is mainly about expression context, so I will start with the expression layer and then decide whether more evidence is needed.',
            default => 'I will first see whether the local graph already contains direct relations, and then decide what knowledge is still missing.',
        };

        return $lead . "\n\n" .
            'Recognized entities: ' . $entityText . "\n" .
            'Current knowledge gaps: ' . implode(', ', $needs) . '.';
    }

    private function toolStartMessage(string $pluginName): string
    {
        return match ($pluginName) {
            'Graph Plugin' => 'I will start with the local graph to see which relation types are most useful for the answer.',
            'Literature Plugin' => 'Next I will add local literature and PubMed evidence to see which mechanisms still need support.',
            'Tree Plugin' => 'I will also resolve the tree context so the entities can be placed in their lineage.',
            'Expression Plugin' => 'I will inspect the expression layer to see whether it adds useful biological context.',
            'Genome Plugin' => 'I will also inspect genomic loci and browser entry points in case locus-level context is useful.',
            default => 'Calling a tool.',
        };
    }

    private function synthesizingMessage(array $pluginResults): string
    {
        $used = implode(', ', array_keys($pluginResults));
        return 'I am now synthesizing the structured relations, literature, and supporting context into a coherent answer. Tools used: ' . $used . '.';
    }

    private function knowledgeNeeds(array $analysis): array
    {
        $needs = [];
        $intent = (string)($analysis['intent'] ?? 'relationship');
        if ($intent === 'mechanism') {
            $needs[] = 'structured relations';
            $needs[] = 'mechanism literature';
        }
        if (($analysis['asks_for_papers'] ?? false)) {
            $needs[] = 'literature evidence';
        }
        if (($analysis['asks_for_classification'] ?? false)) {
            $needs[] = 'classification context';
        }
        if (($analysis['asks_for_expression'] ?? false)) {
            $needs[] = 'expression context';
        }
        if (($analysis['asks_for_genome'] ?? false)) {
            $needs[] = 'genomic loci';
        }
        if ($needs === []) {
            $needs[] = 'structured relations';
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

    private function aggregateLimits(array $pluginResults): array
    {
        $limits = [];
        foreach ($pluginResults as $result) {
            foreach ((array)($result['errors'] ?? []) as $error) {
                $limits[] = (string)$error;
            }
        }
        if ($this->aggregateEvidence($pluginResults) === []) {
            $limits[] = 'There was not enough direct structured or external evidence for a stronger answer.';
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

    private function fallbackAnswer(array $analysis, array $pluginResults, array $citations, array $limits): string
    {
        $intent = (string)($analysis['intent'] ?? 'relationship');
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
        return 'the queried entity';
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
