<?php
declare(strict_types=1);

final class TekgAcademicAgentService
{
    private TekgAgentEntityNormalizer $normalizer;
    private TekgAgentLlmClient $llm;
    private TekgAgentCitationResolver $citationResolver;
    /** @var array<string,TekgAgentPluginInterface> */
    private array $plugins;

    public function __construct(private readonly array $config)
    {
        $neo4j = new TekgAgentNeo4jClient($config);
        $this->normalizer = new TekgAgentEntityNormalizer();
        $this->llm = new TekgAgentLlmClient($config);
        $this->citationResolver = new TekgAgentCitationResolver();
        $this->plugins = [
            'Entity Resolver' => new TekgAgentEntityResolverPlugin(),
            'Graph Plugin' => new TekgAgentGraphPlugin($neo4j, $this->citationResolver),
            'Literature Plugin' => new TekgAgentLiteraturePlugin($neo4j, $config, $this->citationResolver),
            'Tree Plugin' => new TekgAgentTreePlugin(),
            'Expression Plugin' => new TekgAgentExpressionPlugin(),
            'Genome Plugin' => new TekgAgentGenomePlugin(),
            'Sequence Plugin' => new TekgAgentSequencePlugin(),
            'Citation Resolver' => new TekgAgentCitationResolverPlugin($this->citationResolver),
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

        if ($this->shouldRunCitationResolver($pluginResults)) {
            $citationPlugin = $this->plugins['Citation Resolver'] ?? null;
            if ($citationPlugin instanceof TekgAgentPluginInterface) {
                $this->emit($emit, [
                    'type' => 'tool_start',
                    'session_id' => $sessionId,
                    'plugin_name' => 'Citation Resolver',
                    'message' => $this->toolStartMessage('Citation Resolver'),
                ]);

                $citationResult = $citationPlugin->run([
                    'question' => $question,
                    'analysis' => $analysis,
                    'plugin_results' => $pluginResults,
                    'planning' => $planning,
                    'config' => $this->config,
                ]);

                $pluginResults['Citation Resolver'] = $citationResult;
                $pluginCalls[] = $citationResult;
                $detailId = 'tool-' . (++$detailCounter);
                $payloadForUi = $this->toolPayloadForUi($citationResult);

                $this->emit($emit, [
                    'type' => 'tool_progress',
                    'session_id' => $sessionId,
                    'plugin_name' => 'Citation Resolver',
                    'message' => (string)($citationResult['display_summary'] ?? $citationResult['query_summary'] ?? ''),
                ]);

                $this->emit($emit, [
                    'type' => 'tool_result',
                    'session_id' => $sessionId,
                    'plugin_name' => 'Citation Resolver',
                    'display_label' => (string)($citationResult['display_label'] ?? 'Citation Resolver'),
                    'summary' => (string)($citationResult['display_summary'] ?? $citationResult['query_summary'] ?? ''),
                    'message' => (string)(($citationResult['display_details']['result_message'] ?? '') ?: ''),
                    'detail_payload_id' => $detailId,
                    'payload' => $payloadForUi,
                ]);

                $reasoningTrace[] = [
                    'step' => 'querying_plugins',
                    'title' => 'Citation Resolver',
                    'status' => (string)($citationResult['status'] ?? 'ok'),
                    'details' => (string)($citationResult['display_summary'] ?? $citationResult['query_summary'] ?? ''),
                ];
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
            static fn(array $entity): string => (string)($entity['canonical_label'] ?? $entity['label'] ?? '') . ' (' . (string)($entity['type'] ?? '') . ')',
            (array)($analysis['normalized_entities'] ?? [])
        );
        $needs = $this->knowledgeNeeds($analysis);

        return [
            'question_type' => (string)($analysis['intent'] ?? 'relationship'),
            'key_entities' => $entities,
            'alias_chains' => (array)($analysis['alias_chains'] ?? []),
            'requested_target_types' => (array)($analysis['requested_target_types'] ?? []),
            'required_evidence' => $needs,
            'summary' => 'Question: ' . $question . '; intent=' . (string)($analysis['intent'] ?? 'relationship') . '; entities=' . ($entities === [] ? 'none recognized' : implode(', ', $entities)),
            'narrative' => $this->planningNarrative($analysis, $entities, $needs),
        ];
    }

    private function initialPluginQueue(array $analysis): array
    {
        $queue = ['Entity Resolver'];
        $intent = (string)($analysis['intent'] ?? 'relationship');

        if (($analysis['asks_for_sequence'] ?? false) || $intent === 'sequence') {
            $queue[] = 'Sequence Plugin';
        }

        if (in_array($intent, ['mechanism', 'relationship', 'comparison', 'literature'], true)) {
            $queue[] = 'Graph Plugin';
        }
        if (($analysis['asks_for_papers'] ?? false)
            || in_array($intent, ['mechanism', 'literature', 'comparison'], true)
            || (($analysis['needs_external_literature'] ?? false) && $intent !== 'sequence')) {
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
        if (!in_array('Graph Plugin', $queue, true) && ($analysis['normalized_entities'] ?? []) !== []) {
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
            if (($analysis['asks_for_sequence'] ?? false) && !in_array('Sequence Plugin', $queue, true)) {
                $append[] = 'Sequence Plugin';
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
        $aliasChains = is_array($analysis['alias_chains'] ?? null) ? $analysis['alias_chains'] : [];

        $lead = match ($intent) {
            'mechanism' => 'This is a mechanism-style question, so I first need to resolve the right TE aliases, then see which causal relation types and literature evidence are missing.',
            'literature' => 'This question explicitly asks for evidence and papers, so I will resolve stable aliases first and then check local graph citations before deciding whether PubMed is needed.',
            'classification' => 'This question is about classification and lineage, so I will resolve the entity aliases first and then place them in the tree context.',
            'expression' => 'This question is mainly about expression context, so I will resolve stable entity names first and then inspect the expression layer.',
            'sequence' => 'This question is about sequence or structure, so I will resolve the TE aliases first and then match them against the Repbase-backed sequence records.',
            default => 'I will first resolve the entity aliases, then check whether the local graph already contains enough direct relations.',
        };

        return $lead . "\n\n" .
            'Recognized entities: ' . $entityText . "\n" .
            'Alias chains prepared: ' . count($aliasChains) . "\n" .
            'Current knowledge gaps: ' . implode(', ', $needs) . '.';
    }

    private function toolStartMessage(string $pluginName): string
    {
        return match ($pluginName) {
            'Entity Resolver' => 'I will resolve canonical entities and alias chains first so the downstream tools can retry stable names when needed.',
            'Graph Plugin' => 'I will start with the local graph to see which relation types are most useful for the answer.',
            'Literature Plugin' => 'Next I will add local literature and PubMed evidence to see which mechanisms still need support.',
            'Tree Plugin' => 'I will also resolve the tree context so the entities can be placed in their lineage.',
            'Expression Plugin' => 'I will inspect the expression layer to see whether it adds useful biological context.',
            'Genome Plugin' => 'I will also inspect genomic loci and browser entry points in case locus-level context is useful.',
            'Sequence Plugin' => 'I will match the recognized TE names against the Repbase-backed sequence records to recover sequence, length, and structure hints.',
            'Citation Resolver' => 'I will normalize and format the citation records so the final answer uses stable evidence references.',
            default => 'Calling a tool.',
        };
    }

    private function synthesizingMessage(array $pluginResults): string
    {
        $used = implode(', ', array_keys($pluginResults));
        return 'I am now synthesizing the resolved aliases, structured relations, literature, and sequence context into a coherent answer. Tools used: ' . $used . '.';
    }

    private function knowledgeNeeds(array $analysis): array
    {
        $needs = ['entity normalization'];
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
        if (($analysis['asks_for_sequence'] ?? false) || $intent === 'sequence') {
            $needs[] = 'sequence and structure context';
        }
        if ($needs === ['entity normalization']) {
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
        if (isset($pluginResults['Citation Resolver']['citations']) && is_array($pluginResults['Citation Resolver']['citations'])) {
            return $this->citationResolver->normalizeMany($pluginResults['Citation Resolver']['citations']);
        }

        $all = [];
        foreach ($pluginResults as $result) {
            foreach ((array)($result['citations'] ?? []) as $citation) {
                $all[] = $citation;
            }
        }
        return $this->citationResolver->normalizeMany($all);
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
        if ($okPlugins >= 3 && count($evidence) >= 5 && count($citations) >= 3) {
            return 'high';
        }
        if ($okPlugins >= 2 && (count($evidence) >= 3 || count($citations) >= 1)) {
            return 'medium';
        }
        return 'low';
    }

    private function fallbackAnswer(array $analysis, array $pluginResults, array $citations, array $limits): string
    {
        $intent = (string)($analysis['intent'] ?? 'relationship');
        $paragraphs = [];
        $entity = $this->firstEntityLabel($analysis);

        if ($intent === 'sequence') {
            $paragraphs[] = 'I could not reach the model layer, so this fallback answer is based on the Repbase-backed sequence records that were matched for **' . $entity . '**.';
        } elseif ($intent === 'mechanism') {
            $paragraphs[] = 'Based on the current graph, literature, and sequence-aware evidence, **' . $entity . '** is more likely to contribute through multiple parallel mechanisms rather than through a single isolated event.';
        } else {
            $paragraphs[] = 'Based on the current structured relations, literature, and supporting sequence evidence, here is a traceable summary.';
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
                $paragraphs[] = 'The local graph most directly supports these links: ' . implode('; ', $top) . '.';
            }
        }

        $sequenceMatches = $pluginResults['Sequence Plugin']['results']['matched_records'] ?? [];
        if (is_array($sequenceMatches) && $sequenceMatches !== []) {
            $parts = [];
            foreach (array_slice($sequenceMatches, 0, 2) as $match) {
                $entry = is_array($match['entry'] ?? null) ? $match['entry'] : [];
                $name = trim((string)($entry['name'] ?? $match['repbase_name'] ?? ''));
                $headline = trim((string)($entry['sequence_summary']['headline'] ?? ''));
                if ($name !== '') {
                    $parts[] = trim($name . ($headline !== '' ? ' (' . $headline . ')' : ''));
                }
            }
            if ($parts !== []) {
                $paragraphs[] = 'The sequence layer matched Repbase-backed records for ' . implode(', ', $parts) . ', which adds consensus length and annotation context.';
            }
        }

        if ($citations !== []) {
            $formatted = [];
            foreach (array_slice($citations, 0, 5) as $citation) {
                $title = trim((string)($citation['title'] ?? ''));
                $pmid = trim((string)($citation['pmid'] ?? ''));
                $formatted[] = $title !== '' ? $title . ($pmid !== '' ? ' (PMID: ' . $pmid . ')' : '') : ('PMID: ' . $pmid);
            }
            if ($formatted !== []) {
                $paragraphs[] = 'Useful references include ' . implode('; ', $formatted) . '.';
            }
        }

        if ($limits !== []) {
            $paragraphs[] = 'Current limits: ' . implode(' ', array_slice($limits, 0, 3));
        }

        return implode("\n\n", $paragraphs);
    }

    private function firstEntityLabel(array $analysis): string
    {
        $entities = (array)($analysis['normalized_entities'] ?? []);
        if ($entities === []) {
            return 'the recognized TE';
        }
        $first = $entities[0];
        return (string)($first['canonical_label'] ?? $first['label'] ?? 'the recognized TE');
    }

    private function inferProvider(string $model): string
    {
        $value = strtolower(trim($model));
        if (str_contains($value, 'qwen')) {
            return 'qwen';
        }
        return 'deepseek';
    }

    private function emit(?callable $emit, array $event): void
    {
        if ($emit !== null) {
            $emit($event);
        }
    }

    private function shouldRunCitationResolver(array $pluginResults): bool
    {
        foreach ($pluginResults as $pluginName => $result) {
            if (in_array($pluginName, ['Entity Resolver', 'Citation Resolver'], true)) {
                continue;
            }
            if ((array)($result['citations'] ?? []) !== []) {
                return true;
            }
        }
        return false;
    }

    private function toolPayloadForUi(array $result): array
    {
        return [
            'summary' => (string)($result['display_details']['summary'] ?? $result['display_summary'] ?? ''),
            'preview_items' => array_values((array)($result['display_details']['preview_items'] ?? [])),
            'evidence_items' => array_values((array)($result['display_details']['evidence_items'] ?? [])),
            'citations' => array_values((array)($result['display_details']['citations'] ?? $result['citations'] ?? [])),
            'raw_preview' => $result['display_details']['raw_preview'] ?? null,
            'errors' => array_values((array)($result['errors'] ?? [])),
            'result_counts' => (array)($result['result_counts'] ?? []),
            'display_details' => (array)($result['display_details'] ?? []),
        ];
    }
}
