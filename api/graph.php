<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$localConfig = [];
$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    $loaded = require $localConfigPath;
    if (is_array($loaded)) {
        $localConfig = $loaded;
    }
}

$config = [
    'neo4j_url' => $localConfig['neo4j_url'] ?? env_value(['NEO4J_HTTP_URL_BIOLOGY', 'NEO4J_HTTP_URL'], 'http://127.0.0.1:7474/db/tekg/tx/commit'),
    'neo4j_user' => $localConfig['neo4j_user'] ?? env_value(['NEO4J_USER_BIOLOGY', 'NEO4J_USER'], 'neo4j'),
    'neo4j_password' => $localConfig['neo4j_password'] ?? env_value(['NEO4J_PASSWORD_BIOLOGY', 'NEO4J_PASSWORD'], ''),
];

if (!function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'PHP cURL extension is required'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($config['neo4j_password'] === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Neo4j password is not configured'], JSON_UNESCAPED_UNICODE);
    exit;
}

$query = trim((string)($_GET['q'] ?? ''));

if ($query === '') {
    $query = 'LINE1';
}

try {
    $service = new GraphService($config);
    $payload = $service->search($query);
    echo json_encode(['ok' => true] + $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function env_value(array $names, ?string $default = null): ?string
{
    foreach ($names as $name) {
        $value = getenv($name);
        if ($value !== false && trim((string)$value) !== '') {
            return trim((string)$value);
        }
    }
    return $default;
}

final class GraphService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function search(string $query): array
    {
        $normalized = $this->normalizeQuery($query);
        $anchor = $this->findAnchorNode($query, $normalized);
        if ($anchor === null) {
            return [
                'query' => $query,
                'normalized_query' => $normalized,
                'anchor' => null,
                'elements' => [],
                'matches' => [],
            ];
        }

        $anchorType = $this->normalizeType($anchor['labels']);
        if ($anchorType === 'Disease') {
            $rows = $this->buildDiseaseContextRows($anchor);
        } elseif ($anchorType === 'Paper') {
            $rows = $this->buildPaperContextRows($anchor);
        } else {
            $rows = $this->loadDirectRows($anchor['element_id']);
            $rows = $this->pruneGenericRows($rows, $anchorType);
        }

        $elements = $this->buildElements($anchor, $rows);

        return [
            'query' => $query,
            'normalized_query' => $normalized,
            'anchor' => [
                'name' => $anchor['name'],
                'type' => $anchorType,
                'pmid' => $anchor['pmid'],
            ],
            'elements' => $elements,
            'matches' => $anchor['matches'],
        ];
    }

    private function loadDirectRows(string $anchorId): array
    {
        return $this->runNeo4j(
            <<<'CYPHER'
MATCH (n)-[r]-(m)
WHERE elementId(n) = $anchorId
RETURN
  elementId(n) AS source_element_id,
  labels(n) AS source_labels,
  n.name AS source_name,
  n.description AS source_description,
  n.pmid AS source_pmid,
  elementId(m) AS target_element_id,
  labels(m) AS target_labels,
  m.name AS target_name,
  m.description AS target_description,
  m.pmid AS target_pmid,
  type(r) AS relation_type,
  coalesce(r.predicate, type(r)) AS relation_label,
  coalesce(r.pmids, []) AS relation_pmids,
  coalesce(r.evidence, '') AS relation_evidence
CYPHER,
            ['anchorId' => $anchorId]
        );
    }

    private function loadDiseaseTeRows(string $anchorId): array
    {
        return $this->runNeo4j(
            <<<'CYPHER'
MATCH (d:Disease)
WHERE elementId(d) = $anchorId
MATCH (d)-[r1]-(te:TE)
RETURN
  elementId(d) AS source_element_id,
  labels(d) AS source_labels,
  d.name AS source_name,
  d.description AS source_description,
  d.pmid AS source_pmid,
  elementId(te) AS target_element_id,
  labels(te) AS target_labels,
  te.name AS target_name,
  te.description AS target_description,
  te.pmid AS target_pmid,
  type(r1) AS relation_type,
  coalesce(r1.predicate, type(r1)) AS relation_label,
  coalesce(r1.pmids, []) AS relation_pmids,
  coalesce(r1.evidence, '') AS relation_evidence
CYPHER,
            ['anchorId' => $anchorId]
        );
    }

    private function buildDiseaseContextRows(array $anchor): array
    {
        $rows = $this->loadDiseaseTeRows((string)$anchor['element_id']);
        $rows = array_values(array_filter($rows, static fn(array $row): bool => !empty($row['target_element_id'])));
        usort($rows, static function (array $a, array $b): int {
            $left = count($a['relation_pmids'] ?? []);
            $right = count($b['relation_pmids'] ?? []);
            if ($left === $right) {
                return strcasecmp((string)($a['target_name'] ?? ''), (string)($b['target_name'] ?? ''));
            }
            return $right <=> $left;
        });
        $rows = array_slice($rows, 0, 8);

        foreach ($rows as &$row) {
            $teId = (string)$row['target_element_id'];
            $supportPmids = array_values(array_unique(array_filter(
                array_map('strval', $row['relation_pmids'] ?? []),
                static fn(string $pmid): bool => $pmid !== ''
            )));

            $row['expanded'] = array_merge(
                array_slice($this->loadTeFunctionRows($teId), 0, 10),
                array_slice(
                    $this->loadPaperRowsByPmids(
                        $supportPmids,
                        (string)$anchor['element_id'],
                        (string)$anchor['name']
                    ),
                    0,
                    12
                )
            );
        }
        unset($row);

        return $rows;
    }

    private function buildPaperContextRows(array $anchor): array
    {
        $rows = $this->loadDirectRows((string)$anchor['element_id']);
        $rows = $this->pruneGenericRows($rows, 'Paper');
        $pmid = trim((string)($anchor['pmid'] ?? ''));
        if ($pmid === '') {
            return $rows;
        }

        $rows = array_merge($rows, $this->loadPaperEvidenceRows((string)$anchor['element_id'], (string)$anchor['name'], $pmid));
        return $rows;
    }

    private function loadTeFunctionRows(string $teId): array
    {
        $rows = $this->runNeo4j(
            <<<'CYPHER'
MATCH (te:TE)-[r:BIO_RELATION]-(f:Function)
WHERE elementId(te) = $teId
RETURN
  elementId(te) AS source_element_id,
  labels(te) AS source_labels,
  te.name AS source_name,
  te.description AS source_description,
  te.pmid AS source_pmid,
  elementId(f) AS target_element_id,
  labels(f) AS target_labels,
  f.name AS target_name,
  f.description AS target_description,
  f.pmid AS target_pmid,
  type(r) AS relation_type,
  coalesce(r.predicate, type(r)) AS relation_label,
  coalesce(r.pmids, []) AS relation_pmids,
  coalesce(r.evidence, '') AS relation_evidence
LIMIT 20
CYPHER,
            ['teId' => $teId]
        );
        usort($rows, static function (array $a, array $b): int {
            $left = count($a['relation_pmids'] ?? []);
            $right = count($b['relation_pmids'] ?? []);
            if ($left === $right) {
                return strcasecmp((string)($a['target_name'] ?? ''), (string)($b['target_name'] ?? ''));
            }
            return $right <=> $left;
        });
        return $rows;
    }

    private function loadPaperRowsByPmids(array $pmids, string $diseaseId, string $diseaseName): array
    {
        if (empty($pmids)) {
            return [];
        }

        return $this->runNeo4j(
            <<<'CYPHER'
MATCH (p:Paper)
WHERE p.pmid IN $pmids
RETURN
  elementId(p) AS source_element_id,
  labels(p) AS source_labels,
  p.name AS source_name,
  p.description AS source_description,
  p.pmid AS source_pmid,
  $diseaseId AS target_element_id,
  ['Disease'] AS target_labels,
  $diseaseName AS target_name,
  '' AS target_description,
  '' AS target_pmid,
  'EVIDENCE_RELATION' AS relation_type,
  '支持该关联' AS relation_label,
  [p.pmid] AS relation_pmids,
  '' AS relation_evidence
ORDER BY p.pmid
CYPHER,
            [
                'pmids' => $pmids,
                'diseaseId' => $diseaseId,
                'diseaseName' => $diseaseName,
            ]
        );
    }

    private function loadPaperEvidenceRows(string $paperId, string $paperName, string $pmid): array
    {
        return $this->runNeo4j(
            <<<'CYPHER'
MATCH (te:TE)-[r:BIO_RELATION]-(d:Disease)
WHERE $pmid IN coalesce(r.pmids, [])
RETURN
  $paperId AS source_element_id,
  ['Paper'] AS source_labels,
  $paperName AS source_name,
  '' AS source_description,
  $pmid AS source_pmid,
  elementId(d) AS target_element_id,
  labels(d) AS target_labels,
  d.name AS target_name,
  d.description AS target_description,
  d.pmid AS target_pmid,
  'EVIDENCE_RELATION' AS relation_type,
  '支持该关联' AS relation_label,
  [$pmid] AS relation_pmids,
  '' AS relation_evidence
UNION
MATCH (te:TE)-[r:BIO_RELATION]-(d:Disease)
WHERE $pmid IN coalesce(r.pmids, [])
RETURN
  $paperId AS source_element_id,
  ['Paper'] AS source_labels,
  $paperName AS source_name,
  '' AS source_description,
  $pmid AS source_pmid,
  elementId(te) AS target_element_id,
  labels(te) AS target_labels,
  te.name AS target_name,
  te.description AS target_description,
  te.pmid AS target_pmid,
  'EVIDENCE_RELATION' AS relation_type,
  '支持该关联' AS relation_label,
  [$pmid] AS relation_pmids,
  '' AS relation_evidence
LIMIT 16
CYPHER,
            [
                'paperId' => $paperId,
                'paperName' => $paperName,
                'pmid' => $pmid,
            ]
        );
    }

    private function pruneGenericRows(array $rows, string $anchorType): array
    {
        $rows = array_values(array_filter($rows, static fn(array $row): bool => !empty($row['target_element_id'])));
        $limits = [
            'TE' => ['TE' => 14, 'Disease' => 10, 'Function' => 12, 'Paper' => 16],
            'Function' => ['TE' => 10, 'Paper' => 10],
            'Paper' => ['TE' => 10, 'Disease' => 8, 'Function' => 8],
            'Unknown' => ['TE' => 8, 'Disease' => 8, 'Function' => 8, 'Paper' => 8],
        ];
        $typeLimits = $limits[$anchorType] ?? $limits['Unknown'];
        $buckets = [];

        foreach ($rows as $row) {
            $targetType = $this->normalizeType($row['target_labels'] ?? []);
            if (!isset($typeLimits[$targetType])) {
                continue;
            }
            $buckets[$targetType] ??= [];
            $buckets[$targetType][] = $row;
        }

        $selected = [];
        foreach ($buckets as $targetType => $bucketRows) {
            usort($bucketRows, static function (array $a, array $b): int {
                $left = count($a['relation_pmids'] ?? []);
                $right = count($b['relation_pmids'] ?? []);
                if ($left === $right) {
                    return strcasecmp((string)($a['target_name'] ?? ''), (string)($b['target_name'] ?? ''));
                }
                return $right <=> $left;
            });
            $selected = array_merge($selected, array_slice($bucketRows, 0, $typeLimits[$targetType]));
        }

        return $selected;
    }

    private function normalizeQuery(string $query): string
    {
        $trimmed = trim($query);
        $lower = mb_strtolower($trimmed);
        $aliases = [
            'line1' => 'LINE1',
            'line-1' => 'LINE1',
            'l1' => 'LINE1',
            '阿尔兹海默症' => "Alzheimer's disease",
            '阿兹海默症' => "Alzheimer's disease",
            '阿尔茨海默症' => "Alzheimer's disease",
            '阿尔茨海默病' => "Alzheimer's disease",
            'alzheimer disease' => "Alzheimer's disease",
            'alzheimer\'s disease' => "Alzheimer's disease",
            '亨廷顿病' => "Huntington's Disease",
            'down syndrome' => 'Down syndrome',
            '唐氏综合征' => 'Down syndrome',
            'rett综合征' => 'Rett syndrome',
            'rett 综合征' => 'Rett syndrome',
            'autism' => 'autism spectrum disorder',
            '自闭症' => 'autism spectrum disorder',
            '自闭症谱系障碍' => 'autism spectrum disorder',
            '乳腺癌' => 'breast cancer',
            'breast cancer' => 'breast cancer',
            '口腔鳞状细胞癌' => 'Oral Squamous Cell Carcinoma',
            '共济失调毛细血管扩张症' => 'ataxia telangiectasia',
            'l1hs' => 'L1HS',
            'alu' => 'Alu',
            'sva' => 'SVA',
        ];

        return $aliases[$lower] ?? $trimmed;
    }

    private function findAnchorNode(string $query, string $normalized): ?array
    {
        $exact = $this->runNeo4j(
            <<<'CYPHER'
MATCH (n)
WHERE toLower(coalesce(n.name, '')) = toLower($exact)
   OR coalesce(n.pmid, '') = $pmid
RETURN
  elementId(n) AS element_id,
  labels(n) AS labels,
  n.name AS name,
  n.description AS description,
  n.pmid AS pmid
LIMIT 10
CYPHER,
            ['exact' => $normalized, 'pmid' => $query]
        );

        if (!empty($exact)) {
            $primary = $exact[0];
            $primary['matches'] = array_map(
                fn(array $row): array => [
                    'name' => (string)($row['name'] ?? ''),
                    'type' => $this->normalizeType($row['labels'] ?? []),
                    'pmid' => (string)($row['pmid'] ?? ''),
                ],
                $exact
            );
            return $primary;
        }

        $fuzzy = $this->runNeo4j(
            <<<'CYPHER'
MATCH (n)
WHERE toLower(coalesce(n.name, '')) CONTAINS toLower($keyword)
RETURN
  elementId(n) AS element_id,
  labels(n) AS labels,
  n.name AS name,
  n.description AS description,
  n.pmid AS pmid
ORDER BY
  CASE
    WHEN 'TE' IN labels(n) THEN 0
    WHEN 'Disease' IN labels(n) THEN 1
    WHEN 'Function' IN labels(n) THEN 2
    WHEN 'Paper' IN labels(n) THEN 3
    ELSE 4
  END,
  size(coalesce(n.name, ''))
LIMIT 10
CYPHER,
            ['keyword' => $normalized]
        );

        if (empty($fuzzy)) {
            return null;
        }

        $primary = $fuzzy[0];
        $primary['matches'] = array_map(
            fn(array $row): array => [
                'name' => (string)($row['name'] ?? ''),
                'type' => $this->normalizeType($row['labels'] ?? []),
                'pmid' => (string)($row['pmid'] ?? ''),
            ],
            $fuzzy
        );
        return $primary;
    }

    private function buildElements(array $anchor, array $rows): array
    {
        $nodes = [];
        $edges = [];

        $this->addNode($nodes, [
            'element_id' => $anchor['element_id'],
            'labels' => $anchor['labels'],
            'name' => $anchor['name'],
            'description' => $anchor['description'] ?? '',
            'pmid' => $anchor['pmid'] ?? '',
        ]);

        foreach ($rows as $row) {
            if (($row['target_element_id'] ?? null) === null) {
                continue;
            }
            $this->addNode($nodes, [
                'element_id' => $row['source_element_id'],
                'labels' => $row['source_labels'],
                'name' => $row['source_name'],
                'description' => $row['source_description'] ?? '',
                'pmid' => $row['source_pmid'] ?? '',
            ]);
            $this->addNode($nodes, [
                'element_id' => $row['target_element_id'],
                'labels' => $row['target_labels'],
                'name' => $row['target_name'],
                'description' => $row['target_description'] ?? '',
                'pmid' => $row['target_pmid'] ?? '',
            ]);

            $edgeId = $row['source_element_id'] . '__' . $row['relation_type'] . '__' . $row['target_element_id'];
            $edges[$edgeId] = [
                'data' => [
                    'id' => $edgeId,
                    'source' => $row['source_element_id'],
                    'target' => $row['target_element_id'],
                    'relation' => $row['relation_label'] ?: $row['relation_type'],
                    'relationType' => $row['relation_type'],
                    'evidence' => $row['relation_evidence'],
                    'pmids' => $row['relation_pmids'],
                ],
            ];

            foreach (($row['expanded'] ?? []) as $extra) {
                if (($extra['source_element_id'] ?? null) === null || ($extra['target_element_id'] ?? null) === null) {
                    continue;
                }
                $this->addNode($nodes, $extra + [
                    'element_id' => $extra['source_element_id'],
                    'labels' => $extra['source_labels'],
                    'name' => $extra['source_name'],
                    'description' => $extra['source_description'] ?? '',
                    'pmid' => $extra['source_pmid'] ?? '',
                ]);
                $this->addNode($nodes, [
                    'element_id' => $extra['target_element_id'],
                    'labels' => $extra['target_labels'],
                    'name' => $extra['target_name'],
                    'description' => $extra['target_description'] ?? '',
                    'pmid' => $extra['target_pmid'] ?? '',
                ]);
                $extraEdgeId = $extra['source_element_id'] . '__' . $extra['relation_type'] . '__' . $extra['target_element_id'];
                $edges[$extraEdgeId] = [
                    'data' => [
                        'id' => $extraEdgeId,
                        'source' => $extra['source_element_id'],
                        'target' => $extra['target_element_id'],
                        'relation' => $extra['relation_label'] ?: $extra['relation_type'],
                        'relationType' => $extra['relation_type'],
                        'evidence' => $extra['relation_evidence'],
                        'pmids' => $extra['relation_pmids'],
                    ],
                ];
            }
        }

        return array_merge(array_values($nodes), array_values($edges));
    }

    private function addNode(array &$nodes, array $row): void
    {
        $id = (string)($row['element_id'] ?? '');
        if ($id === '') {
            return;
        }
        if (isset($nodes[$id])) {
            return;
        }

        $nodes[$id] = [
            'data' => [
                'id' => $id,
                'label' => (string)($row['name'] ?? ''),
                'type' => $this->normalizeType($row['labels'] ?? []),
                'description' => (string)($row['description'] ?? ''),
                'pmid' => (string)($row['pmid'] ?? ''),
            ],
        ];
    }

    private function normalizeType(array $labels): string
    {
        foreach (['TE', 'Disease', 'Function', 'Paper'] as $preferred) {
            if (in_array($preferred, $labels, true)) {
                return $preferred;
            }
        }
        return $labels[0] ?? 'Unknown';
    }

    private function runNeo4j(string $statement, array $parameters): array
    {
        $payload = json_encode([
            'statements' => [[
                'statement' => $statement,
                'parameters' => $parameters,
            ]],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init($this->config['neo4j_url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->config['neo4j_user'] . ':' . $this->config['neo4j_password'],
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 30,
        ]);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Neo4j request failed: ' . $error);
        }
        if ($status >= 400) {
            throw new RuntimeException('Neo4j HTTP ' . $status);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Neo4j response is not valid JSON');
        }
        if (!empty($decoded['errors'])) {
            $message = (string)($decoded['errors'][0]['message'] ?? 'Neo4j query failed');
            throw new RuntimeException($message);
        }

        $results = $decoded['results'][0] ?? [];
        $columns = $results['columns'] ?? [];
        $rows = [];
        foreach (($results['data'] ?? []) as $row) {
            $mapped = [];
            foreach ($columns as $index => $column) {
                $mapped[$column] = $row['row'][$index] ?? null;
            }
            $rows[] = $mapped;
        }
        return $rows;
    }
}
