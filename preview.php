<?php
$pageTitle = 'TE-KG Preview';
$activePage = 'preview';
$protoCurrentPath = '/TE-/preview.php';
$protoSubtitle = 'Interactive graph preview';
require __DIR__ . '/head.php';

$siteLang = site_lang();
$siteRenderer = site_renderer();
$queryParams = $_GET;
unset($queryParams['lang'], $queryParams['renderer']);

$g6PreviewVersion = max(
    (int)@filemtime(__DIR__ . '/index_g6.html'),
    (int)@filemtime(__DIR__ . '/assets/css/tekg_runtime.css'),
    (int)@filemtime(__DIR__ . '/assets/js/renderers/g6/index-g6-qa.js')
);
$graphSrc = site_url_with_state('/TE-/index_g6.html', $siteLang, 'g6', array_merge($queryParams, ['embed' => 'preview-graphonly']));
$qaSrc = site_url_with_state('/TE-/index_g6.html', $siteLang, 'g6', array_merge($queryParams, ['embed' => 'qa-overlay']));
?>
      <link rel="stylesheet" href="/TE-/assets/css/pages/preview.css">

      <section class="preview-stage" id="previewStage" data-renderer="<?= htmlspecialchars($siteRenderer, ENT_QUOTES, 'UTF-8') ?>">
        <button class="preview-fullscreen-btn" id="previewFullscreenBtn" type="button" aria-label="Enter fullscreen preview">
          Fullscreen
        </button>
        <iframe
          id="preview-graph-frame"
          class="preview-graph-frame"
          src="<?= htmlspecialchars($graphSrc, ENT_QUOTES, 'UTF-8') ?>"
          title="TE-KG preview graph"
        ></iframe>

        <div class="qa-overlay-layer is-open" id="qaOverlay">
          <div class="qa-drawer" id="qaDrawer">
            <button class="qa-drawer-drag" id="qaDrawerDrag" type="button" aria-label="Move QA assistant"></button>
            <div class="qa-drawer-body">
              <iframe
                id="preview-qa-frame"
                title="TE-KG QA overlay"
                data-src="<?= htmlspecialchars($qaSrc, ENT_QUOTES, 'UTF-8') ?>"
              ></iframe>
            </div>
            <button class="qa-drawer-resize qa-drawer-resize-w" id="qaDrawerResizeW" type="button" aria-label="Resize QA assistant width"></button>
            <button class="qa-drawer-resize qa-drawer-resize-e" id="qaDrawerResizeE" type="button" aria-label="Resize QA assistant width"></button>
            <button class="qa-drawer-resize qa-drawer-resize-s" id="qaDrawerResizeS" type="button" aria-label="Resize QA assistant height"></button>
            <button class="qa-drawer-resize qa-drawer-resize-nw" id="qaDrawerResizeNW" type="button" aria-label="Resize QA assistant"></button>
            <button class="qa-drawer-resize qa-drawer-resize-ne" id="qaDrawerResizeNE" type="button" aria-label="Resize QA assistant"></button>
            <button class="qa-drawer-resize qa-drawer-resize-sw" id="qaDrawerResizeSW" type="button" aria-label="Resize QA assistant"></button>
            <button class="qa-drawer-resize qa-drawer-resize-se" id="qaDrawerResizeSE" type="button" aria-label="Resize QA assistant"></button>
          </div>

          <button class="qa-fab" id="qaFab" type="button" aria-label="Toggle QA assistant">
            <svg viewBox="0 0 64 64" aria-hidden="true">
              <rect x="14" y="18" width="36" height="28" rx="10" fill="none" stroke="currentColor" stroke-width="4"/>
              <circle cx="26" cy="31" r="3" fill="currentColor"/>
              <circle cx="38" cy="31" r="3" fill="currentColor"/>
              <path d="M24 40h16" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
              <path d="M32 8v7" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
              <path d="M20 14 16 10" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
              <path d="M44 14 48 10" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
            </svg>
          </button>
        </div>
      </section>
      <script src="/TE-/assets/js/pages/preview.js"></script>
  </main>
  </div>
</body>
</html>
