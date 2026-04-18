<?php
declare(strict_types=1);

final class TekgAgentSequencePlugin implements TekgAgentPluginInterface
{
    private ?array $dataset = null;

    public function getName(): string
    {
        return 'Sequence Plugin';
    }

    public function run(array $context): array
    {
        $started = microtime(true);
        $analysis = is_array($context['analysis'] ?? null) ? $context['analysis'] : [];
        $chains = is_array($analysis['alias_chains'] ?? null) ? $analysis['alias_chains'] : [];
        $dataset = $this->loadDataset();

        $matches = [];
        foreach ($chains as $chain) {
            if (($chain['type'] ?? '') !== 'TE') {
                continue;
            }
            $match = $this->resolveEntry($chain, $dataset);
            if ($match !== null) {
                $matches[] = $match;
            }
        }

        $matches = array_values(array_slice($matches, 0, 3));
        $previewItems = [];
        $evidenceItems = [];
        $citations = [];

        foreach ($matches as $match) {
            $entry = $match['entry'];
            $name = (string)($entry['name'] ?? $match['repbase_name'] ?? '');
            $length = $this->extractLength($entry);
            $headline = trim((string)($entry['sequence_summary']['headline'] ?? ''));
            $keywords = array_values(array_slice(is_array($entry['keywords'] ?? null) ? $entry['keywords'] : [], 0, 6));
            $structureHints = $this->structureHints($entry);
            $sequencePreview = $this->sequencePreview((string)($entry['sequence'] ?? ''));

            $previewItems[] = [
                'title' => $name,
                'meta' => trim(implode(' | ', array_filter([
                    $match['entity_label'] !== $name ? 'matched from ' . $match['entity_label'] : '',
                    $length !== null ? $length . ' bp' : '',
                    $headline,
                ]))),
                'body' => trim(implode(' | ', array_filter([
                    $structureHints !== '' ? 'Structure: ' . $structureHints : '',
                    $keywords !== [] ? 'Keywords: ' . implode(', ', array_map('strval', $keywords)) : '',
                    $sequencePreview !== '' ? 'Sequence: ' . $sequencePreview : '',
                ]))),
            ];

            $evidenceItems[] = $name . ' maps to a Repbase-backed sequence record' .
                ($length !== null ? ' with a consensus length of ' . $length . ' bp' : '') .
                ($structureHints !== '' ? ' and structure hints including ' . $structureHints : '') . '.';

            foreach ((array)($entry['references'] ?? []) as $reference) {
                if (!is_array($reference)) {
                    continue;
                }
                $citations[] = [
                    'source' => 'repbase',
                    'title' => trim((string)($reference['title'] ?? '')),
                    'journal' => trim((string)($reference['journal'] ?? '')),
                    'authors' => trim((string)($reference['authors'] ?? '')),
                    'year' => $this->extractYear((string)($reference['journal'] ?? '')),
                    'relevance' => 'Repbase sequence record reference',
                ];
            }
        }

        $summary = $matches !== []
            ? 'I matched ' . count($matches) . ' TE sequence records from the Repbase-aligned library and extracted consensus length, sequence, and structure hints.'
            : 'No Repbase-backed sequence record could be matched for the recognized TE entities in this round.';

        return [
            'plugin_name' => $this->getName(),
            'status' => $matches !== [] ? 'ok' : 'empty',
            'query_summary' => 'Matched recognized TE entities against the Repbase-backed sequence library.',
            'results' => [
                'matched_records' => $matches,
            ],
            'display_label' => 'Resolved ' . count($matches) . ' sequence records',
            'display_summary' => $summary,
            'display_details' => [
                'summary' => $summary,
                'preview_items' => $previewItems,
                'evidence_items' => $evidenceItems,
                'citations' => $citations,
                'raw_preview' => ['matched_records' => $matches],
                'result_message' => $matches !== []
                    ? 'These sequence-backed records add consensus length, annotation, and structure hints that can stabilize TE-specific answers.'
                    : 'This round did not find a stable Repbase-backed sequence entry for the current TE entities.',
            ],
            'result_counts' => [
                'matched_records' => count($matches),
            ],
            'evidence_items' => $evidenceItems,
            'citations' => $citations,
            'errors' => [],
            'latency_ms' => (int)round((microtime(true) - $started) * 1000),
        ];
    }

