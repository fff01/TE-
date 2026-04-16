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
        $entities = is_array($analysis['normalized_entities'] ?? null) ? $analysis['normalized_entities'] : [];
        $teNames = [];
        foreach ($entities as $entity) {
            if (($entity['type'] ?? '') === 'TE') {
                $teNames[] = (string)$entity['label'];
            }
        }
        if ($teNames === []) {
            $graphResults = $context['plugin_results']['Graph Plugin']['results'] ?? [];
            foreach ((array)$graphResults as $row) {
                $name = trim((string)($row['source_name'] ?? ''));
                if ($name !== '') {
                    $teNames[] = $name;
                }
            }
        }
        $teNames = array_values(array_unique(array_slice($teNames, 0, 5)));
        $results = [];
        $evidence = [];
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
                    $datasetSummaries[] = [
                        'dataset_key' => $datasetKey,
                        'dataset_label' => (string)(($dataset['summary']['dataset_label'] ?? '') ?: $datasetKey),
                        'top_context' => $topContext['context_full_name'] ?? $topContext['context_label'] ?? '',
                        'median_of_median' => $topContext['median_value'] ?? null,
                        'max_of_max' => $topContext['max_value'] ?? null,
                        'available' => $contexts !== [],
                    ];
                }
                $results[] = ['te_name' => $bundle['te_name'] ?? $teName, 'dataset_labels' => array_map(static fn(array $item): string => (string)$item['dataset_label'], $datasetSummaries), 'datasets' => $datasetSummaries];
                foreach ($datasetSummaries as $datasetSummary) {
                    if (!empty($datasetSummary['top_context'])) {
                        $evidence[] = $teName . ' top ' . $datasetSummary['dataset_key'] . ' context: ' . $datasetSummary['top_context'];
                    }
                }
            } catch (Throwable $error) {
                $errors[] = $teName . ': ' . $error->getMessage();
            }
        }
        return [
            'plugin_name' => $this->getName(),
            'status' => $results === [] ? 'empty' : 'ok',
            'query_summary' => 'Summarized expression datasets and top contexts for the recognized TE entities.',
            'results' => $results,
            'evidence_items' => array_values(array_unique($evidence)),
            'citations' => [],
            'errors' => $errors,
            'latency_ms' => (int)round((microtime(true) - $started) * 1000),
        ];
    }
}
