<?php
require_once __DIR__ . '/path_config.php';

$pageTitle = 'TE-KG Download';
$activePage = 'download';
$protoCurrentPath = tekg_app_url('download.php');
$protoSubtitle = 'Public TE-KG datasets and supporting data exports';
$downloadCssVersion = (int)@filemtime(tekg_assets_fs_path('css/pages/download.css'));
$downloadJsVersion = (int)@filemtime(tekg_assets_fs_path('js/pages/download.js'));
$pageExtraStylesheets = [
    tekg_assets_url('css/pages/download.css') . '?v=' . $downloadCssVersion,
];

function download_format_bytes(int $bytes): string
{
    if ($bytes <= 0) {
        return 'Unavailable';
    }

    $units = ['B', 'KB', 'MB', 'GB'];
    $value = (float)$bytes;
    $unitIndex = 0;
    while ($value >= 1024 && $unitIndex < count($units) - 1) {
        $value /= 1024;
        $unitIndex += 1;
    }

    $precision = $value >= 10 || $unitIndex === 0 ? 0 : 1;
    return number_format($value, $precision) . ' ' . $units[$unitIndex];
}

function download_item(string $category, string $dataset, string $filename, string $relativePath, string $format, string $usedIn, string $description): array
{
    $fsPath = tekg_fs_from_project_relative($relativePath);
    $available = is_file($fsPath);
    $bytes = $available ? max(0, (int)filesize($fsPath)) : 0;

    return [
        'category' => $category,
        'dataset' => $dataset,
        'filename' => $filename,
        'href' => tekg_url_from_project_relative($relativePath),
        'format' => $format,
        'used_in' => $usedIn,
        'description' => $description,
        'available' => $available,
        'bytes' => $bytes,
        'size_label' => download_format_bytes($bytes),
    ];
}

$downloadItems = [
    download_item(
        'Expression',
        'Cancer Cell Line expression matrix',
        'CCLE_TE_normalized_count.tsv',
        'data/bulk_expression_web/cancer_cell_line/CCLE_TE_normalized_count.tsv',
        'TSV',
        'Expression browse, detail summary, and Plotly views',
        'Raw TE expression matrix for cancer cell line contexts in the public Expression module.'
    ),
    download_item(
        'Expression',
        'Cancer Cell Line metadata',
        'CCLE_meta.csv',
        'data/bulk_expression_web/cancer_cell_line/CCLE_meta.csv',
        'CSV',
        'Expression dataset preprocessing',
        'Sample metadata mapping cancer cell line runs to cohort labels for aggregation and plotting.'
    ),
    download_item(
        'Expression',
        'Normal Cell Line expression matrix',
        'Normal_cell_line_TE_normalized_count.tsv',
        'data/bulk_expression_web/normal_cell_line/Normal_cell_line_TE_normalized_count.tsv',
        'TSV',
        'Expression browse, detail summary, and Plotly views',
        'Raw TE expression matrix for normal cell line contexts in the public Expression module.'
    ),
    download_item(
        'Expression',
        'Normal Cell Line metadata',
        'Normal_cell_line_meta.csv',
        'data/bulk_expression_web/normal_cell_line/Normal_cell_line_meta.csv',
        'CSV',
        'Expression dataset preprocessing',
        'Sample metadata mapping normal cell line runs to cell type labels.'
    ),
    download_item(
        'Expression',
        'Normal Tissue expression matrix',
        'Normal_tissue_TE_normalized_count.tsv',
        'data/bulk_expression_web/normal_tissue/Normal_tissue_TE_normalized_count.tsv',
        'TSV',
        'Expression browse, detail summary, and Plotly views',
        'Raw TE expression matrix for normal tissue contexts in the public Expression module.'
    ),
    download_item(
        'Expression',
        'Normal Tissue metadata',
        'Normal_tissue_meta.csv',
        'data/bulk_expression_web/normal_tissue/Normal_tissue_meta.csv',
        'CSV',
        'Expression dataset preprocessing',
        'Sample metadata mapping normal tissue runs to organ labels.'
    ),
    download_item(
        'Graph',
        'Graph seed export',
        'te_kg2_graph_seed.json',
        'data/processed/te_kg2_graph_seed.json',
        'JSON',
        'Database import and graph preview',
        'Current public graph seed export containing TE, disease, function, paper nodes, and core relations.'
    ),
    download_item(
        'Graph',
        'Normalized relation extraction',
        'te_kg2_normalized_output.jsonl',
        'data/processed/te_kg2_normalized_output.jsonl',
        'JSONL',
        'Database build pipeline',
        'Normalized relation extraction output used as an upstream structured source for graph import.'
    ),
    download_item(
        'Taxonomy',
        'RMSK + Repbase TE taxonomy tree',
        'tree_rmsk_repbase.txt',
        'data/taxonomy/transposon_tree/tree_rmsk_repbase.txt',
        'TXT',
        'Homepage TE classification and taxonomy tree preview',
        'Human TE taxonomy tree parsed from the RMSK + Repbase source used by the public classification view.'
    ),
    download_item(
        'Taxonomy',
        'Full TE taxonomy tree',
        'tree_all.txt',
        'data/taxonomy/transposon_tree/tree_all.txt',
        'TXT',
        'Taxonomy review and lineage comparison',
        'Full TE taxonomy tree source used for broader lineage inspection.'
    ),
    download_item(
        'Taxonomy',
        'TE classification table',
        'te_234_classification.csv',
        'data/taxonomy/te_234/te_234_classification.csv',
        'CSV',
        'Taxonomy standardization checks',
        'Curated TE classification table used during taxonomy cleanup and standardization.'
    ),
    download_item(
        'Taxonomy',
        'Homepage TE taxonomy snapshot',
        'tekg3_homepage_taxonomy.json',
        'data/processed/tekg3_homepage_taxonomy.json',
        'JSON',
        'Homepage TE classification donut and taxonomy summary',
        'Neo4j tekg3-derived taxonomy snapshot used for homepage classification summaries.'
    ),
    download_item(
        'Evidence',
        'PubMed metadata with journal metrics',
        'pubmed_metadata_with_metrics.jsonl',
        'data/processed/pubmed_metadata_with_metrics.jsonl',
        'JSONL',
        'Evidence tables and journal metric display',
        'PubMed title, journal, year, and journal metric enrichment records used by evidence support views.'
    ),
    download_item(
        'Evidence',
        'Unique PubMed journal inventory',
        'pubmed_unique_journals.csv',
        'data/processed/pubmed_unique_journals.csv',
        'CSV',
        'Journal metric matching audit',
        'Journal title inventory used to audit and match publication metadata to journal metrics.'
    ),
];

