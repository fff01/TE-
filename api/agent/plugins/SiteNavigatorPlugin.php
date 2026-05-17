<?php
declare(strict_types=1);

final class TekgAgentSiteNavigatorPlugin implements TekgAgentPluginInterface
{
    public function getName(): string
    {
        return 'Site Navigator Plugin';
    }

    public function run(array $context): array
    {
        $question = trim((string)($context['question'] ?? ''));
        $analysis = (array)($context['analysis'] ?? []);
        $requestContext = (array)($analysis['request_context'] ?? []);
        $entity = $this->resolveEntity($analysis, $question);
        $language = tekg_agent_detect_language($question, (string)($analysis['answer_language'] ?? $analysis['language'] ?? ''));

        $routes = $this->buildRoutes($entity, (string)($requestContext['current_url'] ?? $context['current_url'] ?? ''));
        $capability = $this->detectCapability($question, $analysis);
        $candidateKeys = $this->candidateKeys($capability, $analysis);
        $candidateRoutes = [];
        foreach ($candidateKeys as $key) {
            if (isset($routes[$key])) {
                $candidateRoutes[] = $routes[$key];
            }
        }
        if ($candidateRoutes === []) {
            $candidateRoutes = array_values(array_slice($routes, 0, 5));
        }

        $primary = $candidateRoutes[0] ?? null;
        if ($primary === null) {
            return [
                'plugin_name' => $this->getName(),
                'status' => 'empty',
                'display_summary' => 'No TE-KG site route could be selected.',
                'results' => [],
                'result_counts' => ['routes' => 0],
            ];
        }

        $confidence = $this->confidenceFor($capability, $question, $analysis);
        $answerMarkdown = $this->buildAnswerMarkdown($primary, array_slice($candidateRoutes, 1, 5), $entity, $language, $confidence);
        $claim = sprintf(
            'TE-KG site navigation route for %s: [%s](%s).',
            $entity !== '' ? $entity : 'the requested item',
            (string)$primary['title'],
            (string)$primary['url']
        );
        $evidence = tekg_agent_make_evidence_item(
            $this->getName(),
            $claim,
            $entity,
            $confidence >= 0.75 ? 'high' : 'medium',
            $primary,
            [
                'title' => (string)$primary['title'],
                'meta' => $capability,
                'body' => $claim,
            ]
        );

        return [
            'plugin_name' => $this->getName(),
            'status' => 'ok',
            'display_summary' => sprintf('Matched the question to the %s site route.', (string)$primary['title']),
            'display_details' => [
                'result_message' => $answerMarkdown,
                'preview_items' => array_map(static fn(array $route): array => [
                    'title' => (string)$route['title'],
                    'body' => (string)$route['description'],
                    'url' => (string)$route['url'],
                    'meta' => (string)$route['capability'],
                ], $candidateRoutes),
                'evidence_items' => [$evidence],
            ],
            'results' => [
                'primary_route' => $primary,
                'candidate_routes' => $candidateRoutes,
                'answer_markdown' => $answerMarkdown,
                'confidence' => $confidence,
                'matched_entity' => $entity,
                'matched_capability' => $capability,
            ],
            'evidence_items' => [$evidence],
            'result_counts' => [
                'routes' => count($candidateRoutes),
                'primary_confidence_percent' => (int)round($confidence * 100),
            ],
        ];
    }

