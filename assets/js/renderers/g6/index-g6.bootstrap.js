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
    qaTitle: document.getElementById('qa-title'),
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
    modeLabel: document.getElementById('qa-mode-label'),
    modeSimple: document.getElementById('mode-simple'),
    modeDetailed: document.getElementById('mode-detailed'),
    modeCustom: document.getElementById('mode-custom'),
    depthLabel: document.getElementById('qa-depth-label'),
    depthShallow: document.getElementById('depth-shallow'),
    depthMedium: document.getElementById('depth-medium'),
    depthDeep: document.getElementById('depth-deep'),
    depthCustom: document.getElementById('depth-custom'),
    modelLabel: document.getElementById('qa-model-label'),
    modelQwen: document.getElementById('model-qwen'),
    modelDeepSeek: document.getElementById('model-deepseek'),
    customEditor: document.getElementById('custom-editor'),
    customPrompt: document.getElementById('custom-prompt'),
    customDepthEditor: document.getElementById('custom-depth-editor'),
    customRows: document.getElementById('custom-rows'),
    customReferences: document.getElementById('custom-references'),
    customEditorTitle: document.getElementById('custom-editor-title'),
    customEditorHelp: document.getElementById('custom-editor-help'),
    customRowsLabel: document.getElementById('custom-rows-label'),
    customReferencesLabel: document.getElementById('custom-references-label'),
    customDepthNote: document.getElementById('custom-depth-note'),
    customEditorConfirm: document.getElementById('confirm-custom-editor'),
    customEditorCancel: document.getElementById('cancel-custom-editor'),
    chatMessages: document.getElementById('chat-messages'),
    inputWrap: document.querySelector('.chat .input'),
    userInput: document.getElementById('user-question'),
    sendBtn: document.getElementById('send-question'),
  };

  const UI_TEXT = {
    en: {
      pageTitle: 'TEKG G6 Workspace',
      badge: 'Independent G6 entry',
      graphTitle: 'G6 Graph Workspace',
      qaTitle: 'Interactive QA Demo',
      searchPlaceholder: 'Search LINE1, L1HS, disease, or function',
      questionPlaceholder: 'Ask your question',
      focusGlobal: 'Focus mode: Global',
      focusLocal: 'Focus mode: Local',
      fixedOn: 'Fixed view: On',
      fixedOff: 'Fixed view: Off',
      reset: 'Reset',
      keyNodeLevel: (level) => `Key-node level: ${level}`,
      ready: 'G6 workspace is ready.',
      notFound: 'No matching graph fragment was found.',
      loading: 'Loading graph...',
      fallback: 'Falling back to the default G6 tree.',
      modeLabel: 'Answer style',
      modeSimple: 'Brief',
      modeDetailed: 'Detailed',
      modeCustom: 'Custom',
      depthLabel: 'Answer depth',
      depthShallow: 'Shallow',
      depthMedium: 'Medium',
      depthDeep: 'Deep',
      depthCustom: 'Custom',
      modelLabel: 'Model',
      customEditorTitle: 'Custom prompt',
      customEditorHelp: 'Write the tone, structure, emphasis, or constraints you want the QA model to follow. After confirmation, it will be temporarily saved and used for subsequent questions.',
      customDepthTitle: 'Custom answer depth',
      customDepthHelp: 'Adjust how many structured relation records and reference papers the QA system should use when constructing the answer.',
      customRowsLabel: 'row (relation count)',
      customReferencesLabel: 'references (paper count)',
      customDepthNote: 'row controls the maximum number of structured relation records; references controls the maximum number of cited papers. Recommended upper bounds are 12 and 8 to avoid overly long answers.',
      customConfirm: 'Confirm',
      customCancel: 'Cancel',
      asking: 'Retrieving graph evidence and generating the answer...',
      backendFallback: 'Backend unavailable. Falling back to a local guidance answer.',
      noGraphContext: 'The answer is available, but no graph context was returned.',
      answerGraphSynced: 'The graph on the left has been synchronized to the answer context.',
      providerPrefix: 'Model: ',
      localFallbackTitle: 'Local fallback answer',
    },
    zh: {
      pageTitle: 'TEKG G6 Workspace',
      badge: 'Independent G6 entry',
      graphTitle: 'G6 Graph Workspace',
      qaTitle: 'Interactive QA Demo',
      searchPlaceholder: 'Search LINE1, L1HS, disease, or function',
      questionPlaceholder: 'Ask your question',
      focusGlobal: 'Focus mode: Global',
      focusLocal: 'Focus mode: Local',
      fixedOn: 'Fixed view: On',
      fixedOff: 'Fixed view: Off',
      reset: 'Reset',
      keyNodeLevel: (level) => `Key-node level: ${level}`,
      ready: 'G6 workspace is ready.',
      notFound: 'No matching graph fragment was found.',
      loading: 'Loading graph...',
      fallback: 'Falling back to the default G6 tree.',
      modeLabel: 'Answer style',
      modeSimple: 'Brief',
      modeDetailed: 'Detailed',
      modeCustom: 'Custom',
      depthLabel: 'Answer depth',
      depthShallow: 'Shallow',
      depthMedium: 'Medium',
      depthDeep: 'Deep',
      depthCustom: 'Custom',
      modelLabel: 'Model',
      customEditorTitle: 'Custom prompt',
      customEditorHelp: 'Write the tone, structure, emphasis, or constraints you want the QA model to follow. After confirmation, it will be temporarily saved and used for subsequent questions.',
      customDepthTitle: 'Custom answer depth',
      customDepthHelp: 'Adjust how many structured relation records and reference papers the QA system should use when constructing the answer.',
      customRowsLabel: 'row (relation count)',
      customReferencesLabel: 'references (paper count)',
      customDepthNote: 'row controls the maximum number of structured relation records; references controls the maximum number of cited papers. Recommended upper bounds are 12 and 8 to avoid overly long answers.',
      customConfirm: 'Confirm',
      customCancel: 'Cancel',
      asking: 'Retrieving graph evidence and generating the answer...',
      backendFallback: 'Backend unavailable. Falling back to a local guidance answer.',
      noGraphContext: 'The answer is available, but no graph context was returned.',
      answerGraphSynced: 'The graph on the left has been synchronized to the answer context.',
      providerPrefix: 'Model: ',
      localFallbackTitle: 'Local fallback answer',
    },
  };

  let searchDebounceId = null;
  let currentResultElements = null;
  let currentResultLabel = '';
  let currentGraphQuery = '';
  let customEditorMode = 'prompt';
  let customPromptDraft = '';
  let customDepthDraft = { rows: 12, references: 8 };
  let previousAnswerStyleBeforeCustom = 'simple';
  let previousAnswerDepthBeforeCustom = 'shallow';

  function getLang() {
    return typeof currentLang === 'string' ? currentLang : 'en';
  }

  function setButtonActive(button, active) {
    if (button) button.classList.toggle('active', !!active);
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function renderMarkdown(text) {
    if (window.marked && typeof window.marked.parse === 'function') {
      return window.marked.parse(String(text || ''));
    }
    return `<p>${escapeHtml(text)}</p>`;
  }

  function addMessage(content, role) {
    if (!els.chatMessages) return null;
    const msg = document.createElement('div');
    msg.className = `msg ${role}`;
    const avatar = document.createElement('div');
    avatar.className = 'avatar';
    avatar.innerHTML = role === 'user' ? '<i class="fas fa-user"></i>' : '<i class="fas fa-robot"></i>';
    const bubble = document.createElement('div');
    bubble.className = 'bubble';
    bubble.innerHTML = role === 'assistant' ? renderMarkdown(content) : `<p>${escapeHtml(content)}</p>`;
    msg.appendChild(avatar);
    msg.appendChild(bubble);
    els.chatMessages.appendChild(msg);
    els.chatMessages.scrollTop = els.chatMessages.scrollHeight;
    return msg;
  }

  function setDetail(text) {
    if (els.detail) els.detail.textContent = text;
  }

  function syncButtons() {
    if (els.zhBtn) els.zhBtn.classList.toggle('active', getLang() === 'zh');
    if (els.enBtn) els.enBtn.classList.toggle('active', getLang() === 'en');
    if (els.modeSimple) els.modeSimple.classList.toggle('active', currentAnswerStyle === 'simple');
    if (els.modeDetailed) els.modeDetailed.classList.toggle('active', currentAnswerStyle === 'detailed');
    if (els.modeCustom) els.modeCustom.classList.toggle('active', currentAnswerStyle === 'custom');
    if (els.depthShallow) els.depthShallow.classList.toggle('active', currentAnswerDepth === 'shallow');
    if (els.depthMedium) els.depthMedium.classList.toggle('active', currentAnswerDepth === 'medium');
    if (els.depthDeep) els.depthDeep.classList.toggle('active', currentAnswerDepth === 'deep');
    if (els.depthCustom) els.depthCustom.classList.toggle('active', currentAnswerDepth === 'custom');
    if (els.modelQwen) els.modelQwen.classList.toggle('active', currentModelProvider === 'qwen');
    if (els.modelDeepSeek) els.modelDeepSeek.classList.toggle('active', currentModelProvider === 'deepseek');
  }

  function openCustomEditor(mode) {
    const t = UI_TEXT[getLang()] || UI_TEXT.en;
    customEditorMode = mode === 'depth' ? 'depth' : 'prompt';
    customPromptDraft = currentCustomPrompt || '';
    customDepthDraft = { ...currentCustomDepth };
    if (els.customPrompt) els.customPrompt.value = customPromptDraft;
    if (els.customRows) els.customRows.value = String(customDepthDraft.rows);
    if (els.customReferences) els.customReferences.value = String(customDepthDraft.references);
    if (els.customEditorTitle) {
      els.customEditorTitle.textContent = customEditorMode === 'depth' ? t.customDepthTitle : t.customEditorTitle;
    }
    if (els.customEditorHelp) {
      els.customEditorHelp.textContent = customEditorMode === 'depth' ? t.customDepthHelp : t.customEditorHelp;
    }
    if (els.customPrompt) {
      els.customPrompt.style.display = customEditorMode === 'depth' ? 'none' : '';
    }
    if (els.customEditor) els.customEditor.classList.add('active');
    if (els.customDepthEditor) els.customDepthEditor.classList.toggle('active', customEditorMode === 'depth');
    if (els.chatMessages) els.chatMessages.style.display = 'none';
    if (els.inputWrap) els.inputWrap.style.display = 'none';
  }

  function closeCustomEditor(save) {
    if (save) {
      if (els.customPrompt) currentCustomPrompt = String(els.customPrompt.value || '').trim();
      if (els.customRows) customDepthDraft.rows = Math.max(1, Number(els.customRows.value || currentCustomDepth.rows || 12));
      if (els.customReferences) customDepthDraft.references = Math.max(1, Number(els.customReferences.value || currentCustomDepth.references || 8));
      currentCustomDepth = { ...customDepthDraft };
      if (customEditorMode === 'prompt') {
        currentAnswerStyle = 'custom';
      } else {
        currentAnswerDepth = 'custom';
      }
    } else {
      if (customEditorMode === 'prompt') {
        currentAnswerStyle = previousAnswerStyleBeforeCustom;
      } else {
        currentAnswerDepth = previousAnswerDepthBeforeCustom;
      }
    }
    if (els.customEditor) els.customEditor.classList.remove('active');
    if (els.chatMessages) els.chatMessages.style.display = '';
    if (els.inputWrap) els.inputWrap.style.display = '';
    updateUi();
  }

  function updateUi() {
    const t = UI_TEXT[getLang()] || UI_TEXT.en;
    if (els.title) els.title.textContent = t.pageTitle;
    if (els.badge) els.badge.textContent = t.badge;
    if (els.graphTitle) els.graphTitle.textContent = t.graphTitle;
    if (els.qaTitle) els.qaTitle.textContent = t.qaTitle;
    if (els.search) els.search.placeholder = t.searchPlaceholder;
    if (els.userInput) els.userInput.placeholder = t.questionPlaceholder;
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
    if (els.levelMinus) els.levelMinus.disabled = (currentKeyNodeLevel || 1) <= 1;
    if (els.levelPlus) els.levelPlus.disabled = (currentKeyNodeLevel || 1) >= 3;
    if (els.modeLabel) els.modeLabel.textContent = t.modeLabel;
    if (els.modeSimple) els.modeSimple.textContent = t.modeSimple;
    if (els.modeDetailed) els.modeDetailed.textContent = t.modeDetailed;
    if (els.modeCustom) els.modeCustom.textContent = t.modeCustom;
    if (els.depthLabel) els.depthLabel.textContent = t.depthLabel;
    if (els.depthShallow) els.depthShallow.textContent = t.depthShallow;
    if (els.depthMedium) els.depthMedium.textContent = t.depthMedium;
    if (els.depthDeep) els.depthDeep.textContent = t.depthDeep;
    if (els.depthCustom) els.depthCustom.textContent = t.depthCustom;
    if (els.modelLabel) els.modelLabel.textContent = t.modelLabel;
    if (els.customEditorTitle) {
      els.customEditorTitle.textContent = customEditorMode === 'depth' ? t.customDepthTitle : t.customEditorTitle;
    }
    if (els.customEditorHelp) {
      els.customEditorHelp.textContent = customEditorMode === 'depth' ? t.customDepthHelp : t.customEditorHelp;
    }
    if (els.customRowsLabel) els.customRowsLabel.textContent = t.customRowsLabel;
    if (els.customReferencesLabel) els.customReferencesLabel.textContent = t.customReferencesLabel;
    if (els.customDepthNote) els.customDepthNote.textContent = t.customDepthNote;
    if (els.customEditorConfirm) els.customEditorConfirm.textContent = t.customConfirm;
    if (els.customEditorCancel) els.customEditorCancel.textContent = t.customCancel;
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
    currentGraphQuery = '';
    if (window.__TEKG_G6_DYNAMIC_GRAPH && typeof window.__TEKG_G6_DYNAMIC_GRAPH.destroy === 'function') {
      window.__TEKG_G6_DYNAMIC_GRAPH.destroy();
    }
    if (window.__TEKG_G6_DEFAULT_TREE && typeof window.__TEKG_G6_DEFAULT_TREE.render === 'function') {
      await window.__TEKG_G6_DEFAULT_TREE.render();
    }
    updateUi();
  }

  async function renderGraphPayload(payload, fallbackQuery) {
    if (!payload || !Array.isArray(payload.elements) || payload.elements.length === 0) return null;
    currentGraphKind = 'dynamic';
    currentResultElements = payload.elements;
    currentResultLabel = payload.anchor?.name || fallbackQuery || '';
    if (window.__TEKG_G6_DYNAMIC_GRAPH && typeof window.__TEKG_G6_DYNAMIC_GRAPH.render === 'function') {
      await window.__TEKG_G6_DYNAMIC_GRAPH.render(payload.elements, currentResultLabel, payload);
    }
    updateUi();
    return payload;
  }

  async function loadDynamicGraph(query) {
    const q = String(query || '').trim();
    if (!q) {
      await renderDefaultTree();
      return null;
    }
    const t = UI_TEXT[getLang()] || UI_TEXT.en;
    setDetail(t.loading);
    const response = await fetch(`api/graph.php?q=${encodeURIComponent(q)}&key_level=${encodeURIComponent(typeof currentKeyNodeLevel !== 'undefined' ? currentKeyNodeLevel : 1)}`, {
      cache: 'no-store',
    });
    let payload = null;
    try {
      payload = await response.json();
    } catch (_error) {
      throw new Error('Invalid graph response');
    }
    if (!response.ok || !payload || payload.ok === false) {
      throw new Error((payload && payload.error) || `Graph request failed (${response.status})`);
    }
    if (!Array.isArray(payload.elements) || payload.elements.length === 0) {
      setDetail(t.notFound);
      return null;
    }
    currentGraphQuery = q;
    return renderGraphPayload(payload, q);
  }

  async function applyAnswerGraph(result, question) {
    const graph = result && result.graph_context;
    if (!graph || !Array.isArray(graph.elements) || graph.elements.length === 0) {
      setDetail((UI_TEXT[getLang()] || UI_TEXT.en).noGraphContext);
      return false;
    }
    const payload = Object.assign({}, graph, { __fromQa: true });
    const anchorName = graph.anchor && graph.anchor.name ? graph.anchor.name : String(question || '').trim();
    if (els.search) els.search.value = anchorName;
    await renderGraphPayload(payload, anchorName);
    setDetail((UI_TEXT[getLang()] || UI_TEXT.en).answerGraphSynced);
    return true;
  }

  function formatBackendAnswer(result) {
    const t = UI_TEXT[getLang()] || UI_TEXT.en;
    const provider = (result.model_provider || currentModelProvider || 'qwen').toLowerCase() === 'deepseek' ? 'DeepSeek' : 'Qwen';
    const styleLabel = currentAnswerStyle === 'custom'
      ? t.modeCustom
      : (currentAnswerStyle === 'detailed' ? t.modeDetailed : t.modeSimple);
    const depthMap = {
      shallow: t.depthShallow,
      medium: t.depthMedium,
      deep: t.depthDeep,
      custom: t.depthCustom,
    };
    const depthLabel = depthMap[result.answer_depth] || depthMap[currentAnswerDepth] || t.depthShallow;
    return `> ${t.providerPrefix}${provider}\n> ${styleLabel} · ${depthLabel}\n\n${result.answer || ''}`;
  }

  function buildFallbackAnswer(question, errorMessage) {
    const t = UI_TEXT[getLang()] || UI_TEXT.en;
    const lines = [
      `## ${t.localFallbackTitle}`,
      '',
      t.backendFallback,
      '',
      `- Question: ${question}`,
    ];
    if (errorMessage) lines.push(`- Error: ${errorMessage}`);
    lines.push('', '- Try asking a TE name such as LINE1, L1HS, or Alu.');
    return lines.join('\n');
  }

  async function answerWithBackend(question) {
    const payload = {
      question: String(question || '').trim(),
      language: getLang(),
      answer_style: currentAnswerStyle,
      answer_depth: currentAnswerDepth,
      model_provider: currentModelProvider,
      custom_prompt: currentAnswerStyle === 'custom' ? (currentCustomPrompt || '') : '',
    };
    if (currentAnswerDepth === 'custom') {
      payload.custom_rows = currentCustomDepth.rows;
      payload.custom_references = currentCustomDepth.references;
    }
    const response = await fetch('api/qa.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    let data = null;
    try {
      data = await response.json();
    } catch (_error) {
      throw new Error('Invalid QA response');
    }
    if (!response.ok || !data || data.ok === false) {
      throw new Error((data && data.error) || `QA request failed (${response.status})`);
    }
    return data;
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
      els.levelMinus.addEventListener('click', async () => {
        currentKeyNodeLevel = Math.max(1, (currentKeyNodeLevel || 1) - 1);
        updateUi();
        if (currentGraphKind === 'dynamic' && currentGraphQuery) {
          try {
            await loadDynamicGraph(currentGraphQuery);
          } catch (error) {
            setDetail(error && error.message ? error.message : 'Graph request failed');
          }
        }
      });
    }
    if (els.levelPlus) {
      els.levelPlus.addEventListener('click', async () => {
        currentKeyNodeLevel = Math.min(3, (currentKeyNodeLevel || 1) + 1);
        updateUi();
        if (currentGraphKind === 'dynamic' && currentGraphQuery) {
          try {
            await loadDynamicGraph(currentGraphQuery);
          } catch (error) {
            setDetail(error && error.message ? error.message : 'Graph request failed');
          }
        }
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

  function bindQaControls() {
    if (els.modeSimple) {
      els.modeSimple.addEventListener('click', () => {
        currentAnswerStyle = 'simple';
        syncButtons();
      });
    }
    if (els.modeDetailed) {
      els.modeDetailed.addEventListener('click', () => {
        currentAnswerStyle = 'detailed';
        syncButtons();
      });
    }
    if (els.modeCustom) {
      els.modeCustom.addEventListener('click', () => {
        previousAnswerStyleBeforeCustom = currentAnswerStyle === 'custom' ? 'simple' : currentAnswerStyle;
        openCustomEditor('prompt');
      });
    }
    if (els.depthShallow) {
      els.depthShallow.addEventListener('click', () => {
        currentAnswerDepth = 'shallow';
        syncButtons();
      });
    }
    if (els.depthMedium) {
      els.depthMedium.addEventListener('click', () => {
        currentAnswerDepth = 'medium';
        syncButtons();
      });
    }
    if (els.depthDeep) {
      els.depthDeep.addEventListener('click', () => {
        currentAnswerDepth = 'deep';
        syncButtons();
      });
    }
    if (els.depthCustom) {
      els.depthCustom.addEventListener('click', () => {
        previousAnswerDepthBeforeCustom = currentAnswerDepth === 'custom' ? 'shallow' : currentAnswerDepth;
        openCustomEditor('depth');
      });
    }
    if (els.modelQwen) {
      els.modelQwen.addEventListener('click', () => {
        currentModelProvider = 'qwen';
        syncButtons();
      });
    }
    if (els.modelDeepSeek) {
      els.modelDeepSeek.addEventListener('click', () => {
        currentModelProvider = 'deepseek';
        syncButtons();
      });
    }
    if (els.sendBtn) {
      els.sendBtn.addEventListener('click', async () => {
        const question = String(els.userInput?.value || '').trim();
        if (!question) return;
        addMessage(question, 'user');
        if (els.userInput) els.userInput.value = '';
        const loadingMsg = addMessage((UI_TEXT[getLang()] || UI_TEXT.en).asking, 'assistant');
        try {
          const result = await answerWithBackend(question);
          if (loadingMsg) loadingMsg.remove();
          addMessage(formatBackendAnswer(result), 'assistant');
          await applyAnswerGraph(result, question);
        } catch (error) {
          if (loadingMsg) loadingMsg.remove();
          addMessage(buildFallbackAnswer(question, error && error.message ? error.message : 'unknown error'), 'assistant');
        }
      });
    }
    if (els.userInput) {
      els.userInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          if (els.sendBtn) els.sendBtn.click();
        }
      });
    }
    if (els.customEditorConfirm) {
      els.customEditorConfirm.addEventListener('click', () => closeCustomEditor(true));
    }
    if (els.customEditorCancel) {
      els.customEditorCancel.addEventListener('click', () => closeCustomEditor(false));
    }
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
      bindQaControls();
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
