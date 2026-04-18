<?php
declare(strict_types=1);

final class TekgAgentLiteraturePlugin implements TekgAgentPluginInterface
{
    public function __construct(
        private readonly TekgAgentNeo4jClient $neo4j,
        private readonly array $config,
        private readonly TekgAgentCitationResolver $citationResolver,
    ) {
    }

    public function getName(): string
    {
        return 'Literature Plugin';
    }

    public function run(array $context): array
    {
        $started = microtime(true);
        $analysis = is_array($context['analysis'] ?? null) ? $context['analysis'] : [];
        $entities = is_array($analysis['normalized_entities'] ?? null) ? $analysis['normalized_entities'] : [];
        $graphResult = $context['plugin_results']['Graph Plugin'] ?? [];
        $errors = [];
        try {
            $localCitations = $this->collectLocalCitations($graphResult, $entities);
        } catch (Throwable $error) {
            $localCitations = [];
            $errors[] = 'Local graph literature lookup failed: ' . $error->getMessage();
        }
        $queryTerms = $this->buildPubMedTerms((string)($context['question'] ?? ''), $analysis, $localCitations);

        $pubmedCitations = [];
        $pubmedTotalCount = 0;
        $evidenceItems = [];
        $previewItems = [];

        foreach ($queryTerms as $term) {
            try {
                $result = $this->searchPubMed($term);
                $pubmedTotalCount += (int)($result['total_count'] ?? 0);
                $pubmedCitations = array_merge($pubmedCitations, (array)($result['citations'] ?? []));
                $evidenceItems[] = tekg_agent_make_evidence_item(
                    $this->getName(),
                    'Searched PubMed with the query "' . $term . '".',
                    $term,
                    'medium',
                    ['query_term' => $term],
                    [
                        'title' => 'PubMed query',
                        'meta' => $term,
                        'body' => 'External literature search executed for this query.',
                    ]
                );
            } catch (Throwable $error) {
                $errors[] = 'PubMed query failed for "' . $term . '": ' . $error->getMessage();
            }
        }

        $citations = $this->citationResolver->merge(
            $this->citationResolver->normalizeMany($localCitations, 'local_graph'),
            $this->citationResolver->normalizeMany($pubmedCitations, 'pubmed')
        );

        $reviewedCount = count($citations);
        $localCount = count($localCitations);
        $strictLocalHits = count(array_filter($localCitations, static fn(array $citation): bool => (($citation['match_mode'] ?? 'strict') === 'strict')));
        $broadLocalHits = count(array_filter($localCitations, static fn(array $citation): bool => (($citation['match_mode'] ?? 'strict') === 'broad')));

        foreach (array_slice($citations, 0, 5) as $citation) {
            $previewItems[] = [
                'title' => (string)(($citation['title'] ?? '') !== '' ? $citation['title'] : ('PMID ' . (string)($citation['pmid'] ?? ''))),
                'meta' => trim(implode(' | ', array_filter([
                    (string)($citation['source'] ?? ''),
                    (string)($citation['journal'] ?? ''),
                    (string)($citation['year'] ?? ''),
                    ($citation['pmid'] ?? '') !== '' ? 'PMID ' . (string)$citation['pmid'] : '',
                ]))),
                'url' => (string)($citation['url'] ?? ''),
                'body' => trim((string)($citation['abstract_summary'] ?? '')),
            ];
            $title = trim((string)($citation['title'] ?? ''));
            $pmid = trim((string)($citation['pmid'] ?? ''));
            $evidenceItems[] = tekg_agent_make_evidence_item(
                $this->getName(),
                $title !== ''
                    ? 'Literature evidence includes "' . $title . '"' . ($pmid !== '' ? ' (PMID ' . $pmid . ').' : '.')
                    : ($pmid !== '' ? 'Literature evidence includes PMID ' . $pmid . '.' : 'A literature record was selected for synthesis.'),
                $title !== '' ? $title : ($pmid !== '' ? 'PMID ' . $pmid : 'Literature record'),
                ($citation['source'] ?? '') === 'pubmed' ? 'medium' : 'high',
                [
                    'pmid' => $pmid,
                    'source' => (string)($citation['source'] ?? ''),
                    'query_term' => (string)($citation['query_term'] ?? ''),
                ],
                [
                    'title' => $title !== '' ? $title : ($pmid !== '' ? 'PMID ' . $pmid : 'Literature record'),
                    'meta' => trim(implode(' | ', array_filter([
                        (string)($citation['source'] ?? ''),
                        (string)($citation['journal'] ?? ''),
                        (string)($citation['year'] ?? ''),
                    ]))),
                    'body' => trim((string)($citation['abstract_summary'] ?? '')),
                    'url' => (string)($citation['url'] ?? ''),
                ]
            );
        }

        $displaySummary = $this->buildDisplaySummary($pubmedTotalCount, $reviewedCount, $localCount, $queryTerms !== []);
        $resultMessage = $this->buildResultMessage($citations, $pubmedTotalCount);

        return [
            'plugin_name' => $this->getName(),
            'status' => $citations === [] && $errors === [] ? 'empty' : ($errors === [] ? 'ok' : 'partial'),
            'query_summary' => $queryTerms === []
                ? 'Used local graph literature only.'
                : 'Collected local literature and queried PubMed via NCBI E-utilities.',
            'results' => [
                'query_terms' => $queryTerms,
                'local_citation_count' => $localCount,
                'pubmed_total_hits' => $pubmedTotalCount,
                'reviewed_citation_count' => $reviewedCount,
                'citations' => $citations,
            ],
            'display_label' => 'Reviewed ' . $reviewedCount . ' literature records',
            'display_summary' => $displaySummary,
            'display_details' => [
                'summary' => $displaySummary,
                'preview_items' => $previewItems,
                'evidence_items' => $evidenceItems,
                'citations' => $citations,
                'raw_preview' => [
                    'query_terms' => $queryTerms,
                    'local_citation_count' => $localCount,
                    'pubmed_total_hits' => $pubmedTotalCount,
                    'reviewed_citation_count' => $reviewedCount,
                    'citations' => $citations,
                ],
                'result_message' => $resultMessage,
            ],
            'result_counts' => [
                'local_hits' => $localCount,
                'strict_local_hits' => $strictLocalHits,
                'broad_local_hits' => $broadLocalHits,
                'pubmed_candidates' => $pubmedTotalCount,
                'reviewed' => $reviewedCount,
            ],
            'evidence_items' => $evidenceItems,
            'citations' => $citations,
            'errors' => $errors,
            'latency_ms' => (int)round((microtime(true) - $started) * 1000),
        ];
    }