    private function loadDataset(): array
    {
        if (is_array($this->dataset)) {
            return $this->dataset;
        }

        $path = TEKG_DATA_FS_DIR . '/processed/te_repbase_db_matched.json';
        $decoded = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
        $decoded = is_array($decoded) ? $decoded : [];

        $entriesByName = [];
        foreach ((array)($decoded['entries'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $name = trim((string)($entry['name'] ?? $entry['id'] ?? ''));
            if ($name === '') {
                continue;
            }
            $entriesByName[$name] = $entry;
        }

        $this->dataset = [
            'db_to_repbase' => is_array($decoded['db_to_repbase'] ?? null) ? $decoded['db_to_repbase'] : [],
            'entries_by_name' => $entriesByName,
        ];
        return $this->dataset;
    }

    private function resolveEntry(array $chain, array $dataset): ?array
    {
        $candidates = [];
        $canonical = trim((string)($chain['canonical_label'] ?? $chain['label'] ?? ''));
        if ($canonical !== '') {
            $candidates[] = $canonical;
        }
        foreach ((array)($chain['aliases'] ?? []) as $alias) {
            $value = trim((string)$alias);
            if ($value !== '') {
                $candidates[] = $value;
            }
        }
        $candidates = array_values(array_unique($candidates));

        $dbToRepbase = is_array($dataset['db_to_repbase'] ?? null) ? $dataset['db_to_repbase'] : [];
        $entriesByName = is_array($dataset['entries_by_name'] ?? null) ? $dataset['entries_by_name'] : [];

        foreach ($candidates as $candidate) {
            $repbaseName = trim((string)($dbToRepbase[$candidate] ?? ''));
            if ($repbaseName !== '' && isset($entriesByName[$repbaseName])) {
                return [
                    'entity_label' => $canonical !== '' ? $canonical : $candidate,
                    'matched_alias' => $candidate,
                    'repbase_name' => $repbaseName,
                    'entry' => $entriesByName[$repbaseName],
                ];
            }
            if (isset($entriesByName[$candidate])) {
                return [
                    'entity_label' => $canonical !== '' ? $canonical : $candidate,
                    'matched_alias' => $candidate,
                    'repbase_name' => $candidate,
                    'entry' => $entriesByName[$candidate],
                ];
            }
            $normalizedCandidate = tekg_agent_normalize_lookup_token($candidate);
            foreach ($entriesByName as $name => $entry) {
                if (tekg_agent_normalize_lookup_token((string)$name) === $normalizedCandidate) {
                    return [
                        'entity_label' => $canonical !== '' ? $canonical : $candidate,
                        'matched_alias' => $candidate,
                        'repbase_name' => $name,
                        'entry' => $entry,
                    ];
                }
            }

        }

        return null;
    }

    private function extractLength(array $entry): ?int
    {
        $summary = is_array($entry['sequence_summary'] ?? null) ? $entry['sequence_summary'] : [];
        if (isset($summary['headline']) && preg_match('/(\d+)\s*BP/i', (string)$summary['headline'], $matches) === 1) {
            return (int)$matches[1];
        }
        $sequence = preg_replace('/\s+/u', '', (string)($entry['sequence'] ?? '')) ?? '';
        return $sequence !== '' ? strlen($sequence) : null;
    }

    private function structureHints(array $entry): string
    {
        $text = tekg_agent_lower(trim(implode(' ', array_filter([
            (string)($entry['description'] ?? ''),
            implode(' ', array_map('strval', is_array($entry['keywords'] ?? null) ? $entry['keywords'] : [])),
        ]))));
        $hints = [];
        foreach (['ltr', 'orf1', 'orf2', 'env', 'gag', 'pol', 'sine', 'line', 'dna transposon', 'retrovirus', 'vntr'] as $keyword) {
            if (str_contains($text, $keyword)) {
                $hints[] = strtoupper($keyword);
            }
        }
        return implode(', ', array_values(array_unique($hints)));
    }

    private function sequencePreview(string $sequence): string
    {
        $compact = preg_replace('/\s+/u', '', trim($sequence)) ?? '';
        if ($compact === '') {
            return '';
        }
        return tekg_agent_strlen($compact) > 80 ? tekg_agent_substr($compact, 0, 80) . '...' : $compact;
    }

    private function extractYear(string $journal): string
    {
        return preg_match('/\b(19|20)\d{2}\b/', $journal, $matches) === 1 ? $matches[0] : '';
    }
}
