(function () {
  'use strict';

  const contract = window.__TEKG_COEXPRESSION_CONTRACT;
  const paths = window.__TEKG_PATHS;
  const workspace = document.getElementById('previewCoexpressionWorkspace');
  if (!contract || !paths || !workspace) return;

  const CACHE_LIMIT = 6;
  const els = {
    te: document.getElementById('coexpression-te-search'),
    context: document.getElementById('coexpression-context-select'),
    load: document.getElementById('coexpression-load'),
    frameHost: document.getElementById('coexpression-iframe-host'),
    preloader: document.getElementById('coexpression-preloader'),
    preloaderLabel: document.getElementById('coexpression-preloader-label'),
    state: document.getElementById('coexpression-state'),
    stateMessage: document.getElementById('coexpression-state-message'),
    retry: document.getElementById('coexpression-retry'),
    detail: document.getElementById('coexpression-node-details'),
    expression: document.getElementById('coexpression-expression-layer'),
    expressionText: document.getElementById('coexpression-expression-layer-text'),
    showTe: document.getElementById('coexpression-show-te'),
    showGene: document.getElementById('coexpression-show-gene'),
    edgeScope: document.getElementById('coexpression-edge-scope'),
    legendApply: document.getElementById('coexpression-legend-apply'),
    methodSummary: document.getElementById('coexpression-method-summary'),
    exportWrap: document.getElementById('coexpression-export-menu-wrap'),
    exportToggle: document.getElementById('coexpression-export-menu-toggle'),
    exportMenu: document.getElementById('coexpression-export-menu'),
    exportCsv: document.getElementById('coexpression-export-csv'),
    exportPng: document.getElementById('coexpression-export-png'),
  };

  let catalogPromise = null;
  let catalog = null;
  let frame = null;
  let frameBridgePromise = null;
  let frameIdentity = 0;
  let requestEpoch = 0;
  let abortController = null;
  let currentState = 'idle';
  let currentSelection = null;
  let currentNetwork = null;
  let currentAvailableContexts = [];
  let currentNonblank = false;
  let visible = false;
  let renderQueue = Promise.resolve();
  let expressionEnabled = true;
  let expressionEpoch = 0;
  let expressionAbortController = null;
  let currentExpressionOverlay = { enabled: false, context: 'off', records: {}, min_value: 0, max_value: 0 };
  let currentViewOptions = { showTE: true, showGene: true, edgeScope: 'all' };
  const networkCache = new Map();
  const expressionSummaryCache = new Map();
  const requestCounts = { catalog: 0, network: 0 };

  function setWorkspaceVisible(next) {
    visible = next === true;
    workspace.hidden = !visible;
    workspace.setAttribute('aria-hidden', visible ? 'false' : 'true');
  }

  function setState(state, message = '') {
    currentState = state;
    const loading = state === 'loading-catalog'
      || state === 'loading-network'
      || state === 'loading-iframe'
      || state === 'rendering';
    els.preloader.classList.toggle('is-visible', loading);
    els.preloader.setAttribute('aria-hidden', loading ? 'false' : 'true');
    if (message) els.preloaderLabel.textContent = message;
    const showMessage = state === 'awaiting-selection'
      || state === 'unavailable'
      || state === 'empty'
      || state === 'error';
    els.state.hidden = !showMessage;
    els.stateMessage.textContent = showMessage ? message : '';
    els.retry.hidden = state !== 'error';
    els.exportToggle.disabled = state !== 'ready';
    if (state !== 'ready') closeExportMenu();
  }

  async function fetchPayload(url, signal) {
    const response = await fetch(url, {
      signal,
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
      cache: 'no-store',
    });
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload || payload.ok !== true) {
      const error = new Error(payload?.error?.message || `Request failed with HTTP ${response.status}.`);
      error.code = payload?.error?.code || 'request_failed';
      error.availableContexts = Array.isArray(payload?.error?.available_contexts)
        ? payload.error.available_contexts.slice()
        : [];
      throw error;
    }
    return payload;
  }

  function catalogUrl() {
    return `${paths.apiUrl('coexpression.php')}?action=catalog`;
  }

  function networkUrl(te, context) {
    const url = new URL(paths.apiUrl('coexpression.php'), window.location.origin);
    url.searchParams.set('action', 'network');
    url.searchParams.set('te', te);
    url.searchParams.set('context', context);
    return url.toString();
  }

  function syncExpressionButton() {
    els.expression.classList.toggle('is-active', expressionEnabled);
    els.expression.setAttribute('aria-pressed', expressionEnabled ? 'true' : 'false');
    els.expressionText.textContent = `Expression activity: ${expressionEnabled ? 'On' : 'Off'}`;
  }

  function syncMethodSummary() {
    if (!catalog) return;
    const threshold = Number(catalog.thresholds?.absolute_correlation ?? catalog.thresholds?.abs_correlation ?? 0.4);
    const fdr = Number(catalog.thresholds?.fdr ?? catalog.thresholds?.fdr_threshold ?? 0.05);
    els.methodSummary.textContent = `${catalog.method || 'Spearman correlation'} · r ≥ ${threshold || 0.4} · FDR ≤ ${fdr || 0.05}`;
  }

  function expressionNames(network) {
    return [...new Set((network?.nodes || [])
      .filter((node) => node.kind === 'te')
      .map((node) => String(node.label || node.id || '').trim())
      .filter(Boolean))].slice(0, 80);
  }

  function expressionRecordMap(records) {
    const mapped = {};
    for (const record of Array.isArray(records) ? records : []) {
      if (!record || typeof record !== 'object') continue;
      for (const key of [record.requested_name, record.te_name]) {
        const normalized = String(key || '').trim().toLowerCase();
        if (normalized) mapped[normalized] = record;
      }
    }
    return mapped;
  }

  function buildExpressionOverlay(network, records) {
    const context = String(network?.selection?.context || 'off');
    const names = expressionNames(network);
    const mapped = expressionRecordMap(records);
    const values = names.map((name) => {
      const record = mapped[name.toLowerCase()];
      const value = Number(record?.[context]?.median_value);
      return record?.available === true && Number.isFinite(value) ? value : null;
    }).filter((value) => value !== null);
    return {
      enabled: expressionEnabled,
      context,
      context_label: context.replaceAll('_', ' '),
      records: mapped,
      min_value: values.length ? Math.min(...values) : 0,
      max_value: values.length ? Math.max(...values) : 0,
      evidence_boundary: 'Expression values provide activity context only and do not prove causal graph relations.',
    };
  }

  async function fetchExpressionSummaries(network, signal) {
    const names = expressionNames(network);
    if (!names.length) return [];
    const context = String(network?.selection?.context || '');
    const key = `${network?.version || catalog?.version || ''}|${context}|${names.slice().sort((a, b) => a.localeCompare(b)).join('|')}`;
    if (expressionSummaryCache.has(key)) {
      const cached = expressionSummaryCache.get(key);
      expressionSummaryCache.delete(key);
      expressionSummaryCache.set(key, cached);
      return cached;
    }
    const response = await fetch(paths.apiUrl('graph_expression.php'), {
      method: 'POST',
      signal,
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ te_names: names, context }),
    });
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload || payload.ok !== true) {
      throw new Error(payload?.error || 'Expression activity request failed.');
    }
    const records = Array.isArray(payload.records) ? payload.records : [];
    expressionSummaryCache.set(key, records);
    while (expressionSummaryCache.size > CACHE_LIMIT) {
      expressionSummaryCache.delete(expressionSummaryCache.keys().next().value);
    }
    return records;
  }

  async function expressionOverlayForNetwork(network, epoch) {
    if (!expressionEnabled) return buildExpressionOverlay(network, []);
    if (expressionAbortController) expressionAbortController.abort();
    expressionAbortController = new AbortController();
    try {
      const records = await fetchExpressionSummaries(network, expressionAbortController.signal);
      if (epoch !== expressionEpoch) return null;
      return buildExpressionOverlay(network, records);
    } finally {
      if (epoch === expressionEpoch) expressionAbortController = null;
    }
  }

  function enableTeInput() {
    els.te.disabled = false;
    els.load.disabled = false;
  }

  function itemForTe(te) {
    const wanted = String(te || '').trim().toLowerCase();
    return catalog?.items.find((item) => item.te.toLowerCase() === wanted) || null;
  }

  function populateContextOptions(item, preferredContext = '') {
    const available = new Set(item ? item.availableContexts : []);
    const fragment = document.createDocumentFragment();
    catalog.contexts.forEach((context) => {
      const option = document.createElement('option');
      option.value = context.id;
      option.textContent = context.label;
      option.disabled = !available.has(context.id);
      fragment.appendChild(option);
    });
    els.context.replaceChildren(fragment);
    const nextContext = available.has(preferredContext)
      ? preferredContext
      : (available.has(catalog.defaultSelection.context) ? catalog.defaultSelection.context : [...available][0] || '');
    els.context.value = nextContext;
    els.context.disabled = !item || !nextContext;
    currentAvailableContexts = item ? item.availableContexts.slice() : [];
    return nextContext;
  }

  function loadCatalog() {
    if (catalogPromise) return catalogPromise;
    requestCounts.catalog += 1;
    catalogPromise = fetchPayload(catalogUrl())
      .then((payload) => contract.normalizeCatalog(payload))
      .then((normalized) => {
        catalog = normalized;
        enableTeInput();
        syncMethodSummary();
        return normalized;
      });
    return catalogPromise;
  }

  function cacheGet(key) {
    if (!networkCache.has(key)) return null;
    const value = networkCache.get(key);
    networkCache.delete(key);
    networkCache.set(key, value);
    return value;
  }

  function cacheSet(key, value) {
    if (networkCache.has(key)) networkCache.delete(key);
    networkCache.set(key, value);
    while (networkCache.size > CACHE_LIMIT) {
      networkCache.delete(networkCache.keys().next().value);
    }
  }

  function waitForBridge(maxAttempts = 80, delayMs = 50) {
    if (frameBridgePromise) return frameBridgePromise;
    frameBridgePromise = new Promise((resolve, reject) => {
      let attempts = 0;
      const check = () => {
        attempts += 1;
        try {
          const bridge = frame?.contentWindow?.__TEKG_COEXPRESSION_EMBED;
          if (bridge && typeof bridge.renderNetwork === 'function') {
            resolve(bridge);
            return;
          }
        } catch (_error) {}
        if (attempts >= maxAttempts) {
          reject(new Error('Co-expression iframe bridge is unavailable.'));
          return;
        }
        window.setTimeout(check, delayMs);
      };
      check();
    });
    return frameBridgePromise;
  }

  function enqueueRender(bridge, network, epoch) {
    const queued = renderQueue
      .catch(() => undefined)
      .then(async () => {
        if (epoch !== requestEpoch) return { cancelled: true, report: null };
        const report = await bridge.renderNetwork(network);
        return { cancelled: false, report };
      });
    renderQueue = queued.then(() => undefined, () => undefined);
    return queued;
  }

  function ensureFrame() {
    if (frame) return frame;
    frame = document.createElement('iframe');
    frame.id = 'coexpression-graph-frame';
    frame.title = 'TE-KG Co-expression graph';
    frame.setAttribute('scrolling', 'no');
    frame.src = paths.assetsUrl('html/preview_coexpression_embed.html');
    els.frameHost.replaceChildren(frame);
    frameIdentity += 1;
    return frame;
  }

  function unavailableSelection(item, requestedContext) {
    const labels = item.availableContexts
      .map((id) => catalog.contexts.find((context) => context.id === id)?.label || id)
      .join(', ');
    currentSelection = { te: item.te, context: requestedContext };
    currentAvailableContexts = item.availableContexts.slice();
    currentNonblank = false;
    populateContextOptions(item, item.availableContexts[0]);
    els.te.value = item.te;
    setState('unavailable', `${item.te} is unavailable for this context. Available: ${labels}.`);
  }

  function unavailableTe(requestedTe, requestedContext = '') {
    const te = String(requestedTe || '').trim();
    const option = document.createElement('option');
    option.value = '';
    option.textContent = 'No context available';
    els.context.replaceChildren(option);
    els.context.disabled = true;
    els.te.value = te;
    currentSelection = { te, context: String(requestedContext || '').trim() };
    currentAvailableContexts = [];
    currentNetwork = null;
    currentNonblank = false;
    setState('unavailable', `No co-expression data is available for ${te}.`);
  }

  function awaitTeSelection() {
    const option = document.createElement('option');
    option.value = '';
    option.textContent = 'Select a TE first';
    els.context.replaceChildren(option);
    els.context.disabled = true;
    els.te.value = '';
    currentSelection = null;
    currentAvailableContexts = [];
    currentNetwork = null;
    currentNonblank = false;
    setState('awaiting-selection', 'Select a TE to explore its co-expression network.');
  }

  async function activate(options = {}) {
    const epoch = ++requestEpoch;
    const previous = {
      state: currentState,
      selection: currentSelection ? { ...currentSelection } : null,
      network: currentNetwork,
      availableContexts: currentAvailableContexts.slice(),
      nonblank: currentNonblank,
      message: els.stateMessage.textContent || els.preloaderLabel.textContent || '',
    };
    const restorePrevious = () => {
      currentSelection = previous.selection;
      currentNetwork = previous.network;
      currentAvailableContexts = previous.availableContexts.slice();
      currentNonblank = previous.nonblank;
      if (previous.selection && catalog) {
        const item = itemForTe(previous.selection.te);
        if (item) {
          els.te.value = item.te;
          populateContextOptions(item, previous.selection.context);
        }
      }
      setState(previous.state, previous.message);
    };
    if (abortController) abortController.abort();
    abortController = null;
    setWorkspaceVisible(true);
    setState('loading-catalog', 'Loading co-expression catalog...');

    try {
      const nextCatalog = await loadCatalog();
      if (epoch !== requestEpoch) {
        if (!visible) restorePrevious();
        return null;
      }
      const requestedTe = String(options.te || '').trim();
      if (!requestedTe) {
        awaitTeSelection();
        return null;
      }
      const item = itemForTe(requestedTe);
      if (!item) {
        unavailableTe(requestedTe, options.context);
        return null;
      }

      const explicitlyRequestedContext = String(options.context || '').trim();
      if (explicitlyRequestedContext && !item.availableContexts.includes(explicitlyRequestedContext)) {
        unavailableSelection(item, explicitlyRequestedContext);
        return null;
      }

      const context = populateContextOptions(item, explicitlyRequestedContext);
      els.te.value = item.te;
      currentSelection = { te: item.te, context };
      currentAvailableContexts = item.availableContexts.slice();
      const key = `${nextCatalog.version}\u0000${item.te}\u0000${context}`;
      let network = cacheGet(key);

      if (!network) {
        setState('loading-network', `Loading ${item.te} in ${context.replaceAll('_', ' ')}...`);
        abortController = new AbortController();
        requestCounts.network += 1;
        const payload = await fetchPayload(networkUrl(item.te, context), abortController.signal);
        if (epoch !== requestEpoch) {
          if (!visible) restorePrevious();
          return null;
        }
        network = contract.normalizeNetwork(payload);
        cacheSet(key, network);
      }

      if (epoch !== requestEpoch) {
        if (!visible) restorePrevious();
        return null;
      }
      if (network.nodes.length === 0) {
        currentNetwork = network;
        currentNonblank = false;
        setState('empty', 'This Co-expression network has no visible nodes.');
        return network;
      }

      const nextExpressionEpoch = ++expressionEpoch;
      let expressionOverlay = buildExpressionOverlay(network, []);
      try {
        expressionOverlay = await expressionOverlayForNetwork(network, nextExpressionEpoch) || expressionOverlay;
      } catch (error) {
        if (error?.name !== 'AbortError') {
          console.warn('Co-expression Expression activity is unavailable:', error);
        }
      }
      if (epoch !== requestEpoch) return null;
      currentExpressionOverlay = expressionOverlay;

      setState('loading-iframe', 'Preparing Co-expression graph surface...');
      currentNonblank = false;
      ensureFrame();
      const bridge = await waitForBridge();
      if (epoch !== requestEpoch) {
        if (!visible) restorePrevious();
        return null;
      }
      await bridge.setVisible(true);
      if (typeof bridge.setExpressionOverlay === 'function') {
        await bridge.setExpressionOverlay(currentExpressionOverlay);
      }
      setState('rendering', `Rendering ${item.te} co-expression network...`);
      const renderResult = await enqueueRender(bridge, network, epoch);
      if (renderResult.cancelled) {
        if (!visible) restorePrevious();
        return null;
      }
      const report = renderResult.report;
      if (epoch !== requestEpoch) {
        if (!visible) {
          currentNetwork = network;
          currentSelection = { te: item.te, context };
          currentNonblank = report?.nonblank === true;
          setState(report?.nodeCount > 0 ? 'ready' : 'empty');
          await bridge.setVisible(false);
        }
        return null;
      }
      currentNetwork = network;
      currentSelection = { te: item.te, context };
      currentNonblank = report?.nonblank === true;
      setState(
        report?.nodeCount > 0 ? 'ready' : 'empty',
        report?.nodeCount > 0 ? '' : 'This Co-expression network has no visible nodes.',
      );
      return network;
    } catch (error) {
      if (error?.name === 'AbortError' || epoch !== requestEpoch) {
        if (!visible) restorePrevious();
        return null;
      }
      currentNonblank = false;
      setState('error', error && error.message ? error.message : 'Unable to load Co-expression network.');
      return null;
    }
  }

  function deactivate() {
    requestEpoch += 1;
    expressionEpoch += 1;
    if (abortController) abortController.abort();
    abortController = null;
    if (expressionAbortController) expressionAbortController.abort();
    expressionAbortController = null;
    setWorkspaceVisible(false);
    if (frame?.contentWindow?.__TEKG_COEXPRESSION_EMBED) {
      return frame.contentWindow.__TEKG_COEXPRESSION_EMBED.setVisible(false);
    }
    return Promise.resolve();
  }

  function resume() {
    setWorkspaceVisible(true);
    const bridge = frame?.contentWindow?.__TEKG_COEXPRESSION_EMBED;
    if (bridge && typeof bridge.setVisible === 'function') {
      return bridge.setVisible(true).then(() => currentNetwork);
    }
    return Promise.resolve(currentNetwork);
  }

  async function setExpressionEnabled(enabled) {
    expressionEnabled = enabled === true;
    syncExpressionButton();
    const epoch = ++expressionEpoch;
    if (expressionAbortController) expressionAbortController.abort();
    expressionAbortController = null;
    if (!currentNetwork) return false;
    let overlay = buildExpressionOverlay(currentNetwork, []);
    try {
      overlay = await expressionOverlayForNetwork(currentNetwork, epoch) || overlay;
    } catch (error) {
      if (error?.name !== 'AbortError') {
        console.warn('Co-expression Expression activity is unavailable:', error);
      }
    }
    if (epoch !== expressionEpoch) return false;
    currentExpressionOverlay = overlay;
    const bridge = frame?.contentWindow?.__TEKG_COEXPRESSION_EMBED;
    if (bridge && typeof bridge.setExpressionOverlay === 'function') {
      await bridge.setExpressionOverlay(overlay);
    }
    return true;
  }

  async function setViewOptions(options = {}) {
    currentViewOptions = {
      showTE: options.showTE !== false,
      showGene: options.showGene !== false,
      edgeScope: options.edgeScope === 'center' ? 'center' : 'all',
    };
    const bridge = frame?.contentWindow?.__TEKG_COEXPRESSION_EMBED;
    if (bridge && typeof bridge.setViewOptions === 'function') {
      setState('rendering', 'Applying Co-expression filters...');
      const report = await bridge.setViewOptions(currentViewOptions);
      currentNonblank = report?.nonblank === true;
      setState(report?.nodeCount > 0 ? 'ready' : 'empty', report?.nodeCount > 0 ? '' : 'No nodes match the current filters.');
    }
    els.legendApply.disabled = true;
    return { ...currentViewOptions };
  }

  function csvCell(value) {
    const text = value === null || value === undefined ? '' : String(value);
    return /[",\r\n]/.test(text) ? `"${text.replaceAll('"', '""')}"` : text;
  }

  async function getVisibleExportGraph() {
    const bridge = frame?.contentWindow?.__TEKG_COEXPRESSION_EMBED;
    if (!bridge || typeof bridge.getVisibleSubgraph !== 'function') {
      throw new Error('The Co-expression renderer is not ready for export.');
    }
    return bridge.getVisibleSubgraph();
  }

  async function exportCsvText() {
    const graph = await getVisibleExportGraph();
    const fields = [
      'record_type', 'id', 'label', 'feature_type', 'role', 'source', 'target',
      'correlation', 'fdr', 'pair_type', 'expression_context', 'expression_median',
      'analysis_version', 'method', 'thresholds', 'interpretation_limit',
    ];
    const version = String(currentNetwork?.version || catalog?.version || '');
    const method = String(catalog?.method || 'Spearman correlation');
    const thresholds = JSON.stringify(catalog?.thresholds || {});
    const limit = String(currentNetwork?.interpretation?.limit || catalog?.interpretationLimit || 'Correlation does not imply causation.');
    const lines = [fields.join(',')];
    for (const node of graph.nodes || []) {
      lines.push([
        'node', node.id, node.label, node.type, node.role, '', '', '', '', '',
        node.expression_context, node.expression_value, version, method, thresholds, limit,
      ].map(csvCell).join(','));
    }
    for (const edge of graph.edges || []) {
      lines.push([
        'edge', edge.id, '', '', edge.edge_role, edge.source, edge.target,
        edge.correlation, edge.fdr, edge.pair_type, '', '', version, method, thresholds, limit,
      ].map(csvCell).join(','));
    }
    return `${lines.join('\r\n')}\r\n`;
  }

  function safeFilename(value) {
    return String(value || 'coexpression').trim().replace(/[^a-z0-9_.-]+/gi, '_').replace(/^_+|_+$/g, '') || 'coexpression';
  }

  function downloadBlob(filename, content, mime) {
    const blob = content instanceof Blob ? content : new Blob([content], { type: mime });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  async function exportCsvFile() {
    const text = await exportCsvText();
    const selection = currentSelection || {};
    downloadBlob(
      `tekg_${safeFilename(selection.te)}_${safeFilename(selection.context)}_coexpression.csv`,
      `\uFEFF${text}`,
      'text/csv;charset=utf-8',
    );
    return text;
  }

  async function exportPngFile() {
    const bridge = frame?.contentWindow?.__TEKG_COEXPRESSION_EMBED;
    if (!bridge || typeof bridge.exportPngDataUrl !== 'function') {
      throw new Error('The Co-expression renderer is not ready for PNG export.');
    }
    const dataUrl = await bridge.exportPngDataUrl();
    if (!String(dataUrl || '').startsWith('data:image/png')) {
      throw new Error('The Co-expression PNG export is blank.');
    }
    const response = await fetch(dataUrl);
    const blob = await response.blob();
    const selection = currentSelection || {};
    downloadBlob(
      `tekg_${safeFilename(selection.te)}_${safeFilename(selection.context)}_coexpression.png`,
      blob,
      'image/png',
    );
    return dataUrl;
  }

  function closeExportMenu() {
    els.exportMenu.hidden = true;
    els.exportToggle.setAttribute('aria-expanded', 'false');
  }

  function toggleExportMenu() {
    if (els.exportToggle.disabled) return;
    const open = els.exportMenu.hidden;
    els.exportMenu.hidden = !open;
    els.exportToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function handleDocumentPointerDown(event) {
    if (!els.exportWrap.contains(event.target)) closeExportMenu();
  }

  function destroy() {
    requestEpoch += 1;
    expressionEpoch += 1;
    if (abortController) abortController.abort();
    abortController = null;
    if (expressionAbortController) expressionAbortController.abort();
    expressionAbortController = null;
    const bridge = frame?.contentWindow?.__TEKG_COEXPRESSION_EMBED;
    if (bridge && typeof bridge.destroy === 'function') bridge.destroy();
    if (frame) frame.remove();
    frame = null;
    frameBridgePromise = null;
    currentNetwork = null;
    currentNonblank = false;
    document.removeEventListener('pointerdown', handleDocumentPointerDown);
    setWorkspaceVisible(false);
    setState('idle');
  }

  function getDiagnostics() {
    const bridge = frame?.contentWindow?.__TEKG_COEXPRESSION_EMBED;
    const graph = bridge && typeof bridge.getDiagnostics === 'function'
      ? bridge.getDiagnostics()
      : {};
    return {
      state: currentState,
      visible,
      selection: currentSelection,
      availableContexts: currentAvailableContexts.slice(),
      iframeCount: els.frameHost.querySelectorAll('iframe').length,
      frameIdentity,
      nodeCount: Number(graph.nodeCount || currentNetwork?.nodes?.length || 0),
      edgeCount: Number(graph.edgeCount || currentNetwork?.edges?.length || 0),
      nonblank: currentNonblank && graph.nonblank !== false,
      layoutStopped: graph.layoutStopped === true,
      renderCount: Number(graph.renderCount || 0),
      loaderVisible: els.preloader.classList.contains('is-visible'),
      cacheKeys: [...networkCache.keys()],
      expressionEnabled,
      expressionCacheKeys: [...expressionSummaryCache.keys()],
      expressionAvailableCount: Object.values(currentExpressionOverlay.records || {}).filter((record) => record?.available === true).length,
      viewOptions: { ...currentViewOptions },
      requestCounts: { ...requestCounts },
    };
  }

  window.__TEKG_COEXPRESSION_GRAPH_HOST = {
    setStatus(text) {
      if (currentState === 'rendering' && text) els.preloaderLabel.textContent = text;
    },
    setDetail(payload) {
      els.detail.textContent = payload?.description ? `${payload.title}: ${payload.description}` : String(payload?.title || '');
    },
    setDetailHtml(html) {
      els.detail.innerHTML = String(html || '');
    },
    onSelection() {},
    onReady() {},
    onNonblank(report) {
      currentNonblank = report?.nonblank === true;
    },
    onError(message) {
      setState('error', String(message || 'Unable to initialize Co-expression graph.'));
    },
  };

  els.te.addEventListener('change', () => {
    const item = itemForTe(els.te.value);
    if (item) {
      populateContextOptions(item, els.context.value);
      return;
    }
    els.context.disabled = true;
  });
  els.load.addEventListener('click', () => {
    const coordinator = window.__TEKG_PREVIEW_WORKSPACE_MODE;
    if (coordinator && typeof coordinator.setMode === 'function') {
      void coordinator.setMode('coexpression', {
        te: els.te.value,
        context: els.context.value,
        history: 'push',
      });
      return;
    }
    void activate({ te: els.te.value, context: els.context.value });
  });
  els.expression.addEventListener('click', () => {
    void setExpressionEnabled(!expressionEnabled);
  });
  [els.showTe, els.showGene, els.edgeScope].forEach((control) => {
    control.addEventListener('change', () => {
      els.legendApply.disabled = false;
    });
  });
  els.legendApply.addEventListener('click', () => {
    void setViewOptions({
      showTE: els.showTe.checked,
      showGene: els.showGene.checked,
      edgeScope: els.edgeScope.value,
    });
  });
  els.retry.addEventListener('click', () => {
    if (!currentSelection?.te) return;
    void activate({ te: currentSelection.te, context: currentSelection.context });
  });
  els.exportToggle.addEventListener('click', toggleExportMenu);
  els.exportCsv.addEventListener('click', () => {
    closeExportMenu();
    void exportCsvFile().catch((error) => setState('error', error?.message || 'CSV export failed.'));
  });
  els.exportPng.addEventListener('click', () => {
    closeExportMenu();
    void exportPngFile().catch((error) => setState('error', error?.message || 'PNG export failed.'));
  });
  document.addEventListener('pointerdown', handleDocumentPointerDown);

  window.__TEKG_COEXPRESSION_MODE = {
    activate,
    deactivate,
    resume,
    destroy,
    setExpressionEnabled,
    setViewOptions,
    exportCsvText,
    exportCsvFile,
    exportPngFile,
    getDiagnostics,
  };

  window.TEKGTeAutocomplete?.registerSource?.('coexpression-catalog', {
    label: 'Co-expression TE',
    loadOptions() {
      return loadCatalog().then((nextCatalog) => nextCatalog.items.map((item) => ({ name: item.te })));
    },
  });
  syncExpressionButton();
}());
