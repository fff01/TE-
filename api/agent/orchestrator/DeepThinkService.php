<?php
declare(strict_types=1);

final class TekgDeepThinkService
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

        $requestId = trim((string)($payload['request_id'] ?? ''));
        if ($requestId === '') {
            $requestId = tekg_agent_make_request_id();
        }
        $runtimeConfig = $this->runtimeConfig($payload, $requestId);
        $this->llm = new TekgAgentLlmClient($runtimeConfig);
        $this->applyExecutionBudget($runtimeConfig);
        $this->logDiagnostic($requestId, 'deepthink_request_started', [
            'question' => $question,
            'mode' => 'deepthink',
        ]);

        $model = $this->resolveModel($payload);
        $narratorModel = $this->resolveNarratorModel($payload, $model);
        $sessionId = trim((string)($payload['session_id'] ?? ''));
        if ($sessionId === '') {
            $sessionId = tekg_agent_make_session_id();
        }
        $answerLanguage = tekg_agent_detect_language($question, trim((string)($payload['language'] ?? 'english')));
        $processLanguage = 'english';

        $sessionMemory = tekg_agent_load_session_memory($sessionId);
        $analysis = $this->normalizer->analyze($question, $answerLanguage);
        $analysis['answer_language'] = $answerLanguage;
        $analysis['language'] = 'english';
        $analysis['session_memory'] = $sessionMemory;

        $eventSequence = 0;
        $detailCounter = 0;
        $reasoningTrace = [];
        $pluginResults = [];
        $planning = $this->lightweightPlanning($analysis);
        $this->emitAnalysisThoughtFlow($emit, $sessionId, $narratorModel, $processLanguage, $analysis, $eventSequence);

        $entityResult = $this->runPlugin(
            'Entity Resolver',
            $question,
            $analysis,
            $planning,
            $pluginResults,
            $model,
            $narratorModel,
            $processLanguage,
            $sessionId,
            $eventSequence,
            $detailCounter,
            $requestId,
            $emit,
            $reasoningTrace,
            'I will stabilize entity names first so the later tool calls do not drift across aliases.'
        );
        $pluginResults['Entity Resolver'] = $entityResult;

        $maxSteps = max(2, min(5, (int)($payload['max_plugin_steps'] ?? 4)));
        for ($step = 0; $step < $maxSteps; $step++) {
            $decision = $this->decideNextPlugin($question, $analysis, $pluginResults, $model, $requestId);
            $nextPlugin = trim((string)($decision['next_plugin'] ?? ''));
            $done = (bool)($decision['done'] ?? false);

            if ($done || $nextPlugin === '') {
                $this->emitReflection(
                    $emit,
                    $eventSequence,
                    $sessionId,
                    $narratorModel,
                    $processLanguage,
                    'The currently collected evidence is enough to move into synthesis.',
                    [
                        'type' => 'reflection',
                        'decision' => $decision,
                        'plugin_results' => $this->compressedPluginResults($pluginResults),
                    ]
                );
                break;
            }

            if (!isset($this->plugins[$nextPlugin]) || isset($pluginResults[$nextPlugin])) {
                break;
            }

            $result = $this->runPlugin(
                $nextPlugin,
                $question,
                $analysis,
                $planning,
                $pluginResults,
                $model,
                $narratorModel,
                $processLanguage,
                $sessionId,
                $eventSequence,
                $detailCounter,
                $requestId,
                $emit,
                $reasoningTrace,
                trim((string)($decision['reason'] ?? ''))
            );
            $pluginResults[$nextPlugin] = $result;

            $remaining = $this->candidatePluginOrder($analysis, $pluginResults);
            if ($remaining !== []) {
                $this->emitReflection(
                    $emit,
                    $eventSequence,
                    $sessionId,
                    $narratorModel,
                    $processLanguage,
                    'I collected another evidence layer and will decide whether another plugin is still needed.',
                    [
                        'type' => 'reflection',
                        'plugin_name' => $nextPlugin,
                        'result' => [
                            'status' => (string)($result['status'] ?? 'unknown'),
                            'display_summary' => (string)($result['display_summary'] ?? ''),
                            'result_counts' => (array)($result['result_counts'] ?? []),
                        ],
                        'remaining_candidates' => $remaining,
                    ]
                );
            }
        }

        if ($this->shouldRunCitationResolver($pluginResults) && !isset($pluginResults['Citation Resolver'])) {
            $pluginResults['Citation Resolver'] = $this->runPlugin(
                'Citation Resolver',
                $question,
                $analysis,
                $planning,
                $pluginResults,
                $model,
                $narratorModel,
                $processLanguage,
                $sessionId,
                $eventSequence,
                $detailCounter,
                $requestId,
                $emit,
                $reasoningTrace,
                'I will normalize the citation layer now so the final answer can cite stable records.'
            );
        }

        $evidence = $this->aggregateEvidence($pluginResults);
        $citations = $this->aggregateCitations($pluginResults);
        $limits = $this->aggregateLimits($pluginResults, $evidence);
        $confidence = $this->inferConfidence($pluginResults, $evidence, $citations);
        $synthesizedEvidence = $this->buildSynthesizedEvidence($pluginResults, $evidence);

        $this->emitEvent($emit, $eventSequence, [
            'type' => 'synthesizing',
            'session_id' => $sessionId,
            'node' => 'Deep Think',
            'source' => 'Deep Think',
            'inputs_used' => ['plugin_results', 'evidence_bundle', 'citation_bundle'],
            'outputs_changed' => ['supported_claims', 'answer_structure'],
            'message' => $this->narrateEvent(
                $narratorModel,
                $processLanguage,
                [
                    'type' => 'synthesizing',
                    'plugin_results' => $this->compressedPluginResults($pluginResults),
                    'evidence_count' => count($evidence),
                    'citation_count' => count($citations),
                ],
                'I am now synthesizing the plugin results into a final answer.'
            ),
            'payload' => [
                'synthesized_evidence' => $synthesizedEvidence,
            ],
        ]);

        $answerStructure = $this->generateAnswerStructure(
            $model,
            $question,
            $analysis,
            $synthesizedEvidence,
            $citations,
            [
                'is_sufficient' => true,
                'reason' => 'Deep Think finished its lightweight tool loop.',
                'missing_dimensions' => [],
            ],
            $requestId
        );

        $writingStartedAt = microtime(true);
        $this->logDiagnostic($requestId, 'deepthink_answer_generation_started', [
            'model' => $model,
            'response_mode' => (string)($answerStructure['response_mode'] ?? ''),
        ]);

        $answer = '';
        $writingFailed = false;
        $failureReason = '';
        try {
            $llm = $this->llm->writeStructuredAnswer(
                $model,
                $answerLanguage,
                $question,
                $this->analysisForWriting($analysis),
                $answerStructure,
                $this->limitClaimTexts((array)($synthesizedEvidence['supported_claims'] ?? []), 6),
                $this->limitClaimTexts((array)($synthesizedEvidence['conflicting_claims'] ?? []), 3),
                $this->limitClaimTexts((array)($synthesizedEvidence['missing_evidence'] ?? []), 4),
                $this->lightweightCitations($citations, 8),
                $confidence,
                $limits,
                max(20, (int)($runtimeConfig['llm_answer_timeout'] ?? 40))
            );
        } catch (Throwable $error) {
            $llm = [
                'ok' => false,
                'provider' => $this->inferProvider($model),
                'model' => $model,
                'content' => '',
                'error' => $error->getMessage(),
            ];
        }

        if (($llm['ok'] ?? false) === true) {
            $answer = trim((string)($llm['content'] ?? ''));
        }
        if ($answer === '') {
            $writingFailed = true;
            $failureReason = trim((string)($llm['error'] ?? 'The Deep Think writer did not return usable content.'));
        }

        $timings = [
            'writing_ms' => (int)round((microtime(true) - $writingStartedAt) * 1000),
        ];
        $this->logDiagnostic($requestId, 'deepthink_answer_generation_completed', [
            'writing_failed' => $writingFailed,
            'answer_length' => tekg_agent_strlen($answer),
            'timings' => $timings,
        ]);

        if ($writingFailed) {
            $this->emitEvent($emit, $eventSequence, [
                'type' => 'error',
                'request_id' => $requestId,
                'session_id' => $sessionId,
                'node' => 'Deep Think',
                'source' => 'Deep Think',
                'message' => $failureReason !== '' ? $failureReason : 'The final writing step failed.',
                'payload' => [
                    'writing_failed' => true,
                    'failure_stage' => 'Writing',
                    'failure_reason' => $failureReason,
                ],
            ]);
        } else {
            $this->emitEvent($emit, $eventSequence, [
                'type' => 'answer',
                'request_id' => $requestId,
                'session_id' => $sessionId,
                'node' => 'Deep Think',
                'source' => 'Deep Think',
                'message' => $answer,
                'language' => $answerLanguage,
            ]);
        }

        $response = [
            'question' => $question,
            'mode' => 'deepthink',
            'request_id' => $requestId,
            'session_id' => $sessionId,
            'language' => $answerLanguage,
            'model' => $model,
            'analysis' => $analysis,
            'answer' => $answer,
            'writing_failed' => $writingFailed,
            'failure_stage' => $writingFailed ? 'Writing' : '',
            'failure_reason' => $failureReason,
            'used_plugins' => array_values(array_keys($pluginResults)),
            'plugin_calls' => array_values($pluginResults),
            'evidence' => $evidence,
            'citations' => $citations,
            'answer_structure' => $answerStructure,
            'synthesized_evidence' => $synthesizedEvidence,
            'confidence' => $confidence,
            'timings' => $timings,
        ];

        $this->updateSessionMemory($sessionId, $analysis, $pluginResults, $citations);

        $this->emitEvent($emit, $eventSequence, [
            'type' => 'done',
            'request_id' => $requestId,
            'session_id' => $sessionId,
            'node' => 'Deep Think',
            'source' => 'Deep Think',
            'payload' => [
                'answer' => $answer,
                'language' => $answerLanguage,
                'writing_failed' => $writingFailed,
                'failure_stage' => $response['failure_stage'],
                'failure_reason' => $failureReason,
            ],
        ]);

        return $response;
    }

    private function runtimeConfig(array $payload, string $requestId): array
    {
        $config = $this->config;
        $config['request_id'] = $requestId;
        $config['agent_execution_timeout'] = max(90, (int)($payload['execution_timeout'] ?? $config['agent_execution_timeout'] ?? 300));
        $config['llm_narrator_timeout'] = max(4, (int)($payload['llm_narrator_timeout'] ?? $config['llm_narrator_timeout'] ?? 8));
        $config['llm_json_timeout'] = max(10, (int)($payload['llm_json_timeout'] ?? $config['llm_json_timeout'] ?? 20));
        $config['llm_answer_timeout'] = max(20, (int)($payload['llm_answer_timeout'] ?? $config['llm_answer_timeout'] ?? 40));
        return $config;
    }

    private function applyExecutionBudget(array $config): void
    {
        $timeout = max(60, (int)($config['agent_execution_timeout'] ?? 300));
        @ini_set('max_execution_time', (string)$timeout);
        if (function_exists('set_time_limit')) {
            @set_time_limit($timeout);
        }
    }

    private function logDiagnostic(string $requestId, string $event, array $payload = []): void
    {
        tekg_agent_append_diagnostic_log($requestId, $event, $payload);
    }

    private function resolveModel(array $payload): string
    {
        return trim((string)($payload['model'] ?? $this->config['deepseek_reasoner_model'] ?? $this->config['deepseek_model'] ?? 'deepseek-reasoner'));
    }

    private function resolveNarratorModel(array $payload, string $fallbackModel): string
    {
        return trim((string)($payload['narrator_model'] ?? $fallbackModel));
    }

    private function lightweightPlanning(array $analysis): array
    {
        $intent = (string)($analysis['intent'] ?? 'relationship');
        return [
            'question_type' => $intent,
            'required_evidence' => $this->requiredEvidenceForIntent($intent),
            'knowledge_gaps' => [],
            'subtasks' => [],
            'tool_plan' => [],
        ];
    }

    private function requiredEvidenceForIntent(string $intent): array
    {
        return match ($intent) {
            'sequence' => ['sequence'],
            'genome' => ['genome'],
            'expression' => ['expression'],
            'classification' => ['classification'],
            'graph_analytics' => ['graph structure'],
            'literature' => ['literature'],
            'mechanism' => ['structured relations', 'literature'],
            default => ['structured relations'],
        };
    }

    private function emitAnalysisThoughtFlow(?callable $emit, string $sessionId, string $model, string $processLanguage, array $analysis, int &$eventSequence): void
    {
        $entities = array_values(array_filter(array_map(function (array $entity): string {
            $label = trim((string)($entity['canonical_label'] ?? $entity['label'] ?? ''));
            $type = trim((string)($entity['entity_type'] ?? $entity['type'] ?? ''));
            if ($label === '') {
                return '';
            }
            return $label . ($type !== '' ? ' (' . $type . ')' : '');
        }, (array)($analysis['normalized_entities'] ?? []))));

        $lines = [
            $this->narrateEvent(
                $model,
                $processLanguage,
                ['type' => 'analysis', 'focus' => 'entities', 'entities' => $analysis['normalized_entities'] ?? []],
                'Recognized entities: ' . ($entities === [] ? 'none yet.' : implode(', ', $entities) . '.')
            ),
            $this->narrateEvent(
                $model,
                $processLanguage,
                ['type' => 'analysis', 'focus' => 'intent', 'intent' => $analysis['intent'] ?? '', 'complexity' => $analysis['complexity'] ?? ''],
                'Question type: ' . (string)($analysis['intent'] ?? 'relationship') . '. Complexity: ' . (string)($analysis['complexity'] ?? 'simple_lookup') . '.'
            ),
        ];

        foreach ($lines as $line) {
            if (trim($line) === '') {
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
    }

    private function runPlugin(
        string $pluginName,
        string $question,
        array $analysis,
        array $planning,
        array $pluginResults,
        string $model,
        string $narratorModel,
        string $processLanguage,
        string $sessionId,
        int &$eventSequence,
        int &$detailCounter,
        string $requestId,
        ?callable $emit,
        array &$reasoningTrace,
        string $selectionReason = ''
    ): array {
        $plugin = $this->plugins[$pluginName] ?? null;
        if (!$plugin instanceof TekgAgentPluginInterface) {
            throw new RuntimeException('Unknown plugin: ' . $pluginName);
        }

        $fallbackSelected = $selectionReason !== '' ? $selectionReason : $this->toolSelectedMessage($pluginName);
        $this->emitEvent($emit, $eventSequence, [
            'type' => 'tool_selected',
            'session_id' => $sessionId,
            'plugin_name' => $pluginName,
            'message' => $this->narrateEvent(
                $narratorModel,
                $processLanguage,
                ['type' => 'tool_selected', 'plugin_name' => $pluginName, 'reason' => $selectionReason],
                $fallbackSelected
            ),
        ]);

        $result = $plugin->run([
            'question' => $question,
            'analysis' => $analysis,
            'plugin_results' => $pluginResults,
            'planning' => $planning,
            'config' => $this->expertConfig($model),
        ]);
        $result = $this->augmentPluginResult($pluginName, $result, $analysis, $planning);
        $this->logDiagnostic($requestId, 'deepthink_plugin_completed', [
            'plugin_name' => $pluginName,
            'status' => (string)($result['status'] ?? 'unknown'),
            'result_counts' => (array)($result['result_counts'] ?? []),
            'latency_ms' => (int)($result['latency_ms'] ?? 0),
        ]);

        $payloadForUi = $this->toolPayloadForUi($result);
        $detailId = 'tool-' . (++$detailCounter);
        $this->emitEvent($emit, $eventSequence, [
            'type' => 'tool_result',
            'session_id' => $sessionId,
            'plugin_name' => $pluginName,
            'display_label' => (string)($result['display_label'] ?? $pluginName),
            'summary' => (string)($result['display_summary'] ?? $result['query_summary'] ?? ''),
            'message' => $this->narrateEvent(
                $narratorModel,
                $processLanguage,
                ['type' => 'tool_result', 'plugin_name' => $pluginName, 'result' => $result],
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

        return $result;
    }

    private function decideNextPlugin(string $question, array $analysis, array $pluginResults, string $model, string $requestId): array
    {
        $candidates = $this->candidatePluginOrder($analysis, $pluginResults);
        if ($candidates === []) {
            return [
                'done' => true,
                'next_plugin' => '',
                'reason' => 'No remaining plugins are required for this question type.',
            ];
        }

        $payload = [
            'question' => $question,
            'analysis' => [
                'intent' => (string)($analysis['intent'] ?? ''),
                'complexity' => (string)($analysis['complexity'] ?? ''),
                'normalized_entities' => array_slice((array)($analysis['normalized_entities'] ?? []), 0, 4),
            ],
            'used_plugins' => array_values(array_keys($pluginResults)),
            'plugin_results' => $this->compressedPluginResults($pluginResults),
            'candidate_plugins' => array_map(fn(string $name): array => [
                'name' => $name,
                'purpose' => $this->pluginPurpose($name),
            ], $candidates),
        ];

        $generated = $this->llm->generateJson(
            $model,
            'You are a single-model tool-using academic assistant. Decide whether more plugin evidence is needed. ' .
            'Return JSON with keys done (boolean), next_plugin (string), reason (string). ' .
            'Only choose next_plugin from candidate_plugins. If the existing evidence is already enough for a concise answer, set done=true.',
            $payload,
            max(8, (int)($this->config['llm_json_timeout'] ?? 20)),
            'deepthink_router'
        );

        if (is_array($generated)) {
            $selected = trim((string)($generated['next_plugin'] ?? ''));
            if (($generated['done'] ?? false) === true) {
                return [
                    'done' => true,
                    'next_plugin' => '',
                    'reason' => trim((string)($generated['reason'] ?? 'The current evidence is enough.')),
                ];
            }
            if ($selected !== '' && in_array($selected, $candidates, true)) {
                return [
                    'done' => false,
                    'next_plugin' => $selected,
                    'reason' => trim((string)($generated['reason'] ?? '')),
                ];
            }
        }

        $fallback = $candidates[0] ?? '';
        $this->logDiagnostic($requestId, 'deepthink_router_fallback', [
            'next_plugin' => $fallback,
            'candidates' => $candidates,
        ]);
        return [
            'done' => false,
            'next_plugin' => $fallback,
            'reason' => 'I will continue with the next highest-priority plugin for this question type.',
        ];
    }

    private function candidatePluginOrder(array $analysis, array $pluginResults): array
    {
        $intent = (string)($analysis['intent'] ?? 'relationship');
        $order = match ($intent) {
            'sequence' => ['Sequence Plugin', 'Literature Plugin'],
            'genome' => ['Genome Plugin', 'Literature Plugin'],
            'expression' => ['Expression Plugin', 'Literature Plugin'],
            'classification' => ['Tree Plugin', 'Literature Plugin'],
            'literature' => ['Literature Plugin', 'Literature Reading Plugin'],
            'graph_analytics' => ['Graph Analytics Plugin', 'Cypher Explorer Plugin'],
            'mechanism' => ['Graph Plugin', 'Literature Plugin', 'Literature Reading Plugin'],
            'comparison' => ['Graph Plugin', 'Literature Plugin', 'Literature Reading Plugin'],
            default => ['Graph Plugin', 'Literature Plugin', 'Literature Reading Plugin'],
        };

        if (($analysis['asks_for_graph_analytics'] ?? false) && !in_array('Graph Analytics Plugin', $order, true)) {
            array_unshift($order, 'Graph Analytics Plugin');
        }
        if (($analysis['asks_for_sequence'] ?? false) && !in_array('Sequence Plugin', $order, true)) {
            $order[] = 'Sequence Plugin';
        }
        if (($analysis['asks_for_genome'] ?? false) && !in_array('Genome Plugin', $order, true)) {
            $order[] = 'Genome Plugin';
        }
        if (($analysis['asks_for_expression'] ?? false) && !in_array('Expression Plugin', $order, true)) {
            $order[] = 'Expression Plugin';
        }
        if (($analysis['asks_for_classification'] ?? false) && !in_array('Tree Plugin', $order, true)) {
            $order[] = 'Tree Plugin';
        }

        $filtered = [];
        foreach ($order as $pluginName) {
            if (isset($pluginResults[$pluginName])) {
                continue;
            }
            if ($pluginName === 'Literature Reading Plugin') {
                $reviewed = (int)($pluginResults['Literature Plugin']['result_counts']['reviewed'] ?? 0);
                if ($reviewed <= 0) {
                    continue;
                }
            }
            $filtered[] = $pluginName;
        }
        return array_values(array_unique($filtered));
    }

    private function pluginPurpose(string $pluginName): string
    {
        return match ($pluginName) {
            'Graph Plugin' => 'Lookup structured graph relations around the recognized entities.',
            'Graph Analytics Plugin' => 'Answer rankings, counts, and global topology questions over the knowledge graph.',
            'Cypher Explorer Plugin' => 'Run a read-only custom Cypher exploration when fixed graph templates are insufficient.',
            'Literature Plugin' => 'Collect local and PubMed literature evidence.',
            'Literature Reading Plugin' => 'Synthesize retrieved papers into grouped supported and conflicting claims.',
            'Tree Plugin' => 'Recover lineage and classification context.',
            'Expression Plugin' => 'Recover expression-related biological context.',
            'Genome Plugin' => 'Recover genomic locus and browser context.',
            'Sequence Plugin' => 'Recover sequence, consensus length, and structure facts.',
            default => 'Use a plugin.',
        };
    }

    private function emitReflection(?callable $emit, int &$eventSequence, string $sessionId, string $model, string $processLanguage, string $fallback, array $payload): void
    {
        $this->emitEvent($emit, $eventSequence, [
            'type' => 'reflection',
            'session_id' => $sessionId,
            'node' => 'Deep Think',
            'source' => 'Deep Think',
            'inputs_used' => ['plugin_results'],
            'outputs_changed' => ['plugin_queue'],
            'message' => $this->narrateEvent($model, $processLanguage, $payload, $fallback),
            'payload' => $payload,
        ]);
    }

    private function toolSelectedMessage(string $pluginName): string
    {
        return match ($pluginName) {
            'Entity Resolver' => 'I will stabilize entity names first so later evidence lookup does not drift across aliases.',
            'Graph Plugin' => 'I will check the local graph first because this question needs structured relations.',
            'Graph Analytics Plugin' => 'I will use graph analytics because this question is about ranking, counts, or topology.',
            'Cypher Explorer Plugin' => 'I will use a custom Cypher exploration because the fixed graph templates may not be enough.',
            'Literature Plugin' => 'I will add literature evidence because citation support is still useful here.',
            'Literature Reading Plugin' => 'I will synthesize the retrieved papers into grouped claims before answering.',
            'Tree Plugin' => 'I will recover lineage context because this question is about classification.',
            'Expression Plugin' => 'I will inspect the expression layer because the question asks for expression context.',
            'Genome Plugin' => 'I will inspect genomic locus context for the recognized entity.',
            'Sequence Plugin' => 'I will inspect sequence and structure facts for the recognized entity.',
            'Citation Resolver' => 'I will normalize the collected citation records before the final answer.',
            default => 'I will use the next plugin that best fits the question.',
        };
    }

    private function expertConfig(string $model): array
    {
        $config = $this->config;
        $config['deepseek_model'] = $model;
        $config['deepseek_reasoner_model'] = $model;
        return $config;
    }

    private function narrateEvent(string $model, string $language, array $event, string $fallback): string
    {
        $narrated = $this->llm->narrateEvent($model, $language, $event);
        return $narrated !== null && trim($narrated) !== '' ? trim($narrated) : $fallback;
    }

    private function emitEvent(?callable $emit, int &$eventSequence, array $event): void
    {
        if (!isset($event['request_id']) && !empty($this->config['request_id'])) {
            $event['request_id'] = (string)$this->config['request_id'];
        }
        $event['node'] = (string)($event['node'] ?? 'Deep Think');
        $event['source'] = (string)($event['source'] ?? ($event['plugin_name'] ?? $event['node']));
        $event['inputs_used'] = array_values((array)($event['inputs_used'] ?? []));
        $event['outputs_changed'] = array_values((array)($event['outputs_changed'] ?? []));
        $event['message_payload'] = $event['message_payload'] ?? ($event['payload'] ?? []);
        $event['display_text'] = (string)($event['display_text'] ?? ($event['message'] ?? ''));
        $event['sequence'] = ++$eventSequence;
        if ($emit !== null) {
            $emit($event);
        }
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
                if (is_array($item) && trim((string)($item['title'] ?? '')) !== '') {
                    $keyFindings[] = trim((string)$item['title']);
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
            $excerpt[$key] = is_array($value) ? array_slice($value, 0, 10) : $value;
        }
        return tekg_agent_json_safe($excerpt);
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
                if (is_array($item)) {
                    $claim = trim((string)($item['claim'] ?? ''));
                    if ($claim !== '') {
                        $supportedClaims[] = $claim;
                    }
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

    private function generateAnswerStructure(string $model, string $question, array $analysis, array $synthesizedEvidence, array $citations, array $decision, string $requestId): array
    {
        $payload = [
            'question' => $question,
            'intent' => (string)($analysis['intent'] ?? ''),
            'complexity' => (string)($analysis['complexity'] ?? ''),
            'normalized_entities' => array_slice((array)($analysis['normalized_entities'] ?? []), 0, 4),
            'supported_claims' => $this->limitClaimTexts((array)($synthesizedEvidence['supported_claims'] ?? []), 6),
            'conflicting_claims' => $this->limitClaimTexts((array)($synthesizedEvidence['conflicting_claims'] ?? []), 3),
            'missing_evidence' => $this->limitClaimTexts((array)($synthesizedEvidence['missing_evidence'] ?? []), 4),
            'citation_count' => count($citations),
            'decision' => $decision,
        ];

        try {
            $generated = $this->llm->generateAnswerStructure($model, $payload, max(10, (int)($this->config['llm_json_timeout'] ?? 20)));
        } catch (Throwable $error) {
            $generated = null;
            $this->logDiagnostic($requestId, 'deepthink_answer_structure_error', ['error' => $error->getMessage()]);
        }
        if (is_array($generated)) {
            $normalized = $this->normalizeAnswerStructure($generated);
            if ($this->isValidAnswerStructure($normalized)) {
                return $normalized;
            }
        }
        return $this->fallbackAnswerStructure($analysis, $synthesizedEvidence);
    }

    private function isValidAnswerStructure(array $structure): bool
    {
        return in_array(trim((string)($structure['response_mode'] ?? '')), [
            'mechanism_chain',
            'contrastive',
            'literature_support',
            'lineage_explanation',
            'ranking_summary',
            'evidence_summary',
            'declarative',
        ], true)
            && is_array($structure['section_plan'] ?? null)
            && is_array($structure['claim_order'] ?? null)
            && is_array($structure['uncertainty_notes'] ?? null);
    }

    private function normalizeAnswerStructure(array $structure): array
    {
        $normalized = $structure;
        $normalized['response_mode'] = trim((string)($structure['response_mode'] ?? ''));
        $normalized['opening_claim'] = trim((string)($structure['opening_claim'] ?? ''));
        $normalized['citation_policy'] = trim((string)($structure['citation_policy'] ?? ''));
        $normalized['section_plan'] = array_values(array_filter(array_map('strval', (array)($structure['section_plan'] ?? []))));
        $normalized['claim_order'] = array_values(array_filter(array_map('strval', (array)($structure['claim_order'] ?? []))));
        $normalized['uncertainty_notes'] = array_values(array_filter(array_map('strval', (array)($structure['uncertainty_notes'] ?? []))));
        return $normalized;
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
            'section_plan' => ['Main judgment', 'Supporting evidence', 'Evidence gaps and limits'],
            'claim_order' => array_values(array_slice((array)($synthesizedEvidence['supported_claims'] ?? []), 0, 6)),
            'citation_policy' => 'Use PMID-style in-text citations when available.',
            'uncertainty_notes' => array_values(array_slice((array)($synthesizedEvidence['missing_evidence'] ?? []), 0, 4)),
        ];
    }

    private function analysisForWriting(array $analysis): array
    {
        return [
            'intent' => (string)($analysis['intent'] ?? ''),
            'complexity' => (string)($analysis['complexity'] ?? ''),
            'normalized_entities' => array_slice((array)($analysis['normalized_entities'] ?? []), 0, 4),
            'requested_target_types' => array_slice((array)($analysis['requested_target_types'] ?? []), 0, 6),
        ];
    }

    private function limitClaimTexts(array $claims, int $limit): array
    {
        $clean = [];
        foreach ($claims as $claim) {
            $text = trim((string)$claim);
            if ($text !== '') {
                $clean[] = $text;
            }
        }
        return array_values(array_slice(array_unique($clean), 0, $limit));
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

    private function lightweightCitations(array $citations, int $limit): array
    {
        $light = [];
        foreach (array_slice($citations, 0, $limit) as $citation) {
            if (!is_array($citation)) {
                continue;
            }
            $light[] = [
                'pmid' => (string)($citation['pmid'] ?? ''),
                'title' => (string)($citation['title'] ?? ''),
                'journal' => (string)($citation['journal'] ?? $citation['source'] ?? ''),
                'year' => (string)($citation['year'] ?? ''),
                'url' => (string)($citation['url'] ?? ''),
            ];
        }
        return $light;
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
        return array_values(array_unique(array_filter($limits)));
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
        if ($okPlugins >= 2 && count($evidence) >= 2) {
            return 'medium';
        }
        return 'low';
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

    private function inferProvider(string $model): string
    {
        $value = strtolower(trim($model));
        if (str_contains($value, 'qwen')) {
            return 'qwen';
        }
        return 'deepseek';
    }

    private function updateSessionMemory(string $sessionId, array $analysis, array $pluginResults, array $citations): void
    {
        $memory = tekg_agent_load_session_memory($sessionId);
        $memory = array_replace(tekg_agent_default_session_memory(), $memory);
        $memory['topic_entities'] = array_values(array_unique(array_map(
            static fn(array $entity): string => (string)($entity['canonical_label'] ?? $entity['label'] ?? ''),
            (array)($analysis['normalized_entities'] ?? [])
        )));
        $memory['last_intent'] = (string)($analysis['intent'] ?? '');
        $memory['tool_history'] = array_values(array_slice(array_keys($pluginResults), -10));
        $memory['citations'] = array_values(array_slice(array_map(
            static fn(array $citation): string => (string)($citation['pmid'] ?? $citation['title'] ?? ''),
            $citations
        ), 0, 12));
        tekg_agent_save_session_memory($sessionId, $memory);
    }
}
