<?php
$pageTitle = 'TE-KG Browse';
$activePage = 'browse';
$protoCurrentPath = '/TE-/browse.php';
$protoSubtitle = 'Browse TE classes and records in a structured catalog view';

function tekg_browse_normalize_label(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return str_replace(['_', '-'], ' ', $value);
}

function tekg_browse_extract_length(array $entry): ?int
{
    $summary = $entry['sequence_summary'] ?? null;
    if (is_array($summary)) {
        $headline = trim((string) ($summary['headline'] ?? ''));
        if ($headline !== '' && preg_match('/(\d+)\s*BP/i', $headline, $matches) === 1) {
            return (int) $matches[1];
        }
    }

    $sequence = preg_replace('/\s+/', '', (string) ($entry['sequence'] ?? '')) ?? '';
    return $sequence !== '' ? strlen($sequence) : null;
}

function tekg_browse_infer_lineage(array $entry): array
{
    $name = (string) ($entry['name'] ?? '');
    $description = mb_strtolower((string) ($entry['description'] ?? ''));
    $keywords = array_map(
        static fn ($keyword): string => mb_strtolower((string) $keyword),
        is_array($entry['keywords'] ?? null) ? $entry['keywords'] : []
    );
    $haystack = mb_strtolower($name . ' ' . $description . ' ' . implode(' ', $keywords));

    $className = 'Unclassified';
    $family = '';
    $subtype = '';

    if (
        str_contains($haystack, 'endogenous retrovirus')
        || str_contains($haystack, 'herv')
        || str_contains($haystack, ' erv')
        || str_contains($haystack, 'ltr')
    ) {
        $className = 'Retrotransposon';
        foreach ($entry['keywords'] ?? [] as $keyword) {
            if (preg_match('/^(ERV\d+|ERVL|ERVK|HERV[\w\-]+)$/i', (string) $keyword) === 1) {
                $family = (string) $keyword;
                break;
            }
        }
        $family = $family !== '' ? $family : 'ERV';
        $subtype = str_starts_with($name, 'LTR') ? 'LTR' : '';
    } elseif (
        str_contains($haystack, 'non-ltr retrotransposon')
        || str_contains($haystack, ' line ')
        || str_contains($haystack, 'l1 (line) family')
    ) {
        $className = 'Retrotransposon';
        $family = 'LINE';
        foreach (['CR1', 'L1', 'L2', 'RTE'] as $candidate) {
            if (str_contains($haystack, mb_strtolower($candidate))) {
                $subtype = $candidate;
                break;
            }
        }
    } elseif (str_contains($haystack, 'sine')) {
        $className = 'Retrotransposon';
        $family = 'SINE';
        if (str_contains($haystack, 'alu')) {
            $subtype = 'Alu';
        }
    } elseif (str_contains($haystack, 'dna transposon')) {
        $className = 'DNA Transposon';
        foreach (['hAT-Charlie', 'hAT', 'Mariner/Tc1', 'piggyBac', 'Merlin', 'Helitron'] as $candidate) {
            if (str_contains($haystack, mb_strtolower($candidate))) {
                $family = $candidate;
                break;
            }
        }
    }

    return [
        'className' => tekg_browse_normalize_label($className),
        'family' => tekg_browse_normalize_label($family),
        'subtype' => tekg_browse_normalize_label($subtype),
    ];
}

