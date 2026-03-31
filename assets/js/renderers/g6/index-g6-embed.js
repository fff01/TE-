(function () {
  const shared = window.__TEKG_G6_SHARED;
  if (!shared || typeof shared.createRunner !== 'function') return;

  const container = document.getElementById('container');
  if (!container) return;

  const params = new URLSearchParams(window.location.search);

  function getHost() {
    try {
      return window.parent && window.parent !== window ? window.parent.__TEKG_G6_GRAPH_HOST : null;
    } catch (_error) {
      return null;
    }
  }

  function pushDetail(title, description) {
    const host = getHost();
    if (host && typeof host.setDetail === 'function') host.setDetail(title, description);
  }

  function pushStatus(text) {
    const host = getHost();
    if (host && typeof host.setStatus === 'function') host.setStatus(text);
  }

  function pushMode(mode, query) {
    const host = getHost();
    if (host && typeof host.setMode === 'function') host.setMode(mode, query);
  }

  function pushSelection(node) {
    const host = getHost();
    if (host && typeof host.onNodeSelect === 'function') host.onNodeSelect(node || null);
  }

  function pushReady() {
    const host = getHost();
    if (host && typeof host.onReady === 'function') host.onReady();
  }

  const runner = shared.createRunner({
    container,
    initialFixedView: params.get('fixed') === '1',
    initialKeyNodeLevel: Math.max(1, Math.min(10, Number(params.get('key_level')) || 1)),
    initialQuery: String(params.get('q') || '').trim(),
    initialLang: params.get('lang') === 'zh' ? 'zh' : 'en',
    syncRouteState: ({ query, keyNodeLevel, fixedView, lang }) => {
      const next = new URLSearchParams(window.location.search);
      if (query) next.set('q', query);
      else next.delete('q');
      next.set('key_level', String(keyNodeLevel));
      next.set('fixed', fixedView ? '1' : '0');
      next.set('lang', lang === 'zh' ? 'zh' : 'en');
      window.history.replaceState({}, '', `${window.location.pathname}?${next.toString()}`);
    },
    setStatus: pushStatus,
    setDetail: pushDetail,
    setMode: pushMode,
    onSelection: pushSelection,
    onReady: pushReady,
  });

  const bridge = {
    loadGraph(query) {
      return runner.loadGraph(query);
    },
    setFixedView(next) {
      return runner.setFixedView(next);
    },
    setKeyNodeLevel(level) {
      return runner.setKeyNodeLevel(level);
    },
    setLanguage(lang) {
      return runner.setLanguage(lang);
    },
    resize() {
      runner.resize();
      return Promise.resolve();
    },
    getCurrentQuery() {
      return runner.getCurrentQuery();
    },
    getFixedView() {
      return runner.getFixedView();
    },
    getKeyNodeLevel() {
      return runner.getKeyNodeLevel();
    },
  };

  window.__TEKG_G6_EMBED = bridge;

  runner.init().finally(() => {
    if (runner.getCurrentQuery()) {
      runner.loadGraph(runner.getCurrentQuery()).catch(() => {});
    }
  });
}());
