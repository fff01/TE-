(function () {
  if (window.__TEKG_RENDERER_MODE !== 'g6') return;

  const G6Lib = window.G6;
  if (!G6Lib) return;

  const {
    Graph,
    register,
    ExtensionCategory,
    Rect,
    CircleCombo,
    Badge,
    ConcentricLayout,
    ForceLayout,
  } = G6Lib;
  if (!Graph || !register || !ExtensionCategory || !Rect || !CircleCombo || !Badge || !ConcentricLayout || !ForceLayout) return;

  const TYPE_ORDER = ['TE', 'Disease', 'Function'];
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
  const NODE_STROKE = {
    TE: '#2f63d8',
    Disease: '#d65b6f',
    Function: '#2f9d6b',
    Paper: '#d08c23',
  };

  let g6DynamicGraph = null;
  let isBound = false;
  let registered = false;
  let lastPayload = null;

  const LAYOUT_TUNING = {
    comboPadding: 108,
    spacing: 420,
    nodeSize: 72,
    minNodeSpacing: 42,
    clusterGravity: 0.03,
    clusterLinkDistance: 320,
    clusterNodeStrength: -1600,
    clusterCollideStrength: 1,
  };
  const FOCUS_TUNING = {
    duration: 220,
    zoomDuration: 180,
    localZoom: 1.04,
  };

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

  function wrapNodeLabel(text, maxCharsPerLine = 22) {
    const raw = String(text || '').trim();
    if (!raw) return '';
    const words = raw.split(/\s+/);
    const lines = [];
    let current = '';
    for (const word of words) {
      const candidate = current ? `${current} ${word}` : word;
      if (candidate.length <= maxCharsPerLine || !current) current = candidate;
      else {
        lines.push(current);
        current = word;
      }
    }
    if (current) lines.push(current);
    return lines.join('\n');
  }

  function buildGraphData(elements, options = {}) {
    const includePapers = !!options.includePapers;
    const rawNodes = [];
    const rawEdges = [];

    for (const item of elements || []) {
      const data = item && item.data ? item.data : null;
      if (!data) continue;
      if (data.source && data.target) rawEdges.push(data);
      else rawNodes.push(data);
    }

    const counts = { TE: 0, Disease: 0, Function: 0, Paper: 0 };
    const filteredNodes = rawNodes.filter((node) => {
      const type = TYPE_LABEL[node.type] ? node.type : 'TE';
      return includePapers || type !== 'Paper';
    });
    filteredNodes.forEach((node) => {
      const type = TYPE_LABEL[node.type] ? node.type : 'TE';
      counts[type] = (counts[type] || 0) + 1;
    });
    const nodes = filteredNodes.map((node) => {
      const type = TYPE_LABEL[node.type] ? node.type : 'TE';
      const comboId = counts[type] > 1 ? `combo-${type}` : undefined;
      return {
        id: node.id,
        ...(comboId ? { combo: comboId } : {}),
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
          size: [Math.max(260, String(getNameSafe(node.label, type, node.description, node.pmid)).length * 20 + 72), 148],
        },
      };
    });

    const nodeMap = new Map(nodes.map((node) => [node.id, node]));
    const comboTypes = TYPE_ORDER.concat(includePapers ? ['Paper'] : []);
    const combos = comboTypes
      .filter((type) => counts[type] > 1)
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

  class TEKGDynamicNode extends Rect {
    render(attributes = this.parsedAttributes, container) {
      const withoutBuiltInLabel = { ...attributes, labelText: '' };
      super.render(withoutBuiltInLabel, container);
      this.drawOutlinedLabel(attributes, container);
    }

    drawOutlinedLabel(attributes, container) {
      const [width = 260, height = 148] = Array.isArray(attributes.size) ? attributes.size : [260, 148];
      const text = wrapNodeLabel(attributes.labelText || '', Math.max(16, Math.floor(width / 10)));
      const x = 0;
      const y = 0;
      const fontSize = attributes.labelFontSize || 54;
      const fontWeight = attributes.labelFontWeight || 700;

      this.upsert(
        'label-outline',
        'text',
        {
          x,
          y,
          text,
          fontSize,
          fontWeight,
          fill: '#111827',
          stroke: '#ffffff',
          lineWidth: 14,
          lineJoin: 'round',
          lineCap: 'round',
          textAlign: 'center',
          textBaseline: 'middle',
          wordWrap: true,
          wordWrapWidth: width - 32,
          pointerEvents: 'none',
        },
        container,
      );
    }
  }

  function ensureRegistered() {
    if (registered) return;
    register(ExtensionCategory.NODE, 'tekg-dynamic-node', TEKGDynamicNode);
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
        duration: FOCUS_TUNING.duration,
        easing: 'ease-in-out',
      });
      if (typeof focusLevel !== 'undefined' && focusLevel === 100 && typeof g6DynamicGraph.getZoom === 'function' && typeof g6DynamicGraph.zoomTo === 'function') {
        const currentZoom = g6DynamicGraph.getZoom();
        await g6DynamicGraph.zoomTo(Math.max(currentZoom, FOCUS_TUNING.localZoom), {
          duration: FOCUS_TUNING.zoomDuration,
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

      const data = buildGraphData(elements || [], {
        includePapers: !!payload?.__fromQa,
      });
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
          preventOverlap: true,
          comboPadding: LAYOUT_TUNING.comboPadding,
          spacing: LAYOUT_TUNING.spacing,
          nodeSize: LAYOUT_TUNING.nodeSize,
          innerLayout: new ConcentricLayout({
            preventOverlap: true,
            minNodeSpacing: LAYOUT_TUNING.minNodeSpacing,
          }),
          outerLayout: new ForceLayout({
            preventOverlap: true,
            gravity: LAYOUT_TUNING.clusterGravity,
            linkDistance: LAYOUT_TUNING.clusterLinkDistance,
            nodeStrength: LAYOUT_TUNING.clusterNodeStrength,
            collideStrength: LAYOUT_TUNING.clusterCollideStrength,
          }),
        },
        combo: {
          type: 'circle-combo-with-extra-button',
          animation: {
            collapse: false,
            expand: false,
          },
          style: {
            lineWidth: 1.4,
            stroke: (datum) => COMBO_STROKE[datum.data?.type] || '#d8e4f0',
            fill: (datum) => COMBO_BG[datum.data?.type] || '#f8fbff',
            fillOpacity: 0.18,
            shadowColor: (datum) => COMBO_STROKE[datum.data?.type] || '#d8e4f0',
            shadowBlur: 22,
            shadowOffsetX: 0,
            shadowOffsetY: 18,
            shadowType: 'outer',
            increasedLineWidthForHitTesting: 28,
            collapsedSize: 460,
            collapsedLineWidth: 5.2,
            collapsedFill: (datum) => {
              const type = datum.data?.type;
              const map = {
                TE: '#3b6fdf',
                Disease: '#d86a7a',
                Function: '#3da978',
                Paper: '#d39a34',
              };
              return map[type] || '#64748b';
            },
            collapsedFillOpacity: 1,
            collapsedStroke: (datum) => {
              const type = datum.data?.type;
              const map = {
                TE: '#1d4ed8',
                Disease: '#b84e60',
                Function: '#21885c',
                Paper: '#b57f1d',
              };
              return map[type] || '#334155';
            },
            collapsedStrokeOpacity: 1,
            collapsedShadowColor: (datum) => {
              const type = datum.data?.type;
              const map = {
                TE: 'rgba(29, 78, 216, 0.45)',
                Disease: 'rgba(184, 78, 96, 0.45)',
                Function: 'rgba(33, 136, 92, 0.45)',
                Paper: 'rgba(181, 127, 29, 0.45)',
              };
              return map[type] || 'rgba(51, 65, 85, 0.45)';
            },
            collapsedShadowBlur: 36,
            collapsedShadowOffsetX: 0,
            collapsedShadowOffsetY: 28,
            collapsedShadowType: 'outer',
            collapsedIncreasedLineWidthForHitTesting: 56,
            collapsedMarker: false,
            labelText: (datum) => datum.data?.label || datum.id,
            labelFill: '#1f2b46',
            labelFontWeight: 700,
            labelFontSize: 54,
            collapsedLabelFill: '#ffffff',
            collapsedLabelFontWeight: 700,
            collapsedLabelFontSize: 62,
            labelPlacement: 'top',
            labelMaxWidth: 140,
            collapsed: false,
          },
          state: {
            selected: {
              stroke: (datum) => (datum.style?.collapsed
                ? (({
                  TE: '#1d4ed8',
                  Disease: '#b84e60',
                  Function: '#21885c',
                  Paper: '#b57f1d',
                })[datum.data?.type] || '#334155')
                : '#2563eb'),
              lineWidth: (datum) => (datum.style?.collapsed ? 5.2 : 2),
            },
            active: {
              stroke: (datum) => (datum.style?.collapsed
                ? (({
                  TE: '#1d4ed8',
                  Disease: '#b84e60',
                  Function: '#21885c',
                  Paper: '#b57f1d',
                })[datum.data?.type] || '#334155')
                : '#2563eb'),
              lineWidth: (datum) => (datum.style?.collapsed ? 5.2 : 2),
            },
          },
        },
        node: {
          type: 'tekg-dynamic-node',
          style: {
            size: (datum) => datum.style?.size || [260, 148],
            radius: 30,
            fill: (datum) => TYPE_COLORS[datum.data?.type] || '#94a3b8',
            stroke: (datum) => NODE_STROKE[datum.data?.type] || TYPE_COLORS[datum.data?.type] || '#94a3b8',
            lineWidth: 1.2,
            shadowColor: 'rgba(15, 23, 42, 0.08)',
            shadowBlur: 10,
            labelText: (datum) => datum.data?.label || datum.id,
            labelFill: '#1f2b46',
            labelPlacement: 'center',
            labelTextAlign: 'center',
            labelTextBaseline: 'middle',
            labelFontSize: 54,
            labelFontWeight: 700,
            labelBackground: false,
          },
          state: {
            selected: {
              halo: true,
              haloFill: 'rgba(37, 99, 235, 0.12)',
              lineWidth: 2.4,
              stroke: '#2563eb',
              labelFill: '#1f2b46',
              labelFontSize: 54,
            },
            active: {
              lineWidth: 2.2,
              stroke: '#2563eb',
              labelFill: '#1f2b46',
              labelFontSize: 54,
            },
          },
        },
        edge: {
          type: 'quadratic',
          style: {
            stroke: '#9fb2d8',
            lineWidth: 1.5,
            endArrow: false,
            labelText: (datum) => datum.data?.relation || '',
            labelFill: '#475569',
            labelFontSize: 24,
            labelFontWeight: 600,
            labelPlacement: 'center',
            labelBackground: true,
            labelBackgroundFill: 'rgba(255,255,255,0.92)',
            labelBackgroundRadius: 8,
            labelPadding: [4, 10],
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
          },
        },
        behaviors: [
          'drag-canvas',
          'drag-element',
          { type: 'zoom-canvas', sensitivity: 1.14 },
          {
            type: 'click-select',
            degree: 1,
            state: 'selected',
            neighborState: 'active',
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
        const fixedModeEnabled = typeof fixedView !== 'undefined' && fixedView === true;
        const query = datum?.pmid || datum?.rawLabel || datum?.label || id;
        const homePreviewMode = window.__TEKG_EMBED_MODE === 'home-preview';
        if (!fixedModeEnabled && !homePreviewMode && typeof window.__TEKG_LOAD_DYNAMIC_GRAPH === 'function') {
          try {
            await window.__TEKG_LOAD_DYNAMIC_GRAPH(query);
            return;
          } catch (_error) {
            await focusTarget(id);
            return;
          }
        }
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
