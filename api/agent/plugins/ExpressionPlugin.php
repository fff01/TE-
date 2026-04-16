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
        $analysis = $context['analysis'] ?? [];
        $language = (string)($analysis['language'] ?? 'en');
        $entities = is_array($analysis['normalized_entities'] ?? null) ? $analysis['normalized_entities'] : [];

        $teNames = [];
        foreach ($entities as $entity) {
            if (($entity['type'] ?? '') === 'TE') {
                $teNames[] = (string)$entity['label'];
            }
        }

        if ($teNames === []) {
            $graphRows = (array)(($context['plugin_results']['Graph Plugin']['results']['rows'] ?? []) ?: []);
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
                    $evidence[] = $teName . ' top ' . $summary['dataset_key'] . ' context: ' . $summary['top_context'];
                }

                $primary = $datasetSummaries[0];
                $previewItems[] = [
                    'title' => $teName,
                    'meta' => $primary['dataset_label'] . ' · ' . $primary['top_context'],
                ];
            } catch (Throwable $error) {
                $errors[] = $teName . ': ' . $error->getMessage();
            }
        }

        $displaySummary = $language === 'zh'
            ? ($results === []
                ? '表达数据库这一轮没有给出足够有用的补充信息。'
                : '我补充查看了表达数据，主要记录了这些 TE 在不同数据集中的高表达 context。')
            : ($results === []
                ? 'The expression database did not add useful context in this round.'
                : 'I also checked the expression datasets and captured the top contexts for the recognized TEs.');

        return [
            'plugin_name' => $this->getName(),
            'status' => $results === [] && $errors === [] ? 'empty' : ($errors === [] ? 'ok' : 'partial'),
            'query_summary' => 'Summarized expression datasets and top contexts for the recognized TE entities.',
            'results' => $results,
            'display_label' => $language === 'zh'
                ? '整理了 ' . count($results) . ' 个表达摘要'
                : 'Summarized ' . count($results) . ' expression profiles',
            'display_summary' => $displaySummary,
            'display_details' => [
                'summary' => $displaySummary,
                'preview_items' => array_slice($previewItems, 0, 5),
                'evidence_items' => $evidence,
                'citations' => [],
                'raw_preview' => $results,
                'result_message' => $language === 'zh'
                    ? '这些表达结果更适合作为背景补充，而不是直接回答机制问题。'
                    : 'These expression summaries are better used as supporting context than as the core mechanism evidence.',
            ],
            'result_counts' => [
                'profiles' => count($results),
            ],
            'evidence_items' => array_values(array_unique($evidence)),
            'citations' => [],
            'errors' => $errors,
            'latency_ms' => (int)round((microtime(true) - $started) * 1000),
        ];
    }
}
