(function () {
  if (window.__TEKG_RENDERER_MODE !== 'g6') return;

  const params = new URLSearchParams(window.location.search);
  const embedMode = typeof window.__TEKG_EMBED_MODE === 'string' ? window.__TEKG_EMBED_MODE : '';
  const initialQuery = String(window.__TEKG_INITIAL_QUERY || '').trim();

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
      graphError: (message) => `\u5931\u8d25\uff1a${message || '\u672a\u77e5\u9519\u8bef'}`,
    },
  };

  let currentMode = 'tree';
  let currentGraphQuery = initialQuery;
  let runtimeInitPromise = null;

  window.currentLang = params.get('lang') === 'zh' ? 'zh' : 'en';
  window.fixedView = false;
  window.currentKeyNodeLevel = 1;
  window.focusLevel = 0;
  if (typeof window.cy === 'undefined') {
    window.cy = { nodes: () => [] };
  }

  function textSet() {
    return UI[window.currentLang] || UI.en;
  }

  function setDetail(html) {
    if (els.detail) {
      els.detail.innerHTML = html || '';
    }
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

  function applyPageMode() {
    document.body.classList.add('tekg-g6-preview-ready');
    if (embedMode === 'preview' && els.main) {
      els.main.style.gridTemplateColumns = 'minmax(0,1fr)';
    }
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

  function showTreeSurface() {
    if (els.treeSurface) els.treeSurface.style.display = 'block';
    if (els.dynamicSurface) els.dynamicSurface.style.display = 'none';
  }

  function showDynamicSurface() {
    if (els.treeSurface) els.treeSurface.style.display = 'none';
    if (els.dynamicSurface) els.dynamicSurface.style.display = 'block';
  }

  async function renderDefaultTree() {
    currentMode = 'tree';
    currentGraphQuery = '';
    showTreeSurface();

    if (window.__TEKG_G6_RUNTIME && typeof window.__TEKG_G6_RUNTIME.destroy === 'function') {
      window.__TEKG_G6_RUNTIME.destroy();
    }

    if (window.__TEKG_G6_DEFAULT_TREE && typeof window.__TEKG_G6_DEFAULT_TREE.render === 'function') {
      await window.__TEKG_G6_DEFAULT_TREE.render();
    }

    setDetail(textSet().treeDetail);
  }

  function ensureRuntimeInitialized() {
    if (runtimeInitPromise) return runtimeInitPromise;

    if (!window.__TEKG_G6_RUNTIME || typeof window.__TEKG_G6_RUNTIME.init !== 'function') {
      return Promise.reject(new Error('G6 runtime is not available'));
    }

    runtimeInitPromise = Promise.resolve(
      window.__TEKG_G6_RUNTIME.init({
        containerId: 'g6-dynamic-surface',
        queryInputId: 'node-search',
        levelMinusId: 'decrease-key-node-level',
        levelDisplayId: 'key-node-level-text',
        levelPlusId: 'increase-key-node-level',
        fixedBtnId: 'toggle-fixed-view',
        detailId: 'node-details',
        fixedView: window.fixedView,
        keyNodeLevel: window.currentKeyNodeLevel,
        initialQuery,
      })
    ).catch((error) => {
      runtimeInitPromise = null;
      throw error;
    });

    return runtimeInitPromise;
  }

  async function loadDynamicGraph(query) {
    const q = String(query || '').trim();
    if (!q) {
      await renderDefaultTree();
      return null;
    }

    currentMode = 'dynamic';
    currentGraphQuery = q;

    showDynamicSurface();
    await new Promise((resolve) => requestAnimationFrame(resolve));

    if (els.searchInput) els.searchInput.value = q;

    await ensureRuntimeInitialized();

    if (!window.__TEKG_G6_RUNTIME || typeof window.__TEKG_G6_RUNTIME.loadGraph !== 'function') {
      throw new Error('G6 runtime is not available');
    }

    window.__TEKG_G6_RUNTIME.setFixedView(window.fixedView);
    window.__TEKG_G6_RUNTIME.setKeyNodeLevel(window.currentKeyNodeLevel);
    return window.__TEKG_G6_RUNTIME.loadGraph(q);
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
        if (window.__TEKG_G6_RUNTIME) {
          window.__TEKG_G6_RUNTIME.setFixedView(window.fixedView);
        }
        updateButtons();
      });
    }

    if (els.levelMinus) {
      els.levelMinus.addEventListener('click', () => {
        if (window.currentKeyNodeLevel <= 1) return;
        window.currentKeyNodeLevel -= 1;
        if (window.__TEKG_G6_RUNTIME) {
          window.__TEKG_G6_RUNTIME.setKeyNodeLevel(window.currentKeyNodeLevel);
        }
        updateButtons();
        if (currentMode === 'dynamic' && currentGraphQuery) {
          loadDynamicGraph(currentGraphQuery).catch((error) => {
            setDetail(`<strong>${textSet().graphError(error && error.message)}</strong>`);
          });
        }
      });
    }

    if (els.levelPlus) {
      els.levelPlus.addEventListener('click', () => {
        if (window.currentKeyNodeLevel >= 10) return;
        window.currentKeyNodeLevel += 1;
        if (window.__TEKG_G6_RUNTIME) {
          window.__TEKG_G6_RUNTIME.setKeyNodeLevel(window.currentKeyNodeLevel);
        }
        updateButtons();
        if (currentMode === 'dynamic' && currentGraphQuery) {
          loadDynamicGraph(currentGraphQuery).catch((error) => {
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
        if (currentMode === 'dynamic' && currentGraphQuery) {
          loadDynamicGraph(currentGraphQuery).catch((error) => {
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
        if (currentMode === 'dynamic' && currentGraphQuery) {
          loadDynamicGraph(currentGraphQuery).catch((error) => {
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

  window.__TEKG_LOAD_DYNAMIC_GRAPH = loadDynamicGraph;
  window.__TEKG_G6_SHOW_TREE = renderDefaultTree;

  Promise.resolve()
    .then(applyPageMode)
    .then(() => {
      updateButtons();
      bindEvents();

      if (initialQuery) {
        return loadSharedResources().then(() => loadDynamicGraph(initialQuery));
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
