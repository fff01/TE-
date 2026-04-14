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
    CommonEvent,
    NodeEvent,
    CubicHorizontal,
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
    !CubicHorizontal
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
    COLLAPSE_EXPAND: 'tekg-mindmap-collapse-expand',
  };

  const ROOT_BG = '#cfe0f8';
  const ROOT_TEXT = '#2f5e99';
  const TEXT_COLOR = '#2f3a52';
  const EDGE_COLOR = '#c7cedb';
  const ROOT_HEIGHT = 20;
  const NODE_HEIGHT = 18;
  const ROOT_FONT_SIZE = 12;
  const NODE_FONT_SIZE = 11;
  const ROOT_PADDING_X = 10;
  const NODE_PADDING_X = 8;
  const DEFAULT_TREE_PADDING = [96, 120, 96, 120];
  const COMPACT_TREE_PADDING = [56, 56, 56, 56];
  const DEFAULT_H_GAP = 112;
  const COMPACT_H_GAP = 52;
  const V_GAP = 12;
  const MAX_EXPAND_CHILDREN = 6;
  const INITIAL_ROOT_LEFT_SHIFT = 120;

  let g6Graph = null;
  let registered = false;
  let rootId = null;
  let selectedNodeId = null;
  let activeTreeConfig = null;
  let stateTreeRoot = null;
  let lastRenderOptions = null;

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

  const TREE_DISPLAY_LABELS = new Map([
    ['TE', 'Transposable Elements - Human'],
    ['Retrotransposon', 'Class I: Retrotransposons'],
    ['DNA Transposon', 'Class II: DNA Transposons'],
    ['SINE', 'SINEs'],
  ]);

  function getRootLabel() {
    return 'TE - Human';
  }

  function getTreeDisplayLabel(raw) {
    const key = String(raw || '').trim();
    return TREE_DISPLAY_LABELS.get(key) || key;
  }

  function normalizeTextWidth(text, isRoot) {
    const compact = !!getActiveTreeConfig()?.compactLayout;
    const length = Math.max(2, String(text || '').length);
    const avg = isRoot ? (compact ? 8.8 : 9.6) : (compact ? 6.2 : 7.1);
    const padding = isRoot ? (compact ? 8 : ROOT_PADDING_X) : (compact ? 6 : NODE_PADDING_X);
    return Math.round(length * avg + padding);
  }

  function getDisplayLabel(label, description, depth) {
    const raw = String(label || '');
    if (depth === 0) return getRootLabel();
    const mapped = getTreeDisplayLabel(raw);
    if (mapped !== raw) return mapped;
    if (typeof getName === 'function') return getName(raw, 'TE', description || '', '');
    return raw;
  }

  function getDescription(label, description) {
    if (typeof getDesc === 'function') return getDesc(label || '', 'TE', description || '', '');
    return String(description || '');
  }

  function buildDefaultTreeConfig() {
    const defaultDetailHtml = getCurrentLang() === 'zh'
      ? '<strong>尚未选中节点</strong>当前为 G6 思维导图树视图。'
      : 'G6 mindmap tree view is active.';

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
        const hasChildren = Array.isArray(nodeData?.children) && nodeData.children.length > 0;
        if (!query || hasChildren) return false;
        await window.__TEKG_LOAD_DYNAMIC_GRAPH(query);
        return true;
      },
    };
  }

  function getActiveTreeConfig() {
    if (!activeTreeConfig) activeTreeConfig = buildDefaultTreeConfig();
    return activeTreeConfig;
  }

  function isCompactTreeLayout() {
    return !!getActiveTreeConfig()?.compactLayout;
  }

  function getTreePadding() {
    return isCompactTreeLayout() ? COMPACT_TREE_PADDING : DEFAULT_TREE_PADDING;
  }

  function getHorizontalGap() {
    return isCompactTreeLayout() ? COMPACT_H_GAP : DEFAULT_H_GAP;
  }

  function resolveTreeLabelFill(datum, isRoot) {
    const config = getActiveTreeConfig();
    const data = datum?.data || {};
    if (typeof config.buildLabelFill === 'function') {
      const color = config.buildLabelFill(data, datum?.id || '');
      if (color) return String(color);
    }
    return isRoot ? ROOT_TEXT : TEXT_COLOR;
  }

  function resolveTreeLabelFontWeight(datum, isRoot) {
    const config = getActiveTreeConfig();
    const data = datum?.data || {};
    if (typeof config.buildLabelFontWeight === 'function') {
      const weight = config.buildLabelFontWeight(data, datum?.id || '');
      if (weight) return String(weight);
    }
    return isRoot ? '600' : 'normal';
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

  function buildTreeNode(nodeId, source, parent = null) {
    const node = source.nodes.get(nodeId);
    if (!node) return null;
    const childIds = source.children.get(nodeId) || [];
    const depth = node.treeDepth || 0;
    const label = getDisplayLabel(node.label, node.description, depth);
    const treeNode = {
      id: node.id,
      name: label,
      data: {
        rawLabel: node.label,
        queryLabel: node.queryLabel,
        description: node.description,
        treeDepth: depth,
      },
      _collapsed: true,
      _hidden: false,
      _matched: false,
      _matched_path: false,
      _parent: parent,
      style: {
        collapsed: true,
        direction: depth === 0 ? 'center' : 'right',
        labelText: label,
      },
      children: [],
    };
    treeNode.children = childIds.map((childId) => buildTreeNode(childId, source, treeNode)).filter(Boolean);
    return treeNode;
  }

  function walkTree(node, visitor) {
    if (!node || typeof visitor !== 'function') return;
    visitor(node);
    const children = Array.isArray(node.children) ? node.children : [];
    children.forEach((child) => walkTree(child, visitor));
  }

  function syncCollapsedStyle(node) {
    walkTree(node, (current) => {
      current.style ||= {};
      current.style.collapsed = !!current._collapsed;
    });
  }

  function initTreeState(root, options = {}) {
    const expandAll = !!(options && options.expandAll);
    walkTree(root, (node) => {
      node._matched = false;
      node._matched_path = false;
      node._hidden = false;
      node._collapsed = expandAll ? false : true;
    });
    if (root && !expandAll) {
      root._collapsed = false;
    }
    syncCollapsedStyle(root);
  }

  function findTreeStateNode(node, targetId) {
    if (!node || !targetId) return null;
    if (String(node.id) === String(targetId)) return node;
    const children = Array.isArray(node.children) ? node.children : [];
    for (const child of children) {
      const found = findTreeStateNode(child, targetId);
      if (found) return found;
    }
    return null;
  }

  function setTreeCollapsed(node, collapsed) {
    if (!node) return;
    node._collapsed = !!collapsed;
    node.style ||= {};
    node.style.collapsed = !!collapsed;
  }


  function buildVisibleGraphData(root, viewportWidth, viewportHeight) {
    const nodes = [];
    const edges = [];
    let rowIndex = 0;
    const treePadding = getTreePadding();
    const left = treePadding[3];
    const top = treePadding[0];
    const bottom = treePadding[2];
    const rowGap = NODE_HEIGHT + V_GAP;

    function visit(node, depth) {
      if (!node || node._hidden) return null;
      const children = Array.isArray(node.children) ? node.children.filter((child) => !child._hidden) : [];
      const expandedChildren = node._collapsed ? [] : children;
      const childLayouts = [];
      for (const child of expandedChildren) {
        const childLayout = visit(child, depth + 1);
        if (childLayout) childLayouts.push(childLayout);
      }

      const y = childLayouts.length > 0
        ? (childLayouts[0].y + childLayouts[childLayouts.length - 1].y) / 2
        : rowIndex++ * rowGap;
      const labelText = node?.style?.labelText || node.name || node.id;
      const directChildCount = Array.isArray(node.children) ? node.children.length : 0;
      const isRoot = depth === 0;
      const nodeWidth = normalizeTextWidth(labelText, isRoot);

      nodes.push({
        id: node.id,
        data: {
          ...(node.data || {}),
          directChildCount,
          depth,
        },
        children: expandedChildren.map((child) => child.id),
        style: {
          ...node.style,
          x: 0,
          y,
          collapsed: !!node._collapsed,
          directChildCount,
          labelText,
          __depth: depth,
          __width: nodeWidth,
        },
      });

      for (const child of expandedChildren) {
        edges.push({
          id: `${node.id}__${child.id}`,
          source: node.id,
          target: child.id,
        });
      }

      return { id: node.id, y, depth, width: nodeWidth };
    }

    visit(root, 0);

    if (!nodes.length) {
      return { nodes: [], edges: [] };
    }

    const maxWidthByDepth = new Map();
    for (const node of nodes) {
      const depth = node.style.__depth || 0;
      const width = node.style.__width || 0;
      maxWidthByDepth.set(depth, Math.max(maxWidthByDepth.get(depth) || 0, width));
    }

    const xByDepth = new Map();
    const maxDepth = Math.max(...nodes.map((node) => node.style.__depth || 0));
    for (let depth = 0; depth <= maxDepth; depth += 1) {
      const width = maxWidthByDepth.get(depth) || 0;
      if (depth === 0) {
        xByDepth.set(depth, left + width / 2);
      } else {
        const prevWidth = maxWidthByDepth.get(depth - 1) || 0;
        const prevX = xByDepth.get(depth - 1) || left;
        xByDepth.set(depth, prevX + prevWidth / 2 + getHorizontalGap() + width / 2);
      }
    }

    const ys = nodes.map((node) => node.style.y);
    const minY = Math.min(...ys);
    const maxY = Math.max(...ys);
    const contentHeight = Math.max(0, maxY - minY);
    const availableHeight = Math.max(0, viewportHeight - top - bottom);
    const offsetY = top + Math.max(0, (availableHeight - contentHeight) / 2) - minY;

    nodes.forEach((node) => {
      const depth = node.style.__depth || 0;
      node.style.x = xByDepth.get(depth) || left;
      node.style.y += offsetY;
      delete node.style.__depth;
      delete node.style.__width;
    });

    return { nodes, edges };
  }

  async function rerenderFromStateTree() {
    if (!stateTreeRoot || !lastRenderOptions) return;
    await renderTreeData(stateTreeRoot, lastRenderOptions);
  }

  class TEKGMindmapNode extends BaseNode {
    static defaultStyleProps = {
      showIcon: false,
      ports: [{ placement: 'right' }, { placement: 'left' }],
    };

    constructor(options) {
      Object.assign(options.style, TEKGMindmapNode.defaultStyleProps);
      super(options);
    }

    get directChildCount() {
      return Number(this.parsedAttributes?.directChildCount || 0);
    }

    get rootNodeId() {
      return rootId;
    }

    getKeyStyle(attributes) {
      const [width, height] = this.getSize(attributes);
      return {
        width,
        height,
        ...super.getKeyStyle(attributes),
      };
    }

    drawKeyShape(attributes, container) {
      return this.upsert('key', 'rect', this.getKeyStyle(attributes), container);
    }

    getLabelStyle(attributes) {
      if (attributes.label === false || !attributes.labelText) return false;
      return subStyleProps(this.getGraphicStyle(attributes), 'label');
    }

    isShowCollapse(attributes) {
      const { collapsed, showIcon } = attributes;
      return !collapsed && showIcon && this.directChildCount > 0;
    }

    getCountStyle(attributes) {
      const { collapsed, color } = attributes;
      const directChildren = this.directChildCount;
      if (!collapsed || directChildren === 0 || directChildren >= MAX_EXPAND_CHILDREN) return false;
      const [width, height] = this.getSize(attributes);
      return {
        backgroundFill: color,
        backgroundHeight: 16,
        backgroundWidth: 16,
        cursor: 'pointer',
        fill: '#fff',
        fontSize: 11,
        text: '+',
        textAlign: 'center',
        x: width + 12,
        y: Math.round(height * 0.5),
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

    getCollapseStyle(attributes) {
      const { showIcon, color } = attributes;
      const directChildren = this.directChildCount;
      if (!this.isShowCollapse(attributes) || directChildren >= MAX_EXPAND_CHILDREN) return false;
      const [width, height] = this.getSize(attributes);
      return {
        backgroundFill: color,
        backgroundHeight: 16,
        backgroundWidth: 16,
        cursor: 'pointer',
        fill: '#fff',
        fontSize: 11,
        text: '-',
        textAlign: 'center',
        visibility: showIcon ? 'visible' : 'hidden',
        x: width + 12,
        y: Math.round(height * 0.5),
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

    forwardEvent(target, type, listener) {
      if (target && !Reflect.has(target, '__bind__')) {
        Reflect.set(target, '__bind__', true);
        target.addEventListener(type, listener);
      }
    }

    render(attributes = this.parsedAttributes, container = this) {
      super.render(attributes, container);
      this.drawCollapseShape(attributes, container);
      this.drawCountShape(attributes, container);
    }
  }

  class TEKGMindmapEdge extends CubicHorizontal {
    getKeyPath(attributes) {
      return super.getKeyPath(attributes);
    }
  }

  class TEKGCollapseExpandMindmap extends BaseBehavior {
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
      graph.on(NodeEvent.POINTER_ENTER, this.showIcon);
      graph.on(NodeEvent.POINTER_LEAVE, this.hideIcon);
      graph.on(TREE_EVENT.COLLAPSE_EXPAND, this.onCollapseExpand);
    }

    unbindEvents() {
      const { graph } = this.context;
      graph.off(NodeEvent.POINTER_ENTER, this.showIcon);
      graph.off(NodeEvent.POINTER_LEAVE, this.hideIcon);
      graph.off(TREE_EVENT.COLLAPSE_EXPAND, this.onCollapseExpand);
    }

    status = 'idle';

    showIcon = (event) => {
      this.setIcon(event, true);
    };

    hideIcon = (event) => {
      this.setIcon(event, false);
    };

    setIcon = (event, show) => {
      if (this.status !== 'idle') return;
      const id = event?.target?.id;
      if (!id) return;
      const { graph, element } = this.context;
      graph.updateNodeData([{ id, style: { showIcon: show } }]);
      element.draw({ animation: false, silence: true });
    };

    onCollapseExpand = async (event) => {
      this.status = 'busy';
      const { id, collapsed } = event;
      const stateNode = findTreeStateNode(stateTreeRoot, id);
      const children = Array.isArray(stateNode?.children) ? stateNode.children : [];
      if (!collapsed && children.length >= MAX_EXPAND_CHILDREN) {
        this.status = 'idle';
        return;
      }
      setTreeCollapsed(stateNode, collapsed);
      await rerenderFromStateTree();
      this.status = 'idle';
    };
  }

  function ensureRegistered() {
    if (registered) return;
    register(ExtensionCategory.NODE, 'tekg-mindmap', TEKGMindmapNode);
    register(ExtensionCategory.EDGE, 'tekg-mindmap', TEKGMindmapEdge);
    register(ExtensionCategory.BEHAVIOR, 'tekg-mindmap-collapse-expand-tree', TEKGCollapseExpandMindmap);
    registered = true;
  }

  function updateDetail(nodeData) {
    const detailEl = getEl('node-details');
    if (!detailEl) return;
    const config = getActiveTreeConfig();
    if (!nodeData) {
      detailEl.innerHTML = config.defaultDetailHtml || 'G6 mindmap tree view is active.';
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
    } catch (_error) {}
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

  async function activateNode(nodeId) {
    if (!g6Graph || !nodeId || typeof g6Graph.getNodeData !== 'function') return;
    const nodeData = g6Graph.getNodeData(nodeId);
    updateDetail(nodeData);
    setSelectedNode(nodeId);
  }

  function destroyGraph() {
    if (g6Graph && typeof g6Graph.destroy === 'function') g6Graph.destroy();
    g6Graph = null;
  }

  function alignRootToLeft(graph, viewportHeight) {
    if (!graph || typeof graph.translateBy !== 'function') return;
    try {
      graph.translateBy([INITIAL_ROOT_LEFT_SHIFT, Math.max(0, Math.round(viewportHeight * 0.5))], false);
    } catch (_error) {
      // fallback to default view when viewport translation is unavailable
    }
  }

  async function renderTreeData(treeData, options = {}) {
    const detailEl = getEl('node-details');
    try {
      ensureRegistered();
      setRendererVisibility();
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
        if (detailEl) detailEl.textContent = 'Failed to build G6 mindmap tree data.';
        return;
      }

      rootId = String(options.rootId || treeData.id || '');
      activeTreeConfig = {
        ...buildDefaultTreeConfig(),
        ...(options.config && typeof options.config === 'object' ? options.config : {}),
      };
      lastRenderOptions = {
        rootId,
        config: activeTreeConfig,
      };

      destroyGraph();
      host.innerHTML = '';
      const visibleData = buildVisibleGraphData(treeData, width, height);

      const graph = new Graph({
        container: host,
        width,
        height,
        autoResize: true,
        autoFit: false,
        padding: getTreePadding(),
        animation: false,
        cursor: 'grab',
        data: visibleData,
        node: {
          type: 'tekg-mindmap',
          style: (datum) => {
            const isRoot = datum.id === rootId;
            const labelText = resolveTreeLabel(datum);
            const compact = isCompactTreeLayout();
            return {
              direction: isRoot ? 'center' : 'right',
              labelText,
              size: [normalizeTextWidth(labelText, isRoot), isRoot ? ROOT_HEIGHT : NODE_HEIGHT],
              labelFontFamily: 'Gill Sans',
              labelFontSize: isRoot ? ROOT_FONT_SIZE : NODE_FONT_SIZE,
              labelPlacement: 'center',
              labelTextAlign: 'center',
              labelPadding: isRoot
                ? (compact ? [2, 4, 2, 4] : [2, 6, 2, 6])
                : (compact ? [1, 6, 1, 6] : [1, 8, 1, 8]),
              labelFill: resolveTreeLabelFill(datum, isRoot),
              labelFontWeight: resolveTreeLabelFontWeight(datum, isRoot),
              labelBackground: true,
              labelBackgroundFill: isRoot ? ROOT_BG : '#ffffff',
              fill: isRoot ? ROOT_BG : '#ffffff',
              stroke: isRoot ? ROOT_BG : COLORS[Math.max(0, (datum?.data?.treeDepth || 1) - 1) % COLORS.length],
              radius: 999,
              color: isRoot ? ROOT_BG : COLORS[Math.max(0, (datum?.data?.treeDepth || 1) - 1) % COLORS.length],
              lineWidth: isRoot ? 1.4 : 1.2,
            };
          },
          state: {
            selected: {
              lineWidth: 0,
              labelBackground: true,
              labelBackgroundFill: '#e8f7ff',
              labelBackgroundRadius: 10,
            },
          },
        },
        edge: {
          type: 'tekg-mindmap',
          style: {
            lineWidth: 1.4,
            stroke: EDGE_COLOR,
          },
        },
        behaviors: [
          'drag-canvas',
          'zoom-canvas',
          'tekg-mindmap-collapse-expand-tree',
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
        try {
          if (typeof activeTreeConfig?.onNodeClick === 'function') {
            const handled = await activeTreeConfig.onNodeClick(nodeData, {
              nodeId: targetId,
              fixedModeEnabled: typeof fixedView !== 'undefined' && fixedView === true,
              homePreviewMode: window.__TEKG_EMBED_MODE === 'home-preview',
            });
            if (handled) return;
          }
        } catch (error) {
          if (detailEl) {
            detailEl.textContent = `G6 mindmap tree click failed: ${error && error.message ? error.message : 'unknown error'}`;
          }
          return;
        }
        await activateNode(targetId);
      });
    } catch (error) {
      if (detailEl) {
        detailEl.textContent = `G6 mindmap tree failed: ${error && error.message ? error.message : 'unknown error'}`;
      }
      console.error('G6 mindmap tree failed:', error);
    }
  }

  async function renderDefaultTree() {
    const detailEl = getEl('node-details');
    const source = buildStrictTreeSource();
    if (!source.rootId) {
      if (detailEl) detailEl.textContent = 'Mindmap tree data is unavailable.';
      return;
    }

    stateTreeRoot = buildTreeNode(source.rootId, source);
    initTreeState(stateTreeRoot);
    await renderTreeData(stateTreeRoot, {
      rootId: source.rootId,
      config: buildDefaultTreeConfig(),
    });
  }

  async function renderStructuredTree(options = {}) {
    const treeData = options && typeof options === 'object' ? options.treeData : null;
    stateTreeRoot = treeData || null;
    if (stateTreeRoot) {
      initTreeState(stateTreeRoot, {
        expandAll: !!(options.expandAll || (options.config && options.config.expandAll)),
      });
    }
    await renderTreeData(stateTreeRoot, {
      rootId: options.rootId,
      config: options.config,
    });
  }

  window.__TEKG_G6_MINDMAP_TREE = {
    render: renderDefaultTree,
    renderStructuredTree,
    destroy: destroyGraph,
  };
  window.__TEKG_G6_DEFAULT_TREE = window.__TEKG_G6_MINDMAP_TREE;
}());