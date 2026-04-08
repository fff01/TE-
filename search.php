<?php
require_once __DIR__ . '/site_i18n.php';

$pageTitle = 'TE-KG Search';
$activePage = 'search';
$protoCurrentPath = '/TE-/search.php';
$protoSubtitle = 'Search the current TE knowledge graph';

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

    $clean = static function (string $value): string {
        $value = trim($value);
        $value = preg_replace('/<[^>]+>/', '', $value) ?? $value;
        $value = rtrim($value, ".;,");
        return preg_replace('/\s+/', ' ', $value) ?? $value;
    };
    $canonicalize = static function (string $value) use ($clean): string {
        return str_replace(['_', '-', ' '], '', mb_strtolower($clean($value)));
    };

    $strictKey = mb_strtolower($clean($query));
    $canonicalKey = $canonicalize($query);
    $entryId = $payload['name_index'][$strictKey] ?? $payload['canonical_index'][$canonicalKey] ?? null;
    if (!$entryId || empty($payload['entries']) || !is_array($payload['entries'])) {
        return null;
    }

    foreach ($payload['entries'] as $entry) {
        if (($entry['id'] ?? '') !== $entryId) {
            continue;
        }
        return [
            'matched' => $query,
            'id' => (string) ($entry['id'] ?? ''),
            'nm' => (string) ($entry['name'] ?? ''),
            'description' => (string) ($entry['description'] ?? ''),
            'keywords' => is_array($entry['keywords'] ?? null) ? implode(', ', $entry['keywords']) : '',
            'species' => (string) ($entry['species'] ?? ''),
            'sequence_summary' => (string) (($entry['sequence_summary']['raw'] ?? '') ?: ''),
            'reference_count' => is_array($entry['references'] ?? null) ? count($entry['references']) : 0,
        ];
    }

    return null;
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
          grid-template-columns: minmax(260px, 0.72fr) minmax(380px, 1.28fr);
          gap: 28px 36px;
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
          grid-template-columns: minmax(340px, .92fr) minmax(0, 1.35fr);
          gap: 22px;
          align-items: start;
        }

        .side-stack {
          display: grid;
          gap: 22px;
        }

        .data-panel {
          background: #ffffff;
          border: 1px solid #dbe7f8;
          border-radius: 10px;
          padding: 24px 26px 22px;
          box-shadow: 0 8px 24px rgba(25, 56, 105, 0.05);
        }

        .data-panel h3 {
          margin: 0 0 14px;
          font-size: 22px;
          font-weight: 700;
          color: #214b8d;
          padding-bottom: 12px;
          border-bottom: 1px solid #e5edf7;
        }

        .panel-body {
          line-height: 1.8;
          color: #5e7288;
          min-height: 120px;
        }

        .graph-panel {
          background: #ffffff;
          border: 1px solid #dbe7f8;
          border-radius: 10px;
          padding: 22px;
          box-shadow: 0 8px 24px rgba(25, 56, 105, 0.05);
          display: flex;
          flex-direction: column;
          gap: 14px;
          min-height: 720px;
        }

        .graph-panel-head {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 14px;
        }

        .graph-panel-head h3 {
          margin: 0;
          font-size: 22px;
          color: #214b8d;
        }

        .graph-reset {
          border: 1px solid #dce5f3;
          border-radius: 8px;
          background: #eef4ff;
          color: #2753b7;
          padding: 10px 16px;
          font-weight: 700;
          cursor: pointer;
        }

        .graph-frame {
          flex: 1;
          min-height: 640px;
          border: 1px solid #d8e4f0;
          border-radius: 10px;
          background: linear-gradient(180deg, #ffffff 0%, #eef5ff 100%);
        }

        .graph-frame iframe {
          width: 100%;
          height: 100%;
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
              <div class="query-form-grid">
                <div class="query-field">
                  <label for="search-type">Entity type</label>
                  <select id="search-type" class="query-control" name="type">
                    <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All entity types</option>
                    <option value="TE" <?= $type === 'TE' ? 'selected' : '' ?>>TE</option>
                    <option value="Disease" <?= $type === 'Disease' ? 'selected' : '' ?>>Disease</option>
                    <option value="Function" <?= $type === 'Function' ? 'selected' : '' ?>>Function</option>
                    <option value="Paper" <?= $type === 'Paper' ? 'selected' : '' ?>>Paper</option>
                  </select>
                </div>
                <div class="query-field">
                  <label for="search-query">Identifier or keyword</label>
                  <input id="search-query" class="query-control" type="text" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="Enter a TE, disease, function, or PMID">
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

          <section class="search-layout">
            <div class="side-stack">
              <section class="data-panel">
                <h3>Repbase Reference</h3>
                <div id="search-repbase" class="panel-body">
                  <?php if ($repbase !== null): ?>
                    <div><strong>Matched name: </strong><?= htmlspecialchars($repbase['matched'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Repbase ID: </strong><?= htmlspecialchars($repbase['id'] ?: '-', ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Canonical name: </strong><?= htmlspecialchars($repbase['nm'] ?: $repbase['id'] ?: '-', ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Description: </strong><?= htmlspecialchars($repbase['description'] ?: 'No description', ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Keywords: </strong><?= htmlspecialchars($repbase['keywords'] ?: 'No keywords', ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Species: </strong><?= htmlspecialchars($repbase['species'] ?: 'No species information', ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Sequence summary: </strong><?= htmlspecialchars($repbase['sequence_summary'] ?: 'No sequence summary', ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Reference count: </strong><?= htmlspecialchars((string) ($repbase['reference_count'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                  <?php elseif ($query !== ''): ?>
                    The current query is not found in the aligned Repbase subset. If the best match is a TE, the page will try again using the best-matched TE name.
                  <?php else: ?>
                    This block shows Repbase information for TE entries aligned to the current database, including canonical name, description, keywords, species, and sequence summary.
                  <?php endif; ?>
                </div>
              </section>
            </div>

            <section class="graph-panel">
              <div class="graph-panel-head">
                <h3>Local Graph</h3>
                <button id="search-reset-graph" type="button" class="graph-reset">Reset Graph</button>
              </div>
              <?php if ($siteRenderer === 'g6'): ?>
                <div class="graph-frame">
                  <iframe
                    id="search-g6-frame"
                    src="<?= htmlspecialchars($searchGraphSrc, ENT_QUOTES, 'UTF-8') ?>"
                    title="Search graph (G6)"
                  ></iframe>
                </div>
              <?php else: ?>
                <div class="graph-frame">
                  <iframe
                    id="search-cyt-frame"
                    src="<?= htmlspecialchars($searchGraphSrc, ENT_QUOTES, 'UTF-8') ?>"
                    title="Search graph (Cytoscape)"
                  ></iframe>
                </div>
              <?php endif; ?>
            </section>
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

      <script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.25.0/cytoscape.min.js"></script>
      <script src="/TE-/assets/data/graph_demo_data.js"></script>
      <script>
      (function () {
        const lang = <?= json_encode($siteLang, JSON_UNESCAPED_UNICODE) ?>;
        const renderer = <?= json_encode($siteRenderer, JSON_UNESCAPED_UNICODE) ?>;
        const texts = {
          te: 'TE',
          disease: 'Disease',
          function: 'Function/Mechanism',
          paper: 'Paper',
          relation: 'relation',
          evidence: 'Evidence: ',
          emptyNode: 'No additional description is available for this node.',
          searching: 'Searching for',
          repbaseDefault: 'This block shows Repbase information for TE entries aligned to the current database, including canonical name, description, keywords, species, and sequence summary.',
          repbaseMissing: 'The current query is not found in the aligned Repbase subset.',
          repbaseError: 'Failed to load Repbase reference: ',
          repbaseUnavailable: 'Repbase reference is temporarily unavailable.',
          noDescription: 'No description',
          noKeywords: 'No keywords',
          noSpecies: 'No species information',
          noSequence: 'No sequence summary',
          matchName: 'Matched name: ',
          canonicalName: 'Canonical name: ',
          description: 'Description: ',
          keywords: 'Keywords: ',
          species: 'Species: ',
          sequenceSummary: 'Sequence summary: ',
          referenceCount: 'Reference count: ',
        };

        const repbaseEl = document.getElementById('search-repbase');
        const resetBtn = document.getElementById('search-reset');
        const resetGraphBtn = document.getElementById('search-reset-graph');
        const exampleBtn = document.getElementById('search-example');
        const searchForm = document.getElementById('search-form');
        const queryInput = document.getElementById('search-query');
        const typeField = document.getElementById('search-type');
        let repbaseDataPromise = null;

        function cleanRepbaseLabel(value) {
          return String(value || '').replace(/<[^>]+>/g, '').trim().replace(/[.;,]+$/g, '').replace(/\s+/g, ' ');
        }

        function canonicalizeRepbaseLabel(value) {
          return cleanRepbaseLabel(value).toLowerCase().replace(/[_\-\s]/g, '');
        }

        async function loadRepbaseData() {
          if (!repbaseDataPromise) {
            repbaseDataPromise = fetch('/TE-/data/processed/te_repbase_db_matched.json').then(function (res) {
              if (!res.ok) throw new Error('Repbase data load failed');
              return res.json();
            });
          }
          return repbaseDataPromise;
        }

        function renderRepbaseCard(repbase, matchedName) {
          return [
            '<div><strong>' + texts.matchName + '</strong>' + matchedName + '</div>',
            '<div><strong>Repbase ID: </strong>' + (repbase.id || '-') + '</div>',
            '<div><strong>' + texts.canonicalName + '</strong>' + (repbase.name || repbase.id || '-') + '</div>',
            '<div><strong>' + texts.description + '</strong>' + (repbase.description || texts.noDescription) + '</div>',
            '<div><strong>' + texts.keywords + '</strong>' + ((repbase.keywords && repbase.keywords.length) ? repbase.keywords.join(', ') : texts.noKeywords) + '</div>',
            '<div><strong>' + texts.species + '</strong>' + (repbase.species || texts.noSpecies) + '</div>',
            '<div><strong>' + texts.sequenceSummary + '</strong>' + ((repbase.sequence_summary && repbase.sequence_summary.raw) ? repbase.sequence_summary.raw : texts.noSequence) + '</div>',
            '<div><strong>' + texts.referenceCount + '</strong>' + ((repbase.references && repbase.references.length) ? repbase.references.length : 0) + '</div>'
          ].join('');
        }

        async function updateRepbaseBlock(query, payload) {
          if (!repbaseEl) return;
          const anchor = payload && payload.anchor ? payload.anchor : null;
          const candidateNames = [];
          if (anchor && anchor.type === 'TE') {
            if (anchor.standard_name) candidateNames.push(anchor.standard_name);
            if (anchor.name) candidateNames.push(anchor.name);
          }
          if (query) candidateNames.push(query);
          const uniqueNames = Array.from(new Set(candidateNames.filter(Boolean)));
          if (!uniqueNames.length) {
            repbaseEl.innerHTML = texts.repbaseDefault;
            return;
          }
          try {
            const repbasePayload = await loadRepbaseData();
            const entries = repbasePayload.entries || [];
            const entryById = new Map(entries.map(function (entry) { return [entry.id, entry]; }));
            let matchedId = null;
            let matchedName = null;
            uniqueNames.some(function (name) {
              const strictKey = cleanRepbaseLabel(name).toLowerCase();
              const canonicalKey = canonicalizeRepbaseLabel(name);
              matchedId = (repbasePayload.name_index && repbasePayload.name_index[strictKey]) || (repbasePayload.canonical_index && repbasePayload.canonical_index[canonicalKey]) || null;
              if (matchedId) {
                matchedName = name;
                return true;
              }
              return false;
            });
            if (!matchedId || !entryById.has(matchedId)) {
              repbaseEl.innerHTML = texts.repbaseMissing;
              return;
            }
            repbaseEl.innerHTML = renderRepbaseCard(entryById.get(matchedId), matchedName || matchedId);
          } catch (err) {
            repbaseEl.innerHTML = texts.repbaseError + (err && err.message ? err.message : 'unknown error');
          }
        }

        const g6BaseSrc = <?= json_encode(site_url_with_state('/TE-/index_g6.html', $siteLang, 'g6', ['embed' => 'search-result']), JSON_UNESCAPED_UNICODE) ?>;
        const cytBaseSrc = <?= json_encode(site_url_with_state('/TE-/index_demo.html', $siteLang, 'cytoscape', ['embed' => 'search-result']), JSON_UNESCAPED_UNICODE) ?>;

        function setG6Frame(query) {
          const frame = document.getElementById('search-g6-frame');
          if (!frame) return;
          const url = new URL(g6BaseSrc, window.location.origin);
          if (query) {
            url.searchParams.set('q', query);
          }
          frame.src = url.toString();
        }

        function setCytFrame(query) {
          const frame = document.getElementById('search-cyt-frame');
          if (!frame) return;
          const url = new URL(cytBaseSrc, window.location.origin);
          if (query) {
            url.searchParams.set('q', query);
          }
          frame.src = url.toString();
        }

        function resizeCytFrame() {
          const frame = document.getElementById('search-cyt-frame');
          if (!frame) return;
          try {
            const cy = frame.contentWindow && frame.contentWindow.__TEKG_CY ? frame.contentWindow.__TEKG_CY : null;
            if (cy) {
              cy.resize();
              cy.fit(undefined, 55);
            }
          } catch (_error) {}
        }

        async function runSearch(query) {
          if (!query) {
            repbaseEl.innerHTML = texts.repbaseDefault;
            if (renderer === 'g6') setG6Frame('');
            return;
          }

          try {
            const searchUrl = new URL('/TE-/api/graph.php', window.location.origin);
            searchUrl.searchParams.set('q', query);
            if (typeField && typeField.value !== 'all') {
              searchUrl.searchParams.set('type', typeField.value);
            }
            searchUrl.searchParams.set('lang', lang);
            const response = await fetch(searchUrl.toString(), { cache: 'no-store' });
            const payload = await response.json();
            if (!response.ok || !payload || payload.ok === false) {
              throw new Error((payload && payload.error) || 'search failed');
            }
            await updateRepbaseBlock(query, payload);
            if (renderer === 'g6') {
              setG6Frame(query);
            } else {
              setCytFrame(query);
            }
          } catch (err) {
            repbaseEl.innerHTML = texts.repbaseError + (err && err.message ? err.message : 'unknown error');
          }
        }

        if (exampleBtn) {
          exampleBtn.addEventListener('click', function () {
            queryInput.value = 'L1HS';
            searchForm.requestSubmit();
          });
        }

        if (resetBtn) {
          resetBtn.addEventListener('click', function () {
            queryInput.value = '';
            if (typeField) typeField.value = 'all';
            const url = new URL(window.location.href);
            url.searchParams.delete('q');
            url.searchParams.set('type', 'all');
            url.searchParams.set('lang', lang);
            url.searchParams.set('renderer', renderer);
            window.history.replaceState({}, '', url.toString());
            runSearch('');
          });
        }

        if (resetGraphBtn) {
          resetGraphBtn.addEventListener('click', function () {
            if (renderer === 'g6') {
              setG6Frame('');
            } else {
              setCytFrame('');
            }
          });
        }

        if (searchForm) {
          searchForm.addEventListener('submit', function (evt) {
            evt.preventDefault();
            const query = queryInput.value.trim();
            const url = new URL(window.location.href);
            if (query) {
              url.searchParams.set('q', query);
            } else {
              url.searchParams.delete('q');
            }
            if (typeField) url.searchParams.set('type', typeField.value || 'all');
            url.searchParams.set('lang', lang);
            url.searchParams.set('renderer', renderer);
            window.history.replaceState({}, '', url.toString());
            runSearch(query);
          });
        }

        const cytFrame = document.getElementById('search-cyt-frame');
        if (cytFrame) {
          cytFrame.addEventListener('load', () => {
            setTimeout(resizeCytFrame, 120);
            setTimeout(resizeCytFrame, 420);
          });
        }

        runSearch(queryInput.value.trim());
      }());
      </script>
    </main>
  </div>
</body>
</html>
