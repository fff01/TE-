(function () {
  if (window.__TEKG_RENDERER_MODE !== 'g6') return;

  const nodeDetails = document.getElementById('node-details');
  const chatMessages = document.getElementById('chat-messages');
  const userInput = document.getElementById('user-question');
  const sendBtn = document.getElementById('send-question');

  if (!chatMessages || !userInput || !sendBtn) return;

  function byId(id) {
    return document.getElementById(id);
  }

  function getBridge() {
    return window.__TEKG_G6_BRIDGE || null;
  }

  function getGraphState() {
    const bridge = getBridge();
    if (bridge && typeof bridge.getState === 'function') {
      return bridge.getState() || {};
    }
    return {
      mode: bridge && typeof bridge.getMode === 'function' ? bridge.getMode() : 'tree',
      query: bridge && typeof bridge.getCurrentQuery === 'function' ? bridge.getCurrentQuery() : '',
      fixedView: bridge && typeof bridge.getFixedView === 'function' ? bridge.getFixedView() : false,
      keyNodeLevel: bridge && typeof bridge.getKeyNodeLevel === 'function' ? bridge.getKeyNodeLevel() : 1,
      selectedNode: bridge && typeof bridge.getSelectedNode === 'function' ? bridge.getSelectedNode() : null,
    };
  }

  function uiText() {
    if (typeof ui === 'object' && ui && ui[currentLang]) return ui[currentLang];
    return {
      qaTitle: 'Interactive QA Demo',
      ph: 'Ask your question',
      q: 'LINE-1 related diseases',
      intro: 'Ask about the current graph, and the answer will reuse the same backend QA pipeline as the Cytoscape page.',
      providerPrefix: 'Model: ',
      modelLabel: 'Model',
      modelQwen: 'Qwen',
      modelDeepSeek: 'DeepSeek',
      fallback: 'No answer is available right now.',
      fixedOn: 'Fixed view: On',
      fixedOff: 'Fixed view: Off',
      empty: 'Click a node or edge to inspect graph details.',
    };
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
    if (title) title.textContent = isPromptMode
      ? (currentLang === 'zh' ? '自定义提示词' : 'Custom prompt')
      : (currentLang === 'zh' ? '自定义回答深度' : 'Custom answer depth');
    if (help) help.textContent = isPromptMode
      ? (currentLang === 'zh'
        ? '在这里写下你希望智能问答遵循的语气、结构、重点或限制条件。点击确认后会暂时保存，并在后续提问时优先使用。'
        : 'Write any tone, structure, emphasis, or constraints you want the QA system to follow. Click confirm to save it temporarily for subsequent questions.')
      : (currentLang === 'zh'
        ? '在这里设置回答深度的自定义参数。row 表示最多取多少条结构化关系记录，references 表示最多附上多少篇参考文献。'
        : 'Set custom answer-depth parameters here. Row means the maximum number of structured relation records, and references means the maximum number of cited references.');

    if (promptField) {
      promptField.style.display = isPromptMode ? 'block' : 'none';
      promptField.placeholder = currentLang === 'zh'
        ? '例如：请用学术但通俗的中文回答；先给结论，再分点说明机制；尽量引用 PMID。'
        : 'For example: answer in academic but plain English; give the conclusion first; then explain mechanisms in bullets; cite PMID whenever possible.';
      if (document.activeElement !== promptField) {
        promptField.value = customPromptDraft || currentCustomPrompt || '';
      }
    }

    if (depthWrap) depthWrap.classList.toggle('active', !isPromptMode);
    if (rowsLabel) rowsLabel.textContent = currentLang === 'zh' ? 'row（关系条数）' : 'row (relation limit)';
    if (referencesLabel) referencesLabel.textContent = currentLang === 'zh' ? 'references（文献条数）' : 'references (reference limit)';
    if (depthNote) depthNote.textContent = currentLang === 'zh'
      ? 'row 表示最多取多少条结构化关系记录，references 表示最多附上多少篇参考文献。建议上界分别不超过 12 和 8，避免回答过长。'
      : 'Row means the maximum number of structured relation records, and references means the maximum number of cited references. Recommended upper bounds are 12 and 8 to avoid overly long answers.';

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
    const t = uiText();

    if (byId('qa-title')) byId('qa-title').textContent = t.qaTitle || 'Interactive QA Demo';
    if (byId('qa-mode-label')) byId('qa-mode-label').textContent = currentLang === 'zh' ? '回答模式' : 'Answer mode';
    if (byId('qa-depth-label')) byId('qa-depth-label').textContent = currentLang === 'zh' ? '回答深度' : 'Answer depth';
    if (byId('qa-model-label')) byId('qa-model-label').textContent = t.modelLabel || 'Model';

    if (modeSimpleBtn && modeDetailedBtn && modeCustomBtn) {
      modeSimpleBtn.textContent = currentLang === 'zh' ? '简单' : 'Brief';
      modeDetailedBtn.textContent = currentLang === 'zh' ? '详细' : 'Detailed';
      modeCustomBtn.textContent = currentLang === 'zh' ? '自定义' : 'Custom';
      modeSimpleBtn.classList.toggle('active', currentAnswerStyle === 'simple');
      modeDetailedBtn.classList.toggle('active', currentAnswerStyle === 'detailed');
      modeCustomBtn.classList.toggle('active', currentAnswerStyle === 'custom');
    }

    if (depthShallowBtn && depthMediumBtn && depthDeepBtn && depthCustomBtn) {
      depthShallowBtn.textContent = currentLang === 'zh' ? '浅' : 'Shallow';
      depthMediumBtn.textContent = currentLang === 'zh' ? '中' : 'Medium';
      depthDeepBtn.textContent = currentLang === 'zh' ? '深' : 'Deep';
      depthCustomBtn.textContent = currentLang === 'zh' ? '自定义' : 'Custom';
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

  function closeCustomEditor(options) {
    const save = options && options.save === true;
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

  function renderBubbleContent(text, sender) {
    const raw = String(text || '');
    if (sender === 'user') return escapeHtml(raw).replace(/\n/g, '<br>');
    if (window.marked && typeof window.marked.parse === 'function') {
      return window.marked.parse(raw);
    }
    return escapeHtml(raw).replace(/\n/g, '<br>');
  }

  function addMessage(text, sender) {
    const item = document.createElement('div');
    item.className = `msg ${sender}`;
    item.innerHTML =
      `<div class="avatar"><i class="fas ${sender === 'user' ? 'fa-user' : 'fa-robot'}"></i></div>` +
      `<div class="bubble">${renderBubbleContent(text, sender)}</div>`;
    chatMessages.appendChild(item);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    return item;
  }

  function renderIntro() {
    chatMessages.innerHTML = '';
    addMessage(uiText().intro || 'Ask about the current graph, and the answer will reuse the same backend QA pipeline as the Cytoscape page.', 'assistant');
  }

  function formatNodeLabel(node) {
    if (!node) return currentLang === 'zh' ? '无' : 'None';
    return String(node.displayLabel || node.rawLabel || node.label || node.id || '').trim() || (currentLang === 'zh' ? '无' : 'None');
  }

  function buildEffectiveQuestion(question) {
    const state = getGraphState();
    const modeLabel = state.mode === 'dynamic'
      ? (currentLang === 'zh' ? '动态图' : 'Dynamic graph')
      : (currentLang === 'zh' ? '分类树' : 'Tree');
    const lines = [
      String(question || '').trim(),
      '',
      currentLang === 'zh' ? '[当前 G6 图谱上下文]' : '[Current G6 graph context]',
      `${currentLang === 'zh' ? '模式' : 'Mode'}: ${modeLabel}`,
      `${currentLang === 'zh' ? '中心节点' : 'Center node'}: ${state.query ? String(state.query).trim() : (currentLang === 'zh' ? '无' : 'None')}`,
      `${currentLang === 'zh' ? '选中节点' : 'Selected node'}: ${formatNodeLabel(state.selectedNode)}`,
      `${currentLang === 'zh' ? '关键节点层数' : 'Key-node level'}: ${state.keyNodeLevel || 1}`,
      `${currentLang === 'zh' ? '固定视图' : 'Fixed view'}: ${state.fixedView ? (currentLang === 'zh' ? '开' : 'On') : (currentLang === 'zh' ? '关' : 'Off')}`,
    ];
    return lines.join('\n');
  }

  function formatBackendAnswer(result) {
    const t = uiText();
    const effectiveProvider = (result.model_provider || currentModelProvider || 'qwen').toLowerCase() === 'deepseek' ? 'deepseek' : 'qwen';
    const styleLabel = currentLang === 'zh'
      ? (currentAnswerStyle === 'custom' ? '自定义模式' : result.answer_style === 'detailed' ? '详细模式' : '简单模式')
      : (currentAnswerStyle === 'custom' ? 'Custom mode' : result.answer_style === 'detailed' ? 'Detailed mode' : 'Brief mode');
    const depthLabel = result.answer_depth === 'custom'
      ? (currentLang === 'zh'
        ? `自定义（row=${result.custom_rows || currentCustomDepth.rows}，references=${result.custom_references || currentCustomDepth.references}）`
        : `Custom (row=${result.custom_rows || currentCustomDepth.rows}, references=${result.custom_references || currentCustomDepth.references})`)
      : (currentLang === 'zh'
        ? ({ shallow: '浅', medium: '中', deep: '深' }[result.answer_depth] || '浅')
        : ({ shallow: 'Shallow', medium: 'Medium', deep: 'Deep' }[result.answer_depth] || 'Shallow'));

    const prefix = currentLang === 'zh'
      ? `> ${(t.providerPrefix || '模型：')}${effectiveProvider === 'deepseek' ? (t.modelDeepSeek || 'DeepSeek') : (t.modelQwen || 'Qwen')}\n> ${styleLabel} · 回答深度：${depthLabel}\n\n`
      : `> ${(t.providerPrefix || 'Model: ')}${effectiveProvider === 'deepseek' ? (t.modelDeepSeek || 'DeepSeek') : (t.modelQwen || 'Qwen')}\n> ${styleLabel} · Depth: ${depthLabel}\n\n`;
    return `${prefix}${result.answer || t.fallback || 'No answer is available right now.'}`;
  }

  function buildFallbackAnswer(question, error) {
    const state = getGraphState();
    const lines = [
      currentLang === 'zh'
        ? '后端暂时不可用，下面先保留当前图谱上下文，方便继续排查：'
        : 'Backend is temporarily unavailable. Here is the current graph context for reference:',
      '',
      `- ${currentLang === 'zh' ? '中心节点' : 'Center node'}: ${state.query ? String(state.query).trim() : (currentLang === 'zh' ? '无' : 'None')}`,
      `- ${currentLang === 'zh' ? '选中节点' : 'Selected node'}: ${formatNodeLabel(state.selectedNode)}`,
      `- ${currentLang === 'zh' ? '关键节点层数' : 'Key-node level'}: ${state.keyNodeLevel || 1}`,
      `- ${currentLang === 'zh' ? '固定视图' : 'Fixed view'}: ${state.fixedView ? (currentLang === 'zh' ? '开' : 'On') : (currentLang === 'zh' ? '关' : 'Off')}`,
      '',
      `> ${String(question || '').trim()}`,
    ];
    if (error && error.message) lines.push('', `> ${error.message}`);
    return lines.join('\n');
  }

  async function answerWithBackend(question) {
    const effectiveQuestion = buildEffectiveQuestion(question);
    const effectiveStyle = currentAnswerStyle === 'custom' ? 'custom' : currentAnswerStyle;
    const payload = {
      question: effectiveQuestion,
      language: currentLang,
      answer_style: effectiveStyle,
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

    const loadingText = currentLang === 'zh'
      ? '正在检索图数据库并生成回答…'
      : 'Retrieving graph evidence and generating the answer…';
    const loadingNode = addMessage(loadingText, 'assistant');

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

    window.addEventListener('tekg:g6-state-change', () => {
      updateAnswerModeUi();
    });
  }

  async function initializeQa() {
    if (typeof loadUiText === 'function' || typeof loadLocalQaTemplates === 'function') {
      await Promise.allSettled([
        typeof loadUiText === 'function' ? loadUiText() : Promise.resolve(),
        typeof loadLocalQaTemplates === 'function' ? loadLocalQaTemplates() : Promise.resolve(),
      ]);
    }

    if (!userInput.value) {
      userInput.value = uiText().q || 'LINE-1 related diseases';
    }

    bindEvents();
    updateAnswerModeUi();
    renderIntro();

    if (nodeDetails && !String(nodeDetails.textContent || '').trim()) {
      nodeDetails.textContent = uiText().empty || 'Click a node or edge to inspect graph details.';
    }
  }

  initializeQa().catch((error) => {
    console.error('G6 QA bootstrap failed:', error);
  });
}());
