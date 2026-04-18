<?php
require_once __DIR__ . '/site_i18n.php';
require_once __DIR__ . '/path_config.php';

$pageTitle = 'TE-KG Detail';
$activePage = 'browse';
$protoCurrentPath = '/TE-/search.php';
$protoSubtitle = 'TE detail view';

function tekg_clean_label_proto(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/<[^>]+>/', '', $value) ?? $value;
    $value = rtrim($value, ".;,");
    return preg_replace('/\s+/', ' ', $value) ?? $value;
}

function tekg_lower_proto(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
}

function tekg_canonicalize_label_proto(string $value): string
{
    return str_replace(['_', '-', ' '], '', tekg_lower_proto(tekg_clean_label_proto($value)));
}
function tekg_jbrowse_project_relative_path_proto(string $relativePath): string
{
    $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
    if ($normalized === '') {
        return 'data/JBrowse';
    }
    if (str_starts_with($normalized, 'data/JBrowse/')) {
        return $normalized;
    }
    if ($normalized === 'data/JBrowse') {
        return $normalized;
    }
    if (str_starts_with($normalized, 'JBrowse/')) {
        return 'data/' . $normalized;
    }
    if ($normalized === 'JBrowse') {
        return 'data/JBrowse';
    }
    $marker = '/JBrowse/';
    $markerPos = strpos($normalized, $marker);
    if ($markerPos !== false) {
        return 'data/JBrowse/' . substr($normalized, $markerPos + strlen($marker));
    }
    if (str_ends_with($normalized, '/JBrowse')) {
        return 'data/JBrowse';
    }
    return 'data/JBrowse/' . $normalized;
}

function tekg_jbrowse_project_fs_path_proto(string $relativePath): string
{
    return tekg_fs_from_project_relative(tekg_jbrowse_project_relative_path_proto($relativePath));
}

function tekg_repbase_lookup_proto(string $query): ?array
{
    $query = trim($query);
    if ($query === '') {
        return null;
    }

    $file = __DIR__ . '/data/processed/te_repbase_db_matched.json';
    if (!is_file($file)) {
        return null;
    }

    $payload = json_decode((string) file_get_contents($file), true);
    if (!is_array($payload)) {
        return null;
    }

    $strictKey = tekg_lower_proto(tekg_clean_label_proto($query));
    $canonicalKey = tekg_canonicalize_label_proto($query);
    $entryId = $payload['name_index'][$strictKey] ?? $payload['canonical_index'][$canonicalKey] ?? null;
    if (!$entryId || empty($payload['entries']) || !is_array($payload['entries'])) {
        return null;
    }

    foreach ($payload['entries'] as $entry) {
        if (($entry['id'] ?? '') !== $entryId) {
            continue;
        }
        $sequenceSummary = (string) (($entry['sequence_summary']['raw'] ?? '') ?: '');
        $lengthBp = null;
        if ($sequenceSummary !== '' && preg_match('/(\d+)\s*BP/i', $sequenceSummary, $matches) === 1) {
            $lengthBp = (int) $matches[1];
        } else {
            $sequence = preg_replace('/\s+/', '', (string) ($entry['sequence'] ?? '')) ?? '';
            if ($sequence !== '') {
                $lengthBp = strlen($sequence);
            }
        }
        return [
            'matched' => $query,
            'id' => (string) ($entry['id'] ?? ''),
            'nm' => (string) ($entry['name'] ?? ''),
            'description' => (string) ($entry['description'] ?? ''),
            'keywords' => is_array($entry['keywords'] ?? null) ? implode(', ', $entry['keywords']) : '',
            'species' => (string) ($entry['species'] ?? ''),
            'classification' => is_array($entry['classification'] ?? null) ? implode(' > ', $entry['classification']) : '',
            'sequence_summary' => $sequenceSummary,
            'length_bp' => $lengthBp,
            'reference_count' => is_array($entry['references'] ?? null) ? count($entry['references']) : 0,
            'sequence' => (string) ($entry['sequence'] ?? ''),
        ];
    }

    return null;
}

function tekg_dfam_lookup_index_proto(): ?array
{
    static $lookup = null;
    static $loaded = false;
    if ($loaded) {
        return $lookup;
    }
    $loaded = true;
    $file = __DIR__ . '/data/processed/dfam/dfam_lookup_index.json';
    if (!is_file($file)) {
        return null;
    }
    $decoded = json_decode((string) file_get_contents($file), true);
    $lookup = is_array($decoded) ? $decoded : null;
    return $lookup;
}

function tekg_dfam_entry_proto(string $accession): ?array
{
    static $cache = [];
    if (isset($cache[$accession])) {
        return $cache[$accession];
    }
    $file = __DIR__ . '/data/processed/dfam/entries/' . $accession . '.json';
    if (!is_file($file)) {
        $cache[$accession] = null;
        return null;
    }
    $decoded = json_decode((string) file_get_contents($file), true);
    $cache[$accession] = is_array($decoded) ? $decoded : null;
    return $cache[$accession];
}

function tekg_dfam_model_label_proto(string $modelType): string
{
    $labels = [
        'full' => 'Full consensus model',
        'fragment_3end' => "3' end fragment model",
        'fragment_5end' => "5' end fragment model",
        'fragment_internal' => 'Internal fragment model',
        'fragment_ltr' => 'LTR fragment model',
        'unknown_fragment' => 'Fragment model',
    ];
    return $labels[$modelType] ?? 'Consensus model';
}

function tekg_dfam_plot_relative_path_proto(string $accession): string
{
    return '/TE-/data/processed/dfam/plots/' . rawurlencode($accession) . '.svg';
}

function tekg_dfam_plot_filesystem_path_proto(string $accession): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'processed' . DIRECTORY_SEPARATOR . 'dfam' . DIRECTORY_SEPARATOR . 'plots' . DIRECTORY_SEPARATOR . $accession . '.svg';
}

