(function () {
  const form = document.getElementById('pathFinderForm');
  const sourceTypeSelect = document.getElementById('pathSourceType');
  const sourceInput = document.getElementById('pathSource');
  const targetTypeSelect = document.getElementById('pathTargetType');
  const targetInput = document.getElementById('pathTarget');
  const maxDepthSelect = document.getElementById('pathMaxDepth');
  const submitButton = document.getElementById('pathSubmit');
  const statusEl = document.getElementById('pathStatus');
  const resolvedEl = document.getElementById('pathResolved');
  const resultsEl = document.getElementById('pathResults');

  if (!form || !sourceTypeSelect || !sourceInput || !targetTypeSelect || !targetInput || !maxDepthSelect || !submitButton || !statusEl || !resolvedEl || !resultsEl) {
    return;
  }

  const entityExamples = {
    TE: 'L1HS',
    Disease: "Alzheimer's disease",
    Function: 'A-to-I RNA editing',
    Gene: 'TP53',
    Protein: 'ORF1p',
    RNA: 'mRNA',
    Mutation: 'hypomethylation',
    Pharmaceutical: 'azacytidine',
    Toxin: 'oxidative stress',
    Lipid: 'cholesterol',
    Peptide: 'peptide',
    Carbohydrate: 'glucose',
  };

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function pubmedUrl(pmid) {
    return `https://pubmed.ncbi.nlm.nih.gov/${encodeURIComponent(String(pmid || '').trim())}/`;
  }

  function evidenceValue(value) {
    if (value === null || value === undefined || String(value).trim() === '') {
      return '—';
    }
    return String(value).trim();
  }

  function evidenceMetricValue(value) {
    if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) {
      return '—';
    }
    return Number(value).toFixed(1).replace(/\.0$/, '');
  }

  function compactText(value, maxLength) {
    const text = String(value || '').trim();
    if (text.length <= maxLength) {
      return text;
    }
    return `${text.slice(0, Math.max(0, maxLength - 1)).trim()}…`;
  }

  function setLoading(isLoading) {
    submitButton.disabled = !!isLoading;
    sourceTypeSelect.disabled = !!isLoading;
    sourceInput.disabled = !!isLoading;
    targetTypeSelect.disabled = !!isLoading;
    targetInput.disabled = !!isLoading;
    maxDepthSelect.disabled = !!isLoading;
  }

  function updateEntityPlaceholder(select, input) {
    const type = String(select.value || 'entity').trim();
    const example = entityExamples[type] || type;
    input.placeholder = `Select a ${type} entity such as ${example}`;
  }

  function nodeLabel(node) {
    const name = String(node && node.name ? node.name : '').trim();
    return name || 'Unnamed entity';
  }

  function nodeType(node) {
    return String(node && node.type ? node.type : 'Node').trim() || 'Node';
  }

  function renderResolved(payload) {
    const source = payload.source || {};
    const target = payload.target || {};
    const sourceMatches = Array.isArray(source.matches) ? source.matches.length : 0;
    const targetMatches = Array.isArray(target.matches) ? target.matches.length : 0;
    resolvedEl.hidden = false;
    resolvedEl.innerHTML = [
      `<strong>Resolved as</strong>`,
      `<span>${escapeHtml(nodeLabel(source))} <em>${escapeHtml(nodeType(source))}</em></span>`,
      `<span class="path-resolved-arrow">to</span>`,
      `<span>${escapeHtml(nodeLabel(target))} <em>${escapeHtml(nodeType(target))}</em></span>`,
      `<small>${sourceMatches} source candidate${sourceMatches === 1 ? '' : 's'}, ${targetMatches} target candidate${targetMatches === 1 ? '' : 's'}</small>`,
    ].join('');
  }

  function renderCompactPath(path) {
    const nodes = Array.isArray(path.nodes) ? path.nodes : [];
    if (Number(path.hop_count || 0) <= 1) {
      return '';
    }
    const parts = [];
    nodes.forEach((node, index) => {
      if (index > 0) {
        const edge = Array.isArray(path.edges) ? path.edges[index - 1] : null;
        parts.push(`<span class="compact-path-edge">${escapeHtml(edge && edge.relation_label ? edge.relation_label : 'related to')}</span>`);
      }
      parts.push(`<span class="compact-path-node"><strong>${escapeHtml(nodeLabel(node))}</strong><small>${escapeHtml(nodeType(node))}</small></span>`);
    });
    return `<div class="compact-path-strip" aria-label="Compact path">${parts.join('')}</div>`;
  }

  function evidenceRecordsForEdge(edge) {
    const records = Array.isArray(edge && edge.evidence_records) ? edge.evidence_records : [];
    if (records.length) {
      return records
        .filter((record) => record && typeof record === 'object')
        .map((record) => ({
          pmid: String(record.pmid || '').trim(),
          pubmed_url: String(record.pubmed_url || '').trim(),
          pubmed_title: record.pubmed_title ?? null,
          pubmed_journal_title: record.pubmed_journal_title ?? null,
          pubmed_publication_year: record.pubmed_publication_year ?? null,
          journal_metric_value: record.journal_metric_value ?? null,
          journal_metric_source: record.journal_metric_source ?? null,
          journal_metric_year: record.journal_metric_year ?? null,
          journal_jcr_quartile: record.journal_jcr_quartile ?? null,
          journal_metric_match_method: record.journal_metric_match_method ?? null,
        }))
        .filter((record) => record.pmid);
    }

    return (Array.isArray(edge && edge.pmids) ? edge.pmids : [])
      .map((pmid) => String(pmid || '').trim())
      .filter(Boolean)
      .map((pmid) => ({
        pmid,
        pubmed_url: pubmedUrl(pmid),
        pubmed_title: null,
        pubmed_journal_title: null,
        pubmed_publication_year: null,
        journal_metric_value: null,
        journal_metric_source: null,
        journal_metric_year: null,
        journal_jcr_quartile: null,
        journal_metric_match_method: null,
      }));
  }

  function renderEvidenceTable(edge) {
    const records = evidenceRecordsForEdge(edge);
    if (!records.length) {
      return '<div class="path-no-pmid">No PMID evidence attached to this edge.</div>';
    }

    const rows = records.map((record) => {
      const title = String(record.pubmed_title || '').trim();
      const journal = String(record.pubmed_journal_title || '').trim();
      const compactTitle = title ? compactText(title, 96) : '—';
      const url = record.pubmed_url || pubmedUrl(record.pmid);
      return [
        '<tr>',
        `<td><a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(record.pmid)}</a></td>`,
        `<td>${escapeHtml(evidenceValue(record.pubmed_publication_year))}</td>`,
        `<td title="${escapeHtml(journal)}"><span class="edge-evidence-cell-compact">${escapeHtml(evidenceValue(record.pubmed_journal_title))}</span></td>`,
        `<td>${escapeHtml(evidenceMetricValue(record.journal_metric_value))}</td>`,
        `<td>${escapeHtml(evidenceValue(record.journal_jcr_quartile))}</td>`,
        `<td>${escapeHtml(evidenceValue(record.journal_metric_match_method))}</td>`,
        `<td class="edge-evidence-title-cell" title="${escapeHtml(title)}"><span class="edge-evidence-cell-compact">${escapeHtml(compactTitle)}</span></td>`,
        '</tr>',
      ].join('');
    }).join('');

    return [
      '<div class="edge-evidence-table-wrap">',
      '<table class="edge-evidence-table">',
      '<thead><tr><th>PMID</th><th>Year</th><th>Journal</th><th>IF</th><th>JCR</th><th>Match</th><th>Title</th></tr></thead>',
      `<tbody>${rows}</tbody>`,
      '</table>',
      '</div>',
    ].join('');
  }

  function renderEvidence(path) {
    const edges = Array.isArray(path.edges) ? path.edges : [];
    return edges.map((edge, index) => {
      const evidence = String(edge.evidence || '').trim();
      return `
        <details class="path-evidence" ${index === 0 ? 'open' : ''}>
          <summary>
            <span>${escapeHtml(edge.relation_label || edge.relation_type || 'Relationship')}</span>
            <small>${Number(edge.pmid_count || 0)} PMID${Number(edge.pmid_count || 0) === 1 ? '' : 's'}</small>
          </summary>
          <div class="path-evidence-body">
            <div class="path-evidence-meta">
              <span>Relation type</span>
              <strong>${escapeHtml(edge.relation_type || '-')}</strong>
            </div>
            ${renderEvidenceTable(edge)}
            ${evidence ? `<p>${escapeHtml(evidence)}</p>` : ''}
          </div>
        </details>
      `;
    }).join('');
  }

  function renderPath(path, index) {
    const nodes = Array.isArray(path.nodes) ? path.nodes : [];
    const start = nodes[0] || {};
    const end = nodes[nodes.length - 1] || {};
    const direct = Number(path.hop_count || 0) <= 1;
    return `
      <article class="path-card ${direct ? 'is-direct' : 'is-multihop'}">
        <header class="path-card-header">
          <div>
            <span class="path-rank">Path ${index + 1}</span>
            <h2>${escapeHtml(nodeLabel(start))} <span>to</span> ${escapeHtml(nodeLabel(end))}</h2>
          </div>
          <div class="path-metrics">
            <span>${Number(path.hop_count || 0)} hop${Number(path.hop_count || 0) === 1 ? '' : 's'}</span>
            <span>${Number(path.pmid_count || 0)} PMID${Number(path.pmid_count || 0) === 1 ? '' : 's'}</span>
          </div>
        </header>
        ${renderCompactPath(path)}
        <div class="path-evidence-list">${renderEvidence(path)}</div>
      </article>
    `;
  }

  function renderResults(payload) {
    renderResolved(payload);
    const paths = Array.isArray(payload.paths) ? payload.paths : [];
    if (!paths.length) {
      statusEl.textContent = `No path was found within ${Number(payload.max_depth || 3)} hops.`;
      resultsEl.innerHTML = '';
      return;
    }
    statusEl.textContent = `${paths.length} path${paths.length === 1 ? '' : 's'} found within ${Number(payload.max_depth || 3)} hops.`;
    resultsEl.innerHTML = paths.map(renderPath).join('');
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const source = sourceInput.value.trim();
    const target = targetInput.value.trim();
    const sourceType = sourceTypeSelect.value || 'TE';
    const targetType = targetTypeSelect.value || 'Disease';
    const maxDepth = maxDepthSelect.value || '3';

    resolvedEl.hidden = true;
    resolvedEl.innerHTML = '';
    resultsEl.innerHTML = '';

    if (!source || !target) {
      statusEl.textContent = 'Both source and target are required.';
      return;
    }

    setLoading(true);
    statusEl.textContent = 'Searching paths...';
    try {
      const params = new URLSearchParams({ source, target, source_type: sourceType, target_type: targetType, max_depth: maxDepth });
      const response = await fetch(window.__TEKG_PATHS.apiUrl(`path_finder.php?${params.toString()}`), { credentials: 'same-origin' });
      const payload = await response.json();
      if (!response.ok || payload.ok !== true) {
        throw new Error(payload.error || `Path Finder API HTTP ${response.status}`);
      }
      renderResults(payload);
    } catch (error) {
      statusEl.textContent = `${error && error.message ? error.message : 'Path Finder failed.'} Check spelling or search the entity first.`;
    } finally {
      setLoading(false);
    }
  });

  updateEntityPlaceholder(sourceTypeSelect, sourceInput);
  updateEntityPlaceholder(targetTypeSelect, targetInput);
  sourceTypeSelect.addEventListener('change', () => updateEntityPlaceholder(sourceTypeSelect, sourceInput));
  targetTypeSelect.addEventListener('change', () => updateEntityPlaceholder(targetTypeSelect, targetInput));
}());
