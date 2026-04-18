(function () {
  const configNode = document.getElementById('agent-page-config');
  if (!configNode) return;

  let config = {};
  try {
    config = JSON.parse(configNode.textContent || '{}');
  } catch (_error) {
    config = {};
  }

  if (window.marked && typeof window.marked.setOptions === 'function') {
    window.marked.setOptions({
      breaks: true,
      gfm: true,
      mangle: false,
      headerIds: false,
    });
  }

  const ui = config.ui || {};
  const storageKey = 'tekg-academic-agent-session';

  const app = document.getElementById('agentApp');
  const form = document.getElementById('agentForm');
  const questionInput = document.getElementById('agentQuestion');
  const submitButton = document.getElementById('agentSubmit');
  const statusNode = document.getElementById('agentStatus');
  const composerHintNode = document.getElementById('agentComposerHint');
  const conversationNode = document.getElementById('agentConversation');
  const emptyStateNode = document.getElementById('agentEmptyState');
  const chatScroll = document.getElementById('agentChatScroll');
  const inspector = document.getElementById('agentInspector');
  const inspectorTitle = document.getElementById('agentInspectorTitle');
  const inspectorBody = document.getElementById('agentInspectorBody');
  const inspectorClose = document.getElementById('agentInspectorClose');

  let currentMode = 'agent';
  let sessionId = '';
  try {
    sessionId = window.localStorage.getItem(storageKey) || '';
  } catch (_error) {
    sessionId = '';
  }

  let activeAbortController = null;
  let turnCounter = 0;

  const toolDetailStore = new Map();
  const turnStore = new Map();

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function renderMarkdown(markdown) {
    const source = String(markdown || '')
      .replace(/^\[\^(\d+)\]:\s+.+$/gm, '')
      .trim();
    if (!source) {
      return '';
    }
    if (window.marked && typeof window.marked.parse === 'function') {
      return window.marked.parse(source);
    }
    return `<p>${escapeHtml(source)}</p>`;
  }

  function setStatus(text) {
    statusNode.textContent = text || '';
  }

  function setLoading(loading) {
    submitButton.disabled = !!loading;
    questionInput.disabled = !!loading;
  }

  function normalizeCitationTitle(citation) {
    const title = String(citation && citation.title ? citation.title : '').trim();
    if (title) {
      return title;
    }
    const pmid = String(citation && citation.pmid ? citation.pmid : '').trim();
    return pmid ? `PubMed PMID ${pmid}` : 'Open citation';
  }

  function normalizeCitationUrl(citation) {
    const explicitUrl = String(citation && citation.url ? citation.url : '').trim();
    if (explicitUrl) {
      return explicitUrl;
    }
    const pmid = String(citation && citation.pmid ? citation.pmid : '').trim();
    return pmid ? `https://pubmed.ncbi.nlm.nih.gov/${encodeURIComponent(pmid)}/` : '#';
  }

  function dedupeCitations(citations) {
    const seen = new Set();
    const next = [];
    for (const citation of Array.isArray(citations) ? citations : []) {
      if (!citation || typeof citation !== 'object') continue;
      const pmid = String(citation.pmid || '').trim();
      const title = String(citation.title || '').trim();
      const key = pmid || title.toLowerCase();
      if (!key || seen.has(key)) continue;
      seen.add(key);
      next.push({
        ...citation,
        pmid,
        title,
        url: normalizeCitationUrl(citation),
      });
    }
    return next;
  }

  function mergeTurnCitations(turn, citations) {
    if (!turn) return;
    turn.citations = dedupeCitations([...(turn.citations || []), ...(Array.isArray(citations) ? citations : [])]);
  }

  function enhanceAnswerCitations(turn, answerNode) {
    if (!turn || !answerNode) return;

    answerNode.querySelectorAll('p, li, blockquote').forEach((node) => {
      const text = node.textContent || '';
      if (/^\[\^\d+\]:/.test(text.trim())) {
        node.remove();
      }
    });

    const walker = document.createTreeWalker(answerNode, NodeFilter.SHOW_TEXT);
    const textNodes = [];
    while (walker.nextNode()) {
      textNodes.push(walker.currentNode);
    }

    const markerPattern = /\[\^(\d+)\]/g;
    textNodes.forEach((textNode) => {
      const text = textNode.nodeValue || '';
      markerPattern.lastIndex = 0;
      if (!markerPattern.test(text)) {
        return;
      }

      const fragment = document.createDocumentFragment();
      let lastIndex = 0;
      markerPattern.lastIndex = 0;
      let match;
      while ((match = markerPattern.exec(text)) !== null) {
        const start = match.index;
        if (start > lastIndex) {
          fragment.appendChild(document.createTextNode(text.slice(lastIndex, start)));
        }

        const citationIndex = Math.max(0, Number.parseInt(match[1], 10) - 1);
        const citation = turn.citations[citationIndex] || {};
        const anchor = document.createElement('a');
        anchor.className = 'agent-inline-citation';
        anchor.href = normalizeCitationUrl(citation);
        anchor.target = '_blank';
        anchor.rel = 'noopener noreferrer';
        anchor.textContent = String(citationIndex + 1);
        anchor.setAttribute('aria-label', normalizeCitationTitle(citation));
        anchor.dataset.citationTitle = normalizeCitationTitle(citation);

        const sup = document.createElement('sup');
        sup.appendChild(anchor);
        fragment.appendChild(sup);
        lastIndex = markerPattern.lastIndex;
      }

      if (lastIndex < text.length) {
        fragment.appendChild(document.createTextNode(text.slice(lastIndex)));
      }
      textNode.parentNode.replaceChild(fragment, textNode);
    });
  }

  function scrollConversationToBottom(force = false) {
    requestAnimationFrame(() => {
      const distanceToBottom = chatScroll.scrollHeight - chatScroll.scrollTop - chatScroll.clientHeight;
      if (force || distanceToBottom < 160) {
        chatScroll.scrollTop = chatScroll.scrollHeight;
      }
    });
  }

  function ensureConversationStarted() {
    if (emptyStateNode) {
      emptyStateNode.remove();
    }
    app.classList.remove('is-pristine');
  }

  function setMode() {
    currentMode = 'agent';
    app.dataset.mode = currentMode;
    questionInput.placeholder = ui.placeholder_agent || 'Ask the academic agent...';
    composerHintNode.textContent = '';
  }

  function formatElapsed(ms) {
    const seconds = Math.max(0, ms / 1000);
    return seconds < 1 ? `${seconds.toFixed(1)}s` : `${(Math.round(seconds * 10) / 10).toFixed(1)}s`;
  }

  function createTurn(question) {
    ensureConversationStarted();
    const turnId = `turn-${++turnCounter}`;
    const node = document.createElement('article');
    node.className = 'agent-turn is-pending';
    node.dataset.turnId = turnId;
    node.innerHTML = `
      <div class="agent-user-row">
        <div class="agent-user-bubble">${escapeHtml(question)}</div>
      </div>
      <div class="agent-assistant-row">
        <section class="agent-thinking" data-role="thinking">
          <div class="agent-thinking-head">
            <span class="agent-thinking-title">${escapeHtml(ui.thinking_title || 'Deep thinking')}</span>
            <span class="agent-thinking-meta" data-role="thinking-meta">${escapeHtml(ui.thinking_running || 'Running...')}</span>
          </div>
          <div class="agent-thinking-body" data-role="thinking-body"></div>
        </section>
        <section class="agent-answer" data-role="answer"></section>
      </div>
    `;
    conversationNode.appendChild(node);
    const turn = {
      id: turnId,
      node,
      question,
      startedAt: performance.now(),
      language: 'en',
      toolIds: [],
      evidence: [],
      citations: [],
      limits: [],
      answer: '',
    };
    turnStore.set(turnId, turn);
    scrollConversationToBottom(true);
    return turn;
  }

  function createThinkingLine(turn, text, variant = 'bullet') {
    const body = turn.node.querySelector('[data-role="thinking-body"]');
    const line = document.createElement('div');
    line.className = `agent-thinking-line is-${variant}`;
    line.textContent = text;
    body.appendChild(line);
    scrollConversationToBottom();
    return line;
  }

  function createToolEvent(turn, event) {
    const body = turn.node.querySelector('[data-role="thinking-body"]');
    const wrapper = document.createElement('div');
    wrapper.className = 'agent-tool-block';

    const toolId = String(event.detail_payload_id || `tool-${turn.id}-${turn.toolIds.length + 1}`);
    const label = String(event.display_label || event.summary || event.message || event.plugin_name || 'Tool event');
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'agent-tool-event';
    button.dataset.detailId = toolId;
    button.title = ui.tool_open_hint || 'Click to inspect details';
    button.innerHTML = `
      <span class="agent-tool-event-icon">◎</span>
      <span class="agent-tool-event-label">${escapeHtml(label)}</span>
    `;
    wrapper.appendChild(button);

    const payload = event.payload && typeof event.payload === 'object' ? event.payload : {};
    const previewItems = Array.isArray(payload.preview_items) ? payload.preview_items.slice(0, 5) : [];
    if (previewItems.length) {
      const preview = document.createElement('div');
      preview.className = 'agent-tool-preview';
      preview.innerHTML = previewItems.map((item) => {
        if (typeof item === 'string') {
          return `<div class="agent-tool-preview-item">${escapeHtml(item)}</div>`;
        }
        const title = escapeHtml(item.title || item.label || item.summary || '');
        const subline = escapeHtml(item.meta || item.subtitle || '');
        return `<div class="agent-tool-preview-item"><span>${title}</span>${subline ? `<small>${subline}</small>` : ''}</div>`;
      }).join('');
      wrapper.appendChild(preview);
    }

    body.appendChild(wrapper);
    turn.toolIds.push(toolId);
    mergeTurnCitations(turn, payload.citations || payload.display_details?.citations || []);
    toolDetailStore.set(toolId, {
      title: event.plugin_name || label,
      summary: event.summary || event.message || '',
      payload: payload,
      plugin_name: event.plugin_name || '',
    });
    scrollConversationToBottom();
  }

  function updateThinkingMeta(turn, done) {
    const meta = turn.node.querySelector('[data-role="thinking-meta"]');
    const elapsed = formatElapsed(performance.now() - turn.startedAt);
    meta.textContent = done
      ? `${ui.thinking_done || 'Done'} · ${elapsed}`
      : `${ui.thinking_running || 'Running...'} · ${elapsed}`;
  }

  function setAnswer(turn, markdown, language) {
    turn.answer = String(markdown || '');
    turn.language = language || turn.language || 'en';
    const answerNode = turn.node.querySelector('[data-role="answer"]');
    answerNode.innerHTML = renderMarkdown(turn.answer);
    enhanceAnswerCitations(turn, answerNode);
    scrollConversationToBottom();
  }

  function finalizeTurn(turn) {
    turn.node.classList.remove('is-pending');
    updateThinkingMeta(turn, true);
    scrollConversationToBottom(true);
  }

  function formatInspectorList(items, emptyMessage) {
    if (!Array.isArray(items) || items.length === 0) {
      return `<div class="agent-detail-empty">${escapeHtml(emptyMessage)}</div>`;
    }
    return `<div class="agent-detail-list">${items.map((item) => {
      if (typeof item === 'string') {
        return `<div class="agent-detail-item">${escapeHtml(item)}</div>`;
      }
      const title = escapeHtml(item.title || item.label || item.name || '');
      const meta = escapeHtml(item.meta || item.summary || item.subtitle || '');
      const body = escapeHtml(item.body || item.abstract_summary || item.text || '');
      const link = item.url
        ? `<a class="agent-link" href="${escapeHtml(item.url)}" target="_blank" rel="noopener noreferrer">${title || escapeHtml(item.url)}</a>`
        : title;
      return `
        <div class="agent-detail-item">
          ${link ? `<strong>${link}</strong>` : ''}
          ${meta ? `<div class="agent-detail-meta">${meta}</div>` : ''}
          ${body ? `<div class="agent-detail-body">${body}</div>` : ''}
        </div>
      `;
    }).join('')}</div>`;
  }

  function buildInspectorSection(title, html) {
    return `
      <section class="agent-detail-card">
        <h4>${escapeHtml(title)}</h4>
        ${html}
      </section>
    `;
  }

  function openInspector(detailId) {
    const detail = toolDetailStore.get(detailId);
    if (!detail) return;

    const payload = detail.payload || {};
    const resultCounts = payload.result_counts || {};
    const countLines = Object.keys(resultCounts).map((key) => `${key}: ${resultCounts[key]}`);
    const pluginName = String(detail.plugin_name || detail.title || '');
    const isLiteraturePlugin = pluginName === 'Literature Plugin';
    const isGraphPlugin = pluginName === 'Graph Plugin';

    inspectorTitle.textContent = detail.title || ui.plugin_details || 'Plugin Details';
    const sections = [
      buildInspectorSection(ui.inspector_summary || 'Summary', `
        <div class="agent-detail-meta">
          ${detail.summary ? `<p>${escapeHtml(detail.summary)}</p>` : ''}
          ${countLines.length ? `<p>${escapeHtml(countLines.join(' · '))}</p>` : ''}
        </div>
      `),
    ];

    if (isLiteraturePlugin) {
      sections.push(
        buildInspectorSection(ui.inspector_citations || 'Citations', formatInspectorList(
          payload.citations || payload.display_details?.citations || [],
          ui.tool_empty_citations || 'No citations were returned for this tool call.',
        )),
      );
    } else if (isGraphPlugin) {
      sections.push(
        buildInspectorSection(ui.inspector_evidence || 'Evidence', formatInspectorList(
          payload.evidence_items || payload.display_details?.evidence_items || [],
          ui.tool_empty_evidence || 'No evidence items were returned for this tool call.',
        )),
      );
    } else {
      sections.push(
        buildInspectorSection(ui.inspector_evidence || 'Evidence', formatInspectorList(
          payload.evidence_items || payload.display_details?.evidence_items || [],
          ui.tool_empty_evidence || 'No evidence items were returned for this tool call.',
        )),
        buildInspectorSection(ui.inspector_citations || 'Citations', formatInspectorList(
          payload.citations || payload.display_details?.citations || [],
          ui.tool_empty_citations || 'No citations were returned for this tool call.',
        )),
        buildInspectorSection(ui.inspector_data || 'Returned Data', payload.raw_preview
          ? `<pre class="agent-detail-pre">${escapeHtml(JSON.stringify(payload.raw_preview, null, 2))}</pre>`
          : `<div class="agent-detail-empty">${escapeHtml(ui.tool_empty_data || 'No result payload was returned.')}</div>`),
        buildInspectorSection(ui.inspector_errors || 'Errors', formatInspectorList(
          payload.errors || payload.display_details?.errors || [],
          ui.tool_empty_errors || 'No plugin errors were reported.',
        )),
      );
    }

    inspectorBody.innerHTML = sections.join('');

    inspector.setAttribute('aria-hidden', 'false');
    app.classList.add('is-inspector-open');
  }

  function closeInspector() {
    inspector.setAttribute('aria-hidden', 'true');
    app.classList.remove('is-inspector-open');
  }

  function handleStreamEvent(turn, event) {
    if (!event || typeof event !== 'object') return;

    if (event.type === 'planning') {
      createThinkingLine(turn, String(event.message || ''), 'bullet');
      updateThinkingMeta(turn, false);
      return;
    }

    if (event.type === 'tool_start') {
      if (event.message) {
        createThinkingLine(turn, String(event.message), 'bullet');
      }
      updateThinkingMeta(turn, false);
      return;
    }

    if (event.type === 'tool_progress') {
      if (event.message) {
        createThinkingLine(turn, String(event.message), 'bullet');
      }
      updateThinkingMeta(turn, false);
      return;
    }

    if (event.type === 'tool_result') {
      createToolEvent(turn, event);
      if (event.message) {
        createThinkingLine(turn, String(event.message), 'bullet');
      }
      updateThinkingMeta(turn, false);
      return;
    }

    if (event.type === 'synthesizing') {
      createThinkingLine(turn, String(event.message || ''), 'bullet');
      updateThinkingMeta(turn, false);
      return;
    }

    if (event.type === 'answer') {
      setAnswer(turn, String(event.message || ''), String(event.language || turn.language || 'en'));
      return;
    }

    if (event.type === 'error') {
      createThinkingLine(turn, String(event.message || 'The request failed.'), 'error');
      return;
    }

    if (event.type === 'done') {
      finalizeTurn(turn);
    }
  }

  async function readEventStream(response, onEvent) {
    const reader = response.body.getReader();
    const decoder = new TextDecoder('utf-8');
    let buffer = '';

    while (true) {
      const { value, done } = await reader.read();
      if (done) {
        break;
      }
      buffer += decoder.decode(value, { stream: true });

      let boundaryIndex = buffer.indexOf('\n\n');
      while (boundaryIndex !== -1) {
        const chunk = buffer.slice(0, boundaryIndex);
        buffer = buffer.slice(boundaryIndex + 2);
        const event = parseStreamChunk(chunk);
        if (event) {
          onEvent(event);
        }
        boundaryIndex = buffer.indexOf('\n\n');
      }
    }

    const finalChunk = buffer.trim();
    if (finalChunk) {
      const event = parseStreamChunk(finalChunk);
      if (event) {
        onEvent(event);
      }
    }
  }

  function parseStreamChunk(chunk) {
    const lines = String(chunk || '')
      .split(/\r?\n/)
      .map((line) => line.trimEnd())
      .filter(Boolean);
    if (!lines.length) {
      return null;
    }
    const dataLines = lines
      .filter((line) => line.startsWith('data:'))
      .map((line) => line.slice(5).trimStart());
    if (!dataLines.length) {
      return null;
    }
    try {
      return JSON.parse(dataLines.join('\n'));
    } catch (_error) {
      return null;
    }
  }

  async function submitQuestion(event) {
    event.preventDefault();
    const question = String(questionInput.value || '').trim();
    if (!question) {
      setStatus('Please enter a question.');
      questionInput.focus();
      return;
    }
    if (activeAbortController) {
      activeAbortController.abort();
    }

    const turn = createTurn(question);
    const abortController = new AbortController();
    activeAbortController = abortController;

    setLoading(true);
    setStatus(ui.thinking_running || 'Running...');
    questionInput.value = '';

    try {
      const response = await fetch(config.streamApiUrl || '/TE-/api/agent_stream.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream' },
        body: JSON.stringify({
          question,
          model: String(config.defaultModel || 'deepseek-chat').trim(),
          mode: 'academic',
          session_id: sessionId || undefined,
        }),
        signal: abortController.signal,
      });

      if (!response.ok || !response.body) {
        throw new Error(`Streaming request failed with HTTP ${response.status}`);
      }

      await readEventStream(response, (streamEvent) => {
        if (streamEvent.session_id) {
          sessionId = String(streamEvent.session_id);
          try {
            window.localStorage.setItem(storageKey, sessionId);
          } catch (_error) {}
        }
        handleStreamEvent(turn, streamEvent);
      });

      finalizeTurn(turn);
      setStatus('');
    } catch (error) {
      const message = error && error.name === 'AbortError'
        ? 'The request was cancelled.'
        : (error && error.message ? error.message : 'Unknown request failure');
      handleStreamEvent(turn, { type: 'error', message });
      setAnswer(turn, message, 'en');
      finalizeTurn(turn);
      setStatus('The request failed.');
    } finally {
      if (activeAbortController === abortController) {
        activeAbortController = null;
      }
      setLoading(false);
      questionInput.focus();
    }
  }

  questionInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      form.requestSubmit();
    }
  });

  conversationNode.addEventListener('click', (event) => {
    const toolEvent = event.target.closest('.agent-tool-event');
    if (!toolEvent) return;
    openInspector(String(toolEvent.dataset.detailId || ''));
  });

  inspectorClose.addEventListener('click', closeInspector);

  form.addEventListener('submit', submitQuestion);
  setMode();
})();
