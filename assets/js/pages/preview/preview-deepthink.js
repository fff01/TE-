(() => {
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

  function escapeHtml(text) {
    return String(text || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function renderMarkdown(text) {
    const source = String(text || '')
      .replace(/^\[\^(\d+)\]:\s+.+$/gm, '')
      .trim();
    if (!source) return '';
    if (window.marked && typeof window.marked.parse === 'function') {
      try {
        return window.marked.parse(source);
      } catch (_error) {}
    }
    return `<p>${escapeHtml(source).replace(/\n{2,}/g, '</p><p>').replace(/\n/g, '<br>')}</p>`;
  }

  function normalizeCitationTitle(citation) {
    const title = String(citation && citation.title ? citation.title : '').trim();
    if (title) return title;
    const pmid = String(citation && citation.pmid ? citation.pmid : '').trim();
    return pmid ? `PubMed PMID ${pmid}` : 'Open citation';
  }

  function normalizeCitationUrl(citation) {
    const explicitUrl = String(citation && citation.url ? citation.url : '').trim();
    if (explicitUrl) return explicitUrl;
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

    const markerPattern = /\[(?:\^)?(\d+)\]/g;
    const pmidPattern = /\bPMID[:\s]+(\d{4,9})\b/gi;
    textNodes.forEach((textNode) => {
      if (textNode.parentElement && textNode.parentElement.closest('a')) return;
      const text = textNode.nodeValue || '';
      markerPattern.lastIndex = 0;
      pmidPattern.lastIndex = 0;
      if (!markerPattern.test(text) && !pmidPattern.test(text)) return;

      const fragment = document.createDocumentFragment();
      const replacements = [];
      let lastIndex = 0;

      markerPattern.lastIndex = 0;
      let match;
      while ((match = markerPattern.exec(text)) !== null) {
        const citationIndex = Math.max(0, Number.parseInt(match[1], 10) - 1);
        const citation = turn.citations[citationIndex] || {};
        replacements.push({
          start: match.index,
          end: markerPattern.lastIndex,
          build() {
            const anchor = document.createElement('a');
            anchor.className = 'preview-inline-citation';
            anchor.href = normalizeCitationUrl(citation);
            anchor.target = '_blank';
            anchor.rel = 'noopener noreferrer';
            anchor.textContent = String(citationIndex + 1);
            anchor.setAttribute('aria-label', normalizeCitationTitle(citation));
            anchor.dataset.citationTitle = normalizeCitationTitle(citation);

            const sup = document.createElement('sup');
            sup.appendChild(anchor);
            return sup;
          },
        });
      }

      pmidPattern.lastIndex = 0;
      while ((match = pmidPattern.exec(text)) !== null) {
        const pmid = String(match[1] || '').trim();
        if (!pmid) continue;
        const citation = (turn.citations || []).find((item) => String(item && item.pmid ? item.pmid : '').trim() === pmid) || { pmid };
        replacements.push({
          start: match.index,
          end: pmidPattern.lastIndex,
          build() {
            const anchor = document.createElement('a');
            anchor.className = 'preview-inline-citation';
            anchor.href = normalizeCitationUrl(citation);
            anchor.target = '_blank';
            anchor.rel = 'noopener noreferrer';
            anchor.textContent = `PMID ${pmid}`;
            anchor.setAttribute('aria-label', normalizeCitationTitle(citation));
            anchor.dataset.citationTitle = normalizeCitationTitle(citation);
            return anchor;
          },
        });
      }

      replacements.sort((left, right) => left.start - right.start);
      let cursor = 0;
      for (const replacement of replacements) {
        if (replacement.start < cursor) continue;
        if (replacement.start > lastIndex) {
          fragment.appendChild(document.createTextNode(text.slice(lastIndex, replacement.start)));
        }
        fragment.appendChild(replacement.build());
        lastIndex = replacement.end;
        cursor = replacement.end;
      }

      if (lastIndex < text.length) {
        fragment.appendChild(document.createTextNode(text.slice(lastIndex)));
      }
      textNode.parentNode.replaceChild(fragment, textNode);
    });
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
    const seconds = Math.max(0, ms / 1000);
    return `${seconds.toFixed(1)}s`;
  }

  function updateTurnStatus(turn, final = false) {
    if (!turn) return;
    const label = STAGE_LABELS[turn.stage] || turn.stage || STAGE_LABELS.idle;
    const elapsed = formatElapsed((performance.now ? performance.now() : Date.now()) - turn.startedAt);
    setStatus(final || turn.stage !== 'idle' ? `${label} · ${elapsed}` : label);
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
    node.className = `preview-message preview-message-${kind}`;
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

  function parseStreamChunk(chunk) {
    const lines = String(chunk || '')
      .split(/\r?\n/)
      .map((line) => line.trimEnd())
      .filter(Boolean);
    const dataLines = lines
      .filter((line) => line.startsWith('data:'))
      .map((line) => line.slice(5).trimStart());
    if (!dataLines.length) return null;
    try {
      return JSON.parse(dataLines.join('\n'));
    } catch (_error) {
      return null;
    }
  }

  async function readEventStream(response, onEvent) {
    const reader = response.body.getReader();
    const decoder = new TextDecoder('utf-8');
    let buffer = '';
    while (true) {
      const { value, done } = await reader.read();
      if (done) break;
      buffer += decoder.decode(value, { stream: true });
      let boundaryIndex = buffer.indexOf('\n\n');
      while (boundaryIndex !== -1) {
        const chunk = buffer.slice(0, boundaryIndex);
        buffer = buffer.slice(boundaryIndex + 2);
        const event = parseStreamChunk(chunk);
        if (event) onEvent(event);
        boundaryIndex = buffer.indexOf('\n\n');
      }
    }
    const finalChunk = buffer.trim();
    if (finalChunk) {
      const event = parseStreamChunk(finalChunk);
      if (event) onEvent(event);
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
            relation: String(edge.relation || edge.relationType || edge.label || 'related_to'),
            evidence: String(edge.evidence || ''),
            pmids: Array.isArray(edge.pmids) ? edge.pmids : [],
          })),
        },
      },
    };
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

  async function driveGraphFromEvent(event, turn) {
    if (!event || event.type !== 'tool_result') return;
    const pluginName = String(event.plugin_name || '');
    const graphRelevant = /Graph|Cypher|Analytics|Sequence|Genome/i.test(pluginName);
    if (!graphRelevant) return;

    const bridge = getGraphBridge();
    if (!bridge) return;
    const payload = event.payload && typeof event.payload === 'object' ? event.payload : {};
    const elements = graphElementsFromPayload(payload);
    try {
      if (elements && typeof bridge.applyAnswerGraph === 'function') {
        const query = extractEntityQuery(event) || compactGraphContext().query || turn.question;
        const applied = await bridge.applyAnswerGraph(graphActionFromElements(elements, query));
        if (applied) {
          turn.graphChanged = true;
          return;
        }
      }

      const entityQuery = extractEntityQuery(event);
      if (entityQuery && typeof bridge.loadGraph === 'function') {
        const loaded = await bridge.loadGraph({ query: entityQuery });
        if (loaded) {
          turn.graphChanged = true;
        }
      }
    } catch (_error) {}
  }

  function handleStreamEvent(turn, event) {
    if (!event || typeof event !== 'object') return;
    if (event.request_id) turn.requestId = String(event.request_id);
    if (event.session_id) {
      sessionId = String(event.session_id);
      try {
        window.localStorage.setItem(storageKey, sessionId);
      } catch (_error) {}
    }

    if (event.type === 'analysis') {
      setTurnStage(turn, 'understanding');
      return;
    }
    if (event.type === 'planning' || event.type === 'planning_step') {
      setTurnStage(turn, 'planning');
      return;
    }
    if (event.type === 'tool_selected' || event.type === 'tool_start') {
      setTurnStage(turn, 'executing');
      return;
    }
    if (event.type === 'tool_result') {
      setTurnStage(turn, 'executing');
      const payload = event.payload && typeof event.payload === 'object' ? event.payload : {};
      mergeTurnCitations(turn, payload.citations || payload.display_details?.citations || []);
      turn.toolEvents.push(event);
      driveGraphFromEvent(event, turn);
      return;
    }
    if (event.type === 'reflection' || event.type === 'synthesizing') {
      setTurnStage(turn, event.type === 'synthesizing' ? 'integrating' : 'collecting');
      return;
    }
    if (event.type === 'answer') {
      setTurnStage(turn, 'writing');
      turn.answer = String(event.message || '');
      createAnswerMessage(turn, turn.answer);
      return;
    }
    if (event.type === 'error') {
      turn.failed = true;
      createMessage('error', String(event.message || 'Deep Think failed.'));
      stopTurnTimer(turn, 'failed');
      return;
    }
    if (event.type === 'done') {
      turn.done = true;
      const payload = event.payload && typeof event.payload === 'object' ? event.payload : {};
      if (!turn.answer && payload.answer) {
        turn.answer = String(payload.answer || '');
        if (Array.isArray(payload.citations)) {
          mergeTurnCitations(turn, payload.citations);
        }
        createAnswerMessage(turn, turn.answer);
      }
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
      toolEvents: [],
      citations: [],
      stage: 'starting',
      startedAt: performance.now ? performance.now() : Date.now(),
      timerId: null,
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
        throw new Error('The Deep Think stream ended before a done event was received.');
      }
      if (!turn.answer && !turn.failed) {
        throw new Error('Deep Think completed without returning an answer.');
      }
    } catch (error) {
      const message = error && error.name === 'AbortError'
        ? 'The request was cancelled.'
        : (error && error.message ? error.message : 'Unknown Deep Think failure');
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
