(() => {
  const ANSWER_GRAPH_ACTION_ENABLED = false;
  const root = document.getElementById('previewDeepThink');
  const form = document.getElementById('previewDeepThinkForm');
  const input = document.getElementById('previewDeepThinkInput');
  const submitBtn = document.getElementById('previewDeepThinkSubmit');
  const statusEl = document.getElementById('previewDeepThinkStatus');
  const messagesEl = document.getElementById('previewDeepThinkMessages');
  const clearGraphBtn = document.getElementById('previewDeepThinkClearGraph');
  const configNode = document.getElementById('preview-config');
  if (!root || !form || !input || !submitBtn || !statusEl || !messagesEl || !configNode) return;

  let config = {};
  try {
    config = JSON.parse(configNode.textContent || '{}');
  } catch (_error) {
    config = {};
  }

  const storageKey = String(config.sessionStorageKey || 'tekg-preview-deepthink-session');
  let sessionId = '';
  try {
    sessionId = window.localStorage.getItem(storageKey) || '';
  } catch (_error) {}

  let activeAbortController = null;
  let activeTurn = null;
  const deepThinkClient = window.TEKGDeepThinkClient || {};

  const STAGE_LABELS = {
    idle: 'Ready',
    starting: 'Starting',
    understanding: 'Understanding',
    planning: 'Planning',
    executing: 'Executing',
    collecting: 'Collecting',
    integrating: 'Integrating',
    writing: 'Writing',
    done: 'Done',
    failed: 'Failed',
  };

  function renderMarkdown(text) {
    if (typeof deepThinkClient.renderMarkdown === 'function') {
      return deepThinkClient.renderMarkdown(text);
    }
    return `<p>${String(text || '').replace(/[&<>]/g, '')}</p>`;
  }

  function mergeTurnCitations(turn, citations) {
    if (typeof deepThinkClient.mergeTurnCitations === 'function') {
      deepThinkClient.mergeTurnCitations(turn, citations);
    }
  }

  function enhanceAnswerCitations(turn, answerNode) {
    if (typeof deepThinkClient.enhanceAnswerCitations === 'function') {
      deepThinkClient.enhanceAnswerCitations(turn, answerNode, 'side-dt-inline-citation');
    }
  }
  function getShell() {
    return window.__TEKG_PREVIEW_SHELL || null;
  }

  function getGraphBridge() {
    const shell = getShell();
    if (shell && typeof shell.getGraphBridge === 'function') return shell.getGraphBridge();
    return window.__TEKG_G6_BRIDGE || null;
  }

  function getGraphState() {
    const shell = getShell();
    if (shell && typeof shell.getGraphState === 'function') return shell.getGraphState();
    const bridge = getGraphBridge();
    if (!bridge || typeof bridge.getState !== 'function') return {};
    try {
      return bridge.getState() || {};
    } catch (_error) {
      return {};
    }
  }

  function compactGraphContext() {
    const state = getGraphState();
    const selected = state.selectedNode && typeof state.selectedNode === 'object' ? state.selectedNode : null;
    return {
      mode: String(state.mode || ''),
      query: String(state.query || ''),
      query_type: String(state.queryType || ''),
      selected_node: selected ? {
        id: String(selected.id || selected.data?.id || ''),
        label: String(selected.label || selected.rawLabel || selected.data?.label || selected.data?.rawLabel || ''),
        type: String(selected.type || selected.nodeType || selected.data?.type || selected.data?.nodeType || ''),
      } : null,
      key_node_level: Number(state.keyNodeLevel || 1),
      fixed_view: !!state.fixedView,
      show_labels: !!state.showLabels,
    };
  }

  function setBusy(isBusy) {
    root.classList.toggle('is-busy', isBusy);
    submitBtn.disabled = isBusy;
    input.disabled = isBusy;
  }

  function setStatus(message) {
    statusEl.textContent = message || '';
  }

  function formatElapsed(ms) {
    if (typeof deepThinkClient.formatElapsed === 'function') {
      return deepThinkClient.formatElapsed(ms);
    }
    return `${Math.max(0, ms / 1000).toFixed(1)}s`;
  }

  function updateTurnStatus(turn, final = false) {
    if (!turn) return;
    const stableStage = String(turn.stage || '');
    const label = typeof deepThinkClient.stageDisplayLabel === 'function' && stableStage
      ? deepThinkClient.stageDisplayLabel(stableStage, turn.language)
      : (STAGE_LABELS[turn.stage] || turn.stage || (typeof deepThinkClient.uiText === 'function' ? deepThinkClient.uiText('ready', turn.language) : STAGE_LABELS.idle));
    const elapsed = formatElapsed((performance.now ? performance.now() : Date.now()) - turn.startedAt);
    setStatus(final || turn.stage !== 'idle' ? `${label} \u00b7 ${elapsed}` : label);
  }

  function setTurnStage(turn, stage) {
    if (!turn) return;
    turn.stage = stage;
    updateTurnStatus(turn);
  }

  function startTurnTimer(turn) {
    if (!turn) return;
    stopTurnTimer(turn);
    turn.timerId = window.setInterval(() => updateTurnStatus(turn), 250);
  }

  function stopTurnTimer(turn, stage = '') {
    if (!turn) return;
    if (turn.timerId) {
      window.clearInterval(turn.timerId);
      turn.timerId = null;
    }
    if (stage) {
      turn.stage = stage;
    }
    updateTurnStatus(turn, true);
  }

  function createMessage(kind, htmlOrText, asHtml = false) {
    const node = document.createElement('div');
    node.className = `preview-message preview-message-${kind} side-dt-message side-dt-message-${kind}`;
    if (asHtml) {
      node.innerHTML = htmlOrText || '';
    } else {
      node.textContent = htmlOrText || '';
    }
    messagesEl.appendChild(node);
    messagesEl.scrollTop = messagesEl.scrollHeight;
    return node;
  }

  function createAnswerMessage(turn, markdown) {
    const node = createMessage('answer', renderMarkdown(markdown), true);
    try {
      enhanceAnswerCitations(turn, node);
    } catch (_error) {}
    return node;
  }

  function applyStreamState(turn, event) {
    if (!turn || typeof deepThinkClient.reduceStreamEvent !== 'function') return null;
    turn.streamState = deepThinkClient.reduceStreamEvent(turn.streamState, event);
    if (turn.streamState.progressVisible && !turn.progressNode && typeof deepThinkClient.createProgressMarkup === 'function') {
      turn.progressNode = createMessage('progress', deepThinkClient.createProgressMarkup('deepthink-progress', turn.language), true)
        .querySelector('[data-role="deepthink-progress"]');
    }
    if (turn.progressNode && typeof deepThinkClient.applyProgressState === 'function') {
      deepThinkClient.applyProgressState(turn.progressNode, turn.streamState);
    }
    if (turn.streamState.stage) setTurnStage(turn, turn.streamState.stage.toLowerCase());
    return turn.streamState;
  }

  function parseStreamChunk(chunk) {
    return typeof deepThinkClient.parseStreamChunk === 'function' ? deepThinkClient.parseStreamChunk(chunk) : null;
  }

  async function readEventStream(response, onEvent) {
    if (typeof deepThinkClient.readEventStream === 'function') {
      await deepThinkClient.readEventStream(response, onEvent);
    }
  }

  function normalizeGraphElements(candidate) {
    if (!candidate || typeof candidate !== 'object') return null;
    const nodes = Array.isArray(candidate.nodes) ? candidate.nodes : [];
    const edges = Array.isArray(candidate.edges) ? candidate.edges : [];
    if (!nodes.length || !edges.length) return null;
    return { nodes, edges };
  }

  function graphElementsFromPayload(payload) {
    if (!payload || typeof payload !== 'object') return null;
    const direct = normalizeGraphElements(payload.graph_elements)
      || normalizeGraphElements(payload.raw_result?.graph_elements)
      || normalizeGraphElements(payload.raw_preview?.graph_elements)
      || normalizeGraphElements(payload.display_details?.graph_elements)
      || normalizeGraphElements(payload.display_details?.raw_preview?.graph_elements)
      || normalizeGraphElements(payload.compressed_result?.graph_elements);
    if (direct) return direct;

    const rawRows = payload.raw_result?.rows || payload.raw_preview?.rows || payload.display_details?.raw_preview?.rows;
    if (!Array.isArray(rawRows) || !rawRows.length) return null;
    const nodes = new Map();
    const edges = [];
    rawRows.slice(0, 80).forEach((row, index) => {
      const source = String(row.source_name || row.source || row.te || '').trim();
      const target = String(row.target_name || row.target || row.disease || row.entity || '').trim();
      if (!source || !target) return;
      const relation = String(row.relation_type || row.relation || 'related_to').trim();
      const targetType = String(row.target_label || row.target_type || row.type || 'Disease').trim();
      const sourceId = `deepthink-node-${source.toLowerCase().replace(/[^a-z0-9]+/g, '-') || index}`;
      const targetId = `deepthink-node-${target.toLowerCase().replace(/[^a-z0-9]+/g, '-') || index}`;
      nodes.set(sourceId, { id: sourceId, label: source, type: 'TE', description: '' });
      nodes.set(targetId, { id: targetId, label: target, type: targetType, description: String(row.relation_description || '') });
      edges.push({ id: `deepthink-edge-${index}`, source: sourceId, target: targetId, relation });
    });
    if (!nodes.size || !edges.length) return null;
    return { nodes: Array.from(nodes.values()), edges };
  }

  function graphActionFromElements(elements, fallbackQuery) {
    return {
      graph_action: {
        enabled: true,
        query: fallbackQuery || 'Deep Think result',
        preset_state: {
          key_node_level: 1,
          fixed_view: true,
        },
        subgraph: {
          nodes: elements.nodes.map((node) => ({
            id: String(node.id || node.label || node.displayLabel || ''),
            label: String(node.displayLabel || node.label || node.id || ''),
            type: String(node.nodeType || node.type || 'TE'),
            description: String(node.description || ''),
            pmid: String(node.pmid || ''),
          })),
          edges: elements.edges.map((edge, index) => ({
            id: String(edge.id || `deepthink-edge-${index}`),
            source: String(edge.source || ''),
            target: String(edge.target || ''),
            relation: String(edge.relation || edge.relationType || edge.relation_type || edge.label || 'related_to'),
            evidence: String(edge.evidence || ''),
            pmids: Array.isArray(edge.pmids) ? edge.pmids : [],
          })),
        },
      },
    };
  }

  function normalizeMentionText(value) {
    return String(value || '')
      .normalize('NFKC')
      .toLocaleLowerCase()
      .replace(/[‐‑‒–—―]/g, '-')
      .trim();
  }

  function answerMentionSpans(answer, label) {
    const text = normalizeMentionText(answer);
    const needle = normalizeMentionText(label);
    if (!text || !needle) return [];
    const wordCharacter = /[\p{L}\p{N}_]/u;
    const spans = [];
    let offset = 0;
    while (offset <= text.length - needle.length) {
      const index = text.indexOf(needle, offset);
      if (index < 0) break;
      const before = index > 0 ? text[index - 1] : '';
      const afterIndex = index + needle.length;
      const after = afterIndex < text.length ? text[afterIndex] : '';
      if ((!before || !wordCharacter.test(before)) && (!after || !wordCharacter.test(after))) {
        spans.push({ start: index, end: afterIndex });
      }
      offset = index + Math.max(1, needle.length);
    }
    return spans;
  }

  function collectAnswerGraphEvidence(event, turn) {
    if (!event || event.type !== 'tool_result' || !turn) return;
    const pluginName = String(event.plugin_name || '');
    if (!/(Graph|Cypher|Analytics)/i.test(pluginName)) return;
    const payload = event.payload && typeof event.payload === 'object' ? event.payload : {};
    const elements = graphElementsFromPayload(payload);
    if (!elements) return;

    if (!turn.answerGraphEvidence) {
      turn.answerGraphEvidence = { nodes: new Map(), edges: new Map() };
    }
    elements.nodes.forEach((node) => {
      const id = String(node.id || '').trim();
      if (id) turn.answerGraphEvidence.nodes.set(id, { ...node, id });
    });
    elements.edges.forEach((edge, index) => {
      const source = String(edge.source || '').trim();
      const target = String(edge.target || '').trim();
      if (!source || !target) return;
      const id = String(edge.id || `${source}\u0000${target}\u0000${edge.relation || edge.relationType || index}`);
      turn.answerGraphEvidence.edges.set(id, { ...edge, id, source, target });
    });
    const entityQuery = extractEntityQuery(event);
    if (entityQuery && !turn.answerGraphQuery) turn.answerGraphQuery = entityQuery;
  }

  function buildAnswerGraphElements(turn) {
    const evidence = turn && turn.answerGraphEvidence;
    const answer = String(turn && turn.answer || '');
    if (!evidence || !answer) return null;

    const candidates = Array.from(evidence.nodes.values()).map((node) => {
      const label = String(node.displayLabel || node.label || node.rawLabel || '').trim();
      return {
        node,
        labelLength: normalizeMentionText(label).length,
        spans: label === '' ? [] : answerMentionSpans(answer, label),
      };
    }).filter((candidate) => candidate.spans.length > 0)
      .sort((left, right) => right.labelLength - left.labelLength);

    const occupiedSpans = [];
    const mentionedNodes = [];
    candidates.forEach((candidate) => {
      const hasDistinctMention = candidate.spans.some((span) => !occupiedSpans.some((occupied) => (
        span.start < occupied.end && span.end > occupied.start
      )));
      if (!hasDistinctMention) return;
      mentionedNodes.push(candidate.node);
      occupiedSpans.push(...candidate.spans);
    });
    const mentionedIds = new Set(mentionedNodes.map((node) => String(node.id)));
    const edges = Array.from(evidence.edges.values()).filter((edge) => (
      mentionedIds.has(String(edge.source)) && mentionedIds.has(String(edge.target))
    ));
    if (!edges.length) return null;

    const connectedIds = new Set(edges.flatMap((edge) => [String(edge.source), String(edge.target)]));
    const nodes = mentionedNodes.filter((node) => connectedIds.has(String(node.id)));
    if (nodes.length < 2) return null;
    return { nodes, edges };
  }

  function renderAnswerGraphAction(turn) {
    if (!ANSWER_GRAPH_ACTION_ENABLED) return;
    if (!turn || turn.failed || !turn.answerNode) return;
    const elements = buildAnswerGraphElements(turn);
    if (!elements) return;
    const relationLabel = elements.edges.length === 1 ? 'relation' : 'relations';
    const nodeLabel = elements.nodes.length === 1 ? 'node' : 'nodes';
    const action = document.createElement('div');
    action.className = 'preview-answer-graph-action';
    action.innerHTML = `
      <span class="preview-answer-graph-summary" data-answer-graph-summary>${elements.nodes.length} ${nodeLabel} · ${elements.edges.length} ${relationLabel}</span>
      <button type="button" class="preview-answer-graph-button" data-answer-graph-action="view">View answer graph</button>
      <span class="preview-answer-graph-error" data-answer-graph-error hidden></span>
    `;
    const button = action.querySelector('[data-answer-graph-action="view"]');
    const errorNode = action.querySelector('[data-answer-graph-error]');
    button.addEventListener('click', async () => {
      if (button.disabled) return;
      button.disabled = true;
      errorNode.hidden = true;
      try {
        const workspaceMode = window.__TEKG_PREVIEW_WORKSPACE_MODE;
        if (workspaceMode && typeof workspaceMode.ensureKnowledgeForGraphAction === 'function') {
          await workspaceMode.ensureKnowledgeForGraphAction();
        }
        const bridge = getGraphBridge();
        if (!bridge || typeof bridge.applyAnswerGraph !== 'function') {
          throw new Error('Answer graph is unavailable.');
        }
        const query = turn.answerGraphQuery || compactGraphContext().query || turn.question;
        const applied = await bridge.applyAnswerGraph(graphActionFromElements(elements, query));
        if (!applied) throw new Error('Answer graph could not be opened.');
        button.textContent = 'Answer graph opened';
        turn.graphChanged = true;
      } catch (error) {
        button.disabled = false;
        errorNode.textContent = String(error && error.message ? error.message : 'Answer graph could not be opened.');
        errorNode.hidden = false;
      }
    });
    turn.answerNode.appendChild(action);
    turn.answerGraphProposal = elements;
  }

  function extractEntityQuery(event) {
    const payload = event && event.payload && typeof event.payload === 'object' ? event.payload : {};
    const candidates = [
      payload.compressed_result?.entity,
      payload.compressed_result?.canonical_entity,
      payload.raw_result?.entity,
      payload.raw_result?.canonical_entity,
      payload.display_details?.entity,
      payload.preview_items?.[0]?.label,
      payload.evidence_items?.[0]?.subject,
      payload.evidence_items?.[0]?.entity,
    ];
    for (const candidate of candidates) {
      const value = String(candidate || '').trim();
      if (value) return value;
    }
    return '';
  }

  function handleStreamEvent(turn, event) {
    if (!event || typeof event !== 'object') return;
    const streamState = applyStreamState(turn, event);
    if (event.request_id) turn.requestId = String(event.request_id);
    if (event.session_id) {
      sessionId = String(event.session_id);
      try {
        window.localStorage.setItem(storageKey, sessionId);
      } catch (_error) {}
    }

    if (event.type === 'stage_state') {
      return;
    }
    if (event.type === 'tool_selected' || event.type === 'tool_start') {
      return;
    }
    if (event.type === 'tool_result') {
      const payload = event.payload && typeof event.payload === 'object' ? event.payload : {};
      mergeTurnCitations(turn, payload.citations || payload.display_details?.citations || []);
      turn.toolEvents.push(event);
      if (ANSWER_GRAPH_ACTION_ENABLED) collectAnswerGraphEvidence(event, turn);
      return;
    }
    if (event.type === 'answer') {
      if (!streamState || !streamState.renderAnswer) return;
      turn.answer = streamState.answer;
      turn.answerNode = createAnswerMessage(turn, streamState.answer);
      return;
    }
    if (event.type === 'error') {
      turn.failed = true;
      if (!streamState || streamState.appendError) {
        createMessage('error', typeof deepThinkClient.errorMessage === 'function' ? deepThinkClient.errorMessage(turn.language, event.message) : String(event.message || 'Deep Think failed.'));
      }
      return;
    }
    if (event.type === 'done') {
      turn.done = true;
      const payload = event.payload && typeof event.payload === 'object' ? event.payload : {};
      turn.failed = !!(streamState && streamState.failed);
      if (turn.failed && turn.answerNode) {
        turn.answerNode.remove();
        turn.answerNode = null;
        turn.answer = '';
      }
      if (streamState && streamState.renderAnswer) {
        turn.answer = streamState.answer;
        if (Array.isArray(payload.citations)) {
          mergeTurnCitations(turn, payload.citations);
        }
        turn.answerNode = createAnswerMessage(turn, turn.answer);
      }
      renderAnswerGraphAction(turn);
      stopTurnTimer(turn, turn.failed ? 'failed' : 'done');
    }
  }

  async function submitQuestion(question) {
    if (activeAbortController) {
      activeAbortController.abort();
    }
    const graphContext = compactGraphContext();
    const turn = {
      question,
      answer: '',
      done: false,
      failed: false,
      graphChanged: false,
      answerGraphEvidence: null,
      answerGraphProposal: null,
      answerGraphQuery: '',
      toolEvents: [],
      citations: [],
      streamState: typeof deepThinkClient.createStreamState === 'function' ? deepThinkClient.createStreamState() : null,
      progressNode: null,
      answerNode: null,
      stage: 'starting',
      startedAt: performance.now ? performance.now() : Date.now(),
      timerId: null,
      language: typeof deepThinkClient.detectLanguage === 'function' ? deepThinkClient.detectLanguage(question) : 'en',
    };
    activeTurn = turn;
    activeAbortController = new AbortController();

    createMessage('user', question);
    setBusy(true);
    startTurnTimer(turn);
    updateTurnStatus(turn);

    try {
      const response = await fetch(config.deepThinkStreamApiUrl || window.__TEKG_PATHS.apiUrl('deep_think_stream.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream' },
        body: JSON.stringify({
          question,
          question_raw: question,
          graph_context: graphContext,
          current_url: window.location.href,
          page_context: {
            title: document.title,
            path: window.location.pathname,
          },
          source_page: String(config.sourcePage || 'preview'),
          model: String(config.defaultModel || 'deepseek-v4-flash'),
          mode: 'deepthink',
          session_id: sessionId || undefined,
        }),
        signal: activeAbortController.signal,
      });

      if (!response.ok || !response.body) {
        throw new Error(`Deep Think request failed with HTTP ${response.status}`);
      }

      await readEventStream(response, (event) => handleStreamEvent(turn, event));
      if (!turn.done) {
        throw new Error(typeof deepThinkClient.uiText === 'function' ? deepThinkClient.uiText('stream_ended', turn.language) : 'The Deep Think stream ended before a done event was received.');
      }
      if (!turn.answer && !turn.failed) {
        throw new Error(typeof deepThinkClient.uiText === 'function' ? deepThinkClient.uiText('answer_missing', turn.language) : 'Deep Think completed without returning an answer.');
      }
    } catch (error) {
      const message = error && error.name === 'AbortError'
        ? (typeof deepThinkClient.uiText === 'function' ? deepThinkClient.uiText('cancelled', turn.language) : 'The request was cancelled.')
        : (typeof deepThinkClient.errorMessage === 'function' ? deepThinkClient.errorMessage(turn.language, error && error.message) : (error && error.message ? error.message : 'Unknown Deep Think failure'));
      if (activeTurn === turn && !turn.failed) {
        createMessage('error', message);
        stopTurnTimer(turn, 'failed');
      }
    } finally {
      if (activeTurn === turn) {
        if (turn.timerId) {
          stopTurnTimer(turn, turn.failed ? 'failed' : (turn.done ? 'done' : turn.stage));
        }
        activeTurn = null;
        activeAbortController = null;
      }
      setBusy(false);
      input.focus();
    }
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const question = String(input.value || '').trim();
    if (!question) return;
    submitQuestion(question);
  });

  input.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      form.requestSubmit();
    }
  });

  clearGraphBtn?.addEventListener('click', () => {
    const bridge = getGraphBridge();
    if (!bridge) return;
    if (typeof bridge.goBack === 'function') {
      bridge.goBack();
      setStatus('Ready');
    } else if (typeof bridge.showTree === 'function') {
      bridge.showTree();
      setStatus('Ready');
    }
  });

  setStatus('Ready');
})();
