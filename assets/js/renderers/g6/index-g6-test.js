(function () {
  const shared = window.__TEKG_G6_SHARED;
  if (!shared || typeof shared.createRunner !== 'function') return;

  const container = document.getElementById('container');
  const queryInput = document.getElementById('query-input');
  const loadBtn = document.getElementById('load-btn');
  const levelMinusBtn = document.getElementById('level-minus');
  const levelDisplayBtn = document.getElementById('level-display');
  const levelPlusBtn = document.getElementById('level-plus');
  const fixedBtn = document.getElementById('fixed-btn');
  const status = document.getElementById('status');
  const detail = document.getElementById('detail');
  if (!container) return;

  function setStatus(text) {
    if (status) status.textContent = text;
  }

  function setDetail(title, description) {
    if (!detail) return;
    detail.innerHTML = `<strong>${shared.escapeHtml(title)}</strong>${shared.escapeHtml(description || 'No description.')}`;
  }

  const url = new URL(window.location.href);
  const runner = shared.createRunner({
    container,
    initialQuery: String(url.searchParams.get('q') || queryInput?.value || 'LINE1').trim() || 'LINE1',
    initialKeyNodeLevel: Math.max(1, Math.min(10, Number(url.searchParams.get('key_level')) || 1)),
    initialFixedView: url.searchParams.get('fixed') === '1',
    initialLang: url.searchParams.get('lang') === 'zh' ? 'zh' : 'en',
    getQuery: () => (queryInput?.value || '').trim(),
    setQueryUi: (query) => {
      if (queryInput) queryInput.value = query;
    },
    syncRouteState: ({ query, keyNodeLevel, fixedView, lang }) => {
      const next = new URL(window.location.href);
      if (query) next.searchParams.set('q', query);
      else next.searchParams.delete('q');
      next.searchParams.set('key_level', String(keyNodeLevel));
      next.searchParams.set('fixed', fixedView ? '1' : '0');
      next.searchParams.set('lang', lang === 'zh' ? 'zh' : 'en');
      window.history.replaceState({}, '', next.toString());
    },
    setStatus,
    setDetail,
  });

  function updateFixedButton() {
    if (fixedBtn) fixedBtn.textContent = `Fixed view: ${runner.getFixedView() ? 'On' : 'Off'}`;
  }

  function updateLevelControls() {
    if (levelDisplayBtn) levelDisplayBtn.textContent = `Key-node level: ${runner.getKeyNodeLevel()}`;
    if (levelMinusBtn) levelMinusBtn.disabled = runner.getKeyNodeLevel() <= 1;
    if (levelPlusBtn) levelPlusBtn.disabled = runner.getKeyNodeLevel() >= 10;
  }

  function refreshControls() {
    updateFixedButton();
    updateLevelControls();
  }

  if (loadBtn) {
    loadBtn.addEventListener('click', () => {
      runner.loadGraph().catch(() => {});
    });
  }

  if (fixedBtn) {
    fixedBtn.addEventListener('click', () => {
      runner.setFixedView(!runner.getFixedView()).then(refreshControls);
    });
  }

  if (levelMinusBtn) {
    levelMinusBtn.addEventListener('click', () => {
      if (runner.getKeyNodeLevel() <= 1) return;
      runner.setKeyNodeLevel(runner.getKeyNodeLevel() - 1).then(refreshControls);
    });
  }

  if (levelPlusBtn) {
    levelPlusBtn.addEventListener('click', () => {
      if (runner.getKeyNodeLevel() >= 10) return;
      runner.setKeyNodeLevel(runner.getKeyNodeLevel() + 1).then(refreshControls);
    });
  }

  if (queryInput) {
    queryInput.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        runner.loadGraph().catch(() => {});
      }
    });
  }

  refreshControls();
  runner.init().finally(() => {
    runner.loadGraph(runner.getCurrentQuery() || 'LINE1').catch(() => {});
  });
}());
