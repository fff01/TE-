<?php
declare(strict_types=1);

if (!function_exists('tekg_agent_create_default_plugins')) {
    require_once dirname(__DIR__) . '/plugin_registry.php';
}

require_once __DIR__ . '/traits/DeepThinkRoutingTrait.php';
require_once __DIR__ . '/traits/DeepThinkEvidenceTrait.php';

final class TekgDeepThinkService
{
    use TekgDeepThinkRoutingTrait;
    use TekgDeepThinkEvidenceTrait;

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
        $processLanguage = $this->resolveProcessLanguage($answerLanguage);

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
        $writingModel = $this->resolveWritingModel($payload, $analysis);

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

        $maxSteps = $this->maxPluginSteps($payload, $analysis);
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
            'outputs_changed' => ['supported_claims', 'answer'],
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

        $writingStartedAt = microtime(true);
        $answer = '';
        $writingFailed = false;
        $failureReason = '';
        $deterministicAnswer = $this->buildDeterministicAnswer($question, $answerLanguage, $analysis, $pluginResults, $citations);
        if ($deterministicAnswer !== null) {
            $answer = $deterministicAnswer['body'];
            $this->logDiagnostic($requestId, 'deepthink_answer_generation_started', [
                'model' => 'deterministic+summary',
                'path' => (string)($deterministicAnswer['path'] ?? 'deterministic'),
                'intent' => (string)($analysis['intent'] ?? 'relationship'),
            ]);
            $summary = $this->writeDeterministicSummary(
                $writingModel,
                $answerLanguage,
                $question,
                $analysis,
                $synthesizedEvidence,
                $citations,
                $confidence,
                $limits,
                (string)($deterministicAnswer['summary_hint'] ?? '')
            );
            if ($summary !== '') {
                $answer .= ($answerLanguage === 'chinese' ? "\n\n简要总结：\n" : "\n\nSummary:\n") . $summary;
            }
        } else {
            $this->logDiagnostic($requestId, 'deepthink_answer_generation_started', [
                'model' => $writingModel,
                'path' => 'direct_answer',
                'intent' => (string)($analysis['intent'] ?? 'relationship'),
            ]);
            try {
                $llm = $this->llm->writeDirectAnswer(
                    $writingModel,
                    $answerLanguage,
                    $question,
                    $this->analysisForWriting($analysis),
                    $this->limitClaimTexts((array)($synthesizedEvidence['supported_claims'] ?? []), 6),
                    $this->limitClaimTexts((array)($synthesizedEvidence['conflicting_claims'] ?? []), 3),
                    $this->limitClaimTexts((array)($synthesizedEvidence['missing_evidence'] ?? []), 4),
                    $this->lightweightCitations($citations, 8),
                    $confidence,
                    $limits,
                    $this->extraWritingContext($question, $analysis, $pluginResults),
                    $this->answerTimeoutForModel($runtimeConfig, $writingModel)
                );
            } catch (Throwable $error) {
                $llm = [
                    'ok' => false,
                    'provider' => $this->inferProvider($writingModel),
                    'model' => $writingModel,
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
            'model' => $writingModel,
            'models' => [
                'reasoner' => $model,
                'narrator' => $narratorModel,
                'writer' => $writingModel,
            ],
            'analysis' => $analysis,
            'answer' => $answer,
            'writing_failed' => $writingFailed,
            'failure_stage' => $writingFailed ? 'Writing' : '',
            'failure_reason' => $failureReason,
            'used_plugins' => array_values(array_keys($pluginResults)),
            'plugin_calls' => array_values($pluginResults),
            'evidence' => $evidence,
            'citations' => $citations,
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

    private function resolveAnswerStructureModel(array $payload, array $analysis): string
    {
        if (trim((string)($payload['answer_structure_model'] ?? '')) !== '') {
            return trim((string)$payload['answer_structure_model']);
        }
        $intent = (string)($analysis['intent'] ?? 'relationship');
        return $this->isSimpleIntent($intent)
            ? trim((string)($this->config['deepseek_model'] ?? 'deepseek-chat'))
            : trim((string)($this->config['deepseek_model'] ?? $this->config['deepseek_reasoner_model'] ?? 'deepseek-chat'));
    }

    private function resolveWritingModel(array $payload, array $analysis): string
    {
        if (trim((string)($payload['writing_model'] ?? '')) !== '') {
            return trim((string)$payload['writing_model']);
        }
        $intent = (string)($analysis['intent'] ?? 'relationship');
        return $this->isSimpleIntent($intent)
            ? trim((string)($this->config['deepseek_model'] ?? 'deepseek-chat'))
            : trim((string)($this->config['deepseek_reasoner_model'] ?? $this->config['deepseek_model'] ?? 'deepseek-reasoner'));
    }

    private function resolveProcessLanguage(string $answerLanguage): string
    {
        return tekg_agent_detect_language(['language' => $answerLanguage], '');
    }

    private function answerTimeoutForModel(array $runtimeConfig, string $model): int
    {
        $provider = $this->inferProvider($model);
        if ($provider === 'deepseek' && stripos($model, 'reasoner') !== false) {
            return max(25, (int)($runtimeConfig['llm_answer_reasoner_timeout'] ?? $runtimeConfig['llm_answer_timeout'] ?? 35));
        }
        return max(12, (int)($runtimeConfig['llm_answer_chat_timeout'] ?? 18));
    }

    private function maxPluginSteps(array $payload, array $analysis): int
    {
        $intent = (string)($analysis['intent'] ?? 'relationship');
        $default = $this->isSimpleIntent($intent) ? 2 : 4;
        return max(1, min(5, (int)($payload['max_plugin_steps'] ?? $default)));
    }

    private function isSimpleIntent(string $intent): bool
    {
        return in_array($intent, ['sequence', 'relationship', 'classification', 'expression', 'genome'], true);
    }

    private function narrateEvent(string $model, string $language, array $event, string $fallback): string
    {
        if ($this->shouldUseDeterministicNarration($event)) {
            return $fallback;
        }
        $narrated = $this->llm->narrateEvent($model, $language, $event);
        return $narrated !== null && trim($narrated) !== '' ? trim($narrated) : $fallback;
    }

    private function shouldUseDeterministicNarration(array $event): bool
    {
        $type = (string)($event['type'] ?? '');
        if ($type === '') {
            return true;
        }

        if (in_array($type, ['tool_selected', 'tool_result', 'reflection', 'synthesizing'], true)) {
            return true;
        }

        if ($type !== 'analysis') {
            return false;
        }

        $intent = (string)($event['intent'] ?? '');
        if ($intent === '' && isset($event['entities'])) {
            return true;
        }

        return $this->isSimpleIntent($intent);
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

    private function formatSequenceCitations(array $citations): string
    {
        $labels = [];
        foreach (array_slice($citations, 0, 3) as $citation) {
            if (!is_array($citation)) {
                continue;
            }
            $pmid = trim((string)($citation['pmid'] ?? ''));
            $title = trim((string)($citation['title'] ?? ''));
            $year = trim((string)($citation['year'] ?? ''));
            if ($pmid !== '') {
                $labels[] = trim('PMID ' . $pmid . ($title !== '' ? ': ' . $title : '') . ($year !== '' ? ' (' . $year . ')' : ''));
                continue;
            }
            $labels[] = trim($title . ($year !== '' ? ' (' . $year . ')' : ''));
        }
        return implode('; ', array_filter($labels));
    }

}
