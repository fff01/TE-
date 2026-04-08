<?php
$pageTitle = 'TE-KG Browse';
$activePage = 'browse';
$protoCurrentPath = '/TE-/browse.php';
$protoSubtitle = 'Browse TE classes and records in a structured catalog view';
require __DIR__ . '/head.php';
$browseItemUrl = site_url_with_state('/TE-/browse_item.php', $siteLang, $siteRenderer);
?>
      <style>
        .browse-shell {
          background: #f7f9fc;
          min-height: calc(100vh - 82px);
          padding: 28px 0 48px;
        }

        .proto-container {
          max-width: 1320px;
          margin: 0 auto;
          padding: 0 28px;
        }

        .browse-page-title {
          margin: 0 0 14px;
          font-size: 46px;
          font-weight: 700;
          color: #8a93a3;
          line-height: 1.08;
        }

        .browse-intro {
          max-width: 1180px;
          margin: 0 0 22px;
          color: #5f6f86;
          font-size: 15px;
          line-height: 1.85;
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
          grid-template-columns: 260px minmax(0, 1fr);
          gap: 18px;
          align-items: start;
        }

        .browse-panel,
        .browse-results {
          background: #ffffff;
          border: 1px solid #dfe6ef;
          border-radius: 10px;
          box-shadow: 0 4px 14px rgba(34, 56, 92, 0.05);
        }

        .browse-panel {
          padding: 18px 16px;
          position: sticky;
          top: 104px;
        }

        .browse-panel h3,
        .browse-results h3 {
          margin: 0 0 14px;
          font-size: 22px;
          font-weight: 700;
          color: #1b3558;
        }

        .browse-filter-group {
          display: grid;
          gap: 10px;
          margin-bottom: 18px;
        }

        .browse-filter-label {
          font-size: 12px;
          font-weight: 700;
          color: #617089;
          letter-spacing: 0.04em;
          text-transform: uppercase;
        }

        .browse-filter-input,
        .browse-filter-select {
          width: 100%;
          min-height: 42px;
          border: 1px solid #d8e0ea;
          border-radius: 8px;
          background: #ffffff;
          display: flex;
          align-items: center;
          padding: 0 14px;
          color: #49627f;
          font-size: 14px;
          outline: none;
          transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .browse-filter-input:focus,
        .browse-filter-select:focus,
        .browse-search-input:focus {
          border-color: #79a6ea;
          box-shadow: 0 0 0 4px rgba(92, 143, 219, 0.14);
        }

        .browse-filter-actions {
          display: flex;
          gap: 10px;
          margin-top: 8px;
          flex-wrap: wrap;
        }

        .browse-filter-btn {
          min-height: 38px;
          padding: 0 14px;
          border: 1px solid #d6e2f5;
          border-radius: 999px;
          background: #ffffff;
          color: #31588f;
          font-size: 13px;
          font-weight: 700;
          cursor: pointer;
        }

        .browse-filter-btn.is-primary {
          background: #2f63b9;
          border-color: #2f63b9;
          color: #ffffff;
        }

        .browse-results {
          padding: 18px 18px 16px;
        }

        .browse-results-top {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 12px;
          margin-bottom: 14px;
          flex-wrap: wrap;
        }

        .browse-search-box {
          flex: 1 1 320px;
          min-height: 44px;
          border: 1px solid #d8e0ea;
          border-radius: 8px;
          background: #ffffff;
          display: flex;
          align-items: center;
          padding: 0 18px;
          color: #7a879a;
          gap: 10px;
        }

        .browse-search-box svg {
          width: 18px;
          height: 18px;
          flex: 0 0 auto;
          color: #6f87aa;
        }

        .browse-search-input {
          width: 100%;
          min-width: 0;
          border: none;
          background: transparent;
          color: #334a6b;
          font-size: 14px;
          outline: none;
        }

        .browse-summary {
          font-size: 13px;
          color: #6f8096;
        }

        .browse-table-wrap {
          overflow: auto;
          border: 1px solid #dfe6ef;
          border-radius: 10px;
        }

        .browse-table {
          width: 100%;
          border-collapse: collapse;
          min-width: 760px;
          background: #ffffff;
        }

        .browse-table th,
        .browse-table td {
          padding: 12px 14px;
          text-align: left;
          border-bottom: 1px solid #ebeff5;
          font-size: 14px;
        }

        .browse-table th {
          background: #f3f6fa;
          color: #53657e;
          font-size: 12px;
          letter-spacing: 0.05em;
          text-transform: uppercase;
          font-weight: 800;
        }

        .browse-table tr:last-child td {
          border-bottom: none;
        }

        .browse-table tbody tr:hover {
          background: #f8fbff;
        }

        .browse-table tbody tr {
          transition: background-color 0.18s ease;
        }

        .browse-row-link {
          color: #214b8d;
          font-weight: 700;
          text-decoration: none;
        }

        .browse-row-link:hover {
          text-decoration: underline;
          text-underline-offset: 3px;
        }

        .browse-tag {
          display: inline-flex;
          align-items: center;
          min-height: 24px;
          padding: 0 9px;
          border-radius: 999px;
          border: 1px solid #dbe3ec;
          background: #f8fbff;
          color: #556b89;
          font-size: 11px;
          font-weight: 700;
          letter-spacing: 0.02em;
        }

        .browse-empty {
          display: none;
          padding: 34px 18px;
          text-align: center;
          color: #6f83a3;
          font-size: 14px;
          line-height: 1.8;
        }

        .browse-empty.is-visible {
          display: block;
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
            <p class="browse-intro">This browse view is designed as a lightweight catalog-style entry point inspired by Dfam. It prioritizes scanning, filtering, and shortlisting TE records in a clean table layout before users move into deeper search or graph exploration.</p>
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
                  <input class="browse-filter-input" id="browseKeyword" type="text" placeholder="Search TE names or labels">
                </div>
                <div class="browse-filter-group">
                  <div class="browse-filter-label">Class</div>
                  <select class="browse-filter-select" id="browseClass">
                    <option value="">All classes</option>
                  </select>
                </div>
                <div class="browse-filter-group">
                  <div class="browse-filter-label">Family</div>
                  <select class="browse-filter-select" id="browseFamily">
                    <option value="">All families</option>
                  </select>
                </div>
                <div class="browse-filter-group">
                  <div class="browse-filter-label">Species</div>
                  <select class="browse-filter-select" id="browseSpecies">
                    <option value="">All species</option>
                  </select>
                </div>
                <div class="browse-filter-actions">
                  <button class="browse-filter-btn is-primary" id="browseApplyBtn" type="button">Apply</button>
                  <button class="browse-filter-btn" id="browseResetBtn" type="button">Reset</button>
                </div>
              </aside>

              <section class="browse-results">
                <div class="browse-results-top">
                  <label class="browse-search-box" for="browseSearch">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"></circle><path d="m20 20-3.6-3.6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path></svg>
                    <input class="browse-search-input" id="browseSearch" type="text" placeholder="Filter the current TE catalog">
                  </label>
                  <div class="browse-summary" id="browseSummary">Showing 7 TE records in the current mock catalog.</div>
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
                    <tbody id="browseTableBody"></tbody>
                  </table>
                </div>
                <div class="browse-empty" id="browseEmpty">No TE records match the current search and filter combination. Try clearing one or more conditions.</div>

                <p class="browse-note">This version already supports frontend search and filter interactions with mock data. The next step is to replace the mock catalog with a database-backed data source without changing the page structure.</p>
              </section>
            </div>
          </div>
        </section>
      </main>
    </div>
    <script>
      (() => {
        const browseItemBase = <?= json_encode($browseItemUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        const mockRows = [
          { name: 'L1HS', className: 'Retrotransposon', family: 'LINE', subtype: 'L1', species: 'Homo sapiens' },
          { name: 'LINE1', className: 'Retrotransposon', family: 'LINE', subtype: 'L1', species: 'Homo sapiens' },
          { name: 'AluYa5', className: 'Retrotransposon', family: 'SINE', subtype: 'Alu', species: 'Homo sapiens' },
          { name: 'AluYb8', className: 'Retrotransposon', family: 'SINE', subtype: 'Alu', species: 'Homo sapiens' },
          { name: 'SVA_F', className: 'Retrotransposon', family: 'SVA', subtype: 'Composite retroposon', species: 'Homo sapiens' },
          { name: 'MER75', className: 'DNA Transposon', family: 'Mariner/Tc1', subtype: 'MER', species: 'Homo sapiens' },
          { name: 'Merlin1_HS', className: 'DNA Transposon', family: 'Merlin', subtype: 'Merlin', species: 'Homo sapiens' },
        ];

        const keywordInput = document.getElementById('browseKeyword');
        const classSelect = document.getElementById('browseClass');
        const familySelect = document.getElementById('browseFamily');
        const speciesSelect = document.getElementById('browseSpecies');
        const searchInput = document.getElementById('browseSearch');
        const applyBtn = document.getElementById('browseApplyBtn');
        const resetBtn = document.getElementById('browseResetBtn');
        const tableBody = document.getElementById('browseTableBody');
        const emptyState = document.getElementById('browseEmpty');
        const summary = document.getElementById('browseSummary');

        function fillSelect(select, values) {
          values.forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            select.appendChild(option);
          });
        }

        function uniqueValues(key) {
          return [...new Set(mockRows.map((row) => row[key]).filter(Boolean))].sort((a, b) => a.localeCompare(b));
        }

        function renderRows(rows) {
          tableBody.innerHTML = '';
          rows.forEach((row) => {
            const tr = document.createElement('tr');
            const targetUrl = `${browseItemBase}${browseItemBase.includes('?') ? '&' : '?'}te=${encodeURIComponent(row.name)}`;
            tr.innerHTML = `
              <td><a class="browse-row-link" href="${targetUrl}">${row.name}</a></td>
              <td><span class="browse-tag">${row.className}</span></td>
              <td>${row.family}</td>
              <td>${row.subtype}</td>
              <td>${row.species}</td>
            `;
            tableBody.appendChild(tr);
          });
          emptyState.classList.toggle('is-visible', rows.length === 0);
          summary.textContent = `Showing ${rows.length} TE record${rows.length === 1 ? '' : 's'} in the current browse view.`;
        }

        function applyFilters() {
          const keyword = (keywordInput.value || '').trim().toLowerCase();
          const search = (searchInput.value || '').trim().toLowerCase();
          const classValue = classSelect.value;
          const familyValue = familySelect.value;
          const speciesValue = speciesSelect.value;

          const filtered = mockRows.filter((row) => {
            const haystack = [row.name, row.className, row.family, row.subtype, row.species].join(' ').toLowerCase();
            if (keyword && !haystack.includes(keyword)) return false;
            if (search && !haystack.includes(search)) return false;
            if (classValue && row.className !== classValue) return false;
            if (familyValue && row.family !== familyValue) return false;
            if (speciesValue && row.species !== speciesValue) return false;
            return true;
          });

          renderRows(filtered);
        }

        function resetFilters() {
          keywordInput.value = '';
          searchInput.value = '';
          classSelect.value = '';
          familySelect.value = '';
          speciesSelect.value = '';
          renderRows(mockRows);
        }

        fillSelect(classSelect, uniqueValues('className'));
        fillSelect(familySelect, uniqueValues('family'));
        fillSelect(speciesSelect, uniqueValues('species'));
        renderRows(mockRows);

        applyBtn.addEventListener('click', applyFilters);
        resetBtn.addEventListener('click', resetFilters);
        [keywordInput, searchInput].forEach((input) => {
          input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') applyFilters();
          });
        });
        [classSelect, familySelect, speciesSelect].forEach((select) => {
          select.addEventListener('change', applyFilters);
        });
      })();
    </script>
  </body>
</html>
