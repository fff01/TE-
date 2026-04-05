(function () {
  if (window.__TEKG_RENDERER_MODE !== 'g6') return;

  const G6Lib = window.G6;
  if (!G6Lib) return;

  const {
    Graph,
    treeToGraphData,
    register,
    ExtensionCategory,
    BaseNode,
    BaseBehavior,
    Badge,
    CanvasEvent,
    CommonEvent,
    NodeEvent,
    Polyline,
    subStyleProps,
  } = G6Lib;

  if (
    typeof Graph !== 'function' ||
    typeof treeToGraphData !== 'function' ||
    typeof register !== 'function' ||
    !ExtensionCategory ||
    !BaseNode ||
    !BaseBehavior ||
    !Badge ||
    !Polyline
  ) {
    return;
  }

  const COLORS = [
    '#5B8FF9',
    '#F6BD16',
    '#5AD8A6',
    '#945FB9',
    '#E86452',
    '#6DC8EC',
    '#FF99C3',
    '#1E9493',
    '#FF9845',
    '#5D7092',
  ];
  const TREE_EVENT = {
    COLLAPSE_EXPAND: 'tekg-collapse-expand',
  };
  const COLLAPSE_THRESHOLD = 6;
  const ROOT_BG = '#576286';
  const ROOT_TEXT = '#ffffff';
  const TEXT_COLOR = '#666666';
  const INDENT = 40;
  const V_GAP = 10;
  const NODE_HEIGHT = 20;
  const INTERACTION_TUNING = {
    zoomSensitivity: 1.18,
    focusDuration: 220,
    focusZoomDuration: 180,
    localFocusZoom: 1.18,
    dragSpeed: 1.45,
  };

  let g6Graph = null;
  let isBound = false;
  let registered = false;
  let rootId = null;
  let selectedNodeId = null;
  let activeTreeConfig = null;

  function getEl(id) {
    return document.getElementById(id);
  }

  function getCurrentLang() {
    return typeof currentLang === 'string' ? currentLang : 'en';
  }

  function escapeHtml(text) {
    return String(text || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function getRootLabel() {
    return getCurrentLang() === 'zh' ? '人类转座子' : 'TE';
  }

  function normalizeTextWidth(text) {
    return Math.max(36, String(text || '').length * 8 + 6);
  }

  function getDisplayLabel(label, description, depth) {
    const raw = String(label || '');
    if (depth === 0) return getRootLabel();
    if (typeof getName === 'function') return getName(raw, 'TE', description || '', '');
    return raw;
  }

  function getDescription(label, description) {
    if (typeof getDesc === 'function') return getDesc(label || '', 'TE', description || '', '');
    return String(description || '');
  }

  function buildDefaultTreeConfig() {
    const defaultDetailHtml = getCurrentLang() === 'zh'
      ? '<strong>尚未选中节点</strong>当前为 G6 树图视图。'
      : 'G6 indented default-tree view is active.';

    return {
      defaultDetailHtml,
      buildLabel(data, nodeId) {
        return getDisplayLabel(data.rawLabel || nodeId, data.description || '', data.treeDepth || 0);
      },
      buildDetailHtml(nodeData) {
        const data = nodeData?.data || {};
        const label = getDisplayLabel(data.rawLabel || nodeData.id, data.description || '', data.treeDepth || 0);
        const desc = getDescription(data.rawLabel || nodeData.id, data.description || '');
        return `<strong>${escapeHtml(label)}</strong> (Transposable Element)<br>${escapeHtml(desc)}`;
      },
      async onNodeClick(nodeData, context) {
        const { fixedModeEnabled, homePreviewMode } = context || {};
        if (fixedModeEnabled || homePreviewMode || typeof window.__TEKG_LOAD_DYNAMIC_GRAPH !== 'function') {
          return false;
        }
        const data = nodeData?.data || {};
        const query = data.queryLabel || data.rawLabel || nodeData?.id;
        if (!query) return false;
        await window.__TEKG_LOAD_DYNAMIC_GRAPH(query);
        return true;
      },
    };
  }

  function getActiveTreeConfig() {
    if (!activeTreeConfig) activeTreeConfig = buildDefaultTreeConfig();
    return activeTreeConfig;
  }

  function resolveTreeLabel(datum) {
    const config = getActiveTreeConfig();
    const data = datum?.data || {};
    if (typeof config.buildLabel === 'function') {
      const label = config.buildLabel(data, datum?.id || '');
      if (label !== undefined && label !== null && label !== '') return String(label);
    }
    return String(data.displayLabel || data.rawLabel || datum?.id || '');
  }

  function resolveTreeLabelFill(datum) {
    const config = getActiveTreeConfig();
    const data = datum?.data || {};
    if (typeof config.buildLabelFill === 'function') {
      const fill = config.buildLabelFill(data, datum?.id || '');
      if (fill) return fill;
    }
    return datum?.id === rootId ? ROOT_TEXT : TEXT_COLOR;
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

  function buildStrictTreeSource() {
    const nodes = new Map();
    const children = new Map();
    const parentOf = new Map();
    let detectedRootId = null;

    for (const item of (window.GRAPH_DEMO_DATA?.elements || [])) {
      const data = item && item.data ? item.data : null;
      if (!data || data.source) continue;
      nodes.set(data.id, {
        id: data.id,
        label: data.label,
        queryLabel: data.query_label,
        description: data.description,
        treeDepth: data.tree_depth || 0,
      });
      if (data.tree_depth === 0 && !detectedRootId) detectedRootId = data.id;
    }

    const getY = (id) => {
      const matched = (window.GRAPH_DEMO_DATA?.elements || []).find((item) => item?.data?.id === id);
      return matched?.position?.y ?? 0;
    };

    const edges = [];
    for (const item of (window.GRAPH_DEMO_DATA?.elements || [])) {
      const data = item && item.data ? item.data : null;
      if (!data || !data.source || !data.target) continue;
      if (!nodes.has(data.source) || !nodes.has(data.target)) continue;
      edges.push({ source: data.source, target: data.target, y: getY(data.target) });
    }

    edges.sort((a, b) => a.y - b.y);
    for (const edge of edges) {
      if (parentOf.has(edge.target)) continue;
      parentOf.set(edge.target, edge.source);
      if (!children.has(edge.source)) children.set(edge.source, []);
      children.get(edge.source).push(edge.target);
    }

    for (const [parentId, childIds] of children.entries()) {
      childIds.sort((a, b) => getY(a) - getY(b));
    }

    return { nodes, children, rootId: detectedRootId };
  }

  function buildTreeNode(nodeId, source) {
    const node = source.nodes.get(nodeId);
    if (!node) return null;
    const childIds = source.children.get(nodeId) || [];
    const depth = node.treeDepth || 0;
    const label = getDisplayLabel(node.label, node.description, depth);
    const children = childIds.map((childId) => buildTreeNode(childId, source)).filter(Boolean);

    return {
      id: node.id,
      data: {
        rawLabel: node.label,
        queryLabel: node.queryLabel,
        description: node.description,
        treeDepth: depth,
      },
      style: {
        collapsed: children.length >= COLLAPSE_THRESHOLD,
        labelText: label,
      },
      children,
    };
  }

  class TEKGIndentedNode extends BaseNode {
    static defaultStyleProps = {
      ports: [
        { key: 'in', placement: 'right-bottom' },
        { key: 'out', placement: 'left-bottom' },
      ],
      showIcon: true,
    };

    constructor(options) {
      Object.assign(options.style, TEKGIndentedNode.defaultStyleProps);
      super(options);
    }

    get childrenData() {
      return this.context.model.getChildrenData(this.id) || [];
    }

    getKeyStyle(attributes) {
      const [width, height] = this.getSize(attributes);
      const keyStyle = super.getKeyStyle(attributes);
      return {
        width,
        height,
        ...keyStyle,
        fill: 'transparent',
        stroke: 'transparent',
      };
    }

    drawKeyShape(attributes, container) {
      return this.upsert('key', 'rect', this.getKeyStyle(attributes), container);
    }

    getLabelStyle(attributes) {
      if (attributes.label === false || !attributes.labelText) return false;
      return subStyleProps(this.getGraphicStyle(attributes), 'label');
    }

    drawIconArea(attributes, container) {
      const [, h] = this.getSize(attributes);
      this.upsert(
        'icon-area',
        'rect',
        {
          fill: 'transparent',
          height: 30,
          width: 12,
          x: -6,
          y: h,
          zIndex: -1,
        },
        container,
      );
    }

    forwardEvent(target, type, listener) {
      if (target && !Reflect.has(target, '__bind__')) {
        Reflect.set(target, '__bind__', true);
        target.addEventListener(type, listener);
      }
    }

    getCountStyle(attributes) {
      const { collapsed, color } = attributes;
      if (!collapsed) return false;
      const [, height] = this.getSize(attributes);
      return {
        backgroundFill: color,
        cursor: 'pointer',
        fill: '#fff',
        fontSize: 8,
        padding: [0, 10],
        text: '+',
        textAlign: 'center',
        y: height + 8,
      };
    }

    drawCountShape(attributes, container) {
      const countStyle = this.getCountStyle(attributes);
      const btn = this.upsert('count', Badge, countStyle, container);
      this.forwardEvent(btn, CommonEvent.CLICK, (event) => {
        event.stopPropagation();
        this.context.graph.emit(TREE_EVENT.COLLAPSE_EXPAND, { id: this.id, collapsed: false });
      });
    }

    isShowCollapse(attributes) {
      return !attributes.collapsed && this.childrenData.length > 0;
    }

    getCollapseStyle(attributes) {
      const { color } = attributes;
      if (!this.isShowCollapse(attributes)) return false;
      const [, height] = this.getSize(attributes);
      return {
        visibility: 'visible',
        backgroundFill: color,
        backgroundHeight: 12,
        backgroundWidth: 12,
        cursor: 'pointer',
        fill: '#fff',
        fontSize: 10,
        text: '-',
        textAlign: 'center',
        x: -1,
        y: height + 8,
      };
    }

    drawCollapseShape(attributes, container) {
      const iconStyle = this.getCollapseStyle(attributes);
      const btn = this.upsert('collapse-expand', Badge, iconStyle, container);
      this.forwardEvent(btn, CommonEvent.CLICK, (event) => {
        event.stopPropagation();
        this.context.graph.emit(TREE_EVENT.COLLAPSE_EXPAND, {
          id: this.id,
          collapsed: !attributes.collapsed,
        });
      });
    }

    render(attributes = this.parsedAttributes, container = this) {
      super.render(attributes, container);
      this.drawCountShape(attributes, container);
      this.drawIconArea(attributes, container);
      this.drawCollapseShape(attributes, container);
    }
  }

  class TEKGIndentedEdge extends Polyline {
    getControlPoints(attributes) {
      const [sourcePoint, targetPoint] = this.getEndpoints(attributes, false);
      const [sx] = sourcePoint;
      const [, ty] = targetPoint;
      return [[sx, ty]];
    }
  }

  class TEKGCollapseExpandTree extends BaseBehavior {
    constructor(context, options) {
      super(context, options);
      this.bindEvents();
    }

    update(options) {
      this.unbindEvents();
      super.update(options);
      this.bindEvents();
    }

    bindEvents() {
      const { graph } = this.context;
      graph.on(TREE_EVENT.COLLAPSE_EXPAND, this.onCollapseExpand);
    }

    unbindEvents() {
      const { graph } = this.context;
      graph.off(TREE_EVENT.COLLAPSE_EXPAND, this.onCollapseExpand);
    }

    status = 'idle';

    onCollapseExpand = async (event) => {
      this.status = 'busy';
      const { id, collapsed } = event;
      const { graph } = this.context;
      if (collapsed) await graph.collapseElement(id, { animation: false });
      else await graph.expandElement(id, { animation: false });
      await activateNode(id, { shouldFocus: true });
      this.status = 'idle';
    };
  }

  class TEKGDragCanvasFast extends BaseBehavior {
    constructor(context, options) {
      super(context, options);
      this.bindEvents();
    }

    update(options) {
      this.unbindEvents();
      super.update(options);
      this.bindEvents();
    }

    dragging = false;

    lastPoint = null;

    bindEvents() {
      const { graph } = this.context;
      graph.on(CanvasEvent.POINTER_DOWN, this.onPointerDown);
      graph.on(CommonEvent.POINTER_MOVE, this.onPointerMove);
      graph.on(CommonEvent.POINTER_UP, this.onPointerUp);
      graph.on(CommonEvent.POINTER_UP_OUTSIDE, this.onPointerUp);
      graph.on(CommonEvent.DRAG_END, this.onPointerUp);
    }

    unbindEvents() {
      const { graph } = this.context;
      graph.off(CanvasEvent.POINTER_DOWN, this.onPointerDown);
      graph.off(CommonEvent.POINTER_MOVE, this.onPointerMove);
      graph.off(CommonEvent.POINTER_UP, this.onPointerUp);
      graph.off(CommonEvent.POINTER_UP_OUTSIDE, this.onPointerUp);
      graph.off(CommonEvent.DRAG_END, this.onPointerUp);
    }

    getClientPoint(event) {
      const point = event?.client || event?.nativeEvent || {};
      const x = Number(point.x ?? point.clientX);
      const y = Number(point.y ?? point.clientY);
      if (!Number.isFinite(x) || !Number.isFinite(y)) return null;
      return { x, y };
    }

    onPointerDown = (event) => {
      if (event?.targetType && event.targetType !== 'canvas') return;
      this.dragging = true;
      this.lastPoint = this.getClientPoint(event);
      const { graph } = this.context;
      if (typeof graph.setCursor === 'function') graph.setCursor('grabbing');
    };

    onPointerMove = (event) => {
      if (!this.dragging) return;
      const currentPoint = this.getClientPoint(event);
      if (!currentPoint || !this.lastPoint) return;
      const { graph } = this.context;
      const dx = (currentPoint.x - this.lastPoint.x) * INTERACTION_TUNING.dragSpeed;
      const dy = (currentPoint.y - this.lastPoint.y) * INTERACTION_TUNING.dragSpeed;
      this.lastPoint = currentPoint;
      if (typeof graph.translateBy === 'function') {
        graph.translateBy([dx, dy], false);
      }
    };

    onPointerUp = () => {
      this.dragging = false;
      this.lastPoint = null;
      const { graph } = this.context;
      if (typeof graph.setCursor === 'function') graph.setCursor('grab');
    };
  }

  function ensureRegistered() {
    if (registered) return;
    register(ExtensionCategory.NODE, 'tekg-indented', TEKGIndentedNode);
    register(ExtensionCategory.EDGE, 'tekg-indented', TEKGIndentedEdge);
    register(ExtensionCategory.BEHAVIOR, 'tekg-collapse-expand-tree', TEKGCollapseExpandTree);
    register(ExtensionCategory.BEHAVIOR, 'tekg-drag-canvas-fast', TEKGDragCanvasFast);
    registered = true;
  }

  function updateDetail(nodeData) {
    const detailEl = getEl('node-details');
    if (!detailEl) return;
    const config = getActiveTreeConfig();
    if (!nodeData) {
      detailEl.innerHTML = config.defaultDetailHtml || 'G6 indented default-tree view is active.';
      return;
    }
    if (typeof config.buildDetailHtml === 'function') {
      detailEl.innerHTML = config.buildDetailHtml(nodeData) || '';
      return;
    }
    detailEl.innerHTML = '';
  }

  function clearSelectedNode() {
    if (!g6Graph || typeof g6Graph.setElementState !== 'function' || !selectedNodeId) return;
    try {
      g6Graph.setElementState(selectedNodeId, []);
    } catch (_error) {
      // stale node ids after rerender can be ignored safely
    }
    selectedNodeId = null;
  }

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

  function setSelectedNode(nodeId) {
    if (!g6Graph || !nodeId || typeof g6Graph.setElementState !== 'function') return;
    clearSelectedNode();
    try {
      g6Graph.setElementState(nodeId, ['selected']);
      selectedNodeId = nodeId;
    } catch (_error) {
      selectedNodeId = null;
    }
  }

  async function focusNode(nodeId) {
    if (!g6Graph || !nodeId || typeof g6Graph.focusElement !== 'function') return;
    try {
      await g6Graph.focusElement(nodeId, {
        duration: INTERACTION_TUNING.focusDuration,
        easing: 'ease-in-out',
      });
      if (typeof focusLevel !== 'undefined' && focusLevel === 100 && typeof g6Graph.getZoom === 'function' && typeof g6Graph.zoomTo === 'function') {
        const currentZoom = g6Graph.getZoom();
        const nextZoom = Math.max(currentZoom, INTERACTION_TUNING.localFocusZoom);
        if (nextZoom !== currentZoom) {
          await g6Graph.zoomTo(nextZoom, {
            duration: INTERACTION_TUNING.focusZoomDuration,
            easing: 'ease-out',
          });
        }
      }
    } catch (error) {
      console.warn('G6 focus failed:', error);
    }
  }

  async function activateNode(nodeId, { shouldFocus = true } = {}) {
    if (!g6Graph || !nodeId || typeof g6Graph.getNodeData !== 'function') return;
    const nodeData = g6Graph.getNodeData(nodeId);
    updateDetail(nodeData);
    setSelectedNode(nodeId);
    const fixedModeEnabled = typeof fixedView !== 'undefined' && fixedView === true;
    if (shouldFocus && !fixedModeEnabled) {
      await focusNode(nodeId);
    }
  }

  function destroyGraph() {
    if (g6Graph && typeof g6Graph.destroy === 'function') g6Graph.destroy();
    g6Graph = null;
  }

  async function renderTreeData(treeData, options = {}) {
    const detailEl = getEl('node-details');
    try {
      ensureRegistered();
      setRendererVisibility();
      if (window.__TEKG_G6_DYNAMIC_GRAPH && typeof window.__TEKG_G6_DYNAMIC_GRAPH.destroy === 'function') {
        window.__TEKG_G6_DYNAMIC_GRAPH.destroy();
      }

      const host = getEl('g6-default-tree-surface');
      if (!host) return;

      await new Promise((resolve) => requestAnimationFrame(resolve));

      const width = host.clientWidth || host.offsetWidth;
      const height = host.clientHeight || host.offsetHeight;
      if (!width || !height) {
        if (detailEl) detailEl.textContent = 'G6 container has no size yet.';
        return;
      }
      if (!treeData) {
        if (detailEl) detailEl.textContent = 'Failed to build G6 tree data.';
        return;
      }
      rootId = String(options.rootId || treeData.id || '');
      activeTreeConfig = {
        ...buildDefaultTreeConfig(),
        ...(options.config && typeof options.config === 'object' ? options.config : {}),
      };

      destroyGraph();
      host.innerHTML = '';

      const graph = new Graph({
        container: host,
        width,
        height,
        autoResize: true,
        autoFit: 'view',
        padding: [40, 80, 40, 40],
        animation: false,
        cursor: 'grab',
        data: treeToGraphData(treeData),
        node: {
          type: 'tekg-indented',
          style: {
            size: (datum) => [normalizeTextWidth(resolveTreeLabel(datum)), NODE_HEIGHT],
            labelBackground: true,
            labelBackgroundRadius: 0,
            labelBackgroundFill: (datum) => (datum.id === rootId ? ROOT_BG : '#ffffff'),
            labelFill: (datum) => resolveTreeLabelFill(datum),
            labelText: (datum) => resolveTreeLabel(datum),
            labelTextAlign: (datum) => (datum.id === rootId ? 'center' : 'left'),
            labelTextBaseline: 'top',
            color: (datum) => {
              const depth = graph.getAncestorsData(datum.id, 'tree').length - 1;
              return COLORS[depth % COLORS.length] || ROOT_BG;
            },
            showIcon: false,
          },
          state: {
            selected: {
              lineWidth: 0,
              labelFill: '#40A8FF',
              labelBackground: true,
              labelFontWeight: 'normal',
              labelBackgroundFill: '#e8f7ff',
              labelBackgroundRadius: 10,
            },
          },
        },
        edge: {
          type: 'tekg-indented',
          style: {
            radius: 16,
            lineWidth: 2,
            sourcePort: 'out',
            targetPort: 'in',
            stroke: (datum) => {
              const depth = graph.getAncestorsData(datum.source, 'tree').length;
              return COLORS[depth % COLORS.length] || COLORS[0];
            },
          },
        },
        layout: {
          type: 'indented',
          direction: 'LR',
          isHorizontal: true,
          indent: INDENT,
          getHeight: () => NODE_HEIGHT,
          getVGap: () => V_GAP,
        },
        behaviors: [
          {
            type: 'tekg-drag-canvas-fast',
          },
          {
            type: 'zoom-canvas',
            sensitivity: INTERACTION_TUNING.zoomSensitivity,
          },
          'tekg-collapse-expand-tree',
          {
            type: 'click-select',
            enable: (event) => event.targetType === 'node' && event.target.id !== rootId,
          },
        ],
      });

      g6Graph = graph;
      await graph.render();
      clearSelectedNode();
      updateDetail(null);

      graph.on('node:click', async (event) => {
        const targetId = resolveEventNodeId(event);
        if (!targetId || typeof graph.getNodeData !== 'function') return;
        const nodeData = graph.getNodeData(targetId);
        const fixedModeEnabled = typeof fixedView !== 'undefined' && fixedView === true;
        const homePreviewMode = window.__TEKG_EMBED_MODE === 'home-preview';
        try {
          if (typeof activeTreeConfig?.onNodeClick === 'function') {
            const handled = await activeTreeConfig.onNodeClick(nodeData, {
              nodeId: targetId,
              fixedModeEnabled,
              homePreviewMode,
              loadDynamicGraph(query) {
                if (typeof window.__TEKG_LOAD_DYNAMIC_GRAPH !== 'function') {
                  return Promise.reject(new Error('Dynamic graph loader is unavailable.'));
                }
                return window.__TEKG_LOAD_DYNAMIC_GRAPH(query);
              },
            });
            if (handled) return;
          }
        } catch (error) {
          const detailEl = getEl('node-details');
          if (detailEl) {
            detailEl.textContent = `G6 tree click failed to load dynamic graph: ${error && error.message ? error.message : 'unknown error'}`;
          }
          console.error('G6 tree click failed to load dynamic graph:', error);
          return;
        }
        await activateNode(targetId, { shouldFocus: true });
      });
    } catch (error) {
      if (detailEl) {
        detailEl.textContent = `G6 default tree failed: ${error && error.message ? error.message : 'unknown error'}`;
      }
      console.error('G6 default tree failed:', error);
    }
  }

  async function renderDefaultTree() {
    const detailEl = getEl('node-details');
    const source = buildStrictTreeSource();
    if (!source.rootId) {
      if (detailEl) detailEl.textContent = 'Default tree data is unavailable.';
      return;
    }

    const treeData = buildTreeNode(source.rootId, source);
    await renderTreeData(treeData, {
      rootId: source.rootId,
      config: buildDefaultTreeConfig(),
    });
  }

  async function renderStructuredTree(options = {}) {
    const treeData = options && typeof options === 'object' ? options.treeData : null;
    await renderTreeData(treeData, {
      rootId: options.rootId,
      config: options.config,
    });
  }

  function bindTriggers() {
    if (isBound) return;
    isBound = true;

    const resetBtn = getEl('reset-graph');
    if (resetBtn) resetBtn.addEventListener('click', () => setTimeout(renderDefaultTree, 0));

    const zhBtn = getEl('lang-zh');
    const enBtn = getEl('lang-en');
    if (zhBtn) zhBtn.addEventListener('click', () => setTimeout(renderDefaultTree, 0));
    if (enBtn) enBtn.addEventListener('click', () => setTimeout(renderDefaultTree, 0));

    window.addEventListener('resize', () => {
      if (!g6Graph || typeof g6Graph.resize !== 'function') return;
      const host = getEl('g6-default-tree-surface');
      if (!host) return;
      const width = host.clientWidth || host.offsetWidth;
      const height = host.clientHeight || host.offsetHeight;
      if (width && height) g6Graph.resize(width, height);
    });

    window.addEventListener('tekg:shared-ready', () => {
      if (window.__TEKG_G6_BOOTSTRAP_OWN_TREE) return;
      renderDefaultTree();
    });

      if (!window.__TEKG_G6_BOOTSTRAP_OWN_TREE) {
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
          setTimeout(renderDefaultTree, 0);
        } else {
          window.addEventListener('DOMContentLoaded', () => setTimeout(renderDefaultTree, 0), { once: true });
        }
      }
    }

  bindTriggers();
  window.__TEKG_G6_DEFAULT_TREE = {
    render: renderDefaultTree,
    renderStructuredTree,
    destroy: destroyGraph,
  };
}());
