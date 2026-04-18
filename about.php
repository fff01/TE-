<?php
$pageTitle = 'TE-KG About';
$activePage = 'about';
$protoCurrentPath = '/TE-/about.php';
$protoSubtitle = 'Overview of the TE-KG interface and public workflows';
require __DIR__ . '/head.php';

$treeEmbedUrl = site_url_with_state('/TE-/index_g6.html', $siteLang, null, ['embed' => 'home-preview']);
?>
      <link rel="stylesheet" href="/TE-/assets/css/pages/about.css">

      <section class="about-shell">
        <div class="proto-container">
          <h1 class="page-title-hero">About</h1>
          <div class="page-crumbs">
            <a href="<?= htmlspecialchars(site_url_with_state('/TE-/index.php'), ENT_QUOTES, 'UTF-8') ?>">Home</a>
            <span>/</span>
            <span>About</span>
          </div>

          <section class="about-panel">
            <div class="about-layout">
              <aside class="about-side">
                <nav class="about-nav">
                  <a href="#introduction" data-pane="introduction" class="is-active">Introduction</a>
                  <a href="#preview" data-pane="preview">Preview</a>
                  <a href="#search" data-pane="search">Search</a>
                  <a href="#browse" data-pane="browse">Browse</a>
                  <a href="#download" data-pane="download">Download</a>
                </nav>
              </aside>

              <div class="about-content">
                <section class="about-pane is-active" id="pane-introduction">
                  <div class="about-block">
                    <h4>Introduction</h4>
                    <p>TE-KG is a transposable-element-centered knowledge graph designed to organize structured links among TE entities, diseases, molecular functions, and literature evidence. The current interface focuses on a more formal database-style presentation while keeping the existing graph, search, and download workflows intact.</p>
                    <div class="about-figure">
                      <div class="about-figure-frame">
                        <div class="overview-mock">
                          <div class="overview-copy-box">
                            <strong>Overview</strong>
                            <span>A cleaner entry point for TE-centric knowledge discovery. The homepage combines a concise overview, a TE lineage tree, dataset status, and direct links into preview, search, download, and about pages.</span>
                          </div>
                          <div class="overview-image-box">
                            <div class="overview-tree-box">
                              <iframe src="<?= htmlspecialchars($treeEmbedUrl, ENT_QUOTES, 'UTF-8') ?>" title="TE classification tree" loading="lazy" style="width:100%;height:100%;border:0;display:block;border-radius:14px;background:#fff;"></iframe>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </section>

                <section class="about-pane" id="pane-search">
                  <div class="about-block">
                    <h4>Search</h4>
                    <p>The search page keeps the current best-match workflow while adopting a cleaner database-like query panel. Users can select an entity type, enter an identifier or keyword, and immediately drive the best-match panel, the Repbase block, and the local graph preview below.</p>
                    <h5>Query by keyword</h5>
                    <p>This section is intended for direct TE, disease, function, or PMID lookup. The current search page keeps the existing search result behavior while presenting it in a more formal structure closer to academic database sites.</p>
                    <div class="search-mock">
                      <div class="search-top-row">
                        <div class="mock-input">Entity type</div>
                        <div class="mock-input">Identifier or keyword</div>
                      </div>
                      <div class="mock-action-row">
                        <div class="mock-btn is-primary">Search</div>
                        <div class="mock-btn">Reset</div>
                        <div class="mock-btn">Example</div>
                      </div>
                    </div>
                  </div>
                </section>

                <section class="about-pane" id="pane-preview">
                  <div class="about-block">
                    <h4>Preview</h4>
                    <p>The preview workflow emphasizes graph browsing. The graph area is close to full screen, the default tree remains available, and the QA assistant can be launched as a floating overlay instead of squeezing the graph area.</p>
                    <div class="preview-mock">
                      <div class="preview-canvas"></div>
                      <div class="preview-qa-dock">
                        <div class="mock-input" style="height:100%;">QA overlay region</div>
                        <div class="preview-robot"></div>
                      </div>
                    </div>
                  </div>
                </section>

                <section class="about-pane" id="pane-browse">
                  <div class="about-block">
                    <h4>Browse</h4>
                    <p>The browse page is intended as a structured entry point for exploring TE classes and TE records in a table-first view inspired by Dfam. Instead of starting from a graph query, users can scan a catalog-style layout, narrow the visible rows with lightweight filters, and move into Search or Preview only when they need more detail.</p>
                    <p>The first browse iteration focuses on page structure rather than full data interaction: a left filter panel, a main results table, and a header area reserved for summary and search controls. This gives the project a clear database-style browsing surface without changing the existing graph workflows.</p>
                    <div class="download-mock">
                      <table>
                        <thead>
                          <tr>
                            <th>TE name</th>
                            <th>Class</th>
                            <th>Family</th>
                            <th>Species</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td><em>L1HS</em></td>
                            <td>Retrotransposon</td>
                            <td>LINE</td>
                            <td>Homo sapiens</td>
                          </tr>
                          <tr>
                            <td><em>AluYa5</em></td>
                            <td>Retrotransposon</td>
                            <td>SINE</td>
                            <td>Homo sapiens</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </section>

                <section class="about-pane" id="pane-download">
                  <div class="about-block">
                    <h4>Download</h4>
                    <p>The download page is intentionally simple. It exposes only the datasets already used by the current public database and preview workflow, so the table stays aligned with what users can actually see in the graph.</p>
                    <div class="download-mock">
                      <table>
                        <thead>
                          <tr>
                            <th>Dataset</th>
                            <th>File</th>
                            <th>Used in</th>
                            <th>Format</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td><em>Graph seed</em></td>
                            <td>te_kg2_graph_seed.json</td>
                            <td>Database import and graph preview</td>
                            <td>JSON</td>
                          </tr>
                          <tr>
                            <td><em>Normalized graph extraction</em></td>
                            <td>te_kg2_normalized_output.jsonl</td>
                            <td>Database build pipeline</td>
                            <td>JSONL</td>
                          </tr>
                          <tr>
                            <td><em>TE lineage tree</em></td>
                            <td>tree_te_lineage.json</td>
                            <td>Tree preview and lineage expansion</td>
                            <td>JSON</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <div class="muted-note">Legacy files, archived assets, and internal intermediate outputs are intentionally excluded from the public-facing download list.</div>
                  </div>
                </section>
              </div>
            </div>
          </section>
        </div>
      </section>

      <script src="/TE-/assets/js/pages/about.js"></script>
    </main>
  </div>
</body>
</html>