function tekg_browse_load_rows(): array
{
    $repbaseFile = __DIR__ . '/data/processed/te_repbase_db_matched.json';
    $lineageFile = __DIR__ . '/data/processed/tree_te_lineage.json';
    if (!is_file($repbaseFile)) {
        return [];
    }

    $repbase = json_decode((string) file_get_contents($repbaseFile), true);
    if (!is_array($repbase) || !is_array($repbase['entries'] ?? null)) {
        return [];
    }

    $lineage = is_file($lineageFile)
        ? json_decode((string) file_get_contents($lineageFile), true)
        : null;

    $parentMap = [];
    foreach (($lineage['edges'] ?? []) as $edge) {
        $child = (string) ($edge['child'] ?? '');
        $parent = (string) ($edge['parent'] ?? '');
        if ($child !== '' && $parent !== '') {
            $parentMap[$child] = $parent;
        }
    }

    $pathCache = [];
    $pathToRoot = static function (string $name) use (&$pathCache, $parentMap): array {
        if (isset($pathCache[$name])) {
            return $pathCache[$name];
        }
        $path = [];
        $cursor = $name;
        $seen = [];
        while ($cursor !== '' && !isset($seen[$cursor])) {
            $seen[$cursor] = true;
            $path[] = $cursor;
            $cursor = $parentMap[$cursor] ?? '';
        }
        $pathCache[$name] = array_reverse($path);
        return $pathCache[$name];
    };

    $rows = [];
    foreach ($repbase['entries'] as $entry) {
        $name = trim((string) ($entry['name'] ?? $entry['id'] ?? ''));
        if ($name === '') {
            continue;
        }

        $path = $pathToRoot($name);
        $ancestors = $path;
        if (!empty($ancestors) && $ancestors[0] === 'TE') {
            array_shift($ancestors);
        }
        if (!empty($ancestors) && end($ancestors) === $name) {
            array_pop($ancestors);
        }

        $inferred = tekg_browse_infer_lineage($entry);
        $className = $ancestors[0] ?? $inferred['className'];
        $family = $ancestors[1] ?? $inferred['family'];
        $subtype = count($ancestors) > 2 ? (string) end($ancestors) : $inferred['subtype'];

        $rows[] = [
            'name' => $name,
            'className' => tekg_browse_normalize_label($className !== '' ? $className : 'Unclassified'),
            'family' => tekg_browse_normalize_label($family),
            'subtype' => tekg_browse_normalize_label($subtype),
            'description' => trim((string) ($entry['description'] ?? '')),
            'lengthBp' => tekg_browse_extract_length($entry),
            'referenceCount' => is_array($entry['references'] ?? null) ? count($entry['references']) : 0,
            'keywords' => is_array($entry['keywords'] ?? null) ? array_values(array_filter(array_map('strval', $entry['keywords']))) : [],
        ];
    }

    usort(
        $rows,
        static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name'])
    );

    return $rows;
}

