<?php
$pageTitle = 'TE-KG Browse';
$activePage = 'browse';
$protoCurrentPath = '/TE-/browse.php';
$protoSubtitle = 'Browse TE classes and records in a structured catalog view';
require __DIR__ . '/head.php';
?>
      <style>
        .browse-shell {
          background: #f5f9ff;
          min-height: calc(100vh - 82px);
          padding: 34px 0 54px;
        }

        .proto-container {
          max-width: 1320px;
          margin: 0 auto;
          padding: 0 28px;
        }

        .browse-page-title {
          margin: 0 0 22px;
          font-size: 52px;
          font-weight: 700;
          color: #8a93a3;
          line-height: 1.1;
        }

        .browse-crumbs {
          display: flex;
          align-items: center;
          gap: 10px;
          margin-bottom: 34px;
          font-size: 16px;
          color: #70809a;
        }

        .browse-crumbs a {
          color: #2f63b9;
          font-weight: 500;
        }

        .browse-layout {
          display: grid;
          grid-template-columns: 280px minmax(0, 1fr);
          gap: 22px;
          align-items: start;
        }

        .browse-panel,
        .browse-results {
          background: #ffffff;
          border: 1px solid #dbe7f8;
          border-radius: 12px;
          box-shadow: 0 10px 28px rgba(26, 60, 112, 0.08);
        }

        .browse-panel {
          padding: 20px 18px;
          position: sticky;
          top: 104px;
        }

        .browse-panel h3,
        .browse-results h3 {
          margin: 0 0 16px;
          font-size: 24px;
          font-weight: 700;
          color: #16345f;
        }

        .browse-filter-group {
          display: grid;
          gap: 10px;
          margin-bottom: 18px;
        }

        .browse-filter-label {
          font-size: 13px;
          font-weight: 700;
          color: #4d648a;
          letter-spacing: 0.02em;
          text-transform: uppercase;
        }

        .browse-filter-box {
          min-height: 44px;
          border: 1px solid #dce5f3;
          border-radius: 10px;
          background: #fbfdff;
          display: flex;
          align-items: center;
          padding: 0 14px;
          color: #7a879a;
          font-size: 14px;
        }

        .browse-results {
          padding: 22px 22px 18px;
        }

        .browse-results-top {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 14px;
          margin-bottom: 16px;
          flex-wrap: wrap;
        }

        .browse-search-box {
          flex: 1 1 320px;
          min-height: 48px;
          border: 1px solid #dce5f3;
          border-radius: 999px;
          background: #fbfdff;
          display: flex;
          align-items: center;
          padding: 0 18px;
          color: #7a879a;
        }

        .browse-summary {
          font-size: 14px;
          color: #607798;
        }

        .browse-table-wrap {
          overflow: auto;
          border: 1px solid #e1e9f6;
          border-radius: 12px;
        }

        .browse-table {
          width: 100%;
          border-collapse: collapse;
          min-width: 760px;
          background: #ffffff;
        }

        .browse-table th,
        .browse-table td {
          padding: 14px 16px;
          text-align: left;
          border-bottom: 1px solid #e9eef8;
          font-size: 14px;
        }

        .browse-table th {
          background: #f7faff;
          color: #284977;
          font-size: 13px;
          letter-spacing: 0.02em;
          text-transform: uppercase;
        }

        .browse-table tr:last-child td {
          border-bottom: none;
        }

        .browse-note {
          margin-top: 14px;
          color: #6f83a3;
          font-size: 14px;
          line-height: 1.8;
        }

        @media (max-width: 980px) {
          .browse-layout {
            grid-template-columns: 1fr;
          }

          .browse-panel {
            position: static;
          }
        }
      </style>

      <main class="proto-main">
        <section class="browse-shell">
          <div class="proto-container">
            <h1 class="browse-page-title">Browse</h1>
            <div class="browse-crumbs">
              <a href="<?= htmlspecialchars(site_url_with_state('/TE-/index.php', $siteLang, $siteRenderer), ENT_QUOTES, 'UTF-8') ?>">Home</a>
              <span>/</span>
              <span>Browse</span>
            </div>

            <div class="browse-layout">
              <aside class="browse-panel">
                <h3>Filters</h3>
                <div class="browse-filter-group">
                  <div class="browse-filter-label">Keyword</div>
                  <div class="browse-filter-box">Search TE names or labels</div>
                </div>
                <div class="browse-filter-group">
                  <div class="browse-filter-label">Class</div>
                  <div class="browse-filter-box">All classes</div>
                </div>
                <div class="browse-filter-group">
                  <div class="browse-filter-label">Family</div>
                  <div class="browse-filter-box">All families</div>
                </div>
                <div class="browse-filter-group">
                  <div class="browse-filter-label">Species</div>
                  <div class="browse-filter-box">Homo sapiens</div>
                </div>
              </aside>

              <section class="browse-results">
                <div class="browse-results-top">
                  <div class="browse-search-box">Filter the current TE catalog</div>
                  <div class="browse-summary">Static browse skeleton based on the Dfam-style layout.</div>
                </div>

                <div class="browse-table-wrap">
                  <table class="browse-table">
                    <thead>
                      <tr>
                        <th>TE name</th>
                        <th>Class</th>
                        <th>Family</th>
                        <th>Subtype</th>
                        <th>Species</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>L1HS</td>
                        <td>Retrotransposon</td>
                        <td>LINE</td>
                        <td>L1</td>
                        <td>Homo sapiens</td>
                      </tr>
                      <tr>
                        <td>AluYa5</td>
                        <td>Retrotransposon</td>
                        <td>SINE</td>
                        <td>Alu</td>
                        <td>Homo sapiens</td>
                      </tr>
                      <tr>
                        <td>SVA_F</td>
                        <td>Retrotransposon</td>
                        <td>SVA</td>
                        <td>Composite retroposon</td>
                        <td>Homo sapiens</td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <p class="browse-note">This first version only establishes the Browse page structure. The next step is to bind the panel and table to the actual TE data source, then add live search and filter interactions.</p>
              </section>
            </div>
          </div>
        </section>
      </main>
    </div>
  </body>
</html>
