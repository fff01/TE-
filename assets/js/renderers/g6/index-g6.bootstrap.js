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
    fixedBtn: document.getElementById('toggle-fixed-view'),
    resetBtn: document.getElementById('reset-graph'),
    levelMinus: document.getElementById('decrease-key-node-level'),
    levelPlus: document.getElementById('increase-key-node-level'),
    levelText: document.getElementById('key-node-level-text'),
    detail: document.getElementById('node-details'),
    treeSurface: document.getElementById('g6-default-tree-surface'),
    dynamicSurface: document.getElementById('g6-dynamic-surface'),
    main: document.querySelector('.main'),
  };

  const UI = {
    en: {
      pageTitle: 'TEKG G6 Workspace',
      badge: 'Tree-first preview with test-aligned dynamic graph',
      graphTitle: 'G6 Graph Workspace',
      searchPlaceholder: 'Search LINE1, L1HS, disease, or function',
      fixedOn: 'Fixed view: On',
      fixedOff: 'Fixed view: Off',
      reset: 'Reset',
      keyNodeLevel: (level) => `Key-node level: ${level}`,
      treeDetail:
        '<strong>No node selected</strong>Click a TE node to inspect it, then click again to enter the dynamic graph.',
      loadingDetail: (query) => `<strong>Loading graph</strong>Preparing the dynamic graph for ${escapeHtml(query)}.`,
      graphError: (message) => `Failed: ${message || 'unknown error'}`,
    },
    zh: {
      pageTitle: 'TEKG G6 Workspace',
      badge: '\u4ee5\u5206\u7c7b\u6811\u4e3a\u9ed8\u8ba4\u5165\u53e3\u7684 G6 \u9884\u89c8',
      graphTitle: 'G6 Graph Workspace',
      searchPlaceholder: '\u641c\u7d22 LINE1\u3001L1HS\u3001\u75be\u75c5\u6216\u529f\u80fd',
      fixedOn: '\u56fa\u5b9a\u89c6\u56fe\uff1a\u5f00',
      fixedOff: '\u56fa\u5b9a\u89c6\u56fe\uff1a\u5173',
      reset: '\u91cd\u7f6e',
      keyNodeLevel: (level) => `\u5173\u952e\u8282\u70b9\u5c42\u6570\uff1a${level}`,
      treeDetail:
        '<strong>\u5c1a\u672a\u9009\u4e2d\u8282\u70b9</strong>\u70b9\u51fb\u4e00\u4e2a TE \u8282\u70b9\u67e5\u770b\u8be6\u60c5\uff0c\u518d\u6b21\u70b9\u51fb\u53ef\u8fdb\u5165\u52a8\u6001\u56fe\u3002',
      loadingDetail: (query) => `<strong>\u6b63\u5728\u52a0\u8f7d\u52a8\u6001\u56fe</strong>\u6b63\u5728\u4e3a ${escapeHtml(query)} \u51c6\u5907 G6 \u52a8\u6001\u56fe\u3002`,
      graphError: (message) => `\u5931\u8d25\uff1a${message || '\u672a\u77e5\u9519\u8bef'}`,
    },
  };

  let currentMode = 'tree';
  let currentGraphQuery = '';
  let currentGraphQueryType = '';
  let currentGraphClassQuery = '';
  let currentSelectedNode = null;
  let dynamicFrame = null;
  let dynamicBridgePromise = null;

  window.currentLang = params.get('lang') === 'zh' ? 'zh' : 'en';
  window.fixedView = false;
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

  function textSet() {
    return UI[window.currentLang] || UI.en;
  }

  function setDetail(html) {
    if (els.detail) {
      els.detail.innerHTML = html || '';
    }
  }

  function snapshotState() {
    return {
      mode: currentMode,
      query: currentGraphQuery,
      queryType: currentGraphQueryType,
      classQuery: currentGraphClassQuery,
      fixedView: !!window.fixedView,
      keyNodeLevel: window.currentKeyNodeLevel,
      selectedNode: currentSelectedNode,
      lang: window.currentLang,
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
    if (els.fixedBtn) els.fixedBtn.textContent = window.fixedView ? t.fixedOn : t.fixedOff;
    if (els.resetBtn) els.resetBtn.textContent = t.reset;
    if (els.levelText) els.levelText.textContent = t.keyNodeLevel(window.currentKeyNodeLevel);
    if (els.levelMinus) els.levelMinus.disabled = window.currentKeyNodeLevel <= 1;
    if (els.levelPlus) els.levelPlus.disabled = window.currentKeyNodeLevel >= 10;
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
  }

  function showDynamicSurface() {
    if (els.treeSurface) els.treeSurface.style.display = 'none';
    if (els.dynamicSurface) els.dynamicSurface.style.display = 'block';
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

  async function renderDefaultTree() {
    currentMode = 'tree';
    currentGraphQuery = '';
    currentGraphQueryType = '';
    currentGraphClassQuery = '';
    currentSelectedNode = null;
    showTreeSurface();

    if (window.__TEKG_G6_DEFAULT_TREE && typeof window.__TEKG_G6_DEFAULT_TREE.render === 'function') {
      await window.__TEKG_G6_DEFAULT_TREE.render();
    }

    setDetail(textSet().treeDetail);
    notifyStateChange();
  }

  async function loadDynamicGraph(requestLike) {
    const request = normalizeGraphRequest(requestLike);
    const q = String(request.query || '').trim();
    if (!q) {
      await renderDefaultTree();
      return null;
    }

    currentMode = 'dynamic';
    currentGraphQuery = q;
    currentGraphQueryType = request.queryType || '';
    currentGraphClassQuery = currentGraphQueryType === 'disease_class' ? String(request.classQuery || q).trim() : '';
    currentSelectedNode = null;
    showDynamicSurface();
    if (els.searchInput) els.searchInput.value = q;
    setDetail(textSet().loadingDetail(q));
    notifyStateChange();

    await waitForDynamicSurfaceSize();
    ensureDynamicFrame(request);
    return Promise.resolve();
  }

  function bindEvents() {
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
        renderDefaultTree().catch((error) => {
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
          loadDynamicGraph(buildCurrentGraphRequest()).catch((error) => {
            setDetail(`<strong>${textSet().graphError(error && error.message)}</strong>`);
          });
        }
      });
    }

    if (els.levelMinus) {
      els.levelMinus.addEventListener('click', () => {
        if (window.currentKeyNodeLevel <= 1) return;
        window.currentKeyNodeLevel -= 1;
        updateButtons();
        notifyStateChange();
        if (currentMode === 'dynamic' && currentGraphQuery) {
          loadDynamicGraph(buildCurrentGraphRequest()).catch((error) => {
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
          loadDynamicGraph(buildCurrentGraphRequest()).catch((error) => {
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
          loadDynamicGraph(buildCurrentGraphRequest()).catch((error) => {
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
          loadDynamicGraph(buildCurrentGraphRequest()).catch((error) => {
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
      setDetail(buildDetail(title, description));
    },
    setDetailHtml(html) {
      if (currentMode !== 'dynamic') return;
      setDetail(html || '');
    },
    setStatus(_text) {},
    setMode(mode, payload) {
      if (mode === 'dynamic') {
        const request = normalizeGraphRequest(payload);
        currentMode = 'dynamic';
        currentGraphQuery = String(request.query || currentGraphQuery || '').trim();
        currentGraphQueryType = request.queryType || '';
        currentGraphClassQuery = currentGraphQueryType === 'disease_class'
          ? String(request.classQuery || currentGraphQuery || '').trim()
          : '';
        notifyStateChange();
      }
    },
    onReady() {},
    onNodeSelect(node) {
      currentSelectedNode = node || null;
      notifyStateChange();
    },
  };

  window.__TEKG_LOAD_DYNAMIC_GRAPH = loadDynamicGraph;
  window.__TEKG_G6_SHOW_TREE = renderDefaultTree;
  window.__TEKG_G6_BRIDGE = {
    loadGraph(query) {
      return loadDynamicGraph(query);
    },
    showTree() {
      return renderDefaultTree();
    },
    reset() {
      return renderDefaultTree();
    },
    setKeyNodeLevel(level) {
      window.currentKeyNodeLevel = Math.max(1, Math.min(10, Number(level) || 1));
      updateButtons();
      if (currentMode === 'dynamic' && currentGraphQuery) {
        return loadDynamicGraph(buildCurrentGraphRequest());
      }
      return Promise.resolve();
    },
    setFixedView(next) {
      window.fixedView = !!next;
      updateButtons();
      if (currentMode === 'dynamic') {
        return loadDynamicGraph(buildCurrentGraphRequest()).then(() => window.fixedView);
      }
      return Promise.resolve(window.fixedView);
    },
    getFixedView() {
      return !!window.fixedView;
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
        }));
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