$categoryCounts = [];
$formats = [];
$availableCount = 0;
$totalBytes = 0;
foreach ($downloadItems as $item) {
    $category = (string)$item['category'];
    $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
    $formats[(string)$item['format']] = true;
    if ($item['available']) {
        $availableCount += 1;
        $totalBytes += (int)$item['bytes'];
    }
}
ksort($categoryCounts);
ksort($formats);

$downloadSummary = [
    'datasets' => count($downloadItems),
    'available' => $availableCount,
    'categories' => count($categoryCounts),
    'formats' => count($formats),
    'total_size_label' => download_format_bytes($totalBytes),
];

require __DIR__ . '/head.php';
?>
      <section class="download-shell">
        <div class="proto-container">
          <div class="download-hero">
            <div>
              <h1 class="download-page-title">Download</h1>
              <p class="download-page-copy">Browse TE-KG public data exports by category, verify availability, and download the files that back the site experience.</p>
            </div>
            <div class="download-crumbs">
              <a href="<?= htmlspecialchars(site_url_with_state(tekg_app_url('index.php')), ENT_QUOTES, 'UTF-8') ?>">Home</a>
              <span>/</span>
              <span>Download</span>
            </div>
          </div>

          <section class="download-summary-grid" aria-label="Download catalog summary">
            <article class="download-summary-card">
              <span>Datasets</span>
              <strong><?= htmlspecialchars((string)$downloadSummary['datasets'], ENT_QUOTES, 'UTF-8') ?></strong>
            </article>
            <article class="download-summary-card">
              <span>Available</span>
              <strong><?= htmlspecialchars((string)$downloadSummary['available'], ENT_QUOTES, 'UTF-8') ?></strong>
            </article>
            <article class="download-summary-card">
              <span>Categories</span>
              <strong><?= htmlspecialchars((string)$downloadSummary['categories'], ENT_QUOTES, 'UTF-8') ?></strong>
            </article>
            <article class="download-summary-card">
              <span>Total Size</span>
              <strong><?= htmlspecialchars((string)$downloadSummary['total_size_label'], ENT_QUOTES, 'UTF-8') ?></strong>
            </article>
          </section>

          <section class="download-panel">
            <div class="download-panel-header">
              <div>
                <h2>Data catalog</h2>
                <p>Files are grouped by how they support TE-KG runtime pages, graph evidence, and taxonomy views.</p>
              </div>
              <div class="download-tools-right">
                <label for="download-search">Search</label>
                <input id="download-search" class="download-search" type="text" placeholder="Dataset, filename, usage, or format">
              </div>
            </div>

            <div class="download-category-filter" aria-label="Dataset category filter">
              <button type="button" class="is-active" data-download-category="All">All <span><?= count($downloadItems) ?></span></button>
<?php foreach ($categoryCounts as $category => $count): ?>
              <button type="button" data-download-category="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?> <span><?= htmlspecialchars((string)$count, ENT_QUOTES, 'UTF-8') ?></span></button>
<?php endforeach; ?>
            </div>

            <div class="download-tools">
              <div class="download-tools-left">
                <select id="download-page-size" class="download-select" aria-label="Entries per page">
                  <option value="6" selected>6</option>
                  <option value="12">12</option>
                  <option value="24">24</option>
                </select>
                <span>items per page</span>
              </div>
              <div id="download-summary" class="download-result-summary">Showing 0 to 0 of 0 datasets</div>
            </div>

            <div id="download-card-list" class="download-card-grid"></div>
            <div id="download-empty" class="download-empty" hidden>No datasets match the current filter.</div>

            <div class="download-footer">
              <div class="download-footnote">Unavailable files are shown for audit visibility but cannot be downloaded.</div>
              <div id="download-pagination" class="download-pagination"></div>
            </div>
          </section>
        </div>
      </section>

      <script id="download-page-data" type="application/json"><?= json_encode($downloadItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
      <script src="<?= htmlspecialchars(tekg_assets_url('js/pages/download.js') . '?v=' . $downloadJsVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
    </main>
  </div>
</body>
</html>
