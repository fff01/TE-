<?php
declare(strict_types=1);

trait TekgAcademicAgentPluginResultTrait
{
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

    private function toolStartMessage(string $pluginName, array $planning): string
    {
        return match ($pluginName) {
            'Entity Resolver' => 'I will resolve canonical entities, strict aliases, and broad alias boundaries first so the downstream tools can avoid unstable name matching.',
            'Graph Plugin' => 'I will start with the local graph and check whether it already contains enough structured relations to support the current task.',
            'Graph Analytics Plugin' => 'I will run a graph analytics query now because this question asks for global structure, ranking, or topology rather than a single local entity neighborhood.',
            'Cypher Explorer Plugin' => 'I will generate a read-only Cypher query to explore graph patterns that are not covered by the fixed neighborhood templates.',
            'Site Navigator Plugin' => 'I will locate the TE-KG page, panel, or dataset entry that best matches the request.',
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
            'Site Navigator Plugin' => 'I will use the site navigator now because the user needs a clickable TE-KG route or panel location.',
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
            } elseif ($pluginName === 'Site Navigator Plugin') {
                $collectedResults['site_navigation_result'] = $result;
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
