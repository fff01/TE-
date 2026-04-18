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

        $sessionMemory = tekg_agent_load_session_memory($sessionId);
        $analysis = $this->normalizer->analyze($question, $answerLanguage);
        $analysis['answer_language'] = $answerLanguage;
        $analysis['language'] = 'english';
        $analysis['session_memory'] = $sessionMemory;

        $planning = $this->buildPlan($question, $analysis, $sessionMemory);
        $pluginQueue = $this->initialPluginQueue($analysis, $planning);
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
                'message' => $this->toolStartMessage($pluginName, $planning),
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

            foreach ($this->maybeAppendPlugins($analysis, $planning, $pluginName, $result, $pluginQueue) as $additionalPlugin) {
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
                    'message' => $this->toolStartMessage('Citation Resolver', $planning),
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
        $limits = $this->aggregateLimits($pluginResults, $evidence);
        $confidence = $this->inferConfidence($pluginResults, $evidence, $citations);

        $synthesizingMessage = $this->synthesizingMessage($planning, $pluginResults, $evidence);
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
            : $this->fallbackAnswer($analysis, $planning, $pluginResults, $evidence, $citations, $limits);

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
            'analysis' => $analysis,
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

        $updatedMemory = $this->updateSessionMemory($sessionMemory, $analysis, $planning, $pluginResults, $citations, $evidence);
        tekg_agent_save_session_memory($sessionId, $updatedMemory);

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

    private function buildPlan(string $question, array $analysis, array $sessionMemory): array
    {
        $entities = array_map(
            static fn(array $entity): array => [
                'label' => (string)($entity['canonical_label'] ?? $entity['label'] ?? ''),
                'type' => (string)($entity['type'] ?? ''),
                'confidence' => (float)($entity['confidence'] ?? 0.0),
            ],
            (array)($analysis['normalized_entities'] ?? [])
        );

        $knowledgeGaps = $this->buildKnowledgeGaps($analysis);
        $toolPlan = $this->buildToolPlan($analysis, $knowledgeGaps);
        $subtasks = $this->buildSubtasks($analysis, $knowledgeGaps);

        return [
            'question_type' => (string)($analysis['intent'] ?? 'relationship'),
            'complexity' => (string)($analysis['complexity'] ?? 'simple_lookup'),
            'key_entities' => $entities,
            'alias_chains' => (array)($analysis['alias_chains'] ?? []),
            'requested_target_types' => (array)($analysis['requested_target_types'] ?? []),
            'required_evidence' => array_values(array_unique(array_map(static fn(array $gap): string => (string)$gap['gap_type'], $knowledgeGaps))),
            'knowledge_gaps' => $knowledgeGaps,
            'tool_plan' => $toolPlan,
            'subtasks' => $subtasks,
            'session_context' => [
                'recent_topic_entities' => array_values((array)($sessionMemory['topic_entities'] ?? [])),
                'last_intent' => (string)($sessionMemory['last_intent'] ?? ''),
            ],
            'summary' => 'Question: ' . $question . '; intent=' . (string)($analysis['intent'] ?? 'relationship') . '; complexity=' . (string)($analysis['complexity'] ?? 'simple_lookup'),
            'narrative' => $this->planningNarrative($analysis, $knowledgeGaps, $subtasks, $sessionMemory),
        ];
    }

    private function buildKnowledgeGaps(array $analysis): array
    {
        $intent = (string)($analysis['intent'] ?? 'relationship');
        $gaps = [
            [
                'gap_type' => 'entity normalization',
                'why_needed' => 'The system must resolve stable canonical entities and alias chains before any evidence lookup can be trusted.',
                'priority' => 100,
                'candidate_tools' => ['Entity Resolver'],
            ],
        ];

        if ($intent === 'mechanism') {
            $gaps[] = [
                'gap_type' => 'structured relations',
                'why_needed' => 'Mechanism questions first need local graph relations that can connect TE entities to functions, genes, mutations, proteins, RNAs, or diseases.',
                'priority' => 90,
                'candidate_tools' => ['Graph Plugin'],
            ];
            $gaps[] = [
                'gap_type' => 'mechanism literature',
                'why_needed' => 'Mechanism claims need literature evidence to confirm whether the graph patterns are supported by traceable publications.',
                'priority' => 80,
                'candidate_tools' => ['Literature Plugin'],
            ];
        }

        if (($analysis['asks_for_papers'] ?? false) || $intent === 'literature') {
            $gaps[] = [
                'gap_type' => 'literature evidence',
                'why_needed' => 'The user explicitly asked for papers or literature support.',
                'priority' => 85,
                'candidate_tools' => ['Literature Plugin'],
            ];
        }

        if (($analysis['asks_for_classification'] ?? false) || $intent === 'classification') {
            $gaps[] = [
                'gap_type' => 'classification context',
                'why_needed' => 'The answer needs lineage or taxonomy background.',
                'priority' => 70,
                'candidate_tools' => ['Tree Plugin'],
            ];
        }

        if (($analysis['asks_for_expression'] ?? false) || $intent === 'expression') {
            $gaps[] = [
                'gap_type' => 'expression context',
                'why_needed' => 'The answer needs expression-related context or top biological settings.',
                'priority' => 65,
                'candidate_tools' => ['Expression Plugin'],
            ];
        }

        if (($analysis['asks_for_genome'] ?? false) || $intent === 'genome') {
            $gaps[] = [
                'gap_type' => 'genomic loci',
                'why_needed' => 'The answer needs locus-level context or genome browser coordinates.',
                'priority' => 65,
                'candidate_tools' => ['Genome Plugin'],
            ];
        }

        if (($analysis['asks_for_sequence'] ?? false) || $intent === 'sequence') {
            $gaps[] = [
                'gap_type' => 'sequence and structure context',
                'why_needed' => 'The answer needs sequence-backed annotation, consensus length, or structure hints.',
                'priority' => 75,
                'candidate_tools' => ['Sequence Plugin'],
            ];
        }

        if (count($gaps) === 1) {
            $gaps[] = [
                'gap_type' => 'structured relations',
                'why_needed' => 'No specialized gap dominates this question, so the local graph is the best first evidence layer.',
                'priority' => 70,
                'candidate_tools' => ['Graph Plugin'],
            ];
        }

        usort($gaps, static fn(array $left, array $right): int => (int)$right['priority'] <=> (int)$left['priority']);
        return $gaps;
    }

    private function buildToolPlan(array $analysis, array $knowledgeGaps): array
    {
        $plan = [];
        $seen = [];
        $plan[] = [
            'plugin' => 'Entity Resolver',
            'reason' => 'Resolve canonical entities, alias chains, and broad alias fallback boundaries.',
        ];
        $seen['Entity Resolver'] = true;

        foreach ($knowledgeGaps as $gap) {
            foreach ((array)($gap['candidate_tools'] ?? []) as $tool) {
                if (isset($seen[$tool])) {
                    continue;
                }
                $plan[] = [
                    'plugin' => $tool,
                    'reason' => (string)($gap['why_needed'] ?? ''),
                ];
                $seen[$tool] = true;
            }
        }

        if (($analysis['needs_external_literature'] ?? false) && !isset($seen['Literature Plugin'])) {
            $plan[] = [
                'plugin' => 'Literature Plugin',
                'reason' => 'External literature may be needed if the graph does not yield enough direct support.',
            ];
        }

        return $plan;
    }

    private function buildSubtasks(array $analysis, array $knowledgeGaps): array
    {
        $subtasks = [];
        $intent = (string)($analysis['intent'] ?? 'relationship');
        $entityLabels = array_map(static fn(array $entity): string => (string)($entity['canonical_label'] ?? $entity['label'] ?? ''), (array)($analysis['normalized_entities'] ?? []));
        $entityText = $entityLabels !== [] ? implode(', ', array_filter($entityLabels)) : 'the recognized entities';

        $subtasks[] = 'Resolve the canonical identity and alias boundaries for ' . $entityText . '.';
        foreach (array_slice($knowledgeGaps, 0, 4) as $gap) {
            $subtasks[] = 'Collect evidence for ' . (string)$gap['gap_type'] . ' because ' . tekg_agent_lower((string)$gap['why_needed']);
        }
        if ($intent === 'mechanism') {
            $subtasks[] = 'Integrate the strongest relation and literature evidence into a causal mechanism chain without inventing unsupported steps.';
        } elseif ($intent === 'comparison') {
            $subtasks[] = 'Compare the evidence sides directly and keep unsupported claims separate from supported ones.';
        } else {
            $subtasks[] = 'Synthesize only the strongest supported claims into a concise academic answer.';
        }

        return array_values(array_unique(array_filter($subtasks)));
    }

    private function initialPluginQueue(array $analysis, array $planning): array
    {
        $queue = array_map(static fn(array $item): string => (string)$item['plugin'], (array)($planning['tool_plan'] ?? []));
        $intent = (string)($analysis['intent'] ?? 'relationship');
        if ($queue === []) {
            $queue = ['Entity Resolver', 'Graph Plugin'];
        }
        if ($intent === 'mechanism' && !in_array('Graph Plugin', $queue, true)) {
            $queue[] = 'Graph Plugin';
        }
        return array_values(array_unique($queue));
    }

    private function maybeAppendPlugins(array $analysis, array $planning, string $pluginName, array $result, array $queue): array
    {
        $append = [];
        $intent = (string)($analysis['intent'] ?? 'relationship');

        if ($pluginName === 'Graph Plugin') {
            $relationCount = (int)($result['result_counts']['relations'] ?? 0);
            if ($relationCount === 0 && !in_array('Literature Plugin', $queue, true)) {
                $append[] = 'Literature Plugin';
            }
            if ($relationCount < 3 && $intent === 'mechanism' && !in_array('Sequence Plugin', $queue, true) && ($analysis['asks_for_sequence'] ?? false)) {
                $append[] = 'Sequence Plugin';
            }
        }

        if ($pluginName === 'Literature Plugin') {
            $reviewedCount = (int)($result['result_counts']['reviewed'] ?? 0);
            if ($reviewedCount === 0 && ($analysis['asks_for_classification'] ?? false) && !in_array('Tree Plugin', $queue, true)) {
                $append[] = 'Tree Plugin';
            }
        }

        foreach ((array)($planning['tool_plan'] ?? []) as $plannedTool) {
            $plugin = (string)($plannedTool['plugin'] ?? '');
            if ($plugin !== '' && !in_array($plugin, $queue, true) && !in_array($plugin, $append, true)) {
                $append[] = $plugin;
            }
        }

        return array_values(array_unique($append));
    }

    private function planningNarrative(array $analysis, array $knowledgeGaps, array $subtasks, array $sessionMemory): string
    {
        $intent = (string)($analysis['intent'] ?? 'relationship');
        $complexity = (string)($analysis['complexity'] ?? 'simple_lookup');
        $entities = array_map(
            static fn(array $entity): string => (string)($entity['canonical_label'] ?? $entity['label'] ?? '') . ' (' . (string)($entity['type'] ?? '') . ', confidence ' . number_format((float)($entity['confidence'] ?? 0.0), 2) . ')',
            (array)($analysis['normalized_entities'] ?? [])
        );
        $recent = array_values((array)($sessionMemory['topic_entities'] ?? []));

        $lead = match ($intent) {
            'mechanism' => 'This is a mechanism-style question, so I need to determine which local relation types and literature sources can support a causal chain without overstating weak links.',
            'literature' => 'This question explicitly asks for literature support, so I will resolve stable aliases first and then determine whether the local graph citations are enough before extending to PubMed.',
            'classification' => 'This is a classification question, so the key task is to anchor the recognized entities in the TE or disease lineage before adding any extra supporting layers.',
            'expression' => 'This is an expression-focused question, so I will resolve the stable entity names first and then see whether the expression layer adds useful biological context.',
            'genome' => 'This is a locus-focused question, so I need to resolve the TE identity first and then check whether genomic coordinates and browser entry points exist.',
            'sequence' => 'This is a sequence or structure question, so I will resolve TE aliases first and then match them against the Repbase-backed sequence library.',
            default => 'I will start by resolving canonical entities and then decide which evidence layers are actually needed, instead of running every tool by default.',
        };

        $lines = [$lead];
        $lines[] = 'Recognized entities: ' . ($entities === [] ? 'none yet' : implode(', ', $entities)) . '.';
        $lines[] = 'Complexity level: ' . $complexity . '.';
        if ($recent !== []) {
            $lines[] = 'Session memory suggests the recent topic focus was: ' . implode(', ', $recent) . '.';
        }
        $lines[] = 'Current knowledge gaps: ' . implode('; ', array_map(
            static fn(array $gap): string => (string)$gap['gap_type'] . ' because ' . tekg_agent_lower((string)$gap['why_needed']),
            $knowledgeGaps
        )) . '.';
        $lines[] = 'Planned subtasks: ' . implode(' ', $subtasks);

        return implode("\n\n", $lines);
    }

    private function toolStartMessage(string $pluginName, array $planning): string
    {
        return match ($pluginName) {
            'Entity Resolver' => 'I will resolve canonical entities, strict aliases, and broad alias boundaries first so the downstream tools can avoid unstable name matching.',
            'Graph Plugin' => 'I will start with the local graph and check whether it already contains enough structured relations to support the current task.',
            'Literature Plugin' => 'Next I will add local paper evidence and PubMed support if the current structured relations are not strong enough on their own.',
            'Tree Plugin' => 'I will place the recognized entities in their lineage to recover classification context where needed.',
            'Expression Plugin' => 'I will inspect the expression layer to see whether it contributes useful supporting biological context.',
            'Genome Plugin' => 'I will check whether representative loci and browser entry points exist for the current TE entities.',
            'Sequence Plugin' => 'I will match the recognized TE aliases against the Repbase-backed sequence records to recover consensus length, annotation, and structure hints.',
            'Citation Resolver' => 'I will normalize and deduplicate the citation records so the final answer can use stable references.',
            default => 'Calling a tool.',
        };
    }

    private function synthesizingMessage(array $planning, array $pluginResults, array $evidence): string
    {
        $used = implode(', ', array_keys($pluginResults));
        $gapCount = count((array)($planning['knowledge_gaps'] ?? []));
        return 'I am now synthesizing the resolved entities, ' . $gapCount . ' identified knowledge gaps, and ' . count($evidence) . ' evidence items into a coherent answer. Tools used: ' . $used . '.';
    }

    private function aggregateEvidence(array $pluginResults): array
    {
        $all = [];
        foreach ($pluginResults as $pluginName => $result) {
            foreach ((array)($result['evidence_items'] ?? []) as $item) {
                $normalized = tekg_agent_normalize_evidence_item($item, $pluginName);
                if ($normalized !== null) {
                    $all[] = $normalized;
                }
            }
        }

        $seen = [];
        $unique = [];
        foreach ($all as $item) {
            $key = strtolower(trim((string)($item['claim'] ?? ''))) . '::' . strtolower(trim((string)($item['source_plugin'] ?? '')));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $item;
        }

        usort($unique, static function (array $left, array $right): int {
            $order = ['high' => 3, 'medium' => 2, 'low' => 1];
            return ($order[$right['support_strength']] ?? 0) <=> ($order[$left['support_strength']] ?? 0);
        });

        return $unique;
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
        $citations = $this->citationResolver->normalizeMany($all);
        usort($citations, static function (array $left, array $right): int {
            $leftPmid = trim((string)($left['pmid'] ?? ''));
            $rightPmid = trim((string)($right['pmid'] ?? ''));
            if ($leftPmid !== '' && $rightPmid !== '') {
                return strcmp($leftPmid, $rightPmid);
            }
            return strcasecmp((string)($left['title'] ?? ''), (string)($right['title'] ?? ''));
        });
        return $citations;
    }

    private function aggregateLimits(array $pluginResults, array $evidence): array
    {
        $limits = [];
        foreach ($pluginResults as $result) {
            foreach ((array)($result['errors'] ?? []) as $error) {
                $limits[] = (string)$error;
            }
        }
        if ($evidence === []) {
            $limits[] = 'There was not enough direct structured or external evidence for a stronger answer.';
        }
        $citationCount = count($this->aggregateCitations($pluginResults));
        if ($citationCount === 0) {
            $limits[] = 'No directly traceable citation could be attached to this answer in the current round.';
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

        $strongEvidence = count(array_filter($evidence, static fn(array $item): bool => (string)($item['support_strength'] ?? 'low') === 'high'));
        if ($okPlugins >= 3 && $strongEvidence >= 2 && count($citations) >= 3) {
            return 'high';
        }
        if ($okPlugins >= 2 && count($evidence) >= 3) {
            return 'medium';
        }
        return 'low';
    }

    private function fallbackAnswer(array $analysis, array $planning, array $pluginResults, array $evidence, array $citations, array $limits): string
    {
        $intent = (string)($analysis['intent'] ?? 'relationship');
        $entity = $this->firstEntityLabel($analysis);
        $paragraphs = [];

        if ($intent === 'mechanism') {
            $paragraphs[] = 'The model layer is currently unavailable, so this answer is a deterministic synthesis of the strongest graph, literature, and supporting domain evidence that was collected for **' . $entity . '**.';
        } elseif ($intent === 'sequence') {
            $paragraphs[] = 'The model layer is currently unavailable, so this fallback answer is based on the Repbase-backed sequence evidence that could be collected for **' . $entity . '**.';
        } else {
            $paragraphs[] = 'The model layer is currently unavailable, so this is a deterministic summary of the strongest evidence gathered for the current question.';
        }

        if ($evidence !== []) {
            $claims = [];
            foreach (array_slice($evidence, 0, 5) as $item) {
                $claims[] = trim((string)($item['claim'] ?? ''));
            }
            if ($claims !== []) {
                $paragraphs[] = 'The most directly supported findings are: ' . implode(' ', $claims);
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

        return implode("\n\n", array_filter($paragraphs));
    }

    private function updateSessionMemory(array $memory, array $analysis, array $planning, array $pluginResults, array $citations, array $evidence): array
    {
        $memory['topic_entities'] = array_values(array_unique(array_map(
            static fn(array $entity): string => (string)($entity['canonical_label'] ?? $entity['label'] ?? ''),
            (array)($analysis['normalized_entities'] ?? [])
        )));
        $memory['last_intent'] = (string)($analysis['intent'] ?? '');
        $memory['confirmed_claims'] = array_values(array_unique(array_map(
            static fn(array $item): string => (string)($item['claim'] ?? ''),
            array_slice($evidence, 0, 8)
        )));
        $memory['citations'] = array_values(array_slice(array_map(
            static fn(array $citation): string => (string)($citation['pmid'] ?? $citation['title'] ?? ''),
            $citations
        ), 0, 12));
        $memory['failed_aliases'] = array_values(array_unique(array_merge(
            (array)($memory['failed_aliases'] ?? []),
            $this->collectFailedBroadAliases($analysis, $pluginResults)
        )));
        $memory['tool_history'] = array_values(array_slice(array_map(
            static fn(array $item): string => (string)($item['plugin'] ?? ''),
            (array)($planning['tool_plan'] ?? [])
        ), -10));

        return $memory;
    }

    private function collectFailedBroadAliases(array $analysis, array $pluginResults): array
    {
        $failed = [];
        $relations = (int)($pluginResults['Graph Plugin']['result_counts']['relations'] ?? 0);
        if ($relations > 0) {
            return [];
        }

        foreach ((array)($analysis['alias_chains'] ?? []) as $chain) {
            if (!is_array($chain) || !(bool)($chain['used_broad_alias'] ?? false)) {
                continue;
            }
            $matched = trim((string)($chain['matched_alias'] ?? ''));
            if ($matched !== '') {
                $failed[] = $matched;
            }
        }
        return $failed;
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
        $evidenceItems = [];
        foreach ((array)($result['display_details']['evidence_items'] ?? $result['evidence_items'] ?? []) as $item) {
            $normalized = tekg_agent_normalize_evidence_item($item, (string)($result['plugin_name'] ?? 'Unknown'));
            if ($normalized !== null) {
                $evidenceItems[] = $normalized;
            }
        }

        return [
            'summary' => (string)($result['display_details']['summary'] ?? $result['display_summary'] ?? ''),
            'preview_items' => array_values((array)($result['display_details']['preview_items'] ?? [])),
            'evidence_items' => $evidenceItems,
            'citations' => array_values((array)($result['display_details']['citations'] ?? $result['citations'] ?? [])),
            'raw_preview' => $result['display_details']['raw_preview'] ?? null,
            'errors' => array_values((array)($result['errors'] ?? [])),
            'result_counts' => (array)($result['result_counts'] ?? []),
            'display_details' => (array)($result['display_details'] ?? []),
        ];
    }
}