    private function collectLocalCitations(array $graphResult, array $entities): array
    {
        $citations = is_array($graphResult['citations'] ?? null) ? $graphResult['citations'] : [];
        if ($citations !== []) {
            return $this->citationResolver->normalizeMany($citations, 'local_graph');
        }

        $rows = [];
        foreach ($entities as $entity) {
            $candidateGroups = $this->entityCandidateGroups($entity);
            $type = (string)($entity['type'] ?? 'TE');
            $sourceLabel = $type === 'Disease' ? 'Disease' : 'TE';
            $cypher = "MATCH (a:$sourceLabel)-[r]->(p:Paper)
                       WHERE replace(replace(replace(toLower(trim(coalesce(a.name,''))), '-', ''), '_', ''), ' ', '') = replace(replace(replace(toLower(trim(\$entity)), '-', ''), '_', ''), ' ', '')
                       RETURN coalesce(p.pmid,'') AS pmid, coalesce(p.name,'') AS title, '' AS year, '' AS journal";
            foreach ($candidateGroups as $mode => $candidates) {
                foreach ($candidates as $candidate) {
                    $candidateRows = $this->neo4j->run($cypher, ['entity' => $candidate]);
                    if ($candidateRows === []) {
                        continue;
                    }
                    foreach ($candidateRows as $candidateRow) {
                        $candidateRow['matched_alias'] = $candidate;
                        $candidateRow['match_mode'] = $mode;
                        $rows[] = $candidateRow;
                    }
                    break 2;
                }
            }
        }

        foreach ($rows as $row) {
            $citations[] = [
                'source' => 'local_graph',
                'pmid' => trim((string)($row['pmid'] ?? '')),
                'title' => trim((string)($row['title'] ?? '')),
                'year' => trim((string)($row['year'] ?? '')),
                'journal' => trim((string)($row['journal'] ?? '')),
                'matched_alias' => trim((string)($row['matched_alias'] ?? '')),
                'match_mode' => trim((string)($row['match_mode'] ?? 'strict')),
            ];
        }

        return $this->citationResolver->normalizeMany($citations, 'local_graph');
    }

