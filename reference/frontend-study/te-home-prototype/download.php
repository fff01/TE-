<?php
$pageTitle = 'TE-KG Download Prototype';
$activePage = 'download';
$protoCurrentPath = '/TE-/reference/frontend-study/te-home-prototype/download.php';
$protoSubtitle = 'Prototype download page aligned to the ncPlantDB download structure';
require __DIR__ . '/head.php';

$downloadItems = [
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
      <style>
        .download-shell {
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

        .download-panel {
          background: #ffffff;
          border: 1px solid #dbe7f8;
          border-radius: 10px;
          box-shadow: 0 10px 28px rgba(26, 60, 112, 0.08);
          padding: 30px 26px 18px;
        }

        .download-panel h2 {
          margin: 0;
          font-size: 28px;
          color: #7b8597;
          font-weight: 700;
        }

        .download-divider {
          height: 1px;
          background: #e2e8f2;
          margin: 20px 0 22px;
        }

        .download-tools {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 16px;
          margin-bottom: 14px;
          flex-wrap: wrap;
        }

        .download-tools-left,
        .download-tools-right {
          display: flex;
          align-items: center;
          gap: 10px;
          color: #7b8597;
          font-size: 15px;
        }

        .download-select,
        .download-search {
          min-height: 46px;
          border: 1px solid #dce5f3;
          border-radius: 8px;
          background: #ffffff;
          padding: 0 14px;
          font-size: 15px;
          color: #193458;
          outline: none;
        }

        .download-search {
          min-width: 280px;
        }

        .download-table-wrap {
          overflow-x: auto;
        }

        .download-table {
          width: 100%;
          border-collapse: collapse;
          min-width: 980px;
        }

        .download-table thead th {
          padding: 16px 12px;
          text-align: left;
          font-size: 16px;
          font-weight: 700;
          color: #2d5f1f;
          border-bottom: 2px solid #1f2937;
        }

        .download-table tbody td {
          padding: 18px 12px;
          border-bottom: 1px solid #dde6f3;
          color: #193458;
          font-size: 15px;
          vertical-align: top;
          line-height: 1.6;
        }

        .download-table td.dataset-cell {
          width: 31%;
        }

        .download-table td.dataset-cell em {
          font-style: italic;
          color: #17345c;
        }

        .dataset-toggle {
          border: 0;
          background: transparent;
          padding: 0;
          margin: 0;
          font: inherit;
          color: inherit;
          cursor: pointer;
          text-align: left;
          width: 100%;
        }

        .dataset-title-line {
          display: flex;
          align-items: center;
          gap: 10px;
        }

        .dataset-caret {
          width: 18px;
          height: 18px;
          flex: 0 0 18px;
          transition: transform 0.22s ease;
          color: #5877aa;
        }

        .dataset-row.is-open .dataset-caret {
          transform: rotate(90deg);
        }

        .dataset-description {
          max-height: 0;
          overflow: hidden;
          transition: max-height 0.26s ease;
          color: #76859b;
          font-size: 13px;
          padding-left: 28px;
        }

        .dataset-row.is-open .dataset-description {
          max-height: 140px;
          margin-top: 8px;
        }

        .dataset-description-inner {
          padding-top: 4px;
          line-height: 1.65;
        }

        .download-table a.file-link {
          color: #5f8f3d;
          font-weight: 500;
          word-break: break-word;
        }

        .download-footer {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 16px;
          padding-top: 18px;
          flex-wrap: wrap;
          color: #7b8597;
          font-size: 15px;
        }

        .download-pagination {
          display: inline-flex;
          border: 1px solid #dce5f3;
          border-radius: 8px;
          overflow: hidden;
          background: #ffffff;
        }

        .download-page-btn {
          min-width: 42px;
          height: 42px;
          border: 0;
          border-right: 1px solid #dce5f3;
          background: #ffffff;
          color: #31588f;
          cursor: pointer;
          font-size: 16px;
        }

        .download-page-btn:last-child {
          border-right: 0;
        }

        .download-page-btn.is-active {
          background: #2f63b9;
          color: #ffffff;
        }

        .download-empty {
          padding: 28px 10px 10px;
          color: #7b8597;
          font-size: 15px;
        }

        @media (max-width: 720px) {
          .proto-container {
            padding: 0 18px;
          }

          .download-panel {
            padding-left: 18px;
            padding-right: 18px;
          }

          .download-page-title {
            font-size: 40px;
          }

          .download-search {
            min-width: 220px;
          }
        }
      </style>

      <section class="download-shell">
        <div class="proto-container">
          <h1 class="download-page-title">Download</h1>
          <div class="download-crumbs">
            <a href="<?= htmlspecialchars(site_url_with_state('/TE-/reference/frontend-study/te-home-prototype/home.php'), ENT_QUOTES, 'UTF-8') ?>">Home</a>
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
        (() => {
          const rows = <?= json_encode($downloadItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
          const body = document.getElementById('download-table-body');
          const summary = document.getElementById('download-summary');
          const pagination = document.getElementById('download-pagination');
          const searchInput = document.getElementById('download-search');
          const sizeSelect = document.getElementById('download-page-size');
          const emptyState = document.getElementById('download-empty');

          let currentPage = 1;

          function filteredRows() {
            const query = (searchInput.value || '').trim().toLowerCase();
            if (!query) return rows;
            return rows.filter((row) => {
              return [
                row.dataset,
                row.filename,
                row.used_in,
                row.format,
                row.description
              ].some((value) => String(value || '').toLowerCase().includes(query));
            });
          }

          function renderPagination(totalPages) {
            pagination.innerHTML = '';
            if (totalPages <= 1) return;

            const makeButton = (label, page, active = false) => {
              const button = document.createElement('button');
              button.type = 'button';
              button.className = 'download-page-btn' + (active ? ' is-active' : '');
              button.textContent = label;
              button.addEventListener('click', () => {
                currentPage = page;
                render();
              });
              return button;
            };

            pagination.appendChild(makeButton('‹', Math.max(1, currentPage - 1), false));
            for (let page = 1; page <= totalPages; page += 1) {
              pagination.appendChild(makeButton(String(page), page, page === currentPage));
            }
            pagination.appendChild(makeButton('›', Math.min(totalPages, currentPage + 1), false));
          }

          function render() {
            const pageSize = Number(sizeSelect.value || 10);
            const items = filteredRows();
            const total = items.length;
            const totalPages = Math.max(1, Math.ceil(total / pageSize));
            currentPage = Math.min(currentPage, totalPages);
            const start = total === 0 ? 0 : (currentPage - 1) * pageSize;
            const pageItems = items.slice(start, start + pageSize);

            body.innerHTML = '';
            emptyState.hidden = pageItems.length !== 0;

            pageItems.forEach((row) => {
              const tr = document.createElement('tr');
              tr.className = 'dataset-row';
              tr.innerHTML = `
                <td class="dataset-cell">
                  <button class="dataset-toggle" type="button" aria-expanded="false">
                    <span class="dataset-title-line">
                      <svg class="dataset-caret" viewBox="0 0 16 16" aria-hidden="true"><path fill="currentColor" d="M5.2 2.8 10.4 8l-5.2 5.2-.9-.9L8.6 8 4.3 3.7z"/></svg>
                      <em>${row.dataset}</em>
                    </span>
                  </button>
                  <div class="dataset-description">
                    <div class="dataset-description-inner">${row.description}</div>
                  </div>
                </td>
                <td><a class="file-link" href="${row.path}" download>${row.filename}</a></td>
                <td>${row.used_in}</td>
                <td>${row.format}</td>
              `;
              const toggle = tr.querySelector('.dataset-toggle');
              toggle.addEventListener('click', () => {
                const isOpen = tr.classList.contains('is-open');
                body.querySelectorAll('.dataset-row').forEach((rowEl) => {
                  rowEl.classList.remove('is-open');
                  const btn = rowEl.querySelector('.dataset-toggle');
                  if (btn) btn.setAttribute('aria-expanded', 'false');
                });
                if (!isOpen) {
                  tr.classList.add('is-open');
                  toggle.setAttribute('aria-expanded', 'true');
                }
              });
              body.appendChild(tr);
            });

            const shownFrom = total === 0 ? 0 : start + 1;
            const shownTo = total === 0 ? 0 : start + pageItems.length;
            summary.textContent = `Showing ${shownFrom} to ${shownTo} of ${total} entries`;
            renderPagination(totalPages);
          }

          searchInput.addEventListener('input', () => {
            currentPage = 1;
            render();
          });

          sizeSelect.addEventListener('change', () => {
            currentPage = 1;
            render();
          });

          render();
        })();
      </script>
    </main>
  </div>
</body>
</html>
