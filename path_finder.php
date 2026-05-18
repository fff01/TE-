<?php
require_once __DIR__ . '/path_config.php';

$pageTitle = 'TE-KG Path Finder';
$activePage = 'path_finder';
$protoCurrentPath = tekg_app_url('path_finder.php');
$protoSubtitle = 'Find interpretable paths between TE-KG entities';
$pageExtraStylesheets = [
    tekg_assets_url('css/pages/path_finder.css'),
];

require __DIR__ . '/head.php';
?>
        <section class="path-finder-shell">
          <div class="proto-container">
            <h1 class="path-finder-title">Path Finder</h1>
            <p class="path-finder-intro">Search for concise, evidence-backed paths between two TE-KG entities. Paper nodes are kept out of the path and PMID evidence is shown on each relationship.</p>
            <div class="path-finder-crumbs">
              <a href="<?= htmlspecialchars(site_url_with_state(tekg_app_url('index.php'), $siteLang), ENT_QUOTES, 'UTF-8') ?>">Home</a>
              <span>/</span>
              <span>Path Finder</span>
            </div>

            <section class="path-finder-panel" aria-label="Path Finder query controls">
              <form class="path-finder-form" id="pathFinderForm">
                <label class="path-field">
                  <span>Source entity</span>
                  <input id="pathSource" name="source" type="text" value="L1HS" autocomplete="off" placeholder="L1HS, Alu, ERVL">
                </label>
                <label class="path-field">
                  <span>Target entity</span>
                  <input id="pathTarget" name="target" type="text" value="Alzheimer's disease" autocomplete="off" placeholder="Alzheimer's disease, TP53, A-to-I RNA editing">
                </label>
                <label class="path-field path-depth-field">
                  <span>Max hops</span>
                  <select id="pathMaxDepth" name="max_depth">
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3" selected>3</option>
                  </select>
                </label>
                <div class="path-actions">
                  <button class="path-submit" id="pathSubmit" type="submit">Find paths</button>
                </div>
              </form>
              <div class="path-finder-note">Direct relationships are shown as cards only. Multi-hop paths include a compact path strip for quick scanning.</div>
            </section>

            <section class="path-results-panel" aria-live="polite">
              <div class="path-results-status" id="pathStatus">Enter two entities and run a search to inspect relationship paths.</div>
              <div class="path-resolved" id="pathResolved" hidden></div>
              <div class="path-results-list" id="pathResults"></div>
            </section>
          </div>
        </section>
      </main>
    </div>
    <script src="<?= htmlspecialchars(tekg_assets_url('js/pages/path_finder.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
  </body>
</html>
