<?php
declare(strict_types=1);

final class PathFinderService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function find(string $sourceQuery, string $targetQuery, int $maxDepth): array
    {
        $sourceQuery = trim($sourceQuery);
        $targetQuery = trim($targetQuery);
        $maxDepth = max(1, min(3, $maxDepth));

        if ($sourceQuery === '' || $targetQuery === '') {
            return [
                'ok' => false,
                'error' => 'Both source and target are required.',
                'max_depth' => $maxDepth,
            ];
        }

        $source = $this->resolveEntity($sourceQuery);
        $target = $this->resolveEntity($targetQuery);

        if ($source === null || $target === null) {
            return [
                'ok' => false,
                'error' => $source === null ? 'Source entity was not found.' : 'Target entity was not found.',
                'source_query' => $sourceQuery,
                'target_query' => $targetQuery,
                'source' => $source,
                'target' => $target,
                'max_depth' => $maxDepth,
                'paths' => [],
            ];
        }

        $paths = $this->loadPaths((string)$source['element_id'], (string)$target['element_id'], $maxDepth);

        return [
            'ok' => true,
            'source_query' => $sourceQuery,
            'target_query' => $targetQuery,
            'source' => $source,
            'target' => $target,
            'max_depth' => $maxDepth,
            'path_count' => count($paths),
            'paths' => $paths,
        ];
    }

    private function resolveEntity(string $query): ?array
    {
        $normalized = $this->normalizeQuery($query);
        $rows = $this->runNeo4j(
            <<<'CYPHER'
MATCH (n)
WHERE NOT 'Paper' IN labels(n)
  AND (toLower(coalesce(n.name, '')) = toLower($exact) OR coalesce(n.pmid, '') = $pmid)
RETURN elementId(n) AS element_id,
       labels(n) AS labels,
       n.name AS name,
       n.description AS description,
       n.pmid AS pmid,
       n.disease_class AS disease_class
ORDER BY
  CASE
    WHEN 'TE' IN labels(n) THEN 0
    WHEN 'Disease' IN labels(n) THEN 1
    WHEN 'Function' IN labels(n) THEN 2
    ELSE 3
  END,
  size(coalesce(n.name, ''))
LIMIT 10
CYPHER,
            ['exact' => $normalized, 'pmid' => $query]
        );

        if (empty($rows) && $this->allowFuzzy($normalized)) {
            $rows = $this->runNeo4j(
                <<<'CYPHER'
MATCH (n)
WHERE NOT 'Paper' IN labels(n)
  AND toLower(coalesce(n.name, '')) CONTAINS toLower($keyword)
RETURN elementId(n) AS element_id,
       labels(n) AS labels,
       n.name AS name,
       n.description AS description,
       n.pmid AS pmid,
       n.disease_class AS disease_class
ORDER BY
  CASE
    WHEN 'TE' IN labels(n) THEN 0
    WHEN 'Disease' IN labels(n) THEN 1
    WHEN 'Function' IN labels(n) THEN 2
    ELSE 3
  END,
  size(coalesce(n.name, ''))
LIMIT 10
CYPHER,
                ['keyword' => $normalized]
            );
        }

        if (empty($rows)) {
            return null;
        }

        $matches = array_map(fn(array $row): array => $this->normalizeNode($row), $rows);
        $primary = $matches[0];
        $primary['matches'] = $matches;
        return $primary;
    }

    private function loadPaths(string $sourceId, string $targetId, int $maxDepth): array
    {
        $depth = max(1, min(3, $maxDepth));
        $cypher = sprintf(
            <<<'CYPHER'
MATCH (source), (target)
WHERE elementId(source) = $sourceId AND elementId(target) = $targetId
MATCH p = (source)-[:BIO_RELATION*1..%d]-(target)
WHERE ALL(node IN nodes(p) WHERE NONE(label IN labels(node) WHERE label = 'Paper'))
WITH p, nodes(p) AS pathNodes, relationships(p) AS pathRels
WITH p, pathNodes, pathRels,
     reduce(pathKey = '', node IN pathNodes | pathKey + '|' + coalesce(node.name, '')) AS path_key
RETURN
  [node IN pathNodes | {
    element_id: elementId(node),
    labels: labels(node),
    name: node.name,
    description: node.description,
    pmid: node.pmid,
    disease_class: node.disease_class
  }] AS nodes,
  [i IN range(0, size(pathRels) - 1) | {
    source: elementId(pathNodes[i]),
    target: elementId(pathNodes[i + 1]),
    relation_type: type(pathRels[i]),
    relation_label: coalesce(pathRels[i].predicate, type(pathRels[i])),
    evidence: coalesce(pathRels[i].evidence, ''),
    pmids: coalesce(pathRels[i].pmids, [])
  }] AS edges,
  length(p) AS hop_count,
  reduce(total = 0, rel IN pathRels | total + size(coalesce(rel.pmids, []))) AS pmid_count
ORDER BY hop_count ASC, pmid_count DESC, path_key ASC
LIMIT 25
CYPHER,
            $depth
        );

        $rows = $this->runNeo4j($cypher, ['sourceId' => $sourceId, 'targetId' => $targetId]);
        $evidenceRecordsByPmid = $this->loadEvidenceRecordsByPmids($this->collectPathPmids($rows));
        $paths = [];
        foreach ($rows as $index => $row) {
            $nodes = array_map(fn(array $node): array => $this->normalizeNode($node), (array)($row['nodes'] ?? []));
            $edges = array_map(fn(array $edge): array => $this->normalizeEdge($edge, $evidenceRecordsByPmid), (array)($row['edges'] ?? []));
            $pmids = [];
            foreach ($edges as $edge) {
                $pmids = array_merge($pmids, $edge['pmids']);
            }
            $pmids = $this->normalizePmids($pmids);
            $paths[] = [
                'id' => 'path_' . ($index + 1),
                'hop_count' => (int)($row['hop_count'] ?? count($edges)),
                'pmid_count' => count($pmids),
                'pmids' => $pmids,
                'nodes' => $nodes,
                'edges' => $edges,
            ];
        }

        usort($paths, static function (array $left, array $right): int {
            $hopCompare = ((int)$left['hop_count']) <=> ((int)$right['hop_count']);
            if ($hopCompare !== 0) {
                return $hopCompare;
            }
            $pmidCompare = ((int)$right['pmid_count']) <=> ((int)$left['pmid_count']);
            if ($pmidCompare !== 0) {
                return $pmidCompare;
            }
            return strcasecmp(
                implode('|', array_map(static fn(array $node): string => (string)$node['name'], $left['nodes'])),
                implode('|', array_map(static fn(array $node): string => (string)$node['name'], $right['nodes']))
            );
        });

        return $paths;
    }

    private function normalizeNode(array $row): array
    {
        return [
            'element_id' => (string)($row['element_id'] ?? ''),
            'name' => (string)($row['name'] ?? ''),
            'type' => $this->normalizeType((array)($row['labels'] ?? [])),
            'labels' => array_values(array_map('strval', (array)($row['labels'] ?? []))),
            'description' => (string)($row['description'] ?? ''),
            'pmid' => (string)($row['pmid'] ?? ''),
            'disease_class' => (string)($row['disease_class'] ?? ''),
        ];
    }

    private function normalizeEdge(array $row, array $evidenceRecordsByPmid = []): array
    {
        $pmids = $this->normalizePmids((array)($row['pmids'] ?? []));
        return [
            'source' => (string)($row['source'] ?? ''),
            'target' => (string)($row['target'] ?? ''),
            'relation_type' => (string)($row['relation_type'] ?? ''),
            'relation_label' => (string)($row['relation_label'] ?? ($row['relation_type'] ?? '')),
            'evidence' => (string)($row['evidence'] ?? ''),
            'pmid_count' => count($pmids),
            'pmids' => $pmids,
            'evidence_records' => $this->evidenceRecordsForPmids($pmids, $evidenceRecordsByPmid),
        ];
    }

    private function collectPathPmids(array $rows): array
    {
        $pmids = [];
        foreach ($rows as $row) {
            foreach ((array)($row['edges'] ?? []) as $edge) {
                foreach ((array)($edge['pmids'] ?? []) as $pmid) {
                    $key = trim((string)$pmid);
                    if ($key !== '') {
                        $pmids[$key] = true;
                    }
                }
            }
        }
        return array_keys($pmids);
    }

    private function loadEvidenceRecordsByPmids(array $pmids): array
    {
        $pmids = array_values(array_unique(array_filter(array_map(
            static fn($pmid): string => trim((string)$pmid),
            $pmids
        ))));
        if ($pmids === []) {
            return [];
        }

        $records = [];
        foreach (array_chunk($pmids, 500) as $chunk) {
            $rows = $this->runNeo4j(
                <<<'CYPHER'
MATCH (p:Paper)
WHERE p.pmid IN $pmids
RETURN
  p.pmid AS pmid,
  p.pubmed_title AS pubmed_title,
  p.pubmed_journal_title AS pubmed_journal_title,
  p.pubmed_publication_year AS pubmed_publication_year,
  p.journal_metric_value AS journal_metric_value,
  p.journal_metric_source AS journal_metric_source,
  p.journal_metric_year AS journal_metric_year,
  p.journal_jcr_quartile AS journal_jcr_quartile,
  p.journal_metric_match_method AS journal_metric_match_method
CYPHER,
                ['pmids' => $chunk]
            );

            foreach ($rows as $row) {
                $pmid = trim((string)($row['pmid'] ?? ''));
                if ($pmid === '') {
                    continue;
                }
                $records[$pmid] = $this->normalizeEvidenceRecord($pmid, $row);
            }
        }

        return $records;
    }

    private function evidenceRecordsForPmids(array $pmids, array $recordsByPmid): array
    {
        $records = [];
        foreach ($pmids as $pmid) {
            $key = trim((string)$pmid);
            if ($key === '') {
                continue;
            }
            $records[] = $recordsByPmid[$key] ?? $this->normalizeEvidenceRecord($key, []);
        }
        return $records;
    }

    private function normalizeEvidenceRecord(string $pmid, array $row): array
    {
        return [
            'pmid' => $pmid,
            'pubmed_url' => 'https://pubmed.ncbi.nlm.nih.gov/' . rawurlencode($pmid) . '/',
            'pubmed_title' => $this->nullableStringValue($row['pubmed_title'] ?? null),
            'pubmed_journal_title' => $this->nullableStringValue($row['pubmed_journal_title'] ?? null),
            'pubmed_publication_year' => $this->nullableIntValue($row['pubmed_publication_year'] ?? null),
            'journal_metric_value' => $this->nullableFloatValue($row['journal_metric_value'] ?? null),
            'journal_metric_source' => $this->nullableStringValue($row['journal_metric_source'] ?? null),
            'journal_metric_year' => $this->nullableIntValue($row['journal_metric_year'] ?? null),
            'journal_jcr_quartile' => $this->nullableStringValue($row['journal_jcr_quartile'] ?? null),
            'journal_metric_match_method' => $this->nullableStringValue($row['journal_metric_match_method'] ?? null),
        ];
    }

    private function normalizePmids(array $pmids): array
    {
        $seen = [];
        $normalized = [];
        foreach ($pmids as $pmid) {
            $value = trim((string)$pmid);
            if ($value !== '' && preg_match('/^\d{4,12}$/', $value) === 1 && !isset($seen[$value])) {
                $seen[$value] = true;
                $normalized[] = $value;
            }
        }
        sort($normalized, SORT_NATURAL);
        return $normalized;
    }

    private function normalizeType(array $labels): string
    {
        foreach (['TE', 'Disease', 'Function', 'Gene', 'Protein', 'RNA', 'Mutation', 'Pharmaceutical', 'Toxin', 'Lipid', 'Peptide', 'Carbohydrate'] as $type) {
            if (in_array($type, $labels, true)) {
                return $type;
            }
        }
        return $labels[0] ?? 'Node';
    }

    private function normalizeQuery(string $query): string
    {
        return trim(preg_replace('/\s+/', ' ', $query) ?? $query);
    }

    private function allowFuzzy(string $query): bool
    {
        return mb_strlen($query) >= 3 && !preg_match('/^\d+$/', $query);
    }

    private function nullableFloatValue(mixed $value): ?float
    {
        return is_numeric($value) ? (float)$value : null;
    }

    private function nullableIntValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int)$value : null;
    }

    private function nullableStringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string)$value);
        return $text === '' ? null : $text;
    }

    private function runNeo4j(string $statement, array $parameters = []): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required');
        }

        $payload = json_encode([
            'statements' => [[
                'statement' => $statement,
                'parameters' => $parameters === [] ? new stdClass() : $parameters,
            ]],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init((string)$this->config['neo4j_url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => (string)$this->config['neo4j_user'] . ':' . (string)$this->config['neo4j_password'],
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Neo4j request failed: ' . $error);
        }
        if ($status >= 400) {
            throw new RuntimeException('Neo4j HTTP ' . $status);
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Neo4j response is not valid JSON');
        }
        if (!empty($decoded['errors'])) {
            throw new RuntimeException((string)($decoded['errors'][0]['message'] ?? 'Neo4j query failed'));
        }

        $result = $decoded['results'][0] ?? null;
        if (!is_array($result)) {
            return [];
        }

        $columns = $result['columns'] ?? [];
        $rows = [];
        foreach (($result['data'] ?? []) as $entry) {
            $values = $entry['row'] ?? [];
            $row = [];
            foreach ($columns as $index => $column) {
                $row[(string)$column] = $values[$index] ?? null;
            }
            $rows[] = $row;
        }
        return $rows;
    }
}
