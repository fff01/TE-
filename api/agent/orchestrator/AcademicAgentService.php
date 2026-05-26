<?php
declare(strict_types=1);

if (!function_exists('tekg_agent_create_default_plugins')) {
    require_once dirname(__DIR__) . '/plugin_registry.php';
}

require_once __DIR__ . '/traits/AcademicAgentWorkflowTrait.php';
require_once __DIR__ . '/traits/AcademicAgentPlanningTrait.php';
require_once __DIR__ . '/traits/AcademicAgentPluginResultTrait.php';
require_once __DIR__ . '/traits/AcademicAgentNarrationTrait.php';
require_once __DIR__ . '/traits/AcademicAgentEvidenceTrait.php';
require_once dirname(__DIR__) . '/contracts/EvidencePackage.php';
require_once dirname(__DIR__) . '/contracts/EvidenceWalk.php';
require_once dirname(__DIR__) . '/contracts/ReportPlan.php';
require_once dirname(__DIR__) . '/contracts/ReportIntegrityGate.php';

final class TekgAcademicAgentService
{
    use TekgAcademicAgentWorkflowTrait;
    use TekgAcademicAgentPlanningTrait;
    use TekgAcademicAgentPluginResultTrait;
    use TekgAcademicAgentNarrationTrait;
    use TekgAcademicAgentEvidenceTrait;

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
        $this->plugins = tekg_agent_create_default_plugins($config, $neo4j, $this->llm, $this->citationResolver);
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
        $startedAt = microtime(true);
        $runtimeConfig = $this->runtimeConfig($payload, $requestId);
        $this->llm = new TekgAgentLlmClient($runtimeConfig);
        $this->applyExecutionBudget($runtimeConfig);
        $this->logDiagnostic($requestId, 'request_started', [
            'question' => $question,
            'mode' => (string)($payload['mode'] ?? 'academic'),
            'execution_timeout' => (int)($runtimeConfig['agent_execution_timeout'] ?? 0),
            'llm_json_timeout' => (int)($runtimeConfig['llm_json_timeout'] ?? 0),
            'llm_answer_timeout' => (int)($runtimeConfig['llm_answer_timeout'] ?? 0),
            'llm_narrator_timeout' => (int)($runtimeConfig['llm_narrator_timeout'] ?? 0),
        ]);

        $answerLanguage = tekg_agent_detect_language($question, trim((string)($payload['language'] ?? 'english')));
        $processLanguage = 'english';
        $coreModel = $this->resolveCoreModel($payload);
        $sufficiencyModel = $this->resolveSufficiencyModel($payload);
        $expertModel = $this->resolveExpertModel($payload);
        $narratorModel = $this->resolveNarratorModel($payload);
        $answerStructureModel = $this->resolveAnswerStructureModel($payload);
        $sessionId = trim((string)($payload['session_id'] ?? ''));
        if ($sessionId === '') {
            $sessionId = tekg_agent_make_session_id();
        }

        $sessionMemory = tekg_agent_load_session_memory($sessionId);
        $analysis = $this->normalizer->analyze($question, $answerLanguage);
        $analysis['answer_language'] = $answerLanguage;
        $analysis['language'] = 'english';
        $analysis['session_memory'] = $sessionMemory;
        $analysis['request_context'] = [
            'source_page' => (string)($payload['source_page'] ?? ''),
            'current_url' => (string)($payload['current_url'] ?? ''),
            'page_context' => (array)($payload['page_context'] ?? []),
            'graph_context' => (array)($payload['graph_context'] ?? []),
        ];

        $planning = $this->buildPlan($question, $analysis, $sessionMemory);
        $routingPolicy = $this->routingPolicyFor($analysis);
        $pluginQueue = $this->initialPluginQueue($analysis, $planning, $routingPolicy);
        $pluginResults = [];
        $pluginCalls = [];
        $reasoningTrace = [];
        $detailCounter = 0;
        $eventSequence = 0;
        $workflowState = $this->initialWorkflowState();
        $collectionState = $this->initialCollectionState($analysis, $planning, $routingPolicy, $pluginQueue);
        $sufficiencyDecision = [
            'is_sufficient' => false,
            'reason' => 'No expert evidence has been collected yet.',
            'missing_dimensions' => array_values((array)($collectionState['active_gaps'] ?? [])),
            'recommended_next_experts' => array_values((array)($collectionState['remaining_candidates'] ?? [])),
        ];