    private function buildPubMedTerms(string $question, array $analysis, array $localCitations): array
    {
        $needsPubMed = ($analysis['needs_external_literature'] ?? false)
            || ($analysis['asks_for_papers'] ?? false)
            || ($analysis['compare_mode'] ?? false)
            || count($localCitations) < 3;

        if (!$needsPubMed) {
            return [];
        }

        $entities = is_array($analysis['normalized_entities'] ?? null) ? $analysis['normalized_entities'] : [];
        $teLabels = [];
        $diseaseLabels = [];
        foreach ($entities as $entity) {
            if (($entity['type'] ?? '') === 'TE') {
                $teLabels[] = (string)($entity['canonical_label'] ?? $entity['label'] ?? '');
            } elseif (($entity['type'] ?? '') === 'Disease') {
                $diseaseLabels[] = (string)($entity['canonical_label'] ?? $entity['label'] ?? '');
            }
        }

        $keywords = is_array($analysis['question_keywords'] ?? null) ? $analysis['question_keywords'] : [];
        if (($analysis['compare_mode'] ?? false) && count($diseaseLabels) >= 2 && $teLabels !== []) {
            return [
                $this->composeQueryTerm($teLabels[0], $diseaseLabels[0], $keywords, (string)($analysis['intent'] ?? 'relationship')),
                $this->composeQueryTerm($teLabels[0], $diseaseLabels[1], $keywords, (string)($analysis['intent'] ?? 'relationship')),
            ];
        }

        $term = $this->composeQueryTerm($teLabels[0] ?? '', $diseaseLabels[0] ?? '', $keywords, (string)($analysis['intent'] ?? 'relationship'));
        if ($term !== '') {
            return [$term];
        }
        return [trim($question)];
    }

    private function composeQueryTerm(string $te, string $disease, array $keywords, string $intent = 'relationship'): string
    {
        $parts = [];
        if ($te !== '') {
            $parts[] = '"' . $te . '"';
        }
        if ($disease !== '') {
            $parts[] = '"' . $disease . '"';
        }
        $normalizedKeywords = [];
        foreach ($keywords as $keyword) {
            $normalized = $this->normalizeKeywordForQuery((string)$keyword);
            if ($normalized !== '' && !in_array($normalized, $normalizedKeywords, true)) {
                $normalizedKeywords[] = $normalized;
            }
        }
        if ($normalizedKeywords === []) {
            $fallback = $this->fallbackKeywordForIntent($intent);
            if ($fallback !== '') {
                $normalizedKeywords[] = $fallback;
            }
        }
        foreach ($normalizedKeywords as $keyword) {
            $parts[] = '"' . $keyword . '"';
        }
        return implode(' AND ', array_filter($parts));
    }

    private function normalizeKeywordForQuery(string $keyword): string
    {
        $value = trim($keyword);
        if ($value === '') {
            return '';
        }
        if (!preg_match('/^[A-Za-z0-9 _\\-\\/]+$/', $value)) {
            return '';
        }
        return $value;
    }

    private function fallbackKeywordForIntent(string $intent): string
    {
        return match ($intent) {
            'sequence' => 'sequence',
            'mechanism' => 'mechanism',
            'literature' => 'evidence',
            'classification' => 'classification',
            'expression' => 'expression',
            'genome' => 'genome',
            'comparison' => 'comparison',
            default => '',
        };
    }

