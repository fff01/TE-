<?php
require_once __DIR__ . '/site_i18n.php';

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
            'sequence_summary' => $sequenceSummary,
            'length_bp' => $lengthBp,
            'reference_count' => is_array($entry['references'] ?? null) ? count($entry['references']) : 0,
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

function tekg_jbrowse_index_proto(): ?array
{
    static $lookup = null;
    static $loaded = false;
    if ($loaded) {
        return $lookup;
    }
    $loaded = true;

    $representativeFile = __DIR__ . '/new_data/JBrowse/repeats/te_representative_index.json';
    $manifestFile = __DIR__ . '/new_data/JBrowse/repeats/te_hits_manifest.json';
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

    $absolutePath = __DIR__ . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
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
    $entry['browser_url'] = site_url_with_state('/TE-/jbrowse.php', $lang ?? site_lang(), 'g6', $browserParams);
    $entry['config_url'] = site_url_with_state('/TE-/jbrowse.php', $lang ?? site_lang(), 'g6', $browserParams + ['format' => 'config']);
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
$siteRenderer = site_renderer();
$query = tekg_request_scalar_proto($_GET, 'q', '');
$type = tekg_request_scalar_proto($_GET, 'type', 'all');
$repbase = tekg_repbase_lookup_proto($query);
$dfamSequence = tekg_dfam_lookup_proto($query, $type);
$genomeDistribution = tekg_karyotype_lookup_proto($query, $type, $repbase);
$jbrowseSession = tekg_jbrowse_lookup_proto($query, $type, $repbase, $siteLang);
$searchGraphSrc = site_url_with_state('/TE-/index_g6.html', $siteLang, 'g6', array_filter([
    'embed' => 'search-result',
    'q' => $query !== '' ? $query : null,
], static fn ($value) => $value !== null && $value !== ''));
$browseBackUrl = site_url_with_state('/TE-/browse.php', $siteLang, $siteRenderer);
$detailSections = [
    ['id' => 'search-summary-panel', 'label' => 'Summary'],
    ['id' => 'search-graph-panel', 'label' => 'Local Graph'],
];
if ($dfamSequence !== null) {
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
      <style>
        .search-shell {
          background: #f5f9ff;
          min-height: calc(100vh - 82px);
          padding: 34px 0 54px;
        }

        .proto-container {
          max-width: 1480px;
          margin: 0 auto;
          padding: 0 28px;
        }

        .download-page-title {
          margin: 0 0 22px;
          font-size: 52px;
          font-weight: 700;
          color: #8a93a3;
          line-height: 1.1;
        }

        .download-crumbs {
          display: flex;
          align-items: center;
          gap: 10px;
          margin-bottom: 38px;
          font-size: 16px;
          color: #70809a;
        }

        .download-crumbs a {
          color: #2f63b9;
          font-weight: 500;
        }



        html {
          scroll-behavior: smooth;
        }

        .detail-toolbar {
          --detail-sidebar-width: 196px;
          display: grid;
          grid-template-columns: var(--detail-sidebar-width) minmax(0, 1fr);
          gap: 18px;
          align-items: center;
        }

        .detail-back-link {
          display: inline-flex;
          align-items: center;
          justify-content: flex-start;
          gap: 8px;
          width: 100%;
          box-sizing: border-box;
          margin-left: -12px;
          min-height: 48px;
          padding: 0 14px;
          border: 1px solid #dfe6ef;
          border-radius: 10px;
          background: #ffffff;
          box-shadow: 0 8px 24px rgba(25, 56, 105, 0.05);
          color: #214b8d;
          font-size: 15px;
          font-weight: 700;
          white-space: nowrap;
        }

        .detail-search-form {
          min-width: 0;
          width: min(100%, 440px);
          justify-self: end;
        }

        .detail-search-box {
          position: relative;
        }

        .detail-search-icon {
          position: absolute;
          top: 50%;
          left: 18px;
          transform: translateY(-50%);
          width: 18px;
          height: 18px;
          color: #88a0bf;
          pointer-events: none;
        }

        .detail-search-box .query-control {
          padding-left: 48px;
          min-height: 50px;
        }

        .detail-layout {
          display: grid;
          grid-template-columns: 196px minmax(0, 1fr);
          gap: 18px;
          align-items: start;
        }

        .detail-layout.is-hidden {
          display: none;
        }

        .detail-sidebar {
          position: sticky;
          top: 108px;
          margin-left: -12px;
        }

        .detail-nav {
          background: #ffffff;
          border: 1px solid #dfe6ef;
          border-radius: 10px;
          box-shadow: 0 8px 24px rgba(25, 56, 105, 0.05);
          padding: 14px 12px;
          display: grid;
          gap: 8px;
        }

        .detail-nav-title {
          margin: 0 0 6px;
          padding: 0 8px;
          color: #6f8198;
          font-size: 13px;
          font-weight: 700;
          letter-spacing: 0.06em;
          text-transform: uppercase;
        }

        .detail-nav-link {
          display: block;
          padding: 11px 12px;
          border-radius: 8px;
          color: #61789f;
          font-size: 15px;
          font-weight: 600;
          transition: background 0.18s ease, color 0.18s ease;
        }

        .detail-nav-link.is-active,
        .detail-nav-link:hover {
          background: #eef4ff;
          color: #214b8d;
        }

        .detail-content {
          min-width: 0;
          display: grid;
          gap: 14px;
        }

        .query-panel {
          background: transparent;
          border: 0;
          border-radius: 0;
          box-shadow: none;
          padding: 0;
          margin-bottom: 18px;
        }


        .query-form-grid {
          display: grid;
          grid-template-columns: 1fr;
          gap: 18px;
          align-items: end;
        }

        .query-field label {
          display: block;
          margin-bottom: 12px;
          font-size: 16px;
          color: #7b8597;
          font-weight: 500;
        }

        .query-control {
          width: 100%;
          min-height: 58px;
          border: 1px solid #dce5f3;
          border-radius: 8px;
          background: #ffffff;
          padding: 0 18px;
          font-size: 17px;
          color: #193458;
          outline: none;
          transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .query-control:focus {
          border-color: #8fb2ea;
          box-shadow: 0 0 0 3px rgba(47, 99, 185, 0.08);
        }

        .query-actions {
          display: flex;
          gap: 12px;
          align-items: center;
          flex-wrap: wrap;
          margin-top: 28px;
        }

        .query-btn {
          min-width: 126px;
          min-height: 54px;
          border-radius: 8px;
          border: 1px solid #cfdcf0;
          background: #ffffff;
          color: #61789f;
          font-size: 16px;
          font-weight: 600;
          cursor: pointer;
        }

        .query-btn.is-primary {
          background: #2f63b9;
          border-color: #2f63b9;
          color: #ffffff;
        }

        .search-layout {
          display: grid;
          grid-template-columns: 1fr;
          gap: 22px;
          align-items: start;
        }

        .data-panel,
        .graph-panel {
          background: #ffffff;
          border: 1px solid #dbe7f8;
          border-radius: 10px;
          padding: 24px 26px 22px;
          box-shadow: 0 8px 24px rgba(25, 56, 105, 0.05);
        }

        .data-panel h3,
        .graph-panel-head h3 {
          margin: 0 0 14px;
          font-size: 22px;
          font-weight: 700;
          color: #214b8d;
        }

        .data-panel h3 {
          padding-bottom: 12px;
          border-bottom: 1px solid #e5edf7;
        }

        .panel-body {
          line-height: 1.8;
          color: #5e7288;
          min-height: 120px;
        }

        .sequence-panel {
          display: grid;
          gap: 18px;
          grid-column: 1 / -1;
          width: 100%;
        }

        .sequence-meta {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
          gap: 12px 20px;
          font-size: 15px;
          color: #52687f;
        }

        .sequence-meta strong {
          color: #214b8d;
        }

        .sequence-fragment-note {
          padding: 12px 14px;
          border: 1px solid #f2d7a1;
          border-radius: 10px;
          background: #fff8e8;
          color: #8a6410;
          font-size: 15px;
          line-height: 1.6;
        }

        .sequence-code-wrap {
          width: 100%;
          max-width: 100%;
          min-height: 320px;
          max-height: 320px;
          border: 1px solid #dbe6f4;
          border-radius: 10px;
          background: #f9fbff;
          overflow-x: auto;
          overflow-y: auto;
        }

        .sequence-code {
          margin: 0;
          padding: 16px 18px;
          min-width: max-content;
          font-family: Consolas, 'Courier New', monospace;
          font-size: 13px;
          line-height: 1.6;
          white-space: pre;
          word-break: normal;
          color: #183152;
        }

        .sequence-plot {
          border: 1px solid #dbe6f4;
          border-radius: 10px;
          background: linear-gradient(180deg, #ffffff 0%, #f4f8ff 100%);
          padding: 14px 16px;
        }

        .sequence-plot img {
          display: block;
          width: 100%;
          height: auto;
        }

        .distribution-panel {
          display: grid;
          gap: 18px;
          grid-column: 1 / -1;
          width: 100%;
        }

        .distribution-meta {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
          gap: 12px 20px;
          font-size: 15px;
          color: #52687f;
        }

        .distribution-meta strong {
          color: #214b8d;
        }

        .distribution-karyotype-wrap {
          width: 100%;
          border: 1px solid #dbe6f4;
          border-radius: 10px;
          background: linear-gradient(180deg, #ffffff 0%, #f4f8ff 100%);
          padding: 14px 16px;
          overflow-x: auto;
          overflow-y: hidden;
        }

        .distribution-karyotype {
          min-height: 360px;
          min-width: 1080px;
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .distribution-status {
          margin: 0;
          font-size: 14px;
          color: #7f91a6;
        }

        .distribution-karyotype svg {
          display: block;
        }

        .jbrowse-panel {
          display: grid;
          gap: 18px;
          grid-column: 1 / -1;
          width: 100%;
        }

        .jbrowse-panel-head {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 14px;
          flex-wrap: wrap;
          padding-bottom: 12px;
          border-bottom: 1px solid #e5edf7;
        }

        .jbrowse-panel h3 {
          margin: 0;
          padding: 0;
          border: 0;
        }

        .jbrowse-open-link {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          min-height: 40px;
          padding: 0 16px;
          border-radius: 999px;
          border: 1px solid #d9e5f7;
          background: #eef4ff;
          color: #214b8d;
          font-size: 14px;
          font-weight: 700;
          white-space: nowrap;
        }

        .jbrowse-summary {
          background: #ffffff;
          border: 1px solid #dbe7f8;
          border-radius: 14px;
          box-shadow: 0 10px 28px rgba(26, 60, 112, 0.08);
          padding: 20px 22px;
        }

        .jbrowse-summary h2 {
          margin: 0 0 12px;
          font-size: 24px;
          color: #193458;
          font-weight: 700;
        }

        .jbrowse-meta {
          display: grid;
          grid-template-columns: repeat(3, minmax(0, 1fr));
          gap: 12px 18px;
        }

        .jbrowse-meta-item {
          min-width: 0;
        }

        .jbrowse-meta-label {
          display: block;
          font-size: 12px;
          letter-spacing: 0.08em;
          text-transform: uppercase;
          color: #7a8baa;
          margin-bottom: 4px;
          font-weight: 700;
        }

        .jbrowse-meta-value {
          display: block;
          font-size: 17px;
          line-height: 1.45;
          color: #27466f;
          word-break: break-word;
        }

        .jbrowse-track-toolbar {
          margin-top: 18px;
          padding-top: 16px;
          border-top: 1px solid #e5edf9;
        }

        .jbrowse-control-row {
          display: flex;
          flex-wrap: wrap;
          align-items: flex-end;
          gap: 14px 18px;
        }

        .jbrowse-hit-picker {
          min-width: min(420px, 100%);
          flex: 1 1 420px;
        }

        .jbrowse-hit-picker-label {
          display: block;
          margin-bottom: 8px;
          font-size: 12px;
          letter-spacing: 0.08em;
          text-transform: uppercase;
          color: #7a8baa;
          font-weight: 700;
        }

        .jbrowse-hit-picker-select {
          width: 100%;
          min-height: 44px;
          padding: 10px 14px;
          border-radius: 12px;
          border: 1px solid #d7e3f7;
          background: #fbfdff;
          color: #27466f;
          font-size: 14px;
          line-height: 1.4;
        }

        .jbrowse-track-list {
          display: flex;
          flex-wrap: wrap;
          gap: 10px;
        }

        .jbrowse-track-item {
          display: inline-flex;
          align-items: center;
          gap: 10px;
          padding: 10px 14px;
          border-radius: 999px;
          background: #f5f8ff;
          border: 1px solid #e0e9f8;
        }

        .jbrowse-track-item input[type='checkbox'] {
          width: 18px;
          height: 18px;
          accent-color: #3d8f57;
          cursor: pointer;
          margin: 0;
          flex: 0 0 auto;
        }

        .jbrowse-track-dot {
          width: 14px;
          height: 14px;
          border-radius: 50%;
          flex: 0 0 auto;
          border: 2px solid rgba(18, 43, 86, 0.12);
        }

        .jbrowse-track-name {
          font-size: 15px;
          color: #26466f;
          font-weight: 700;
          white-space: nowrap;
        }

        .jbrowse-browser-stage {
          margin-top: 10px;
        }

        #search_jbrowse_linear_genome_view {
          height: 840px;
          background: transparent;
        }

        .jbrowse-loading {
          height: 840px;
          display: flex;
          align-items: center;
          justify-content: center;
          color: #587399;
          font-size: 18px;
          border-radius: 16px;
          background: rgba(255, 255, 255, 0.62);
          border: 1px solid rgba(219, 231, 248, 0.9);
          box-shadow: inset 0 1px 0 rgba(255,255,255,0.7);
        }
        .graph-panel {
          display: flex;
          flex-direction: column;
          gap: 14px;
        }

        .graph-panel-head {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 14px;
        }

        .graph-toggle {
          border: 1px solid #dce5f3;
          border-radius: 8px;
          background: #eef4ff;
          color: #2753b7;
          width: 44px;
          height: 40px;
          font-size: 20px;
          line-height: 1;
          font-weight: 700;
          cursor: pointer;
          display: inline-flex;
          align-items: center;
          justify-content: center;
        }

        .graph-frame {
          flex: 0 0 auto;
          max-height: 680px;
          border: 1px solid #d8e4f0;
          border-radius: 10px;
          background: linear-gradient(180deg, #ffffff 0%, #eef5ff 100%);
          overflow: hidden;
          opacity: 1;
          transform: translateY(0);
          margin-top: 4px;
          transition:
            max-height 0.36s cubic-bezier(0.22, 1, 0.36, 1),
            opacity 0.22s ease,
            transform 0.36s cubic-bezier(0.22, 1, 0.36, 1),
            margin-top 0.36s cubic-bezier(0.22, 1, 0.36, 1),
            border-color 0.22s ease;
        }

        .graph-panel.is-collapsed .graph-frame {
          max-height: 0;
          opacity: 0;
          transform: translateY(-10px);
          margin-top: 0;
          border-color: transparent;
          pointer-events: none;
        }

        .graph-frame iframe {
          width: 100%;
          height: 640px;
          min-height: 640px;
          border: 0;
          display: block;
          border-radius: 10px;
        }

        .example-note {
          font-size: 14px;
          color: #8b98ad;
        }

        @media (max-width: 1100px) {
          .search-layout {
            grid-template-columns: 1fr;
          }

          .detail-layout {
            grid-template-columns: 1fr;
          }

          .detail-sidebar {
            position: static;
          }
        }

        @media (max-width: 680px) {
          .proto-container {
            padding: 0 18px;
          }

          .detail-toolbar {
            grid-template-columns: 1fr;
          }

          .detail-back-link {
            width: 100%;
          }

          .detail-search-form {
            width: 100%;
            justify-self: stretch;
          }
        }
      </style>

      <section class="search-shell">
        <div class="proto-container">
          <section class="query-panel">
            <div class="detail-toolbar">
              <a class="detail-back-link" href="<?= htmlspecialchars($browseBackUrl, ENT_QUOTES, 'UTF-8') ?>">&larr; Back to Browse</a>
              <form id="search-form" class="detail-search-form" method="GET">
                <input type="hidden" name="type" value="all">
                <input type="hidden" name="lang" value="<?= htmlspecialchars($siteLang, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="renderer" value="<?= htmlspecialchars($siteRenderer, ENT_QUOTES, 'UTF-8') ?>">
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
                <div id="search-summary" class="panel-body">
                  <?php if ($repbase !== null): ?>
                    <div><strong>Matched query: </strong><?= htmlspecialchars($repbase['matched'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Entity type: </strong>TE</div>
                    <div><strong>Name: </strong><?= htmlspecialchars($repbase['nm'] ?: $repbase['id'] ?: '-', ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Description: </strong><?= htmlspecialchars($repbase['description'] ?: 'No description', ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Keywords: </strong><?= htmlspecialchars($repbase['keywords'] ?: 'No keywords', ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Length: </strong><?= htmlspecialchars($repbase['length_bp'] !== null ? ((string) $repbase['length_bp']) . ' bp' : 'No length available', ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Reference count: </strong><?= htmlspecialchars((string) ($repbase['reference_count'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                  <?php elseif ($query !== ''): ?>
                    No structured TE summary is available for the current query yet.
                  <?php else: ?>
                    Search for a TE, disease, function, or PMID to view a concise summary here.
                  <?php endif; ?>
                </div>
              </section>

              <section id="search-graph-panel" class="graph-panel is-collapsed">
                <div class="graph-panel-head">
                  <h3>Local Graph</h3>
                  <button id="search-graph-toggle" type="button" class="graph-toggle" aria-expanded="false" aria-controls="search-graph-frame-wrap" title="Expand local graph"><span id="search-graph-toggle-icon" aria-hidden="true">&#9662;</span></button>
                </div>
                <div id="search-graph-frame-wrap" class="graph-frame">
                  <iframe
                    id="search-g6-frame"
                    src="<?= htmlspecialchars($searchGraphSrc, ENT_QUOTES, 'UTF-8') ?>"
                    title="Search graph (G6)"
                  ></iframe>
                </div>
              </section>

              <?php if ($dfamSequence !== null): ?>
                <section id="search-sequence-panel" class="data-panel sequence-panel">
                  <h3>Sequence</h3>
                  <div class="sequence-meta">
                    <div><strong>Matched query: </strong><?= htmlspecialchars($dfamSequence['matched_query'] ?? $query, ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Dfam accession: </strong><?= htmlspecialchars($dfamSequence['accession'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Dfam family: </strong><?= htmlspecialchars($dfamSequence['name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Length: </strong><?= htmlspecialchars(!empty($dfamSequence['sequence_length_bp']) ? ((string) $dfamSequence['sequence_length_bp']) . ' bp' : 'No length available', ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Model type: </strong><?= htmlspecialchars($dfamSequence['model_type_label'] ?? 'Consensus model', ENT_QUOTES, 'UTF-8') ?></div>
                    <?php if (!empty($dfamSequence['display_classification'])): ?>
                      <div><strong>Classification: </strong><?= htmlspecialchars((string) $dfamSequence['display_classification'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                  </div>
                  <?php if (!empty($dfamSequence['is_fragment'])): ?>
                    <div class="sequence-fragment-note">
                      This sequence is a Dfam fragment consensus model (<?= htmlspecialchars(strtolower((string) ($dfamSequence['model_type_label'] ?? 'fragment model')), ENT_QUOTES, 'UTF-8') ?>).
                    </div>
                  <?php endif; ?>
                  <div class="sequence-code-wrap">
                    <pre class="sequence-code"><?= htmlspecialchars(tekg_format_sequence_proto((string) ($dfamSequence['sequence'] ?? '')), ENT_QUOTES, 'UTF-8') ?></pre>
                  </div>
                  <?php if (!empty($dfamSequence['structure_svg_path'])): ?>
                    <div class="sequence-plot">
                      <img src="<?= htmlspecialchars((string) $dfamSequence['structure_svg_path'], ENT_QUOTES, 'UTF-8') ?>" alt="Dfam sequence structure plot for <?= htmlspecialchars((string) ($dfamSequence['name'] ?? $query), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                  <?php endif; ?>
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
                          <label class="jbrowse-hit-picker-label" for="searchJBrowseHitSelect">Genomic hit</label>
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

      <script>
        (() => {
          const header = document.getElementById('protoHeader');
          function syncHeader() {
            if (!header) return;
            header.classList.toggle('is-scrolled', window.scrollY > 12);
          }
          window.addEventListener('scroll', syncHeader, { passive: true });
          syncHeader();
        })();
      </script>

      <script>
      (function () {
        const panel = document.getElementById('search-karyotype-panel');
        const view = document.getElementById('search-karyotype-view');
        const status = document.getElementById('search-karyotype-status');

        if (!panel || !view) {
          return;
        }

        const dataPath = view.dataset.karyotypePath || '';
        if (!dataPath || typeof window.Karyotype !== 'function') {
          if (status) {
            status.textContent = 'Genome annotation distribution is unavailable right now.';
          }
          return;
        }

        fetch(dataPath, { cache: 'no-store' })
          .then(function (response) {
            if (!response.ok) {
              throw new Error('Failed to load karyotype data');
            }
            return response.json();
          })
          .then(function (data) {
            view.innerHTML = '';
            new window.Karyotype(view, data);
            if (status) {
              status.hidden = true;
            }
          })
          .catch(function (error) {
            console.error(error);
            if (status) {
              status.textContent = 'Genome annotation distribution is unavailable right now.';
              status.hidden = false;
            }
          });
      }());
      </script>
      <?php if ($jbrowseSession !== null): ?>
      <script src="https://unpkg.com/@jbrowse/react-linear-genome-view2@3.5.0/dist/react-linear-genome-view.umd.production.min.js" crossorigin></script>
      <script>
      (function () {
        const mount = document.getElementById('search_jbrowse_linear_genome_view');
        const controls = Array.from(document.querySelectorAll('#searchJBrowseTrackControls input[data-track-id]'));
        const hitSelect = document.getElementById('searchJBrowseHitSelect');
        const repeatCountEl = document.getElementById('searchJBrowseRepeatCount');
        const refseqCountEl = document.getElementById('searchJBrowseRefseqCount');
        const defaultLocEl = document.getElementById('searchJBrowseDefaultLoc');
        const openLink = document.getElementById('searchJBrowseOpenLink');
        const browserBaseUrl = <?= json_encode((string) ($jbrowseSession['browser_url'] ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        const configUrl = <?= json_encode((string) ($jbrowseSession['config_url'] ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

        if (!mount || !configUrl || typeof window.JBrowseReactLinearGenomeView === 'undefined') {
          return;
        }

        const { React, createRoot, createViewState, JBrowseLinearGenomeView } = window.JBrowseReactLinearGenomeView;
        const root = createRoot(mount);
        let runtimeConfig = null;

        function getSelectedTrackIds() {
          return controls.filter(input => input.checked).map(input => input.dataset.trackId);
        }

        function getSelectedHitParams() {
          const option = hitSelect && hitSelect.selectedOptions.length ? hitSelect.selectedOptions[0] : null;
          return {
            chrom: option ? String(option.dataset.chrom || '') : '',
            start: option ? String(option.dataset.start || '') : '',
            end: option ? String(option.dataset.end || '') : '',
          };
        }

        function buildBrowserUrl() {
          const url = new URL(browserBaseUrl, window.location.origin);
          const hit = getSelectedHitParams();
          if (hit.chrom) {
            url.searchParams.set('chr', hit.chrom);
          }
          if (hit.start) {
            url.searchParams.set('start', hit.start);
          }
          if (hit.end) {
            url.searchParams.set('end', hit.end);
          }
          url.searchParams.delete('format');
          return url.toString();
        }

        function buildConfigUrl() {
          const url = new URL(buildBrowserUrl());
          url.searchParams.set('format', 'config');
          return url.toString();
        }

        function renderBrowser() {
          if (!runtimeConfig) {
            return;
          }
          const selectedTrackIds = getSelectedTrackIds();
          const trackConfigs = [
            {
              type: 'FeatureTrack',
              trackId: 'repeats_hg38',
              name: 'Repeats',
              assemblyNames: ['hg38'],
              category: ['Annotation'],
              adapter: {
                type: 'Gff3Adapter',
                gffLocation: { uri: runtimeConfig.repeatTrackUrl },
              },
            },
            {
              type: 'FeatureTrack',
              trackId: 'ncbi_refseq_window',
              name: 'NCBI RefSeq',
              assemblyNames: ['hg38'],
              category: ['Annotation'],
              adapter: {
                type: 'Gff3Adapter',
                gffLocation: { uri: runtimeConfig.refseqTrackUrl },
              },
            },
            {
              type: 'FeatureTrack',
              trackId: 'clinvar_variants',
              name: 'ClinVar variants',
              assemblyNames: ['hg38'],
              category: ['ClinVar'],
              adapter: {
                type: 'BigBedAdapter',
                uri: runtimeConfig.clinvarMainUrl,
              },
            },
            {
              type: 'FeatureTrack',
              trackId: 'clinvar_cnv',
              name: 'ClinVar CNV',
              assemblyNames: ['hg38'],
              category: ['ClinVar'],
              adapter: {
                type: 'BigBedAdapter',
                uri: runtimeConfig.clinvarCnvUrl,
              },
            },
          ];
          const selectedTracks = trackConfigs.filter(track => selectedTrackIds.includes(track.trackId));
          const state = new createViewState({
            assembly: {
              name: 'hg38',
              sequence: {
                type: 'ReferenceSequenceTrack',
                trackId: 'hg38_reference',
                name: 'Reference sequence',
                assemblyNames: ['hg38'],
                adapter: {
                  type: 'IndexedFastaAdapter',
                  fastaLocation: { uri: runtimeConfig.fastaUrl },
                  faiLocation: { uri: runtimeConfig.faiUrl },
                },
              },
            },
            tracks: selectedTracks,
            defaultSession: {
              name: runtimeConfig.pageMeta && runtimeConfig.pageMeta.te ? `JBrowse - ${runtimeConfig.pageMeta.te}` : 'JBrowse locus session',
              view: {
                id: 'linearGenomeView',
                type: 'LinearGenomeView',
                init: {
                  assembly: 'hg38',
                  loc: runtimeConfig.pageMeta.defaultLoc,
                  tracks: selectedTrackIds,
                },
              },
            },
          });
          root.render(React.createElement(JBrowseLinearGenomeView, { viewState: state }));
        }

        function applyConfig(config) {
          runtimeConfig = config;
          if (defaultLocEl && config.pageMeta) {
            defaultLocEl.textContent = String(config.pageMeta.defaultLoc ?? '-');
          }
          if (repeatCountEl && config.pageMeta) {
            repeatCountEl.textContent = String(config.pageMeta.repeatFeatureCount ?? '-');
          }
          if (refseqCountEl && config.pageMeta) {
            refseqCountEl.textContent = String(config.pageMeta.refseqFeatureCount ?? '-');
          }
          if (openLink) {
            openLink.href = buildBrowserUrl();
          }
          renderBrowser();
          window.requestAnimationFrame(renderBrowser);
          window.setTimeout(renderBrowser, 120);
        }

        function loadConfig(url) {
          root.render(React.createElement('div', { className: 'jbrowse-loading' }, 'Loading selected genomic hit...'));
          fetch(url, { cache: 'no-store' })
            .then(function (response) {
              if (!response.ok) {
                throw new Error('Failed to load JBrowse config');
              }
              return response.json();
            })
            .then(applyConfig)
            .catch(function (error) {
              console.error(error);
              mount.innerHTML = '<div class="jbrowse-loading">Genome browser is unavailable right now.</div>';
            });
        }

        controls.forEach(input => {
          input.addEventListener('change', renderBrowser);
        });
        if (hitSelect) {
          hitSelect.addEventListener('change', function () {
            loadConfig(buildConfigUrl());
          });
        }

        loadConfig(configUrl);
      }());
      </script>
      <?php endif; ?>

      <script>
      (function () {
        const graphPanelEl = document.getElementById('search-graph-panel');
        const graphToggleBtn = document.getElementById('search-graph-toggle');
        const graphToggleIconEl = document.getElementById('search-graph-toggle-icon');
        const navLinks = Array.from(document.querySelectorAll('[data-detail-nav-link]'));

        function setGraphExpanded(expanded) {
          if (!graphPanelEl || !graphToggleBtn) return;
          graphPanelEl.classList.toggle('is-collapsed', !expanded);
          graphToggleBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
          if (graphToggleIconEl) {
            graphToggleIconEl.innerHTML = expanded ? '&#9652;' : '&#9662;';
          }
          graphToggleBtn.title = expanded ? 'Collapse local graph' : 'Expand local graph';
        }

        function setActiveSection(id) {
          navLinks.forEach((link) => {
            const isActive = link.getAttribute('href') === `#${id}`;
            link.classList.toggle('is-active', isActive);
            if (isActive) {
              link.setAttribute('aria-current', 'location');
            } else {
              link.removeAttribute('aria-current');
            }
          });
        }

        if (graphToggleBtn) {
          graphToggleBtn.addEventListener('click', function () {
            const expanded = graphPanelEl ? graphPanelEl.classList.contains('is-collapsed') : false;
            setGraphExpanded(expanded);
          });
        }

        navLinks.forEach((link) => {
          link.addEventListener('click', function () {
            const targetId = (link.getAttribute('href') || '').replace('#', '');
            if (targetId === 'search-graph-panel') {
              setGraphExpanded(true);
            }
            if (targetId) {
              setActiveSection(targetId);
            }
          });
        });

        const sections = navLinks
          .map((link) => document.getElementById((link.getAttribute('href') || '').replace('#', '')))
          .filter(Boolean);

        if (sections.length > 0) {
          setActiveSection(sections[0].id);
          if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
              const visible = entries
                .filter((entry) => entry.isIntersecting)
                .sort((a, b) => b.intersectionRatio - a.intersectionRatio);
              if (visible.length > 0 && visible[0].target && visible[0].target.id) {
                setActiveSection(visible[0].target.id);
              }
            }, {
              rootMargin: '-15% 0px -65% 0px',
              threshold: [0.05, 0.15, 0.35, 0.6],
            });
            sections.forEach((section) => observer.observe(section));
          }
        }

        setGraphExpanded(false);
      }());
      </script>
    </main>
  </div>
</body>
</html>