        $this->activateWorkflowStage($workflowState, 'Understanding', null, $emit, $eventSequence, $sessionId);
        $this->emitAnalysisThoughtFlow($emit, $sessionId, $narratorModel, $processLanguage, $analysis, $eventSequence);
        $this->activateWorkflowStage($workflowState, 'Planning', 'Understanding', $emit, $eventSequence, $sessionId);
        $this->emitPlanningThoughtFlow($emit, $sessionId, $narratorModel, $processLanguage, $planning, $eventSequence);
        $this->activateWorkflowStage($workflowState, 'Collecting', 'Planning', $emit, $eventSequence, $sessionId);
        $this->logDiagnostic($requestId, 'planning_completed', [
            'intent' => (string)($analysis['intent'] ?? ''),
            'complexity' => (string)($analysis['complexity'] ?? ''),
            'plugin_queue' => $pluginQueue,
            'knowledge_gaps' => (array)($planning['knowledge_gaps'] ?? []),
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

            if (($workflowState['current_stage'] ?? '') !== 'Collecting') {
                $previousStage = ($workflowState['current_stage'] ?? '') === 'Executing' ? 'Executing' : null;
                $this->activateWorkflowStage($workflowState, 'Collecting', $previousStage, $emit, $eventSequence, $sessionId);
            }
            $this->activateWorkflowStage($workflowState, 'Executing', 'Collecting', $emit, $eventSequence, $sessionId);
            $this->emitEvent($emit, $eventSequence, [
                'type' => 'tool_selected',
                'session_id' => $sessionId,
                'plugin_name' => $pluginName,
                'message' => $this->narrateEvent(
                    $narratorModel,
                    $processLanguage,
                    [
                        'type' => 'tool_selected',
                        'plugin_name' => $pluginName,
                        'planning' => $planning,
                    ],
                    $this->toolSelectedMessage($pluginName, $planning)
                ),
            ]);
            $result = $plugin->run([
                'question' => $question,
                'analysis' => $analysis,
                'plugin_results' => $pluginResults,
                'planning' => $planning,
                'config' => $this->expertConfig($expertModel),
            ]);
            $result = $this->augmentPluginResult($pluginName, $result, $analysis, $planning);
            $this->logDiagnostic($requestId, 'plugin_completed', [
                'plugin_name' => $pluginName,
                'status' => (string)($result['status'] ?? 'unknown'),
                'result_counts' => (array)($result['result_counts'] ?? []),
                'latency_ms' => (int)($result['latency_ms'] ?? 0),
            ]);

            $pluginResults[$pluginName] = $result;
            $pluginCalls[] = $result;
            $collectionState = $this->updateCollectionState($collectionState, $pluginName, $result);

            $detailId = 'tool-' . (++$detailCounter);
            $payloadForUi = $this->toolPayloadForUi($result);

            $this->emitEvent($emit, $eventSequence, [
                'type' => 'tool_result',
                'session_id' => $sessionId,
                'plugin_name' => $pluginName,
                'display_label' => (string)($result['display_label'] ?? $pluginName),
                'summary' => (string)($result['display_summary'] ?? $result['query_summary'] ?? ''),
                'message' => $this->narrateEvent(
                    $narratorModel,
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
                $sufficiencyModel,
                $question,
                $analysis,
                $planning,
                $pluginResults,
                $collectionState,
                $routingPolicy
            );
            $collectionState['sufficiency_decision'] = $sufficiencyDecision;
            $this->logDiagnostic($requestId, 'sufficiency_evaluated', [
                'plugin_name' => $pluginName,
                'is_sufficient' => (bool)($sufficiencyDecision['is_sufficient'] ?? false),
                'reason' => (string)($sufficiencyDecision['reason'] ?? ''),
                'recommended_next_experts' => (array)($sufficiencyDecision['recommended_next_experts'] ?? []),
            ]);
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
                        $narratorModel,
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

            if (($sufficiencyDecision['is_sufficient'] ?? false) !== true
                && (
                    array_values((array)($sufficiencyDecision['recommended_next_experts'] ?? [])) !== []
                    || array_slice($pluginQueue, $index + 1) !== []
                )
            ) {
                $this->activateWorkflowStage($workflowState, 'Collecting', 'Executing', $emit, $eventSequence, $sessionId);
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
                if (($workflowState['current_stage'] ?? '') !== 'Executing') {
                    $fromStage = ($workflowState['current_stage'] ?? '') === 'Collecting' ? 'Collecting' : null;
                    $this->activateWorkflowStage($workflowState, 'Executing', $fromStage, $emit, $eventSequence, $sessionId);
                }
                $this->emitEvent($emit, $eventSequence, [
                    'type' => 'tool_selected',
                    'session_id' => $sessionId,
                    'plugin_name' => 'Citation Resolver',
                    'message' => $this->narrateEvent(
                        $narratorModel,
                        $processLanguage,
                        [
                            'type' => 'tool_selected',
                            'plugin_name' => 'Citation Resolver',
                            'planning' => $planning,
                        ],
                        $this->toolSelectedMessage('Citation Resolver', $planning)
                    ),
                ]);
                $citationResult = $citationPlugin->run([
                    'question' => $question,
                    'analysis' => $analysis,
                    'plugin_results' => $pluginResults,
                    'planning' => $planning,
                    'config' => $this->expertConfig($expertModel),
                ]);
                $citationResult = $this->augmentPluginResult('Citation Resolver', $citationResult, $analysis, $planning);

                $pluginResults['Citation Resolver'] = $citationResult;
                $pluginCalls[] = $citationResult;
                $detailId = 'tool-' . (++$detailCounter);
                $payloadForUi = $this->toolPayloadForUi($citationResult);

                $this->emitEvent($emit, $eventSequence, [
                    'type' => 'tool_result',
                    'session_id' => $sessionId,
                    'plugin_name' => 'Citation Resolver',
                    'display_label' => (string)($citationResult['display_label'] ?? 'Citation Resolver'),
                    'summary' => (string)($citationResult['display_summary'] ?? $citationResult['query_summary'] ?? ''),
                    'message' => $this->narrateEvent(
                        $narratorModel,
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
        $writingModel = $this->resolveWritingModel($analysis, $payload, $pluginResults);
        $polisherModel = $this->resolvePolisherModel($payload, $writingModel);
        $this->activateWorkflowStage($workflowState, 'Integrating', (string)($workflowState['current_stage'] ?? 'Executing'), $emit, $eventSequence, $sessionId);
        $evidencePackage = $this->buildValidatedEvidencePackage($question, $analysis, $pluginResults, $requestId);
        $evidenceWalk = EvidenceWalk::fromEvidencePackage($evidencePackage, $analysis, $planning, $sufficiencyDecision);
        $evidenceWalkValidation = EvidenceWalk::validate($evidenceWalk);
        $synthesizedEvidence = $this->buildSynthesizedEvidenceFromPackage($evidencePackage);
        $answerStructureStartedAt = microtime(true);
        $this->logDiagnostic($requestId, 'answer_structure_started', [
            'model' => $answerStructureModel,
            'supported_claim_count' => count((array)($synthesizedEvidence['supported_claims'] ?? [])),
            'citation_count' => count($citations),
        ]);
        $answerStructure = $this->generateAnswerStructure(
            $answerStructureModel,
            $question,
            $analysis,
            $synthesizedEvidence,
            $this->citationsFromEvidencePackage($evidencePackage),
            $sufficiencyDecision,
            $requestId
        );
        $answerStructureDurationMs = (int)round((microtime(true) - $answerStructureStartedAt) * 1000);
        $this->logDiagnostic($requestId, 'answer_structure_completed', [
            'response_mode' => (string)($answerStructure['response_mode'] ?? ''),
            'section_count' => count((array)($answerStructure['section_plan'] ?? [])),
            'duration_ms' => $answerStructureDurationMs,
        ]);
        $reportPlan = ReportPlan::fromEvidenceWalk($question, $analysis, $evidenceWalk, $answerStructure);
        $reportPlanValidation = ReportPlan::validate($reportPlan);

        $synthesizingMessage = $this->synthesizingMessage($planning, $pluginResults, $evidence);
        $this->emitEvent($emit, $eventSequence, [
            'type' => 'synthesizing',
            'session_id' => $sessionId,
            'node' => 'Evidence Synthesis Node',
            'source' => 'Evidence Synthesis Node',
            'inputs_used' => ['result_envelopes', 'evidence_package'],
            'outputs_changed' => ['evidence_package', 'evidence_walk', 'report_plan', 'supported_claims', 'conflicting_claims', 'missing_evidence', 'claim_clusters', 'answer_structure'],
            'message' => $this->narrateEvent(
                $narratorModel,
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
                'evidence_package' => $evidencePackage,
                'evidence_walk' => $evidenceWalk,
                'report_plan' => $reportPlan,
                'synthesized_evidence' => $synthesizedEvidence,
                'answer_structure' => $answerStructure,
            ],
        ]);
        $this->activateWorkflowStage($workflowState, 'Writing', 'Integrating', $emit, $eventSequence, $sessionId);

        $analysisForWriting = $this->analysisForWriting($analysis);
        $this->logDiagnostic($requestId, 'answer_generation_started', [
            'model' => $writingModel,
            'polisher_model' => $polisherModel,
            'response_mode' => (string)($answerStructure['response_mode'] ?? ''),
            'citation_count' => count($citations),
            'confidence' => $confidence,
        ]);
        $answer = '';
        $draftReport = '';
        $polishedReport = '';
        $draftLlm = [
            'ok' => false,
            'provider' => $this->inferProvider($writingModel),
            'model' => $writingModel,
            'content' => '',
            'error' => null,
        ];
        $polishLlm = [
            'ok' => false,
            'provider' => $this->inferProvider($polisherModel),
            'model' => $polisherModel,
            'content' => '',
            'error' => null,
        ];
        $integrityReport = [
            'evidence_walk_validation' => $evidenceWalkValidation,
            'report_plan_validation' => $reportPlanValidation,
            'draft' => null,
            'polish' => null,
            'warnings' => [],
        ];
        $writingFailed = false;
        $failureReason = '';
        $writingStartedAt = microtime(true);

        if (($evidenceWalkValidation['ok'] ?? false) !== true || ($reportPlanValidation['ok'] ?? false) !== true) {
            $writingFailed = true;
            $failureReason = 'EvidenceWalk or ReportPlan validation failed before writing.';
        }

        if (!$writingFailed) {
            try {
                $draftLlm = $this->llm->writeEvidenceWalkDraft(
                    $writingModel,
                    $answerLanguage,
                    $question,
                    $analysisForWriting,
                    $evidencePackage,
                    $evidenceWalk,
                    $reportPlan,
                    $confidence,
                    $limits,
                    $this->answerTimeoutForModel($writingModel)
                );
            } catch (Throwable $error) {
                $draftLlm = [
                    'ok' => false,
                    'provider' => $this->inferProvider($writingModel),
                    'model' => $writingModel,
                    'content' => '',
                    'error' => $error->getMessage(),
                ];
                $this->logDiagnostic($requestId, 'answer_generation_error', [
                    'stage' => 'draft',
                    'error' => $error->getMessage(),
                ]);
            }

            if (($draftLlm['ok'] ?? false) === true) {
                $draftReport = trim((string)($draftLlm['content'] ?? ''));
            }
            if ($draftReport === '') {
                $writingFailed = true;
                $failureReason = trim((string)($draftLlm['error'] ?? 'The evidence-walk draft writer did not return usable content.'));
            } else {
                $draftIntegrity = ReportIntegrityGate::check($draftReport, $evidencePackage, $evidenceWalk, $reportPlan);
                $integrityReport['draft'] = $draftIntegrity;
                if (($draftIntegrity['ok'] ?? false) !== true) {
                    $writingFailed = true;
                    $failureReason = 'The evidence-walk draft failed integrity checks: ' . implode('; ', (array)($draftIntegrity['errors'] ?? []));
                }
            }
        }

        if (!$writingFailed) {
            try {
                $polishLlm = $this->llm->polishEvidenceWalkAnswer(
                    $polisherModel,
                    $answerLanguage,
                    $draftReport,
                    $analysisForWriting,
                    $evidencePackage,
                    $evidenceWalk,
                    $reportPlan,
                    (array)$integrityReport['draft'],
                    $this->answerTimeoutForModel($polisherModel)
                );
            } catch (Throwable $error) {
                $polishLlm = [
                    'ok' => false,
                    'provider' => $this->inferProvider($polisherModel),
                    'model' => $polisherModel,
                    'content' => '',
                    'error' => $error->getMessage(),
                ];
                $this->logDiagnostic($requestId, 'answer_generation_error', [
                    'stage' => 'polish',
                    'error' => $error->getMessage(),
                ]);
            }

            if (($polishLlm['ok'] ?? false) === true) {
                $polishedReport = trim((string)($polishLlm['content'] ?? ''));
            }
            if ($polishedReport === '') {
                $writingFailed = true;
                $failureReason = trim((string)($polishLlm['error'] ?? 'The evidence-walk polisher did not return usable content.'));
            } else {
                $polishIntegrity = ReportIntegrityGate::check($polishedReport, $evidencePackage, $evidenceWalk, $reportPlan);
                $integrityReport['polish'] = $polishIntegrity;
                if (($polishIntegrity['ok'] ?? false) === true) {
                    $answer = $polishedReport;
                } else {
                    $answer = $draftReport;
                    $integrityReport['warnings'][] = 'Polished report failed integrity checks; using the validated draft report as the conservative answer.';
                }
            }
        }
        if (!$writingFailed && $answer === '') {
            $writingFailed = true;
            $failureReason = 'The final writing node did not produce a validated answer.';
        }
        $this->logDiagnostic($requestId, 'answer_generation_completed', [
            'draft_ok' => (bool)($draftLlm['ok'] ?? false),
            'polish_ok' => (bool)($polishLlm['ok'] ?? false),
            'draft_integrity_ok' => (bool)($integrityReport['draft']['ok'] ?? false),
            'polish_integrity_ok' => (bool)($integrityReport['polish']['ok'] ?? false),
            'writing_failed' => $writingFailed,
            'writing_duration_ms' => (int)round((microtime(true) - $writingStartedAt) * 1000),
            'answer_length' => tekg_agent_strlen($answer),
        ]);

        $workflowState['stage_statuses']['Writing'] = 'done';
        $workflowState['current_stage'] = 'Writing';
        $workflowState['complete'] = true;

        $reasoningTrace[] = [
            'step' => 'synthesizing',
            'title' => 'Synthesis',
            'status' => $writingFailed ? 'failed' : 'done',
            'details' => $synthesizingMessage,
        ];

        $response = [
            'question' => $question,
            'mode' => trim((string)($payload['mode'] ?? 'academic')) ?: 'academic',
            'request_id' => $requestId,
            'language' => $answerLanguage,
            'session_id' => $sessionId,
            'model' => $writingModel,
            'model_provider' => $this->inferProvider($answer === $polishedReport && $polishedReport !== '' ? $polisherModel : $writingModel),
            'models' => [
                'core' => $coreModel,
                'sufficiency' => $sufficiencyModel,
                'expert' => $expertModel,
                'narrator' => $narratorModel,
                'answer_structure' => $answerStructureModel,
                'writer' => $writingModel,
                'writer_draft' => $writingModel,
                'writer_polisher' => $polisherModel,
            ],
            'analysis' => $analysis,
            'answer' => $answer,
            'writing_failed' => $writingFailed,
            'failure_stage' => $writingFailed ? 'Writing' : '',
            'failure_reason' => $failureReason,
            'reasoning_trace' => $reasoningTrace,
            'used_plugins' => array_map(static fn(array $call): string => (string)($call['plugin_name'] ?? ''), $pluginCalls),
            'plugin_calls' => $pluginCalls,
            'evidence' => $evidence,
            'citations' => $citations,
            'evidence_package' => $evidencePackage,
            'evidence_walk' => $evidenceWalk,
            'report_plan' => $reportPlan,
            'draft_report' => $draftReport,
            'polished_report' => $polishedReport,
            'integrity_report' => $integrityReport,
            'confidence' => $confidence,
            'limits' => array_values(array_unique($limits)),
            'planning' => $planning,
            'collection_state' => $collectionState,
            'sufficiency_decision' => $sufficiencyDecision,
            'answer_structure' => $answerStructure,
            'synthesized_evidence' => $synthesizedEvidence,
            'timings' => [
                'answer_structure_ms' => $answerStructureDurationMs,
                'writing_ms' => (int)round((microtime(true) - $writingStartedAt) * 1000),
            ],
            'workflow_state' => $workflowState,
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
                $synthesizedEvidence,
                $evidencePackage,
                $evidenceWalk,
                $reportPlan,
                $draftReport,
                $polishedReport,
                $integrityReport,
                $answer
            )),
        ];

        $updatedMemory = $this->updateSessionMemory($sessionMemory, $analysis, $planning, $pluginResults, $citations, $evidence, $collectionState, $synthesizedEvidence);
        tekg_agent_save_session_memory($sessionId, $updatedMemory);

        $this->completeWorkflowStage($workflowState, 'Writing', $emit, $eventSequence, $sessionId);
        $this->logDiagnostic($requestId, 'answer_event_emitting', [
            'answer_length' => tekg_agent_strlen($answer),
            'writing_failed' => $writingFailed,
            'workflow_complete' => true,
        ]);
        if (!$writingFailed) {
            $this->emitEvent($emit, $eventSequence, [
                'type' => 'answer',
                'request_id' => $requestId,
                'session_id' => $sessionId,
                'language' => $answerLanguage,
                'message' => $answer,
            ]);
        } else {
            $this->emitEvent($emit, $eventSequence, [
                'type' => 'error',
                'request_id' => $requestId,
                'session_id' => $sessionId,
                'node' => 'Answer Writer Node',
                'source' => 'Answer Writer Node',
                'message' => 'The final writing node failed, so no academic answer was emitted for this run.',
                'payload' => [
                    'writing_failed' => true,
                    'failure_stage' => 'Writing',
                    'failure_reason' => $failureReason,
                ],
            ]);
        }
        $this->emitEvent($emit, $eventSequence, [
            'type' => 'done',
            'request_id' => $requestId,
            'session_id' => $sessionId,
            'payload' => [
                'confidence' => $confidence,
                'used_plugins' => $response['used_plugins'],
                'answer' => $answer,
                'language' => $answerLanguage,
                'writing_failed' => $writingFailed,
                'failure_stage' => $writingFailed ? 'Writing' : '',
                'failure_reason' => $failureReason,
                'workflow_state' => $workflowState,
            ],
        ]);
        $this->logDiagnostic($requestId, 'request_completed', [
            'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            'used_plugins' => $response['used_plugins'],
            'answer_length' => tekg_agent_strlen($answer),
        ]);

        return $response;
    }

    private function runtimeConfig(array $payload, string $requestId): array
    {
        $config = $this->config;
        $config['request_id'] = $requestId;

        $executionTimeout = (int)($payload['execution_timeout'] ?? $config['agent_execution_timeout'] ?? 300);
        $config['agent_execution_timeout'] = max(90, $executionTimeout);
        $config['llm_narrator_timeout'] = max(4, (int)($config['llm_narrator_timeout'] ?? 6));
        $config['llm_json_timeout'] = max(10, (int)($config['llm_json_timeout'] ?? 15));
        $config['llm_answer_timeout'] = max(15, (int)($config['llm_answer_timeout'] ?? 20));
        $config['llm_answer_chat_timeout'] = max(15, (int)($config['llm_answer_chat_timeout'] ?? 18));
        $config['llm_answer_reasoner_timeout'] = max(25, (int)($config['llm_answer_reasoner_timeout'] ?? 35));

        if (isset($payload['llm_json_timeout'])) {
            $config['llm_json_timeout'] = max(5, (int)$payload['llm_json_timeout']);
        }
        if (isset($payload['llm_answer_timeout'])) {
            $config['llm_answer_timeout'] = max(5, (int)$payload['llm_answer_timeout']);
        }
        if (isset($payload['llm_answer_chat_timeout'])) {
            $config['llm_answer_chat_timeout'] = max(5, (int)$payload['llm_answer_chat_timeout']);
        }
        if (isset($payload['llm_answer_reasoner_timeout'])) {
            $config['llm_answer_reasoner_timeout'] = max(5, (int)$payload['llm_answer_reasoner_timeout']);
        }
        if (isset($payload['llm_narrator_timeout'])) {
            $config['llm_narrator_timeout'] = max(2, (int)$payload['llm_narrator_timeout']);
        }

        return $config;
    }

    private function applyExecutionBudget(array $config): void
    {
        $timeout = max(60, (int)($config['agent_execution_timeout'] ?? 240));
        @ini_set('max_execution_time', (string)$timeout);
        if (function_exists('set_time_limit')) {
            @set_time_limit($timeout);
        }
    }

    private function logDiagnostic(string $requestId, string $event, array $payload = []): void
    {
        tekg_agent_append_diagnostic_log($requestId, $event, $payload);
    }

    private function resolveCoreModel(array $payload): string
    {
        return trim((string)($payload['model'] ?? $this->config['deepseek_reasoner_model'] ?? $this->config['deepseek_model'] ?? 'deepseek-reasoner'));
    }

    private function resolveSufficiencyModel(array $payload): string
    {
        return trim((string)($payload['sufficiency_model'] ?? $this->resolveCoreModel($payload)));
    }

    private function resolveExpertModel(array $payload): string
    {
        return trim((string)($payload['expert_model'] ?? $this->config['deepseek_model'] ?? 'deepseek-chat'));
    }

    private function resolveNarratorModel(array $payload): string
    {
        return trim((string)($payload['narrator_model'] ?? $this->config['deepseek_model'] ?? 'deepseek-chat'));
    }

    private function resolveAnswerStructureModel(array $payload): string
    {
        return trim((string)($payload['answer_structure_model'] ?? $this->config['deepseek_model'] ?? 'deepseek-chat'));
    }

    private function resolveWritingModel(array $analysis, array $payload, array $pluginResults): string
    {
        if (trim((string)($payload['writing_model'] ?? '')) !== '') {
            return trim((string)$payload['writing_model']);
        }

        if (trim((string)($this->config['agent_writing_model'] ?? '')) !== '') {
            return trim((string)($this->config['agent_writing_model'] ?? ''));
        }

        $intent = (string)($analysis['intent'] ?? 'relationship');
        $reasonerIntents = ['mechanism', 'comparison', 'graph_analytics'];
        if (in_array($intent, $reasonerIntents, true) || isset($pluginResults['Cypher Explorer Plugin'])) {
            return trim((string)($this->config['deepseek_reasoner_model'] ?? $this->config['deepseek_model'] ?? 'deepseek-reasoner'));
        }

        return trim((string)($this->config['deepseek_model'] ?? 'deepseek-chat'));
    }

    private function resolvePolisherModel(array $payload, string $writingModel): string
    {
        if (trim((string)($payload['polisher_model'] ?? '')) !== '') {
            return trim((string)$payload['polisher_model']);
        }

        if (trim((string)($this->config['agent_polisher_model'] ?? '')) !== '') {
            return trim((string)($this->config['agent_polisher_model'] ?? ''));
        }

        return $writingModel;
    }

    private function answerTimeoutForModel(string $model): int
    {
        $provider = $this->inferProvider($model);
        if ($provider === 'deepseek' && stripos($model, 'reasoner') !== false) {
            return max(25, (int)($this->config['llm_answer_reasoner_timeout'] ?? 35));
        }
        return max(15, (int)($this->config['llm_answer_chat_timeout'] ?? 18));
    }

    private function expertConfig(string $expertModel): array
    {
        $config = $this->config;
        $config['deepseek_model'] = $expertModel;
        return $config;
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

}
