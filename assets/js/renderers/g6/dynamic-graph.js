(function () {
  if (window.__TEKG_RENDERER_MODE !== 'g6') return;

  const G6Lib = window.G6;
  if (!G6Lib) return;

  const { Graph, register, ExtensionCategory, CircleCombo, Badge } = G6Lib;
  if (!Graph || !register || !ExtensionCategory || !CircleCombo || !Badge) return;

  const TYPE_ORDER = ['TE', 'Disease', 'Function', 'Paper'];
  const TYPE_LABEL = {
    TE: 'TE',
    Disease: 'Disease',
    Function: 'Function',
    Paper: 'Paper',
  };
  const TYPE_COLORS = {
    TE: '#4e79ff',
    Disease: '#ff7a7a',
    Function: '#41b883',
    Paper: '#f2a93b',
  };
  const COMBO_BG = {
    TE: '#eef4ff',
    Disease: '#fff1f1',
    Function: '#eefaf5',
    Paper: '#fff7ea',
  };
  const COMBO_STROKE = {
    TE: '#b9ccff',
    Disease: '#ffc8c8',
    Function: '#bfe9d4',
    Paper: '#ffd89b',
  };

  let g6DynamicGraph = null;
  let isBound = false;
  let registered = false;
  let lastPayload = null;

  function getEl(id) {
    return document.getElementById(id);
  }

  function getCurrentLang() {
    return typeof currentLang === 'string' ? currentLang : 'en';
  }

  function getNameSafe(label, type, description, pmid) {
    if (typeof getName === 'function') return getName(label || '', type || 'Unknown', description || '', pmid || '');
    return String(label || '');
  }

  function getDescSafe(label, type, description, pmid) {
    if (typeof getDesc === 'function') return getDesc(label || '', type || 'Unknown', description || '', pmid || '');
    return String(description || '');
  }

  function getRelSafe(relation) {
    if (typeof getRel === 'function') return getRel(relation || '');
    return String(relation || '');
  }

  function setRendererVisibility() {
    const cyHost = getEl('cy');
    const g6Host = getEl('g6-default-tree-surface');
    if (cyHost) cyHost.style.display = 'none';
    if (g6Host) {
      g6Host.classList.remove('hidden');
      g6Host.style.display = 'block';
      g6Host.style.width = '100%';
      g6Host.style.height = '100%';
    }
  }

  function updateDetailFromDatum(datum) {
    const detailEl = getEl('node-details');
    if (!detailEl) return;
    if (!datum) {
      detailEl.textContent = getCurrentLang() === 'zh'
        ? 'G6 动态图已激活。'
        : 'G6 dynamic graph is active.';
      return;
    }

    if (datum.kind === 'combo') {
      detailEl.innerHTML = `<strong>${datum.label}</strong><br>${datum.description}`;
      return;
    }

    if (datum.kind === 'edge') {
      detailEl.innerHTML = `<strong>${datum.sourceLabel}</strong> → ${datum.relation} → <strong>${datum.targetLabel}</strong><br>${datum.evidence}`;
      return;
    }

    const label = getNameSafe(datum.rawLabel || datum.label, datum.type, datum.description, datum.pmid);
    const desc = getDescSafe(datum.rawLabel || datum.label, datum.type, datum.description, datum.pmid);
    const typeName = TYPE_LABEL[datum.type] || datum.type || 'Node';
    const pmidLine = datum.pmid ? ` | PMID: ${datum.pmid}` : '';
    detailEl.innerHTML = `<strong>${label}</strong> (${typeName})<br>${desc}<div class="meta">${datum.rawLabel || datum.label}${pmidLine}</div>`;
  }

  function buildComboDescription(type, nodeCount) {
    const lang = getCurrentLang();
    if (lang === 'zh') {
      const map = {
        TE: `当前分组包含 ${nodeCount} 个转座元件节点。`,
        Disease: `当前分组包含 ${nodeCount} 个疾病节点。`,
        Function: `当前分组包含 ${nodeCount} 个功能或机制节点。`,
        Paper: `当前分组包含 ${nodeCount} 个文献节点。`,
      };
      return map[type] || `当前分组包含 ${nodeCount} 个节点。`;
    }
    const map = {
      TE: `This group contains ${nodeCount} transposable element nodes.`,
      Disease: `This group contains ${nodeCount} disease nodes.`,
      Function: `This group contains ${nodeCount} function or mechanism nodes.`,
      Paper: `This group contains ${nodeCount} paper nodes.`,
    };
    return map[type] || `This group contains ${nodeCount} nodes.`;
  }

  function buildGraphData(elements) {
    const rawNodes = [];
    const rawEdges = [];

    for (const item of elements || []) {
      const data = item && item.data ? item.data : null;
      if (!data) continue;
      if (data.source && data.target) rawEdges.push(data);
      else rawNodes.push(data);
    }

    const counts = { TE: 0, Disease: 0, Function: 0, Paper: 0 };
    const nodes = rawNodes.map((node) => {
      const type = TYPE_LABEL[node.type] ? node.type : 'TE';
      counts[type] = (counts[type] || 0) + 1;
      return {
        id: node.id,
        combo: `combo-${type}`,
        data: {
          kind: 'node',
          rawLabel: node.label,
          label: getNameSafe(node.label, type, node.description, node.pmid),
          type,
          description: node.description || '',
          pmid: node.pmid || '',
          relationCount: 0,
        },
        style: {
          size: [Math.max(90, String(getNameSafe(node.label, type, node.description, node.pmid)).length * 10 + 28), 38],
        },
      };
    });

    const nodeMap = new Map(nodes.map((node) => [node.id, node]));
    const combos = TYPE_ORDER
      .filter((type) => counts[type] > 0)
      .map((type) => ({
        id: `combo-${type}`,
        data: {
          kind: 'combo',
          type,
          label: TYPE_LABEL[type],
          description: buildComboDescription(type, counts[type]),
        },
        style: {
          labelText: TYPE_LABEL[type],
          collapsed: false,
        },
      }));

    const edges = rawEdges
      .filter((edge) => nodeMap.has(edge.source) && nodeMap.has(edge.target))
      .map((edge, index) => {
        const source = nodeMap.get(edge.source);
        const target = nodeMap.get(edge.target);
        source.data.relationCount += 1;
        target.data.relationCount += 1;
        return {
          id: edge.id || `edge-${index}`,
          source: edge.source,
          target: edge.target,
          data: {
            kind: 'edge',
            relation: getRelSafe(edge.relation || edge.relationType || ''),
            sourceLabel: source.data.label,
            targetLabel: target.data.label,
            evidence: edge.evidence || (Array.isArray(edge.pmids) && edge.pmids.length ? `PMID: ${edge.pmids.join(', ')}` : (getCurrentLang() === 'zh' ? '当前未附证据。' : 'No evidence attached.')),
          },
        };
      });

    return { nodes, edges, combos };
  }

  class CircleComboWithExtraButton extends CircleCombo {
    render(attributes, container) {
      super.render(attributes, container);
      this.drawButton(attributes);
    }

    drawButton(attributes) {
      const { collapsed } = attributes;
      const [, height] = this.getKeySize(attributes);
      const y = height / 2 + 8;
      this.upsert(
        'hit-area',
        Badge,
        {
          text: collapsed ? '+' : '-',
          fill: '#3d81f7',
          fontSize: 10,
          textAlign: 'center',
          backgroundFill: '#ffffff',
          backgroundStroke: '#3d81f7',
          backgroundLineWidth: 1.2,
          backgroundWidth: 18,
          backgroundHeight: 18,
          x: 0,
          y,
          cursor: 'pointer',
        },
        this,
      );
    }

    onCreate() {
      this.shapeMap['hit-area'].addEventListener('click', (event) => {
        event.stopPropagation();
        const id = this.id;
        const collapsed = !this.attributes.collapsed;
        const { graph } = this.context;
        if (collapsed) graph.collapseElement(id);
        else graph.expandElement(id);
      });
    }
  }

  function ensureRegistered() {
    if (registered) return;
    register(ExtensionCategory.COMBO, 'circle-combo-with-extra-button', CircleComboWithExtraButton);
    registered = true;
  }

  function destroyGraph() {
    if (g6DynamicGraph && typeof g6DynamicGraph.destroy === 'function') g6DynamicGraph.destroy();
    g6DynamicGraph = null;
  }

  async function focusTarget(id) {
    if (!g6DynamicGraph || !id || typeof g6DynamicGraph.focusElement !== 'function') return;
    const fixedModeEnabled = typeof fixedView !== 'undefined' && fixedView === true;
    if (fixedModeEnabled) return;
    try {
      await g6DynamicGraph.focusElement(id, {
        duration: 220,
        easing: 'ease-in-out',
      });
      if (typeof focusLevel !== 'undefined' && focusLevel === 100 && typeof g6DynamicGraph.getZoom === 'function' && typeof g6DynamicGraph.zoomTo === 'function') {
        const currentZoom = g6DynamicGraph.getZoom();
        await g6DynamicGraph.zoomTo(Math.max(currentZoom, 1.12), {
          duration: 180,
          easing: 'ease-out',
        });
      }
    } catch (error) {
      console.warn('G6 dynamic focus failed:', error);
    }
  }

  async function renderDynamicGraph(elements, focusLabel = '', payload = null) {
    const detailEl = getEl('node-details');
    try {
      ensureRegistered();
      setRendererVisibility();
      currentGraphKind = 'dynamic';
      lastPayload = { elements: JSON.parse(JSON.stringify(elements || [])), focusLabel, payload };
      if (window.__TEKG_G6_DEFAULT_TREE && typeof window.__TEKG_G6_DEFAULT_TREE.destroy === 'function') {
        window.__TEKG_G6_DEFAULT_TREE.destroy();
      }

      const host = getEl('g6-default-tree-surface');
      if (!host) return;

      await new Promise((resolve) => requestAnimationFrame(resolve));
      const width = host.clientWidth || host.offsetWidth;
      const height = host.clientHeight || host.offsetHeight;
      if (!width || !height) {
        if (detailEl) detailEl.textContent = 'G6 dynamic graph container has no size yet.';
        return;
      }

      const data = buildGraphData(elements || []);
      destroyGraph();
      host.innerHTML = '';

      const graph = new Graph({
        container: host,
        width,
        height,
        autoResize: true,
        autoFit: 'view',
        padding: 48,
        animation: false,
        data,
        layout: {
          type: 'combo-combined',
          comboPadding: 16,
        },
        combo: {
          type: 'circle-combo-with-extra-button',
          style: {
            lineWidth: 1.4,
            stroke: (datum) => COMBO_STROKE[datum.data?.type] || '#d8e4f0',
            fill: (datum) => COMBO_BG[datum.data?.type] || '#f8fbff',
            labelText: (datum) => datum.data?.label || datum.id,
            labelFill: '#1f2b46',
            labelFontWeight: 700,
            labelPlacement: 'top',
            labelMaxWidth: 140,
            collapsed: false,
          },
          state: {
            selected: {
              stroke: '#2563eb',
              lineWidth: 2,
            },
            active: {
              stroke: '#2563eb',
              lineWidth: 2,
            },
            inactive: {
              opacity: 0.28,
            },
          },
        },
        node: {
          type: 'rect',
          style: {
            size: (datum) => datum.style?.size || [110, 38],
            radius: 12,
            fill: '#ffffff',
            stroke: (datum) => TYPE_COLORS[datum.data?.type] || '#94a3b8',
            lineWidth: 1.6,
            shadowColor: 'rgba(15, 23, 42, 0.08)',
            shadowBlur: 10,
            labelText: (datum) => datum.data?.label || datum.id,
            labelFill: '#1f2b46',
            labelMaxWidth: 120,
          },
          state: {
            selected: {
              halo: true,
              haloFill: 'rgba(37, 99, 235, 0.12)',
              lineWidth: 2.4,
              stroke: '#2563eb',
            },
            active: {
              lineWidth: 2.2,
              stroke: '#2563eb',
            },
            inactive: {
              opacity: 0.3,
            },
          },
        },
        edge: {
          type: 'quadratic',
          style: {
            stroke: '#9fb2d8',
            lineWidth: 1.5,
            endArrow: false,
            labelText: '',
          },
          state: {
            selected: {
              stroke: '#2563eb',
              lineWidth: 2.2,
            },
            active: {
              stroke: '#2563eb',
              lineWidth: 2,
            },
            inactive: {
              opacity: 0.18,
            },
          },
        },
        behaviors: [
          'drag-canvas',
          { type: 'zoom-canvas', sensitivity: 1.14 },
          {
            type: 'click-select',
            degree: 1,
            state: 'selected',
            neighborState: 'active',
            unselectedState: 'inactive',
          },
          'focus-element',
          {
            type: 'collapse-expand',
            trigger: 'dblclick',
            enable: (event) => event.targetType === 'combo',
          },
        ],
      });

      g6DynamicGraph = graph;
      await graph.render();
      updateDetailFromDatum(null);

      graph.on('node:click', async (event) => {
        const id = event?.target?.id;
        if (!id || typeof graph.getNodeData !== 'function') return;
        const datum = graph.getNodeData(id)?.data || null;
        updateDetailFromDatum(datum);
        await focusTarget(id);
      });

      graph.on('edge:click', (event) => {
        const id = event?.target?.id;
        if (!id || typeof graph.getEdgeData !== 'function') return;
        const datum = graph.getEdgeData(id)?.data || null;
        updateDetailFromDatum(datum);
      });

      graph.on('combo:click', async (event) => {
        const id = event?.target?.id;
        if (!id || typeof graph.getComboData !== 'function') return;
        const datum = graph.getComboData(id)?.data || null;
        updateDetailFromDatum(datum);
        await focusTarget(id);
      });

      const targetNode = focusLabel
        ? data.nodes.find((node) => node.data?.rawLabel === focusLabel || node.data?.label === focusLabel || node.id === focusLabel)
        : null;
      if (targetNode) {
        updateDetailFromDatum(targetNode.data);
        await focusTarget(targetNode.id);
      }
    } catch (error) {
      if (detailEl) {
        detailEl.textContent = `G6 dynamic graph failed: ${error && error.message ? error.message : 'unknown error'}`;
      }
      console.error('G6 dynamic graph failed:', error);
    }
  }

  function rerenderLast() {
    if (!lastPayload) return;
    renderDynamicGraph(lastPayload.elements, lastPayload.focusLabel, lastPayload.payload);
  }

  function bindTriggers() {
    if (isBound) return;
    isBound = true;
    const zhBtn = getEl('lang-zh');
    const enBtn = getEl('lang-en');
    if (zhBtn) zhBtn.addEventListener('click', () => setTimeout(rerenderLast, 0));
    if (enBtn) enBtn.addEventListener('click', () => setTimeout(rerenderLast, 0));
  }

  bindTriggers();
  window.__TEKG_G6_DYNAMIC_GRAPH = {
    render: renderDynamicGraph,
    rerender: rerenderLast,
    destroy: destroyGraph,
  };
}());
