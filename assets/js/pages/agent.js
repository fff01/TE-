(function () {
  const configNode = document.getElementById('agent-page-config');
  if (!configNode) return;

  let config = {};
  try {
    config = JSON.parse(configNode.textContent || '{}');
  } catch (_error) {
    config = {};
  }

  const storageKey = 'tekg-academic-agent-session';
  let sessionId = '';
  try {
    sessionId = window.localStorage.getItem(storageKey) || '';
  } catch (_error) {
    sessionId = '';
  }

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
  const modeSwitch = document.getElementById('agentModeSwitch');
  const threadHead = document.getElementById('agentThreadHead');
  const threadTitle = document.getElementById('agentThreadTitle');
  const threadMode = document.getElementById('agentThreadMode');

  const turnStore = new Map();
  let turnCounter = 0;
  let currentMode = 'agent';
  const ui = config.ui || {};

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function setStatus(text) {
    statusNode.textContent = text || '';
  }

  function setLoading(loading) {
    submitButton.disabled = !!loading;
  }

  function setMode(nextMode) {
    currentMode = nextMode === 'quick' ? 'quick' : 'agent';
    app.dataset.mode = currentMode;
    document.querySelectorAll('.agent-mode-button').forEach((button) => {
      button.classList.toggle('is-active', button.dataset.mode === currentMode);
    });
    if (currentMode === 'quick') {
      questionInput.placeholder = ui.placeholder_quick || 'Quick QA is coming soon.';
      composerHintNode.textContent = ui.quick_mode_notice || '';
      threadMode.textContent = ui.quick_mode || 'Quick QA';
    } else {
      questionInput.placeholder = ui.placeholder_agent || 'Message the academic agent...';
      composerHintNode.textContent = '';
      threadMode.textContent = ui.agent_mode || 'Agent';
    }
  }

  function autoResizeTextarea() {
    questionInput.style.height = 'auto';
    questionInput.style.height = Math.min(questionInput.scrollHeight, 280) + 'px';
  }

  function scrollConversationToBottom() {
    requestAnimationFrame(() => {
      chatScroll.scrollTop = chatScroll.scrollHeight;
    });
  }

  function ensureConversationStarted() {
    if (emptyStateNode) {
      emptyStateNode.remove();
    }
    app.classList.remove('is-pristine');
    threadHead.hidden = false;
  }

  function createTurn(question) {
    ensureConversationStarted();
    if (!threadTitle.textContent) {
      threadTitle.textContent = question.length > 40 ? question.slice(0, 40) + '…' : question;
    }
    const turnId = 'turn-' + (++turnCounter);
    const turn = document.createElement('article');
    turn.className = 'agent-turn is-pending';
    turn.dataset.turnId = turnId;
    turn.innerHTML = `
      <div class="agent-user-row">
        <div class="agent-user-bubble">${escapeHtml(question)}</div>
      </div>
      <div class="agent-assistant-row">
        <div class="agent-thinking">
          <div class="agent-thinking-head">
            <span>Deep thinking</span>
            <span class="agent-assistant-meta" data-role="timer">Running...</span>
          </div>
          <div class="agent-thinking-body" data-role="thinking-body">
            <div class="agent-thinking-bullet">Planning the academic route and choosing plugins.</div>
          </div>
        </div>
        <div class="agent-answer" data-role="answer">Working on the answer...</div>
        <div class="agent-assistant-meta" data-role="meta"></div>
      </div>
    `;
    conversationNode.appendChild(turn);
    scrollConversationToBottom();
    return { id: turnId, node: turn, startedAt: performance.now() };
  }

  function simpleMarkdownToHtml(text) {
    const escaped = escapeHtml(text || '');
    const lines = escaped.split(/\r?\n/);
    const parts = [];
    let inList = false;

    const closeList = () => {
      if (inList) {
        parts.push('</ul>');
        inList = false;
      }
    };

    lines.forEach((line) => {
      const trimmed = line.trim();
      if (!trimmed) {
        closeList();
        return;
      }
      if (trimmed.startsWith('## ')) {
        closeList();
        parts.push(`<h3>${trimmed.slice(3)}</h3>`);
        return;
      }
      if (trimmed.startsWith('### ')) {
        closeList();
        parts.push(`<h4>${trimmed.slice(4)}</h4>`);
        return;
      }
      if (trimmed.startsWith('- ')) {
        if (!inList) {
          parts.push('<ul>');
          inList = true;
        }
        parts.push(`<li>${trimmed.slice(2)}</li>`);
        return;
      }
      closeList();
      parts.push(`<p>${trimmed}</p>`);
    });

    closeList();
    return parts.join('');
  }

  function formatElapsed(ms) {
    const seconds = Math.max(0, ms / 1000);
    if (seconds < 1) {
      return seconds.toFixed(1) + 's';
    }
    return Math.round(seconds * 10) / 10 + 's';
  }

  function toolEventLabel(call, language) {
    const resultCount = Array.isArray(call.results)
      ? call.results.length
      : (call.results && typeof call.results === 'object'
        ? Object.keys(call.results).length
        : 0);

    if (call.plugin_name === 'Graph Plugin') {
      return language === 'zh'
        ? `查询到了 ${resultCount} 条关系`
        : `Queried ${resultCount} graph relations`;
    }
    if (call.plugin_name === 'Literature Plugin') {
      const total = (call.results && ((call.results.local_citation_count || 0) + (call.results.pubmed_citation_count || 0))) || 0;
      return language === 'zh'
        ? `查阅了 ${total} 篇文献摘要`
        : `Reviewed ${total} literature records`;
    }
    if (call.plugin_name === 'Tree Plugin') {
      return language === 'zh'
        ? `解析了 ${resultCount} 条分类路径`
        : `Resolved ${resultCount} classification paths`;
    }
    if (call.plugin_name === 'Expression Plugin') {
      return language === 'zh'
        ? `整理了 ${resultCount} 个表达摘要`
        : `Summarized ${resultCount} expression profiles`;
    }
    if (call.plugin_name === 'Genome Plugin') {
      return language === 'zh'
        ? `定位了 ${resultCount} 个基因组位点`
        : `Resolved ${resultCount} genomic loci`;
    }
    return call.query_summary || call.plugin_name || 'Tool call';
  }

  function renderThinking(turn, data, elapsedMs) {
    const body = turn.node.querySelector('[data-role="thinking-body"]');
    const timer = turn.node.querySelector('[data-role="timer"]');
    body.innerHTML = '';
    timer.textContent = `(${formatElapsed(elapsedMs)})`;

    const trace = Array.isArray(data.reasoning_trace) ? data.reasoning_trace : [];
    const pluginCalls = Array.isArray(data.plugin_calls) ? data.plugin_calls : [];

    trace
      .filter((item) => item.step !== 'synthesizing')
      .forEach((item) => {
        const bullet = document.createElement('div');
        bullet.className = 'agent-thinking-bullet';
        bullet.textContent = item.details || item.title || item.step || '';
        body.appendChild(bullet);
      });

    pluginCalls.forEach((call, index) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'agent-tool-event';
      button.dataset.turnId = turn.id;
      button.dataset.pluginIndex = String(index);
      button.innerHTML = `
        <span class="agent-tool-event-icon">◎</span>
        <span>${escapeHtml(toolEventLabel(call, data.language || 'en'))}</span>
      `;
      body.appendChild(button);
    });
  }

  function renderTurn(turn, data, elapsedMs) {
    turnStore.set(turn.id, data);
    turn.node.classList.remove('is-pending');
    turn.node.querySelector('[data-role="answer"]').innerHTML = simpleMarkdownToHtml(data.answer || '');
    turn.node.querySelector('[data-role="meta"]').textContent = `${data.model_provider || 'model'} · ${data.model || ''} · confidence ${String(data.confidence || 'unknown').toUpperCase()}`;
    renderThinking(turn, data, elapsedMs);
    scrollConversationToBottom();
  }

  function buildInspectorSection(title, innerHtml) {
    return `
      <section class="agent-detail-card">
        <h4>${escapeHtml(title)}</h4>
        ${innerHtml}
      </section>
    `;
  }

  function citationMarkup(citations) {
    if (!Array.isArray(citations) || citations.length === 0) {
      return '<div class="agent-detail-meta">No citations were returned for this tool call.</div>';
    }
    return `<div class="agent-detail-list">${citations.map((citation) => {
      const title = citation.title || 'Untitled record';
      const pmid = citation.pmid ? `PMID: ${citation.pmid}` : '';
      const meta = [citation.source || '', pmid, citation.journal || '', citation.year || ''].filter(Boolean).join(' · ');
      const titleHtml = citation.url
        ? `<a class="agent-link" href="${escapeHtml(citation.url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(title)}</a>`
        : escapeHtml(title);
      const abstract = citation.abstract_summary ? `<div class="agent-detail-meta">${escapeHtml(citation.abstract_summary)}</div>` : '';
      return `<div class="agent-detail-item"><strong>${titleHtml}</strong><div class="agent-detail-meta">${escapeHtml(meta)}</div>${abstract}</div>`;
    }).join('')}</div>`;
  }

  function resultPreviewMarkup(results) {
    if (results == null) {
      return '<div class="agent-detail-meta">No result payload was returned.</div>';
    }
    const preview = JSON.stringify(results, null, 2);
    return `<pre class="agent-detail-pre">${escapeHtml(preview)}</pre>`;
  }

  function openInspectorFor(turnId, pluginIndex) {
    const data = turnStore.get(turnId);
    const call = data && Array.isArray(data.plugin_calls) ? data.plugin_calls[pluginIndex] : null;
    if (!call) {
      return;
    }
    inspectorTitle.textContent = call.plugin_name || 'Plugin details';
    inspectorBody.innerHTML = [
      buildInspectorSection('Summary', `
        <div class="agent-detail-meta">
          <div><strong>Status:</strong> ${escapeHtml(call.status || '')}</div>
          <div><strong>Latency:</strong> ${escapeHtml(String(call.latency_ms || 0))} ms</div>
          <div><strong>Query:</strong> ${escapeHtml(call.query_summary || '')}</div>
        </div>
      `),
      buildInspectorSection('Evidence', Array.isArray(call.evidence_items) && call.evidence_items.length
        ? `<div class="agent-detail-list">${call.evidence_items.map((item) => `<div class="agent-detail-item">${escapeHtml(item)}</div>`).join('')}</div>`
        : '<div class="agent-detail-meta">No evidence items were returned for this tool call.</div>'),
      buildInspectorSection('Citations', citationMarkup(call.citations)),
      buildInspectorSection('Returned Data', resultPreviewMarkup(call.results)),
      buildInspectorSection('Errors', Array.isArray(call.errors) && call.errors.length
        ? `<div class="agent-detail-list">${call.errors.map((item) => `<div class="agent-detail-item">${escapeHtml(item)}</div>`).join('')}</div>`
        : '<div class="agent-detail-meta">No plugin errors were reported.</div>'),
    ].join('');
    inspector.setAttribute('aria-hidden', 'false');
    app.classList.add('is-inspector-open');
  }

  function closeInspector() {
    inspector.setAttribute('aria-hidden', 'true');
    app.classList.remove('is-inspector-open');
  }

  async function submitQuestion(event) {
    event.preventDefault();
    const question = (questionInput.value || '').trim();
    if (!question) {
      setStatus('Please enter a question.');
      questionInput.focus();
      return;
    }
    if (currentMode === 'quick') {
      setStatus(ui.quick_mode_notice || 'Quick QA is not available yet. Please switch back to Agent mode.');
      return;
    }

    const turn = createTurn(question);
    setLoading(true);
    setStatus('Thinking...');
    questionInput.value = '';
    autoResizeTextarea();

    try {
      const response = await fetch(config.apiUrl || '/TE-/api/agent.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          question,
          model: (config.defaultModel || 'deepseek-chat').trim(),
          mode: 'academic',
          session_id: sessionId || undefined,
        }),
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok) {
        throw new Error(payload.error || `Request failed with HTTP ${response.status}`);
      }
      const data = payload.data || {};
      sessionId = data.session_id || sessionId;
      try {
        if (sessionId) {
          window.localStorage.setItem(storageKey, sessionId);
        }
      } catch (_error) {}
      renderTurn(turn, data, performance.now() - turn.startedAt);
      setStatus('');
    } catch (error) {
      renderTurn(turn, {
        answer: `## Error\n${error && error.message ? error.message : 'Unknown request failure'}`,
        model_provider: 'system',
        model: config.defaultModel || 'deepseek-chat',
        confidence: 'low',
        language: 'en',
        reasoning_trace: [{
          step: 'planning',
          title: 'Request failed',
          details: error && error.message ? error.message : 'Unknown request failure',
        }],
        plugin_calls: [],
      }, performance.now() - turn.startedAt);
      setStatus('The request failed.');
    } finally {
      setLoading(false);
      questionInput.focus();
    }
  }

  questionInput.addEventListener('input', autoResizeTextarea);
  questionInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      form.requestSubmit();
    }
  });

  conversationNode.addEventListener('click', (event) => {
    const target = event.target.closest('.agent-tool-event');
    if (!target) return;
    openInspectorFor(target.dataset.turnId || '', Number(target.dataset.pluginIndex || 0));
  });

  inspectorClose.addEventListener('click', closeInspector);

  if (modeSwitch) {
    modeSwitch.addEventListener('click', (event) => {
      const button = event.target.closest('.agent-mode-button');
      if (!button) return;
      setMode(button.dataset.mode || 'agent');
    });
  }

  form.addEventListener('submit', submitQuestion);
  setMode('agent');
  autoResizeTextarea();
})();
