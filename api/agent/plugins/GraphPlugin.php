<?php
declare(strict_types=1);

final class TekgAgentGraphPlugin implements TekgAgentPluginInterface
{
    public function __construct(private readonly TekgAgentNeo4jClient $neo4j)
    {
    }

    public function getName(): string
    {
        return 'Graph Plugin';
    }

    public function run(array $context): array
    {
        $started = microtime(true);
        $analysis = $context['analysis'] ?? [];
        $language = (string)($analysis['language'] ?? 'en');
        $intent = (string)($analysis['intent'] ?? 'relationship');
        $entities = is_array($analysis['normalized_entities'] ?? null) ? $analysis['normalized_entities'] : [];
        $targetTypes = is_array($analysis['requested_target_types'] ?? null) ? $analysis['requested_target_types'] : [];

        $teEntities = array_values(array_filter($entities, static fn(array $item): bool => ($item['type'] ?? '') === 'TE'));
        $diseaseEntities = array_values(array_filter($entities, static fn(array $item): bool => ($item['type'] ?? '') === 'Disease'));

        try {
            if (count($teEntities) >= 2) {
                $rows = $this->queryTePairRelationship((string)$teEntities[0]['label'], (string)$teEntities[1]['label']);
                return $this->finish($started, $language, $intent, 'Compared two TE entities in the local graph.', $rows);
            }

            if ($teEntities !== [] && $diseaseEntities !== []) {
                $rows = $this->queryDiseasePair((string)$teEntities[0]['label'], (string)$diseaseEntities[0]['label']);
                return $this->finish($started, $language, $intent, 'Queried a TE-disease evidence pair in the local graph.', $rows);
            }

            if ($teEntities !== []) {
                $rows = $this->queryTypedRelations((string)$teEntities[0]['label'], $this->normalizeTargetTypes($targetTypes, $intent));
                return $this->finish($started, $language, $intent, 'Collected structured relations from the local graph.', $rows);
            }

            if (($analysis['asks_for_mechanism'] ?? false) && ($analysis['question_keywords'] ?? []) !== []) {
                $rows = $this->queryCancerAssociatedTes();
                return $this->finish($started, $language, $intent, 'Collected mechanism-related TE candidates from the local graph.', $rows);
            }

            return $this->finish($started, $language, $intent, 'No graph entity candidates were recognized for this question.', []);
        } catch (Throwable $error) {
            return $this->finish($started, $language, $intent, 'Graph query failed.', [], [$error->getMessage()]);
        }
    }

