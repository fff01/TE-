<?php
require_once __DIR__ . '/site_i18n.php';

$pageTitle = 'TE-KG Search';
$activePage = 'search';
$protoCurrentPath = '/TE-/search.php';
$protoSubtitle = 'Search the current TE knowledge graph';

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
$searchGraphSrc = $siteRenderer === 'g6'
    ? site_url_with_state('/TE-/index_g6.html', $siteLang, 'g6', array_filter([
        'embed' => 'search-result',
        'q' => $query !== '' ? $query : null,
      ], static fn ($value) => $value !== null && $value !== ''))
    : site_url_with_state('/TE-/index_demo.html', $siteLang, 'cytoscape', array_filter([
        'embed' => 'search-result',
        'q' => $query !== '' ? $query : null,
      ], static fn ($value) => $value !== null && $value !== ''));

require __DIR__ . '/head.php';
?>
      <style>
        .search-shell {
          background: #f5f9ff;
          min-height: calc(100vh - 82px);
          padding: 34px 0 54px;
        }

        .proto-container {
          max-width: 1320px;
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

        .query-panel {
          background: #ffffff;
          border: 1px solid #dbe7f8;
          border-radius: 10px;
          box-shadow: 0 10px 28px rgba(26, 60, 112, 0.08);
          padding: 34px 42px 30px;
          margin-bottom: 28px;
        }

        .query-panel h2 {
          margin: 0 0 22px;
          font-size: 28px;
          font-weight: 700;
          color: #7b8597;
        }

        .query-divider {
          height: 1px;
          background: #e2e8f2;
          margin-bottom: 32px;
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

        .search-layout.is-hidden {
          display: none;
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
          .query-form-grid,
          .search-layout {
            grid-template-columns: 1fr;
          }
        }

        @media (max-width: 680px) {
          .proto-container {
            padding: 0 18px;
          }

          .query-panel {
            padding: 24px 18px 22px;
          }

          .query-actions {
            gap: 10px;
          }

          .query-btn {
            width: 100%;
          }
        }
      </style>

      <section class="search-shell">
        <div class="proto-container">
          <h1 class="download-page-title">Search</h1>
          <div class="download-crumbs">
            <a href="<?= htmlspecialchars(site_url_with_state('/TE-/index.php'), ENT_QUOTES, 'UTF-8') ?>">Home</a>
            <span>/</span>
            <span>Search</span>
          </div>

          <section class="query-panel">
            <h2>Query by keyword</h2>
            <div class="query-divider"></div>
            <form id="search-form" method="GET">
              <input type="hidden" name="type" value="all">
              <div class="query-form-grid">
                <div class="query-field">
                  <label for="search-query">Keyword or identifier</label>
                  <input id="search-query" class="query-control" type="text" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="Enter a keyword, TE, disease, function, or PMID">
                </div>
              </div>
              <div class="query-actions">
                <button class="query-btn is-primary" type="submit">Search</button>
                <button class="query-btn" id="search-reset" type="button">Reset</button>
                <button class="query-btn" id="search-example" type="button">Example</button>
                <span class="example-note">Example query: L1HS</span>
              </div>
            </form>
          </section>

          <section id="search-results" class="search-layout<?= $query === '' ? ' is-hidden' : '' ?>">
            <section class="data-panel">
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
                  id="<?= $siteRenderer === 'g6' ? 'search-g6-frame' : 'search-cyt-frame' ?>"
                  src="<?= htmlspecialchars($searchGraphSrc, ENT_QUOTES, 'UTF-8') ?>"
                  title="Search graph (<?= htmlspecialchars(strtoupper($siteRenderer), ENT_QUOTES, 'UTF-8') ?>)"
                ></iframe>
              </div>
            </section>

            <?php if ($dfamSequence !== null): ?>
              <section class="data-panel sequence-panel">
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
          </section>
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

      <script>
      (function () {
        const graphPanelEl = document.getElementById('search-graph-panel');
        const graphToggleBtn = document.getElementById('search-graph-toggle');
        const graphToggleIconEl = document.getElementById('search-graph-toggle-icon');
        const resetBtn = document.getElementById('search-reset');
        const exampleBtn = document.getElementById('search-example');
        const searchForm = document.getElementById('search-form');
        const queryInput = document.getElementById('search-query');
        const siteLang = <?= json_encode($siteLang, JSON_UNESCAPED_UNICODE) ?>;
        const siteRenderer = <?= json_encode($siteRenderer, JSON_UNESCAPED_UNICODE) ?>;

        function setGraphExpanded(expanded) {
          if (!graphPanelEl || !graphToggleBtn) return;
          graphPanelEl.classList.toggle('is-collapsed', !expanded);
          graphToggleBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
          if (graphToggleIconEl) {
            graphToggleIconEl.innerHTML = expanded ? '&#9652;' : '&#9662;';
          }
          graphToggleBtn.title = expanded ? 'Collapse local graph' : 'Expand local graph';
        }

        if (graphToggleBtn) {
          graphToggleBtn.addEventListener('click', function () {
            const expanded = graphPanelEl ? graphPanelEl.classList.contains('is-collapsed') : false;
            setGraphExpanded(expanded);
          });
        }

        if (exampleBtn) {
          exampleBtn.addEventListener('click', function () {
            if (queryInput) {
              queryInput.value = 'L1HS';
            }
            if (searchForm) {
              searchForm.requestSubmit();
            }
          });
        }

        if (resetBtn) {
          resetBtn.addEventListener('click', function () {
            const url = new URL('/TE-/search.php', window.location.origin);
            url.searchParams.set('type', 'all');
            url.searchParams.set('lang', siteLang);
            url.searchParams.set('renderer', siteRenderer);
            window.location.href = url.toString();
          });
        }

        setGraphExpanded(false);
      }());
      </script>
    </main>
  </div>
</body>
</html>
