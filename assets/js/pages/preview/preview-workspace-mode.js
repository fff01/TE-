(() => {
  'use strict';

  const knowledgeWorkspace = document.getElementById('previewGraphWorkspace');
  const coexpressionWorkspace = document.getElementById('previewCoexpressionWorkspace');
  const workspaceControl = document.getElementById('previewWorkspaceMode');
  const topControls = workspaceControl?.closest('.preview-top-controls');
  const knowledgeTab = document.getElementById('preview-mode-knowledge');
  const coexpressionTab = document.getElementById('preview-mode-coexpression');
  const taxonomyControl = document.getElementById('previewTaxonomyMode');
  const taxonomyAllTab = document.getElementById('preview-taxonomy-all');
  const taxonomyRmskRepbaseTab = document.getElementById('preview-taxonomy-rmsk-repbase');
  const knowledgeToolbar = knowledgeWorkspace?.querySelector('.preview-graph-toolbar');
  const coexpressionToolbar = coexpressionWorkspace?.querySelector('.preview-graph-toolbar');
  const edgeLabelsButton = document.getElementById('toggle-edge-labels');
  const coexpressionContextControl = coexpressionWorkspace?.querySelector('.coexpression-context-control');
  const coexpressionMode = window.__TEKG_COEXPRESSION_MODE;
  if (
    !knowledgeWorkspace
    || !coexpressionWorkspace
    || !workspaceControl
    || !topControls
    || !knowledgeTab
    || !coexpressionTab
    || !taxonomyControl
    || !taxonomyAllTab
    || !taxonomyRmskRepbaseTab
    || !knowledgeToolbar
    || !coexpressionToolbar
    || !edgeLabelsButton
    || !coexpressionContextControl
    || !coexpressionMode
  ) return;

  let currentMode = 'knowledge';
  let knowledgeGraphMode = 'tree';
  let transitionEpoch = 0;
  let switchCount = 0;

  function updateTab(tab, selected) {
    tab.classList.toggle('is-active', selected);
    tab.setAttribute('aria-selected', selected ? 'true' : 'false');
    tab.setAttribute('aria-pressed', selected ? 'true' : 'false');
    tab.tabIndex = selected ? 0 : -1;
  }

  function placeTopControls(mode) {
    if (mode === 'coexpression') {
      coexpressionToolbar.insertBefore(topControls, coexpressionContextControl);
      return;
    }
    knowledgeToolbar.insertBefore(topControls, edgeLabelsButton);
  }

  function setWorkspaceVisibility(mode) {
    const showKnowledge = mode === 'knowledge';
    placeTopControls(mode);
    knowledgeWorkspace.hidden = !showKnowledge;
    knowledgeWorkspace.setAttribute('aria-hidden', showKnowledge ? 'false' : 'true');
    if (showKnowledge) {
      coexpressionWorkspace.hidden = true;
      coexpressionWorkspace.setAttribute('aria-hidden', 'true');
    }
    updateTab(knowledgeTab, showKnowledge);
    updateTab(coexpressionTab, !showKnowledge);
    syncTopControlVisibility();
  }

  function syncTopControlVisibility() {
    const showTaxonomyControl = currentMode === 'knowledge' && knowledgeGraphMode === 'tree';
    taxonomyControl.hidden = !showTaxonomyControl;
    taxonomyControl.setAttribute('aria-hidden', showTaxonomyControl ? 'false' : 'true');
    workspaceControl.hidden = showTaxonomyControl;
    workspaceControl.setAttribute('aria-hidden', showTaxonomyControl ? 'true' : 'false');
  }

  function notifyModeChange() {
    window.dispatchEvent(new CustomEvent('tekg:preview-workspace-mode-change', {
      detail: { mode: currentMode },
    }));
    window.dispatchEvent(new CustomEvent('tekg:preview-layout-change'));
  }

  function resizeKnowledgeGraph() {
    const bridge = window.__TEKG_G6_BRIDGE;
    if (bridge && typeof bridge.resize === 'function') {
      try {
        bridge.resize();
      } catch (_error) {}
    }
  }

  function syncRoute(mode, selection = null, historyAction = 'none') {
    if (historyAction !== 'push' && historyAction !== 'replace') return false;
    const url = new URL(window.location.href);
    if (mode === 'coexpression') {
      url.searchParams.set('mode', 'coexpression');
      const te = String(selection?.te || '').trim();
      const context = String(selection?.context || '').trim();
      if (te) url.searchParams.set('te', te);
      else url.searchParams.delete('te');
      if (context) url.searchParams.set('context', context);
      else url.searchParams.delete('context');
    } else {
      url.searchParams.delete('mode');
      url.searchParams.delete('te');
      url.searchParams.delete('context');
    }
    const next = `${url.pathname}${url.search}${url.hash}`;
    const current = `${window.location.pathname}${window.location.search}${window.location.hash}`;
    if (next === current) return false;
    window.history[historyAction === 'replace' ? 'replaceState' : 'pushState'](
      { tekgPreviewMode: mode, selection },
      '',
      next,
    );
    return true;
  }

  function updateTaxonomyTab(tab, selected) {
    tab.classList.toggle('is-active', selected);
    tab.setAttribute('aria-selected', selected ? 'true' : 'false');
    tab.setAttribute('aria-pressed', selected ? 'true' : 'false');
    tab.tabIndex = selected ? 0 : -1;
  }

  function syncTaxonomyMode(variant) {
    const selected = variant === 'all' ? 'all' : 'rmsk_repbase';
    updateTaxonomyTab(taxonomyAllTab, selected === 'all');
    updateTaxonomyTab(taxonomyRmskRepbaseTab, selected === 'rmsk_repbase');
  }

  async function setTreeVariant(variant) {
    if (currentMode !== 'knowledge') return false;
    const bridge = window.__TEKG_G6_BRIDGE;
    if (!bridge || typeof bridge.setTreeVariant !== 'function') return false;
    const nextVariant = variant === 'all' ? 'all' : 'rmsk_repbase';
    await bridge.setTreeVariant(nextVariant);
    syncTaxonomyMode(nextVariant);
    return true;
  }

  async function setMode(mode, options = {}) {
    const requestedMode = mode === 'coexpression' ? 'coexpression' : 'knowledge';
    const hasSelectionOverride = options.clearSelection === true
      || !!String(options.te || options.context || '').trim();
    const epoch = ++transitionEpoch;
    const changed = requestedMode !== currentMode;
    if (!changed && !hasSelectionOverride) return currentMode;
    currentMode = requestedMode;
    if (changed) switchCount += 1;

    setWorkspaceVisibility(requestedMode);
    if (requestedMode === 'knowledge') {
      await coexpressionMode.deactivate();
      if (epoch !== transitionEpoch) return currentMode;
      resizeKnowledgeGraph();
      syncRoute('knowledge', null, options.history);
      notifyModeChange();
      return currentMode;
    }

    const coexpressionState = coexpressionMode.getDiagnostics();
    const stableCoexpressionState = coexpressionState.state !== 'idle'
      && coexpressionState.state !== 'loading-catalog'
      && coexpressionState.state !== 'loading-network'
      && coexpressionState.state !== 'loading-iframe'
      && coexpressionState.state !== 'rendering';
    const canResume = !hasSelectionOverride
      && stableCoexpressionState
      && (coexpressionState.state !== 'ready' || coexpressionState.iframeCount === 1);
    if (canResume && typeof coexpressionMode.resume === 'function') {
      await coexpressionMode.resume();
    } else {
      await coexpressionMode.activate({
        te: options.te,
        context: options.context,
      });
    }
    if (epoch !== transitionEpoch) return currentMode;
    const nextCoexpressionState = coexpressionMode.getDiagnostics();
    syncRoute('coexpression', nextCoexpressionState.selection, options.history);
    notifyModeChange();
    return currentMode;
  }

  function ensureKnowledgeForGraphAction() {
    return setMode('knowledge');
  }

  function getDiagnostics() {
    return {
      mode: currentMode,
      switchCount,
      knowledgeVisible: !knowledgeWorkspace.hidden,
      coexpressionVisible: !coexpressionWorkspace.hidden,
      knowledgeSelected: knowledgeTab.getAttribute('aria-selected') === 'true',
      coexpressionSelected: coexpressionTab.getAttribute('aria-selected') === 'true',
    };
  }

  knowledgeTab.addEventListener('click', () => {
    void setMode('knowledge', { history: 'push' });
  });
  coexpressionTab.addEventListener('click', () => {
    void setMode('coexpression', { history: 'push', clearSelection: false });
  });
  taxonomyAllTab.addEventListener('click', () => {
    void setTreeVariant('all');
  });
  taxonomyRmskRepbaseTab.addEventListener('click', () => {
    void setTreeVariant('rmsk_repbase');
  });
  window.addEventListener('tekg:g6-state-change', (event) => {
    knowledgeGraphMode = String(event.detail?.mode || knowledgeGraphMode);
    syncTaxonomyMode(event.detail?.treeVariant);
    syncTopControlVisibility();
  });

  [knowledgeTab, coexpressionTab].forEach((tab) => {
    tab.addEventListener('keydown', (event) => {
      if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
      event.preventDefault();
      const nextMode = currentMode === 'knowledge' ? 'coexpression' : 'knowledge';
      const nextTab = nextMode === 'knowledge' ? knowledgeTab : coexpressionTab;
      nextTab.focus();
      void setMode(nextMode, { history: 'push' });
    });
  });

  window.__TEKG_PREVIEW_WORKSPACE_MODE = {
    setMode,
    getMode: () => currentMode,
    getDiagnostics,
    ensureKnowledgeForGraphAction,
    setTreeVariant,
  };

  const initialKnowledgeState = window.__TEKG_G6_BRIDGE?.getState?.() || {};
  knowledgeGraphMode = String(initialKnowledgeState.mode || 'tree');
  setWorkspaceVisibility('knowledge');
  syncTaxonomyMode(initialKnowledgeState.treeVariant);
  const params = new URLSearchParams(window.location.search);
  if (params.get('mode') === 'coexpression') {
    void setMode('coexpression', {
      te: params.get('te') || undefined,
      context: params.get('context') || undefined,
      clearSelection: !params.get('te'),
    });
  }
  window.addEventListener('popstate', () => {
    const route = new URLSearchParams(window.location.search);
    if (route.get('mode') === 'coexpression') {
      void setMode('coexpression', {
        te: route.get('te') || undefined,
        context: route.get('context') || undefined,
        clearSelection: !route.get('te'),
        history: 'none',
      });
      return;
    }
    void setMode('knowledge', { history: 'none' });
  });
})();
