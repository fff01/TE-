<?php
require_once __DIR__ . '/path_config.php';
$pageTitle = 'TE-KG Download';
$activePage = 'download';
$protoCurrentPath = tekg_app_url('download.php');
$protoSubtitle = 'Public graph datasets currently exposed through the site';
$downloadCssVersion = (int)@filemtime(tekg_assets_fs_path('css/pages/download.css'));
$downloadJsVersion = (int)@filemtime(tekg_assets_fs_path('js/pages/download.js'));
$pageExtraStylesheets = [
    tekg_assets_url('css/pages/download.css') . '?v=' . $downloadCssVersion,
];
require __DIR__ . '/head.php';

$downloadItems = [
    [
        'category' => 'Expression',
        'dataset' => 'Cancer Cell Line expression matrix',
        'filename' => 'CCLE_TE_normalized_count.tsv',
        'path' => tekg_app_url('data/bulk_expression_web/cancer_cell_line/CCLE_TE_normalized_count.tsv'),
        'format' => 'TSV',
        'used_in' => 'Expression browse, detail summary, and Plotly views',
        'description' => 'Raw bulk expression matrix for the cancer cell line cohort used to derive Cancer Cell Line expression summaries and charts.',
    ],
    [
        'category' => 'Expression',
        'dataset' => 'Cancer Cell Line metadata',
        'filename' => 'CCLE_meta.csv',
        'path' => tekg_app_url('data/bulk_expression_web/cancer_cell_line/CCLE_meta.csv'),
        'format' => 'CSV',
        'used_in' => 'Expression dataset preprocessing',
        'description' => 'Metadata mapping each cancer cell line run to its cohort label for aggregation and plotting.',
    ],
    [
        'category' => 'Expression',
        'dataset' => 'Normal Cell Line expression matrix',
        'filename' => 'Normal_cell_line_TE_normalized_count.tsv',
        'path' => tekg_app_url('data/bulk_expression_web/normal_cell_line/Normal_cell_line_TE_normalized_count.tsv'),
        'format' => 'TSV',
        'used_in' => 'Expression browse, detail summary, and Plotly views',
        'description' => 'Raw bulk expression matrix for normal cell line contexts used in the public Expression module.',
    ],
    [
        'category' => 'Expression',
        'dataset' => 'Normal Cell Line metadata',
        'filename' => 'Normal_cell_line_meta.csv',
        'path' => tekg_app_url('data/bulk_expression_web/normal_cell_line/Normal_cell_line_meta.csv'),
        'format' => 'CSV',
        'used_in' => 'Expression dataset preprocessing',
        'description' => 'Metadata mapping normal cell line runs to their cell type labels.',
    ],
    [
        'category' => 'Expression',
        'dataset' => 'Normal Tissue expression matrix',
        'filename' => 'Normal_tissue_TE_normalized_count.tsv',
        'path' => tekg_app_url('data/bulk_expression_web/normal_tissue/Normal_tissue_TE_normalized_count.tsv'),
        'format' => 'TSV',
        'used_in' => 'Expression browse, detail summary, and Plotly views',
        'description' => 'Raw bulk expression matrix for normal tissue contexts used in the public Expression module.',
    ],
    [
        'category' => 'Expression',
        'dataset' => 'Normal Tissue metadata',
        'filename' => 'Normal_tissue_meta.csv',
        'path' => tekg_app_url('data/bulk_expression_web/normal_tissue/Normal_tissue_meta.csv'),
        'format' => 'CSV',
        'used_in' => 'Expression dataset preprocessing',
        'description' => 'Metadata mapping normal tissue runs to their organ labels.',
    ],
    [
        'category' => 'Graph',
        'dataset' => 'Graph seed',
        'filename' => 'te_kg2_graph_seed.json',
        'path' => tekg_data_url('processed/te_kg2_graph_seed.json'),
        'format' => 'JSON',
        'used_in' => 'Database import and graph preview',
        'description' => 'Canonical TE, disease, function, and paper nodes together with the core graph relations used by the current public graph.',
    ],
    [
        'category' => 'Graph',
        'dataset' => 'Normalized graph extraction',
        'filename' => 'te_kg2_normalized_output.jsonl',
        'path' => tekg_data_url('processed/te_kg2_normalized_output.jsonl'),
        'format' => 'JSONL',
        'used_in' => 'Database build pipeline',
        'description' => 'Normalized relation extraction result used as the upstream structured source for the current graph seed.',
    ],
    [
        'category' => 'Taxonomy',
        'dataset' => 'RMSK + Repbase TE taxonomy tree',
        'filename' => 'tree_rmsk_repbase.txt',
        'path' => tekg_data_url('taxonomy/transposon_tree/tree_rmsk_repbase.txt'),
        'format' => 'TXT',
        'used_in' => 'Homepage TE classification and taxonomy tree preview',
        'description' => 'Human TE taxonomy tree parsed from the RMSK + Repbase source used by the public classification view.',
    ],
    [
        'category' => 'Taxonomy',
        'dataset' => 'Full TE taxonomy tree',
        'filename' => 'tree_all.txt',
        'path' => tekg_data_url('taxonomy/transposon_tree/tree_all.txt'),
        'format' => 'TXT',
        'used_in' => 'Taxonomy review and lineage comparison',
        'description' => 'Full TE taxonomy tree source used for broader lineage inspection.',
    ],
];

$categoryCounts = [];
foreach ($downloadItems as $item) {
    $category = (string)($item['category'] ?? 'Other');
    $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
}
ksort($categoryCounts);
?>
      <section class="download-shell">
        <div class="proto-container">
          <h1 class="download-page-title">Download</h1>

          <section class="download-panel">
            <div class="download-category-filter" aria-label="Dataset category filter">
              <button type="button" class="is-active" data-download-category="All">All <span><?= htmlspecialchars((string)count($downloadItems), ENT_QUOTES, 'UTF-8') ?></span></button>
<?php foreach ($categoryCounts as $category => $count): ?>
              <button type="button" data-download-category="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?> <span><?= htmlspecialchars((string)$count, ENT_QUOTES, 'UTF-8') ?></span></button>
<?php endforeach; ?>
            </div>
            <div class="download-divider"></div>

            <div class="download-tools">
              <div class="download-tools-left">
                <select id="download-page-size" class="download-select" aria-label="Entries per page">
                  <option value="5">5</option>
                  <option value="10" selected>10</option>
                  <option value="20">20</option>
                </select>
                <span>entries per page</span>
              </div>
              <div class="download-tools-right">
                <label for="download-search">Search:</label>
                <input id="download-search" class="download-search" type="text" placeholder="Dataset, filename, or usage">
              </div>
            </div>

            <div class="download-table-wrap">
              <table class="download-table">
                <thead>
                  <tr>
                    <th>Dataset</th>
                    <th>File</th>
                    <th>Used in</th>
                    <th>Format</th>
                  </tr>
                </thead>
                <tbody id="download-table-body"></tbody>
              </table>
              <div id="download-empty" class="download-empty" hidden>No datasets match the current filter.</div>
            </div>

            <div class="download-footer">
              <div id="download-summary">Showing 0 to 0 of 0 entries</div>
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
