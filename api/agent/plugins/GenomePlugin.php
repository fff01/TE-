<?php
declare(strict_types=1);

final class TekgAgentGenomePlugin implements TekgAgentPluginInterface
{
    private ?array $representativeIndex = null;

    public function getName(): string
    {
        return 'Genome Plugin';
    }

    public function run(array $context): array
    {
        $started = microtime(true);
        $analysis = $context['analysis'] ?? [];
        $entities = is_array($analysis['normalized_entities'] ?? null) ? $analysis['normalized_entities'] : [];
        $results = [];
        $evidence = [];
        foreach ($entities as $entity) {
            if (($entity['type'] ?? '') !== 'TE') {
                continue;
            }
            $label = (string)$entity['label'];
            $hitBundle = $this->loadHitBundle($label);
            if ($hitBundle === null) {
                continue;
            }
            $locus = $hitBundle['representative_locus'] ?? [];
            $chrom = (string)($locus['chrom'] ?? '');
            $start = (int)($locus['start'] ?? 0);
            $end = (int)($locus['end'] ?? 0);
            $jbrowseUrl = site_url_with_state('/TE-/jbrowse.php', site_lang(), site_renderer(), [
                'te' => $label,
                'chr' => $chrom,
                'start' => $start,
                'end' => $end,
            ]);
            $results[] = [
                'te_name' => $label,
                'total_hits' => (int)($hitBundle['total_hits'] ?? 0),
                'representative_locus' => $locus,
                'sample_hits' => array_slice((array)($hitBundle['sample_hits'] ?? []), 0, 5),
                'jbrowse_url' => $jbrowseUrl,
            ];
            if ($chrom !== '' && $start > 0 && $end > 0) {
                $evidence[] = $label . ' representative locus: ' . $chrom . ':' . $start . '-' . $end . ' with ' . (int)($hitBundle['total_hits'] ?? 0) . ' total hits';
            }
        }
        return [
            'plugin_name' => $this->getName(),
            'status' => $results === [] ? 'empty' : 'ok',
            'query_summary' => 'Loaded representative genomic hits and browser loci for recognized TE entities.',
            'results' => $results,
            'evidence_items' => $evidence,
            'citations' => [],
            'errors' => [],
            'latency_ms' => (int)round((microtime(true) - $started) * 1000),
        ];
    }

    private function loadHitBundle(string $teName): ?array
    {
        $path = tekg_jbrowse_fs_path('repeats/te_hits/' . $teName . '.json');
        if (!is_file($path)) {
            return null;
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }
}
