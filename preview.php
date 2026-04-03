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

if ($siteRenderer === 'g6') {
    $graphSrc = site_url_with_state('/TE-/index_g6.html', $siteLang, 'g6', array_merge($queryParams, ['embed' => 'preview-graphonly']));
    $qaSrc = site_url_with_state('/TE-/index_g6.html', $siteLang, 'g6', array_merge($queryParams, ['embed' => 'qa-overlay']));
} else {
    $graphSrc = site_url_with_state('/TE-/index_demo.html', $siteLang, 'cytoscape', array_merge($queryParams, ['embed' => 'preview-graphonly']));
    $qaSrc = site_url_with_state('/TE-/index_demo.html', $siteLang, 'cytoscape', array_merge($queryParams, ['embed' => 'qa-overlay']));
}
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

        .qa-overlay-layer {
          position: absolute;
          inset: 0;
          pointer-events: none;
          z-index: 20;
        }

        .qa-drawer {
          position: absolute;
          top: 18px;
          right: 18px;
          bottom: 18px;
          width: min(430px, calc(100vw - 36px));
          background: #ffffff;
          border: 1px solid #dbe7f3;
          border-radius: 20px;
          box-shadow: 0 18px 42px rgba(15, 23, 42, 0.16);
          overflow: hidden;
          transform: translateX(calc(100% + 24px));
          transition: transform 0.22s ease;
          pointer-events: auto;
        }

        .qa-overlay-layer.is-open .qa-drawer {
          transform: translateX(0);
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

      <section class="preview-stage" data-renderer="<?= htmlspecialchars($siteRenderer, ENT_QUOTES, 'UTF-8') ?>">
        <iframe
          id="preview-graph-frame"
          class="preview-graph-frame"
          src="<?= htmlspecialchars($graphSrc, ENT_QUOTES, 'UTF-8') ?>"
          title="TE-KG preview graph"
        ></iframe>

        <div class="qa-overlay-layer" id="qaOverlay">
          <div class="qa-drawer">
            <iframe
              id="preview-qa-frame"
              title="TE-KG QA overlay"
              data-src="<?= htmlspecialchars($qaSrc, ENT_QUOTES, 'UTF-8') ?>"
            ></iframe>
          </div>

          <button class="qa-fab" id="qaFab" type="button" aria-label="Open QA assistant">
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
          const graphFrame = document.getElementById('preview-graph-frame');
          const qaFrame = document.getElementById('preview-qa-frame');
          const overlay = document.getElementById('qaOverlay');
          const fab = document.getElementById('qaFab');
          if (!graphFrame || !qaFrame || !overlay || !fab) return;

          let qaLoaded = false;
          let boundGraphWindow = null;
          let dragState = null;
          let movedDuringDrag = false;

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

          function toggleOverlay() {
            const willOpen = !overlay.classList.contains('is-open');
            overlay.classList.toggle('is-open', willOpen);
            if (willOpen) {
              ensureQaLoaded();
            }
          }

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

          window.addEventListener('resize', () => {
            clampFabPosition();
            updateFabPosition();
          });

          clampFabPosition();
          updateFabPosition();
        })();
      </script>
    </main>
  </div>
</body>
</html>