    private function searchPubMed(string $term): array
    {
        $cachePath = rtrim((string)$this->config['pubmed_cache_dir'], '/\\') . '/' . md5($term) . '.json';
        if (is_file($cachePath) && filemtime($cachePath) >= time() - 86400) {
            $cached = json_decode((string)file_get_contents($cachePath), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $base = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/';
        $common = ['db' => 'pubmed', 'retmode' => 'json', 'tool' => (string)$this->config['pubmed_tool']];
        if (($this->config['pubmed_email'] ?? '') !== '') {
            $common['email'] = (string)$this->config['pubmed_email'];
        }

        $search = $this->httpJson($base . 'esearch.fcgi?' . http_build_query($common + ['term' => $term, 'retmax' => 8, 'sort' => 'relevance']));
        $totalCount = (int)($search['esearchresult']['count'] ?? 0);
        $ids = array_values(array_filter((array)($search['esearchresult']['idlist'] ?? []), static fn($id): bool => trim((string)$id) !== ''));
        if ($ids === []) {
            $result = ['term' => $term, 'total_count' => $totalCount, 'citations' => []];
            file_put_contents($cachePath, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            return $result;
        }

        $summary = $this->httpJson($base . 'esummary.fcgi?' . http_build_query($common + ['id' => implode(',', $ids)]));
        $abstracts = $this->fetchPubMedAbstracts($ids, $common);
        $citations = [];
        foreach ($ids as $pmid) {
            $doc = $summary['result'][$pmid] ?? [];
            if (!is_array($doc)) {
                continue;
            }
            $citations[] = [
                'source' => 'pubmed',
                'pmid' => (string)$pmid,
                'title' => trim((string)($doc['title'] ?? '')),
                'year' => trim((string)($doc['pubdate'] ?? '')),
                'journal' => trim((string)($doc['fulljournalname'] ?? '')),
                'url' => 'https://pubmed.ncbi.nlm.nih.gov/' . rawurlencode((string)$pmid) . '/',
                'relevance' => 'PubMed external search',
                'abstract_summary' => $this->summarizeAbstract($abstracts[$pmid] ?? ''),
                'query_term' => $term,
            ];
        }

        $result = [
            'term' => $term,
            'total_count' => $totalCount,
            'citations' => $citations,
        ];
        file_put_contents($cachePath, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        return $result;
    }

    private function fetchPubMedAbstracts(array $ids, array $common): array
    {
        $base = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/';
        $xml = $this->httpText($base . 'efetch.fcgi?' . http_build_query($common + ['id' => implode(',', $ids), 'rettype' => 'abstract', 'retmode' => 'xml']));
        $parsed = @simplexml_load_string($xml);
        if ($parsed === false) {
            return [];
        }

        $abstracts = [];
        foreach ($parsed->PubmedArticle as $article) {
            $pmid = trim((string)($article->MedlineCitation->PMID ?? ''));
            $pieces = [];
            foreach ($article->MedlineCitation->Article->Abstract->AbstractText ?? [] as $fragment) {
                $pieces[] = trim((string)$fragment);
            }
            if ($pmid !== '') {
                $abstracts[$pmid] = trim(implode(' ', array_filter($pieces)));
            }
        }
        return $abstracts;
    }

    private function summarizeAbstract(string $abstract): string
    {
        $abstract = trim(preg_replace('/\s+/u', ' ', $abstract) ?? $abstract);
        if ($abstract === '') {
            return '';
        }
        return tekg_agent_strlen($abstract) > 280 ? tekg_agent_substr($abstract, 0, 277) . '...' : $abstract;
    }

    private function buildDisplaySummary(int $pubmedTotalCount, int $reviewedCount, int $localCount, bool $usedPubMed): string
    {
        if ($usedPubMed) {
            return 'I combined the local paper evidence with an external PubMed search, found ' . $pubmedTotalCount . ' candidate records, and reviewed ' . $reviewedCount . ' citations that were worth carrying into the answer.';
        }
        return 'This round mainly relied on local graph literature evidence and assembled ' . $localCount . ' directly citable records.';
    }

    private function buildResultMessage(array $citations, int $pubmedTotalCount): string
    {
        if ($citations === []) {
            return 'This round did not yield stable literature evidence, so I will need stronger local context or more specific search terms.';
        }
        return $pubmedTotalCount > 0
            ? 'These papers mainly cover mechanism, cancer relevance, and disease evidence, which is enough to support the next synthesis step.'
            : 'The current local literature is already strong enough to support a first evidence-backed answer.';
    }

    private function httpJson(string $url): array
    {
        $text = $this->httpText($url);
        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('PubMed returned invalid JSON.');
        }
        return $decoded;
    }

    private function httpText(string $url): string
    {
        $response = tekg_agent_http_request($url, 'GET', ['Accept: application/json, text/xml'], null, 45, (bool)($this->config['ssl_verify'] ?? false));
        if ((int)$response['status'] >= 400) {
            throw new RuntimeException('PubMed returned HTTP ' . (int)$response['status']);
        }
        return (string)$response['body'];
    }

    private function entityCandidateGroups(array $entity): array
    {
        return tekg_agent_entity_candidate_groups($entity);
    }
}
