(() => {
  if (window.TEKGDeepThinkClient) return;

  function escapeHtml(text) {
    return String(text || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function fallbackMarkdown(source) {
    const html = escapeHtml(source)
      .replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+|\/[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>')
      .replace(/\n{2,}/g, '</p><p>')
      .replace(/\n/g, '<br>');
    return `<p>${html}</p>`;
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
    return fallbackMarkdown(source);
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
      next.push({ ...citation, pmid, title, url: normalizeCitationUrl(citation) });
    }
    return next;
  }

  function mergeTurnCitations(turn, citations) {
    if (!turn) return;
    turn.citations = dedupeCitations([...(turn.citations || []), ...(Array.isArray(citations) ? citations : [])]);
  }

  function enhanceAnswerCitations(turn, answerNode, className = 'deepthink-inline-citation') {
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

      const replacements = [];
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
            anchor.className = className;
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
            anchor.className = className;
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
      const fragment = document.createDocumentFragment();
      let lastIndex = 0;
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

  function formatElapsed(ms) {
    return `${Math.max(0, ms / 1000).toFixed(1)}s`;
  }

  window.TEKGDeepThinkClient = {
    escapeHtml,
    renderMarkdown,
    normalizeCitationTitle,
    normalizeCitationUrl,
    dedupeCitations,
    mergeTurnCitations,
    enhanceAnswerCitations,
    parseStreamChunk,
    readEventStream,
    formatElapsed,
  };
})();