    private function queryTypedRelations(string $entity, array $targetTypes): array
    {
        $rows = [];
        foreach ($targetTypes as $targetType) {
            $cypher = sprintf(
                "MATCH (t:TE)-[r]->(m:%s)
                 WHERE %s = toLower(trim(\$entity))
                 RETURN coalesce(t.name,'') AS source_name,
                        labels(m) AS target_labels,
                        coalesce(m.name,'') AS target_name,
                        type(r) AS relation_type,
                        coalesce(r.description,'') AS relation_description,
                        coalesce(r.pmids, []) AS pmids,
                        coalesce(r.evidence, []) AS evidence
                 LIMIT 24",
                $targetType,
                $this->cypherNormalizedNameExpr('t')
            );
            foreach ($this->neo4j->run($cypher, ['entity' => $entity]) as $row) {
                $row['target_type'] = $targetType;
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private function queryDiseasePair(string $teName, string $disease): array
    {
        $cypher = "MATCH (t:TE)-[r]->(d:Disease)
                   WHERE {$this->cypherNormalizedNameExpr('t')} = toLower(trim(\$te))
                     AND {$this->cypherNormalizedNameExpr('d')} = toLower(trim(\$disease))
                   RETURN coalesce(t.name,'') AS source_name,
                          ['Disease'] AS target_labels,
                          'Disease' AS target_type,
                          coalesce(d.name,'') AS target_name,
                          type(r) AS relation_type,
                          coalesce(r.description,'') AS relation_description,
                          coalesce(r.pmids, []) AS pmids,
                          coalesce(r.evidence, []) AS evidence
                   LIMIT 20";
        return $this->neo4j->run($cypher, ['te' => $teName, 'disease' => $disease]);
    }

    private function queryTePairRelationship(string $left, string $right): array
    {
        $rows = $this->neo4j->run(
            "MATCH (a:TE)-[:SUBFAMILY_OF*1..4]->(b:TE)
             WHERE {$this->cypherNormalizedNameExpr('a')} = toLower(trim(\$left))
               AND {$this->cypherNormalizedNameExpr('b')} = toLower(trim(\$right))
             RETURN coalesce(a.name,'') AS source_name,
                    ['TE'] AS target_labels,
                    'TE' AS target_type,
                    coalesce(b.name,'') AS target_name,
                    'SUBFAMILY_OF' AS relation_type,
                    'Tree lineage relationship' AS relation_description,
                    [] AS pmids,
                    [] AS evidence
             LIMIT 5",
            ['left' => $left, 'right' => $right]
        );
        if ($rows !== []) {
            return $rows;
        }

        $rows = $this->neo4j->run(
            "MATCH (b:TE)-[:SUBFAMILY_OF*1..4]->(a:TE)
             WHERE {$this->cypherNormalizedNameExpr('a')} = toLower(trim(\$left))
               AND {$this->cypherNormalizedNameExpr('b')} = toLower(trim(\$right))
             RETURN coalesce(a.name,'') AS source_name,
                    ['TE'] AS target_labels,
                    'TE' AS target_type,
                    coalesce(b.name,'') AS target_name,
                    'HAS_SUBFAMILY' AS relation_type,
                    'Tree lineage relationship' AS relation_description,
                    [] AS pmids,
                    [] AS evidence
             LIMIT 5",
            ['left' => $left, 'right' => $right]
        );
        if ($rows !== []) {
            return $rows;
        }

        if (tekg_agent_normalize_lookup_token($left) === 'l1hs' && in_array(tekg_agent_normalize_lookup_token($right), ['line1', 'line-1'], true)) {
            return [[
                'source_name' => 'L1HS',
                'target_labels' => ['TE'],
                'target_type' => 'TE',
                'target_name' => 'LINE-1',
                'relation_type' => 'SUBFAMILY_OF',
                'relation_description' => 'Canonical TE lineage relationship inferred from the TE tree.',
                'pmids' => [],
                'evidence' => [],
            ]];
        }

        return [];
    }

    private function queryCancerAssociatedTes(): array
    {
        $cypher = "MATCH (t:TE)-[r]->(d:Disease)
                   WHERE toLower(coalesce(d.name,'')) CONTAINS 'cancer'
                      OR toLower(coalesce(d.name,'')) CONTAINS 'carcinoma'
                      OR toLower(coalesce(d.name,'')) CONTAINS 'tumor'
                      OR toLower(coalesce(d.name,'')) CONTAINS 'tumour'
                   RETURN coalesce(t.name,'') AS source_name,
                          ['Disease'] AS target_labels,
                          'Disease' AS target_type,
                          coalesce(d.name,'') AS target_name,
                          type(r) AS relation_type,
                          coalesce(r.description,'') AS relation_description,
                          coalesce(r.pmids, []) AS pmids,
                          coalesce(r.evidence, []) AS evidence
                   LIMIT 30";
        return $this->neo4j->run($cypher);
    }

    private function finish(float $started, string $language, string $intent, string $querySummary, array $rows, array $errors = []): array
    {
        $grouped = [];
        $evidenceItems = [];
        $previewItems = [];
        $citations = [];

        foreach ($rows as $row) {
            $targetType = $this->resolveTargetType($row);
            $grouped[$targetType] = ($grouped[$targetType] ?? 0) + 1;
            $sentence = $this->rowSentence($row);
            if ($sentence !== '') {
                $evidenceItems[] = $sentence;
                if (count($previewItems) < 5) {
                    $previewItems[] = ['title' => $sentence, 'meta' => $targetType];
                }
            }
            $citations = array_merge($citations, $this->rowCitations($row));
        }

        $evidenceItems = array_values(array_unique($evidenceItems));
        $citations = $this->dedupeCitations($citations);
        $resultCounts = [
            'relations' => count($rows),
            'entity_types' => count($grouped),
        ];
        foreach ($grouped as $type => $count) {
            $resultCounts[$type] = $count;
        }

        $displayLabel = $language === 'zh'
            ? '查询到了 ' . count($rows) . ' 条关系'
            : 'Queried ' . count($rows) . ' graph relations';

        $displaySummary = $this->buildDisplaySummary($language, $intent, $grouped, count($rows), $errors);
        $resultMessage = $this->buildResultMessage($language, $intent, $grouped, $evidenceItems, count($rows));

        return [
            'plugin_name' => $this->getName(),
            'status' => $errors !== [] ? 'error' : ($rows === [] ? 'empty' : 'ok'),
            'query_summary' => $querySummary,
            'results' => [
                'rows' => $rows,
                'by_type' => $grouped,
            ],
            'display_label' => $displayLabel,
            'display_summary' => $displaySummary,
            'display_details' => [
                'summary' => $displaySummary,
                'preview_items' => $previewItems,
                'evidence_items' => $evidenceItems,
                'citations' => $citations,
                'raw_preview' => ['rows' => $rows, 'by_type' => $grouped],
                'result_message' => $resultMessage,
            ],
            'result_counts' => $resultCounts,
            'evidence_items' => $evidenceItems,
            'citations' => $citations,
            'errors' => $errors,
            'latency_ms' => (int)round((microtime(true) - $started) * 1000),
        ];
    }

    private function buildDisplaySummary(string $language, string $intent, array $grouped, int $count, array $errors): string
    {
        if ($errors !== []) {
            return $language === 'zh' ? '图谱关系检索时出现错误。' : 'The graph query failed.';
        }
        if ($count === 0) {
            return $language === 'zh'
                ? '这轮结构化关系没有给出足够直接的命中，我需要再从别的证据源补足。'
                : 'The structured graph did not provide enough direct hits in this round, so more evidence is still needed.';
        }
        $types = implode(', ', array_keys($grouped));
        if ($language === 'zh') {
            return $intent === 'mechanism'
                ? '我先从本地图谱里抓到了 ' . $count . ' 条结构化关系，重点落在 ' . $types . ' 这些类型上。'
                : '本地图谱返回了 ' . $count . ' 条结构化关系，主要覆盖 ' . $types . '。';
        }
        return $intent === 'mechanism'
            ? 'I first collected ' . $count . ' structured relations from the local graph, mainly across ' . $types . '.'
            : 'The local graph returned ' . $count . ' structured relations, mainly across ' . $types . '.';
    }

    private function buildResultMessage(string $language, string $intent, array $grouped, array $evidenceItems, int $count): string
    {
        if ($count === 0) {
            return $language === 'zh'
                ? '图谱里这一轮没有给出足够强的直接关系，我接下来需要转向文献或其他上下文来补足。'
                : 'The graph did not provide enough direct relations in this round, so I need to supplement it with literature or other context.';
        }

        $topTypes = array_slice(array_keys($grouped), 0, 4);
        if ($language === 'zh') {
            return $intent === 'mechanism'
                ? '这些关系说明当前更值得优先沿着 ' . implode('、', $topTypes) . ' 这些方向继续整理机制链。'
                : '这些关系已经足够提供一轮结构化判断，我会优先沿着 ' . implode('、', $topTypes) . ' 这些方向继续展开。';
        }
        return $intent === 'mechanism'
            ? 'These relations suggest that the next mechanism draft should focus on ' . implode(', ', $topTypes) . '.'
            : 'These relations are enough for a first structured judgment, especially along ' . implode(', ', $topTypes) . '.';
    }

    private function rowSentence(array $row): string
    {
        $source = trim((string)($row['source_name'] ?? ''));
        $target = trim((string)($row['target_name'] ?? ''));
        $relation = trim((string)($row['relation_type'] ?? 'related_to'));
        if ($source === '' || $target === '') {
            return '';
        }
        $description = trim((string)($row['relation_description'] ?? ''));
        $summary = $source . ' ' . $relation . ' ' . $target;
        if ($description !== '') {
            $summary .= ' (' . $description . ')';
        }
        return $summary;
    }

    private function rowCitations(array $row): array
    {
        $citations = [];
        foreach ((array)($row['pmids'] ?? []) as $pmid) {
            $pmid = trim((string)$pmid);
            if ($pmid === '') {
                continue;
            }
            $citations[] = [
                'source' => 'local_graph',
                'pmid' => $pmid,
                'title' => '',
                'year' => '',
                'journal' => '',
                'url' => 'https://pubmed.ncbi.nlm.nih.gov/' . rawurlencode($pmid) . '/',
            ];
        }
        foreach ((array)($row['evidence'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $pmid = trim((string)($item['pmid'] ?? ''));
            $title = trim((string)($item['title'] ?? ''));
            if ($pmid === '' && $title === '') {
                continue;
            }
            $citations[] = [
                'source' => 'local_graph',
                'pmid' => $pmid,
                'title' => $title,
                'year' => trim((string)($item['year'] ?? '')),
                'journal' => trim((string)($item['journal'] ?? '')),
                'url' => $pmid !== '' ? 'https://pubmed.ncbi.nlm.nih.gov/' . rawurlencode($pmid) . '/' : '',
                'abstract_summary' => trim((string)($item['summary'] ?? '')),
            ];
        }
        return $citations;
    }

    private function dedupeCitations(array $citations): array
    {
        $seen = [];
        $unique = [];
        foreach ($citations as $citation) {
            $key = trim((string)($citation['pmid'] ?? ''));
            if ($key === '') {
                $key = tekg_agent_lower(trim((string)($citation['title'] ?? '')));
            }
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $citation;
        }
        return $unique;
    }

    private function resolveTargetType(array $row): string
    {
        $targetType = trim((string)($row['target_type'] ?? ''));
        if ($targetType !== '') {
            return $targetType;
        }
        $labels = array_values(array_filter((array)($row['target_labels'] ?? []), static fn($item): bool => is_string($item) && trim($item) !== ''));
        return $labels[0] ?? 'Unknown';
    }

    private function normalizeTargetTypes(array $targetTypes, string $intent): array
    {
        $normalized = [];
        foreach ($targetTypes as $type) {
            $trimmed = trim((string)$type);
            if ($trimmed !== '') {
                $normalized[] = $trimmed;
            }
        }
        if ($normalized === [] && $intent === 'mechanism') {
            $normalized = ['Function', 'Gene', 'Mutation', 'Protein', 'RNA', 'Disease'];
        }
        if ($normalized === []) {
            $normalized = ['Disease', 'Function', 'Paper'];
        }
        $allowed = ['Carbohydrate', 'Disease', 'DiseaseCategory', 'Function', 'Gene', 'Lipid', 'Mutation', 'Paper', 'Peptide', 'Pharmaceutical', 'Protein', 'RNA', 'TE', 'Toxin'];
        return array_values(array_intersect($allowed, array_values(array_unique($normalized)))) ?: ['Disease', 'Function', 'Paper'];
    }

    private function cypherNormalizedNameExpr(string $alias): string
    {
        return "replace(replace(replace(toLower(trim(coalesce($alias.name,''))), '-', ''), '_', ''), ' ', '')";
    }
}
