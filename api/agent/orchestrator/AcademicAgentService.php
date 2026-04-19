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
            'Graph Analytics Plugin' => new TekgAgentGraphAnalyticsPlugin($neo4j),
            'Cypher Explorer Plugin' => new TekgAgentCypherExplorerPlugin($neo4j, $this->llm, $config),
            'Literature Plugin' => new TekgAgentLiteraturePlugin($neo4j, $config, $this->citationResolver),
            'Literature Reading Plugin' => new TekgAgentLiteratureReadingPlugin($this->llm, $config),
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
        $processLanguage = 'english';
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
        $routingPolicy = $this->routingPolicyFor($analysis);
        $pluginQueue = $this->initialPluginQueue($analysis, $planning, $routingPolicy);
        $pluginResults = [];
        $pluginCalls = [];
        $reasoningTrace = [];
        $detailCounter = 0;
        $eventSequence = 0;
        $collectionState = $this->initialCollectionState($analysis, $planning, $routingPolicy, $pluginQueue);
        $sufficiencyDecision = [
            'is_sufficient' => false,
            'reason' => 'No expert evidence has been collected yet.',
            'missing_dimensions' => array_values((array)($collectionState['active_gaps'] ?? [])),
            'recommended_next_experts' => array_values((array)($collectionState['remaining_candidates'] ?? [])),
        ];

        $this->emitThoughtFlow($emit, $sessionId, $model, $processLanguage, $analysis, $planning, $eventSequence);

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

            $this->emitEvent($emit, $eventSequence, [
                'type' => 'tool_selected',
                'session_id' => $sessionId,
                'plugin_name' => $pluginName,
                'message' => $this->narrateEvent(
                    $model,
                    $processLanguage,
                    [
                        'type' => 'tool_selected',
                        'plugin_name' => $pluginName,
                        'planning' => $planning,
                    ],
                    $this->toolSelectedMessage($pluginName, $planning)
                ),
            ]);
            $this->emitHeartbeat($emit, $eventSequence, $sessionId);
            $this->emitEvent($emit, $eventSequence, [
                'type' => 'tool_start',
                'session_id' => $sessionId,
                'plugin_name' => $pluginName,
                'message' => $this->narrateEvent(
                    $model,
                    $processLanguage,
                    [
                        'type' => 'tool_start',
                        'plugin_name' => $pluginName,
                        'planning' => $planning,
                    ],
                    $this->toolStartMessage($pluginName, $planning)
                ),
            ]);

            $result = $plugin->run([
                'question' => $question,
                'analysis' => $analysis,
                'plugin_results' => $pluginResults,
                'planning' => $planning,
                'config' => $this->config,
            ]);
            $result = $this->augmentPluginResult($pluginName, $result, $analysis, $planning);

            $pluginResults[$pluginName] = $result;
            $pluginCalls[] = $result;
            $collectionState = $this->updateCollectionState($collectionState, $pluginName, $result);

            $detailId = 'tool-' . (++$detailCounter);
            $payloadForUi = $this->toolPayloadForUi($result);

            $this->emitEvent($emit, $eventSequence, [
                'type' => 'tool_progress',
                'session_id' => $sessionId,
                'plugin_name' => $pluginName,
                'message' => $this->narrateEvent(
                    $model,
                    $processLanguage,
                    [
                        'type' => 'tool_progress',
                        'plugin_name' => $pluginName,
                        'result' => $result,
                    ],
                    (string)($result['display_summary'] ?? $result['query_summary'] ?? '')
                ),
            ]);

            $this->emitEvent($emit, $eventSequence, [
                'type' => 'tool_result',
                'session_id' => $sessionId,
                'plugin_name' => $pluginName,
                'display_label' => (string)($result['display_label'] ?? $pluginName),
                'summary' => (string)($result['display_summary'] ?? $result['query_summary'] ?? ''),
                'message' => $this->narrateEvent(
                    $model,
                    $processLanguage,
                    [
                        'type' => 'tool_result',
                        'plugin_name' => $pluginName,
                        'result' => $result,
                    ],
                    (string)(($result['display_details']['result_message'] ?? '') ?: ($result['display_summary'] ?? $result['query_summary'] ?? ''))
                ),
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

            $sufficiencyDecision = $this->evaluateSufficiency(
                $model,
                $question,
                $analysis,
                $planning,
                $pluginResults,
                $collectionState,
                $routingPolicy
            );
            $collectionState['sufficiency_decision'] = $sufficiencyDecision;
            foreach (array_values((array)($sufficiencyDecision['recommended_next_experts'] ?? [])) as $recommendedPlugin) {
                if ($recommendedPlugin !== ''
                    && !in_array($recommendedPlugin, $pluginQueue, true)
                    && !in_array($recommendedPlugin, array_keys($pluginResults), true)
                ) {
                    $pluginQueue[] = $recommendedPlugin;
                }
            }

            $reflection = $this->reflectionMessage($pluginName, $result, $pluginQueue, $index);
            if ($reflection !== '') {
                $this->emitEvent($emit, $eventSequence, [
                    'type' => 'reflection',
                    'session_id' => $sessionId,
                    'plugin_name' => $pluginName,
                    'node' => 'Evidence Collection Node',
                    'source' => 'Evidence Collection Node',
                    'inputs_used' => ['collection_state', 'compressed_result', 'routing_policy'],
                    'outputs_changed' => ['sufficiency_decision', 'remaining_candidates', 'closed_gaps'],
                    'message' => $this->narrateEvent(
                        $model,
                        $processLanguage,
                        [
                            'type' => 'reflection',
                            'plugin_name' => $pluginName,
                            'result' => $result,
                            'sufficiency_decision' => $sufficiencyDecision,
                            'remaining_tools' => array_slice($pluginQueue, $index + 1),
                        ],
                        $reflection . ' Sufficiency: ' . (string)($sufficiencyDecision['reason'] ?? '')
                    ),
                    'payload' => [
                        'collection_state' => $collectionState,
                        'sufficiency_decision' => $sufficiencyDecision,
                    ],
                ]);
            }

            if (($sufficiencyDecision['is_sufficient'] ?? false) === true
                && ((bool)($routingPolicy['stop_conditions']['stop_on_sufficient'] ?? true))
            ) {
                break;
            }
        }

        if ($this->shouldRunCitationResolver($pluginResults)) {
            $citationPlugin = $this->plugins['Citation Resolver'] ?? null;
            if ($citationPlugin instanceof TekgAgentPluginInterface) {
                $this->emitEvent($emit, $eventSequence, [
                    'type' => 'tool_selected',
                    'session_id' => $sessionId,
                    'plugin_name' => 'Citation Resolver',
                    'message' => $this->narrateEvent(
                        $model,
                        $processLanguage,
                        [
                            'type' => 'tool_selected',
                            'plugin_name' => 'Citation Resolver',
                            'planning' => $planning,
                        ],
                        $this->toolSelectedMessage('Citation Resolver', $planning)
                    ),
                ]);
                $this->emitHeartbeat($emit, $eventSequence, $sessionId);
                $this->emitEvent($emit, $eventSequence, [
                    'type' => 'tool_start',
                    'session_id' => $sessionId,
                    'plugin_name' => 'Citation Resolver',
                    'message' => $this->narrateEvent(
                        $model,
                        $processLanguage,
                        [
                            'type' => 'tool_start',
                            'plugin_name' => 'Citation Resolver',
                            'planning' => $planning,
                        ],
                        $this->toolStartMessage('Citation Resolver', $planning)
                    ),
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

                $this->emitEvent($emit, $eventSequence, [
                    'type' => 'tool_progress',
                    'session_id' => $sessionId,
                    'plugin_name' => 'Citation Resolver',
                    'message' => $this->narrateEvent(
                        $model,
                        $processLanguage,
                        [
                            'type' => 'tool_progress',
                            'plugin_name' => 'Citation Resolver',
                            'result' => $citationResult,
                        ],
                        (string)($citationResult['display_summary'] ?? $citationResult['query_summary'] ?? '')
                    ),
                ]);

                $this->emitEvent($emit, $eventSequence, [
                    'type' => 'tool_result',
                    'session_id' => $sessionId,
                    'plugin_name' => 'Citation Resolver',
                    'display_label' => (string)($citationResult['display_label'] ?? 'Citation Resolver'),
                    'summary' => (string)($citationResult['display_summary'] ?? $citationResult['query_summary'] ?? ''),
                    'message' => $this->narrateEvent(
                        $model,
                        $processLanguage,
                        [
                            'type' => 'tool_result',
                            'plugin_name' => 'Citation Resolver',
                            'result' => $citationResult,
                        ],
                        (string)(($citationResult['display_details']['result_message'] ?? '') ?: ($citationResult['display_summary'] ?? $citationResult['query_summary'] ?? ''))
                    ),
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
        $synthesizedEvidence = $this->buildSynthesizedEvidence($pluginResults, $evidence);
        $answerStructure = $this->generateAnswerStructure(
            $model,
            $question,
            $analysis,
            $planning,
            $synthesizedEvidence,
            $citations,
            $sufficiencyDecision
        );

        $synthesizingMessage = $this->synthesizingMessage($planning, $pluginResults, $evidence);
        $this->emitEvent($emit, $eventSequence, [
            'type' => 'synthesizing',
            'session_id' => $sessionId,
            'node' => 'Evidence Synthesis Node',
            'source' => 'Evidence Synthesis Node',
            'inputs_used' => ['compressed_results', 'citation_bundle'],
            'outputs_changed' => ['supported_claims', 'conflicting_claims', 'missing_evidence', 'claim_clusters', 'answer_structure'],
            'message' => $this->narrateEvent(
                $model,
                $processLanguage,
                [
                    'type' => 'synthesizing',
                    'planning' => $planning,
                    'plugin_results' => $pluginResults,
                    'evidence_count' => count($evidence),
                    'answer_structure' => $answerStructure,
                ],
                $synthesizingMessage
            ),
            'payload' => [
                'synthesized_evidence' => $synthesizedEvidence,
                'answer_structure' => $answerStructure,
            ],
        ]);
        $this->emitHeartbeat($emit, $eventSequence, $sessionId);

        try {
            $llm = $this->llm->writeStructuredAnswer(
                $model,
                $answerLanguage,
                $question,
                $analysis,
                $answerStructure,
                (array)($synthesizedEvidence['supported_claims'] ?? []),
                (array)($synthesizedEvidence['conflicting_claims'] ?? []),
                (array)($synthesizedEvidence['missing_evidence'] ?? []),
                $citations,
                $confidence,
                $limits
            );
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
            'collection_state' => $collectionState,
            'sufficiency_decision' => $sufficiencyDecision,
            'answer_structure' => $answerStructure,
            'synthesized_evidence' => $synthesizedEvidence,
            'node_contracts' => tekg_agent_node_contracts(),
            'node_payloads' => tekg_agent_json_safe($this->buildNodePayloads(
                $question,
                $analysis,
                $planning,
                $pluginResults,
                $evidence,
                $citations,
                $collectionState,
                $sufficiencyDecision,
                $answerStructure,
                $synthesizedEvidence
            )),
        ];

        $updatedMemory = $this->updateSessionMemory($sessionMemory, $analysis, $planning, $pluginResults, $citations, $evidence, $collectionState, $synthesizedEvidence);
        tekg_agent_save_session_memory($sessionId, $updatedMemory);

        $this->emitEvent($emit, $eventSequence, [
            'type' => 'answer',
            'session_id' => $sessionId,
            'language' => $answerLanguage,
            'message' => $answer,
        ]);
        $this->emitEvent($emit, $eventSequence, [
            'type' => 'done',
            'session_id' => $sessionId,
            'payload' => [
                'confidence' => $confidence,
                'used_plugins' => $response['used_plugins'],
            ],
        ]);

        return $response;
    }

    private function emitThoughtFlow(?callable $emit, string $sessionId, string $model, string $processLanguage, array $analysis, array $planning, int &$eventSequence): void
    {
        $entities = array_values(array_filter(array_map(function (array $entity): string {
            $label = trim((string)($entity['canonical_label'] ?? $entity['label'] ?? ''));
            $type = trim((string)($entity['entity_type'] ?? ''));
            $matchedAlias = trim((string)($entity['matched_alias'] ?? ''));
            if ($label === '') {
                return '';
            }
            $aliasPart = $matchedAlias !== '' ? ' via ' . $matchedAlias : ' directly';
            return $label . ($type !== '' ? ' (' . $type . ')' : '') . $aliasPart;
        }, (array)($analysis['normalized_entities'] ?? []))));

        $analysisLines = [
            $this->narrateEvent(
                $model,
                $processLanguage,
                [
                    'type' => 'analysis',
                    'focus' => 'entities',
                    'entities' => $analysis['normalized_entities'] ?? [],
                ],
                'Recognized entities: ' . ($entities === [] ? 'none yet.' : implode(', ', $entities) . '.')
            ),
            $this->narrateEvent(
                $model,
                $processLanguage,
                [
                    'type' => 'analysis',
                    'focus' => 'intent',
                    'intent' => $analysis['intent'] ?? '',
                    'complexity' => $analysis['complexity'] ?? '',
                ],
                'Question type: ' . (string)($analysis['intent'] ?? 'relationship') . '. Complexity: ' . (string)($analysis['complexity'] ?? 'simple_lookup') . '.'
            ),
        ];

        foreach ($analysisLines as $line) {
            if ($line === '') {
                continue;
            }
            $this->emitEvent($emit, $eventSequence, [
                'type' => 'analysis',
                'session_id' => $sessionId,
                'message' => $line,
                'payload' => [
                    'intent' => $analysis['intent'] ?? '',
                    'complexity' => $analysis['complexity'] ?? '',
                    'normalized_entities' => $analysis['normalized_entities'] ?? [],
                ],
            ]);
        }

        foreach ((array)($planning['knowledge_gaps'] ?? []) as $gap) {
            $fallback = 'Current knowledge gap: ' . (string)($gap['gap_type'] ?? 'unknown') . ' because ' . tekg_agent_lower((string)($gap['why_needed'] ?? 'it is still needed')) . '.';
            $this->emitEvent($emit, $eventSequence, [
                'type' => 'planning_step',
                'session_id' => $sessionId,
                'message' => $this->narrateEvent(
                    $model,
                    $processLanguage,
                    ['type' => 'planning_step', 'focus' => 'knowledge_gap', 'gap' => $gap],
                    $fallback
                ),
                'payload' => $gap,
            ]);
        }

        foreach ((array)($planning['subtasks'] ?? []) as $subtask) {
            $this->emitEvent($emit, $eventSequence, [
                'type' => 'planning_step',
                'session_id' => $sessionId,
                'message' => $this->narrateEvent(
                    $model,
                    $processLanguage,
                    ['type' => 'planning_step', 'focus' => 'subtask', 'subtask' => $subtask],
                    (string)$subtask
                ),
                'payload' => ['subtask' => $subtask],
            ]);
        }
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

        if (($analysis['asks_for_graph_analytics'] ?? false) || $intent === 'graph_analytics') {
            $gaps[] = [
                'gap_type' => 'graph analytics',
                'why_needed' => 'This question asks for global graph statistics, ranking, structure, or topology rather than a single local entity neighborhood.',
                'priority' => 92,
                'candidate_tools' => ['Graph Analytics Plugin'],
            ];
        }

        if (($analysis['asks_for_cypher_explorer'] ?? false) || ($analysis['asks_for_graph_structure'] ?? false)) {
            $gaps[] = [
                'gap_type' => 'graph exploration',
                'why_needed' => 'This question may require exploratory read-only Cypher beyond the fixed entity-neighborhood templates.',
                'priority' => 76,
                'candidate_tools' => ['Cypher Explorer Plugin'],
            ];
        }

        if (in_array($intent, ['literature', 'mechanism', 'comparison'], true) || ($analysis['needs_external_literature'] ?? false)) {
            $gaps[] = [
                'gap_type' => 'literature synthesis',
                'why_needed' => 'Retrieved citations still need to be grouped into supported claims, conflicts, and evidence gaps.',
                'priority' => 72,
                'candidate_tools' => ['Literature Reading Plugin'],
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
        $preferredOrder = [
            'Entity Resolver' => 10,
            'Graph Analytics Plugin' => 20,
            'Graph Plugin' => 30,
            'Cypher Explorer Plugin' => 40,
            'Literature Plugin' => 50,
            'Literature Reading Plugin' => 60,
            'Tree Plugin' => 70,
            'Expression Plugin' => 80,
            'Genome Plugin' => 90,
            'Sequence Plugin' => 100,
            'Citation Resolver' => 110,
        ];
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

        usort($plan, static function (array $left, array $right) use ($preferredOrder): int {
            $leftOrder = $preferredOrder[(string)($left['plugin'] ?? '')] ?? 999;
            $rightOrder = $preferredOrder[(string)($right['plugin'] ?? '')] ?? 999;
            return $leftOrder <=> $rightOrder;
        });

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

    private function initialCollectionState(array $analysis, array $planning, array $routingPolicy, array $pluginQueue): array
    {
        return [
            'executed_experts' => [],
            'remaining_candidates' => array_values(array_filter($pluginQueue, static fn(string $plugin): bool => $plugin !== 'Entity Resolver')),
            'closed_gaps' => [],
            'active_gaps' => array_values(array_map(
                static fn(array $gap): string => (string)($gap['gap_type'] ?? ''),
                (array)($planning['knowledge_gaps'] ?? [])
            )),
            'evidence_count' => 0,
            'citation_count' => 0,
            'question_type' => (string)($analysis['intent'] ?? 'relationship'),
            'routing_policy' => $routingPolicy,
        ];
    }

    private function routingPolicyFor(array $analysis): array
    {
        $policy = tekg_agent_routing_policy();
        $questionTypes = is_array($policy['question_types'] ?? null) ? $policy['question_types'] : [];
        $intent = (string)($analysis['intent'] ?? ($policy['default_question_type'] ?? 'relationship'));
        $selected = is_array($questionTypes[$intent] ?? null) ? $questionTypes[$intent] : (is_array($questionTypes['relationship'] ?? null) ? $questionTypes['relationship'] : []);
        $selected['question_type'] = $intent;
        return $selected;
    }

    private function initialPluginQueue(array $analysis, array $planning, array $routingPolicy): array
    {
        $queue = array_values(array_filter(array_map('strval', (array)($routingPolicy['candidate_experts'] ?? []))));
        if ($queue === []) {
            $queue = array_map(static fn(array $item): string => (string)$item['plugin'], (array)($planning['tool_plan'] ?? []));
        }
        $intent = (string)($analysis['intent'] ?? 'relationship');
        if ($queue === []) {
            $queue = $intent === 'graph_analytics'
                ? ['Entity Resolver', 'Graph Analytics Plugin']
                : ['Entity Resolver', 'Graph Plugin'];
        }
        if ($intent === 'mechanism' && !in_array('Graph Plugin', $queue, true)) {
            $queue[] = 'Graph Plugin';
        }
        if ($intent === 'graph_analytics' && !in_array('Graph Analytics Plugin', $queue, true)) {
            $queue[] = 'Graph Analytics Plugin';
        }
        return array_values(array_unique($queue));
    }

    private function augmentPluginResult(string $pluginName, array $result, array $analysis, array $planning): array
    {
        $rawResult = tekg_agent_json_safe((array)($result['results'] ?? []));
        $result['raw_result'] = $rawResult;
        $result['compressed_result'] = $this->compressPluginResult($pluginName, $result, $analysis, $planning);
        return $result;
    }

    private function compressPluginResult(string $pluginName, array $result, array $analysis, array $planning): array
    {
        $rawResult = tekg_agent_json_safe((array)($result['results'] ?? []));
        $evidenceItems = [];
        foreach ((array)($result['evidence_items'] ?? []) as $item) {
            $normalized = tekg_agent_normalize_evidence_item($item, $pluginName);
            if ($normalized !== null) {
                $evidenceItems[] = $normalized;
            }
        }

        $keyFindings = [];
        foreach (array_slice($evidenceItems, 0, 5) as $item) {
            $claim = trim((string)($item['claim'] ?? ''));
            if ($claim !== '') {
                $keyFindings[] = $claim;
            }
        }
        if ($keyFindings === []) {
            foreach (array_slice((array)($result['display_details']['preview_items'] ?? []), 0, 5) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $title = trim((string)($item['title'] ?? ''));
                if ($title !== '') {
                    $keyFindings[] = $title;
                }
            }
        }
        if ($keyFindings === []) {
            $summary = trim((string)($result['display_summary'] ?? $result['query_summary'] ?? ''));
            if ($summary !== '') {
                $keyFindings[] = $summary;
            }
        }

        $limitations = array_values(array_filter(array_map(
            static fn($value): string => trim((string)$value),
            (array)($result['errors'] ?? [])
        )));
        if (in_array((string)($result['status'] ?? ''), ['empty', 'error'], true)) {
            $limitations[] = trim((string)($result['display_summary'] ?? $result['query_summary'] ?? ''));
        }

        $previewItems = array_values(array_slice((array)($result['display_details']['preview_items'] ?? []), 0, 8));
        $citationPreview = array_values(array_slice((array)($result['citations'] ?? []), 0, 12));
        $evidencePreview = array_values(array_map(
            static fn(array $item): array => [
                'claim' => (string)($item['claim'] ?? ''),
                'title' => (string)($item['title'] ?? ''),
                'meta' => (string)($item['meta'] ?? ''),
                'support_strength' => (string)($item['support_strength'] ?? 'medium'),
            ],
            array_slice($evidenceItems, 0, 8)
        ));

        $carryForward = [
            'plugin_name' => $pluginName,
            'status' => (string)($result['status'] ?? 'unknown'),
            'query_summary' => (string)($result['query_summary'] ?? ''),
            'display_summary' => (string)($result['display_summary'] ?? ''),
            'result_counts' => (array)($result['result_counts'] ?? []),
            'preview_items' => $previewItems,
            'evidence_preview' => $evidencePreview,
            'citations' => $citationPreview,
        ];

        if ($pluginName === 'Cypher Explorer Plugin') {
            $cypherResult = (array)($rawResult['cypher_result'] ?? []);
            $carryForward['query_purpose'] = (string)($cypherResult['query_intent'] ?? 'graph_exploration');
            $carryForward['result_shape'] = [
                'row_count' => (int)($cypherResult['result_counts']['rows'] ?? 0),
                'columns' => (array)($cypherResult['column_schema'] ?? []),
            ];
            $carryForward['top_rows'] = array_slice((array)($cypherResult['rows'] ?? []), 0, 10);
            $carryForward['why_it_matters'] = $keyFindings[0] ?? trim((string)($result['display_summary'] ?? ''));
        } else {
            $carryForward['raw_result_excerpt'] = $this->rawResultExcerpt($rawResult);
        }

        return tekg_agent_json_safe([
            'key_findings' => array_values(array_unique(array_filter($keyFindings))),
            'coverage' => [
                'question_type' => (string)($analysis['intent'] ?? 'relationship'),
                'status' => (string)($result['status'] ?? 'unknown'),
                'result_counts' => (array)($result['result_counts'] ?? []),
                'required_evidence' => (array)($planning['required_evidence'] ?? []),
                'latency_ms' => (int)($result['latency_ms'] ?? 0),
            ],
            'limitations' => array_values(array_unique(array_filter($limitations))),
            'candidate_claims' => array_values(array_unique(array_filter(array_map(
                static fn(array $item): string => trim((string)($item['claim'] ?? '')),
                array_slice($evidenceItems, 0, 10)
            )))),
            'carry_forward_fields' => $carryForward,
        ]);
    }

    private function rawResultExcerpt(array $rawResult): array
    {
        $excerpt = [];
        foreach ($rawResult as $key => $value) {
            if (is_array($value)) {
                $excerpt[$key] = array_slice($value, 0, 10);
                continue;
            }
            $excerpt[$key] = $value;
        }
        return tekg_agent_json_safe($excerpt);
    }

    private function updateCollectionState(array $collectionState, string $pluginName, array $result): array
    {
        $collectionState['executed_experts'] = array_values(array_unique(array_merge(
            (array)($collectionState['executed_experts'] ?? []),
            [$pluginName]
        )));
        $collectionState['remaining_candidates'] = array_values(array_filter(
            (array)($collectionState['remaining_candidates'] ?? []),
            static fn(string $candidate): bool => $candidate !== $pluginName
        ));
        $collectionState['evidence_count'] = (int)($collectionState['evidence_count'] ?? 0) + count((array)($result['evidence_items'] ?? []));
        $collectionState['citation_count'] = (int)($collectionState['citation_count'] ?? 0) + count((array)($result['citations'] ?? []));
        if (in_array((string)($result['status'] ?? ''), ['ok', 'partial'], true)) {
            $collectionState['closed_gaps'] = array_values(array_unique(array_merge(
                (array)($collectionState['closed_gaps'] ?? []),
                [(string)$pluginName]
            )));
        }
        return $collectionState;
    }

    private function evaluateSufficiency(
        string $model,
        string $question,
        array $analysis,
        array $planning,
        array $pluginResults,
        array $collectionState,
        array $routingPolicy
    ): array {
        $hardGate = $this->evaluateMinimumEvidenceGate($pluginResults, $routingPolicy);
        if (!$hardGate['passed']) {
            return [
                'is_sufficient' => false,
                'reason' => $hardGate['reason'],
                'missing_dimensions' => $hardGate['missing_dimensions'],
                'recommended_next_experts' => $this->recommendedNextExperts($routingPolicy, $pluginResults, $hardGate['missing_dimensions']),
            ];
        }

        $payload = [
            'question' => $question,
            'analysis' => $analysis,
            'planning' => $planning,
            'collection_state' => $collectionState,
            'plugin_results' => $this->compressedPluginResults($pluginResults),
            'minimum_evidence_gate' => $routingPolicy['minimum_evidence_gate'] ?? [],
        ];
        $generated = $this->llm->assessSufficiency($model, $payload);
        if (is_array($generated)) {
            return [
                'is_sufficient' => (bool)($generated['is_sufficient'] ?? false),
                'reason' => trim((string)($generated['reason'] ?? 'The sufficiency assessor returned no reason.')),
                'missing_dimensions' => array_values(array_map('strval', (array)($generated['missing_dimensions'] ?? []))),
                'recommended_next_experts' => array_values(array_map('strval', (array)($generated['recommended_next_experts'] ?? []))),
            ];
        }

        return [
            'is_sufficient' => true,
            'reason' => 'The minimum evidence gate passed and no further model-driven expansion was available.',
            'missing_dimensions' => [],
            'recommended_next_experts' => [],
        ];
    }

    private function evaluateMinimumEvidenceGate(array $pluginResults, array $routingPolicy): array
    {
        $gate = (array)($routingPolicy['minimum_evidence_gate'] ?? []);
        $missing = [];

        foreach ((array)($gate['require_all_plugins'] ?? []) as $pluginName) {
            if (!isset($pluginResults[$pluginName])) {
                $missing[] = 'required plugin ' . $pluginName . ' has not run';
                continue;
            }
            if (!in_array((string)($pluginResults[$pluginName]['status'] ?? ''), ['ok', 'partial'], true)) {
                $allowExplicitEmpty = in_array($pluginName, (array)($gate['allow_explicit_empty_from'] ?? []), true);
                if (!$allowExplicitEmpty || (string)($pluginResults[$pluginName]['status'] ?? '') !== 'empty') {
                    $missing[] = 'required plugin ' . $pluginName . ' did not return usable results';
                }
            }
        }

        $requireAny = array_values((array)($gate['require_any_plugins'] ?? []));
        if ($requireAny !== []) {
            $matched = false;
            foreach ($requireAny as $pluginName) {
                if (!isset($pluginResults[$pluginName])) {
                    continue;
                }
                $status = (string)($pluginResults[$pluginName]['status'] ?? '');
                if (in_array($status, ['ok', 'partial'], true)) {
                    $matched = true;
                    break;
                }
                if (in_array($pluginName, (array)($gate['allow_explicit_empty_from'] ?? []), true) && $status === 'empty') {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $missing[] = 'none of the preferred experts produced a usable result';
            }
        }

        $evidenceCount = 0;
        $citationCount = 0;
        foreach ($pluginResults as $result) {
            $evidenceCount += count((array)($result['evidence_items'] ?? []));
            $citationCount += count((array)($result['citations'] ?? []));
        }
        if ((int)($gate['min_evidence_items'] ?? 0) > $evidenceCount) {
            $missing[] = 'insufficient evidence items';
        }
        if ((int)($gate['min_citations'] ?? 0) > $citationCount) {
            $missing[] = 'insufficient traceable citations';
        }
        if ((bool)($gate['require_sortable_statistics'] ?? false)) {
            $hasSortable = false;
            foreach (['Graph Analytics Plugin', 'Cypher Explorer Plugin'] as $pluginName) {
                $rows = (array)($pluginResults[$pluginName]['results']['analytics_result']['top_k'] ?? $pluginResults[$pluginName]['results']['cypher_result']['rows'] ?? []);
                if ($rows !== []) {
                    $hasSortable = true;
                    break;
                }
            }
            if (!$hasSortable) {
                $missing[] = 'no sortable graph statistics were collected';
            }
        }

        return [
            'passed' => $missing === [],
            'reason' => $missing === [] ? 'The minimum evidence gate has been satisfied.' : 'The minimum evidence gate is still missing required dimensions.',
            'missing_dimensions' => $missing,
        ];
    }

    private function recommendedNextExperts(array $routingPolicy, array $pluginResults, array $missingDimensions): array
    {
        $executed = array_keys($pluginResults);
        $candidates = array_values(array_filter(
            array_map('strval', (array)($routingPolicy['candidate_experts'] ?? [])),
            static fn(string $plugin): bool => $plugin !== 'Citation Resolver'
        ));
        $recommended = [];
        foreach ($candidates as $plugin) {
            if (!in_array($plugin, $executed, true)) {
                $recommended[] = $plugin;
            }
        }
        if (($routingPolicy['cypher_explorer_fallback'] ?? false) && !in_array('Cypher Explorer Plugin', $executed, true)) {
            $recommended[] = 'Cypher Explorer Plugin';
        }
        return array_values(array_unique($recommended));
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
            if (($analysis['asks_for_graph_analytics'] ?? false) && !in_array('Graph Analytics Plugin', $queue, true)) {
                $append[] = 'Graph Analytics Plugin';
            }
            if (($analysis['asks_for_cypher_explorer'] ?? false) && !in_array('Cypher Explorer Plugin', $queue, true)) {
                $append[] = 'Cypher Explorer Plugin';
            }
            if ($relationCount < 3 && $intent === 'mechanism' && !in_array('Sequence Plugin', $queue, true) && ($analysis['asks_for_sequence'] ?? false)) {
                $append[] = 'Sequence Plugin';
            }
        }

        if ($pluginName === 'Graph Analytics Plugin') {
            $topRows = (int)($result['result_counts']['top_k'] ?? 0);
            if ($topRows === 0 && !in_array('Cypher Explorer Plugin', $queue, true)) {
                $append[] = 'Cypher Explorer Plugin';
            }
        }

        if ($pluginName === 'Literature Plugin') {
            $reviewedCount = (int)($result['result_counts']['reviewed'] ?? 0);
            if ($reviewedCount === 0 && ($analysis['asks_for_classification'] ?? false) && !in_array('Tree Plugin', $queue, true)) {
                $append[] = 'Tree Plugin';
            }
            if ($reviewedCount > 0 && !in_array('Literature Reading Plugin', $queue, true)) {
                $append[] = 'Literature Reading Plugin';
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
            'Graph Analytics Plugin' => 'I will run a graph analytics query now because this question asks for global structure, ranking, or topology rather than a single local entity neighborhood.',
            'Cypher Explorer Plugin' => 'I will generate a read-only Cypher query to explore graph patterns that are not covered by the fixed neighborhood templates.',
            'Literature Plugin' => 'Next I will add local paper evidence and PubMed support if the current structured relations are not strong enough on their own.',
            'Literature Reading Plugin' => 'I will synthesize the retrieved citations into grouped claims, conflicts, and remaining evidence gaps.',
            'Tree Plugin' => 'I will place the recognized entities in their lineage to recover classification context where needed.',
            'Expression Plugin' => 'I will inspect the expression layer to see whether it contributes useful supporting biological context.',
            'Genome Plugin' => 'I will check whether representative loci and browser entry points exist for the current TE entities.',
            'Sequence Plugin' => 'I will match the recognized TE aliases against the Repbase-backed sequence records to recover consensus length, annotation, and structure hints.',
            'Citation Resolver' => 'I will normalize and deduplicate the citation records so the final answer can use stable references.',
            default => 'Calling a tool.',
        };
    }

    private function toolSelectedMessage(string $pluginName, array $planning): string
    {
        $gapNames = array_values(array_filter(array_map(
            static fn(array $gap): string => trim((string)($gap['gap_type'] ?? '')),
            (array)($planning['knowledge_gaps'] ?? [])
        )));
        $gapText = $gapNames === [] ? 'the current evidence gap' : implode(', ', $gapNames);

        return match ($pluginName) {
            'Entity Resolver' => 'I will stabilize entity names first so later evidence lookup does not drift across aliases.',
            'Graph Plugin' => 'I will check the local graph first because it is the strongest initial layer for ' . $gapText . '.',
            'Graph Analytics Plugin' => 'I will use graph analytics now because this question is about ranking, counts, or global graph structure.',
            'Cypher Explorer Plugin' => 'I will use the Cypher Explorer now because the fixed plugins may not cover the required graph pattern or aggregation.',
            'Literature Plugin' => 'I will add literature evidence now because the current question still needs direct citation support.',
            'Literature Reading Plugin' => 'I will synthesize the retrieved citations now so later steps receive grouped claims instead of a flat citation list.',
            'Tree Plugin' => 'I will use the lineage tree now because classification context is still missing.',
            'Expression Plugin' => 'I will inspect the expression layer now because expression context is still relevant.',
            'Genome Plugin' => 'I will inspect the genome layer now because locus-level context is still relevant.',
            'Sequence Plugin' => 'I will inspect the sequence layer now because sequence-level facts are still required.',
            'Citation Resolver' => 'I will normalize the citation layer now so the final answer can cite stable records.',
            default => 'I will use the next tool that best addresses the current evidence gap.',
        };
    }

    private function synthesizingMessage(array $planning, array $pluginResults, array $evidence): string
    {
        $used = implode(', ', array_keys($pluginResults));
        $gapCount = count((array)($planning['knowledge_gaps'] ?? []));
        return 'I am now synthesizing the resolved entities, ' . $gapCount . ' identified knowledge gaps, and ' . count($evidence) . ' evidence items into a coherent answer. Tools used: ' . $used . '.';
    }

    private function reflectionMessage(string $pluginName, array $result, array $pluginQueue, int $currentIndex): string
    {
        $remaining = array_values(array_slice($pluginQueue, $currentIndex + 1));
        $remainingText = $remaining === [] ? 'No additional tools are currently queued.' : 'Next queued tools: ' . implode(', ', $remaining) . '.';
        $counts = (array)($result['result_counts'] ?? []);
        $status = trim((string)($result['status'] ?? 'ok'));
        $summary = trim((string)($result['display_summary'] ?? $result['query_summary'] ?? ''));

        if ($status !== '' && $status !== 'ok') {
            return 'This tool did not produce a strong result. ' . $remainingText;
        }

        if ($summary !== '') {
            return $summary . ' ' . $remainingText;
        }

        if ($counts !== []) {
            return 'This tool returned ' . implode(', ', array_map(
                static fn(string $key, $value): string => $key . '=' . (string)$value,
                array_keys($counts),
                array_values($counts)
            )) . '. ' . $remainingText;
        }

        return $remainingText;
    }

    private function narrateEvent(string $model, string $language, array $event, string $fallback): string
    {
        $narrated = $this->llm->narrateEvent($model, $language, $event);
        return $narrated !== null && trim($narrated) !== '' ? trim($narrated) : $fallback;
    }

    private function emitEvent(?callable $emit, int &$eventSequence, array $event): void
    {
        $event['node'] = (string)($event['node'] ?? $this->defaultNodeForEvent((string)($event['type'] ?? 'event')));
        $event['source'] = (string)($event['source'] ?? ($event['plugin_name'] ?? $event['node']));
        $event['inputs_used'] = array_values((array)($event['inputs_used'] ?? []));
        $event['outputs_changed'] = array_values((array)($event['outputs_changed'] ?? []));
        $event['message_payload'] = $event['message_payload'] ?? ($event['payload'] ?? []);
        $event['display_text'] = (string)($event['display_text'] ?? ($event['message'] ?? ''));
        $event['sequence'] = ++$eventSequence;
        $this->emit($emit, $event);
    }

    private function emitHeartbeat(?callable $emit, int &$eventSequence, string $sessionId): void
    {
        $this->emitEvent($emit, $eventSequence, [
            'type' => 'heartbeat',
            'session_id' => $sessionId,
            'node' => 'Process Narrator Node',
            'source' => 'Process Narrator Node',
            'message' => '',
        ]);
    }

    private function defaultNodeForEvent(string $type): string
    {
        return match ($type) {
            'analysis' => 'Question Understanding Node',
            'planning_step' => 'Planning Node',
            'tool_selected', 'tool_start', 'tool_progress', 'tool_result', 'reflection' => 'Evidence Collection Node',
            'synthesizing' => 'Evidence Synthesis Node',
            'answer' => 'Answer Writer Node',
            'heartbeat', 'done', 'error' => 'Process Narrator Node',
            default => 'AcademicAgentService',
        };
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

    private function compressedPluginResults(array $pluginResults): array
    {
        $compressed = [];
        foreach ($pluginResults as $pluginName => $result) {
            $compressed[$pluginName] = [
                'plugin_name' => $pluginName,
                'status' => (string)($result['status'] ?? 'unknown'),
                'compressed_result' => (array)($result['compressed_result'] ?? []),
            ];
        }
        return $compressed;
    }

    private function buildSynthesizedEvidence(array $pluginResults, array $evidence): array
    {
        $supportedClaims = [];
        $conflictingClaims = [];
        $missingEvidence = [];
        $claimClusters = [];

        $literatureSynthesis = (array)($pluginResults['Literature Reading Plugin']['results'] ?? []);
        if ($literatureSynthesis !== []) {
            $supportedClaims = array_values(array_map('strval', (array)($literatureSynthesis['supported_claims'] ?? [])));
            $conflictingClaims = array_values(array_map('strval', (array)($literatureSynthesis['conflicting_claims'] ?? [])));
            $missingEvidence = array_values(array_map('strval', (array)($literatureSynthesis['missing_evidence'] ?? [])));
            $claimClusters = array_values((array)($literatureSynthesis['claim_clusters'] ?? []));
        }

        if ($supportedClaims === []) {
            foreach (array_slice($evidence, 0, 8) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $claim = trim((string)($item['claim'] ?? ''));
                if ($claim !== '') {
                    $supportedClaims[] = $claim;
                }
            }
        }

        if ($claimClusters === []) {
            foreach (array_slice($evidence, 0, 6) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $claim = trim((string)($item['claim'] ?? ''));
                if ($claim === '') {
                    continue;
                }
                $claimClusters[] = [
                    'claim' => $claim,
                    'summary' => trim((string)($item['body'] ?? $claim)),
                    'citations' => [],
                ];
            }
        }

        return [
            'supported_claims' => array_values(array_unique(array_filter($supportedClaims))),
            'conflicting_claims' => array_values(array_unique(array_filter($conflictingClaims))),
            'missing_evidence' => array_values(array_unique(array_filter($missingEvidence))),
            'claim_clusters' => $claimClusters,
        ];
    }

    private function generateAnswerStructure(
        string $model,
        string $question,
        array $analysis,
        array $planning,
        array $synthesizedEvidence,
        array $citations,
        array $sufficiencyDecision
    ): array {
        $payload = [
            'question' => $question,
            'analysis' => $analysis,
            'planning' => [
                'question_type' => (string)($planning['question_type'] ?? ''),
                'complexity' => (string)($planning['complexity'] ?? ''),
                'required_evidence' => (array)($planning['required_evidence'] ?? []),
            ],
            'supported_claims' => (array)($synthesizedEvidence['supported_claims'] ?? []),
            'conflicting_claims' => (array)($synthesizedEvidence['conflicting_claims'] ?? []),
            'missing_evidence' => (array)($synthesizedEvidence['missing_evidence'] ?? []),
            'citation_count' => count($citations),
            'sufficiency_decision' => $sufficiencyDecision,
        ];
        $generated = $this->llm->generateAnswerStructure($model, $payload);
        if (is_array($generated) && $this->isValidAnswerStructure($generated)) {
            return $generated;
        }
        return $this->fallbackAnswerStructure($analysis, $synthesizedEvidence);
    }

    private function isValidAnswerStructure(array $structure): bool
    {
        return trim((string)($structure['response_mode'] ?? '')) !== ''
            && is_array($structure['section_plan'] ?? null)
            && is_array($structure['claim_order'] ?? null)
            && is_array($structure['uncertainty_notes'] ?? null);
    }

    private function fallbackAnswerStructure(array $analysis, array $synthesizedEvidence): array
    {
        $intent = (string)($analysis['intent'] ?? 'relationship');
        $responseMode = match ($intent) {
            'mechanism' => 'mechanism_chain',
            'comparison' => 'contrastive',
            'literature' => 'literature_support',
            'classification' => 'lineage_explanation',
            'graph_analytics' => 'ranking_summary',
            default => 'evidence_summary',
        };

        return [
            'response_mode' => $responseMode,
            'opening_claim' => (string)($synthesizedEvidence['supported_claims'][0] ?? 'State the strongest supported claim first.'),
            'section_plan' => [
                'Main judgment',
                'Supporting evidence',
                'Evidence gaps and limits',
            ],
            'claim_order' => array_values(array_slice((array)($synthesizedEvidence['supported_claims'] ?? []), 0, 6)),
            'citation_policy' => 'Use PMID-style in-text citations when available.',
            'uncertainty_notes' => array_values(array_slice((array)($synthesizedEvidence['missing_evidence'] ?? []), 0, 4)),
        ];
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

    private function updateSessionMemory(
        array $memory,
        array $analysis,
        array $planning,
        array $pluginResults,
        array $citations,
        array $evidence,
        array $collectionState,
        array $synthesizedEvidence
    ): array
    {
        $memory = array_replace(tekg_agent_default_session_memory(), $memory);
        $memory['topic_entities'] = array_values(array_unique(array_map(
            static fn(array $entity): string => (string)($entity['canonical_label'] ?? $entity['label'] ?? ''),
            (array)($analysis['normalized_entities'] ?? [])
        )));
        $memory['last_intent'] = (string)($analysis['intent'] ?? '');
        $memory['resolved_entities'] = array_values(array_map(
            static fn(array $entity): array => [
                'label' => (string)($entity['canonical_label'] ?? $entity['label'] ?? ''),
                'type' => (string)($entity['entity_type'] ?? $entity['type'] ?? ''),
                'confidence' => (float)($entity['confidence'] ?? 0.0),
            ],
            (array)($analysis['normalized_entities'] ?? [])
        ));
        $memory['active_gaps'] = array_values((array)($collectionState['active_gaps'] ?? []));
        $memory['closed_gaps'] = array_values((array)($collectionState['closed_gaps'] ?? []));
        $memory['confirmed_claims'] = array_values(array_unique(array_map(
            static fn(array $item): string => (string)($item['claim'] ?? ''),
            array_slice($evidence, 0, 8)
        )));
        $memory['strong_claims'] = array_values(array_unique(array_map(
            static fn(array $item): string => (string)($item['claim'] ?? ''),
            array_filter($evidence, static fn(array $item): bool => (string)($item['support_strength'] ?? '') === 'high')
        )));
        $memory['weak_claims'] = array_values(array_unique(array_map(
            static fn(array $item): string => (string)($item['claim'] ?? ''),
            array_filter($evidence, static fn(array $item): bool => in_array((string)($item['support_strength'] ?? ''), ['low', 'medium'], true))
        )));
        $memory['claim_status_by_source'] = tekg_agent_json_safe(array_map(
            static fn(array $item): array => [
                'claim' => (string)($item['claim'] ?? ''),
                'source_plugin' => (string)($item['source_plugin'] ?? ''),
                'support_strength' => (string)($item['support_strength'] ?? 'medium'),
            ],
            array_slice($evidence, 0, 16)
        ));
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
        $memory['expert_attempts'] = tekg_agent_json_safe(array_map(
            static fn(string $pluginName, array $result): array => [
                'plugin' => $pluginName,
                'status' => (string)($result['status'] ?? 'unknown'),
                'latency_ms' => (int)($result['latency_ms'] ?? 0),
            ],
            array_keys($pluginResults),
            array_values($pluginResults)
        ));
        $memory['failed_queries'] = tekg_agent_json_safe(array_values(array_filter(array_map(
            static fn(string $pluginName, array $result): ?array => in_array((string)($result['status'] ?? ''), ['empty', 'error'], true)
                ? [
                    'plugin' => $pluginName,
                    'status' => (string)($result['status'] ?? ''),
                    'summary' => (string)($result['display_summary'] ?? $result['query_summary'] ?? ''),
                ]
                : null,
            array_keys($pluginResults),
            array_values($pluginResults)
        ))));
        $memory['compression_notes'] = tekg_agent_json_safe(array_values(array_filter(array_map(
            static fn(array $result): ?array => isset($result['compressed_result'])
                ? [
                    'plugin' => (string)($result['plugin_name'] ?? ''),
                    'key_findings' => (array)($result['compressed_result']['key_findings'] ?? []),
                    'limitations' => (array)($result['compressed_result']['limitations'] ?? []),
                ]
                : null,
            array_values($pluginResults)
        ))));
        $memory['next_step_hints'] = tekg_agent_json_safe(array_values(array_slice(array_filter([
            (array)($planning['subtasks'] ?? []),
            (array)($collectionState['remaining_candidates'] ?? []),
            (array)($synthesizedEvidence['missing_evidence'] ?? []),
        ]), 0, 3)));
        $memory['session_snapshot'] = tekg_agent_json_safe([
            'intent' => (string)($analysis['intent'] ?? ''),
            'resolved_entities' => $memory['resolved_entities'],
            'closed_gaps' => $memory['closed_gaps'],
            'strong_claims' => array_slice((array)$memory['strong_claims'], 0, 6),
            'next_step_hints' => $memory['next_step_hints'],
        ]);

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
            'compressed_result' => (array)($result['compressed_result'] ?? []),
            'raw_result' => (array)($result['raw_result'] ?? []),
            'raw_preview' => $result['display_details']['raw_preview'] ?? null,
            'errors' => array_values((array)($result['errors'] ?? [])),
            'result_counts' => (array)($result['result_counts'] ?? []),
            'display_details' => (array)($result['display_details'] ?? []),
        ];
    }

    private function buildNodePayloads(
        string $question,
        array $analysis,
        array $planning,
        array $pluginResults,
        array $evidence,
        array $citations,
        array $collectionState = [],
        array $sufficiencyDecision = [],
        array $answerStructure = [],
        array $synthesizedEvidence = []
    ): array {
        $collectedResults = [];
        foreach ($pluginResults as $result) {
            $pluginName = (string)($result['plugin_name'] ?? '');
            if ($pluginName === 'Graph Plugin') {
                $collectedResults['graph_result'] = $result;
            } elseif ($pluginName === 'Graph Analytics Plugin') {
                $collectedResults['analytics_result'] = $result;
            } elseif ($pluginName === 'Cypher Explorer Plugin') {
                $collectedResults['cypher_result'] = $result;
            } elseif ($pluginName === 'Literature Plugin') {
                $collectedResults['literature_result'] = $result;
            } elseif ($pluginName === 'Literature Reading Plugin') {
                $collectedResults['literature_synthesis'] = $result;
            } elseif ($pluginName === 'Tree Plugin') {
                $collectedResults['tree_result'] = $result;
            } elseif ($pluginName === 'Expression Plugin') {
                $collectedResults['expression_result'] = $result;
            } elseif ($pluginName === 'Genome Plugin') {
                $collectedResults['genome_result'] = $result;
            } elseif ($pluginName === 'Sequence Plugin') {
                $collectedResults['sequence_result'] = $result;
            } elseif ($pluginName === 'Citation Resolver') {
                $collectedResults['citation_result'] = $result;
            }
        }

        $supportedClaims = array_values(array_filter(array_map(
            static fn(array $item): string => trim((string)($item['claim'] ?? '')),
            array_slice($evidence, 0, 10)
        )));
        $synthesisOutput = $synthesizedEvidence !== [] ? $synthesizedEvidence : [
            'supported_claims' => $supportedClaims,
            'conflicting_claims' => [],
            'missing_evidence' => [],
            'claim_clusters' => [],
        ];
        if ($sufficiencyDecision === []) {
            $sufficiencyDecision = [
                'is_sufficient' => count($evidence) > 0 || count($pluginResults) >= 2,
                'reason' => count($evidence) > 0
                    ? 'At least one evidence item has been collected.'
                    : (count($pluginResults) >= 2
                        ? 'Multiple experts have already returned results, so the controller can now decide whether to stop or continue.'
                        : 'More expert outputs are still needed before the controller should stop.'),
            ];
        }
        if ($answerStructure === []) {
            $answerStructure = [
                'opening' => 'State the strongest answer first.',
                'sections' => array_values(array_filter([
                    isset($collectedResults['graph_result']) ? 'Structured graph evidence' : null,
                    isset($collectedResults['analytics_result']) ? 'Graph analytics summary' : null,
                    isset($collectedResults['literature_result']) ? 'Literature evidence' : null,
                    isset($collectedResults['literature_synthesis']) ? 'Claim consistency and gaps' : null,
                    isset($collectedResults['sequence_result']) ? 'Sequence-backed facts' : null,
                ])),
                'citation_style' => 'PMID-backed references only',
            ];
        }

        return [
            'Question Understanding Node' => [
                'input' => ['question' => $question],
                'output' => [
                    'analysis' => $analysis,
                    'entity_resolution' => (array)($analysis['normalized_entities'] ?? []),
                ],
            ],
            'Planning Node' => [
                'input' => [
                    'question' => $question,
                    'analysis' => $analysis,
                    'entity_resolution' => (array)($analysis['normalized_entities'] ?? []),
                    'session_context' => (array)($planning['session_context'] ?? []),
                ],
                'output' => ['planning' => $planning],
            ],
            'Evidence Collection Node' => [
                'input' => [
                    'question' => $question,
                    'analysis' => $analysis,
                    'planning' => $planning,
                    'graph_result' => $collectedResults['graph_result'] ?? null,
                    'analytics_result' => $collectedResults['analytics_result'] ?? null,
                    'cypher_result' => $collectedResults['cypher_result'] ?? null,
                    'literature_result' => $collectedResults['literature_result'] ?? null,
                    'literature_synthesis' => $collectedResults['literature_synthesis'] ?? null,
                    'tree_result' => $collectedResults['tree_result'] ?? null,
                    'expression_result' => $collectedResults['expression_result'] ?? null,
                    'genome_result' => $collectedResults['genome_result'] ?? null,
                    'sequence_result' => $collectedResults['sequence_result'] ?? null,
                    'citation_result' => $collectedResults['citation_result'] ?? null,
                    'collected_results' => $collectedResults,
                    'evidence_bundle' => $evidence,
                    'citation_bundle' => $citations,
                ],
                'output' => [
                    'collection_state' => [
                        'tool_plan' => array_values((array)($planning['tool_plan'] ?? [])),
                        'executed_plugins' => array_values(array_map(
                            static fn(array $item): string => (string)($item['plugin_name'] ?? ''),
                            $pluginResults
                        )),
                        'evidence_count' => count($evidence),
                        'citation_count' => count($citations),
                    ],
                    'active_expert' => (array)($planning['tool_plan'][0] ?? []),
                    'sufficiency_decision' => $sufficiencyDecision,
                    'graph_result' => $collectedResults['graph_result'] ?? null,
                    'analytics_result' => $collectedResults['analytics_result'] ?? null,
                    'cypher_result' => $collectedResults['cypher_result'] ?? null,
                    'literature_result' => $collectedResults['literature_result'] ?? null,
                    'literature_synthesis' => $collectedResults['literature_synthesis'] ?? null,
                    'tree_result' => $collectedResults['tree_result'] ?? null,
                    'expression_result' => $collectedResults['expression_result'] ?? null,
                    'genome_result' => $collectedResults['genome_result'] ?? null,
                    'sequence_result' => $collectedResults['sequence_result'] ?? null,
                    'citation_result' => $collectedResults['citation_result'] ?? null,
                    'collected_results' => $collectedResults,
                    'evidence_bundle' => $evidence,
                    'citation_bundle' => $citations,
                ],
            ],
            'Evidence Synthesis Node' => [
                'input' => [
                    'question' => $question,
                    'analysis' => $analysis,
                    'planning' => $planning,
                    'graph_result' => $collectedResults['graph_result'] ?? null,
                    'analytics_result' => $collectedResults['analytics_result'] ?? null,
                    'cypher_result' => $collectedResults['cypher_result'] ?? null,
                    'literature_result' => $collectedResults['literature_result'] ?? null,
                    'literature_synthesis' => $collectedResults['literature_synthesis'] ?? null,
                    'tree_result' => $collectedResults['tree_result'] ?? null,
                    'expression_result' => $collectedResults['expression_result'] ?? null,
                    'genome_result' => $collectedResults['genome_result'] ?? null,
                    'sequence_result' => $collectedResults['sequence_result'] ?? null,
                    'citation_result' => $collectedResults['citation_result'] ?? null,
                    'collected_results' => $collectedResults,
                    'evidence_bundle' => $evidence,
                    'citation_bundle' => $citations,
                ],
                'output' => $synthesisOutput,
            ],
            'Answer Structuring Node' => [
                'input' => array_merge(
                    [
                        'question' => $question,
                        'analysis' => $analysis,
                        'planning' => $planning,
                        'collected_results' => $collectedResults,
                    ],
                    $collectedResults,
                    $synthesisOutput
                ),
                'output' => ['answer_structure' => $answerStructure],
            ],
            'Answer Writer Node' => [
                'input' => [
                    'question' => $question,
                    'analysis' => $analysis,
                    'answer_structure' => $answerStructure,
                    'supported_claims' => $synthesisOutput['supported_claims'],
                    'conflicting_claims' => $synthesisOutput['conflicting_claims'],
                    'missing_evidence' => $synthesisOutput['missing_evidence'],
                    'citation_bundle' => $citations,
                ],
                'output' => ['answer' => null],
            ],
            'Process Narrator Node' => [
                'input' => [
                    'event_stream' => [],
                    'analysis' => $analysis,
                    'entity_resolution' => (array)($analysis['normalized_entities'] ?? []),
                    'planning' => $planning,
                    'collection_state' => [
                        'tool_plan' => array_values((array)($planning['tool_plan'] ?? [])),
                        'executed_plugins' => array_values(array_map(
                            static fn(array $item): string => (string)($item['plugin_name'] ?? ''),
                            $pluginResults
                        )),
                    ],
                    'active_expert' => (array)($planning['tool_plan'][0] ?? []),
                    'sufficiency_decision' => $sufficiencyDecision,
                    'graph_result' => $collectedResults['graph_result'] ?? null,
                    'analytics_result' => $collectedResults['analytics_result'] ?? null,
                    'cypher_result' => $collectedResults['cypher_result'] ?? null,
                    'literature_result' => $collectedResults['literature_result'] ?? null,
                    'literature_synthesis' => $collectedResults['literature_synthesis'] ?? null,
                    'tree_result' => $collectedResults['tree_result'] ?? null,
                    'expression_result' => $collectedResults['expression_result'] ?? null,
                    'genome_result' => $collectedResults['genome_result'] ?? null,
                    'sequence_result' => $collectedResults['sequence_result'] ?? null,
                    'citation_result' => $collectedResults['citation_result'] ?? null,
                    'supported_claims' => $synthesisOutput['supported_claims'],
                    'conflicting_claims' => $synthesisOutput['conflicting_claims'],
                    'missing_evidence' => $synthesisOutput['missing_evidence'],
                    'claim_clusters' => $synthesisOutput['claim_clusters'],
                    'collected_results' => $collectedResults,
                    'answer_structure' => $answerStructure,
                    'answer' => null,
                ],
                'output' => ['trace_event' => null],
            ],
        ];
    }
}
