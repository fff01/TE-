(function () {
  if (window.__TEKG_RENDERER_MODE !== 'g6') return;

  const chatMessages = document.getElementById('chat-messages');
  const userInput = document.getElementById('user-question');
  const sendBtn = document.getElementById('send-question');
  const nodeDetails = document.getElementById('node-details');

  if (!chatMessages || !userInput || !sendBtn) return;

  const byId = (id) => document.getElementById(id);

  function getBridge() {
    return window.__TEKG_G6_BRIDGE || null;
  }

  function getGraphState() {
    const bridge = getBridge();
    if (bridge && typeof bridge.getState === 'function') return bridge.getState() || {};
    return {
      mode: bridge && typeof bridge.getMode === 'function' ? bridge.getMode() : 'tree',
      query: bridge && typeof bridge.getCurrentQuery === 'function' ? bridge.getCurrentQuery() : '',
      fixedView: bridge && typeof bridge.getFixedView === 'function' ? bridge.getFixedView() : false,
      keyNodeLevel: bridge && typeof bridge.getKeyNodeLevel === 'function' ? bridge.getKeyNodeLevel() : 1,
      selectedNode: bridge && typeof bridge.getSelectedNode === 'function' ? bridge.getSelectedNode() : null,
    };
  }

  function qaText() {
    if (typeof ui === 'object' && ui && ui[currentLang]) return ui[currentLang];
    return {
      qaTitle: 'Interactive QA Demo',
      ph: 'Ask your question',
      q: 'LINE-1 related diseases',
      intro: 'This demo is grounded on a local graph subnetwork. You can ask about the current graph context.',
      providerPrefix: 'Model: ',
      modelLabel: 'Model',
      modelQwen: 'Qwen',
      modelDeepSeek: 'DeepSeek',
      fallback: 'No answer is available right now.',
      empty: 'Click a node in the graph to inspect its details.',
    };
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value || '');
    return div.innerHTML;
  }

  function renderBubbleContent(content, sender) {
    const raw = String(content || '');
    if (sender === 'user') return escapeHtml(raw).replace(/\n/g, '<br>');
    if (window.marked && typeof window.marked.parse === 'function') {
      window.marked.setOptions({ breaks: true, gfm: true });
      return window.marked.parse(raw);
    }
    return escapeHtml(raw).replace(/\n/g, '<br>');
  }

  function addMessage(content, sender) {
    const item = document.createElement('div');
    item.className = `msg ${sender}`;
    item.innerHTML =
      `<div class="avatar"><i class="fas ${sender === 'user' ? 'fa-user' : 'fa-robot'}"></i></div>` +
      `<div class="bubble">${renderBubbleContent(content, sender)}</div>`;
    chatMessages.appendChild(item);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    return item;
  }

  function renderIntro() {
    chatMessages.innerHTML = '';
    addMessage(qaText().intro || 'This demo is grounded on a local graph subnetwork. You can ask about the current graph context.', 'assistant');
  }

  function getCustomEditorElements() {
    return {
      editor: byId('custom-editor'),
      title: byId('custom-editor-title'),
      help: byId('custom-editor-help'),
      promptField: byId('custom-prompt'),
      depthWrap: byId('custom-depth-editor'),
      rowsField: byId('custom-rows'),
      referencesField: byId('custom-references'),
      rowsLabel: byId('custom-rows-label'),
      referencesLabel: byId('custom-references-label'),
      depthNote: byId('custom-depth-note'),
      msgs: chatMessages,
      inputWrap: document.querySelector('.chat .input'),
    };
  }

  function updateCustomEditorUi() {
    const {
      editor,
      title,
      help,
      promptField,
      depthWrap,
      rowsField,
      referencesField,
      rowsLabel,
      referencesLabel,
      depthNote,
      msgs,
      inputWrap,
    } = getCustomEditorElements();

    const isPromptMode = customEditorMode === 'prompt';

    if (title) title.textContent = isPromptMode ? 'Custom prompt' : 'Custom answer depth';
    if (help) {
      help.textContent = isPromptMode
        ? 'Write any tone, structure, emphasis, or constraints you want the QA system to follow.'
        : 'Set custom answer-depth parameters here. Row means the maximum relation count, and references means the maximum number of cited papers.';
    }

    if (promptField) {
      promptField.style.display = isPromptMode ? 'block' : 'none';
      promptField.placeholder = 'For example: give the conclusion first, then explain mechanisms in bullet points, and cite PMID whenever possible.';
      if (document.activeElement !== promptField) {
        promptField.value = customPromptDraft || currentCustomPrompt || '';
      }
    }

    if (depthWrap) depthWrap.classList.toggle('active', !isPromptMode);
    if (rowsLabel) rowsLabel.textContent = 'row (relation count)';
    if (referencesLabel) referencesLabel.textContent = 'references (paper count)';
    if (depthNote) depthNote.textContent = 'Recommended upper bounds are 12 for row and 8 for references.';

    if (rowsField && document.activeElement !== rowsField) rowsField.value = String(customDepthDraft.rows ?? currentCustomDepth.rows ?? 12);
    if (referencesField && document.activeElement !== referencesField) referencesField.value = String(customDepthDraft.references ?? currentCustomDepth.references ?? 8);

    if (editor) editor.classList.toggle('active', customEditorOpen);
    if (msgs) msgs.style.display = customEditorOpen ? 'none' : 'flex';
    if (inputWrap) inputWrap.style.display = customEditorOpen ? 'none' : 'flex';
  }

  function updateAnswerModeUi() {
    const modeSimpleBtn = byId('mode-simple');
    const modeDetailedBtn = byId('mode-detailed');
    const modeCustomBtn = byId('mode-custom');
    const depthShallowBtn = byId('depth-shallow');
    const depthMediumBtn = byId('depth-medium');
    const depthDeepBtn = byId('depth-deep');
    const depthCustomBtn = byId('depth-custom');
    const modelQwenBtn = byId('model-qwen');
    const modelDeepSeekBtn = byId('model-deepseek');
    const t = qaText();

    if (byId('qa-title')) byId('qa-title').textContent = t.qaTitle || 'Interactive QA Demo';
    if (byId('qa-mode-label')) byId('qa-mode-label').textContent = 'Answer mode';
    if (byId('qa-depth-label')) byId('qa-depth-label').textContent = 'Answer depth';
    if (byId('qa-model-label')) byId('qa-model-label').textContent = t.modelLabel || 'Model';

    if (modeSimpleBtn && modeDetailedBtn && modeCustomBtn) {
      modeSimpleBtn.textContent = 'Brief';
      modeDetailedBtn.textContent = 'Detailed';
      modeCustomBtn.textContent = 'Custom';
      modeSimpleBtn.classList.toggle('active', currentAnswerStyle === 'simple');
      modeDetailedBtn.classList.toggle('active', currentAnswerStyle === 'detailed');
      modeCustomBtn.classList.toggle('active', currentAnswerStyle === 'custom');
    }

    if (depthShallowBtn && depthMediumBtn && depthDeepBtn && depthCustomBtn) {
      depthShallowBtn.textContent = 'Shallow';
      depthMediumBtn.textContent = 'Medium';
      depthDeepBtn.textContent = 'Deep';
      depthCustomBtn.textContent = 'Custom';
      depthShallowBtn.classList.toggle('active', currentAnswerDepth === 'shallow');
      depthMediumBtn.classList.toggle('active', currentAnswerDepth === 'medium');
      depthDeepBtn.classList.toggle('active', currentAnswerDepth === 'deep');
      depthCustomBtn.classList.toggle('active', currentAnswerDepth === 'custom');
    }

    if (modelQwenBtn && modelDeepSeekBtn) {
      modelQwenBtn.textContent = t.modelQwen || 'Qwen';
      modelDeepSeekBtn.textContent = t.modelDeepSeek || 'DeepSeek';
      modelQwenBtn.classList.toggle('active', currentModelProvider === 'qwen');
      modelDeepSeekBtn.classList.toggle('active', currentModelProvider === 'deepseek');
    }

    userInput.placeholder = t.ph || 'Ask your question';
    updateCustomEditorUi();
  }

  function openCustomPromptEditor() {
    previousAnswerStyleBeforeCustom = currentAnswerStyle === 'custom' ? 'custom' : currentAnswerStyle;
    customPromptDraft = currentCustomPrompt || '';
    customEditorMode = 'prompt';
    customEditorOpen = true;
    updateCustomEditorUi();
  }

  function openCustomDepthEditor() {
    previousAnswerDepthBeforeCustom = currentAnswerDepth === 'custom' ? 'custom' : currentAnswerDepth;
    customDepthDraft = {
      rows: currentCustomDepth.rows || 12,
      references: currentCustomDepth.references || 8,
    };
    customEditorMode = 'depth';
    customEditorOpen = true;
    updateCustomEditorUi();
  }

  function closeCustomEditor(options = {}) {
    const save = options.save === true;
    const promptField = byId('custom-prompt');
    const rowsField = byId('custom-rows');
    const referencesField = byId('custom-references');

    if (customEditorMode === 'prompt') {
      customPromptDraft = promptField ? String(promptField.value || '').trim() : customPromptDraft;
      if (save) {
        currentCustomPrompt = customPromptDraft;
        currentAnswerStyle = 'custom';
      } else if (currentAnswerStyle !== 'custom') {
        currentAnswerStyle = previousAnswerStyleBeforeCustom || 'simple';
      }
    } else {
      const parsedRows = Math.max(1, Number(rowsField && rowsField.value) || currentCustomDepth.rows || 12);
      const parsedReferences = Math.max(1, Number(referencesField && referencesField.value) || currentCustomDepth.references || 8);
      customDepthDraft = { rows: parsedRows, references: parsedReferences };
      if (save) {
        currentCustomDepth = { ...customDepthDraft };
        currentAnswerDepth = 'custom';
      } else if (currentAnswerDepth !== 'custom') {
        currentAnswerDepth = previousAnswerDepthBeforeCustom || 'shallow';
      }
    }

    customEditorOpen = false;
    updateCustomEditorUi();
    updateAnswerModeUi();
  }

  function setAnswerStyle(style) {
    currentAnswerStyle = ['simple', 'detailed', 'custom'].includes(style) ? style : 'simple';
    if (currentAnswerStyle === 'simple' && currentAnswerDepth === 'deep') currentAnswerDepth = 'shallow';
    if (currentAnswerStyle === 'detailed' && currentAnswerDepth === 'shallow') currentAnswerDepth = 'deep';
    if (currentAnswerStyle !== 'custom') customEditorOpen = false;
    updateAnswerModeUi();
  }

  function setAnswerDepth(depth) {
    if (['shallow', 'medium', 'deep', 'custom'].includes(depth)) currentAnswerDepth = depth;
    customEditorOpen = false;
    updateAnswerModeUi();
  }

  function setModelProvider(provider) {
    currentModelProvider = provider === 'deepseek' ? 'deepseek' : 'qwen';
    updateAnswerModeUi();
  }

  function formatNodeLabel(node) {
    if (!node) return 'None';
    return String(node.displayLabel || node.rawLabel || node.label || node.id || '').trim() || 'None';
  }

  function buildEffectiveQuestion(question) {
    const state = getGraphState();
    const modeLabel = state.mode === 'dynamic' ? 'Dynamic graph' : 'Tree';

    return [
      String(question || '').trim(),
      '',
      '[Current G6 graph context]',
      `Mode: ${modeLabel}`,
      `Center node: ${state.query ? String(state.query).trim() : 'None'}`,
      `Selected node: ${formatNodeLabel(state.selectedNode)}`,
      `Key-node level: ${state.keyNodeLevel || 1}`,
      `Fixed view: ${state.fixedView ? 'On' : 'Off'}`,
    ].join('\n');
  }

  function formatBackendAnswer(result) {
    const provider = (result.model_provider || currentModelProvider || 'qwen').toLowerCase() === 'deepseek' ? 'deepseek' : 'qwen';
    const styleLabel = currentAnswerStyle === 'custom'
      ? 'Custom mode'
      : result.answer_style === 'detailed'
        ? 'Detailed mode'
        : 'Brief mode';
    const depthLabel = result.answer_depth === 'custom'
      ? `Custom (row=${result.custom_rows || currentCustomDepth.rows}, references=${result.custom_references || currentCustomDepth.references})`
      : ({ shallow: 'Shallow', medium: 'Medium', deep: 'Deep' }[result.answer_depth] || 'Shallow');
    const t = qaText();
    const prefix = `> ${(t.providerPrefix || 'Model: ')}${provider === 'deepseek' ? t.modelDeepSeek : t.modelQwen}\n> ${styleLabel} · Depth: ${depthLabel}\n\n`;
    return `${prefix}${result.answer || t.fallback || 'No answer is available right now.'}`;
  }

  function buildFallbackAnswer(question, error) {
    const state = getGraphState();
    const lines = [
      'Backend is temporarily unavailable. Here is the current graph context for reference:',
      '',
      `- Center node: ${state.query ? String(state.query).trim() : 'None'}`,
      `- Selected node: ${formatNodeLabel(state.selectedNode)}`,
      `- Key-node level: ${state.keyNodeLevel || 1}`,
      `- Fixed view: ${state.fixedView ? 'On' : 'Off'}`,
      '',
      `> ${String(question || '').trim()}`,
    ];
    if (error && error.message) lines.push('', `> ${error.message}`);
    return lines.join('\n');
  }

  async function answerWithBackend(question) {
    const payload = {
      question: buildEffectiveQuestion(question),
      language: currentLang,
      answer_style: currentAnswerStyle === 'custom' ? 'custom' : currentAnswerStyle,
      answer_depth: currentAnswerDepth,
      custom_prompt: currentCustomPrompt || '',
      model_provider: currentModelProvider,
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

    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.error || 'Backend request failed');
    return { text: formatBackendAnswer(data), result: data };
  }

  async function handleSend() {
    const question = String(userInput.value || '').trim();
    if (!question) return;

    addMessage(question, 'user');
    userInput.value = '';

    const loadingNode = addMessage('Retrieving graph evidence and generating the answer…', 'assistant');
    try {
      const backend = await answerWithBackend(question);
      loadingNode.remove();
      addMessage(backend.text, 'assistant');
    } catch (error) {
      loadingNode.remove();
      addMessage(buildFallbackAnswer(question, error), 'assistant');
    }
  }

  function bindEvents() {
    sendBtn.addEventListener('click', handleSend);
    userInput.addEventListener('keypress', (event) => {
      if (event.key === 'Enter') handleSend();
    });

    document.addEventListener('click', (event) => {
      if (!event.target) return;
      if (event.target.id === 'mode-simple') setAnswerStyle('simple');
      if (event.target.id === 'mode-detailed') setAnswerStyle('detailed');
      if (event.target.id === 'mode-custom') openCustomPromptEditor();
      if (event.target.id === 'confirm-custom-editor') closeCustomEditor({ save: true });
      if (event.target.id === 'cancel-custom-editor') {
        customPromptDraft = currentCustomPrompt || '';
        customDepthDraft = { ...currentCustomDepth };
        closeCustomEditor({ save: false });
      }
      if (event.target.id === 'depth-shallow') setAnswerDepth('shallow');
      if (event.target.id === 'depth-medium') setAnswerDepth('medium');
      if (event.target.id === 'depth-deep') setAnswerDepth('deep');
      if (event.target.id === 'depth-custom') openCustomDepthEditor();
      if (event.target.id === 'model-qwen') setModelProvider('qwen');
      if (event.target.id === 'model-deepseek') setModelProvider('deepseek');
    });

    window.addEventListener('tekg:g6-state-change', updateAnswerModeUi);
  }

  async function initializeQa() {
    await Promise.allSettled([
      typeof loadUiText === 'function' ? loadUiText() : Promise.resolve(),
      typeof loadLocalQaTemplates === 'function' ? loadLocalQaTemplates() : Promise.resolve(),
    ]);

    if (!userInput.value) userInput.value = qaText().q || 'LINE-1 related diseases';

    bindEvents();
    updateAnswerModeUi();
    renderIntro();

    if (nodeDetails && !String(nodeDetails.textContent || '').trim()) {
      nodeDetails.textContent = qaText().empty || 'Click a node in the graph to inspect its details.';
    }
  }

  initializeQa().catch((error) => {
    console.error('G6 QA bootstrap failed:', error);
  });
}());
