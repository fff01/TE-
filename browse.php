<?php
require_once __DIR__ . '/path_config.php';
$pageTitle = 'TE-KG Browse';
$activePage = 'browse';
$protoCurrentPath = tekg_app_url('browse.php');
$protoSubtitle = 'Browse TE classes and records in a structured catalog view';
$pageExtraStylesheets = [
    tekg_assets_url('css/components/te-autocomplete.css'),
    tekg_assets_url('css/pages/browse.css'),
];

require __DIR__ . '/head.php';
$browseSearchUrl = site_url_with_state(tekg_app_url('search.php'), $siteLang);
$browseApiUrl = tekg_api_url('browse.php?view=items');
?>
      <main class="proto-main">
        <section class="browse-shell">
          <div class="proto-container">
            <h1 class="browse-page-title">Browse</h1>
            <p class="browse-intro">This browse view is designed as a lightweight catalog-style entry point, which prioritizes scanning, filtering, and shortlisting TE records in a clean table layout before users move into deeper search or graph exploration.</p>
            <div class="browse-crumbs">
              <a href="<?= htmlspecialchars(site_url_with_state(tekg_app_url('index.php'), $siteLang), ENT_QUOTES, 'UTF-8') ?>">Home</a>
              <span>/</span>
              <span>Browse</span>
            </div>

            <div class="browse-layout">
              <?php require __DIR__ . '/templates/components/browse_filters.php'; ?>

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
                        <th>TE Name</th>
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

                <?php require __DIR__ . '/templates/components/browse_pagination.php'; ?>

                <p class="browse-note">This browse catalog now uses the aligned Repbase-backed TE dataset and current lineage reference. It shows formal catalog pagination and hands TE clicks off to Search for detailed inspection.</p>
              </section>
            </div>
          </div>
        </section>
      </main>
    </div>
    <script id="browse-page-data" type="application/json"><?= json_encode(['browseSearchBase' => $browseSearchUrl, 'browseApiUrl' => $browseApiUrl], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script src="<?= htmlspecialchars(tekg_assets_url('js/components/te-autocomplete.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(tekg_assets_url('js/pages/browse.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  </body>
</html>

