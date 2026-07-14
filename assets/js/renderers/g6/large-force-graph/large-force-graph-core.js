(function () {
  if (window.__TEKG_RENDERER_MODE && window.__TEKG_RENDERER_MODE !== 'g6') return;

  const contract = window.__TEKG_LARGE_FORCE_GRAPH_CONTRACT;
  const style = window.__TEKG_LARGE_FORCE_GRAPH_STYLES;
  const layout = window.__TEKG_LARGE_FORCE_GRAPH_LAYOUT;
  const G6Lib = window.G6;
  if (!contract || !style || !layout || !G6Lib || typeof G6Lib.Graph !== 'function') return;

  const { Graph, NodeEvent } = G6Lib;

  function resolveEventNodeId(event) {
    const directId = event?.target?.id;
    if (directId && typeof directId === 'string') return directId;
    const candidateIds = [
      event?.target?.config?.id,
      event?.target?.context?.element?.id,
      event?.target?.attributes?.id,
      event?.target?.style?.id,
    ];
    for (const value of candidateIds) {
      if (value && typeof value === 'string') return value;
    }
    return '';
  }

  function toG6Data(data) {
    return {
      nodes: data.nodes.map((node) => ({
        id: node.id,
        label: node.label,
        level: node.level,
        legendKeys: node.legendKeys,
        pinnedLabel: node.pinnedLabel === true,
        displayLabel: node.displayLabel || node.label,
        style: {
          x: Number(node.x) || 0,
          y: Number(node.y) || 0,
          clusterX: Number(node.clusterX ?? node.x) || 0,
          clusterY: Number(node.clusterY ?? node.y) || 0,
          size: Number(node.size) || 16,
          fill: node.color || '#bfdbfe',
          stroke: node.stroke || '#1d4ed8',
          lineDash: Array.isArray(node.lineDash) ? node.lineDash : [],
        },
        data: {
          ...(node.payload || {}),
          id: node.id,
          rawLabel: node.rawLabel || node.label,
          displayLabel: node.displayLabel || node.label,
          queryLabel: node.payload?.queryLabel || node.queryLabel || '',
          description: node.description || node.payload?.description || '',
          treeDepth: node.level,
          taxonomyLevelKey: node.legendKeys?.[0] || '',
          taxonomyLevelLabel: node.payload?.taxonomyLevelLabel || '',
          taxonomyOnly: node.payload?.taxonomyOnly === true,
          hasGraphEntity: node.payload?.hasGraphEntity === true,
          isRoot: node.payload?.isRoot === true || node.level === 0,
          pinnedLabel: node.pinnedLabel === true,
          legendKeys: node.legendKeys || [],
        },
      })),
      edges: data.edges.map((edge) => ({
        id: edge.id,
        source: edge.source,
        target: edge.target,
        kind: edge.kind,
        data: {
          ...(edge.payload || {}),
          relation: edge.label || edge.kind || '',
          kind: edge.kind,
          legendKeys: edge.legendKeys || [],
        },
      })),
    };
  }

  function createRenderer(options = {}) {
    let graph = null;
    let data = contract.normalizeGraphData(options.data || {});
    let nodeById = new Map(data.nodes.map((node) => [String(node.id), node]));
    const callbacks = options.callbacks && typeof options.callbacks === 'object' ? options.callbacks : {};
    const state = {
      legendState: { ...(data.legend?.state || {}) },
      legendFocus: options.legendFocus || null,
      hoverNodeId: null,
      selectedNodeId: null,
      dragging: false,
      nodeById,
    };

    function redraw() {
      if (!graph || typeof graph.draw !== 'function') return Promise.resolve(false);
      return Promise.resolve(graph.draw()).then(() => true);
    }

    function destroy() {
      if (graph && typeof graph.destroy === 'function') graph.destroy();
      graph = null;
    }

    function getNodeData(nodeId) {
      if (!graph || typeof graph.getNodeData !== 'function') return null;
      return graph.getNodeData(nodeId);
    }

    async function render(nextData = data) {
      data = contract.normalizeGraphData(nextData || {});
      nodeById = new Map(data.nodes.map((node) => [String(node.id), node]));
      state.nodeById = nodeById;
      state.legendState = { ...(data.legend?.state || state.legendState || {}) };
      if (typeof callbacks.onDroppedData === 'function' && data.report && (data.report.droppedNodes || data.report.droppedEdges)) {
        callbacks.onDroppedData(data.report);
      }

      destroy();
      const container = options.container;
      if (!container) return false;
      const width = Number(options.width || container.clientWidth || container.offsetWidth || 0);
      const height = Number(options.height || container.clientHeight || container.offsetHeight || 0);
      const rootId = String(data.rootId || data.nodes.find((node) => node.level === 0)?.id || '');
      const g6Data = toG6Data(data);

      graph = new Graph({
        container,
        width,
        height,
        autoResize: true,
        autoFit: 'view',
        padding: [80, 80, 80, 80],
        animation: false,
        cursor: 'grab',
        data: g6Data,
        node: {
          type: 'circle',
          style: {
            x: (datum) => datum.style?.x,
            y: (datum) => datum.style?.y,
            size: (datum) => datum.style?.size || 16,
            fill: (datum) => datum.style?.fill || '#bfdbfe',
            stroke: (datum) => datum.style?.stroke || '#1d4ed8',
            lineWidth: (datum) => (datum.id === rootId ? 4 : 1.8),
            lineDash: (datum) => datum.style?.lineDash || [],
            opacity: (datum) => style.nodeOpacity(datum, state),
            fillOpacity: (datum) => style.nodeOpacity(datum, state),
            labelText: (datum) => style.displayLabel(datum, state),
            labelPlacement: 'center',
            labelFill: (datum) => (datum.id === rootId ? '#ffffff' : '#0f172a'),
            labelOpacity: (datum) => style.nodeOpacity(datum, state),
            labelFontSize: style.labelFontSize,
            labelFontWeight: (datum) => (datum.id === rootId ? 800 : 650),
            labelStroke: '#ffffff',
            labelLineWidth: (datum) => (datum.id === rootId ? 0 : 2),
          },
        },
        edge: {
          style: {
            stroke: '#93c5fd',
            lineWidth: (datum) => (state.legendFocus && style.edgeOpacity(datum, state) > 0.5 ? 2 : 1.05),
            opacity: (datum) => style.edgeOpacity(datum, state),
            labelText: '',
          },
        },
        layout: layout.createLayout(data.options?.layoutProfile || 'taxonomy-large', { nodeById }),
        behaviors: [
          { type: 'drag-element-force', trigger: [], enable: (event) => event.targetType === 'node' },
          'drag-canvas',
          { type: 'zoom-canvas', sensitivity: 1 },
          { type: 'click-select', enable: (event) => event.targetType === 'node' },
        ],
      });

      await graph.render();

      graph.on(NodeEvent.DRAG_START, async () => {
        state.dragging = true;
        await redraw();
      });
      graph.on(NodeEvent.DRAG_END, async () => {
        state.dragging = false;
        await redraw();
      });
      graph.on(NodeEvent.POINTER_ENTER, async (event) => {
        const nodeId = resolveEventNodeId(event);
        if (!nodeId) return;
        state.hoverNodeId = nodeId;
        if (typeof callbacks.onNodeHover === 'function') callbacks.onNodeHover(getNodeData(nodeId), { nodeId });
        await redraw();
      });
      graph.on(NodeEvent.POINTER_LEAVE, async (event) => {
        const nodeId = resolveEventNodeId(event);
        if (!nodeId || state.hoverNodeId !== nodeId) return;
        state.hoverNodeId = null;
        if (typeof callbacks.onNodeHover === 'function') callbacks.onNodeHover(null, { nodeId });
        await redraw();
      });
      graph.on('node:click', async (event) => {
        const nodeId = resolveEventNodeId(event);
        if (!nodeId) return;
        state.selectedNodeId = nodeId;
        const nodeData = getNodeData(nodeId);
        if (typeof callbacks.onNodeClick === 'function') await callbacks.onNodeClick(nodeData, { nodeId });
        if (typeof callbacks.onNodeSelect === 'function') callbacks.onNodeSelect(nodeData, { nodeId });
        await redraw();
      });

      if (typeof callbacks.onRenderStats === 'function') {
        callbacks.onRenderStats({ nodes: data.nodes.length, edges: data.edges.length, graphId: data.graphId });
      }
      return true;
    }

    return {
      render,
      destroy,
      redraw,
      getGraph() {
        return graph;
      },
      getData() {
        return data;
      },
      getState() {
        return { ...state };
      },
      setLegendState(nextState = {}) {
        state.legendState = { ...state.legendState, ...(nextState || {}) };
        return redraw();
      },
      setLegendFocus(nextKey = null) {
        state.legendFocus = String(nextKey || '').trim() || null;
        return redraw();
      },
    };
  }

  window.__TEKG_LARGE_FORCE_GRAPH_CORE = {
    createRenderer,
  };
}());