function tekg_run_python_for_dfam_plot_proto(string $accession): bool
{
    $script = __DIR__ . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'plot' . DIRECTORY_SEPARATOR . 'render_dfam_structure_svg.py';
    if (!is_file($script)) {
        return false;
    }

    $commands = [
        'py -3',
        'python',
    ];

    foreach ($commands as $command) {
        @shell_exec($command . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($accession) . ' 2>&1');
        if (is_file(tekg_dfam_plot_filesystem_path_proto($accession))) {
            return true;
        }
    }

    return false;
}

function tekg_dfam_structure_svg_path_proto(array $entry): ?string
{
    $accession = trim((string) ($entry['accession'] ?? ''));
    if ($accession === '' || empty($entry['sequence']) || empty($entry['length_bp'])) {
        return null;
    }

    $svgFile = tekg_dfam_plot_filesystem_path_proto($accession);
    $catalogFile = __DIR__ . '/data/processed/dfam/dfam_curated_catalog.json';
    $rendererScript = __DIR__ . '/scripts/plot/render_dfam_structure_svg.py';
    $baseRenderer = __DIR__ . '/scripts/plot/base_SVG.py';
    $needsRender = !is_file($svgFile);

    if (!$needsRender) {
        $svgTime = @filemtime($svgFile) ?: 0;
        $sourceTime = max(
            @filemtime($catalogFile) ?: 0,
            @filemtime($rendererScript) ?: 0,
            @filemtime($baseRenderer) ?: 0
        );
        $needsRender = $svgTime < $sourceTime;
    }

    if ($needsRender && !tekg_run_python_for_dfam_plot_proto($accession)) {
        return null;
    }

    return is_file($svgFile) ? tekg_dfam_plot_relative_path_proto($accession) : null;
}


function tekg_repbase_structure_svg_url_proto(?array $repbase, string $query): ?string
{
    $candidate = '';
    if (is_array($repbase)) {
        foreach (['nm', 'id'] as $key) {
            $value = trim((string) ($repbase[$key] ?? ''));
            if ($value !== '') {
                $candidate = $value;
                break;
            }
        }
    }

    if ($candidate === '') {
        $candidate = trim($query);
    }
    if ($candidate === '') {
        return null;
    }

    return '/TE-/repbase_structure_svg.php?te=' . rawurlencode($candidate);
}

function tekg_dfam_lookup_proto(string $query, string $type = 'all'): ?array
{
    $query = trim($query);
    if ($query === '') {
        return null;
    }

    $normalizedType = strtoupper(trim($type));
    if (in_array($normalizedType, ['DISEASE', 'FUNCTION', 'PAPER'], true)) {
        return null;
    }

    $lookup = tekg_dfam_lookup_index_proto();
    if (!is_array($lookup)) {
        return null;
    }

    $strictKey = tekg_lower_proto(tekg_clean_label_proto($query));
    $canonicalKey = tekg_canonicalize_label_proto($query);
    $accession = $lookup['name_index'][$strictKey] ?? $lookup['canonical_index'][$canonicalKey] ?? null;
    if (!is_string($accession) || $accession === '') {
        return null;
    }

    $entry = tekg_dfam_entry_proto($accession);
    if (!is_array($entry)) {
        return null;
    }

    $entry['matched_query'] = $query;
    $entry['sequence_length_bp'] = (int) ($entry['length_bp'] ?? 0);
    $entry['model_type_label'] = tekg_dfam_model_label_proto((string) ($entry['model_type'] ?? 'full'));
    $entry['structure_svg_path'] = tekg_dfam_structure_svg_path_proto($entry);
    return $entry;
}

function tekg_karyotype_index_proto(): ?array
{
    static $lookup = null;
    static $loaded = false;
    if ($loaded) {
        return $lookup;
    }
    $loaded = true;
    $file = __DIR__ . '/data/processed/rmsk/karyotype_index.json';
    if (!is_file($file)) {
        return null;
    }
    $decoded = json_decode((string) file_get_contents($file), true);
    $lookup = is_array($decoded) ? $decoded : null;
    return $lookup;
}

function tekg_karyotype_lookup_proto(string $query, string $type = 'all', ?array $repbase = null): ?array
{
    $query = trim($query);
    if ($query === '') {
        return null;
    }

    $normalizedType = strtoupper(trim($type));
    if (in_array($normalizedType, ['DISEASE', 'FUNCTION', 'PAPER'], true)) {
        return null;
    }

    $lookup = tekg_karyotype_index_proto();
    if (!is_array($lookup)) {
        return null;
    }

    $candidates = [$query];
    if (is_array($repbase)) {
        foreach (['nm', 'id'] as $key) {
            $candidate = trim((string) ($repbase[$key] ?? ''));
            if ($candidate !== '') {
                $candidates[] = $candidate;
            }
        }
    }

    $teName = null;
    foreach ($candidates as $candidate) {
        $strictKey = tekg_lower_proto(tekg_clean_label_proto($candidate));
        $canonicalKey = tekg_canonicalize_label_proto($candidate);
        $teName = $lookup['name_index'][$strictKey] ?? $lookup['canonical_index'][$canonicalKey] ?? null;
        if (is_string($teName) && $teName !== '') {
            break;
        }
    }

    if (!is_string($teName) || $teName === '') {
        return null;
    }

    $entry = $lookup['entries'][$teName] ?? null;
    if (!is_array($entry)) {
        return null;
    }

    $entry['matched_query'] = $query;
    $entry['resolved_te_name'] = $teName;
    return $entry;
}


function tekg_project_relative_from_site_path_proto(string $path): string
{
    $normalized = trim(str_replace('\\', '/', $path));
    if ($normalized === '') {
        return '';
    }
    if (str_starts_with($normalized, '/TE-/')) {
        $normalized = substr($normalized, 5);
    }
    return ltrim($normalized, '/');
}

