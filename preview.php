<?php
require_once __DIR__ . '/path_config.php';
$pageTitle = 'TE-KG Preview';
$activePage = 'preview';
$protoCurrentPath = tekg_app_url('preview.php');
$protoSubtitle = 'Interactive graph preview';
$protoMainClass = 'preview-main';
$pageExtraStylesheets = [
    tekg_assets_url('css/tekg_runtime.css'),
    tekg_assets_url('css/pages/preview.css'),
];
require __DIR__ . '/head.php';

$siteLang = site_lang();
$initialQuery = trim((string)($_GET['q'] ?? ''));
$previewVersion = max(
    (int)@filemtime(__FILE__),
    (int)@filemtime(tekg_assets_fs_path('css/pages/preview.css')),
    (int)@filemtime(tekg_assets_fs_path('js/components/deepthink-client.js')),
    (int)@filemtime(tekg_assets_fs_path('js/pages/preview/preview-shell.js')),
    (int)@filemtime(tekg_assets_fs_path('js/pages/preview/preview-deepthink.js')),
    (int)@filemtime(tekg_assets_fs_path('js/renderers/g6/index-g6-type-meta.js')),
    (int)@filemtime(tekg_assets_fs_path('js/renderers/g6/index-g6.bootstrap.js'))
);
$previewConfig = [
    'deepThinkStreamApiUrl' => tekg_api_url('deep_think_stream.php'),
    'sessionStorageKey' => 'tekg-preview-deepthink-session',
    'sourcePage' => 'preview',
    'initialQuery' => $initialQuery,
    'defaultQuestion' => $initialQuery !== '' ? $initialQuery . ' related diseases' : 'LINE-1 related diseases',
    'defaultModel' => 'deepseek-v4-flash',
];
?>
      <section class="preview-stage" id="previewStage">
        <button class="preview-fullscreen-btn" id="previewFullscreenBtn" type="button" aria-label="Enter fullscreen preview">
          Fullscreen
        </button>

        <div class="main preview-graph-workspace" id="previewGraphWorkspace">
          <section class="panel preview-graph-panel" aria-label="TE-KG graph workspace">
            <div class="toolbar preview-graph-toolbar">
              <div class="search">
                <span class="preview-search-icon" aria-hidden="true">Search</span>
                <input id="node-search" type="text" placeholder="Search LINE1, L1HS, disease, or function">
              </div>
              <button id="toggle-focus-view" class="focus-legacy" type="button" style="display:none">
                <span id="focus-view-text">Focus mode: Global</span>
              </button>
              <button id="toggle-expand-mode" class="expand-mode is-toggle" type="button" aria-pressed="false"><span id="expand-mode-text">Expand mode: Off</span></button>
              <button id="toggle-non-key-nodes" class="non-key-legacy" type="button" style="display:none">
                <span id="non-key-nodes-text">Hide non-key nodes: Off</span>
              </button>
              <button id="toggle-edge-labels" class="is-toggle" type="button" aria-pressed="false"><span id="edge-labels-text">Show labels: Off</span></button>
              <button id="toggle-show-labels" class="is-toggle" type="button" aria-pressed="false"><span id="show-labels-text">Show names: Off</span></button>
              <button id="toggle-fixed-view" class="is-toggle" type="button" aria-pressed="true"><span id="fixed-view-text">Fixed view: On</span></button>
              <button id="back-graph" type="button" disabled><span id="back-text">Back</span></button>
              <button id="reset-graph" type="button"><span id="reset-text">Reset</span></button>
            </div>
            <div class="g6-surface-stack preview-g6-surface-stack">
              <div id="cy" style="display:none"></div>
              <div id="g6-default-tree-surface"></div>
              <div id="g6-dynamic-surface"></div>
              <div id="graph-preloader" class="graph-preloader" aria-hidden="true">
                <div class="graph-preloader-inner">
                  <div class="graph-preloader-icon" aria-hidden="true">
                    <span></span>
                    <span></span>
                  </div>
                  <div id="graph-preloader-label" class="graph-preloader-label">Loading graph...</div>
                </div>
              </div>
              <div id="graph-type-legend" class="graph-legend-panel" aria-label="Entity legend" aria-hidden="true" hidden>
                <div class="graph-legend-mode-switch" role="tablist" aria-label="Graph legend mode">
                  <button id="graph-legend-entity-tab" class="graph-legend-tab is-active" type="button" data-legend-mode="entity" aria-pressed="true">Entity</button>
                  <span class="graph-legend-mode-separator" aria-hidden="true">/</span>
                  <button id="graph-legend-relation-tab" class="graph-legend-tab" type="button" data-legend-mode="relation" aria-pressed="false">Relation</button>
                </div>
                <div id="graph-legend-title" class="graph-legend-title">Entity Legend</div>
                <div id="graph-relation-controls" class="graph-relation-controls" hidden>
                  <label class="graph-relation-min-pmids-label" for="graph-relation-min-pmids">Min PMID</label>
                  <input id="graph-relation-min-pmids" class="graph-relation-min-pmids" type="number" min="0" max="99" step="1" value="0">
                </div>
                <div id="graph-legend-list" class="graph-legend-list"></div>
                <div class="graph-legend-footer">
                  <button id="graph-legend-apply" class="graph-legend-apply" type="button" disabled>Apply</button>
                </div>
              </div>
            </div>
            <div class="nav" id="search-results-nav" style="display:none">
              <button id="prev-result" type="button">Prev</button>
              <span id="result-counter">0/0</span>
              <span id="result-name"></span>
              <button id="next-result" type="button">Next</button>
            </div>
            <div class="detail" id="node-details">Preparing graph workspace...</div>
          </section>
        </div>

        <div class="qa-overlay-layer is-open" id="qaOverlay">
          <div class="qa-drawer" id="qaDrawer">
            <button class="qa-drawer-drag" id="qaDrawerDrag" type="button" aria-label="Move Deep Think assistant"></button>
            <div class="qa-drawer-body">
              <section class="preview-deepthink" id="previewDeepThink" aria-label="Deep Think assistant">
                <header class="preview-deepthink-head">
                  <div class="preview-deepthink-titlebar">
                    <h2>Deep thinking</h2>
                    <span class="preview-deepthink-status" id="previewDeepThinkStatus">Ready</span>
                  </div>
                  <button class="preview-deepthink-clear" id="previewDeepThinkClearGraph" type="button">Back</button>
                </header>
                <div class="preview-deepthink-messages" id="previewDeepThinkMessages" aria-live="polite"></div>
                <form class="preview-deepthink-form" id="previewDeepThinkForm">
                  <textarea id="previewDeepThinkInput" rows="2" placeholder="Ask about L1HS, LINE-1, diseases, locations, expression, or mechanisms."><?= htmlspecialchars($initialQuery !== '' ? $initialQuery . ' 和哪些疾病相关' : '', ENT_QUOTES, 'UTF-8') ?></textarea>
                  <button id="previewDeepThinkSubmit" type="submit">Send</button>
                </form>
              </section>
            </div>
            <button class="qa-drawer-resize qa-drawer-resize-w" id="qaDrawerResizeW" type="button" aria-label="Resize Deep Think assistant width"></button>
            <button class="qa-drawer-resize qa-drawer-resize-e" id="qaDrawerResizeE" type="button" aria-label="Resize Deep Think assistant width"></button>
            <button class="qa-drawer-resize qa-drawer-resize-s" id="qaDrawerResizeS" type="button" aria-label="Resize Deep Think assistant height"></button>
            <button class="qa-drawer-resize qa-drawer-resize-nw" id="qaDrawerResizeNW" type="button" aria-label="Resize Deep Think assistant"></button>
            <button class="qa-drawer-resize qa-drawer-resize-ne" id="qaDrawerResizeNE" type="button" aria-label="Resize Deep Think assistant"></button>
            <button class="qa-drawer-resize qa-drawer-resize-sw" id="qaDrawerResizeSW" type="button" aria-label="Resize Deep Think assistant"></button>
            <button class="qa-drawer-resize qa-drawer-resize-se" id="qaDrawerResizeSE" type="button" aria-label="Resize Deep Think assistant"></button>
          </div>

          <button class="qa-fab" id="qaFab" type="button" aria-label="Toggle Deep Think assistant">
            <svg viewBox="0 0 64 64" aria-hidden="true">
              <rect x="14" y="18" width="36" height="28" rx="10" fill="none" stroke="currentColor" stroke-width="4"/>
              <circle cx="26" cy="31" r="3" fill="currentColor"/>
              <circle cx="38" cy="31" r="3" fill="currentColor"/>
              <path d="M24 40h16" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
              <path d="M32 8v7" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
            </svg>
          </button>
        </div>
      </section>

      <script id="preview-config" type="application/json"><?= json_encode($previewConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
      <script>
        window.__TEKG_RENDERER_MODE = 'g6';
        window.__TEKG_EMBED_MODE = 'preview-direct';
        window.__TEKG_INITIAL_QUERY = <?= json_encode($initialQuery, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.__TEKG_COMPACT_EMBED = false;
        window.__TEKG_G6_BOOTSTRAP_OWN_TREE = true;
      </script>
      <script src="<?= htmlspecialchars(tekg_assets_url('js/components/deepthink-client.js') . '?v=' . $previewVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
      <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
      <script src="https://cdn.jsdelivr.net/npm/@antv/g6@5/dist/g6.min.js"></script>
      <script src="<?= htmlspecialchars(tekg_assets_url('js/tekg_runtime_data.js') . '?v=' . $previewVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
      <script src="<?= htmlspecialchars(tekg_assets_url('js/renderers/g6/index-g6-type-meta.js') . '?v=' . $previewVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
      <script src="<?= htmlspecialchars(tekg_assets_url('js/renderers/g6/default-tree-mindmap.js') . '?v=' . $previewVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
      <script src="<?= htmlspecialchars(tekg_assets_url('js/renderers/g6/index-g6-runtime.js') . '?v=' . $previewVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
      <script src="<?= htmlspecialchars(tekg_assets_url('js/renderers/g6/index-g6.bootstrap.js') . '?v=' . $previewVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
      <script src="<?= htmlspecialchars(tekg_assets_url('js/pages/preview/preview-shell.js') . '?v=' . $previewVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
      <script src="<?= htmlspecialchars(tekg_assets_url('js/pages/preview/preview-deepthink.js') . '?v=' . $previewVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
  </main>
  </div>
</body>
</html>
