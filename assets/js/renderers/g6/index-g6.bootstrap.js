(function () {
  if (window.__TEKG_RENDERER_MODE !== 'g6') return;

  const pageParams = new URLSearchParams(window.location.search);
  if (!pageParams.get('renderer')) {
    const next = new URL(window.location.href);
    next.searchParams.set('renderer', 'g6');
    window.history.replaceState({}, '', next.toString());
  }

  if (typeof window.cy === 'undefined') {
    window.cy = { nodes: () => [] };
  }

  const els = {
    zhBtn: document.getElementById('lang-zh'),
    enBtn: document.getElementById('lang-en'),
    title: document.getElementById('page-title'),
    badge: document.getElementById('page-badge'),
    graphTitle: document.getElementById('graph-title'),
    search: document.getElementById('node-search'),
    focusBtn: document.getElementById('toggle-focus-view'),
    focusText: document.getElementById('focus-view-text'),
    fixedBtn: document.getElementById('toggle-fixed-view'),
    fixedText: document.getElementById('fixed-view-text'),
    resetBtn: document.getElementById('reset-graph'),
    resetText: document.getElementById('reset-text'),
    levelMinus: document.getElementById('decrease-key-node-level'),
    levelPlus: document.getElementById('increase-key-node-level'),
    levelText: document.getElementById('key-node-level-text'),
    detail: document.getElementById('node-details'),
  };

  const UI_TEXT = {
    en: {
      pageTitle: 'TEKG G6 Workspace',
      badge: 'Independent G6 entry',
      graphTitle: 'G6 Graph Workspace',
      searchPlaceholder: 'Search LINE1, L1HS, disease, or function',
      focusGlobal: 'Focus mode: Global',
      focusLocal: 'Focus mode: Local',
      fixedOn: 'Fixed view: On',
      fixedOff: 'Fixed view: Off',
      reset: 'Reset',
      keyNodeLevel: (level) => `Key-node level: ${level}`,
      ready: 'G6 workspace is ready.',
      notFound: 'No matching graph fragment was found.',
      loading: 'Loading graph…',
      fallback: 'Falling back to the default G6 tree.',
    },
    zh: {
      pageTitle: 'TEKG G6 工作台',
      badge: '独立 G6 入口',
      graphTitle: 'G6 图谱工作台',
      searchPlaceholder: '搜索 LINE1、L1HS、疾病或功能',
      focusGlobal: '聚焦模式：全局',
      focusLocal: '聚焦模式：局部',
      fixedOn: '固定模式：开',
      fixedOff: '固定模式：关',
      reset: '重置',
      keyNodeLevel: (level) => `关键节点级数：${level}`,
      ready: 'G6 工作台已就绪。',
      notFound: '没有找到匹配的图谱片段。',
      loading: '正在加载图谱…',
      fallback: '已回退到默认 G6 树图。',
    },
  };

  let searchDebounceId = null;
  let currentResultElements = null;
  let currentResultLabel = '';

  function getLang() {
    return typeof currentLang === 'string' ? currentLang : 'en';
  }

  function setDetail(text) {
    if (els.detail) els.detail.textContent = text;
  }

  function syncButtons() {
    if (els.zhBtn) els.zhBtn.classList.toggle('active', getLang() === 'zh');
    if (els.enBtn) els.enBtn.classList.toggle('active', getLang() === 'en');
  }

  function updateUi() {
    const t = UI_TEXT[getLang()] || UI_TEXT.en;
    if (els.title) els.title.textContent = t.pageTitle;
    if (els.badge) els.badge.textContent = t.badge;
    if (els.graphTitle) els.graphTitle.textContent = t.graphTitle;
    if (els.search) els.search.placeholder = t.searchPlaceholder;
    if (els.focusText) {
      els.focusText.textContent = typeof focusLevel !== 'undefined' && focusLevel === 100
        ? t.focusLocal
        : t.focusGlobal;
    }
    if (els.fixedText) {
      els.fixedText.textContent = typeof fixedView !== 'undefined' && fixedView === true
        ? t.fixedOn
        : t.fixedOff;
    }
    if (els.resetText) els.resetText.textContent = t.reset;
    if (els.levelText) {
      const level = typeof currentKeyNodeLevel !== 'undefined' ? currentKeyNodeLevel : 1;
      els.levelText.textContent = t.keyNodeLevel(level);
    }
    syncButtons();
  }

  async function loadSharedResources() {
    const tasks = [];
    if (typeof loadTerminology === 'function') tasks.push(loadTerminology());
    if (typeof loadTeDescriptions === 'function') tasks.push(loadTeDescriptions());
    if (typeof loadEntityDescriptions === 'function') tasks.push(loadEntityDescriptions());
    if (typeof loadUiText === 'function') tasks.push(loadUiText());
    if (typeof loadLocalQaTemplates === 'function') tasks.push(loadLocalQaTemplates());
    await Promise.all(tasks);
  }

  async function renderDefaultTree() {
    currentGraphKind = 'default-tree';
    currentResultElements = null;
    currentResultLabel = '';
    if (window.__TEKG_G6_DYNAMIC_GRAPH && typeof window.__TEKG_G6_DYNAMIC_GRAPH.destroy === 'function') {
      window.__TEKG_G6_DYNAMIC_GRAPH.destroy();
    }
    if (window.__TEKG_G6_DEFAULT_TREE && typeof window.__TEKG_G6_DEFAULT_TREE.render === 'function') {
      await window.__TEKG_G6_DEFAULT_TREE.render();
    }
    updateUi();
  }

  async function loadDynamicGraph(query) {
    const q = String(query || '').trim();
    if (!q) {
      await renderDefaultTree();
      return null;
    }
    const t = UI_TEXT[getLang()] || UI_TEXT.en;
    setDetail(t.loading);
    const response = await fetch(`api/graph.php?q=${encodeURIComponent(q)}&level=${encodeURIComponent(typeof currentKeyNodeLevel !== 'undefined' ? currentKeyNodeLevel : 1)}`, {
      cache: 'no-store',
    });
    let payload = null;
    try {
      payload = await response.json();
    } catch (error) {
      throw new Error('Invalid graph response');
    }
    if (!response.ok || !payload || payload.ok === false) {
      throw new Error((payload && payload.error) || `Graph request failed (${response.status})`);
    }
    if (!Array.isArray(payload.elements) || payload.elements.length === 0) {
      setDetail(t.notFound);
      return null;
    }
    currentGraphKind = 'dynamic';
    currentResultElements = payload.elements;
    currentResultLabel = payload.anchor?.name || q;
    if (window.__TEKG_G6_DYNAMIC_GRAPH && typeof window.__TEKG_G6_DYNAMIC_GRAPH.render === 'function') {
      await window.__TEKG_G6_DYNAMIC_GRAPH.render(payload.elements, currentResultLabel, payload);
    }
    updateUi();
    return payload;
  }

  window.__TEKG_LOAD_DYNAMIC_GRAPH = loadDynamicGraph;

  function bindLang() {
    if (els.zhBtn) {
      els.zhBtn.addEventListener('click', async () => {
        currentLang = 'zh';
        updateUi();
        if (currentGraphKind === 'dynamic' && currentResultElements && window.__TEKG_G6_DYNAMIC_GRAPH?.rerender) {
          await window.__TEKG_G6_DYNAMIC_GRAPH.rerender();
        } else {
          await renderDefaultTree();
        }
      });
    }
    if (els.enBtn) {
      els.enBtn.addEventListener('click', async () => {
        currentLang = 'en';
        updateUi();
        if (currentGraphKind === 'dynamic' && currentResultElements && window.__TEKG_G6_DYNAMIC_GRAPH?.rerender) {
          await window.__TEKG_G6_DYNAMIC_GRAPH.rerender();
        } else {
          await renderDefaultTree();
        }
      });
    }
  }

  function bindSearch() {
    if (!els.search) return;
    els.search.addEventListener('keydown', async (event) => {
      if (event.key !== 'Enter') return;
      event.preventDefault();
      const q = els.search.value.trim();
      try {
        await loadDynamicGraph(q);
      } catch (error) {
        setDetail(error && error.message ? error.message : 'Graph request failed');
      }
    });
    els.search.addEventListener('input', () => {
      clearTimeout(searchDebounceId);
      searchDebounceId = setTimeout(async () => {
        const q = els.search.value.trim();
        if (!q) {
          await renderDefaultTree();
        }
      }, 180);
    });
  }

  function bindFocusAndFixed() {
    if (els.focusBtn) {
      els.focusBtn.addEventListener('click', () => {
        focusLevel = focusLevel === 100 ? 0 : 100;
        updateUi();
      });
    }
    if (els.fixedBtn) {
      els.fixedBtn.addEventListener('click', () => {
        fixedView = !fixedView;
        updateUi();
      });
    }
  }

  function bindKeyNodeLevel() {
    if (els.levelMinus) {
      els.levelMinus.addEventListener('click', () => {
        currentKeyNodeLevel = Math.max(1, (currentKeyNodeLevel || 1) - 1);
        updateUi();
      });
    }
    if (els.levelPlus) {
      els.levelPlus.addEventListener('click', () => {
        currentKeyNodeLevel = Math.min(4, (currentKeyNodeLevel || 1) + 1);
        updateUi();
      });
    }
  }

  function bindReset() {
    if (!els.resetBtn) return;
    els.resetBtn.addEventListener('click', async () => {
      if (els.search) els.search.value = '';
      await renderDefaultTree();
      setDetail((UI_TEXT[getLang()] || UI_TEXT.en).fallback);
    });
  }

  async function initialize() {
    try {
      await loadSharedResources();
      updateUi();
      bindLang();
      bindSearch();
      bindFocusAndFixed();
      bindKeyNodeLevel();
      bindReset();
      window.dispatchEvent(new CustomEvent('tekg:shared-ready'));
      await renderDefaultTree();
      setDetail((UI_TEXT[getLang()] || UI_TEXT.en).ready);
    } catch (error) {
      setDetail(error && error.message ? error.message : 'Failed to initialize G6 workspace');
      console.error('Failed to initialize G6 workspace:', error);
    }
  }

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    setTimeout(initialize, 0);
  } else {
    document.addEventListener('DOMContentLoaded', () => setTimeout(initialize, 0), { once: true });
  }
}());
