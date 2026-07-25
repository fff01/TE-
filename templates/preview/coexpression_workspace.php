        <div class="main preview-graph-workspace preview-coexpression-workspace" id="previewCoexpressionWorkspace" aria-hidden="true" hidden>
          <section class="panel preview-graph-panel" aria-label="TE-KG co-expression graph workspace">
            <div class="toolbar preview-graph-toolbar">
              <div class="search preview-entity-search">
                <div class="preview-entity-control">
                  <select id="coexpression-search-type" aria-label="Co-expression search entity type">
                    <option value="TE" selected>TE</option>
                  </select>
                  <div class="te-autocomplete" data-te-autocomplete-root data-te-autocomplete-source="coexpression-catalog">
                    <input id="coexpression-te-search" type="text" autocomplete="off" placeholder="Select a TE" aria-label="Co-expression TE" data-te-autocomplete disabled>
                    <button class="te-autocomplete-toggle" type="button" aria-label="Show Co-expression TE names" aria-expanded="false" data-te-autocomplete-toggle></button>
                    <div class="te-autocomplete-menu" data-te-autocomplete-menu hidden></div>
                  </div>
                  <button id="coexpression-load" class="preview-search-submit" type="button" disabled>Search</button>
                </div>
              </div>
              <label class="coexpression-context-control" for="coexpression-context-select">
                <span>Context</span>
                <select id="coexpression-context-select" disabled>
                  <option value="">Select context</option>
                </select>
              </label>
              <button id="coexpression-expression-layer" class="is-toggle is-active" type="button" aria-pressed="true">
                <span id="coexpression-expression-layer-text">Expression activity: On</span>
              </button>
              <div id="coexpression-export-menu-wrap" class="graph-export-menu-wrap">
                <button id="coexpression-export-menu-toggle" class="graph-export-action" type="button" aria-haspopup="true" aria-expanded="false" disabled>
                  Export
                </button>
                <div id="coexpression-export-menu" class="graph-export-menu" role="menu" hidden>
                  <button id="coexpression-export-csv" type="button" role="menuitem">CSV</button>
                  <button id="coexpression-export-png" type="button" role="menuitem">PNG</button>
                </div>
              </div>
            </div>
            <div class="g6-surface-stack preview-g6-surface-stack">
              <div id="coexpression-iframe-host" class="coexpression-iframe-host"></div>
              <div id="coexpression-preloader" class="graph-preloader" aria-hidden="true">
                <div class="graph-preloader-inner">
                  <div class="graph-preloader-icon" aria-hidden="true">
                    <span></span>
                    <span></span>
                  </div>
                  <div id="coexpression-preloader-label" class="graph-preloader-label">Loading co-expression network...</div>
                </div>
              </div>
              <div id="coexpression-state" class="coexpression-state" role="status" aria-live="polite" hidden>
                <span id="coexpression-state-message"></span>
                <button id="coexpression-retry" type="button" hidden>Retry</button>
              </div>
              <aside id="coexpression-legend" class="graph-legend-panel coexpression-legend" aria-label="Co-expression legend">
                <div class="graph-legend-title">Co-expression Legend</div>
                <label class="graph-legend-item">
                  <input id="coexpression-show-te" class="graph-legend-check" type="checkbox" checked>
                  <span class="graph-legend-swatch coexpression-legend-te" aria-hidden="true"></span>
                  <span class="graph-legend-text">TE nodes</span>
                </label>
                <label class="graph-legend-item">
                  <input id="coexpression-show-gene" class="graph-legend-check" type="checkbox" checked>
                  <span class="graph-legend-swatch coexpression-legend-gene" aria-hidden="true"></span>
                  <span class="graph-legend-text">Gene nodes</span>
                </label>
                <div class="graph-legend-item coexpression-legend-static">
                  <span class="coexpression-hub-ring" aria-hidden="true"></span>
                  <span class="graph-legend-text">Module hub</span>
                </div>
                <div class="graph-legend-item coexpression-legend-static">
                  <span class="coexpression-activity-ring" aria-hidden="true"></span>
                  <span class="graph-legend-text">Relative TE expression</span>
                </div>
                <label class="coexpression-edge-scope-control" for="coexpression-edge-scope">
                  <span>Edges</span>
                  <select id="coexpression-edge-scope">
                    <option value="center">Center edges</option>
                    <option value="all" selected>All selected edges</option>
                  </select>
                </label>
                <div class="graph-legend-footer">
                  <button id="coexpression-legend-apply" class="graph-legend-apply" type="button" disabled>Apply</button>
                </div>
              </aside>
              <div id="coexpression-correlation-notice" class="coexpression-correlation-notice">
                <strong id="coexpression-method-summary">Spearman correlation</strong>
                <span>Association only; correlation and expression activity do not imply causation.</span>
              </div>
            </div>
            <div class="detail" id="coexpression-node-details">Preparing co-expression workspace...</div>
          </section>
        </div>
