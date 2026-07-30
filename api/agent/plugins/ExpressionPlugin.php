<?php
declare(strict_types=1);

final class TekgAgentExpressionPlugin implements TekgAgentPluginInterface
{
    public function getName(): string
    {
        return 'Expression Plugin';
    }

    public function run(array $context): array
    {
        $started = microtime(true);
        $entities = tekg_agent_context_resolved_entities($context, 'TE');

        $teNames = [];
        foreach ($entities as $entity) {
            $label = trim((string)($entity['canonical_label'] ?? $entity['label'] ?? ''));
            if ($label !== '') {
                $teNames[] = $label;
            }
        }

        if ($teNames === []) {
            $graphResult = tekg_agent_context_plugin_result($context, 'Graph Plugin');
            $graphRows = (array)(($graphResult['results']['rows'] ?? []) ?: []);
            foreach ($graphRows as $row) {
                $name = trim((string)($row['source_name'] ?? ''));
                if ($name !== '') {
                    $teNames[] = $name;
                }
            }
        }

        $teNames = array_values(array_unique(array_slice($teNames, 0, 5)));
        $results = [];
        $evidence = [];
        $previewItems = [];
        $errors = [];

        foreach ($teNames as $teName) {
            try {
                $bundle = tekg_expression_fetch_detail_bundle($teName, 'median', 'default', 'box');
                if (!is_array($bundle)) {
                    continue;
                }

                $datasetSummaries = [];
                foreach (($bundle['datasets'] ?? []) as $datasetKey => $dataset) {
                    $contexts = is_array($dataset['contexts'] ?? null) ? $dataset['contexts'] : [];
                    $topContext = $contexts[0] ?? null;
                    if (!$topContext) {
                        continue;
                    }
                    $datasetSummaries[] = [
                        'dataset_key' => $datasetKey,
                        'dataset_label' => (string)(($dataset['summary']['dataset_label'] ?? '') ?: $datasetKey),
                        'top_context' => (string)($topContext['context_full_name'] ?? $topContext['context_label'] ?? ''),
                        'median_of_median' => $topContext['median_value'] ?? null,
                        'max_of_max' => $topContext['max_value'] ?? null,
                    ];
                }

                if ($datasetSummaries === []) {
                    continue;
                }

                $results[] = [
                    'te_name' => $bundle['te_name'] ?? $teName,
                    'datasets' => $datasetSummaries,
                ];

                foreach ($datasetSummaries as $summary) {
                    $evidence[] = tekg_agent_make_evidence_item(
                        $this->getName(),
                        $teName . ' has a top expression context in ' . $summary['dataset_key'] . ': ' . $summary['top_context'] . '.',
                        $teName,
                        'medium',
                        [
                            'dataset_key' => $summary['dataset_key'],
                            'dataset_label' => $summary['dataset_label'],
                            'top_context' => $summary['top_context'],
                            'median_of_median' => $summary['median_of_median'],
                            'max_of_max' => $summary['max_of_max'],
                        ],
                        [
                            'title' => $teName,
                            'meta' => $summary['dataset_label'] . ' | ' . $summary['top_context'],
                        ],
                        [
                            'evidence_type' => 'profile_summary',
                            'coverage_dimension' => 'expression',
                            'subject' => $teName,
                            'object' => $summary['top_context'],
                            'provenance' => ['source' => 'expression_runtime'],
                        ]
                    );
                }

                $primary = $datasetSummaries[0];
                $previewItems[] = [
                    'title' => $teName,
                    'meta' => $primary['dataset_label'] . ' | ' . $primary['top_context'],
                ];
            } catch (Throwable $error) {
                $errors[] = $teName . ': ' . $error->getMessage();
            }
        }

        $displaySummary = $results === []
            ? 'The expression database did not add useful context in this round.'
            : 'I also checked the expression datasets and captured the top contexts for the recognized TEs.';
        if ($results === [] && $errors !== []) {
            $displaySummary = 'The expression lookup hit a system error and did not produce usable expression evidence.';
        }

        if ($errors !== []) {
            foreach ($errors as $errorMessage) {
                $evidence[] = tekg_agent_make_evidence_item(
                    $this->getName(),
                    'Expression lookup did not produce usable evidence because a system error occurred.',
                    '',
                    'none',
                    ['error' => $errorMessage],
                    [
                        'title' => 'Expression lookup failed',
                        'meta' => 'system_error',
                        'body' => $errorMessage,
                    ],
                    [
                        'evidence_type' => 'system_error',
                        'coverage_dimension' => 'expression',
                        'provenance' => ['source' => 'expression_runtime'],
                        'diagnostic' => ['error' => $errorMessage],
                        'quality_flags' => ['not_evidence'],
                    ]
                );
            }
        } elseif ($results === []) {
            $evidence[] = tekg_agent_make_evidence_item(
                $this->getName(),
                'Expression lookup returned no usable expression profiles for the recognized entities.',
                '',
                'none',
                [],
                [
                    'title' => 'No expression profiles',
                    'meta' => 'empty',
                ],
                [
                    'evidence_type' => 'empty_result',
                    'coverage_dimension' => 'expression',
                    'provenance' => ['source' => 'expression_runtime'],
                    'diagnostic' => ['reason' => 'no_usable_profile'],
                    'quality_flags' => ['not_evidence'],
                ]
            );
        }

        return [
            'plugin_name' => $this->getName(),
            'status' => tekg_agent_plugin_status($results !== [], $errors),
            'query_summary' => 'Summarized expression datasets and top contexts for the recognized TE entities.',
            'results' => $results,
            'display_label' => 'Summarized ' . count($results) . ' expression profiles',
            'display_summary' => $displaySummary,
            'display_details' => [
                'summary' => $displaySummary,
                'preview_items' => array_slice($previewItems, 0, 5),
                'evidence_items' => $evidence,
                'citations' => [],
                'raw_preview' => $results,
                'result_message' => 'These expression summaries are better used as supporting context than as the core mechanism evidence.',
            ],
            'result_counts' => [
                'profiles' => count($results),
            ],
            'evidence_items' => $evidence,
            'citations' => [],
            'errors' => $errors,
            'latency_ms' => (int)round((microtime(true) - $started) * 1000),
        ];
    }
}
