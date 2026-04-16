<?php
$pageTitle = 'TE-KG Download';
$activePage = 'download';
$protoCurrentPath = '/TE-/download.php';
$protoSubtitle = 'Public graph datasets currently exposed through the site';
require __DIR__ . '/head.php';

$downloadItems = [
    [
        'dataset' => 'Cancer Cell Line expression matrix',
        'filename' => 'CCLE_TE_normalized_count.tsv',
        'path' => '/TE-/new_data/bulk_expression_web/cancer_cell_line/CCLE_TE_normalized_count.tsv',
        'format' => 'TSV',
        'used_in' => 'Expression browse, detail summary, and Plotly views',
        'description' => 'Raw bulk expression matrix for the cancer cell line cohort used to derive Cancer Cell Line expression summaries and charts.',
    ],
    [
        'dataset' => 'Cancer Cell Line metadata',
        'filename' => 'CCLE_meta.csv',
        'path' => '/TE-/new_data/bulk_expression_web/cancer_cell_line/CCLE_meta.csv',
        'format' => 'CSV',
        'used_in' => 'Expression dataset preprocessing',
        'description' => 'Metadata mapping each cancer cell line run to its cohort label for aggregation and plotting.',
    ],
    [
        'dataset' => 'Normal Cell Line expression matrix',
        'filename' => 'Normal_cell_line_TE_normalized_count.tsv',
        'path' => '/TE-/new_data/bulk_expression_web/normal_cell_line/Normal_cell_line_TE_normalized_count.tsv',
        'format' => 'TSV',
        'used_in' => 'Expression browse, detail summary, and Plotly views',
        'description' => 'Raw bulk expression matrix for normal cell line contexts used in the public Expression module.',
    ],
    [
        'dataset' => 'Normal Cell Line metadata',
        'filename' => 'Normal_cell_line_meta.csv',
        'path' => '/TE-/new_data/bulk_expression_web/normal_cell_line/Normal_cell_line_meta.csv',
        'format' => 'CSV',
        'used_in' => 'Expression dataset preprocessing',
        'description' => 'Metadata mapping normal cell line runs to their cell type labels.',
    ],
    [
        'dataset' => 'Normal Tissue expression matrix',
        'filename' => 'Normal_tissue_TE_normalized_count.tsv',
        'path' => '/TE-/new_data/bulk_expression_web/normal_tissue/Normal_tissue_TE_normalized_count.tsv',
        'format' => 'TSV',
        'used_in' => 'Expression browse, detail summary, and Plotly views',
        'description' => 'Raw bulk expression matrix for normal tissue contexts used in the public Expression module.',
    ],
    [
        'dataset' => 'Normal Tissue metadata',
        'filename' => 'Normal_tissue_meta.csv',
        'path' => '/TE-/new_data/bulk_expression_web/normal_tissue/Normal_tissue_meta.csv',
        'format' => 'CSV',
        'used_in' => 'Expression dataset preprocessing',
        'description' => 'Metadata mapping normal tissue runs to their organ labels.',
    ],
    [
        'dataset' => 'Graph seed',
        'filename' => 'te_kg2_graph_seed.json',
        'path' => '/TE-/data/processed/te_kg2_graph_seed.json',
        'format' => 'JSON',
        'used_in' => 'Database import and graph preview',
        'description' => 'Canonical TE, disease, function, and paper nodes together with the core graph relations used by the current public graph.',
    ],
    [
        'dataset' => 'Normalized graph extraction',
        'filename' => 'te_kg2_normalized_output.jsonl',
        'path' => '/TE-/data/processed/te_kg2_normalized_output.jsonl',
        'format' => 'JSONL',
        'used_in' => 'Database build pipeline',
        'description' => 'Normalized relation extraction result used as the upstream structured source for the current graph seed.',
    ],
    [
        'dataset' => 'TE lineage tree',
        'filename' => 'tree_te_lineage.json',
        'path' => '/TE-/data/processed/tree_te_lineage.json',
        'format' => 'JSON',
        'used_in' => 'Tree preview and lineage expansion',
        'description' => 'Structured TE lineage tree used in the public classification tree and lineage-aware graph expansion.',
    ],
    [
        'dataset' => 'TE lineage table',
        'filename' => 'tree_te_lineage.csv',
        'path' => '/TE-/data/processed/tree_te_lineage.csv',
        'format' => 'CSV',
        'used_in' => 'Manual inspection of lineage data',
        'description' => 'Tabular export of the TE lineage hierarchy corresponding to the public lineage JSON asset.',
    ],
];
?>
      <link rel="stylesheet" href="/TE-/assets/css/pages/download.css">

      <section class="download-shell">
        <div class="proto-container">
          <h1 class="download-page-title">Download</h1>
          <div class="download-crumbs">
            <a href="<?= htmlspecialchars(site_url_with_state('/TE-/index.php'), ENT_QUOTES, 'UTF-8') ?>">Home</a>
            <span>/</span>
            <span>Download</span>
          </div>

          <section class="download-panel">
            <h2>Public graph datasets</h2>
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
<script src="/TE-/assets/js/pages/download.js"></script>
    </main>
  </div>
</body>
</html>