require __DIR__ . '/head.php';
$browseSearchUrl = site_url_with_state('/TE-/search.php', $siteLang, $siteRenderer);
$browseRows = tekg_browse_load_rows();
?>
      <style>
        .browse-shell {
          background: #f7f9fc;
          min-height: calc(100vh - 82px);
          padding: 28px 0 48px;
        }

        .proto-container {
          max-width: 1480px;
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
          max-width: 1220px;
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
          grid-template-columns: 1fr;
          gap: 18px;
        }

        .browse-panel,
        .browse-results {
          background: #ffffff;
          border: 1px solid #dfe6ef;
          border-radius: 10px;
          box-shadow: 0 4px 14px rgba(34, 56, 92, 0.05);
        }

        .browse-panel {
          padding: 24px 24px 20px;
        }

        .browse-panel h3 {
          margin: 0 0 18px;
          font-size: 22px;
          font-weight: 700;
          color: #1b3558;
        }

        .browse-filter-grid {
          display: grid;
          grid-template-columns: minmax(250px, 1.35fr) repeat(3, minmax(170px, 1fr)) auto;
          gap: 18px;
          align-items: end;
        }

        .browse-filter-group {
          display: grid;
          gap: 10px;
          margin: 0;
        }

        .browse-filter-label {
          font-size: 12px;
          font-weight: 700;
          color: #617089;
          letter-spacing: 0.04em;
          text-transform: uppercase;
        }

        .browse-filter-input,
        .browse-filter-select,
        .browse-page-size-select,
        .browse-page-jump-input {
          width: 100%;
          min-height: 46px;
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
        .browse-page-size-select:focus,
        .browse-page-jump-input:focus {
          border-color: #79a6ea;
          box-shadow: 0 0 0 4px rgba(92, 143, 219, 0.14);
        }

        .browse-filter-actions {
          display: flex;
          gap: 10px;
          align-items: center;
          justify-content: flex-end;
          min-height: 46px;
        }

        .browse-filter-btn {
          min-height: 40px;
          padding: 0 16px;
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

        .browse-table-wrap {
          overflow: auto;
          border: 1px solid #dfe6ef;
          border-radius: 10px;
        }

        .browse-table {
          width: 100%;
          border-collapse: collapse;
          min-width: 1120px;
          background: #ffffff;
          table-layout: fixed;
        }

        .browse-table th,
        .browse-table td {
          padding: 12px 14px;
          text-align: left;
          border-bottom: 1px solid #ebeff5;
          font-size: 14px;
          vertical-align: top;
          overflow: hidden;
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

        .browse-row-link {
          color: #214b8d;
          font-weight: 700;
          text-decoration: none;
        }

        .browse-row-link:hover {
          text-decoration: underline;
          text-underline-offset: 3px;
        }

        .browse-name-cell,
        .browse-meta-cell {
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }

        .browse-description-cell {
          max-width: 440px;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
          color: #4b617e;
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

        .browse-pagination {
          display: flex;
          align-items: center;
          justify-content: flex-end;
          gap: 24px;
          margin-top: 16px;
          flex-wrap: wrap;
        }

        .browse-page-size,
        .browse-page-jump {
          display: inline-flex;
          align-items: center;
          gap: 14px;
          color: #445a79;
          font-size: 15px;
        }

        .browse-page-size-label,
        .browse-page-jump-label {
          white-space: nowrap;
          font-weight: 500;
        }

        .browse-page-size-select {
          width: 118px;
          font-size: 15px;
          font-weight: 600;
          color: #2a436a;
          appearance: auto;
        }

        .browse-page-jump-input {
          width: 86px;
          font-size: 15px;
          color: #2a436a;
        }

        .browse-page-status {
          font-size: 15px;
          color: #243b61;
          min-width: 140px;
          text-align: center;
        }

        .browse-page-actions {
          display: flex;
          align-items: center;
          gap: 12px;
        }

        .browse-page-btn {
          width: 40px;
          height: 40px;
          border: none;
          background: transparent;
          color: #70819a;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          padding: 0;
        }

        .browse-page-btn svg {
          width: 24px;
          height: 24px;
          stroke: currentColor;
          stroke-width: 2.4;
          fill: none;
          stroke-linecap: round;
          stroke-linejoin: round;
        }

        .browse-page-btn:hover:not(:disabled) {
          color: #2f63b9;
        }

        .browse-page-btn:disabled {
          opacity: 0.35;
          cursor: not-allowed;
        }

        .browse-note {
          margin-top: 14px;
          color: #6f83a3;
          font-size: 14px;
          line-height: 1.8;
        }

        @media (max-width: 1180px) {
          .browse-filter-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
          }

          .browse-filter-actions {
            justify-content: flex-start;
          }
        }

        @media (max-width: 760px) {
          .proto-container {
            padding: 0 16px;
          }

          .browse-filter-grid {
            grid-template-columns: 1fr;
          }

          .browse-pagination {
            justify-content: flex-start;
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
              <section class="browse-panel">
                <h3>Filters</h3>
                <div class="browse-filter-grid">
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
                    <div class="browse-filter-label">Subtype</div>
                    <select class="browse-filter-select" id="browseSubtype">
                      <option value="">All subtypes</option>
                    </select>
                  </div>
                  <div class="browse-filter-actions">
                    <button class="browse-filter-btn is-primary" id="browseApplyBtn" type="button">Apply</button>
                    <button class="browse-filter-btn" id="browseResetBtn" type="button">Reset</button>
                  </div>
                </div>
              </section>

              <section class="browse-results">
                <div class="browse-table-wrap">
                  <table class="browse-table">
                    <colgroup>
                      <col style="width: 18%">
                      <col style="width: 14%">
                      <col style="width: 14%">
                      <col style="width: 14%">
                      <col style="width: 30%">
                      <col style="width: 10%">
                    </colgroup>
                    <thead>
                      <tr>
                        <th>TE name</th>
                        <th>Class</th>
                        <th>Family</th>
                        <th>Subtype</th>
                        <th>Description</th>
                        <th>Length</th>
                      </tr>
                    </thead>
                    <tbody id="browseTableBody"></tbody>
                  </table>
                </div>
                <div class="browse-empty" id="browseEmpty">No TE records match the current search and filter combination. Try clearing one or more conditions.</div>

                <div class="browse-pagination">
                  <div class="browse-page-size">
                    <span class="browse-page-size-label">Items per page:</span>
                    <select class="browse-page-size-select" id="browsePageSize">
                      <option value="10" selected>10</option>
                      <option value="20">20</option>
                      <option value="50">50</option>
                    </select>
                  </div>
                  <div class="browse-page-status" id="browsePageStatus">1 - 10 of 10</div>
                  <div class="browse-page-jump">
                    <span class="browse-page-jump-label">Page</span>
                    <input class="browse-page-jump-input" id="browsePageJump" type="number" min="1" step="1" value="1">
                  </div>
                  <div class="browse-page-actions">
                    <button class="browse-page-btn" id="browsePrevBtn" type="button" aria-label="Previous page">
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6"></path></svg>
                    </button>
                    <button class="browse-page-btn" id="browseNextBtn" type="button" aria-label="Next page">
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"></path></svg>
                    </button>
                  </div>
                </div>

                <p class="browse-note">This browse catalog now uses the aligned Repbase-backed TE dataset and current lineage reference. It shows formal catalog pagination and hands TE clicks off to Search for detailed inspection.</p>
              </section>
            </div>
          </div>
        </section>
      </main>
    </div>
    <script>
      (() => {
        const browseSearchBase = <?= json_encode($browseSearchUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        const browseRows = <?= json_encode($browseRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        let pageSize = 10;
        let currentPage = 1;
        let filteredRows = browseRows.slice();

        const keywordInput = document.getElementById('browseKeyword');
        const classSelect = document.getElementById('browseClass');
        const familySelect = document.getElementById('browseFamily');
        const subtypeSelect = document.getElementById('browseSubtype');
        const applyBtn = document.getElementById('browseApplyBtn');
        const resetBtn = document.getElementById('browseResetBtn');
        const prevBtn = document.getElementById('browsePrevBtn');
        const nextBtn = document.getElementById('browseNextBtn');
        const pageSizeSelect = document.getElementById('browsePageSize');
        const pageJumpInput = document.getElementById('browsePageJump');
        const pageStatus = document.getElementById('browsePageStatus');
        const tableBody = document.getElementById('browseTableBody');
        const emptyState = document.getElementById('browseEmpty');

        function fillSelect(select, values) {
          values.forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            select.appendChild(option);
          });
        }

        function uniqueValues(key) {
          return [...new Set(browseRows.map((row) => row[key]).filter(Boolean))].sort((a, b) => a.localeCompare(b));
        }

        function createCell(text, className = '') {
          const td = document.createElement('td');
          td.textContent = text;
          if (className) td.className = className;
          td.title = text || '';
          return td;
        }

        function createDescriptionCell(text) {
          const td = document.createElement('td');
          td.className = 'browse-description-cell';
          td.textContent = text || '-';
          td.title = text || '';
          return td;
        }

        function renderRows() {
          tableBody.innerHTML = '';
          const total = filteredRows.length;
          const totalPages = total === 0 ? 1 : Math.ceil(total / pageSize);
          currentPage = Math.max(1, Math.min(currentPage, totalPages));
          const startIndex = (currentPage - 1) * pageSize;
          const pageRows = filteredRows.slice(startIndex, startIndex + pageSize);

          pageRows.forEach((row) => {
            const tr = document.createElement('tr');
            const targetUrl = new URL(browseSearchBase, window.location.origin);
            targetUrl.searchParams.set('q', row.name);
            targetUrl.searchParams.set('type', 'TE');

            const nameTd = document.createElement('td');
            const link = document.createElement('a');
            link.className = 'browse-row-link';
            link.href = targetUrl.toString();
            link.textContent = row.name;
            nameTd.appendChild(link);
            nameTd.className = 'browse-name-cell';
            nameTd.title = row.name || '';
            tr.appendChild(nameTd);
            tr.appendChild(createCell(row.className || '-', 'browse-meta-cell'));
            tr.appendChild(createCell(row.family || '-', 'browse-meta-cell'));
            tr.appendChild(createCell(row.subtype || '-', 'browse-meta-cell'));
            tr.appendChild(createDescriptionCell(row.description || '-'));
            tr.appendChild(createCell(row.lengthBp ? `${row.lengthBp} bp` : '-', 'browse-meta-cell'));
            tableBody.appendChild(tr);
          });

          emptyState.classList.toggle('is-visible', total === 0);
          if (total === 0) {
            pageStatus.textContent = '0 - 0 of 0';
            pageJumpInput.value = '1';
          } else {
            const from = startIndex + 1;
            const to = Math.min(startIndex + pageSize, total);
            pageStatus.textContent = `${from} - ${to} of ${total}`;
            pageJumpInput.value = String(currentPage);
          }
          prevBtn.disabled = currentPage <= 1 || total === 0;
          nextBtn.disabled = currentPage >= totalPages || total === 0;
          pageJumpInput.disabled = total === 0;
        }

        function applyFilters() {
          const keyword = (keywordInput.value || '').trim().toLowerCase();
          const classValue = classSelect.value;
          const familyValue = familySelect.value;
          const subtypeValue = subtypeSelect.value;

          filteredRows = browseRows.filter((row) => {
            const haystack = [row.name, row.className, row.family, row.subtype, row.description, ...(row.keywords || [])].join(' ').toLowerCase();
            if (keyword && !haystack.includes(keyword)) return false;
            if (classValue && row.className !== classValue) return false;
            if (familyValue && row.family !== familyValue) return false;
            if (subtypeValue && row.subtype !== subtypeValue) return false;
            return true;
          });

          currentPage = 1;
          renderRows();
        }

        function resetFilters() {
          keywordInput.value = '';
          classSelect.value = '';
          familySelect.value = '';
          subtypeSelect.value = '';
          filteredRows = browseRows.slice();
          currentPage = 1;
          renderRows();
        }

        function jumpToPage() {
          const totalPages = filteredRows.length === 0 ? 1 : Math.ceil(filteredRows.length / pageSize);
          const requestedPage = Number.parseInt(pageJumpInput.value || '1', 10);
          if (Number.isNaN(requestedPage)) {
            pageJumpInput.value = String(currentPage);
            return;
          }
          currentPage = Math.max(1, Math.min(requestedPage, totalPages));
          renderRows();
        }

        fillSelect(classSelect, uniqueValues('className'));
        fillSelect(familySelect, uniqueValues('family'));
        fillSelect(subtypeSelect, uniqueValues('subtype'));
        renderRows();

        applyBtn.addEventListener('click', applyFilters);
        resetBtn.addEventListener('click', resetFilters);
        prevBtn.addEventListener('click', () => {
          if (currentPage > 1) {
            currentPage -= 1;
            renderRows();
          }
        });
        nextBtn.addEventListener('click', () => {
          const totalPages = filteredRows.length === 0 ? 1 : Math.ceil(filteredRows.length / pageSize);
          if (currentPage < totalPages) {
            currentPage += 1;
            renderRows();
          }
        });
        pageSizeSelect.addEventListener('change', () => {
          const nextSize = Number.parseInt(pageSizeSelect.value || '10', 10);
          pageSize = Number.isNaN(nextSize) ? 10 : nextSize;
          currentPage = 1;
          renderRows();
        });
        pageJumpInput.addEventListener('keydown', (event) => {
          if (event.key === 'Enter') jumpToPage();
        });
        keywordInput.addEventListener('keydown', (event) => {
          if (event.key === 'Enter') applyFilters();
        });
        [classSelect, familySelect, subtypeSelect].forEach((select) => {
          select.addEventListener('change', applyFilters);
        });
      })();
    </script>
  </body>
</html>
