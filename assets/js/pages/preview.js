(() => {
          const header = document.getElementById('protoHeader');
          const stage = document.getElementById('previewStage');
          const rendererMode = String((stage && stage.dataset.renderer) || 'g6');
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
