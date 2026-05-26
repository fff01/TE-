<?php
declare(strict_types=1);

trait TekgAcademicAgentNarrationTrait
{
    private function emitAnalysisThoughtFlow(?callable $emit, string $sessionId, string $model, string $processLanguage, array $analysis, int &$eventSequence): void
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

        if (($analysis['recommended_mode'] ?? '') === 'deepthink') {
            $analysisLines[] = $this->narrateEvent(
                $model,
                $processLanguage,
                [
                    'type' => 'analysis',
                    'focus' => 'task_boundary',
                    'intent' => $analysis['intent'] ?? '',
                    'complexity' => $analysis['complexity'] ?? '',
                    'task_complexity' => $analysis['task_complexity'] ?? '',
                    'recommended_mode' => $analysis['recommended_mode'] ?? '',
                    'task_complexity_reason' => $analysis['task_complexity_reason'] ?? '',
                ],
                'This is a quick lookup; Deep Think is usually faster. If you continue with Agent, I will collect evidence through the research workflow.'
            );
        }

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
                    'task_complexity' => $analysis['task_complexity'] ?? '',
                    'recommended_mode' => $analysis['recommended_mode'] ?? '',
                    'task_complexity_reason' => $analysis['task_complexity_reason'] ?? '',
                    'normalized_entities' => $analysis['normalized_entities'] ?? [],
                ],
            ]);
        }
    }

    private function emitPlanningThoughtFlow(?callable $emit, string $sessionId, string $model, string $processLanguage, array $planning, int &$eventSequence): void
    {
        foreach (array_slice((array)($planning['knowledge_gaps'] ?? []), 0, 2) as $gap) {
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

        foreach (array_slice((array)($planning['subtasks'] ?? []), 0, 3) as $subtask) {
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
        return in_array($type, ['analysis', 'planning_step', 'tool_selected', 'tool_result', 'reflection', 'synthesizing'], true);
    }
}