    private function resolveEntity(array $analysis, string $question): string
    {
        foreach ((array)($analysis['normalized_entities'] ?? []) as $entity) {
            if (!is_array($entity)) {
                continue;
            }
            if (strtolower((string)($entity['type'] ?? '')) !== 'te') {
                continue;
            }
            $label = trim((string)($entity['label'] ?? $entity['canonical'] ?? $entity['name'] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }

        if (preg_match('/\b([A-Z][A-Z0-9_-]{1,24})\b/u', $question, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function buildRoutes(string $entity, string $currentUrl): array
    {
        $teParams = $entity !== '' ? ['q' => $entity, 'type' => 'TE'] : ['type' => 'TE'];
        $exprParams = $entity !== '' ? ['te' => $entity] : [];
        $previewParams = $entity !== '' ? ['q' => $entity] : [];
        $jbrowseParams = $entity !== '' ? ['te' => $entity] : [];

        return [
            'search_summary' => $this->route('Search summary', 'search_summary', 'search.php', $teParams, 'search-summary-panel', 'Overview card for the selected TE.', $currentUrl),
            'local_graph' => $this->route('Local graph', 'local_graph', 'search.php', $teParams, 'search-graph-panel', 'Local relationship graph around the selected TE.', $currentUrl),
            'sequence' => $this->route('Sequence', 'sequence', 'search.php', $teParams, 'search-sequence-panel', 'Consensus sequence and sequence metadata.', $currentUrl),
            'genome_distribution' => $this->route('Genome Annotation Distribution', 'genome_distribution', 'search.php', $teParams, 'search-karyotype-panel', 'Genome annotation distribution panel.', $currentUrl),
            'genome_browser' => $this->route('Genome Browser', 'genome_browser', 'search.php', $teParams, 'search-jbrowse-panel', 'Embedded Genome Browser entry from the search detail page.', $currentUrl),
            'jbrowse_direct' => $this->route('JBrowse direct entry', 'genome_browser', 'jbrowse.php', $jbrowseParams, '', 'Direct genome browser page.', $currentUrl),
            'expression_summary' => $this->route('Expression detail summary', 'expression', 'expression_detail.php', $exprParams, 'expression-detail-summary', 'Expression detail overview for the selected TE.', $currentUrl),
            'expression_normal_tissue' => $this->route('Normal tissue expression', 'expression', 'expression_detail.php', $exprParams, 'expression-detail-normal-tissue', 'Normal tissue expression panel.', $currentUrl),
            'expression_normal_cell_line' => $this->route('Normal cell line expression', 'expression', 'expression_detail.php', $exprParams, 'expression-detail-normal-cell-line', 'Normal cell line expression panel.', $currentUrl),
            'expression_cancer_cell_line' => $this->route('Cancer cell line expression', 'expression', 'expression_detail.php', $exprParams, 'expression-detail-cancer-cell-line', 'Cancer cell line expression panel.', $currentUrl),
            'browse_catalog' => $this->route('Browse catalog', 'browse', 'browse.php', [], '', 'Catalog page for TE browsing and filtering.', $currentUrl),
            'preview_graph' => $this->route('TE-KG graph', 'graph', 'preview.php', $previewParams, '', 'Interactive TE-KG graph preview.', $currentUrl),
            'download' => $this->route('Download datasets', 'download', 'download.php', [], '', 'Dataset download page.', $currentUrl),
            'genomic' => $this->route('Genomic overview', 'genomic', 'genomic.php', [], '', 'General genomic analysis page.', $currentUrl),
        ];
    }

    private function route(string $title, string $capability, string $path, array $params, string $fragment, string $description, string $currentUrl): array
    {
        $query = http_build_query(array_filter($params, static fn($value): bool => trim((string)$value) !== ''));
        $relative = tekg_app_url($path);
        if ($query !== '') {
            $relative .= '?' . $query;
        }
        if ($fragment !== '') {
            $relative .= '#' . $fragment;
        }

        return [
            'title' => $title,
            'capability' => $capability,
            'url' => $this->absoluteUrl($relative, $currentUrl),
            'path' => $path,
            'fragment' => $fragment,
            'description' => $description,
        ];
    }

    private function absoluteUrl(string $relative, string $currentUrl): string
    {
        $parts = parse_url($currentUrl);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $relative;
        }
        $origin = (string)$parts['scheme'] . '://' . (string)$parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . (string)$parts['port'];
        }
        return rtrim($origin, '/') . $relative;
    }

    private function detectCapability(string $question, array $analysis): string
    {
        $lower = tekg_agent_lower($question);
        $scores = [
            'genome_distribution' => $this->score($lower, ['genome annotation distribution' => 10, 'annotation distribution' => 8, 'karyotype' => 7, 'genome distribution' => 7, '基因组注释分布' => 9, '注释分布' => 8, '基因组分布' => 7]),
            'genome_browser' => $this->score($lower, ['genome browser' => 9, 'jbrowse' => 9, 'browser' => 5, '基因组浏览器' => 9, '浏览器' => 5]),
            'sequence' => $this->score($lower, ['sequence' => 8, 'consensus' => 6, 'repbase' => 6, 'full sequence' => 9, '完整序列' => 9, '序列' => 7]),
            'expression_normal_tissue' => $this->score($lower, ['normal tissue' => 9, 'tissue expression' => 8, '组织表达' => 9, '组织' => 6]),
            'expression_normal_cell_line' => $this->score($lower, ['normal cell line' => 9, 'cell line' => 6, '正常细胞系' => 9, '细胞系' => 6]),
            'expression_cancer_cell_line' => $this->score($lower, ['cancer cell line' => 9, 'cancer expression' => 7, '癌细胞系' => 9, '癌症细胞系' => 9]),
            'expression_summary' => $this->score($lower, ['expression' => 7, '表达' => 7, 'expression page' => 9]),
            'preview_graph' => $this->score($lower, ['te-kg' => 7, 'knowledge graph' => 7, 'graph page' => 8, '图谱' => 7, '知识图谱' => 8]),
            'local_graph' => $this->score($lower, ['local graph' => 8, 'relationship graph' => 7, '关系图' => 8, '局部图' => 8]),
            'browse_catalog' => $this->score($lower, ['browse' => 7, 'catalog' => 7, '目录' => 7, '浏览' => 5]),
            'download' => $this->score($lower, ['download' => 8, 'dataset' => 6, 'datasets' => 6, '下载' => 8, '数据集' => 6]),
            'search_summary' => $this->score($lower, ['search' => 5, 'summary' => 5, 'overview' => 5, '搜索' => 5, '概览' => 5, '简介' => 5]),
        ];

        arsort($scores);
        $best = (string)array_key_first($scores);
        if (($scores[$best] ?? 0) > 0) {
            return $best;
        }

        return match ((string)($analysis['intent'] ?? '')) {
            'sequence' => 'sequence',
            'genome' => 'genome_browser',
            'expression' => 'expression_summary',
            'classification' => 'browse_catalog',
            'relationship', 'mechanism', 'comparison' => 'preview_graph',
            default => 'search_summary',
        };
    }

    private function score(string $question, array $weights): int
    {
        $score = 0;
        foreach ($weights as $needle => $weight) {
            if ($needle !== '' && str_contains($question, (string)$needle)) {
                $score += (int)$weight;
            }
        }
        return $score;
    }

    private function candidateKeys(string $capability, array $analysis): array
    {
        return match ($capability) {
            'genome_distribution' => ['genome_distribution', 'genome_browser', 'jbrowse_direct', 'search_summary', 'preview_graph'],
            'genome_browser' => ['genome_browser', 'jbrowse_direct', 'genome_distribution', 'genomic', 'search_summary'],
            'sequence' => ['sequence', 'search_summary', 'browse_catalog', 'preview_graph'],
            'expression_normal_tissue' => ['expression_normal_tissue', 'expression_summary', 'expression_normal_cell_line', 'expression_cancer_cell_line'],
            'expression_normal_cell_line' => ['expression_normal_cell_line', 'expression_summary', 'expression_normal_tissue', 'expression_cancer_cell_line'],
            'expression_cancer_cell_line' => ['expression_cancer_cell_line', 'expression_summary', 'expression_normal_cell_line', 'expression_normal_tissue'],
            'expression_summary' => ['expression_summary', 'expression_normal_tissue', 'expression_normal_cell_line', 'expression_cancer_cell_line'],
            'preview_graph' => ['preview_graph', 'local_graph', 'search_summary'],
            'local_graph' => ['local_graph', 'preview_graph', 'search_summary'],
            'browse_catalog' => ['browse_catalog', 'search_summary', 'preview_graph'],
            'download' => ['download', 'browse_catalog', 'search_summary'],
            default => ['search_summary', 'sequence', 'genome_distribution', 'expression_summary', 'preview_graph'],
        };
    }

    private function confidenceFor(string $capability, string $question, array $analysis): float
    {
        if (($analysis['asks_for_site_navigation'] ?? false) === true) {
            return 0.86;
        }
        if ($this->score(tekg_agent_lower($question), [$capability => 1]) > 0) {
            return 0.78;
        }
        return 0.68;
    }

    private function buildAnswerMarkdown(array $primary, array $alternatives, string $entity, string $language, float $confidence): string
    {
        $isChinese = in_array($language, ['zh', 'chinese'], true);
        $prefix = $isChinese
            ? ($entity !== '' ? "{$entity} 对应的站内入口是" : '对应的站内入口是')
            : ($entity !== '' ? "The TE-KG entry for {$entity} is" : 'The TE-KG entry is');
        $body = sprintf('%s [%s](%s).', $prefix, (string)$primary['title'], (string)$primary['url']);

        if ($alternatives !== []) {
            $body .= $isChinese ? "\n\n如果你想看的不是这个面板，也可以选择：" : "\n\nIf this is not the panel you wanted, use one of these choices:";
            foreach ($alternatives as $route) {
                $body .= sprintf("\n- [%s](%s) - %s", (string)$route['title'], (string)$route['url'], (string)$route['description']);
            }
        }

        if ($confidence < 0.75) {
            $body .= $isChinese
                ? "\n\n我没有强行判定唯一入口，因此保留了多个候选链接。"
                : "\n\nI kept multiple choices because the requested site location is not unique.";
        }

        return $body;
    }
}
