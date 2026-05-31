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
  const viewToggleEl = document.getElementById('pathViewToggle');
  const tableViewButton = document.getElementById('pathTableView');
  const graphViewButton = document.getElementById('pathGraphView');
  const graphPanelEl = document.getElementById('pathGraphPanel');
  const graphSurfaceEl = document.getElementById('pathGraphSurface');
  const graphDetailEl = document.getElementById('pathGraphDetail');
  const graphShowNamesButton = document.getElementById('pathGraphShowNames');
  const graphShowRelationsButton = document.getElementById('pathGraphShowRelations');
  const graphExportButton = document.getElementById('pathGraphExport');

  if (!form || !sourceTypeSelect || !sourceInput || !targetTypeSelect || !targetInput || !maxDepthSelect || !submitButton || !statusEl || !resolvedEl || !resultsEl) {
    return;
  }

  let currentPayload = null;
  let currentView = 'table';
  let graphRunner = null;
  let graphInitPromise = null;
  let graphShowNames = true;
  let graphShowRelations = true;

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

  function uniqueStringArray(values) {
    const seen = new Set();
    const out = [];
    (Array.isArray(values) ? values : []).forEach((value) => {
      const text = String(value || '').trim();
      if (!text || seen.has(text)) {
        return;
      }
      seen.add(text);
      out.push(text);
    });
    return out;
  }

  function uniqueEvidenceRecords(records) {
    const seen = new Set();
    const out = [];
    (Array.isArray(records) ? records : []).forEach((record) => {
      if (!record || typeof record !== 'object') {
        return;
      }
      const pmid = String(record.pmid || '').trim();
      const key = pmid || JSON.stringify(record);
      if (!key || seen.has(key)) {
        return;
      }
      seen.add(key);
      out.push({ ...record, pmid });
    });
    return out;
  }

  function graphNodeId(node) {
    return String(node && (node.element_id || node.id || node.name) ? (node.element_id || node.id || node.name) : '').trim();
  }

  function graphNodeLabel(node) {
    return String(node && (node.name || node.label || node.rawLabel || node.id) ? (node.name || node.label || node.rawLabel || node.id) : '').trim();
  }

  function relationName(edge) {
    const label = String(edge && (edge.relation_label || edge.relation || edge.predicate) ? (edge.relation_label || edge.relation || edge.predicate) : '').trim();
    if (label && label !== 'BIO_RELATION') {
      return label;
    }
    return 'related to';
  }

  function mergeEvidenceText(existing, next) {
    const values = uniqueStringArray([existing, next].flatMap((value) => String(value || '').split(/\n+/)));
    return values.join('\n');
  }

  function supportStats(pmids, records) {
    const safeRecords = Array.isArray(records) ? records : [];
    const metricValues = [];
    const years = [];
    const quartiles = { Q1: 0, Q2: 0, Q3: 0, Q4: 0 };
    uniqueEvidenceRecords(safeRecords).forEach((record) => {
      const metric = Number(record.journal_metric_value);
      if (Number.isFinite(metric)) {
        metricValues.push(metric);
      }
      const year = Number(record.pubmed_publication_year);
      if (Number.isFinite(year)) {
        years.push(year);
      }
      const quartile = String(record.journal_jcr_quartile || '').trim().toUpperCase();
      if (Object.prototype.hasOwnProperty.call(quartiles, quartile)) {
        quartiles[quartile] += 1;
      }
    });
    metricValues.sort((left, right) => left - right);
    const metricSum = metricValues.reduce((sum, value) => sum + value, 0);
    const median = metricValues.length
      ? metricValues[Math.floor((metricValues.length - 1) / 2)]
      : null;
    return {
      support_pmid_count: pmids.length,
      support_metric_paper_count: metricValues.length,
      support_metric_coverage: pmids.length ? metricValues.length / pmids.length : 0,
      support_if_max: metricValues.length ? Math.max(...metricValues) : null,
      support_if_mean: metricValues.length ? metricSum / metricValues.length : null,
      support_if_median: median,
      support_jcr_q1_count: quartiles.Q1,
      support_jcr_q2_count: quartiles.Q2,
      support_jcr_q3_count: quartiles.Q3,
      support_jcr_q4_count: quartiles.Q4,
      support_journal_count: uniqueStringArray(safeRecords.map((record) => record && record.pubmed_journal_title)).length,
      support_publication_year_min: years.length ? Math.min(...years) : null,
      support_publication_year_max: years.length ? Math.max(...years) : null,
    };
  }

  function buildGraphElements(payload) {
    const paths = Array.isArray(payload && payload.paths) ? payload.paths : [];
    const nodeMap = new Map();
    const edgeMap = new Map();
    const degreeCounts = new Map();

    paths.forEach((path) => {
      (Array.isArray(path.nodes) ? path.nodes : []).forEach((node) => {
        const id = graphNodeId(node);
        if (!id || nodeMap.has(id)) {
          return;
        }
        nodeMap.set(id, {
          id,
          label: graphNodeLabel(node) || id,
          rawLabel: graphNodeLabel(node) || id,
          type: nodeType(node),
          description: String(node && node.description ? node.description : ''),
          pmid: String(node && node.pmid ? node.pmid : ''),
          disease_class: String(node && node.disease_class ? node.disease_class : ''),
        });
      });

      (Array.isArray(path.edges) ? path.edges : []).forEach((edge) => {
        const source = String(edge && edge.source ? edge.source : '').trim();
        const target = String(edge && edge.target ? edge.target : '').trim();
        if (!source || !target) {
          return;
        }
        const relation = relationName(edge);
        const key = [source, target, relation].join('::');
        if (!edgeMap.has(key)) {
          edgeMap.set(key, {
            id: `path_edge_${edgeMap.size + 1}`,
            source,
            target,
            relation,
            relationType: relation,
            raw_relation_type: String(edge && edge.relation_type ? edge.relation_type : ''),
            evidence: '',
            pmids: [],
            evidence_records: [],
          });
        }
        const entry = edgeMap.get(key);
        entry.evidence = mergeEvidenceText(entry.evidence, edge && edge.evidence);
        entry.pmids = uniqueStringArray([...entry.pmids, ...(Array.isArray(edge && edge.pmids) ? edge.pmids : [])]);
        entry.evidence_records = uniqueEvidenceRecords([
          ...entry.evidence_records,
          ...(Array.isArray(edge && edge.evidence_records) ? edge.evidence_records : []),
        ]);
      });
    });

    edgeMap.forEach((edge) => {
      degreeCounts.set(edge.source, (degreeCounts.get(edge.source) || 0) + 1);
      degreeCounts.set(edge.target, (degreeCounts.get(edge.target) || 0) + 1);
    });

    const nodeElements = Array.from(nodeMap.values()).map((node) => ({
      data: {
        ...node,
        degree: Math.max(1, degreeCounts.get(node.id) || 1),
      },
    }));

    const edgeElements = Array.from(edgeMap.values()).map((edge) => {
      const stats = supportStats(edge.pmids, edge.evidence_records);
      return {
        data: {
          ...edge,
          ...stats,
        },
      };
    });

    return [...nodeElements, ...edgeElements];
  }

  function setGraphDetail(title, description) {
    if (!graphDetailEl) {
      return;
    }
    const heading = String(title || '').trim();
    const body = String(description || '').trim();
    graphDetailEl.innerHTML = [
      heading ? `<strong>${escapeHtml(heading)}</strong>` : '',
      body ? `<div>${escapeHtml(body)}</div>` : '',
    ].filter(Boolean).join('');
  }

  function setGraphDetailHtml(html) {
    if (graphDetailEl) {
      graphDetailEl.innerHTML = html || '';
    }
  }

  function setGraphStatus(text) {
    if (graphDetailEl) {
      graphDetailEl.textContent = text;
    }
  }

  function ensureGraphRunner() {
    if (graphRunner) {
      return graphInitPromise || Promise.resolve();
    }
    if (!graphSurfaceEl || !window.__TEKG_G6_SHARED || typeof window.__TEKG_G6_SHARED.createRunner !== 'function') {
      return Promise.reject(new Error('G6 graph renderer is unavailable on this page.'));
    }
    graphRunner = window.__TEKG_G6_SHARED.createRunner({
      container: graphSurfaceEl,
      initialFixedView: true,
      initialShowAllLabels: true,
      initialKeyNodeLevel: 1,
      initialLang: 'en',
      initialAllowNodeActions: false,
      syncRouteState: () => {},
      setStatus: setGraphStatus,
      setDetail: setGraphDetail,
      setDetailHtml: setGraphDetailHtml,
      setMode: () => {},
      setQueryUi: () => {},
      onSelection: () => {},
      onDiseaseClassClick: () => false,
      onNodeExpand: () => false,
      onReady: () => {},
    });
    graphInitPromise = Promise.resolve(graphRunner.init());
    return graphInitPromise;
  }

  function updateGraphDebugBridge() {
    window.__TEKG_PATH_FINDER_GRAPH_DEBUG = {
      getVisibleSubgraph() {
        if (!graphRunner || typeof graphRunner.getVisibleSubgraph !== 'function') {
          return null;
        }
        return graphRunner.getVisibleSubgraph();
      },
      inspectFirstNode() {
        if (!graphRunner || typeof graphRunner.getVisibleSubgraph !== 'function' || typeof graphRunner.inspectNode !== 'function') {
          return false;
        }
        const subgraph = graphRunner.getVisibleSubgraph();
        const firstNode = Array.isArray(subgraph && subgraph.nodes) ? subgraph.nodes[0] : null;
        if (!firstNode || !firstNode.id) {
          return false;
        }
        return graphRunner.inspectNode(firstNode.id);
      },
    };
  }

  async function renderGraphFromCurrentPayload() {
    if (!currentPayload || !Array.isArray(currentPayload.paths) || !currentPayload.paths.length) {
      return;
    }
    if (!graphPanelEl || !graphSurfaceEl) {
      return;
    }
    if (graphExportButton) {
      graphExportButton.disabled = true;
    }
    setGraphStatus('Rendering graph result...');
    const elements = buildGraphElements(currentPayload);
    const source = nodeLabel(currentPayload.source || {});
    const target = nodeLabel(currentPayload.target || {});
    const endpointHighlightIds = uniqueStringArray([
      graphNodeId(currentPayload.source || {}),
      graphNodeId(currentPayload.target || {}),
    ]);
    await ensureGraphRunner();
    await graphRunner.renderElements(elements, { query: `${source} to ${target}` }, {
      skipInitialStatus: true,
      graphDataOptions: {
        showAllLabels: graphShowNames, // 含义：是否显示节点名称。TE-KG default: controlled by Show names, initially false.
        showEdgeLabels: graphShowRelations, // 含义：是否显示边上的关系名称。TE-KG default: controlled by Show relations, initially false.
        allowInspectCard: true, // 含义：是否允许点击节点/边后显示详情卡片。TE-KG default: true.
        allowNodeActions: false, // 含义：是否允许节点卡片里的 Jump/Expand 按钮。TE-KG default: true; Path Finder disables Jump/Expand.
        restrictToAnchorComponent: false, // 含义：过滤后是否只保留与 anchor 连通的组件。TE-KG default: true.
        forceAnchorLabel: true, // 含义：是否强制显示第一个/anchor 节点标签。TE-KG default: false.
        preferFullLabels: true, // 含义：是否优先显示完整节点名，而不是为了塞进圆里而截断。TE-KG default: false.
        endpointHighlightIds, // 含义：需要 source/target ripple 高光的节点 id。TE-KG default: [].
        nodeSizeScale: 1.5, // 含义：所有节点直径的整体倍率。TE-KG default: 1.
        nodeMinSize: 50, // 含义：普通节点最小直径。TE-KG default: 0.
        endpointNodeMinSize: 0, // 含义：搜索起点/终点节点的最小直径。TE-KG default: 0.
        layoutDistanceScale: 3, // 含义：边/连接的理想长度倍率；越大节点越分散。TE-KG default: 1.
        collisionPaddingScale: 8, // 含义：节点碰撞安全距离倍率；越大越不容易重叠。TE-KG default: 1.
        collisionIterations: 52, // 含义：碰撞计算迭代次数；越大越努力分开节点，但更耗时。TE-KG default: 16.
        chargeScale: 4, // 含义：节点之间排斥力倍率；越大整体越向外散开。TE-KG default: 1.
        edgeLabelFontSize: 15, // 含义：边上关系名称的字体大小。TE-KG default: 9.
      },
    });
    updateGraphDebugBridge();
    if (graphExportButton) {
      graphExportButton.disabled = false;
    }
  }

  function updateViewButtons() {
    if (!tableViewButton || !graphViewButton) {
      return;
    }
    const isGraph = currentView === 'graph';
    tableViewButton.classList.toggle('is-active', !isGraph);
    graphViewButton.classList.toggle('is-active', isGraph);
    tableViewButton.setAttribute('aria-pressed', String(!isGraph));
    graphViewButton.setAttribute('aria-pressed', String(isGraph));
  }

  function updateGraphToggleButtons() {
    if (graphShowNamesButton) {
      graphShowNamesButton.classList.toggle('is-on', graphShowNames);
      graphShowNamesButton.setAttribute('aria-pressed', String(graphShowNames));
      graphShowNamesButton.textContent = `Show names: ${graphShowNames ? 'On' : 'Off'}`;
    }
    if (graphShowRelationsButton) {
      graphShowRelationsButton.classList.toggle('is-on', graphShowRelations);
      graphShowRelationsButton.setAttribute('aria-pressed', String(graphShowRelations));
      graphShowRelationsButton.textContent = `Show relations: ${graphShowRelations ? 'On' : 'Off'}`;
    }
  }

  function setResultView(view) {
    currentView = view === 'graph' ? 'graph' : 'table';
    updateViewButtons();
    resultsEl.hidden = currentView === 'graph';
    if (graphPanelEl) {
      graphPanelEl.hidden = currentView !== 'graph';
    }
    if (currentView === 'graph') {
      renderGraphFromCurrentPayload().catch((error) => {
        setGraphStatus(`${error && error.message ? error.message : 'Graph rendering failed.'}`);
        if (graphExportButton) {
          graphExportButton.disabled = true;
        }
      });
    }
  }

  function clearGraphResult() {
    if (graphPanelEl) {
      graphPanelEl.hidden = true;
    }
    if (graphSurfaceEl) {
      graphSurfaceEl.innerHTML = '';
    }
    if (graphDetailEl) {
      graphDetailEl.textContent = 'Switch to Graph after a search to inspect nodes and relationships.';
    }
    if (graphExportButton) {
      graphExportButton.disabled = true;
    }
    if (window.__TEKG_PATH_FINDER_GRAPH_DEBUG) {
      delete window.__TEKG_PATH_FINDER_GRAPH_DEBUG;
    }
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
            <span>${escapeHtml(relationName(edge) || 'Relationship')}</span>
            <small>${Number(edge.pmid_count || 0)} PMID${Number(edge.pmid_count || 0) === 1 ? '' : 's'}</small>
          </summary>
          <div class="path-evidence-body">
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
    currentPayload = payload;
    renderResolved(payload);
    const paths = Array.isArray(payload.paths) ? payload.paths : [];
    if (!paths.length) {
      statusEl.textContent = `No path was found within ${Number(payload.max_depth || 3)} hops.`;
      resultsEl.innerHTML = '';
      if (viewToggleEl) {
        viewToggleEl.hidden = true;
      }
      resultsEl.hidden = false;
      clearGraphResult();
      return;
    }
    statusEl.textContent = `${paths.length} path${paths.length === 1 ? '' : 's'} found within ${Number(payload.max_depth || 3)} hops.`;
    resultsEl.innerHTML = paths.map(renderPath).join('');
    if (viewToggleEl) {
      viewToggleEl.hidden = false;
    }
    setResultView(currentView);
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
    resultsEl.hidden = false;
    currentPayload = null;
    if (viewToggleEl) {
      viewToggleEl.hidden = true;
    }
    clearGraphResult();

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

  if (tableViewButton) {
    tableViewButton.addEventListener('click', () => setResultView('table'));
  }
  if (graphViewButton) {
    graphViewButton.addEventListener('click', () => setResultView('graph'));
  }
  if (graphShowNamesButton) {
    graphShowNamesButton.addEventListener('click', () => {
      graphShowNames = !graphShowNames;
      updateGraphToggleButtons();
      if (currentView === 'graph') {
        renderGraphFromCurrentPayload().catch((error) => setGraphStatus(error && error.message ? error.message : 'Graph rendering failed.'));
      }
    });
  }
  if (graphShowRelationsButton) {
    graphShowRelationsButton.addEventListener('click', () => {
      graphShowRelations = !graphShowRelations;
      updateGraphToggleButtons();
      if (currentView === 'graph') {
        renderGraphFromCurrentPayload().catch((error) => setGraphStatus(error && error.message ? error.message : 'Graph rendering failed.'));
      }
    });
  }
  if (graphExportButton) {
    graphExportButton.addEventListener('click', async () => {
      if (!graphRunner || typeof graphRunner.exportPngDataUrl !== 'function') {
        setGraphStatus('Graph export is not available yet.');
        return;
      }
      graphExportButton.disabled = true;
      try {
        const dataUrl = await graphRunner.exportPngDataUrl();
        const link = document.createElement('a');
        link.href = dataUrl;
        link.download = 'path-finder-graph.png';
        document.body.appendChild(link);
        link.click();
        link.remove();
      } catch (error) {
        setGraphStatus(error && error.message ? error.message : 'Graph export failed.');
      } finally {
        graphExportButton.disabled = false;
      }
    });
  }

  updateGraphToggleButtons();
  updateViewButtons();
  updateEntityPlaceholder(sourceTypeSelect, sourceInput);
  updateEntityPlaceholder(targetTypeSelect, targetInput);
  sourceTypeSelect.addEventListener('change', () => updateEntityPlaceholder(sourceTypeSelect, sourceInput));
  targetTypeSelect.addEventListener('change', () => updateEntityPlaceholder(targetTypeSelect, targetInput));
}());
