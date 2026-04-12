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
$graphSrc = site_url_with_state('/TE-/index_g6.html', $siteLang, 'g6', array_merge($queryParams, ['embed' => 'preview-graphonly', 'v' => (string)$g6PreviewVersion]));
$qaSrc = site_url_with_state('/TE-/index_g6.html', $siteLang, 'g6', array_merge($queryParams, ['embed' => 'qa-overlay', 'v' => (string)$g6PreviewVersion]));
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
          right: 18px;
          bottom: 18px;
          width: 430px;
          min-width: 340px;
          max-width: calc(100vw - 72px);
          background: #ffffff;
          border: 1px solid #dbe7f3;
          border-radius: 20px;
          box-shadow: 0 18px 42px rgba(15, 23, 42, 0.16);
          overflow: hidden;
          transform: translateX(calc(100% + 24px));
          transition: transform 0.22s ease;
          pointer-events: auto;
          user-select: none;
        }

        .qa-overlay-layer.is-open .qa-drawer {
          transform: translateX(0);
        }

        .qa-overlay-layer.is-resizing .qa-drawer {
          transition: none;
        }

        .qa-overlay-layer.is-resizing,
        .qa-overlay-layer.is-resizing * {
          cursor: ew-resize !important;
          user-select: none !important;
        }

        .qa-drawer-resize {
          position: absolute;
          top: 0;
          left: 0;
          bottom: 0;
          width: 14px;
          padding: 0;
          border: 0;
          background: linear-gradient(90deg, rgba(143, 178, 234, 0.22) 0%, rgba(143, 178, 234, 0.06) 45%, rgba(143, 178, 234, 0) 100%);
          cursor: ew-resize;
          z-index: 2;
        }

        .qa-drawer-resize::after {
          content: '';
          position: absolute;
          top: 50%;
          left: 4px;
          transform: translateY(-50%);
          width: 4px;
          height: 56px;
          border-radius: 999px;
          background: rgba(120, 146, 190, 0.42);
        }

        .qa-drawer-resize:hover::after,
        .qa-overlay-layer.is-resizing .qa-drawer-resize::after {
          background: rgba(79, 123, 214, 0.68);
        }

        .qa-drawer iframe {
          width: 100%;
          height: 100%;
          border: 0;
          display: block;
          background: #fff;
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
            right: 10px;
            bottom: 10px;
            width: min(420px, calc(100vw - 20px));
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
            <button class="qa-drawer-resize" id="qaDrawerResize" type="button" aria-label="Resize QA assistant"></button>
            <iframe
              id="preview-qa-frame"
              title="TE-KG QA overlay"
              data-src="<?= htmlspecialchars($qaSrc, ENT_QUOTES, 'UTF-8') ?>"
            ></iframe>
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
          const qaDrawerResize = document.getElementById('qaDrawerResize');
          const fab = document.getElementById('qaFab');
          if (!stage || !fullscreenBtn || !graphFrame || !qaFrame || !overlay || !qaDrawer || !qaDrawerResize || !fab) return;

          let qaLoaded = false;
          let drawerOpen = true;
          let qaDrawerWidth = 430;
          let boundGraphWindow = null;
          let dragState = null;
          let movedDuringDrag = false;
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


          function getMinDrawerWidth() {
            return 340;
          }

          function getMaxDrawerWidth() {
            return Math.max(420, Math.min(820, window.innerWidth - 56));
          }

          function clampDrawerWidth(width) {
            return Math.max(getMinDrawerWidth(), Math.min(getMaxDrawerWidth(), width));
          }

          function applyDrawerWidth() {
            qaDrawerWidth = clampDrawerWidth(qaDrawerWidth);
            qaDrawer.style.width = `${qaDrawerWidth}px`;
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
            dragState = {
              pointerId: event.pointerId,
              startX: event.clientX,
              startY: event.clientY,
              baseX: fabPosition.x,
              baseY: fabPosition.y,
            };
            fab.setPointerCapture(event.pointerId);
          });

          fab.addEventListener('pointermove', (event) => {
            if (!dragState || dragState.pointerId !== event.pointerId) return;
            const dx = event.clientX - dragState.startX;
            const dy = event.clientY - dragState.startY;
            if (Math.abs(dx) > 4 || Math.abs(dy) > 4) movedDuringDrag = true;
            fabPosition.x = dragState.baseX + dx;
            fabPosition.y = dragState.baseY + dy;
            clampFabPosition();
            updateFabPosition();
          });

          fab.addEventListener('pointerup', (event) => {
            if (dragState && dragState.pointerId === event.pointerId) {
              fab.releasePointerCapture(event.pointerId);
              dragState = null;
              if (!movedDuringDrag) {
                toggleOverlay();
              }
            }
          });

          fab.addEventListener('pointercancel', (event) => {
            if (dragState && dragState.pointerId === event.pointerId) {
              try { fab.releasePointerCapture(event.pointerId); } catch (_error) {}
              dragState = null;
            }
          });

          qaDrawerResize.addEventListener('pointerdown', (event) => {
            resizeState = {
              pointerId: event.pointerId,
              startX: event.clientX,
              startWidth: qaDrawer.getBoundingClientRect().width || qaDrawerWidth,
            };
            overlay.classList.add('is-resizing');
            qaDrawerResize.setPointerCapture(event.pointerId);
            event.preventDefault();
          });

          qaDrawerResize.addEventListener('pointermove', (event) => {
            if (!resizeState || resizeState.pointerId !== event.pointerId) return;
            const dx = event.clientX - resizeState.startX;
            qaDrawerWidth = clampDrawerWidth(resizeState.startWidth - dx);
            applyDrawerWidth();
          });

          function finishResize(event) {
            if (!resizeState || resizeState.pointerId !== event.pointerId) return;
            try {
              qaDrawerResize.releasePointerCapture(event.pointerId);
            } catch (_error) {}
            resizeState = null;
            overlay.classList.remove('is-resizing');
          }

          qaDrawerResize.addEventListener('pointerup', finishResize);
          qaDrawerResize.addEventListener('pointercancel', finishResize);

          window.addEventListener('resize', () => {
            clampFabPosition();
            updateFabPosition();
            applyDrawerWidth();
          });

          qaDrawerWidth = Math.round(qaDrawer.getBoundingClientRect().width) || qaDrawerWidth;
          applyDrawerWidth();
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
