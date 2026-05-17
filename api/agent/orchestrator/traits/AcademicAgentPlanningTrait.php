<?php
declare(strict_types=1);

trait TekgAcademicAgentPlanningTrait
{
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

        if (($analysis['asks_for_site_navigation'] ?? false)) {
            $gaps[] = [
                'gap_type' => 'site navigation',
                'why_needed' => 'The user is asking where a TE-KG page, panel, route, or dataset entry can be opened.',
                'priority' => 94,
                'candidate_tools' => ['Site Navigator Plugin'],
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
            'Site Navigator Plugin' => 15,
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
        return $this->normalizeRoutingPolicy($selected, $intent);
    }

    private function initialPluginQueue(array $analysis, array $planning, array $routingPolicy): array
    {
        $queue = array_values(array_filter(array_map('strval', (array)($routingPolicy['primary_path'] ?? []))));
        if ($queue === []) {
            $queue = array_values(array_filter(array_map('strval', (array)($routingPolicy['candidate_experts'] ?? []))));
        }
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
        if (($analysis['asks_for_site_navigation'] ?? false) && !in_array('Site Navigator Plugin', $queue, true)) {
            array_splice($queue, in_array('Entity Resolver', $queue, true) ? 1 : 0, 0, ['Site Navigator Plugin']);
        }
        $forbidden = array_values(array_filter(array_map('strval', (array)($routingPolicy['forbidden_path'] ?? []))));
        $queue = array_values(array_unique(array_filter($queue, static fn(string $plugin): bool => $plugin !== '' && !in_array($plugin, $forbidden, true))));
        return $queue;
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
}
