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
      <style>
        .proto-main.preview-main {
          padding: 0;
          background: #f3f8ff;
        }

        .preview-stage {
          position: relative;
          width: 100%;
          height: calc(100vh - 82px);
          min-height: 640px;
          background: #f3f8ff;
        }

        .preview-graph-frame {
          width: 100%;
          height: 100%;
          border: 0;
          display: block;
          background: #fff;
        }

        .preview-fullscreen-btn {
          position: absolute;
          top: 92px;
          left: 18px;
          z-index: 40;
          border: 1px solid rgba(219, 231, 243, 0.95);
          border-radius: 999px;
          background: rgba(255, 255, 255, 0.96);
          color: #18345f;
          padding: 10px 16px;
          font-size: 13px;
          font-weight: 700;
          cursor: pointer;
          box-shadow: 0 12px 26px rgba(15, 23, 42, 0.12);
          transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
        }

        .preview-fullscreen-btn:hover {
          transform: translateY(-1px);
          box-shadow: 0 16px 30px rgba(15, 23, 42, 0.16);
        }

        .preview-stage.is-immersive {
          background: #ffffff;
        }

        .preview-stage.is-immersive .preview-fullscreen-btn,
        .preview-stage.is-immersive .qa-overlay-layer {
          display: none;
        }

        .preview-stage:fullscreen,
        .preview-stage:-webkit-full-screen {
          width: 100vw;
          height: 100vh;
          min-height: 100vh;
          background: #ffffff;
        }

        .qa-overlay-layer {
          position: absolute;
          inset: 0;
          pointer-events: none;
          z-index: 20;
        }

        .qa-drawer {
          position: absolute;
          top: 92px;
          left: calc(100% - 448px);
          width: 430px;
          height: calc(100% - 110px);
          background: #ffffff;
          border: 1px solid #dbe7f3;
          border-radius: 20px;
          box-shadow: 0 18px 42px rgba(15, 23, 42, 0.16);
          overflow: hidden;
          display: flex;
          flex-direction: column;
          opacity: 0;
          visibility: hidden;
          pointer-events: none;
          transform: translateY(12px) scale(0.985);
          transition: opacity 0.22s ease, transform 0.22s ease, visibility 0.22s ease;
          user-select: none;
        }

        .qa-overlay-layer.is-open .qa-drawer {
          opacity: 1;
          visibility: visible;
          pointer-events: auto;
          transform: translateY(0) scale(1);
        }

        .qa-overlay-layer.is-dragging .qa-drawer,
        .qa-overlay-layer.is-resizing .qa-drawer {
          transition: none;
        }

        .qa-overlay-layer.is-dragging,
        .qa-overlay-layer.is-dragging * {
          cursor: move !important;
          user-select: none !important;
        }

        .qa-overlay-layer.is-resizing,
        .qa-overlay-layer.is-resizing * {
          user-select: none !important;
        }

        .qa-drawer-drag {
          position: relative;
          flex: 0 0 22px;
          width: 100%;
          padding: 0;
          border: 0;
          border-bottom: 1px solid rgba(219, 231, 243, 0.95);
          background: linear-gradient(180deg, rgba(248, 251, 255, 0.98) 0%, rgba(244, 248, 253, 0.94) 100%);
          cursor: move;
        }

        .qa-drawer-drag::after {
          content: '';
          position: absolute;
          top: 50%;
          left: 50%;
          transform: translate(-50%, -50%);
          width: 56px;
          height: 4px;
          border-radius: 999px;
          background: rgba(120, 146, 190, 0.42);
        }

        .qa-drawer-drag:hover::after,
        .qa-overlay-layer.is-dragging .qa-drawer-drag::after {
          background: rgba(79, 123, 214, 0.68);
        }

        .qa-drawer-body {
          position: relative;
          flex: 1 1 auto;
          min-height: 0;
          overflow: hidden;
        }

        .qa-drawer iframe {
          width: 100%;
          height: 100%;
          border: 0;
          display: block;
          background: #fff;
        }

        .qa-drawer-resize {
          position: absolute;
          padding: 0;
          border: 0;
          background: transparent;
          z-index: 3;
        }

        .qa-drawer-resize-w {
          top: 24px;
          left: 0;
          bottom: 14px;
          width: 14px;
          cursor: ew-resize;
        }

        .qa-drawer-resize-e {
          top: 24px;
          right: 0;
          bottom: 14px;
          width: 14px;
          cursor: ew-resize;
        }

        .qa-drawer-resize-s {
          left: 14px;
          right: 24px;
          bottom: 0;
          height: 14px;
          cursor: ns-resize;
        }

        .qa-drawer-resize-nw {
          top: 0;
          left: 0;
          width: 24px;
          height: 24px;
          cursor: nwse-resize;
        }

        .qa-drawer-resize-ne {
          top: 0;
          right: 0;
          width: 24px;
          height: 24px;
          cursor: nesw-resize;
        }

        .qa-drawer-resize-sw {
          left: 0;
          bottom: 0;
          width: 24px;
          height: 24px;
          cursor: nesw-resize;
        }

        .qa-drawer-resize-se {
          right: 0;
          bottom: 0;
          width: 24px;
          height: 24px;
          cursor: nwse-resize;
        }

        .qa-drawer-resize-w::after,
        .qa-drawer-resize-e::after,
        .qa-drawer-resize-s::after,
        .qa-drawer-resize-nw::after,
        .qa-drawer-resize-ne::after,
        .qa-drawer-resize-sw::after,
        .qa-drawer-resize-se::after {
          content: '';
          position: absolute;
          opacity: 0;
          transition: opacity 0.16s ease, background 0.16s ease, border-color 0.16s ease;
        }

        .qa-drawer-resize-w::after,
        .qa-drawer-resize-e::after {
          top: 50%;
          transform: translateY(-50%);
          width: 4px;
          height: 56px;
          border-radius: 999px;
          background: rgba(120, 146, 190, 0.42);
        }

        .qa-drawer-resize-w::after {
          left: 4px;
        }

        .qa-drawer-resize-e::after {
          right: 4px;
        }

        .qa-drawer-resize-s::after {
          left: 50%;
          bottom: 4px;
          transform: translateX(-50%);
          width: 56px;
          height: 4px;
          border-radius: 999px;
          background: rgba(120, 146, 190, 0.42);
        }

        .qa-drawer-resize-nw::after,
        .qa-drawer-resize-ne::after,
        .qa-drawer-resize-sw::after,
        .qa-drawer-resize-se::after {
          width: 12px;
          height: 12px;
          background: transparent;
        }

        .qa-drawer-resize-nw::after {
          top: 5px;
          left: 5px;
          border-left: 3px solid rgba(120, 146, 190, 0.58);
          border-top: 3px solid rgba(120, 146, 190, 0.58);
          border-top-left-radius: 10px;
        }

        .qa-drawer-resize-ne::after {
          top: 5px;
          right: 5px;
          border-right: 3px solid rgba(120, 146, 190, 0.58);
          border-top: 3px solid rgba(120, 146, 190, 0.58);
          border-top-right-radius: 10px;
        }

        .qa-drawer-resize-sw::after {
          left: 5px;
          bottom: 5px;
          border-left: 3px solid rgba(120, 146, 190, 0.58);
          border-bottom: 3px solid rgba(120, 146, 190, 0.58);
          border-bottom-left-radius: 10px;
        }

        .qa-drawer-resize-se::after {
          right: 5px;
          bottom: 5px;
          border-right: 3px solid rgba(120, 146, 190, 0.58);
          border-bottom: 3px solid rgba(120, 146, 190, 0.58);
          border-bottom-right-radius: 10px;
        }

        .qa-drawer-resize-w:hover::after,
        .qa-drawer-resize-e:hover::after,
        .qa-drawer-resize-s:hover::after,
        .qa-drawer-resize-nw:hover::after,
        .qa-drawer-resize-ne:hover::after,
        .qa-drawer-resize-sw:hover::after,
        .qa-drawer-resize-se:hover::after {
          opacity: 1;
        }

        .qa-drawer-resize-w:hover::after,
        .qa-drawer-resize-e:hover::after,
        .qa-drawer-resize-s:hover::after,
        .qa-overlay-layer.is-resizing .qa-drawer-resize-w::after,
        .qa-overlay-layer.is-resizing .qa-drawer-resize-e::after,
        .qa-overlay-layer.is-resizing .qa-drawer-resize-s::after {
          opacity: 1;
          background: rgba(79, 123, 214, 0.68);
        }

        .qa-overlay-layer.is-resizing .qa-drawer-resize-nw::after,
        .qa-overlay-layer.is-resizing .qa-drawer-resize-ne::after,
        .qa-overlay-layer.is-resizing .qa-drawer-resize-sw::after,
        .qa-overlay-layer.is-resizing .qa-drawer-resize-se::after,
        .qa-drawer-resize-nw:hover::after,
        .qa-drawer-resize-ne:hover::after,
        .qa-drawer-resize-sw:hover::after,
        .qa-drawer-resize-se:hover::after {
          opacity: 1;
          border-left-color: rgba(79, 123, 214, 0.78);
          border-right-color: rgba(79, 123, 214, 0.78);
          border-top-color: rgba(79, 123, 214, 0.78);
          border-bottom-color: rgba(79, 123, 214, 0.78);
        }

        .qa-fab {
          position: fixed;
          width: 72px;
          height: 72px;
          border: 0;
          border-radius: 50%;
          background: linear-gradient(180deg, #4f7bd6 0%, #2f63b9 100%);
          color: #fff;
          box-shadow: 0 16px 34px rgba(47, 99, 185, 0.28);
          display: grid;
          place-items: center;
          cursor: grab;
          pointer-events: auto;
          z-index: 30;
          user-select: none;
          touch-action: none;
        }

        .qa-fab:active {
          cursor: grabbing;
        }

        .qa-fab svg {
          width: 34px;
          height: 34px;
          display: block;
        }

        @media (max-width: 1120px) {
          .preview-stage {
            height: calc(100vh - 126px);
          }
        }

        @media (max-width: 680px) {
          .preview-stage {
            height: calc(100vh - 118px);
            min-height: 560px;
          }

          .qa-drawer {
            top: 10px;
            left: 10px;
            width: min(420px, calc(100vw - 20px));
            height: calc(100% - 20px);
            border-radius: 16px;
          }
        }
      </style>

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

      <script>
        (() => {
          const rendererMode = <?= json_encode($siteRenderer, JSON_UNESCAPED_UNICODE) ?>;
          const header = document.getElementById('protoHeader');
          const stage = document.getElementById('previewStage');
          const fullscreenBtn = document.getElementById('previewFullscreenBtn');
          const graphFrame = document.getElementById('preview-graph-frame');
          const qaFrame = document.getElementById('preview-qa-frame');
          const overlay = document.getElementById('qaOverlay');
          const qaDrawer = document.getElementById('qaDrawer');
          const qaDrawerDrag = document.getElementById('qaDrawerDrag');
          const qaDrawerResizeW = document.getElementById('qaDrawerResizeW');
          const qaDrawerResizeE = document.getElementById('qaDrawerResizeE');
          const qaDrawerResizeS = document.getElementById('qaDrawerResizeS');
          const qaDrawerResizeNW = document.getElementById('qaDrawerResizeNW');
          const qaDrawerResizeNE = document.getElementById('qaDrawerResizeNE');
          const qaDrawerResizeSW = document.getElementById('qaDrawerResizeSW');
          const qaDrawerResizeSE = document.getElementById('qaDrawerResizeSE');
          const fab = document.getElementById('qaFab');
          if (!stage || !fullscreenBtn || !graphFrame || !qaFrame || !overlay || !qaDrawer || !qaDrawerDrag || !qaDrawerResizeW || !qaDrawerResizeE || !qaDrawerResizeS || !qaDrawerResizeNW || !qaDrawerResizeNE || !qaDrawerResizeSW || !qaDrawerResizeSE || !fab) return;

          let qaLoaded = false;
          let drawerOpen = true;
          let qaWindowRect = null;
          let boundGraphWindow = null;
          let fabDragState = null;
          let movedDuringDrag = false;
          let moveState = null;
          let resizeState = null;

          function resizeEmbeddedCytFrame(frame, padding = 55) {
            if (!frame) return;
            try {
              const cy = frame.contentWindow && frame.contentWindow.__TEKG_CY ? frame.contentWindow.__TEKG_CY : null;
              if (cy) {
                cy.resize();
                cy.fit(undefined, padding);
              }
            } catch (_error) {}
          }

          function getStageBounds() {
            return stage.getBoundingClientRect();
          }

          function getMinDrawerWidth() {
            return 340;
          }

          function getMinDrawerHeight() {
            return 360;
          }

          function getMaxDrawerWidth() {
            const bounds = getStageBounds();
            return Math.max(280, Math.min(820, bounds.width - 24));
          }

          function getMaxDrawerHeight() {
            const bounds = getStageBounds();
            return Math.max(280, bounds.height - 24);
          }

          function clampDrawerWidth(width) {
            const maxWidth = getMaxDrawerWidth();
            const minWidth = Math.min(getMinDrawerWidth(), maxWidth);
            return Math.max(minWidth, Math.min(maxWidth, width));
          }

          function clampDrawerHeight(height) {
            const maxHeight = getMaxDrawerHeight();
            const minHeight = Math.min(getMinDrawerHeight(), maxHeight);
            return Math.max(minHeight, Math.min(maxHeight, height));
          }

          function getDefaultDrawerRect() {
            const bounds = getStageBounds();
            const width = clampDrawerWidth(430);
            const height = clampDrawerHeight(Math.max(getMinDrawerHeight(), bounds.height - 110));
            return clampDrawerRect({
              left: bounds.width - width - 18,
              top: 92,
              width,
              height,
            });
          }

          function clampDrawerRect(rect) {
            const bounds = getStageBounds();
            const width = clampDrawerWidth(rect.width);
            const height = clampDrawerHeight(rect.height);
            const maxLeft = Math.max(12, bounds.width - width - 12);
            const maxTop = Math.max(12, bounds.height - height - 12);
            return {
              left: Math.max(12, Math.min(maxLeft, rect.left)),
              top: Math.max(12, Math.min(maxTop, rect.top)),
              width,
              height,
            };
          }

          function applyDrawerRect() {
            if (!qaWindowRect) qaWindowRect = getDefaultDrawerRect();
            qaWindowRect = clampDrawerRect(qaWindowRect);
            qaDrawer.style.left = `${qaWindowRect.left}px`;
            qaDrawer.style.top = `${qaWindowRect.top}px`;
            qaDrawer.style.width = `${qaWindowRect.width}px`;
            qaDrawer.style.height = `${qaWindowRect.height}px`;
          }

          const fabPosition = {
            x: Math.max(16, window.innerWidth - 104),
            y: Math.max(96, window.innerHeight - 176),
          };

          function updateFabPosition() {
            fab.style.left = `${fabPosition.x}px`;
            fab.style.top = `${fabPosition.y}px`;
          }

          function clampFabPosition() {
            const width = fab.offsetWidth || 72;
            const height = fab.offsetHeight || 72;
            fabPosition.x = Math.max(12, Math.min(window.innerWidth - width - 12, fabPosition.x));
            fabPosition.y = Math.max((header ? header.offsetHeight : 72) + 12, Math.min(window.innerHeight - height - 12, fabPosition.y));
          }

          function getGraphBridge() {
            try {
              return graphFrame.contentWindow && graphFrame.contentWindow.__TEKG_G6_BRIDGE
                ? graphFrame.contentWindow.__TEKG_G6_BRIDGE
                : null;
            } catch (_error) {
              return null;
            }
          }

          function getGraphState() {
            const bridge = getGraphBridge();
            if (!bridge || typeof bridge.getState !== 'function') return {};
            try {
              return bridge.getState() || {};
            } catch (_error) {
              return {};
            }
          }

          function injectQaBridge() {
            const qaWin = qaFrame.contentWindow;
            if (!qaWin) return;
            qaWin.__TEKG_G6_BRIDGE = {
              getState() {
                return getGraphState();
              },
              getMode() {
                return getGraphState().mode || 'tree';
              },
              getCurrentQuery() {
                return getGraphState().query || '';
              },
              getFixedView() {
                return !!getGraphState().fixedView;
              },
              getKeyNodeLevel() {
                return getGraphState().keyNodeLevel || 1;
              },
              getSelectedNode() {
                return getGraphState().selectedNode || null;
              },
              loadGraph(request) {
                const bridge = getGraphBridge();
                if (!bridge || typeof bridge.loadGraph !== 'function') {
                  return Promise.resolve(false);
                }
                return bridge.loadGraph(request);
              },
              applyAnswerGraph(result) {
                const bridge = getGraphBridge();
                if (!bridge || typeof bridge.applyAnswerGraph !== 'function') {
                  return Promise.resolve(false);
                }
                return bridge.applyAnswerGraph(result);
              },
              goBackGraph() {
                const bridge = getGraphBridge();
                if (!bridge || typeof bridge.goBackGraph !== 'function') {
                  return Promise.resolve(false);
                }
                return bridge.goBackGraph();
              },
              renderDefaultTree() {
                const bridge = getGraphBridge();
                if (!bridge || typeof bridge.renderDefaultTree !== 'function') {
                  return Promise.resolve(false);
                }
                return bridge.renderDefaultTree();
              },
            };
          }

          function relayStateToQa() {
            const qaWin = qaFrame.contentWindow;
            if (!qaLoaded || !qaWin) return;
            injectQaBridge();
            try {
              qaWin.dispatchEvent(new qaWin.CustomEvent('tekg:g6-state-change', { detail: getGraphState() }));
            } catch (_error) {}
          }

          function bindGraphRelay() {
            if (rendererMode !== 'g6') return;
            try {
              const graphWin = graphFrame.contentWindow;
              if (!graphWin || graphWin === boundGraphWindow) return;
              boundGraphWindow = graphWin;
              graphWin.addEventListener('tekg:g6-state-change', relayStateToQa);
            } catch (_error) {}
          }

          function ensureQaLoaded() {
            if (qaLoaded) {
              if (rendererMode === 'g6') {
                relayStateToQa();
              }
              return;
            }
            qaLoaded = true;
            qaFrame.src = qaFrame.dataset.src || '';
          }

          function applyOverlayState() {
            const immersive = document.fullscreenElement === stage;
            stage.classList.toggle('is-immersive', immersive);
            overlay.classList.toggle('is-open', !immersive && drawerOpen);
          }

          function updateImmersiveState() {
            applyOverlayState();
          }

          async function enterFullscreenPreview() {
            if (document.fullscreenElement === stage) return;
            try {
              await stage.requestFullscreen();
            } catch (_error) {}
          }

          function toggleOverlay() {
            if (stage.classList.contains('is-immersive')) return;
            drawerOpen = !drawerOpen;
            if (drawerOpen) {
              ensureQaLoaded();
            }
            applyOverlayState();
          }

          function clearBodyCursor() {
            document.body.style.cursor = '';
          }

          function setBodyCursor(value) {
            document.body.style.cursor = value;
          }

          fullscreenBtn.addEventListener('click', () => {
            enterFullscreenPreview();
          });

          document.addEventListener('fullscreenchange', () => {
            updateImmersiveState();
          });

          graphFrame.addEventListener('load', () => {
            if (rendererMode === 'g6') {
              setTimeout(bindGraphRelay, 250);
              setTimeout(relayStateToQa, 450);
            } else {
              setTimeout(() => resizeEmbeddedCytFrame(graphFrame, 55), 120);
              setTimeout(() => resizeEmbeddedCytFrame(graphFrame, 55), 420);
            }
          });

          qaFrame.addEventListener('load', () => {
            if (rendererMode === 'g6') {
              setTimeout(relayStateToQa, 250);
            } else {
              setTimeout(() => resizeEmbeddedCytFrame(qaFrame, 30), 120);
            }
          });

          fab.addEventListener('pointerdown', (event) => {
            movedDuringDrag = false;
            fabDragState = {
              pointerId: event.pointerId,
              startX: event.clientX,
              startY: event.clientY,
              baseX: fabPosition.x,
              baseY: fabPosition.y,
            };
            fab.setPointerCapture(event.pointerId);
          });

          fab.addEventListener('pointermove', (event) => {
            if (!fabDragState || fabDragState.pointerId !== event.pointerId) return;
            const dx = event.clientX - fabDragState.startX;
            const dy = event.clientY - fabDragState.startY;
            if (Math.abs(dx) > 4 || Math.abs(dy) > 4) movedDuringDrag = true;
            fabPosition.x = fabDragState.baseX + dx;
            fabPosition.y = fabDragState.baseY + dy;
            clampFabPosition();
            updateFabPosition();
          });

          fab.addEventListener('pointerup', (event) => {
            if (fabDragState && fabDragState.pointerId === event.pointerId) {
              fab.releasePointerCapture(event.pointerId);
              fabDragState = null;
              if (!movedDuringDrag) {
                toggleOverlay();
              }
            }
          });

          fab.addEventListener('pointercancel', (event) => {
            if (fabDragState && fabDragState.pointerId === event.pointerId) {
              try { fab.releasePointerCapture(event.pointerId); } catch (_error) {}
              fabDragState = null;
            }
          });

          qaDrawerDrag.addEventListener('pointerdown', (event) => {
            moveState = {
              pointerId: event.pointerId,
              startX: event.clientX,
              startY: event.clientY,
              startRect: { ...(qaWindowRect || getDefaultDrawerRect()) },
            };
            overlay.classList.add('is-dragging');
            setBodyCursor('move');
            qaDrawerDrag.setPointerCapture(event.pointerId);
            event.preventDefault();
          });

          qaDrawerDrag.addEventListener('pointermove', (event) => {
            if (!moveState || moveState.pointerId !== event.pointerId) return;
            const dx = event.clientX - moveState.startX;
            const dy = event.clientY - moveState.startY;
            qaWindowRect = clampDrawerRect({
              ...moveState.startRect,
              left: moveState.startRect.left + dx,
              top: moveState.startRect.top + dy,
            });
            applyDrawerRect();
          });

          function finishMove(event) {
            if (!moveState || moveState.pointerId !== event.pointerId) return;
            try {
              qaDrawerDrag.releasePointerCapture(event.pointerId);
            } catch (_error) {}
            moveState = null;
            overlay.classList.remove('is-dragging');
            clearBodyCursor();
          }

          qaDrawerDrag.addEventListener('pointerup', finishMove);
          qaDrawerDrag.addEventListener('pointercancel', finishMove);

          function getResizeCursor(handle) {
            if (handle === 'w' || handle === 'e') return 'ew-resize';
            if (handle === 's') return 'ns-resize';
            if (handle === 'nw' || handle === 'se') return 'nwse-resize';
            if (handle === 'ne' || handle === 'sw') return 'nesw-resize';
            return 'default';
          }

          function startResize(handle, element, event) {
            resizeState = {
              handle,
              pointerId: event.pointerId,
              startX: event.clientX,
              startY: event.clientY,
              startRect: { ...(qaWindowRect || getDefaultDrawerRect()) },
              element,
            };
            overlay.classList.add('is-resizing');
            setBodyCursor(getResizeCursor(handle));
            element.setPointerCapture(event.pointerId);
            event.preventDefault();
          }

          function updateResize(event) {
            if (!resizeState || resizeState.pointerId !== event.pointerId) return;
            const dx = event.clientX - resizeState.startX;
            const dy = event.clientY - resizeState.startY;
            const startRect = resizeState.startRect;
            const minWidth = Math.min(getMinDrawerWidth(), getMaxDrawerWidth());
            const maxWidth = getMaxDrawerWidth();
            const minHeight = Math.min(getMinDrawerHeight(), getMaxDrawerHeight());
            const maxHeight = getMaxDrawerHeight();
            const startRight = startRect.left + startRect.width;
            const startBottom = startRect.top + startRect.height;
            let left = startRect.left;
            let top = startRect.top;
            let width = startRect.width;
            let height = startRect.height;

            if (resizeState.handle.includes('w')) {
              left = Math.min(Math.max(12, startRect.left + dx), startRight - minWidth);
              width = startRight - left;
              if (width > maxWidth) {
                width = maxWidth;
                left = startRight - width;
              }
            }

            if (resizeState.handle.includes('e')) {
              width = Math.max(minWidth, Math.min(maxWidth, startRect.width + dx));
            }

            if (resizeState.handle.includes('n')) {
              top = Math.min(Math.max(12, startRect.top + dy), startBottom - minHeight);
              height = startBottom - top;
              if (height > maxHeight) {
                height = maxHeight;
                top = startBottom - height;
              }
            }

            if (resizeState.handle.includes('s')) {
              height = Math.max(minHeight, Math.min(maxHeight, startRect.height + dy));
            }

            qaWindowRect = clampDrawerRect({ left, top, width, height });
            applyDrawerRect();
          }

          qaDrawerResizeW.addEventListener('pointerdown', (event) => startResize('w', qaDrawerResizeW, event));
          qaDrawerResizeE.addEventListener('pointerdown', (event) => startResize('e', qaDrawerResizeE, event));
          qaDrawerResizeS.addEventListener('pointerdown', (event) => startResize('s', qaDrawerResizeS, event));
          qaDrawerResizeNW.addEventListener('pointerdown', (event) => startResize('nw', qaDrawerResizeNW, event));
          qaDrawerResizeNE.addEventListener('pointerdown', (event) => startResize('ne', qaDrawerResizeNE, event));
          qaDrawerResizeSW.addEventListener('pointerdown', (event) => startResize('sw', qaDrawerResizeSW, event));
          qaDrawerResizeSE.addEventListener('pointerdown', (event) => startResize('se', qaDrawerResizeSE, event));

          qaDrawerResizeW.addEventListener('pointermove', updateResize);
          qaDrawerResizeE.addEventListener('pointermove', updateResize);
          qaDrawerResizeS.addEventListener('pointermove', updateResize);
          qaDrawerResizeNW.addEventListener('pointermove', updateResize);
          qaDrawerResizeNE.addEventListener('pointermove', updateResize);
          qaDrawerResizeSW.addEventListener('pointermove', updateResize);
          qaDrawerResizeSE.addEventListener('pointermove', updateResize);

          function finishResize(event) {
            if (!resizeState || resizeState.pointerId !== event.pointerId) return;
            try {
              resizeState.element.releasePointerCapture(event.pointerId);
            } catch (_error) {}
            resizeState = null;
            overlay.classList.remove('is-resizing');
            clearBodyCursor();
          }

          qaDrawerResizeW.addEventListener('pointerup', finishResize);
          qaDrawerResizeW.addEventListener('pointercancel', finishResize);
          qaDrawerResizeE.addEventListener('pointerup', finishResize);
          qaDrawerResizeE.addEventListener('pointercancel', finishResize);
          qaDrawerResizeS.addEventListener('pointerup', finishResize);
          qaDrawerResizeS.addEventListener('pointercancel', finishResize);
          qaDrawerResizeNW.addEventListener('pointerup', finishResize);
          qaDrawerResizeNW.addEventListener('pointercancel', finishResize);
          qaDrawerResizeNE.addEventListener('pointerup', finishResize);
          qaDrawerResizeNE.addEventListener('pointercancel', finishResize);
          qaDrawerResizeSW.addEventListener('pointerup', finishResize);
          qaDrawerResizeSW.addEventListener('pointercancel', finishResize);
          qaDrawerResizeSE.addEventListener('pointerup', finishResize);
          qaDrawerResizeSE.addEventListener('pointercancel', finishResize);

          window.addEventListener('resize', () => {
            clampFabPosition();
            updateFabPosition();
            qaWindowRect = clampDrawerRect(qaWindowRect || getDefaultDrawerRect());
            applyDrawerRect();
          });

          qaWindowRect = getDefaultDrawerRect();
          applyDrawerRect();
          clampFabPosition();
          updateFabPosition();
          ensureQaLoaded();
          updateImmersiveState();
        })();
      </script>
    </main>
  </div>
</body>
</html>
