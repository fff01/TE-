(function () {
  const contract = window.__TEKG_LARGE_FORCE_GRAPH_CONTRACT;
  if (!contract) return;

  function seededUnit(seed) {
    let hash = 2166136261;
    const text = String(seed || '');
    for (let index = 0; index < text.length; index += 1) {
      hash ^= text.charCodeAt(index);
      hash = Math.imul(hash, 16777619);
    }
    return ((hash >>> 0) % 10000) / 10000;
  }

  function levelKey(depth) {
    return `level-${Math.max(0, Number(depth) || 0)}`;
  }

  function levelLabel(depth) {
    const safeDepth = Math.max(0, Number(depth) || 0);
    const labels = ['Human TE', 'Class', 'Order', 'Superfamily', 'Family', 'Subfamily'];
    return labels[safeDepth] || `Level ${safeDepth}`;
  }

  function nodeColor(depth, maxDepth) {
    const safeDepth = Math.max(0, Number(depth) || 0);
    const safeMax = Math.max(1, Number(maxDepth) || 1);
    const t = Math.max(0, Math.min(1, safeDepth / safeMax));
    const start = [20, 47, 124];
    const end = [37, 99, 235];
    const rgb = start.map((value, index) => Math.round(value + (end[index] - value) * t));
    return `rgb(${rgb[0]}, ${rgb[1]}, ${rgb[2]})`;
  }

  function nodeSize(depth, siblingCount = 0, childCount = 0) {
    const safeDepth = Math.max(0, Number(depth) || 0);
    const base = [68, 46, 34, 24, 15, 9][safeDepth] || 7;
    const siblingPenalty = siblingCount > 160 ? 4 : siblingCount > 90 ? 3 : siblingCount > 48 ? 2 : 0;
    const childBonus = childCount > 20 ? 3 : childCount > 8 ? 1 : 0;
    return Math.max(6, base - siblingPenalty + childBonus);
  }

  function isMapLike(value) {
    return !!value
      && typeof value.get === 'function'
      && typeof value.set === 'function'
      && typeof value.values === 'function'
      && typeof value.entries === 'function';
  }

  function buildTree(source) {
    const nodes = isMapLike(source?.nodes) ? source.nodes : new Map();
    const children = isMapLike(source?.children) ? source.children : new Map();
    const rootId = String(source?.rootId || [...nodes.values()].find((node) => Number(node.treeDepth || 0) === 0)?.id || '');
    function makeNode(id, parent = null) {
      const node = nodes.get(id);
      if (!node) return null;
      const childIds = children.get(id) || [];
      const treeNode = {
        ...node,
        id,
        parent,
        children: [],
      };
      treeNode.children = childIds.map((childId) => makeNode(childId, treeNode)).filter(Boolean);
      return treeNode;
    }
    return rootId ? makeNode(rootId) : null;
  }

  function walk(node, visitor) {
    if (!node || typeof visitor !== 'function') return;
    visitor(node);
    for (const child of Array.isArray(node.children) ? node.children : []) walk(child, visitor);
  }

  function fromTaxonomySource(source, options = {}) {
    const rootTree = buildTree(source);
    if (!rootTree) {
      return contract.normalizeGraphData({
        graphId: `taxonomy:${options.treeVariant || 'unknown'}`,
        meta: { source: 'taxonomy', truthSource: 'api/taxonomy.php' },
        nodes: [],
        edges: [],
      });
    }

    const width = Number(options.width) || 800;
    const height = Number(options.height) || 520;
    const treeVariant = String(options.treeVariant || 'rmsk_repbase');
    const nodeEntries = [];
    const parentById = new Map();
    const descendantCountById = new Map();
    walk(rootTree, (node) => {
      nodeEntries.push(node);
      for (const child of Array.isArray(node.children) ? node.children : []) {
        parentById.set(child.id, node.id);
      }
    });
    const maxDepth = nodeEntries.reduce((max, node) => Math.max(max, Number(node.treeDepth || 0)), 0);
    const centerX = Math.max(260, width * 0.42);
    const centerY = Math.max(160, height / 2);
    const firstLevelChildren = Array.isArray(rootTree.children) ? rootTree.children : [];
    const branchIndexById = new Map(firstLevelChildren.map((child, index) => [child.id, index]));
    const branchById = new Map([[rootTree.id, rootTree.id]]);
    const branchSize = new Map();
    const positionById = new Map([[rootTree.id, { x: centerX, y: centerY }]]);

    function countDescendants(node) {
      const children = Array.isArray(node.children) ? node.children : [];
      const count = children.reduce((sum, child) => sum + 1 + countDescendants(child), 0);
      descendantCountById.set(node.id, count);
      return count;
    }
    countDescendants(rootTree);

    function assignBranch(node, branchId) {
      const depth = Math.max(0, Number(node.treeDepth || 0));
      const nextBranch = depth === 1 ? node.id : branchId;
      branchById.set(node.id, nextBranch || rootTree.id);
      branchSize.set(nextBranch || rootTree.id, (branchSize.get(nextBranch || rootTree.id) || 0) + 1);
      for (const child of Array.isArray(node.children) ? node.children : []) assignBranch(child, nextBranch);
    }
    assignBranch(rootTree, rootTree.id);

    const nodes = [];
    walk(rootTree, (node) => {
      const depth = Math.max(0, Number(node.treeDepth || 0));
      const childCount = Array.isArray(node.children) ? node.children.length : 0;
      const parentId = parentById.get(node.id) || '';
      const siblingIds = parentId && isMapLike(source.children) ? source.children.get(parentId) : null;
      const siblingCount = Array.isArray(siblingIds) ? siblingIds.length : firstLevelChildren.length || 1;
      const branchId = branchById.get(node.id) || rootTree.id;
      const branchIndex = branchIndexById.has(branchId) ? branchIndexById.get(branchId) : 0;
      const branchCount = Math.max(1, firstLevelChildren.length);
      let x = centerX;
      let y = centerY;

      if (depth === 1) {
        const branchAngle = -Math.PI / 2 + (Math.PI * 2 * branchIndex) / branchCount;
        const branchRadius = Math.min(width, height) * 0.14 + Math.min(72, Math.sqrt(branchSize.get(branchId) || 1) * 2.5);
        x = centerX + Math.cos(branchAngle) * branchRadius;
        y = centerY + Math.sin(branchAngle) * branchRadius;
      } else if (depth > 1) {
        const parentPosition = positionById.get(parentId) || { x: centerX, y: centerY };
        const childIds = parentId && isMapLike(source.children) ? source.children.get(parentId) || [] : [];
        const childIndex = Math.max(0, childIds.indexOf(node.id));
        const localAngle = (Math.PI * 2 * childIndex) / Math.max(1, childIds.length) + (Math.PI * 2 * seededUnit(`${node.id}:angle`)) * 0.18;
        const crowding = siblingCount > 120 ? 0.58 : siblingCount > 64 ? 0.72 : siblingCount > 32 ? 0.86 : 1;
        const localRadius = (24 + Math.min(58, Math.sqrt(Math.max(1, siblingCount)) * 4.5)) * crowding;
        x = parentPosition.x + Math.cos(localAngle) * localRadius;
        y = parentPosition.y + Math.sin(localAngle) * localRadius;
      }
      positionById.set(node.id, { x, y });

      const rawLabel = String(node.label || node.id || '').trim();
      const displayLabel = depth === 0 ? 'Human TE' : String(options.getDisplayLabel ? options.getDisplayLabel(rawLabel, node.description, depth) : rawLabel);
      const treeIsMeta = node.treeIsMeta === true;
      const jumpable = !!String(node.queryLabel || '').trim() && !treeIsMeta && depth > 0;
      const key = levelKey(depth);
      nodes.push({
        id: node.id,
        label: displayLabel,
        rawLabel,
        displayLabel,
        description: String(node.description || ''),
        type: 'TE',
        level: depth,
        x,
        y,
        clusterX: x,
        clusterY: y,
        size: nodeSize(depth, siblingCount, childCount),
        color: nodeColor(depth, maxDepth),
        stroke: depth === 0 ? '#0f172a' : '#1d4ed8',
        lineDash: depth === 0 || jumpable ? [] : [5, 4],
        legendKeys: [key],
        pinnedLabel: depth <= 2,
        payload: {
          treeDepth: depth,
          queryLabel: node.queryLabel || rawLabel,
          taxonomyOnly: !jumpable,
          treeVariant,
          hasGraphEntity: jumpable,
          isRoot: depth === 0,
          taxonomyLevelKey: key,
          taxonomyLevelLabel: levelLabel(depth),
          directChildCount: childCount,
          descendantCount: descendantCountById.get(node.id) || 0,
          siblingCount,
          parentId,
          description: String(node.description || ''),
        },
      });
    });

    const edges = [];
    if (isMapLike(source.children)) {
      for (const [parentId, childIds] of source.children.entries()) {
        for (const childId of childIds) {
          edges.push({
            id: `${parentId}__taxonomy__${childId}`,
            source: parentId,
            target: childId,
            kind: 'taxonomy-parent',
            direction: 'directed',
            label: '',
            payload: { relation: 'taxonomy parent' },
          });
        }
      }
    }

    const counts = new Map();
    for (const node of nodes) counts.set(node.legendKeys[0], (counts.get(node.legendKeys[0]) || 0) + 1);
    const legendItems = Array.from({ length: maxDepth + 1 }, (_, depth) => {
      const key = levelKey(depth);
      return {
        key,
        depth,
        kind: 'taxonomy-level',
        label: levelLabel(depth),
        count: counts.get(key) || 0,
        color: nodeColor(depth, maxDepth),
        visible: typeof options.visibleTaxonomyLevels?.[key] === 'boolean' ? options.visibleTaxonomyLevels[key] : depth < 6,
        focusable: true,
      };
    }).filter((item) => item.count > 0);
    const legendState = contract.defaultLegendState(legendItems, options.visibleTaxonomyLevels || {});
    const visibleNodes = nodes.filter((node) => legendState[node.legendKeys[0]] !== false);
    const visibleNodeIds = new Set(visibleNodes.map((node) => String(node.id)));
    const visibleEdges = edges.filter((edge) => visibleNodeIds.has(String(edge.source)) && visibleNodeIds.has(String(edge.target)));

    const normalized = contract.normalizeGraphData({
      graphId: `taxonomy:${treeVariant}`,
      version: 1,
      meta: {
        source: 'taxonomy',
        truthSource: 'api/taxonomy.php',
        label: 'TE taxonomy graph',
        treeVariant,
        evidenceBoundary: '',
      },
      rootId: rootTree.id,
      maxDepth,
      nodes: visibleNodes,
      edges: visibleEdges,
      legend: { items: legendItems, state: legendState },
      options: {
        layoutProfile: 'taxonomy-large',
        performanceProfile: 'large-static',
      },
    });
    normalized.originalNodeCount = nodes.length;
    normalized.originalEdgeCount = edges.length;
    return normalized;
  }

  window.__TEKG_LARGE_FORCE_GRAPH_TAXONOMY_ADAPTER = {
    fromTaxonomySource,
    levelKey,
    levelLabel,
    nodeColor,
  };
}());
