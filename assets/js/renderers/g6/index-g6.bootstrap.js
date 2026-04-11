(function () {
  if (window.__TEKG_RENDERER_MODE !== 'g6') return;

  const params = new URLSearchParams(window.location.search);
  const embedMode = typeof window.__TEKG_EMBED_MODE === 'string' ? window.__TEKG_EMBED_MODE : '';
  const initialQuery = String(window.__TEKG_INITIAL_QUERY || '').trim();
  const initialQueryType = String(params.get('type') || '').trim().toLowerCase();
  const initialClassQuery = String(params.get('class') || '').trim();

  const els = {
    zhBtn: document.getElementById('lang-zh'),
    enBtn: document.getElementById('lang-en'),
    title: document.getElementById('page-title'),
    badge: document.getElementById('page-badge'),
    graphTitle: document.getElementById('graph-title'),
    searchInput: document.getElementById('node-search'),
    showLabelsBtn: document.getElementById('toggle-show-labels'),
    showLabelsText: document.getElementById('show-labels-text'),
    fixedBtn: document.getElementById('toggle-fixed-view'),
    fixedText: document.getElementById('fixed-view-text'),
    backBtn: document.getElementById('back-graph'),
    backText: document.getElementById('back-text'),
    resetBtn: document.getElementById('reset-graph'),
    resetText: document.getElementById('reset-text'),
    levelMinus: document.getElementById('decrease-key-node-level'),
    levelPlus: document.getElementById('increase-key-node-level'),
    levelText: document.getElementById('key-node-level-text'),
    detail: document.getElementById('node-details'),
    treeSurface: document.getElementById('g6-default-tree-surface'),
    dynamicSurface: document.getElementById('g6-dynamic-surface'),
    graphLoader: document.getElementById('graph-preloader'),
    graphLoaderLabel: document.getElementById('graph-preloader-label'),
    graphLegend: document.getElementById('graph-type-legend'),
    graphLegendTitle: document.getElementById('graph-legend-title'),
    graphLegendList: document.getElementById('graph-legend-list'),
    main: document.querySelector('.main'),
  };

  const UI = {
    en: {
      pageTitle: 'TEKG G6 Workspace',
      badge: 'Tree-first preview with test-aligned dynamic graph',
      graphTitle: 'G6 Graph Workspace',
      searchPlaceholder: 'Search LINE1, L1HS, disease, or function',
      showNamesOn: 'Show names: On',
      showNamesOff: 'Show names: Off',
      fixedOn: 'Fixed view: On',
      fixedOff: 'Fixed view: Off',
      back: 'Back',
      backTo: (label) => `Back to ${label}`,
      backToTree: 'Back to tree',
      reset: 'Reset',
      keyNodeLevel: (level) => `Key-node level: ${level}`,
      treeDetail:
        '<strong>No node selected</strong>Click a TE node to inspect it, then click again to enter the dynamic graph.',
      loadingDetail: (query) => buildLoadingDetailHtml(`Preparing the dynamic graph for ${escapeHtml(query)}.`),
      loadingOverlay: (query) => `Preparing ${escapeHtml(query)} ...`,
      graphError: (message) => `Failed: ${message || 'unknown error'}`,
      legendTitle: 'Entity Legend',
    },
    zh: {
      pageTitle: 'TEKG G6 Workspace',
      badge: '\u4ee5\u5206\u7c7b\u6811\u4e3a\u9ed8\u8ba4\u5165\u53e3\u7684 G6 \u9884\u89c8',
      graphTitle: 'G6 Graph Workspace',
      searchPlaceholder: '\u641c\u7d22 LINE1\u3001L1HS\u3001\u75be\u75c5\u6216\u529f\u80fd',
      showNamesOn: '\u663e\u793a\u540d\u5b57\uff1a\u5f00',
      showNamesOff: '\u663e\u793a\u540d\u5b57\uff1a\u5173',
      fixedOn: '\u56fa\u5b9a\u89c6\u56fe\uff1a\u5f00',
      fixedOff: '\u56fa\u5b9a\u89c6\u56fe\uff1a\u5173',
      back: '\u8fd4\u56de',
      backTo: (label) => `\u8fd4\u56de\u5230 ${label}`,
      backToTree: '\u8fd4\u56de\u5230\u5206\u7c7b\u6811',
      reset: '\u91cd\u7f6e',
      keyNodeLevel: (level) => `\u5173\u952e\u8282\u70b9\u5c42\u6570\uff1a${level}`,
      treeDetail:
        '<strong>\u5c1a\u672a\u9009\u4e2d\u8282\u70b9</strong>\u70b9\u51fb\u4e00\u4e2a TE \u8282\u70b9\u67e5\u770b\u8be6\u60c5\uff0c\u518d\u6b21\u70b9\u51fb\u53ef\u8fdb\u5165\u52a8\u6001\u56fe\u3002',
      loadingDetail: (query) => buildLoadingDetailHtml(`\u6b63\u5728\u4e3a ${escapeHtml(query)} \u51c6\u5907 G6 \u52a8\u6001\u56fe\u3002`),
      loadingOverlay: (query) => `\u6b63\u5728\u51c6\u5907 ${escapeHtml(query)} ...`,
      graphError: (message) => `\u5931\u8d25\uff1a${message || '\u672a\u77e5\u9519\u8bef'}`,
      legendTitle: '\u5b9e\u4f53\u56fe\u4f8b',
    },
  };

  let currentMode = 'tree';
  let currentGraphSource = 'tree';
  let currentGraphQuery = '';
  let currentGraphQueryType = '';
  let currentGraphClassQuery = '';
  let currentSelectedNode = null;
  let currentAnswerGraphElements = [];
  let currentQueryGraphElements = [];
  let graphHistory = [];
  let dynamicFrame = null;
  let dynamicBridgePromise = null;

  window.currentLang = params.get('lang') === 'zh' ? 'zh' : 'en';
  window.fixedView = false;
  window.showLabels = false;
  window.currentKeyNodeLevel = 1;
  window.focusLevel = 0;
  if (typeof window.cy === 'undefined') {
    window.cy = { nodes: () => [] };
  }

  function escapeHtml(text) {
    return String(text || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function buildLoadingDetailHtml(label) {
    return [
      '<div class="detail-loading">',
      '  <div class="detail-loading-icon" aria-hidden="true">',
      '    <span></span>',
      '    <span></span>',
      '  </div>',
      `  <div class="detail-loading-label">${label || 'Loading graph...'}</div>`,
      '</div>',
    ].join('');
  }

  function textSet() {
    return UI[window.currentLang] || UI.en;
  }

  const LEGEND_FALLBACK_ORDER = ['TE', 'Disease', 'Function', 'Gene', 'Protein', 'RNA', 'Mutation', 'Pharmaceutical', 'Toxin', 'Lipid', 'Peptide', 'Carbohydrate', 'Paper'];
  const LEGEND_FALLBACK_LABELS = {
    TE: 'TE',
    Disease: 'Disease',
    Function: 'Function',
    Gene: 'Gene',
    Protein: 'Protein',
    RNA: 'RNA',
    Mutation: 'Mutation',
    Pharmaceutical: 'Pharmaceutical',
    Toxin: 'Toxin',
    Lipid: 'Lipid',
    Peptide: 'Peptide',
    Carbohydrate: 'Carbohydrate',
    Paper: 'Paper',
  };
  const LEGEND_FALLBACK_COLORS = {
    TE: '#4e79ff',
    Disease: '#ff7a7a',
    Function: '#41b883',
    Gene: '#8a7cf8',
    Protein: '#59bfb6',
    RNA: '#72b6ff',
    Mutation: '#ffb066',
    Pharmaceutical: '#a98cf6',
    Toxin: '#df8a78',
    Lipid: '#95c863',
    Peptide: '#54c9c0',
    Carbohydrate: '#d5b458',
    Paper: '#f2a93b',
  };

  let visibleTypeState = null;

  function getLegendTypeMeta() {
    const sharedMeta = window.__TEKG_G6_TYPE_META && typeof window.__TEKG_G6_TYPE_META === 'object'
      ? window.__TEKG_G6_TYPE_META
      : {};
    const order = Array.isArray(sharedMeta.legendOrder) && sharedMeta.legendOrder.length
      ? sharedMeta.legendOrder
      : LEGEND_FALLBACK_ORDER;
    const labels = sharedMeta.labels && typeof sharedMeta.labels === 'object'
      ? sharedMeta.labels
      : LEGEND_FALLBACK_LABELS;
    const colors = sharedMeta.colors && typeof sharedMeta.colors === 'object'
      ? sharedMeta.colors
      : LEGEND_FALLBACK_COLORS;

    return [...new Set(order)]
      .filter((type) => (labels[type] || type) && colors[type])
      .map((type) => ({
        type,
        label: String(labels[type] || type),
        color: String(colors[type] || '#94a3b8'),
      }));
  }

  function ensureVisibleTypeState() {
    const next = visibleTypeState && typeof visibleTypeState === 'object'
      ? { ...visibleTypeState }
      : {};
    for (const item of getLegendTypeMeta()) {
      if (typeof next[item.type] !== 'boolean') next[item.type] = true;
    }
    visibleTypeState = next;
    return visibleTypeState;
  }

  function getVisibleTypePayload() {
    return { ...ensureVisibleTypeState() };
  }

  function buildCurrentGraphDataOptions(extra = {}) {
    return Object.assign({
      showAllLabels: window.showLabels,
      visibleTypes: getVisibleTypePayload(),
    }, extra || {});
  }

  function renderGraphLegend() {
    const t = textSet();
    if (els.graphLegendTitle) {
      els.graphLegendTitle.textContent = t.legendTitle || 'Entity Legend';
    }
    if (!els.graphLegendList) return;

    const visibleMap = ensureVisibleTypeState();
    const items = getLegendTypeMeta();
    els.graphLegendList.innerHTML = items.map((item) => {
      const safeType = escapeHtml(item.type);
      const safeLabel = escapeHtml(item.label);
      const safeColor = escapeHtml(item.color);
      const checked = visibleMap[item.type] !== false ? ' checked' : '';
      return [
        '<div class="graph-legend-item">',
        `  <input class="graph-legend-check" type="checkbox" data-type="${safeType}" aria-label="${safeLabel}"${checked}>`,
        `  <span class="graph-legend-swatch" style="--legend-color:${safeColor};"></span>`,
        `  <span class="graph-legend-text">${safeLabel}</span>`,
        '</div>',
      ].join('');
    }).join('');
  }

  function syncLegendVisibility(mode = currentMode) {
    if (!els.graphLegend) return;
    const hasItems = getLegendTypeMeta().length > 0;
    const shouldShow = mode === 'dynamic' && hasItems;
    els.graphLegend.hidden = !shouldShow;
    els.graphLegend.setAttribute('aria-hidden', shouldShow ? 'false' : 'true');
  }

  function applyLegendTypeFilter() {
    if (currentMode !== 'dynamic') return Promise.resolve(false);
    const sourceElements = currentGraphSource === 'answer'
      ? currentAnswerGraphElements
      : currentQueryGraphElements;
    return renderDynamicElementsFromCache(sourceElements, {
      source: currentGraphSource,
      request: buildCurrentGraphRequest(),
    }).then(() => true);
  }

  function setDetail(html) {
    if (els.detail) {
      els.detail.innerHTML = html || '';
    }
  }

  function setGraphLoading(visible, label = '') {
    if (!els.graphLoader) return;
    els.graphLoader.classList.remove('is-visible');
    els.graphLoader.setAttribute('aria-hidden', 'true');
    if (els.graphLoaderLabel) {
      els.graphLoaderLabel.textContent = label || 'Loading graph...';
    }
  }

  function snapshotState() {
    const currentElements = currentGraphSource === 'answer'
      ? cloneAnswerElements(currentAnswerGraphElements)
      : cloneAnswerElements(currentQueryGraphElements);

    return {
      mode: currentMode,
      source: currentGraphSource,
      query: currentGraphQuery,
      queryType: currentGraphQueryType,
      classQuery: currentGraphClassQuery,
      fixedView: !!window.fixedView,
      showLabels: !!window.showLabels,
      keyNodeLevel: window.currentKeyNodeLevel,
      selectedNode: currentSelectedNode,
      currentElements,
      lang: window.currentLang,
      historyDepth: graphHistory.length,
    };
  }

  function notifyStateChange() {
    try {
      window.dispatchEvent(new CustomEvent('tekg:g6-state-change', {
        detail: snapshotState(),
      }));
    } catch (_error) {}
  }

  function buildDetail(title, description) {
    return `<strong>${escapeHtml(title || '')}</strong>${escapeHtml(description || '')}`;
  }

  function buildQaDetail() {
    return buildDetail(
      'QA graph synchronized',
      'The graph now shows the nodes and edges used in the current answer.',
    );
  }

  function cloneAnswerElements(elements) {
    return JSON.parse(JSON.stringify(Array.isArray(elements) ? elements : []));
  }

  function filterElementsByVisibleTypes(elements) {
    const source = cloneAnswerElements(elements);
    const visibleMap = getVisibleTypePayload();
    const visibleNodeIds = new Set();
    const filteredNodes = [];
    const filteredEdges = [];

    for (const item of source) {
      const data = item && item.data ? item.data : null;
      if (!data || data.source || data.target) continue;
      const nodeType = String(data.type || 'TE').trim() || 'TE';
      if (visibleMap[nodeType] === false) continue;
      filteredNodes.push(item);
      visibleNodeIds.add(String(data.id || ''));
    }

    for (const item of source) {
      const data = item && item.data ? item.data : null;
      if (!data || !data.source || !data.target) continue;
      if (!visibleNodeIds.has(String(data.source || '')) || !visibleNodeIds.has(String(data.target || ''))) continue;
      filteredEdges.push(item);
    }

    return [...filteredNodes, ...filteredEdges];
  }

  async function renderDynamicElementsFromCache(elements, options = {}) {
    const source = options && options.source === 'answer' ? 'answer' : 'query';
    const request = normalizeGraphRequest(options && options.request ? options.request : buildCurrentGraphRequest());
    const renderElements = filterElementsByVisibleTypes(elements);

    currentSelectedNode = null;
    showDynamicSurface();
    updateButtons();
    setDetail('');
    notifyStateChange();
    setGraphLoading(true, textSet().loadingOverlay(currentGraphQuery || request.query || 'LINE1'));

    try {
      await waitForDynamicSurfaceSize();
      const frame = source === 'answer'
        ? (dynamicFrame || ensureDynamicFrame({ query: '' }))
        : ensureDynamicFrame(request);
      if (!dynamicBridgePromise) {
        dynamicBridgePromise = waitForEmbedBridge(frame);
      }

      const bridge = await dynamicBridgePromise;
      if (!bridge || typeof bridge.renderElements !== 'function') {
        throw new Error('G6 embed bridge cannot render cached graph elements');
      }

      const graphDataOptions = source === 'answer'
        ? buildCurrentGraphDataOptions({
            includePaperNodes: true,
            synthesizeDiseaseClasses: false,
            restrictToAnchorComponent: false,
            forceAnchorLabel: true,
          })
        : buildCurrentGraphDataOptions(
            request.queryType === 'disease_class'
              ? { synthesizeDiseaseClasses: false }
              : {}
          );

      await bridge.renderElements(renderElements, request, {
        sourceLabel: source === 'answer' ? 'qa' : 'query',
        skipInitialStatus: true,
        graphDataOptions,
      });

      notifyStateChange();
      return true;
    } finally {
      setGraphLoading(false);
    }
  }

  function stateSignature(state) {
    if (!state || typeof state !== 'object') return 'none';
    if (state.kind === 'tree') return 'tree';
    if (state.kind === 'disease_class_tree') {
      return [
        'disease_class_tree',
        state.classQuery || '',
      ].join('|');
    }
    if (state.kind === 'query') {
      return [
        'query',
        state.query || '',
        state.queryType || '',
        state.classQuery || '',
        String(state.keyNodeLevel || 1),
        state.fixedView ? '1' : '0',
        state.showLabels ? '1' : '0',
      ].join('|');
    }
    if (state.kind === 'answer') {
      return [
        'answer',
        state.query || '',
        String(state.keyNodeLevel || 1),
        state.fixedView ? '1' : '0',
        state.showLabels ? '1' : '0',
        String((state.elements || []).length),
      ].join('|');
    }
    return 'unknown';
  }

  function captureCurrentGraphState() {
    if (currentMode === 'tree') {
      return { kind: 'tree' };
    }

    if (currentMode === 'disease_class_tree') {
      return {
        kind: 'disease_class_tree',
        query: currentGraphQuery,
        classQuery: currentGraphClassQuery || currentGraphQuery,
      };
    }

    if (currentGraphSource === 'answer') {
      return {
        kind: 'answer',
        query: currentGraphQuery,
        keyNodeLevel: window.currentKeyNodeLevel,
        fixedView: !!window.fixedView,
        showLabels: !!window.showLabels,
        elements: cloneAnswerElements(currentAnswerGraphElements),
      };
    }

    return {
      kind: 'query',
      query: currentGraphQuery,
      queryType: currentGraphQueryType,
      classQuery: currentGraphClassQuery,
      keyNodeLevel: window.currentKeyNodeLevel,
      fixedView: !!window.fixedView,
      showLabels: !!window.showLabels,
    };
  }

  function pushCurrentStateToHistory() {
    const snapshot = captureCurrentGraphState();
    const nextSignature = stateSignature(snapshot);
    const lastSignature = graphHistory.length ? stateSignature(graphHistory[graphHistory.length - 1]) : '';
    if (nextSignature === lastSignature) return;
    graphHistory.push(snapshot);
  }

  function describeHistoryState(state) {
    if (!state || typeof state !== 'object') return textSet().back || 'Back';
    if (state.kind === 'tree') {
      return textSet().backToTree || textSet().back || 'Back';
    }
    if (state.kind === 'disease_class_tree') {
      const label = String(state.classQuery || state.query || '').trim();
      if (!label) return textSet().backToTree || textSet().back || 'Back';
      return typeof textSet().backTo === 'function'
        ? textSet().backTo(label)
        : `Back to ${label}`;
    }
    const label = String(state.classQuery || state.query || '').trim();
    if (!label) {
      return textSet().back || 'Back';
    }
    return typeof textSet().backTo === 'function'
      ? textSet().backTo(label)
      : `Back to ${label}`;
  }

  function updateBackButton() {
    if (!els.backBtn) return;
    els.backBtn.disabled = graphHistory.length === 0;
    if (els.backText) {
      const previousState = graphHistory.length ? graphHistory[graphHistory.length - 1] : null;
      els.backText.textContent = previousState ? describeHistoryState(previousState) : (textSet().back || 'Back');
    }
  }

  function normalizeQueryType(value) {
    const normalized = String(value || '').trim().toLowerCase();
    if (normalized === 'disease_class' || normalized === 'diseaseclass') return 'disease_class';
    return '';
  }

  function buildCurrentGraphRequest() {
    if (currentGraphQueryType === 'disease_class') {
      const classQuery = String(currentGraphClassQuery || currentGraphQuery || '').trim();
      return {
        query: classQuery,
        queryType: 'disease_class',
        classQuery,
      };
    }
    return {
      query: String(currentGraphQuery || '').trim(),
      queryType: '',
      classQuery: '',
    };
  }

  function normalizeGraphRequest(requestLike) {
    if (requestLike && typeof requestLike === 'object' && !Array.isArray(requestLike)) {
      const queryType = normalizeQueryType(requestLike.type || requestLike.queryType);
      const classQuery = String(requestLike.classQuery || requestLike.class || '').trim();
      const query = String(requestLike.query || requestLike.q || classQuery || '').trim();
      if (queryType === 'disease_class') {
        const normalizedClassQuery = classQuery || query;
        return {
          query: normalizedClassQuery,
          queryType,
          classQuery: normalizedClassQuery,
        };
      }
      return {
        query,
        queryType: '',
        classQuery: '',
      };
    }

    if (typeof requestLike === 'string') {
      return {
        query: String(requestLike || '').trim(),
        queryType: '',
        classQuery: '',
      };
    }

    return buildCurrentGraphRequest();
  }

  function applyPageMode() {
    document.body.classList.add('tekg-g6-preview-ready');
  }

  function updateButtons() {
    const t = textSet();
    if (els.zhBtn) els.zhBtn.classList.toggle('active', window.currentLang === 'zh');
    if (els.enBtn) els.enBtn.classList.toggle('active', window.currentLang === 'en');
    if (els.title) els.title.textContent = t.pageTitle;
    if (els.badge) els.badge.textContent = t.badge;
    if (els.graphTitle) els.graphTitle.textContent = t.graphTitle;
    if (els.searchInput) els.searchInput.placeholder = t.searchPlaceholder;
    if (els.showLabelsText) els.showLabelsText.textContent = window.showLabels ? t.showNamesOn : t.showNamesOff;
    if (els.fixedText) els.fixedText.textContent = window.fixedView ? t.fixedOn : t.fixedOff;
    if (els.backText) els.backText.textContent = t.back || 'Back';
    if (els.resetText) els.resetText.textContent = t.reset;
    if (els.levelText) els.levelText.textContent = t.keyNodeLevel(window.currentKeyNodeLevel);
    if (els.graphLegendTitle) els.graphLegendTitle.textContent = t.legendTitle || 'Entity Legend';
    if (els.levelMinus) els.levelMinus.disabled = window.currentKeyNodeLevel <= 1;
    if (els.levelPlus) els.levelPlus.disabled = window.currentKeyNodeLevel >= 10;
    updateBackButton();
  }

  function syncLangParam() {
    const next = new URL(window.location.href);
    next.searchParams.set('lang', window.currentLang);
    if (!embedMode) next.searchParams.set('renderer', 'g6');
    window.history.replaceState({}, '', next.toString());
  }

  function showTreeSurface() {
    if (els.treeSurface) els.treeSurface.style.display = 'block';
    if (els.dynamicSurface) els.dynamicSurface.style.display = 'none';
    syncLegendVisibility('tree');
  }

  function showDynamicSurface() {
    if (els.treeSurface) els.treeSurface.style.display = 'none';
    if (els.dynamicSurface) els.dynamicSurface.style.display = 'block';
    syncLegendVisibility('dynamic');
  }

  function waitForDynamicSurfaceSize(maxAttempts = 60, delayMs = 50) {
    return new Promise((resolve, reject) => {
      let attempts = 0;
      const check = () => {
        attempts += 1;
        const width = els.dynamicSurface ? (els.dynamicSurface.clientWidth || 0) : 0;
        const height = els.dynamicSurface ? (els.dynamicSurface.clientHeight || 0) : 0;
        if (width > 24 && height > 24) {
          resolve({ width, height });
          return;
        }
        if (attempts >= maxAttempts) {
          reject(new Error('Dynamic graph surface has no size yet.'));
          return;
        }
        window.setTimeout(check, delayMs);
      };
      check();
    });
  }

  async function loadSharedResources() {
    const tasks = [];
    if (typeof loadTerminology === 'function') tasks.push(loadTerminology());
    if (typeof loadTeDescriptions === 'function') tasks.push(loadTeDescriptions());
    if (typeof loadEntityDescriptions === 'function') tasks.push(loadEntityDescriptions());
    if (typeof loadUiText === 'function') tasks.push(loadUiText());
    await Promise.all(tasks);
    try {
      window.dispatchEvent(new CustomEvent('tekg:shared-ready'));
    } catch (_error) {}
  }

  function buildDynamicFrameSrc(requestLike = buildCurrentGraphRequest()) {
    const request = normalizeGraphRequest(requestLike);
    const url = new URL('index_g6_embed.html', window.location.href);
    url.searchParams.set('renderer', 'g6');
    url.searchParams.set('lang', window.currentLang);
    url.searchParams.set('key_level', String(window.currentKeyNodeLevel));
    url.searchParams.set('fixed', window.fixedView ? '1' : '0');
    url.searchParams.set('show_labels', window.showLabels ? '1' : '0');
    url.searchParams.set('bridge_v', '20260411a');
    const query = String(request.query || '').trim();
    if (query) {
      url.searchParams.set('q', query);
    } else {
      url.searchParams.delete('q');
    }
    if (request.queryType) url.searchParams.set('type', request.queryType);
    else url.searchParams.delete('type');
    if (request.queryType === 'disease_class' && request.classQuery) url.searchParams.set('class', request.classQuery);
    else url.searchParams.delete('class');
    return url.toString();
  }

  function waitForEmbedBridge(frame, maxAttempts = 60, delayMs = 50) {
    return new Promise((resolve, reject) => {
      let attempts = 0;
      const check = () => {
        attempts += 1;
        try {
          const bridge = frame.contentWindow && frame.contentWindow.__TEKG_G6_EMBED;
          if (bridge && typeof bridge.loadGraph === 'function') {
            resolve(bridge);
            return;
          }
        } catch (_error) {}
        if (attempts >= maxAttempts) {
          reject(new Error('G6 embed bridge is not available'));
          return;
        }
        window.setTimeout(check, delayMs);
      };
      check();
    });
  }

  function ensureDynamicFrame(requestLike = buildCurrentGraphRequest()) {
    const nextSrc = buildDynamicFrameSrc(requestLike);

    if (!dynamicFrame) {
      dynamicFrame = document.createElement('iframe');
      dynamicFrame.id = 'g6-dynamic-frame';
      dynamicFrame.title = 'TEKG G6 dynamic graph';
      dynamicFrame.setAttribute('scrolling', 'no');
      if (els.dynamicSurface) {
        els.dynamicSurface.innerHTML = '';
        els.dynamicSurface.appendChild(dynamicFrame);
      }
    }

    const currentSrc = dynamicFrame.getAttribute('src') || '';
    if (currentSrc !== nextSrc) {
      dynamicBridgePromise = null;
      dynamicFrame.src = nextSrc;
    }

    return dynamicFrame;
  }

  function convertGraphActionSubgraphToElements(graphAction) {
    const elements = [];
    const subgraph = graphAction && typeof graphAction === 'object' ? graphAction.subgraph || {} : {};
    const nodes = Array.isArray(subgraph.nodes) ? subgraph.nodes : [];
    const edges = Array.isArray(subgraph.edges) ? subgraph.edges : [];

    for (const node of nodes) {
      if (!node || typeof node !== 'object') continue;
      elements.push({
        data: {
          id: String(node.id || ''),
          label: String(node.label || node.id || ''),
          rawLabel: String(node.label || node.id || ''),
          type: String(node.type || 'TE'),
          description: String(node.description || ''),
          pmid: String(node.pmid || ''),
        },
      });
    }

    for (const edge of edges) {
      if (!edge || typeof edge !== 'object') continue;
      elements.push({
        data: {
          id: String(edge.id || `${edge.source || ''}__${edge.relation || 'relation'}__${edge.target || ''}`),
          source: String(edge.source || ''),
          target: String(edge.target || ''),
          relation: String(edge.relation || ''),
          relationType: String(edge.relation || ''),
          evidence: String(edge.evidence || ''),
          pmids: Array.isArray(edge.pmids) ? edge.pmids : [],
        },
      });
    }

    return elements;
  }

  function extractAnswerGraphElements(result) {
    const graphAction = result && result.graph_action && typeof result.graph_action === 'object'
      ? result.graph_action
      : null;
    const graphActionElements = graphAction ? convertGraphActionSubgraphToElements(graphAction) : [];
    if (graphActionElements.length) return graphActionElements;

    const graphContextElements = result && result.graph_context && Array.isArray(result.graph_context.elements)
      ? result.graph_context.elements
      : [];
    return graphContextElements;
  }

  async function renderAnswerGraphElements(elements, query, options = {}) {
    const pushHistory = options.pushHistory !== false;
    if (pushHistory) pushCurrentStateToHistory();

    currentMode = 'dynamic';
    currentGraphSource = 'answer';
    currentGraphQuery = String(query || currentGraphQuery || '').trim() || 'LINE1';
    currentGraphQueryType = '';
    currentGraphClassQuery = '';
    currentSelectedNode = null;
    currentAnswerGraphElements = cloneAnswerElements(elements);
    currentQueryGraphElements = [];

    showDynamicSurface();
    updateButtons();
    setDetail('');
    notifyStateChange();
    setGraphLoading(true, textSet().loadingOverlay(currentGraphQuery));

    try {
      await waitForDynamicSurfaceSize();

      const frame = dynamicFrame || ensureDynamicFrame({ query: '' });
      if (!dynamicBridgePromise) {
        dynamicBridgePromise = waitForEmbedBridge(frame);
      }

      const bridge = await dynamicBridgePromise;
      if (!bridge || typeof bridge.renderElements !== 'function') {
        throw new Error('G6 embed bridge cannot render QA elements');
      }

      await bridge.renderElements(filterElementsByVisibleTypes(currentAnswerGraphElements), { query: currentGraphQuery }, {
        sourceLabel: 'qa',
        graphDataOptions: buildCurrentGraphDataOptions({
          includePaperNodes: true,
          synthesizeDiseaseClasses: false,
          restrictToAnchorComponent: false,
          forceAnchorLabel: true,
        }),
      });

      notifyStateChange();
      return true;
    } finally {
      setGraphLoading(false);
    }
  }

  async function fetchDiseaseClassPayload(requestLike) {
    const request = normalizeGraphRequest(requestLike);
    const classQuery = String(request.classQuery || request.query || '').trim();
    if (!classQuery) {
      throw new Error('Disease class query is required.');
    }

    const endpoint = new URL('api/graph.php', window.location.href);
    endpoint.searchParams.set('q', classQuery);
    endpoint.searchParams.set('type', 'disease_class');
    endpoint.searchParams.set('class', classQuery);
    endpoint.searchParams.set('key_level', String(window.currentKeyNodeLevel));

    const response = await fetch(endpoint.toString(), {
      credentials: 'same-origin',
    });
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    return response.json();
  }

  function buildDiseaseClassTreeModel(elements, classQuery) {
    const nodes = new Map();
    const children = new Map();
    const incoming = new Set();
    const typeRank = {
      DiseaseClass: 0,
      DiseaseCategory: 1,
      Disease: 2,
    };

    for (const item of Array.isArray(elements) ? elements : []) {
      const data = item && item.data ? item.data : null;
      if (!data) continue;
      if (data.source && data.target) {
        if (!children.has(data.source)) children.set(data.source, []);
        children.get(data.source).push(data.target);
        incoming.add(data.target);
        continue;
      }

      const nodeType = String(data.type || '');
      nodes.set(data.id, {
        id: data.id,
        rawLabel: String(data.rawLabel || data.label || data.id || ''),
        description: String(data.description || ''),
        nodeType,
        categoryLevel: Number(data.category_level || 0),
        diseaseClass: String(data.disease_class || classQuery || ''),
        queryLabel: nodeType === 'Disease' ? String(data.rawLabel || data.label || data.id || '') : '',
      });
    }

    let rootId = '';
    for (const node of nodes.values()) {
      if (node.nodeType === 'DiseaseClass') {
        rootId = node.id;
        break;
      }
    }
    if (!rootId) {
      for (const node of nodes.values()) {
        if (!incoming.has(node.id)) {
          rootId = node.id;
          break;
        }
      }
    }
    if (!rootId || !nodes.has(rootId)) {
      return { rootId: '', treeData: null };
    }

    const visit = (nodeId, depth, path) => {
      if (!nodeId || path.has(nodeId)) return null;
      const node = nodes.get(nodeId);
      if (!node) return null;

      const nextPath = new Set(path);
      nextPath.add(nodeId);

      const sortedChildIds = [...new Set(children.get(nodeId) || [])]
        .filter((childId) => nodes.has(childId))
        .sort((leftId, rightId) => {
          const left = nodes.get(leftId);
          const right = nodes.get(rightId);
          const leftRank = typeRank[left?.nodeType] ?? 99;
          const rightRank = typeRank[right?.nodeType] ?? 99;
          if (leftRank !== rightRank) return leftRank - rightRank;
          const leftLevel = Number(left?.categoryLevel || 0);
          const rightLevel = Number(right?.categoryLevel || 0);
          if (leftLevel !== rightLevel) return leftLevel - rightLevel;
          return String(left?.rawLabel || '').localeCompare(String(right?.rawLabel || ''));
        });

      const childNodes = sortedChildIds
        .map((childId) => visit(childId, depth + 1, nextPath))
        .filter(Boolean);

      return {
        id: node.id,
        data: {
          rawLabel: node.rawLabel,
          displayLabel: node.rawLabel,
          description: node.description,
          treeDepth: depth,
          queryLabel: node.queryLabel,
          nodeType: node.nodeType,
          diseaseClass: node.diseaseClass,
          treeKind: 'disease_class',
        },
        style: {
          collapsed: false,
        },
        children: childNodes,
      };
    };

    return {
      rootId,
      treeData: visit(rootId, 0, new Set()),
    };
  }

  function buildDiseaseClassTreeConfigLegacy(classQuery) {
    const isZh = window.currentLang === 'zh';
    const typeLabels = isZh
      ? {
          DiseaseClass: '\u75be\u75c5\u5927\u7c7b',
          DiseaseCategory: '\u75be\u75c5\u5206\u7c7b',
          Disease: '\u75be\u75c5',
        }
      : {
          DiseaseClass: 'Disease Class',
          DiseaseCategory: 'Disease Category',
          Disease: 'Disease',
        };

    return {
      defaultDetailHtml: isZh
        ? `<strong>${escapeHtml(classQuery)}</strong><br>\u5f53\u524d\u4e3a\u75be\u75c5\u5206\u7c7b\u6811\u89c6\u56fe\u3002\u70b9\u51fb\u5206\u7c7b\u8282\u70b9\u67e5\u770b\u5c42\u7ea7\uff0c\u70b9\u51fb\u75be\u75c5\u53f6\u5b50\u53ef\u8fdb\u5165\u666e\u901a\u52a8\u6001\u56fe\u3002`
        : `<strong>${escapeHtml(classQuery)}</strong><br>This disease-class tree is active. Click a disease leaf to open the dynamic graph.`,
      buildLabel(data, nodeId) {
        return String(data.displayLabel || data.rawLabel || nodeId || '');
      },
      buildLabelFill(data) {
        return data.nodeType === 'Disease' ? '#c62828' : '';
      },
      buildDetailHtml(nodeData) {
        const data = nodeData?.data || {};
        const label = String(data.displayLabel || data.rawLabel || nodeData?.id || '');
        const typeLabel = typeLabels[data.nodeType] || data.nodeType || '';
        const description = String(data.description || '').trim();
        return [
          `<strong>${escapeHtml(label)}</strong>${typeLabel ? ` (${escapeHtml(typeLabel)})` : ''}`,
          description ? `<br>${escapeHtml(description)}` : '',
        ].join('');
      },
      async onNodeClick(nodeData, context) {
        const data = nodeData?.data || {};
        if (data.nodeType !== 'Disease') return false;
        const { fixedModeEnabled, homePreviewMode, loadDynamicGraph } = context || {};
        if (fixedModeEnabled || homePreviewMode || typeof loadDynamicGraph !== 'function') {
          return false;
        }
        const query = String(data.queryLabel || data.rawLabel || nodeData?.id || '').trim();
        if (!query) return false;
        await loadDynamicGraph(query);
        return true;
      },
    };
  }

  function buildDiseaseClassTreeConfig(classQuery) {
    const isZh = window.currentLang === 'zh';
    const typeLabels = isZh
      ? {
          DiseaseClass: '\u75be\u75c5\u5927\u7c7b',
          DiseaseCategory: '\u75be\u75c5\u5206\u7c7b',
          Disease: '\u75be\u75c5',
        }
      : {
          DiseaseClass: 'Disease Class',
          DiseaseCategory: 'Disease Category',
          Disease: 'Disease',
        };

    return {
      defaultDetailHtml: isZh
        ? `<strong>${escapeHtml(classQuery)}</strong><br>\u5f53\u524d\u4e3a\u75be\u75c5\u5206\u7c7b\u6811\u89c6\u56fe\u3002\u70b9\u51fb\u5206\u7c7b\u8282\u70b9\u67e5\u770b\u5c42\u7ea7\uff0c\u70b9\u51fb\u75be\u75c5\u53f6\u5b50\u53ef\u8fdb\u5165\u666e\u901a\u52a8\u6001\u56fe\u3002`
        : `<strong>${escapeHtml(classQuery)}</strong><br>This disease-class tree is active. Click a disease leaf to open the dynamic graph.`,
      buildLabel(data, nodeId) {
        return String(data.displayLabel || data.rawLabel || nodeId || '');
      },
      buildLabelFill(data) {
        return data.nodeType === 'Disease' ? '#c62828' : '';
      },
      buildLabelFontWeight(data) {
        return data.nodeType === 'Disease' ? 'bold' : 'normal';
      },
      buildDetailHtml(nodeData) {
        const data = nodeData?.data || {};
        const label = String(data.displayLabel || data.rawLabel || nodeData?.id || '');
        const typeLabel = typeLabels[data.nodeType] || data.nodeType || '';
        const description = String(data.description || '').trim();
        return [
          `<strong>${escapeHtml(label)}</strong>${typeLabel ? ` (${escapeHtml(typeLabel)})` : ''}`,
          description ? `<br>${escapeHtml(description)}` : '',
        ].join('');
      },
      async onNodeClick(nodeData, context) {
        const data = nodeData?.data || {};
        if (data.nodeType !== 'Disease') return false;
        const { fixedModeEnabled, homePreviewMode, loadDynamicGraph } = context || {};
        if (fixedModeEnabled || homePreviewMode || typeof loadDynamicGraph !== 'function') {
          return false;
        }
        const query = String(data.queryLabel || data.rawLabel || nodeData?.id || '').trim();
        if (!query) return false;
        await loadDynamicGraph(query);
        return true;
      },
    };
  }

  async function applyAnswerGraph(result, options = {}) {
    const graphAction = result && result.graph_action && typeof result.graph_action === 'object'
      ? result.graph_action
      : null;
    if (!graphAction || graphAction.enabled !== true) return false;

    const elements = extractAnswerGraphElements(result);
    if (!elements.length) return false;

    const preset = graphAction.preset_state && typeof graphAction.preset_state === 'object'
      ? graphAction.preset_state
      : {};
    const query = String(graphAction.query || graphAction.anchor?.name || currentGraphQuery || '').trim() || 'LINE1';

    if (options.pushHistory !== false) {
      pushCurrentStateToHistory();
    }
    window.currentKeyNodeLevel = Math.max(1, Math.min(10, Number(preset.key_node_level) || 1));
    window.fixedView = preset.fixed_view !== false;
    window.showLabels = true;
    updateButtons();
    return renderAnswerGraphElements(elements, query, { ...options, pushHistory: false });
  }

  async function restoreGraphState(state) {
    if (!state || typeof state !== 'object') return false;

    if (state.kind === 'tree') {
      return renderDefaultTree({ pushHistory: false });
    }

    if (state.kind === 'disease_class_tree') {
      return renderDiseaseClassTree({
        query: state.classQuery || state.query,
        queryType: 'disease_class',
        classQuery: state.classQuery || state.query,
      }, { pushHistory: false });
    }

    if (state.kind === 'query') {
      window.currentKeyNodeLevel = Math.max(1, Math.min(10, Number(state.keyNodeLevel) || 1));
      window.fixedView = !!state.fixedView;
      window.showLabels = !!state.showLabels;
      updateButtons();
      return loadDynamicGraph({
        query: state.query,
        queryType: state.queryType,
        classQuery: state.classQuery,
      }, { pushHistory: false });
    }

    if (state.kind === 'answer') {
      window.currentKeyNodeLevel = Math.max(1, Math.min(10, Number(state.keyNodeLevel) || 1));
      window.fixedView = !!state.fixedView;
      window.showLabels = !!state.showLabels;
      updateButtons();
      return renderAnswerGraphElements(state.elements || [], state.query || 'LINE1', { pushHistory: false });
    }

    return false;
  }

  async function goBackGraph() {
    if (!graphHistory.length) return false;
    const previousState = graphHistory.pop();
    updateButtons();
    return restoreGraphState(previousState);
  }

  async function renderDefaultTree(options = {}) {
    if (options.pushHistory === true && currentMode !== 'tree') {
      pushCurrentStateToHistory();
    }
    currentMode = 'tree';
    currentGraphSource = 'tree';
    currentGraphQuery = '';
    currentGraphQueryType = '';
    currentGraphClassQuery = '';
    currentSelectedNode = null;
    currentAnswerGraphElements = [];
    currentQueryGraphElements = [];
    showTreeSurface();
    updateButtons();
    setGraphLoading(true, textSet().loadingOverlay('tree'));

    try {
      if (window.__TEKG_G6_DEFAULT_TREE && typeof window.__TEKG_G6_DEFAULT_TREE.render === 'function') {
        await window.__TEKG_G6_DEFAULT_TREE.render();
      }
    } finally {
      setGraphLoading(false);
    }

    setDetail(textSet().treeDetail);
    notifyStateChange();
  }

  async function renderDiseaseClassTree(requestLike, options = {}) {
    const request = normalizeGraphRequest({
      ...(requestLike && typeof requestLike === 'object' ? requestLike : {}),
      queryType: 'disease_class',
    });
    const classQuery = String(request.classQuery || request.query || '').trim();
    if (!classQuery) {
      return renderDefaultTree(options);
    }

    if (options.pushHistory !== false) {
      pushCurrentStateToHistory();
    }

    currentMode = 'disease_class_tree';
    currentGraphSource = 'disease_class_tree';
    currentGraphQuery = classQuery;
    currentGraphQueryType = 'disease_class';
    currentGraphClassQuery = classQuery;
    currentSelectedNode = null;
    currentAnswerGraphElements = [];
    currentQueryGraphElements = [];
    showTreeSurface();
    if (els.searchInput) els.searchInput.value = classQuery;
    updateButtons();
    setDetail(buildLoadingDetailHtml(`Preparing the disease classification tree for ${escapeHtml(classQuery)}.`));
    notifyStateChange();
    setGraphLoading(true, textSet().loadingOverlay(classQuery));

    try {
      const payload = await fetchDiseaseClassPayload(request);
      currentQueryGraphElements = cloneAnswerElements(Array.isArray(payload && payload.elements) ? payload.elements : []);
      const model = buildDiseaseClassTreeModel(currentQueryGraphElements, classQuery);
      if (!model.rootId || !model.treeData) {
        throw new Error('Disease class tree data is unavailable.');
      }
      if (!window.__TEKG_G6_DEFAULT_TREE || typeof window.__TEKG_G6_DEFAULT_TREE.renderStructuredTree !== 'function') {
        throw new Error('Structured tree renderer is unavailable.');
      }
      await window.__TEKG_G6_DEFAULT_TREE.renderStructuredTree({
        rootId: model.rootId,
        treeData: model.treeData,
        config: buildDiseaseClassTreeConfig(classQuery),
      });
      notifyStateChange();
      return true;
    } finally {
      setGraphLoading(false);
    }
  }

  async function loadDynamicGraph(requestLike, options = {}) {
    const request = normalizeGraphRequest(requestLike);
    const q = String(request.query || '').trim();
    if (!q) {
      await renderDefaultTree(options);
      return null;
    }

    if (options.pushHistory !== false) {
      pushCurrentStateToHistory();
    }

    currentMode = 'dynamic';
    currentGraphSource = 'query';
    currentGraphQuery = q;
    currentGraphQueryType = request.queryType || '';
    currentGraphClassQuery = currentGraphQueryType === 'disease_class' ? String(request.classQuery || q).trim() : '';
    currentSelectedNode = null;
    currentAnswerGraphElements = [];
    currentQueryGraphElements = [];
    showDynamicSurface();
    if (els.searchInput) els.searchInput.value = q;
    updateButtons();
    setDetail(textSet().loadingDetail(q));
    notifyStateChange();
    setGraphLoading(true, textSet().loadingOverlay(q));

    try {
      await waitForDynamicSurfaceSize();
      const frame = ensureDynamicFrame(request);
      if (!dynamicBridgePromise) {
        dynamicBridgePromise = waitForEmbedBridge(frame);
      }

      const bridge = await dynamicBridgePromise;
      if (!bridge || typeof bridge.loadGraph !== 'function') {
        throw new Error('G6 embed bridge cannot load graph requests');
      }

      const payload = await bridge.loadGraph(request, {
        graphDataOptions: buildCurrentGraphDataOptions(),
      });
      currentQueryGraphElements = cloneAnswerElements(Array.isArray(payload && payload.elements) ? payload.elements : []);
      if (Object.values(getVisibleTypePayload()).some((isVisible) => isVisible === false)) {
        await renderDynamicElementsFromCache(currentQueryGraphElements, {
          source: 'query',
          request,
        });
      } else {
        notifyStateChange();
      }
      return true;
    } finally {
      setGraphLoading(false);
    }
  }

  function bindEvents() {
    renderGraphLegend();
    syncLegendVisibility(currentMode);
    window.addEventListener('tekg:g6-state-change', (event) => {
      const nextMode = event && event.detail && typeof event.detail.mode === 'string' ? event.detail.mode : currentMode;
      syncLegendVisibility(nextMode);
    });

    if (els.graphLegendList) {
      els.graphLegendList.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || !target.classList.contains('graph-legend-check')) return;
        const type = String(target.dataset.type || '').trim();
        if (!type) return;
        ensureVisibleTypeState()[type] = target.checked;
        applyLegendTypeFilter().catch((error) => {
          setDetail(`<strong>${textSet().graphError(error && error.message)}</strong>`);
        });
      });
    }

    if (els.searchInput) {
      els.searchInput.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;
        loadDynamicGraph(els.searchInput.value).catch((error) => {
          setDetail(`<strong>${textSet().graphError(error && error.message)}</strong>`);
        });
      });
    }

    if (els.resetBtn) {
      els.resetBtn.addEventListener('click', () => {
        renderDefaultTree({ pushHistory: true }).catch((error) => {
          setDetail(`<strong>${textSet().graphError(error && error.message)}</strong>`);
        });
      });
    }

    if (els.backBtn) {
      els.backBtn.addEventListener('click', () => {
        goBackGraph().catch((error) => {
          setDetail(`<strong>${textSet().graphError(error && error.message)}</strong>`);
        });
      });
    }

    if (els.fixedBtn) {
      els.fixedBtn.addEventListener('click', () => {
        window.fixedView = !window.fixedView;
        updateButtons();
        notifyStateChange();
        if (currentMode === 'dynamic') {
          loadDynamicGraph(buildCurrentGraphRequest(), { pushHistory: false }).catch((error) => {
            setDetail(`<strong>${textSet().graphError(error && error.message)}</strong>`);
          });
        }
      });
    }

    if (els.showLabelsBtn) {
      els.showLabelsBtn.addEventListener('click', () => {
        window.showLabels = !window.showLabels;
        updateButtons();
        notifyStateChange();
        if (currentMode !== 'dynamic') return;
        if (currentGraphSource === 'answer') {
          renderAnswerGraphElements(currentAnswerGraphElements, currentGraphQuery || 'LINE1', { pushHistory: false }).catch((error) => {
            setDetail(`<strong>${textSet().graphError(error && error.message)}</strong>`);
          });
          return;
        }
        loadDynamicGraph(buildCurrentGraphRequest(), { pushHistory: false }).catch((error) => {
          setDetail(`<strong>${textSet().graphError(error && error.message)}</strong>`);
        });
      });
    }

    if (els.levelMinus) {
      els.levelMinus.addEventListener('click', () => {
        if (window.currentKeyNodeLevel <= 1) return;
        window.currentKeyNodeLevel -= 1;
        updateButtons();
        notifyStateChange();
        if (currentMode === 'dynamic' && currentGraphQuery) {
          loadDynamicGraph(buildCurrentGraphRequest(), { pushHistory: false }).catch((error) => {
            setDetail(`<strong>${textSet().graphError(error && error.message)}</strong>`);
          });
        }
      });
    }

    if (els.levelPlus) {
      els.levelPlus.addEventListener('click', () => {
        if (window.currentKeyNodeLevel >= 10) return;
        window.currentKeyNodeLevel += 1;
        updateButtons();
        notifyStateChange();
        if (currentMode === 'dynamic' && currentGraphQuery) {
          loadDynamicGraph(buildCurrentGraphRequest(), { pushHistory: false }).catch((error) => {
            setDetail(`<strong>${textSet().graphError(error && error.message)}</strong>`);
          });
        }
      });
    }

    if (els.zhBtn) {
      els.zhBtn.addEventListener('click', () => {
        window.currentLang = 'zh';
        syncLangParam();
        updateButtons();
        notifyStateChange();
        if (currentMode === 'dynamic' && currentGraphQuery) {
          loadDynamicGraph(buildCurrentGraphRequest(), { pushHistory: false }).catch((error) => {
            setDetail(`<strong>${textSet().graphError(error && error.message)}</strong>`);
          });
          return;
        }
        if (currentMode === 'disease_class_tree' && currentGraphClassQuery) {
          renderDiseaseClassTree({
            query: currentGraphClassQuery,
            queryType: 'disease_class',
            classQuery: currentGraphClassQuery,
          }, { pushHistory: false }).catch((error) => {
            setDetail(`<strong>${textSet().graphError(error && error.message)}</strong>`);
          });
          return;
        }
        renderDefaultTree().catch((error) => {
          setDetail(`<strong>${textSet().graphError(error && error.message)}</strong>`);
        });
      });
    }

    if (els.enBtn) {
      els.enBtn.addEventListener('click', () => {
        window.currentLang = 'en';
        syncLangParam();
        updateButtons();
        notifyStateChange();
        if (currentMode === 'dynamic' && currentGraphQuery) {
          loadDynamicGraph(buildCurrentGraphRequest(), { pushHistory: false }).catch((error) => {
            setDetail(`<strong>${textSet().graphError(error && error.message)}</strong>`);
          });
          return;
        }
        if (currentMode === 'disease_class_tree' && currentGraphClassQuery) {
          renderDiseaseClassTree({
            query: currentGraphClassQuery,
            queryType: 'disease_class',
            classQuery: currentGraphClassQuery,
          }, { pushHistory: false }).catch((error) => {
            setDetail(`<strong>${textSet().graphError(error && error.message)}</strong>`);
          });
          return;
        }
        renderDefaultTree().catch((error) => {
          setDetail(`<strong>${textSet().graphError(error && error.message)}</strong>`);
        });
      });
    }
  }

  window.__TEKG_G6_GRAPH_HOST = {
    setDetail(title, description) {
      if (currentMode !== 'dynamic') return;
      setGraphLoading(false);
      setDetail(buildDetail(title, description));
    },
    setDetailHtml(html) {
      if (currentMode !== 'dynamic') return;
      setGraphLoading(false);
      setDetail(html || '');
    },
    setStatus(_text) {},
    setMode(mode, payload) {
      if (mode === 'dynamic') {
        const request = normalizeGraphRequest(payload);
        const nextQuery = String(request.query || '').trim();
        const nextQueryType = request.queryType || '';
        const nextClassQuery = nextQueryType === 'disease_class'
          ? String(request.classQuery || nextQuery || '').trim()
          : '';
        const nextSource = payload && payload.source === 'qa' ? 'answer' : 'query';

        const hasGraphState = currentMode === 'dynamic' && !!String(currentGraphQuery || '').trim();
        const queryChanged =
          String(currentGraphQuery || '').trim() !== nextQuery ||
          String(currentGraphQueryType || '') !== nextQueryType ||
          String(currentGraphClassQuery || '') !== nextClassQuery ||
          String(currentGraphSource || '') !== nextSource;

        if (hasGraphState && queryChanged) {
          pushCurrentStateToHistory();
          updateBackButton();
        }

        currentMode = 'dynamic';
        currentGraphSource = nextSource;
        currentGraphQuery = nextQuery || String(currentGraphQuery || '').trim();
        currentGraphQueryType = nextQueryType;
        currentGraphClassQuery = nextClassQuery;
        notifyStateChange();
      }
    },
    onReady() {},
    onNodeSelect(node) {
      setGraphLoading(false);
      currentSelectedNode = node || null;
      notifyStateChange();
    },
    onDiseaseClassClick(node, request) {
      const classQuery = String(
        (request && request.classQuery)
        || (node && (node.classQuery || node.diseaseClass || node.queryLabel || node.displayLabel || node.rawLabel))
        || ''
      ).trim();
      if (!classQuery) return Promise.resolve(false);
      return renderDiseaseClassTree({
        query: classQuery,
        queryType: 'disease_class',
        classQuery,
      }, { pushHistory: true }).then(() => true);
    },
  };

  window.__TEKG_LOAD_DYNAMIC_GRAPH = loadDynamicGraph;
  window.__TEKG_G6_SHOW_TREE = renderDefaultTree;
  window.__TEKG_G6_BRIDGE = {
    loadGraph(query) {
      return loadDynamicGraph(query);
    },
    applyAnswerGraph(result) {
      return applyAnswerGraph(result);
    },
    goBack() {
      return goBackGraph();
    },
    canGoBack() {
      return graphHistory.length > 0;
    },
    showTree() {
      return renderDefaultTree();
    },
    showDiseaseClassTree(requestLike) {
      return renderDiseaseClassTree(requestLike);
    },
    reset() {
      return renderDefaultTree();
    },
    setKeyNodeLevel(level) {
      window.currentKeyNodeLevel = Math.max(1, Math.min(10, Number(level) || 1));
      updateButtons();
      if (currentMode === 'dynamic' && currentGraphQuery) {
        return loadDynamicGraph(buildCurrentGraphRequest(), { pushHistory: false });
      }
      return Promise.resolve();
    },
    setFixedView(next) {
      window.fixedView = !!next;
      updateButtons();
      if (currentMode === 'dynamic') {
        return loadDynamicGraph(buildCurrentGraphRequest(), { pushHistory: false }).then(() => window.fixedView);
      }
      return Promise.resolve(window.fixedView);
    },
    getFixedView() {
      return !!window.fixedView;
    },
    setShowLabels(next) {
      window.showLabels = !!next;
      updateButtons();
      if (currentMode === 'dynamic') {
        if (currentGraphSource === 'answer') {
          return renderAnswerGraphElements(currentAnswerGraphElements, currentGraphQuery || 'LINE1', { pushHistory: false }).then(() => window.showLabels);
        }
        return loadDynamicGraph(buildCurrentGraphRequest(), { pushHistory: false }).then(() => window.showLabels);
      }
      return Promise.resolve(window.showLabels);
    },
    getShowLabels() {
      return !!window.showLabels;
    },
    getKeyNodeLevel() {
      return window.currentKeyNodeLevel;
    },
    getMode() {
      return currentMode;
    },
    getCurrentQuery() {
      return currentGraphQuery;
    },
    getCurrentRequest() {
      return buildCurrentGraphRequest();
    },
    getSelectedNode() {
      return currentSelectedNode;
    },
    getState() {
      return snapshotState();
    },
  };

  Promise.resolve()
    .then(applyPageMode)
    .then(() => {
      updateButtons();
      bindEvents();

      if (initialQuery) {
        return loadSharedResources().then(() => loadDynamicGraph({
          query: initialQuery,
          queryType: initialQueryType,
          classQuery: initialClassQuery || initialQuery,
        }, { pushHistory: false }));
      }

      renderDefaultTree().catch((error) => {
        setDetail(`<strong>${textSet().graphError(error && error.message)}</strong>`);
      });
      return loadSharedResources();
    })
    .catch((error) => {
      console.error('Formal G6 bootstrap failed:', error);
      updateButtons();
      setDetail(`<strong>${textSet().graphError(error && error.message)}</strong>`);
    });
}());
