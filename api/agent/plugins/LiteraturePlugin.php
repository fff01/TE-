<?php
declare(strict_types=1);

final class TekgAgentLiteraturePlugin implements TekgAgentPluginInterface
{
    public function __construct(
        private readonly TekgAgentNeo4jClient $neo4j,
        private readonly array $config,
    ) {
    }

    public function getName(): string
    {
        return 'Literature Plugin';
    }

    public function run(array $context): array
    {
        $started = microtime(true);
        $analysis = $context['analysis'] ?? [];
        $entities = is_array($analysis['normalized_entities'] ?? null) ? $analysis['normalized_entities'] : [];
        $graphResult = $context['plugin_results']['Graph Plugin'] ?? [];
        $localCitations = $this->collectLocalCitations($graphResult, $entities);
        $queryTerms = $this->buildPubMedTerms((string)($context['question'] ?? ''), $analysis, $localCitations);
        $pubmedCitations = [];
        $evidence = [];
        $errors = [];

        foreach ($queryTerms as $term) {
            try {
                $pubmedCitations = array_merge($pubmedCitations, $this->searchPubMed($term));
                $evidence[] = 'PubMed query: ' . $term;
            } catch (Throwable $error) {
                $errors[] = 'PubMed query failed for "' . $term . '": ' . $error->getMessage();
            }
        }

        $citations = $this->dedupeCitations(array_merge($localCitations, $pubmedCitations));
        $evidence[] = 'Local literature hits: ' . count($localCitations);
        if ($queryTerms !== []) {
            $evidence[] = 'PubMed citations retrieved: ' . count($pubmedCitations);
        }

        return [
            'plugin_name' => $this->getName(),
            'status' => $citations === [] ? 'empty' : 'ok',
            'query_summary' => $queryTerms === [] ? 'Used local graph literature only.' : 'Collected local literature and queried PubMed via NCBI E-utilities.',
            'results' => [
                'query_terms' => $queryTerms,
                'local_citation_count' => count($localCitations),
                'pubmed_citation_count' => count($pubmedCitations),
            ],
            'evidence_items' => $evidence,
            'citations' => $citations,
            'errors' => $errors,
            'latency_ms' => (int)round((microtime(true) - $started) * 1000),
        ];
    }

    private function collectLocalCitations(array $graphResult, array $entities): array
    {
        $citations = is_array($graphResult['citations'] ?? null) ? $graphResult['citations'] : [];
        if ($citations !== []) {
            return $this->dedupeCitations($citations);
        }
        $rows = [];
        foreach ($entities as $entity) {
            $label = trim((string)($entity['label'] ?? ''));
            $type = (string)($entity['type'] ?? 'TE');
            if ($label === '') {
                continue;
            }
            $sourceLabel = $type === 'Disease' ? 'Disease' : 'TE';
            $cypher = "MATCH (a:$sourceLabel)-[r]->(p:Paper)\nWHERE replace(replace(replace(toLower(trim(coalesce(a.name,''))), '-', ''), '_', ''), ' ', '') = replace(replace(replace(toLower(trim(\$entity)), '-', ''), '_', ''), ' ', '')\nRETURN coalesce(p.pmid,'') AS pmid, coalesce(p.name,'') AS title, '' AS year, '' AS journal";
            $rows = array_merge($rows, $this->neo4j->run($cypher, ['entity' => $label]));
        }
        foreach ($rows as $row) {
            $pmid = trim((string)($row['pmid'] ?? ''));
            $citations[] = [
                'source' => 'local_graph',
                'pmid' => $pmid,
                'title' => trim((string)($row['title'] ?? '')),
                'year' => trim((string)($row['year'] ?? '')),
                'journal' => trim((string)($row['journal'] ?? '')),
                'url' => $pmid !== '' ? 'https://pubmed.ncbi.nlm.nih.gov/' . rawurlencode($pmid) . '/' : '',
            ];
        }
        return $this->dedupeCitations($citations);
    }

    private function buildPubMedTerms(string $question, array $analysis, array $localCitations): array
    {
        $needsPubMed = ($analysis['asks_for_papers'] ?? false) || ($analysis['compare_mode'] ?? false) || count($localCitations) < 3;
        if (!$needsPubMed) {
            return [];
        }
        $entities = is_array($analysis['normalized_entities'] ?? null) ? $analysis['normalized_entities'] : [];
        $teLabels = [];
        $diseaseLabels = [];
        foreach ($entities as $entity) {
            if (($entity['type'] ?? '') === 'TE') {
                $teLabels[] = (string)$entity['label'];
            } elseif (($entity['type'] ?? '') === 'Disease') {
                $diseaseLabels[] = (string)$entity['label'];
            }
        }
        $keywords = is_array($analysis['question_keywords'] ?? null) ? $analysis['question_keywords'] : [];
        if (($analysis['compare_mode'] ?? false) && count($diseaseLabels) >= 2 && $teLabels !== []) {
            return [
                $this->composeQueryTerm($teLabels[0], $diseaseLabels[0], $keywords),
                $this->composeQueryTerm($teLabels[0], $diseaseLabels[1], $keywords),
            ];
        }
        $term = $this->composeQueryTerm($teLabels[0] ?? '', $diseaseLabels[0] ?? '', $keywords);
        if ($term !== '') {
            return [$term];
        }
        return [trim($question)];
    }

    private function composeQueryTerm(string $te, string $disease, array $keywords): string
    {
        $parts = [];
        if ($te !== '') {
            $parts[] = '"' . $te . '"';
        }
        if ($disease !== '') {
            $parts[] = '"' . $disease . '"';
        }
        foreach ($keywords as $keyword) {
            $parts[] = '"' . $keyword . '"';
        }
        return implode(' AND ', $parts);
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

        $search = $this->httpJson($base . 'esearch.fcgi?' . http_build_query($common + ['term' => $term, 'retmax' => 5, 'sort' => 'relevance']));
        $ids = array_values(array_filter((array)($search['esearchresult']['idlist'] ?? []), static fn($id): bool => trim((string)$id) !== ''));
        if ($ids === []) {
            file_put_contents($cachePath, json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            return [];
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
        file_put_contents($cachePath, json_encode($citations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        return $citations;
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
        return mb_strlen($abstract, 'UTF-8') > 280 ? mb_substr($abstract, 0, 277, 'UTF-8') . '...' : $abstract;
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
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => ['Accept: application/json, text/xml'],
        ]);
        if (($this->config['ssl_verify'] ?? false) !== true) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch) ?: 'Unknown PubMed cURL failure';
            curl_close($ch);
            throw new RuntimeException($error);
        }
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status >= 400) {
            throw new RuntimeException('PubMed returned HTTP ' . $status);
        }
        return (string)$raw;
    }
}
