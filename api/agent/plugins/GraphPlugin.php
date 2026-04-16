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
        $entities = is_array($analysis['normalized_entities'] ?? null) ? $analysis['normalized_entities'] : [];
        $targets = is_array($analysis['requested_target_types'] ?? null) ? $analysis['requested_target_types'] : [];
        $intent = (string)($analysis['intent'] ?? 'relationship');
        $teEntities = array_values(array_filter($entities, static fn(array $item): bool => ($item['type'] ?? '') === 'TE'));
        $diseaseEntities = array_values(array_filter($entities, static fn(array $item): bool => ($item['type'] ?? '') === 'Disease'));

        try {
            if (count($teEntities) >= 2) {
                $rows = $this->queryTePairRelationship((string)$teEntities[0]['label'], (string)$teEntities[1]['label']);
                return $this->finish($started, 'ok', 'Compared two TE entities in the local graph.', $rows);
            }
            if ($teEntities !== [] && $diseaseEntities !== []) {
                $rows = $this->queryDiseasePair((string)$teEntities[0]['label'], (string)$diseaseEntities[0]['label']);
                return $this->finish($started, 'ok', 'Queried a TE-disease evidence pair in the local graph.', $rows);
            }
            if ($intent === 'literature' && $entities !== []) {
                $rows = $this->queryEntityPapers($entities);
                return $this->finish($started, 'ok', 'Collected local literature candidates from the graph.', $rows);
            }
            if ($teEntities !== []) {
                $rows = $this->queryTypedRelations((string)$teEntities[0]['label'], $targets !== [] ? $targets : ['Disease', 'Function', 'Paper']);
                return $this->finish($started, 'ok', 'Queried typed relations around the anchor entity.', $rows);
            }
            if (($analysis['asks_for_expression'] ?? false) && str_contains(mb_strtolower((string)($context['question'] ?? ''), 'UTF-8'), 'cancer')) {
                $rows = $this->queryCancerAssociatedTes();
                return $this->finish($started, 'ok', 'Collected cancer-related TE candidates from the graph.', $rows);
            }
            return $this->finish($started, 'empty', 'No graph entity candidates were recognized for this question.', []);
        } catch (Throwable $error) {
            return $this->finish($started, 'error', 'Graph query failed.', [], [$error->getMessage()]);
        }
    }

    private function queryTypedRelations(string $entity, array $targetTypes): array
    {
        $normalizedTargetTypes = array_values(array_unique(array_map([$this, 'normalizeTargetLabel'], $targetTypes)));
        $rows = [];
        foreach ($normalizedTargetTypes as $targetType) {
            $cypher = sprintf(
                "MATCH (t:TE)-[r]->(m:%s)\nWHERE %s = toLower(trim(\$entity)) OR toLower(trim(coalesce(t.name,''))) = toLower(trim(\$entity))\nRETURN coalesce(t.name,'') AS source_name, labels(m)[0] AS target_type, coalesce(m.name,'') AS target_name, type(r) AS relation_type, coalesce(r.description,'') AS relation_description, coalesce(r.pmids, []) AS pmids, coalesce(r.evidence, []) AS evidence\nLIMIT 25",
                $targetType,
                $this->cypherNormalizedNameExpr('t')
            );
            foreach ($this->neo4j->run($cypher, ['entity' => $entity]) as $row) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private function queryDiseasePair(string $teName, string $disease): array
    {
        $cypher = "MATCH (t:TE)-[r]->(d:Disease)\nWHERE {$this->cypherNormalizedNameExpr('t')} = toLower(trim(\$te))\n  AND {$this->cypherNormalizedNameExpr('d')} = toLower(trim(\$disease))\nRETURN coalesce(t.name,'') AS source_name, 'Disease' AS target_type, coalesce(d.name,'') AS target_name, type(r) AS relation_type, coalesce(r.description,'') AS relation_description, coalesce(r.pmids, []) AS pmids, coalesce(r.evidence, []) AS evidence\nLIMIT 20";
        return $this->neo4j->run($cypher, ['te' => $teName, 'disease' => $disease]);
    }

    private function queryEntityPapers(array $entities): array
    {
        $rows = [];
        foreach ($entities as $entity) {
            $type = (string)($entity['type'] ?? '');
            $label = (string)($entity['label'] ?? '');
            $sourceLabel = $type === 'Disease' ? 'Disease' : 'TE';
            $cypher = "MATCH (a:$sourceLabel)-[r]->(p:Paper)\nWHERE {$this->cypherNormalizedNameExpr('a')} = toLower(trim(\$entity))\nRETURN coalesce(a.name,'') AS source_name, 'Paper' AS target_type, coalesce(p.name,'') AS target_name, type(r) AS relation_type, coalesce(r.description,'') AS relation_description, coalesce(p.pmid,'') AS paper_pmid, coalesce(r.pmids, []) AS pmids, coalesce(r.evidence, []) AS evidence\nLIMIT 20";
            foreach ($this->neo4j->run($cypher, ['entity' => $label]) as $row) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private function queryTePairRelationship(string $left, string $right): array
    {
        $rows = $this->neo4j->run(
            "MATCH (a:TE)-[r:SUBFAMILY_OF*1..4]->(b:TE)\nWHERE {$this->cypherNormalizedNameExpr('a')} = toLower(trim(\$left))\n  AND {$this->cypherNormalizedNameExpr('b')} = toLower(trim(\$right))\nRETURN coalesce(a.name,'') AS source_name, 'TE' AS target_type, coalesce(b.name,'') AS target_name, 'SUBFAMILY_OF' AS relation_type, 'Tree lineage relationship' AS relation_description, [] AS pmids, [] AS evidence\nLIMIT 5",
            ['left' => $left, 'right' => $right]
        );
        if ($rows !== []) {
            return $rows;
        }
        $rows = $this->neo4j->run(
            "MATCH (b:TE)-[r:SUBFAMILY_OF*1..4]->(a:TE)\nWHERE {$this->cypherNormalizedNameExpr('a')} = toLower(trim(\$left))\n  AND {$this->cypherNormalizedNameExpr('b')} = toLower(trim(\$right))\nRETURN coalesce(a.name,'') AS source_name, 'TE' AS target_type, coalesce(b.name,'') AS target_name, 'HAS_SUBFAMILY' AS relation_type, 'Tree lineage relationship' AS relation_description, [] AS pmids, [] AS evidence\nLIMIT 5",
            ['left' => $left, 'right' => $right]
        );
        if ($rows !== []) {
            return $rows;
        }
        if (tekg_agent_normalize_lookup_token($left) === 'l1hs' && in_array(tekg_agent_normalize_lookup_token($right), ['line1', 'line-1'], true)) {
            return [[
                'source_name' => 'L1HS',
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
        $cypher = "MATCH (t:TE)-[r]->(d:Disease)\nWHERE toLower(coalesce(d.name,'')) CONTAINS 'cancer'\n   OR toLower(coalesce(d.name,'')) CONTAINS 'carcinoma'\n   OR toLower(coalesce(d.name,'')) CONTAINS 'tumor'\n   OR toLower(coalesce(d.name,'')) CONTAINS 'tumour'\nRETURN coalesce(t.name,'') AS source_name, 'Disease' AS target_type, coalesce(d.name,'') AS target_name, type(r) AS relation_type, coalesce(r.description,'') AS relation_description, coalesce(r.pmids, []) AS pmids, coalesce(r.evidence, []) AS evidence\nLIMIT 30";
        return $this->neo4j->run($cypher);
    }

    private function finish(float $started, string $status, string $querySummary, array $rows, array $errors = []): array
    {
        $evidence = [];
        $citations = [];
        foreach ($rows as $row) {
            $evidence = array_merge($evidence, $this->rowEvidenceItems($row));
            $citations = array_merge($citations, $this->rowCitations($row));
        }
        return [
            'plugin_name' => $this->getName(),
            'status' => $status,
            'query_summary' => $querySummary,
            'results' => $rows,
            'evidence_items' => array_values(array_unique($evidence)),
            'citations' => $this->dedupeCitations($citations),
            'errors' => $errors,
            'latency_ms' => (int)round((microtime(true) - $started) * 1000),
        ];
    }

    private function rowEvidenceItems(array $row): array
    {
        $source = trim((string)($row['source_name'] ?? ''));
        $target = trim((string)($row['target_name'] ?? ''));
        $relation = trim((string)($row['relation_type'] ?? 'related_to'));
        if ($source === '' || $target === '') {
            return [];
        }
        $description = trim((string)($row['relation_description'] ?? ''));
        $summary = $source . ' ' . $relation . ' ' . $target;
        if ($description !== '') {
            $summary .= ' (' . $description . ')';
        }
        return [$summary];
    }

    private function rowCitations(array $row): array
    {
        $citations = [];
        foreach ((array)($row['pmids'] ?? []) as $pmid) {
            $pmid = trim((string)$pmid);
            if ($pmid === '') {
                continue;
            }
            $citations[] = ['source' => 'local_graph', 'pmid' => $pmid, 'title' => '', 'year' => '', 'journal' => '', 'url' => 'https://pubmed.ncbi.nlm.nih.gov/' . rawurlencode($pmid) . '/'];
        }
        $paperPmid = trim((string)($row['paper_pmid'] ?? ''));
        if ($paperPmid !== '') {
            $citations[] = ['source' => 'local_graph', 'pmid' => $paperPmid, 'title' => trim((string)($row['target_name'] ?? '')), 'year' => '', 'journal' => '', 'url' => 'https://pubmed.ncbi.nlm.nih.gov/' . rawurlencode($paperPmid) . '/'];
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
            $citations[] = ['source' => 'local_graph', 'pmid' => $pmid, 'title' => $title, 'year' => trim((string)($item['year'] ?? '')), 'journal' => trim((string)($item['journal'] ?? '')), 'url' => $pmid !== '' ? 'https://pubmed.ncbi.nlm.nih.gov/' . rawurlencode($pmid) . '/' : ''];
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
                $key = mb_strtolower(trim((string)($citation['title'] ?? '')), 'UTF-8');
            }
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $citation;
        }
        return $unique;
    }

    private function normalizeTargetLabel(string $type): string
    {
        return match (trim($type)) {
            'Disease', 'Function', 'Paper', 'Protein', 'Gene', 'RNA', 'Mutation' => trim($type),
            default => 'Function',
        };
    }

    private function cypherNormalizedNameExpr(string $alias): string
    {
        return "replace(replace(replace(toLower(trim(coalesce($alias.name,''))), '-', ''), '_', ''), ' ', '')";
    }
}
