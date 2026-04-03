<?php
$pageTitle = 'TE-KG About';
$activePage = 'about';
$protoCurrentPath = '/TE-/about.php';
$protoSubtitle = 'Overview of the TE-KG interface and public workflows';
require __DIR__ . '/head.php';

$treeEmbedUrl = site_url_with_state('/TE-/index_g6.html', $siteLang, 'g6', ['embed' => 'home-preview']);
?>
      <style>
        .about-shell {
          background: #f5f9ff;
          min-height: calc(100vh - 82px);
          padding: 34px 0 54px;
        }

        .proto-container {
          max-width: 1320px;
          margin: 0 auto;
          padding: 0 28px;
        }

        .page-title-hero {
          margin: 0 0 22px;
          font-size: 52px;
          font-weight: 700;
          color: #8a93a3;
          line-height: 1.1;
        }

        .page-crumbs {
          display: flex;
          align-items: center;
          gap: 10px;
          margin-bottom: 38px;
          font-size: 16px;
          color: #70809a;
        }

        .page-crumbs a {
          color: #2f63b9;
          font-weight: 500;
        }

        .about-panel {
          background: #ffffff;
          border: 1px solid #dbe7f8;
          border-radius: 10px;
          box-shadow: 0 10px 28px rgba(26, 60, 112, 0.08);
          padding: 26px 22px;
        }

        .about-layout {
          display: grid;
          grid-template-columns: 190px minmax(0, 1fr);
          gap: 22px;
          align-items: start;
        }

        .about-side {
          position: sticky;
          top: 104px;
          padding-right: 20px;
          border-right: 1px solid #e2e8f2;
        }

        .about-nav {
          display: grid;
          gap: 10px;
        }

        .about-nav a {
          display: block;
          padding: 14px 18px;
          border-radius: 8px;
          color: #16345f;
          font-size: 16px;
          font-weight: 500;
        }

        .about-nav a.is-active,
        .about-nav a:hover {
          background: #2f63b9;
          color: #ffffff;
        }

        .about-content {
          padding-left: 10px;
          min-height: 780px;
        }

        .about-pane {
          display: none;
        }

        .about-pane.is-active {
          display: block;
        }

        .about-block {
          padding: 6px 0 26px;
        }

        .about-block h4 {
          margin: 0 0 16px;
          font-size: 24px;
          font-weight: 700;
          color: #111827;
        }

        .about-block h5 {
          margin: 18px 0 10px;
          font-size: 18px;
          font-weight: 700;
          color: #214b8d;
        }

        .about-block p {
          margin: 0 0 14px;
          color: #5b7091;
          font-size: 15px;
          line-height: 1.9;
        }

        .about-block ul {
          margin: 0;
          padding-left: 20px;
          color: #5b7091;
          line-height: 1.9;
        }

        .about-figure {
          margin-top: 16px;
          background: #ffffff;
          border: 1px solid #dbe7f8;
          border-radius: 10px;
          box-shadow: 0 8px 22px rgba(25, 56, 105, 0.06);
          overflow: hidden;
        }

        .about-figure-frame {
          padding: 22px;
          background: linear-gradient(180deg, #fbfdff 0%, #f2f7ff 100%);
        }

        .overview-mock {
          display: grid;
          grid-template-columns: 1.05fr 1.2fr;
          gap: 18px;
          align-items: stretch;
        }

        .overview-copy-box {
          padding: 18px 12px 18px 6px;
        }

        .overview-copy-box strong {
          display: block;
          margin-bottom: 12px;
          font-size: 17px;
          color: #214b8d;
        }

        .overview-copy-box span {
          color: #607798;
          line-height: 1.8;
          font-size: 14px;
        }

        .overview-image-box {
          background: #ffffff;
          border: 1px solid #dce7f8;
          border-radius: 8px;
          padding: 16px;
          box-shadow: 0 6px 18px rgba(34, 68, 120, 0.08);
        }

        .overview-tree-box {
          border-radius: 20px;
          background: #dcebff;
          border: 1px solid #cadafb;
          height: 320px;
          display: grid;
          place-items: center;
          color: #4f6e9e;
          font-size: 14px;
          text-align: center;
          padding: 20px;
        }

        .search-mock,
        .preview-mock,
        .download-mock {
          border: 1px solid #dbe7f8;
          border-radius: 10px;
          background: #fbfdff;
          padding: 18px;
          margin-top: 14px;
        }

        .search-top-row {
          display: grid;
          grid-template-columns: 0.7fr 1.3fr;
          gap: 16px;
          margin-bottom: 14px;
        }

        .mock-input {
          min-height: 52px;
          border: 1px solid #dce5f3;
          border-radius: 8px;
          background: #ffffff;
          display: flex;
          align-items: center;
          padding: 0 16px;
          color: #7a879a;
          font-size: 15px;
        }

        .mock-action-row {
          display: flex;
          gap: 10px;
          margin-top: 6px;
        }

        .mock-btn {
          min-width: 104px;
          min-height: 44px;
          border-radius: 8px;
          border: 1px solid #d6e1f2;
          background: #ffffff;
          color: #6980a4;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          font-weight: 600;
          font-size: 14px;
        }

        .mock-btn.is-primary {
          background: #2f63b9;
          border-color: #2f63b9;
          color: #ffffff;
        }

        .preview-mock {
          display: grid;
          grid-template-columns: 1fr 280px;
          gap: 16px;
          align-items: stretch;
        }

        .preview-canvas {
          min-height: 240px;
          border: 1px solid #dce5f3;
          border-radius: 8px;
          background: linear-gradient(180deg, #ffffff 0%, #eef5ff 100%);
          position: relative;
          overflow: hidden;
        }

        .preview-canvas::before {
          content: "";
          position: absolute;
          inset: 24px 28px;
          border-radius: 16px;
          background:
            radial-gradient(circle at 20% 30%, rgba(47,99,185,0.18), transparent 18%),
            radial-gradient(circle at 62% 48%, rgba(239,68,68,0.12), transparent 14%),
            radial-gradient(circle at 76% 22%, rgba(16,185,129,0.14), transparent 13%),
            radial-gradient(circle at 45% 74%, rgba(245,158,11,0.14), transparent 12%);
        }

        .preview-qa-dock {
          min-height: 240px;
          border: 1px solid #dce5f3;
          border-radius: 8px;
          background: #ffffff;
          position: relative;
          padding: 18px;
        }

        .preview-robot {
          position: absolute;
          right: 18px;
          bottom: 18px;
          width: 56px;
          height: 56px;
          border-radius: 50%;
          background: #2f63b9;
          box-shadow: 0 10px 20px rgba(47,99,185,0.22);
        }

        .download-mock table {
          width: 100%;
          border-collapse: collapse;
        }

        .download-mock th,
        .download-mock td {
          padding: 12px 10px;
          text-align: left;
          border-bottom: 1px solid #dfe7f3;
          font-size: 14px;
        }

        .download-mock th {
          color: #2d5f1f;
          font-weight: 700;
        }

        .muted-note {
          color: #7b8ca3;
          font-size: 13px;
          margin-top: 10px;
        }

        @media (max-width: 1100px) {
          .about-layout,
          .overview-mock,
          .preview-mock {
            grid-template-columns: 1fr;
          }

          .about-side {
            position: static;
            border-right: 0;
            padding-right: 0;
            border-bottom: 1px solid #e2e8f2;
            padding-bottom: 18px;
            margin-bottom: 8px;
          }

          .about-nav {
            grid-template-columns: repeat(3, minmax(0, 1fr));
          }
        }

        @media (max-width: 720px) {
          .proto-container {
            padding: 0 18px;
          }

          .page-title-hero {
            font-size: 40px;
          }

          .about-panel {
            padding-left: 18px;
            padding-right: 18px;
          }

          .search-top-row,
          .about-nav {
            grid-template-columns: 1fr;
          }
        }
      </style>

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

      <script>
        (() => {
          const header = document.getElementById('protoHeader');
          function syncHeader() {
            if (!header) return;
            header.classList.toggle('is-scrolled', window.scrollY > 12);
          }
          const links = Array.from(document.querySelectorAll('.about-nav a'));
          const panes = {
            introduction: document.getElementById('pane-introduction'),
            search: document.getElementById('pane-search'),
            preview: document.getElementById('pane-preview'),
            download: document.getElementById('pane-download')
          };

          function setActivePane(name) {
            Object.entries(panes).forEach(([key, pane]) => {
              if (!pane) return;
              pane.classList.toggle('is-active', key === name);
            });
            links.forEach((link) => {
              link.classList.toggle('is-active', link.dataset.pane === name);
            });
          }

          links.forEach((link) => {
            link.addEventListener('click', (event) => {
              event.preventDefault();
              const paneName = link.dataset.pane;
              if (!paneName) return;
              setActivePane(paneName);
              const url = new URL(window.location.href);
              url.hash = paneName;
              window.history.replaceState({}, '', url.toString());
            });
          });

          window.addEventListener('scroll', syncHeader, { passive: true });
          syncHeader();
          setActivePane(window.location.hash ? window.location.hash.replace('#', '') : 'introduction');
        })();
      </script>
    </main>
  </div>
</body>
</html>