function tekg_jbrowse_bin_size_proto(): int
{
    return 1000000;
}

function tekg_jbrowse_bin_cache_directory_proto(): string
{
    $dir = tekg_jbrowse_project_fs_path_proto('repeats/bin_hits');
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    return $dir;
}

function tekg_jbrowse_bin_cache_path_proto(string $teName): string
{
    $slug = preg_replace('/[^A-Za-z0-9._-]+/', '_', $teName) ?? 'te';
    $slug = trim($slug, '_');
    if ($slug === '') {
        $slug = 'te';
    }
    return tekg_jbrowse_bin_cache_directory_proto() . DIRECTORY_SEPARATOR . $slug . '__' . substr(sha1($teName), 0, 12) . '.json';
}

function tekg_jbrowse_hit_label_proto(string $chrom, int $start, int $end, string $strand, int $length, int $score): string
{
    return sprintf(
        '%s:%s-%s | %s | len %s bp | score %s',
        $chrom,
        number_format($start + 1),
        number_format($end),
        $strand === '-' ? 'reverse strand' : 'forward strand',
        number_format($length),
        number_format($score)
    );
}

function tekg_jbrowse_build_bin_hits_for_te_proto(string $teName, int $binSize): ?array
{
    $rmskPath = __DIR__ . '/data/rmsk.txt';
    if (!is_file($rmskPath)) {
        return null;
    }

    $primaryChroms = array_fill_keys(array_merge(
        array_map(static fn (int $i): string => 'chr' . $i, range(1, 22)),
        ['chrX', 'chrY']
    ), true);

    $handle = @fopen($rmskPath, 'r');
    if ($handle === false) {
        return null;
    }

    $bins = [];
    $totalHits = 0;

    try {
        while (($raw = fgets($handle)) !== false) {
            if (!is_string($raw) || trim($raw) === '') {
                continue;
            }
            $parts = explode("	", rtrim($raw, "

"));
            if (count($parts) < 15) {
                continue;
            }

            $repName = (string) ($parts[10] ?? '');
            if ($repName !== $teName) {
                continue;
            }

            $chrom = (string) ($parts[5] ?? '');
            if ($chrom === '' || !isset($primaryChroms[$chrom])) {
                continue;
            }

            $start = isset($parts[6]) ? (int) $parts[6] : -1;
            $end = isset($parts[7]) ? (int) $parts[7] : -1;
            if ($start < 0 || $end <= $start) {
                continue;
            }

            $strand = ((string) ($parts[9] ?? '+')) === '-' ? '-' : '+';
            $length = max(1, $end - $start);
            $score = isset($parts[1]) ? (int) $parts[1] : 0;

            $hit = [
                'chrom' => $chrom,
                'start' => $start,
                'end' => $end,
                'strand' => $strand,
                'length' => $length,
                'score' => $score,
                'label' => tekg_jbrowse_hit_label_proto($chrom, $start, $end, $strand, $length, $score),
            ];

            $totalHits += 1;
            $startBin = intdiv(max(0, $start), $binSize);
            $endBin = intdiv(max(0, $end - 1), $binSize);
            for ($binIndex = $startBin; $binIndex <= $endBin; $binIndex++) {
                $binStart = ($binIndex * $binSize) + 1;
                $binEnd = ($binIndex + 1) * $binSize;
                $key = $chrom . ':' . $binStart . '-' . $binEnd;
                if (!isset($bins[$key])) {
                    $bins[$key] = [
                        'chrom' => $chrom,
                        'start' => $binStart,
                        'end' => $binEnd,
                        'count' => 0,
                        'hits' => [],
                    ];
                }
                $bins[$key]['hits'][] = $hit;
                $bins[$key]['count'] += 1;
            }
        }
    } finally {
        fclose($handle);
    }

    if ($bins === []) {
        return [
            'te' => $teName,
            'bin_size_bp' => $binSize,
            'total_hits' => 0,
            'bins' => [],
        ];
    }

    ksort($bins);
    return [
        'te' => $teName,
        'bin_size_bp' => $binSize,
        'total_hits' => $totalHits,
        'bins' => $bins,
    ];
}

function tekg_jbrowse_load_bin_hits_for_te_proto(string $teName): ?array
{
    static $cache = [];
    if (array_key_exists($teName, $cache)) {
        return $cache[$teName];
    }

    $cachePath = tekg_jbrowse_bin_cache_path_proto($teName);
    $rmskPath = __DIR__ . '/data/rmsk.txt';
    $sourceTime = is_file($rmskPath) ? ((int) @filemtime($rmskPath)) : 0;
    $decoded = null;

    if (is_file($cachePath) && ((int) @filemtime($cachePath)) >= $sourceTime) {
        $decoded = json_decode((string) file_get_contents($cachePath), true);
        if (!is_array($decoded) || (int) ($decoded['bin_size_bp'] ?? 0) !== tekg_jbrowse_bin_size_proto()) {
            $decoded = null;
        }
    }

    if (!is_array($decoded)) {
        $decoded = tekg_jbrowse_build_bin_hits_for_te_proto($teName, tekg_jbrowse_bin_size_proto());
        if (is_array($decoded)) {
            @file_put_contents(
                $cachePath,
                json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                LOCK_EX
            );
        }
    }

    $cache[$teName] = is_array($decoded) ? $decoded : null;
    return $cache[$teName];
}

function tekg_karyotype_bin_hit_map_proto(?array $genomeDistribution, ?array $jbrowseSession): array
{
    $result = [
        'available' => false,
        'bin_size_bp' => (int) ($genomeDistribution['bin_size_bp'] ?? ($jbrowseSession['bin_size_bp'] ?? 0)),
        'sample_hit_total' => is_array($jbrowseSession['sample_hits'] ?? null) ? count($jbrowseSession['sample_hits']) : 0,
        'total_hits' => (int) ($jbrowseSession['total_hits'] ?? ($genomeDistribution['total_hits'] ?? 0)),
        'bins' => [],
    ];

    if (!is_array($genomeDistribution) || !is_array($jbrowseSession)) {
        return $result;
    }

    $rawBinHits = is_array($jbrowseSession['bin_hits'] ?? null) ? $jbrowseSession['bin_hits'] : [];
    if ($rawBinHits === []) {
        return $result;
    }

    $dataJsonPath = tekg_project_relative_from_site_path_proto((string) ($genomeDistribution['data_json_path'] ?? ''));
    if ($dataJsonPath === '') {
        return $result;
    }

    $absolutePath = tekg_fs_from_project_relative($dataJsonPath);
    if (!is_file($absolutePath)) {
        return $result;
    }

    $payload = json_decode((string) file_get_contents($absolutePath), true);
    if (!is_array($payload)) {
        return $result;
    }

    $countByBinKey = [];
    foreach ((array) ($payload['singleton_contigs'] ?? []) as $contig) {
        if (!is_array($contig)) {
            continue;
        }
        $chrom = trim((string) ($contig['name'] ?? ''));
        if ($chrom === '') {
            continue;
        }
        foreach ((array) ($contig['hit_clusters'] ?? []) as $cluster) {
            if (!is_array($cluster) || count($cluster) < 3) {
                continue;
            }
            $start = (int) ($cluster[0] ?? 0);
            $end = (int) ($cluster[1] ?? 0);
            $count = (int) ($cluster[2] ?? 0);
            if ($start <= 0 || $end < $start) {
                continue;
            }
            $countByBinKey[$chrom . ':' . $start . '-' . $end] = $count;
        }
    }

    foreach ($rawBinHits as $key => $bin) {
        if (!is_string($key) || !is_array($bin)) {
            continue;
        }
        $chrom = trim((string) ($bin['chrom'] ?? ''));
        $start = (int) ($bin['start'] ?? 0);
        $end = (int) ($bin['end'] ?? 0);
        $hits = [];
        foreach ((array) ($bin['hits'] ?? []) as $index => $hit) {
            if (!is_array($hit)) {
                continue;
            }
            $hitChrom = trim((string) ($hit['chrom'] ?? ''));
            $hitStart = (int) ($hit['start'] ?? -1);
            $hitEnd = (int) ($hit['end'] ?? -1);
            if ($hitChrom === '' || $hitStart < 0 || $hitEnd <= $hitStart) {
                continue;
            }
            $hitStrand = ((string) ($hit['strand'] ?? '+')) === '-' ? '-' : '+';
            $hitLength = max(1, (int) ($hit['length'] ?? ($hitEnd - $hitStart)));
            $hitScore = (int) ($hit['score'] ?? 0);
            $hits[] = [
                'hitIndex' => (int) $index,
                'chrom' => $hitChrom,
                'start' => $hitStart,
                'end' => $hitEnd,
                'strand' => $hitStrand,
                'length' => $hitLength,
                'score' => $hitScore,
                'label' => (string) ($hit['label'] ?? tekg_jbrowse_hit_label_proto($hitChrom, $hitStart, $hitEnd, $hitStrand, $hitLength, $hitScore)),
            ];
        }

        if ($chrom === '' || $start <= 0 || $end < $start || $hits === []) {
            continue;
        }

        $result['bins'][$key] = [
            'chrom' => $chrom,
            'start' => $start,
            'end' => $end,
            'count' => (int) ($countByBinKey[$key] ?? count($hits)),
            'hits' => $hits,
        ];
    }

    $result['available'] = $result['bins'] !== [];
    return $result;
}


function tekg_tree_classification_index_proto(): ?array
{
    static $lookup = null;
    static $loaded = false;
    if ($loaded) {
        return $lookup;
    }
    $loaded = true;

    $file = __DIR__ . '/data/processed/tree_te_lineage.json';
    if (!is_file($file)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($file), true);
    if (!is_array($decoded)) {
        return null;
    }

    $nodes = is_array($decoded['nodes'] ?? null) ? $decoded['nodes'] : [];
    $edges = is_array($decoded['edges'] ?? null) ? $decoded['edges'] : [];
    $nodeMap = [];
    $nameIndex = [];
    $canonicalIndex = [];
    foreach ($nodes as $node) {
        if (!is_array($node)) {
            continue;
        }
        $name = trim((string) ($node['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $nodeMap[$name] = $node;
        $strictKey = tekg_lower_proto(tekg_clean_label_proto($name));
        $canonicalKey = tekg_canonicalize_label_proto($name);
        if ($strictKey !== '') {
            $nameIndex[$strictKey] = $name;
        }
        if ($canonicalKey !== '') {
            $canonicalIndex[$canonicalKey] = $name;
        }
    }

    $parentMap = [];
    foreach ($edges as $edge) {
        if (!is_array($edge)) {
            continue;
        }
        $child = trim((string) ($edge['child'] ?? ''));
        $parent = trim((string) ($edge['parent'] ?? ''));
        if ($child === '' || $parent === '') {
            continue;
        }
        $parentMap[$child] = $parent;
    }

    $lookup = [
        'root' => (string) ($decoded['root'] ?? ''),
        'nodes' => $nodeMap,
        'name_index' => $nameIndex,
        'canonical_index' => $canonicalIndex,
        'parent_map' => $parentMap,
    ];
    return $lookup;
}

function tekg_tree_classification_display_label_proto(string $label): string
{
    $map = [
        'TE' => 'TE - Human',
        'Retrotransposon' => 'Class I: Retrotransposons',
        'DNA Transposon' => 'Class II: DNA Transposons',
        'SINE' => 'SINEs',
    ];
    return $map[$label] ?? $label;
}

function tekg_tree_classification_lookup_proto(string $query, string $type = 'all', ?array $repbase = null, ?array $dfam = null): ?array
{
    $query = trim($query);
    if ($query === '') {
        return null;
    }

    $normalizedType = strtoupper(trim($type));
    if (in_array($normalizedType, ['DISEASE', 'FUNCTION', 'PAPER'], true)) {
        return null;
    }

    $lookup = tekg_tree_classification_index_proto();
    if (!is_array($lookup)) {
        return null;
    }

    $candidates = [$query];
    if (is_array($repbase)) {
        foreach (['nm', 'id'] as $key) {
            $candidate = trim((string) ($repbase[$key] ?? ''));
            if ($candidate !== '') {
                $candidates[] = $candidate;
            }
        }
    }
    if (is_array($dfam)) {
        foreach (['name', 'accession', 'matched_query'] as $key) {
            $candidate = trim((string) ($dfam[$key] ?? ''));
            if ($candidate !== '') {
                $candidates[] = $candidate;
            }
        }
    }

    $teName = null;
    foreach ($candidates as $candidate) {
        $strictKey = tekg_lower_proto(tekg_clean_label_proto($candidate));
        $canonicalKey = tekg_canonicalize_label_proto($candidate);
        $teName = $lookup['name_index'][$strictKey] ?? $lookup['canonical_index'][$canonicalKey] ?? null;
        if (is_string($teName) && $teName !== '') {
            break;
        }
    }

    if (!is_string($teName) || $teName === '') {
        return null;
    }

    $path = [];
    $seen = [];
    $current = $teName;
    while ($current !== '' && !isset($seen[$current])) {
        $seen[$current] = true;
        $node = $lookup['nodes'][$current] ?? ['name' => $current, 'depth' => count($path), 'description' => ''];
        $path[] = [
            'name' => $current,
            'display_label' => tekg_tree_classification_display_label_proto($current),
            'depth' => (int) ($node['depth'] ?? count($path)),
            'description' => (string) ($node['description'] ?? ''),
        ];
        $current = (string) ($lookup['parent_map'][$current] ?? '');
    }

    $path = array_reverse($path);
    if ($path === []) {
        return null;
    }

    return [
        'matched_query' => $query,
        'resolved_te_name' => $teName,
        'path' => $path,
        'display_path' => implode(' --- ', array_map(static fn(array $node): string => (string) ($node['display_label'] ?? ''), $path)),
    ];
}

function tekg_jbrowse_index_proto(): ?array
{
    static $lookup = null;
    static $loaded = false;
    if ($loaded) {
        return $lookup;
    }
    $loaded = true;

    $representativeFile = tekg_jbrowse_project_fs_path_proto('repeats/te_representative_index.json');
    $manifestFile = tekg_jbrowse_project_fs_path_proto('repeats/te_hits_manifest.json');
    if (!is_file($representativeFile)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($representativeFile), true);
    if (!is_array($decoded)) {
        return null;
    }
    $manifest = is_file($manifestFile)
        ? json_decode((string) file_get_contents($manifestFile), true)
        : [];
    if (!is_array($manifest)) {
        $manifest = [];
    }

    $nameIndex = [];
    $canonicalIndex = [];
    foreach ($decoded as $name => $entry) {
        if (!is_string($name) || $name === '' || !is_array($entry)) {
            continue;
        }
        $strictKey = tekg_lower_proto(tekg_clean_label_proto($name));
        $canonicalKey = tekg_canonicalize_label_proto($name);
        if ($strictKey !== '') {
            $nameIndex[$strictKey] = $name;
        }
        if ($canonicalKey !== '') {
            $canonicalIndex[$canonicalKey] = $name;
        }
    }

    $lookup = [
        'entries' => $decoded,
        'name_index' => $nameIndex,
        'canonical_index' => $canonicalIndex,
        'hit_manifest' => $manifest,
    ];
    return $lookup;
}

function tekg_jbrowse_load_hit_entry_proto(string $teName, array $lookup): ?array
{
    static $cache = [];
    if (array_key_exists($teName, $cache)) {
        return $cache[$teName];
    }

    $relativePath = $lookup['hit_manifest'][$teName] ?? null;
    if (!is_string($relativePath) || $relativePath === '') {
        $cache[$teName] = null;
        return null;
    }

    $absolutePath = tekg_jbrowse_project_fs_path_proto($relativePath);
    if (!is_file($absolutePath)) {
        $cache[$teName] = null;
        return null;
    }

    $decoded = json_decode((string) file_get_contents($absolutePath), true);
    $cache[$teName] = is_array($decoded) ? $decoded : null;
    return $cache[$teName];
}

function tekg_jbrowse_lookup_proto(string $query, string $type = 'all', ?array $repbase = null, ?string $lang = null): ?array
{
    $query = trim($query);
    if ($query === '') {
        return null;
    }

    $normalizedType = strtoupper(trim($type));
    if (in_array($normalizedType, ['DISEASE', 'FUNCTION', 'PAPER'], true)) {
        return null;
    }

    $lookup = tekg_jbrowse_index_proto();
    if (!is_array($lookup)) {
        return null;
    }

    $candidates = [$query];
    if (is_array($repbase)) {
        foreach (['nm', 'id'] as $key) {
            $candidate = trim((string) ($repbase[$key] ?? ''));
            if ($candidate !== '') {
                $candidates[] = $candidate;
            }
        }
    }

    $teName = null;
    foreach ($candidates as $candidate) {
        $strictKey = tekg_lower_proto(tekg_clean_label_proto($candidate));
        $canonicalKey = tekg_canonicalize_label_proto($candidate);
        $teName = $lookup['name_index'][$strictKey] ?? $lookup['canonical_index'][$canonicalKey] ?? null;
        if (is_string($teName) && $teName !== '') {
            break;
        }
    }

    if (!is_string($teName) || $teName === '') {
        return null;
    }

    $entry = $lookup['entries'][$teName] ?? null;
    if (!is_array($entry)) {
        return null;
    }

    $hitEntry = tekg_jbrowse_load_hit_entry_proto($teName, $lookup);
    if (is_array($hitEntry)) {
        $entry = array_replace($entry, $hitEntry);
    }

    $binHitEntry = tekg_jbrowse_load_bin_hits_for_te_proto($teName);
    if (is_array($binHitEntry)) {
        $entry['bin_size_bp'] = (int) ($binHitEntry['bin_size_bp'] ?? tekg_jbrowse_bin_size_proto());
        $entry['bin_hits'] = is_array($binHitEntry['bins'] ?? null) ? $binHitEntry['bins'] : [];
    }

    $locus = is_array($entry['representative_locus'] ?? null) ? $entry['representative_locus'] : null;
    if (!is_array($locus)) {
        return null;
    }

    $sampleHits = [];
    foreach (($entry['sample_hits'] ?? []) as $hit) {
        if (!is_array($hit)) {
            continue;
        }
        $chrom = trim((string) ($hit['chrom'] ?? ''));
        $start = (int) ($hit['start'] ?? -1);
        $end = (int) ($hit['end'] ?? -1);
        if ($chrom === '' || $start < 0 || $end <= $start) {
            continue;
        }
        $strand = ((string) ($hit['strand'] ?? '+')) === '-' ? '-' : '+';
        $length = max(1, (int) ($hit['length'] ?? ($end - $start)));
        $score = (int) ($hit['score'] ?? 0);
        $sampleHits[] = [
            'chrom' => $chrom,
            'start' => $start,
            'end' => $end,
            'strand' => $strand,
            'length' => $length,
            'score' => $score,
            'label' => sprintf(
                '%s:%s-%s | %s | len %s bp | score %s',
                $chrom,
                number_format($start + 1),
                number_format($end),
                $strand === '-' ? 'reverse strand' : 'forward strand',
                number_format($length),
                number_format($score)
            ),
        ];
    }
    if ($sampleHits === []) {
        $fallbackChrom = (string) ($locus['chrom'] ?? '');
        $fallbackStart = (int) ($locus['start'] ?? 0);
        $fallbackEnd = (int) ($locus['end'] ?? 0);
        $fallbackStrand = ((string) ($locus['strand'] ?? '+')) === '-' ? '-' : '+';
        $fallbackLength = max(1, (int) ($locus['length'] ?? ($fallbackEnd - $fallbackStart)));
        $fallbackScore = (int) ($locus['score'] ?? 0);
        $sampleHits[] = [
            'chrom' => $fallbackChrom,
            'start' => $fallbackStart,
            'end' => $fallbackEnd,
            'strand' => $fallbackStrand,
            'length' => $fallbackLength,
            'score' => $fallbackScore,
            'label' => sprintf(
                '%s:%s-%s | %s | len %s bp | score %s',
                $fallbackChrom,
                number_format($fallbackStart + 1),
                number_format($fallbackEnd),
                $fallbackStrand === '-' ? 'reverse strand' : 'forward strand',
                number_format($fallbackLength),
                number_format($fallbackScore)
            ),
        ];
    }

    $browserParams = array_filter([
        'te' => $teName,
        'chr' => (string) ($locus['chrom'] ?? ''),
        'start' => array_key_exists('start', $locus) ? (string) ((int) $locus['start']) : null,
        'end' => array_key_exists('end', $locus) ? (string) ((int) $locus['end']) : null,
    ], static fn ($value) => $value !== null && $value !== '');

    $entry['matched_query'] = $query;
    $entry['resolved_te_name'] = $teName;
    $entry['sample_hits'] = $sampleHits;
    $entry['locus_label'] = sprintf(
        '%s:%s-%s',
        (string) ($locus['chrom'] ?? '-'),
        number_format(((int) ($locus['start'] ?? 0)) + 1),
        number_format((int) ($locus['end'] ?? 0))
    );
    $entry['browser_url'] = site_url_with_state('/TE-/jbrowse.php', $lang ?? site_lang(), null, $browserParams);
    $entry['config_url'] = site_url_with_state('/TE-/jbrowse.php', $lang ?? site_lang(), null, $browserParams + ['format' => 'config']);
    return $entry;
}

function tekg_format_sequence_proto(string $sequence, int $wrap = 80): string
{
    $sequence = preg_replace('/\s+/', '', strtolower(trim($sequence))) ?? '';
    if ($sequence === '') {
        return '';
    }
    return rtrim(chunk_split($sequence, $wrap, "\n"));
}

function tekg_request_scalar_proto(array $source, string $key, string $default = ''): string
{
    if (!array_key_exists($key, $source)) {
        return $default;
    }
    $value = $source[$key];
    if (is_array($value)) {
        foreach ($value as $item) {
            if (is_scalar($item)) {
                return trim((string) $item);
            }
        }
        return $default;
    }
    if (!is_scalar($value)) {
        return $default;
    }
    return trim((string) $value);
}

$siteLang = site_lang();
$query = tekg_request_scalar_proto($_GET, 'q', '');
$type = tekg_request_scalar_proto($_GET, 'type', 'all');
$repbase = tekg_repbase_lookup_proto($query);
$dfamSequence = tekg_dfam_lookup_proto($query, $type);
$repbaseStructureSvgUrl = tekg_repbase_structure_svg_url_proto($repbase, $query);
$genomeDistribution = tekg_karyotype_lookup_proto($query, $type, $repbase);
$jbrowseSession = tekg_jbrowse_lookup_proto($query, $type, $repbase, $siteLang);
$karyotypeHitMap = tekg_karyotype_bin_hit_map_proto($genomeDistribution, $jbrowseSession);
$classificationSession = tekg_tree_classification_lookup_proto($query, $type, $repbase, $dfamSequence);
$searchGraphSrc = site_url_with_state('/TE-/index_g6.html', $siteLang, null, array_filter([
    'embed' => 'search-result',
    'q' => $query !== '' ? $query : null,
], static fn ($value) => $value !== null && $value !== ''));
$browseBackUrl = site_url_with_state('/TE-/browse.php', $siteLang);
$detailSections = [
    ['id' => 'search-summary-panel', 'label' => 'Summary'],
    ['id' => 'search-graph-panel', 'label' => 'Local Graph'],
];
if ($repbase !== null) {
    $detailSections[] = ['id' => 'search-sequence-panel', 'label' => 'Sequence'];
}
if ($genomeDistribution !== null) {
    $detailSections[] = ['id' => 'search-karyotype-panel', 'label' => 'Genome Annotation'];
}
if ($jbrowseSession !== null) {
    $detailSections[] = ['id' => 'search-jbrowse-panel', 'label' => 'Genome Browser'];
}

require __DIR__ . '/head.php';
?>
      <link rel="stylesheet" href="/TE-/assets/css/pages/search.css">

      <section class="search-shell">
        <div class="proto-container">
          <section class="query-panel">
            <div class="detail-toolbar">
              <a class="detail-back-link" href="<?= htmlspecialchars($browseBackUrl, ENT_QUOTES, 'UTF-8') ?>">&larr; Back to Browse</a>
              <form id="search-form" class="detail-search-form" method="GET">
                <input type="hidden" name="type" value="all">
                <input type="hidden" name="lang" value="<?= htmlspecialchars($siteLang, ENT_QUOTES, 'UTF-8') ?>">
                <div class="detail-search-box">
                  <svg class="detail-search-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"></circle><path d="m20 20-3.8-3.8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg>
                  <input id="search-query" class="query-control" type="text" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search a TE, disease, function, or PMID">
                </div>
              </form>
            </div>
          </section>

          <div id="search-results" class="detail-layout<?= $query === '' ? ' is-hidden' : '' ?>">
            <aside class="detail-sidebar">
              <nav class="detail-nav" aria-label="Detail sections">
                <div class="detail-nav-title">Detail Sections</div>
                <?php foreach ($detailSections as $section): ?>
                  <a class="detail-nav-link" data-detail-nav-link href="#<?= htmlspecialchars($section['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($section['label'], ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
              </nav>
            </aside>

            <div class="detail-content">
              <section id="search-summary-panel" class="data-panel">
                <h3>Summary</h3>
                <?php require __DIR__ . '/templates/components/search_summary_meta.php'; ?>
              </section>

              <?php require __DIR__ . '/templates/components/search_graph_panel.php'; ?>

              <?php if ($repbase !== null): ?>
                <section id="search-sequence-panel" class="data-panel sequence-panel">
                  <h3>Sequence</h3>
                  <?php if (!empty($repbase['sequence_summary'])): ?>
                    <div class="sequence-meta">
                      <div><strong>Sequence summary: </strong><?= htmlspecialchars((string) $repbase['sequence_summary'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($repbaseStructureSvgUrl)): ?>
                    <div class="sequence-plot">
                      <object
                        class="sequence-plot-object"
                        data="<?= htmlspecialchars((string) $repbaseStructureSvgUrl, ENT_QUOTES, 'UTF-8') ?>"
                        type="image/svg+xml"
                        aria-label="Sequence structure plot for <?= htmlspecialchars((string) ($repbase['nm'] ?? $query), ENT_QUOTES, 'UTF-8') ?>">
                      </object>
                    </div>
                  <?php endif; ?>
                  <div class="sequence-code-wrap">
                    <pre class="sequence-code"><?= htmlspecialchars(tekg_format_sequence_proto((string) ($repbase['sequence'] ?? '')), ENT_QUOTES, 'UTF-8') ?></pre>
                  </div>
                </section>
              <?php endif; ?>

              <?php if ($genomeDistribution !== null): ?>
                <section id="search-karyotype-panel" class="data-panel distribution-panel">
                  <h3>Genome Annotation Distribution</h3>
                  <div class="distribution-meta">
                    <div><strong>Assembly: </strong><?= htmlspecialchars((string) ($genomeDistribution['assembly_label'] ?? 'Homo sapiens [hg38]'), ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Mode: </strong>All Hits</div>
                    <div><strong>Bin size: </strong><?= htmlspecialchars(number_format(((int) ($genomeDistribution['bin_size_bp'] ?? 1000000)) / 1000000, 0) . ' Mb', ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Total hits: </strong><?= htmlspecialchars(number_format((int) ($genomeDistribution['total_hits'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></div>
                  </div>
                  <p id="search-karyotype-status" class="distribution-status">Loading genome annotation distribution...</p>
                  <div class="distribution-karyotype-wrap">
                    <div
                      id="search-karyotype-view"
                      class="distribution-karyotype"
                      data-karyotype-path="<?= htmlspecialchars((string) ($genomeDistribution['data_json_path'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    ></div>
                  </div>
                </section>
              <?php endif; ?>
              <?php if ($jbrowseSession !== null): ?>
                <section id="search-jbrowse-panel" class="data-panel jbrowse-panel">
                  <div class="jbrowse-panel-head">
                    <h3>Genome Browser</h3>
                    <a class="jbrowse-open-link" id="searchJBrowseOpenLink" href="<?= htmlspecialchars((string) ($jbrowseSession['browser_url'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Open in full browser</a>
                  </div>
                  <div class="jbrowse-summary">
                    <h2>Genome browser session</h2>
                    <div class="jbrowse-meta">
                      <div class="jbrowse-meta-item">
                        <span class="jbrowse-meta-label">TE</span>
                        <span class="jbrowse-meta-value"><?= htmlspecialchars((string) ($jbrowseSession['resolved_te_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                      </div>
                      <div class="jbrowse-meta-item">
                        <span class="jbrowse-meta-label">Representative locus</span>
                        <span class="jbrowse-meta-value"><?= htmlspecialchars((string) ($jbrowseSession['locus_label'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                      </div>
                      <div class="jbrowse-meta-item">
                        <span class="jbrowse-meta-label">Initial browser window</span>
                        <span class="jbrowse-meta-value" id="searchJBrowseDefaultLoc">-</span>
                      </div>
                      <div class="jbrowse-meta-item">
                        <span class="jbrowse-meta-label">Total genomic hits</span>
                        <span class="jbrowse-meta-value"><?= htmlspecialchars(number_format((int) ($jbrowseSession['total_hits'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></span>
                      </div>
                      <div class="jbrowse-meta-item">
                        <span class="jbrowse-meta-label">Repeat features in window</span>
                        <span class="jbrowse-meta-value" id="searchJBrowseRepeatCount">-</span>
                      </div>
                      <div class="jbrowse-meta-item">
                        <span class="jbrowse-meta-label">RefSeq features in window</span>
                        <span class="jbrowse-meta-value" id="searchJBrowseRefseqCount">-</span>
                      </div>
                    </div>
                    <div class="jbrowse-track-toolbar">
                      <div class="jbrowse-control-row">
                        <div class="jbrowse-hit-picker">
                          <div class="jbrowse-hit-picker-head">
                            <label class="jbrowse-hit-picker-label" for="searchJBrowseHitSelect">Genomic hit</label>
                            <button type="button" class="jbrowse-hit-restore" id="searchJBrowseRestoreHits" hidden>Show sampled hits</button>
                          </div>
                          <select id="searchJBrowseHitSelect" class="jbrowse-hit-picker-select">
                            <?php
                              $jbrowseRepresentative = is_array($jbrowseSession['representative_locus'] ?? null) ? $jbrowseSession['representative_locus'] : [];
                              foreach (($jbrowseSession['sample_hits'] ?? []) as $hitIndex => $hit):
                                if (!is_array($hit)) {
                                  continue;
                                }
                                $hitChrom = trim((string) ($hit['chrom'] ?? ''));
                                $hitStart = (int) ($hit['start'] ?? -1);
                                $hitEnd = (int) ($hit['end'] ?? -1);
                                if ($hitChrom === '' || $hitStart < 0 || $hitEnd <= $hitStart) {
                                  continue;
                                }
                                $isSelectedHit = $hitChrom === (string) ($jbrowseRepresentative['chrom'] ?? '')
                                  && $hitStart === (int) ($jbrowseRepresentative['start'] ?? -2)
                                  && $hitEnd === (int) ($jbrowseRepresentative['end'] ?? -3);
                            ?>
                            <option value="<?= (int) $hitIndex ?>"
                                    data-chrom="<?= htmlspecialchars($hitChrom, ENT_QUOTES, 'UTF-8') ?>"
                                    data-start="<?= (int) $hitStart ?>"
                                    data-end="<?= (int) $hitEnd ?>"
                                    <?= $isSelectedHit ? 'selected' : '' ?>><?= htmlspecialchars((string) ($hit['label'] ?? ($hitChrom . ':' . ($hitStart + 1) . '-' . $hitEnd)), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                          </select>
                          <div class="jbrowse-hit-scope" id="searchJBrowseHitScope" hidden></div>
                        </div>
                        <div class="jbrowse-track-list" id="searchJBrowseTrackControls">
                          <label class="jbrowse-track-item">
                            <input type="checkbox" data-track-id="repeats_hg38" checked>
                            <span class="jbrowse-track-dot" style="background:#d8a11a"></span>
                            <span class="jbrowse-track-name">Repeats</span>
                          </label>
                          <label class="jbrowse-track-item">
                            <input type="checkbox" data-track-id="ncbi_refseq_window" checked>
                            <span class="jbrowse-track-dot" style="background:#5fa1da"></span>
                            <span class="jbrowse-track-name">NCBI RefSeq</span>
                          </label>
                          <label class="jbrowse-track-item">
                            <input type="checkbox" data-track-id="clinvar_variants" checked>
                            <span class="jbrowse-track-dot" style="background:#73b36b"></span>
                            <span class="jbrowse-track-name">ClinVar variants</span>
                          </label>
                          <label class="jbrowse-track-item">
                            <input type="checkbox" data-track-id="clinvar_cnv" checked>
                            <span class="jbrowse-track-dot" style="background:#cc7f9f"></span>
                            <span class="jbrowse-track-name">ClinVar CNV</span>
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="jbrowse-browser-stage">
                    <div id="search_jbrowse_linear_genome_view">
                      <div class="jbrowse-loading">Preparing genome browser session...</div>
                    </div>
                  </div>
                </section>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </section>

      <?php if ($genomeDistribution !== null): ?>
      <script src="/TE-/assets/vendor/karyotype/Karyotype.js"></script>
      <?php endif; ?>

      

      
      <?php if ($jbrowseSession !== null): ?>
      <script src="https://unpkg.com/@jbrowse/react-linear-genome-view2@3.5.0/dist/react-linear-genome-view.umd.production.min.js" crossorigin></script>
      
      <?php endif; ?>

      

      <script id="search-page-config" type="application/json"><?= json_encode([
        'browserBaseUrl' => (string) ($jbrowseSession['browser_url'] ?? ''),
        'configUrl' => (string) ($jbrowseSession['config_url'] ?? ''),
        'karyotypeHitMap' => $karyotypeHitMap,
      ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
      <script src="/TE-/assets/js/pages/search.js"></script>
    </main>
  </div>
</body>
</html>







