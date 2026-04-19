<?php
declare(strict_types=1);

final class TekgAgentLiteratureReadingPlugin implements TekgAgentPluginInterface
{
    public function __construct(
        private readonly TekgAgentLlmClient $llm,
        private readonly array $config,
    ) {
    }

    public function getName(): string
    {
        return 'Literature Reading Plugin';
    }

    public function run(array $context): array
    {
        $started = microtime(true);
        $question = trim((string)($context['question'] ?? ''));
        $analysis = is_array($context['analysis'] ?? null) ? $context['analysis'] : [];
        $literature = is_array(($context['plugin_results']['Literature Plugin'] ?? null)) ? $context['plugin_results']['Literature Plugin'] : [];
        $citations = array_values((array)($literature['citations'] ?? []));
        $selected = array_slice($citations, 0, 12);

        if ($selected === []) {
            return [
                'plugin_name' => $this->getName(),
                'status' => 'empty',
                'query_summary' => 'No literature records were available for synthesis.',
                'results' => [
                    'reviewed_count' => 0,
                    'selected_count' => 0,
                    'claim_clusters' => [],
                    'citation_groups' => [],
                    'supported_claims' => [],
                    'conflicting_claims' => [],
                    'missing_evidence' => ['No literature records were available to synthesize.'],
                ],
                'display_label' => 'Synthesized 0 literature claims',
                'display_summary' => 'No literature records were available for deeper synthesis in this round.',
                'display_details' => [
                    'summary' => 'No literature records were available for deeper synthesis in this round.',
                    'preview_items' => [],
                    'evidence_items' => [],
                    'citations' => [],
                    'raw_preview' => ['selected_citations' => []],
                    'result_message' => 'The literature reading layer had no records to summarize.',
                ],
                'result_counts' => ['reviewed_count' => 0, 'selected_count' => 0, 'claim_clusters' => 0],
                'evidence_items' => [],
                'citations' => [],
                'errors' => [],
                'latency_ms' => (int)round((microtime(true) - $started) * 1000),
            ];
        }

        $model = trim((string)($context['config']['deepseek_model'] ?? $this->config['deepseek_model'] ?? 'deepseek-chat'));
        $synthesis = $this->synthesize($question, $analysis, $selected, $model);
        $claimClusters = array_values((array)($synthesis['claim_clusters'] ?? []));
        $supportedClaims = array_values((array)($synthesis['supported_claims'] ?? []));
        $conflictingClaims = array_values((array)($synthesis['conflicting_claims'] ?? []));
        $missingEvidence = array_values((array)($synthesis['missing_evidence'] ?? []));

        $previewItems = [];
        $evidenceItems = [];
        foreach (array_slice($claimClusters, 0, 5) as $cluster) {
            if (!is_array($cluster)) {
                continue;
            }
            $claim = trim((string)($cluster['claim'] ?? ''));
            if ($claim === '') {
                continue;
            }
            $citCount = count((array)($cluster['citations'] ?? []));
            $previewItems[] = [
                'title' => $claim,
                'meta' => 'citations ' . $citCount,
                'body' => trim((string)($cluster['summary'] ?? '')),
            ];
            $evidenceItems[] = tekg_agent_make_evidence_item(
                $this->getName(),
                $claim,
                $claim,
                $citCount >= 2 ? 'high' : 'medium',
                [
                    'citation_count' => $citCount,
                    'question_type' => (string)($analysis['intent'] ?? 'literature'),
                ],
                [
                    'title' => $claim,
                    'meta' => 'citations ' . $citCount,
                    'body' => trim((string)($cluster['summary'] ?? '')),
                ]
            );
        }

        $summary = 'The literature reading layer reviewed ' . count($citations) . ' normalized citations and synthesized ' . count($claimClusters) . ' claim clusters.';

        return [
            'plugin_name' => $this->getName(),
            'status' => 'ok',
            'query_summary' => 'Synthesized selected literature records into grouped claims and evidence gaps.',
            'results' => [
                'reviewed_count' => count($citations),
                'selected_count' => count($selected),
                'claim_clusters' => $claimClusters,
                'citation_groups' => $this->groupCitations($selected),
                'supported_claims' => $supportedClaims,
                'conflicting_claims' => $conflictingClaims,
                'missing_evidence' => $missingEvidence,
            ],
            'display_label' => 'Synthesized ' . count($claimClusters) . ' literature claims',
            'display_summary' => $summary,
            'display_details' => [
                'summary' => $summary,
                'preview_items' => $previewItems,
                'evidence_items' => $evidenceItems,
                'citations' => $selected,
                'raw_preview' => [
                    'selected_citations' => $selected,
                    'claim_clusters' => $claimClusters,
                    'supported_claims' => $supportedClaims,
                    'conflicting_claims' => $conflictingClaims,
                    'missing_evidence' => $missingEvidence,
                ],
                'result_message' => 'These grouped literature claims can be passed to later evidence synthesis or answer-writing nodes as JSON.',
            ],
            'result_counts' => [
                'reviewed_count' => count($citations),
                'selected_count' => count($selected),
                'claim_clusters' => count($claimClusters),
                'supported_claims' => count($supportedClaims),
                'conflicting_claims' => count($conflictingClaims),
            ],
            'evidence_items' => $evidenceItems,
            'citations' => $selected,
            'errors' => [],
            'latency_ms' => (int)round((microtime(true) - $started) * 1000),
        ];
    }

    private function synthesize(string $question, array $analysis, array $citations, string $model): array
    {
        $payload = [
            'question' => $question,
            'intent' => (string)($analysis['intent'] ?? 'literature'),
            'citations' => array_map(function (array $citation): array {
                return [
                    'title' => (string)($citation['title'] ?? ''),
                    'pmid' => (string)($citation['pmid'] ?? ''),
                    'journal' => (string)($citation['journal'] ?? ''),
                    'year' => (string)($citation['year'] ?? ''),
                    'abstract_summary' => (string)($citation['abstract_summary'] ?? ''),
                    'relevance' => (string)($citation['relevance'] ?? ''),
                ];
            }, array_slice($citations, 0, 10)),
        ];

        $generated = $this->llm->generateJson(
            $model,
            'Group the provided literature citations into JSON fields supported_claims, conflicting_claims, missing_evidence, and claim_clusters. Each claim cluster must include claim, summary, and citations (array of PMID or title strings).',
            $payload
        );

        if (is_array($generated)) {
            return $generated;
        }

        $clusters = [];
        $supported = [];
        foreach (array_slice($citations, 0, 5) as $citation) {
            if (!is_array($citation)) {
                continue;
            }
            $title = trim((string)($citation['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $summary = trim((string)($citation['abstract_summary'] ?? ''));
            $clusters[] = [
                'claim' => $title,
                'summary' => $summary !== '' ? $summary : 'This citation was selected as directly relevant to the current question.',
                'citations' => array_values(array_filter([
                    trim((string)($citation['pmid'] ?? '')),
                    $title,
                ])),
            ];
            $supported[] = $title;
        }

        return [
            'supported_claims' => $supported,
            'conflicting_claims' => [],
            'missing_evidence' => [],
            'claim_clusters' => $clusters,
        ];
    }

    private function groupCitations(array $citations): array
    {
        $groups = [];
        foreach ($citations as $citation) {
            if (!is_array($citation)) {
                continue;
            }
            $source = trim((string)($citation['source'] ?? 'unknown')) ?: 'unknown';
            $groups[$source] = ($groups[$source] ?? 0) + 1;
        }

        $result = [];
        foreach ($groups as $source => $count) {
            $result[] = ['source' => $source, 'count' => $count];
        }
        return $result;
    }
}
