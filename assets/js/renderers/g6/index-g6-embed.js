(function () {
  const G6Lib = window.G6;
  if (!G6Lib || !G6Lib.Graph) return;

  const { Graph } = G6Lib;
  const container = document.getElementById('container');
  if (!container) return;

  const params = new URLSearchParams(window.location.search);

  const TYPE_COLORS = {
    TE: '#4e79ff',
    Disease: '#ff7a7a',
    DiseaseClass: '#8f1731',
    Function: '#41b883',
    Paper: '#f2a93b',
  };

  const TYPE_STROKES = {
    TE: '#1f3f99',
    Disease: '#c84f62',
    DiseaseClass: '#5f1020',
    Function: '#2f8b63',
    Paper: '#b77a16',
  };

  let graph = null;
  let fixedView = params.get('fixed') === '1';
  let currentKeyNodeLevel = Math.max(1, Math.min(10, Number(params.get('key_level')) || 1));
  let currentQuery = String(params.get('q') || '').trim();
  let currentLang = params.get('lang') === 'zh' ? 'zh' : 'en';

  let nameTranslations = {};
  let teDescriptions = { en: {} };
  let entityDescriptions = { en: { Disease: {}, Function: {} } };
  let teLineageDepths = new Map();
  let teLineageChildren = new Map();
  let teLineageDescendants = new Map();
  let teDatabaseDegrees = new Map();
  let teFixedRadii = new Map();
  let resourcesPromise = null;
  const TE_MIN_RADIUS = 12.5;

  function getHost() {
    try {
      return window.parent && window.parent !== window ? window.parent.__TEKG_G6_GRAPH_HOST : null;
    } catch (_error) {
      return null;
    }
  }

  function pushDetail(title, description) {
    const host = getHost();
    if (host && typeof host.setDetail === 'function') {
      host.setDetail(title, description);
    }
  }

  function pushStatus(text) {
    const host = getHost();
    if (host && typeof host.setStatus === 'function') {
      host.setStatus(text);
    }
  }

  function pushMode(mode, query) {
    const host = getHost();
    if (host && typeof host.setMode === 'function') {
      host.setMode(mode, query);
    }
  }

  function pushSelection(node) {
    const host = getHost();
    if (host && typeof host.onNodeSelect === 'function') {
      host.onNodeSelect(node || null);
    }
  }

  function pushReady() {
    const host = getHost();
    if (host && typeof host.onReady === 'function') {
      host.onReady();
    }
  }

  function escapeHtml(text) {
    return String(text || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function degreeToSize(degree) {
    const safeDegree = Math.max(1, Number(degree) || 1);
    return Math.max(10, Math.min(64, 10 + Math.log2(safeDegree + 1) * 7.5));
  }

  function canonicalTeLineageName(name) {
    const raw = String(name || '').trim();
    if (!raw) return raw;
    if (raw === 'LINE1' || raw === 'LINE-1') return 'L1';
    return raw;
  }

  function computeTeVisualMetrics() {
    teFixedRadii = new Map();
    if (!teLineageDepths.size) return;

    const lineageNames = new Set([...teLineageDepths.keys(), ...teDatabaseDegrees.keys()]);
    const baseScores = new Map();

    for (const name of lineageNames) {
      const descendantCount = teLineageDescendants.get(name) || 0;
      const directChildrenCount = (teLineageChildren.get(name) || []).length;
      const databaseDegree = Math.max(0, Number(teDatabaseDegrees.get(name)) || 0);
      const nonChildEdgeCount = Math.max(0, databaseDegree - directChildrenCount);
      const weightedSignal = Math.max(1, descendantCount * 0.35 + nonChildEdgeCount * 0.65);
      baseScores.set(name, Math.sqrt(weightedSignal));
    }

    const adjustedScores = new Map(
      [...lineageNames].map((name) => [name, Math.max(1, Number(baseScores.get(name)) || 1)]),
    );

    const namesByDepthDesc = [...lineageNames].sort(
      (left, right) => (teLineageDepths.get(right) || 0) - (teLineageDepths.get(left) || 0),
    );

    for (const name of namesByDepthDesc) {
      const children = teLineageChildren.get(name) || [];
      if (!children.length) continue;
      const maxChildScore = Math.max(...children.map((child) => Math.max(0, Number(adjustedScores.get(child)) || 0)));
      if (maxChildScore <= 0) continue;
      const currentScore = Math.max(1, Number(adjustedScores.get(name)) || 1);
      const descendantCount = teLineageDescendants.get(name) || 0;
      const structuralBonus = 2.6 + Math.min(7.0, children.length * 0.4 + Math.log2(descendantCount + 1) * 0.84);
      const inverseSizeBoost = Math.max(1.2, 5.6 / Math.max(1, currentScore));
      const additiveBonus = structuralBonus * inverseSizeBoost;
      adjustedScores.set(name, Math.max(currentScore, maxChildScore + additiveBonus));
    }

    const line1TargetRadius = degreeToSize(teDatabaseDegrees.get('L1') || teDatabaseDegrees.get('LINE1') || 1) / 2;
    const line1AdjustedScore = Math.max(1, Number(adjustedScores.get('L1')) || 1);
    const scaleCoefficient = line1TargetRadius / line1AdjustedScore;

    for (const name of lineageNames) {
      teFixedRadii.set(name, Math.max(TE_MIN_RADIUS, (adjustedScores.get(name) || 1) * scaleCoefficient));
    }
  }

  function teFillColorForName(name) {
    const canonicalName = canonicalTeLineageName(name);
    const depth = teLineageDepths.get(canonicalName);
    const depths = [...teLineageDepths.values()].filter((value) => Number.isFinite(value));
    const minDepth = depths.length ? Math.min(...depths) : 0;
    const maxDepth = depths.length ? Math.max(...depths) : 1;
    const span = Math.max(1, maxDepth - minDepth);
    const normalizedDepth = Math.max(0, Math.min(1, (((Number.isFinite(depth) ? depth : maxDepth) - minDepth) / span)));
    return interpolateHexColor('#18357d', '#8fb0ff', normalizedDepth);
  }

  function teRadiusForName(name, fallbackDegree) {
    const canonicalName = canonicalTeLineageName(name);
    return teFixedRadii.get(canonicalName) || degreeToSize(fallbackDegree) / 2;
  }

  function teShouldShowLabel(node) {
    const canonicalName = canonicalTeLineageName(node?.rawLabel || node?.displayLabel);
    const depth = teLineageDepths.get(canonicalName);
    return (Number.isFinite(depth) && depth <= 2) || (Math.max(0, Number(node?.databaseDegree) || 0) > 10);
  }

  function teLabelFontSize(node) {
    const text = String(node?.displayLabel || node?.rawLabel || '').trim();
    const diameter = Math.max(16, Number(node?.size) || 16);
    if (!text) return 12;
    const estimated = (diameter - 10) / Math.max(3, text.length * 0.62);
    return Math.max(8, Math.min(16, estimated));
  }

  function secondaryShouldShowLabel(node) {
    const nodeType = String(node?.nodeType || '');
    if (nodeType === 'DiseaseClass') return true;
    if (nodeType === 'Function') {
      return Math.max(0, Number(node?.size) || 0) >= 34;
    }
    return false;
  }

  function secondaryLabelText(node) {
    const raw = String(node?.displayLabel || node?.rawLabel || '').trim();
    if (!raw) return '';
    return fitLabelToCircle(raw, Math.max(16, Number(node?.size) || 16));
  }

  function secondaryLabelFontSize(node) {
    const text = secondaryLabelText(node);
    const diameter = Math.max(16, Number(node?.size) || 16);
    if (!text) return 10;
    const estimated = (diameter - 8) / Math.max(3, text.length * 0.7);
    return Math.max(7, Math.min(12, estimated));
  }

  function interpolateHexColor(startHex, endHex, t) {
    const start = hexToRgb(startHex);
    const end = hexToRgb(endHex);
    const clamped = Math.max(0, Math.min(1, Number(t) || 0));
    const mix = (from, to) => Math.round(from + (to - from) * clamped);
    return `rgb(${mix(start.r, end.r)}, ${mix(start.g, end.g)}, ${mix(start.b, end.b)})`;
  }

  function darkenHexColor(hex, amount) {
    return interpolateHexColor(hex, '#0f172a', amount);
  }

  function diseaseClassDiameterFromMembers(memberNodes) {
    const members = Array.isArray(memberNodes) ? memberNodes.filter(Boolean) : [];
    if (members.length === 0) return 56;

    const radii = members.map((node) => Math.max(1, (Number(node.size) || 0) / 2));
    const maxRadius = Math.max(...radii);
    const sumRadius = radii.reduce((total, radius) => total + radius, 0);

    if (members.length === 1) {
      return maxRadius * 2;
    }

    const compressedRadius = Math.sqrt(sumRadius * maxRadius);
    const boundedRadius = Math.min(maxRadius * 1.25, compressedRadius);
    return boundedRadius * 2;
  }

  function buildTeam(node) {
    if (node.type === 'Disease') {
      const diseaseClass = String(node.disease_class || '').trim() || 'Disease';
      return `Disease::${diseaseClass}`;
    }
    if (node.type === 'TE') return 'TE';
    if (node.type === 'Function') return 'Function';
    return `${node.type || 'Node'}::${node.id}`;
  }

  function fitLabelToCircle(text, diameter) {
    const raw = String(text || '').trim();
    if (!raw) return '';
    const maxChars = Math.max(2, Math.floor((Math.max(0, Number(diameter) || 0) - 14) / 8.5));
    if (raw.length <= maxChars) return raw;
    if (maxChars <= 3) return raw.slice(0, maxChars);
    return `${raw.slice(0, maxChars - 1)}...`;
  }

  function hexToRgb(hex) {
    const raw = String(hex || '').trim();
    const rgbMatch = raw.match(/^rgb\(\s*(\d+),\s*(\d+),\s*(\d+)\s*\)$/i);
    if (rgbMatch) {
      return {
        r: Number(rgbMatch[1]),
        g: Number(rgbMatch[2]),
        b: Number(rgbMatch[3]),
      };
    }
    const value = raw.replace('#', '');
    if (value.length !== 6) return { r: 148, g: 163, b: 184 };
    return {
      r: parseInt(value.slice(0, 2), 16),
      g: parseInt(value.slice(2, 4), 16),
      b: parseInt(value.slice(4, 6), 16),
    };
  }

  function mixEdgeColor(sourceType, targetType, alpha) {
    const source = hexToRgb(TYPE_COLORS[sourceType] || '#94a3b8');
    const target = hexToRgb(TYPE_COLORS[targetType] || '#94a3b8');
    const mixed = {
      r: Math.round((source.r + target.r) / 2),
      g: Math.round((source.g + target.g) / 2),
      b: Math.round((source.b + target.b) / 2),
    };
    return `rgba(${mixed.r}, ${mixed.g}, ${mixed.b}, ${alpha})`;
  }

  function containsChinese(text) {
    return /[\u4e00-\u9fff]/.test(String(text || ''));
  }

  function normalizeTranslationMap(raw) {
    return raw && typeof raw === 'object' ? raw : {};
  }

  async function loadEnglishResources() {
    const [nameRes, teDescRes, entityDescRes, teLineageRes, teMetricsRes] = await Promise.allSettled([
      fetch('data/processed/entity_description_key_translation_cache.json', { credentials: 'same-origin' }),
      fetch('data/processed/te_descriptions.json', { credentials: 'same-origin' }),
      fetch('data/processed/entity_descriptions.json', { credentials: 'same-origin' }),
      fetch('data/processed/tree_te_lineage.json', { credentials: 'same-origin' }),
      fetch('api/te_metrics.php', { credentials: 'same-origin' }),
    ]);

    if (nameRes.status === 'fulfilled' && nameRes.value.ok) {
      nameTranslations = normalizeTranslationMap(await nameRes.value.json());
    }

    if (teDescRes.status === 'fulfilled' && teDescRes.value.ok) {
      const payload = await teDescRes.value.json();
      teDescriptions = { en: payload?.en || {} };
    }

    if (entityDescRes.status === 'fulfilled' && entityDescRes.value.ok) {
      const payload = await entityDescRes.value.json();
      entityDescriptions = {
        en: {
          Disease: payload?.en?.Disease || {},
          Function: payload?.en?.Function || {},
        },
      };
    }

    if (teLineageRes.status === 'fulfilled' && teLineageRes.value.ok) {
      const payload = await teLineageRes.value.json();
      teLineageDepths = new Map(
        (payload?.nodes || []).map((node) => [String(node?.name || '').trim(), Math.max(0, Number(node?.depth) || 0)]),
      );

      teLineageChildren = new Map();
      for (const edge of payload?.edges || []) {
        const parent = String(edge?.parent || '').trim();
        const child = String(edge?.child || '').trim();
        if (!parent || !child) continue;
        if (!teLineageChildren.has(parent)) teLineageChildren.set(parent, []);
        teLineageChildren.get(parent).push(child);
      }

      teLineageDescendants = new Map();
      const countDescendants = (name) => {
        if (teLineageDescendants.has(name)) return teLineageDescendants.get(name);
        const children = teLineageChildren.get(name) || [];
        let total = 0;
        for (const child of children) total += 1 + countDescendants(child);
        teLineageDescendants.set(name, total);
        return total;
      };

      for (const name of teLineageDepths.keys()) {
        countDescendants(name);
      }
    }

    if (teMetricsRes.status === 'fulfilled' && teMetricsRes.value.ok) {
      const payload = await teMetricsRes.value.json();
      teDatabaseDegrees = new Map(
        Object.entries(payload?.metrics || {}).map(([name, degree]) => [canonicalTeLineageName(name), Math.max(0, Number(degree) || 0)]),
      );
    }

    computeTeVisualMetrics();
  }

  function ensureResources() {
    if (!resourcesPromise) {
      resourcesPromise = loadEnglishResources().catch((error) => {
        console.warn('Failed to load English resources for G6 embed:', error);
      });
    }
    return resourcesPromise;
  }

  function translateName(rawLabel) {
    const raw = String(rawLabel || '').trim();
    if (!raw) return '';
    if (currentLang !== 'en') return raw;
    if (!containsChinese(raw)) return raw;
    return nameTranslations[raw] || raw;
  }

  function translateDescription(nodeType, rawLabel, rawDescription) {
    const label = String(rawLabel || '').trim();
    const description = String(rawDescription || '').trim();
    if (currentLang !== 'en') {
      return description || '';
    }
    if (nodeType === 'DiseaseClass') return description || 'Disease class node in the current graph.';
    if (nodeType === 'TE') {
      const key = translateName(label) || label;
      const mapped = String(teDescriptions?.en?.[key] || '').trim();
      if (mapped) return mapped;
    }
    if (nodeType === 'Disease' || nodeType === 'Function') {
      const key = translateName(label) || label;
      const mapped = String(entityDescriptions?.en?.[nodeType]?.[key] || '').trim();
      if (mapped) return mapped;
    }
    return description || '';
  }

  function buildGraphData(elements) {
    const nodes = [];
    const edges = [];
    const allowedNodeIds = new Set();
    let anchorNodeId = '';

    for (const item of elements || []) {
      const data = item && item.data ? item.data : null;
      if (!data) continue;
      if (data.source && data.target) continue;
      if ((data.type || 'TE') === 'Paper') continue;
      if (!anchorNodeId) anchorNodeId = String(data.id || '');

      const node = {
        id: data.id,
        size: degreeToSize(data.degree),
        nodeType: data.type || 'TE',
        rawLabel: data.rawLabel || data.label || data.id,
        displayLabel: translateName(data.label || data.rawLabel || data.id),
        databaseDegree: Math.max(0, Number(data.degree) || 0),
        description: translateDescription(data.type || 'TE', data.label || data.rawLabel || data.id, data.description || ''),
        diseaseClass: String(data.disease_class || ''),
        team: buildTeam(data),
        queryLabel: String(data.rawLabel || data.label || data.id),
        fillColor: TYPE_COLORS[data.type || 'TE'] || '#94a3b8',
        strokeColor: TYPE_STROKES[data.type || 'TE'] || '#111111',
      };

      nodes.push(node);
      allowedNodeIds.add(node.id);
    }

    const teNodes = nodes.filter((node) => node.nodeType === 'TE');
    for (const node of teNodes) {
      const canonicalName = canonicalTeLineageName(node.rawLabel || node.displayLabel);
      const fixedRadius = teRadiusForName(canonicalName, node.databaseDegree);
      node.size = fixedRadius * 2;
      node.fillColor = teFillColorForName(canonicalName);
      node.strokeColor = darkenHexColor(node.fillColor, 0.28);
    }

    const baseEdges = [];
    for (const item of elements || []) {
      const data = item && item.data ? item.data : null;
      if (!data || !data.source || !data.target) continue;
      if (!allowedNodeIds.has(data.source) || !allowedNodeIds.has(data.target)) continue;
      baseEdges.push({
        source: data.source,
        target: data.target,
      });
    }

    const connectedNodeIds = new Set();
    for (const edge of baseEdges) {
      connectedNodeIds.add(edge.source);
      connectedNodeIds.add(edge.target);
    }

    const nonIsolatedNodes = nodes.filter((node) => connectedNodeIds.has(node.id));
    const adjacency = new Map();
    for (const node of nonIsolatedNodes) {
      adjacency.set(node.id, []);
    }
    for (const edge of baseEdges) {
      if (!adjacency.has(edge.source) || !adjacency.has(edge.target)) continue;
      adjacency.get(edge.source).push(edge.target);
      adjacency.get(edge.target).push(edge.source);
    }

    const mainComponentNodeIds = new Set();
    const traversalStartId = adjacency.has(anchorNodeId) ? anchorNodeId : (nonIsolatedNodes[0]?.id || '');
    if (traversalStartId) {
      const queue = [traversalStartId];
      mainComponentNodeIds.add(traversalStartId);
      while (queue.length) {
        const currentId = queue.shift();
        for (const neighborId of adjacency.get(currentId) || []) {
          if (mainComponentNodeIds.has(neighborId)) continue;
          mainComponentNodeIds.add(neighborId);
          queue.push(neighborId);
        }
      }
    }

    const visibleNodes = nonIsolatedNodes.filter((node) => mainComponentNodeIds.has(node.id));
    const visibleNodeIds = new Set(visibleNodes.map((node) => node.id));
    const diseaseMembers = new Map();
    for (const node of visibleNodes) {
      if (node.nodeType !== 'Disease') continue;
      const diseaseClass = node.diseaseClass || 'Disease';
      if (!diseaseMembers.has(diseaseClass)) diseaseMembers.set(diseaseClass, []);
      diseaseMembers.get(diseaseClass).push(node);
    }

    for (const [diseaseClass, members] of diseaseMembers.entries()) {
      const count = members.length;
      const classNodeId = `disease-class::${diseaseClass}`;
      const classNode = {
        id: classNodeId,
        size: diseaseClassDiameterFromMembers(members),
        nodeType: 'DiseaseClass',
        rawLabel: diseaseClass,
        displayLabel: diseaseClass,
        databaseDegree: count,
        description: `Disease class node for ${diseaseClass}. Connected to ${count} disease node${count === 1 ? '' : 's'} in the current graph.`,
        diseaseClass,
        team: `Disease::${diseaseClass}`,
        queryLabel: '',
      };
      visibleNodes.push(classNode);
      visibleNodeIds.add(classNodeId);
    }

    for (const edge of baseEdges) {
      if (!visibleNodeIds.has(edge.source) || !visibleNodeIds.has(edge.target)) continue;
      edges.push({
        source: edge.source,
        target: edge.target,
      });
    }

    for (const [diseaseClass, members] of diseaseMembers.entries()) {
      const classNodeId = `disease-class::${diseaseClass}`;
      for (const member of members) {
        edges.push({
          source: classNodeId,
          target: member.id,
          synthetic: true,
        });
      }
    }

    return { nodes: visibleNodes, edges };
  }

  function resolveNode(edgeSide, nodes) {
    if (edgeSide && typeof edgeSide === 'object') return edgeSide;
    return nodes.find((node) => node.id === edgeSide) || null;
  }

  function getContainerMetrics() {
    const docEl = document.documentElement;
    const width = Math.max(
      container.clientWidth || 0,
      docEl ? docEl.clientWidth || 0 : 0,
      window.innerWidth || 0,
    );
    const height = Math.max(
      container.clientHeight || 0,
      docEl ? docEl.clientHeight || 0 : 0,
      window.innerHeight || 0,
    );
    return { width, height };
  }

  function waitForContainerSize(maxAttempts = 60, delayMs = 50) {
    return new Promise((resolve, reject) => {
      let attempts = 0;
      const check = () => {
        attempts += 1;
        const { width, height } = getContainerMetrics();
        if (width > 24 && height > 24) {
          resolve({ width, height });
          return;
        }
        if (attempts >= maxAttempts) {
          reject(new Error('G6 container has no size yet.'));
          return;
        }
        window.setTimeout(check, delayMs);
      };
      check();
    });
  }

  async function loadGraph(forcedQuery) {
    const query = String(forcedQuery || currentQuery || params.get('q') || 'LINE1').trim() || 'LINE1';
    currentQuery = query;
    params.set('q', query);
    params.set('key_level', String(currentKeyNodeLevel));
    params.set('fixed', fixedView ? '1' : '0');
    params.set('lang', currentLang);
    window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`);

    pushMode('dynamic', query);
    pushStatus(`Loading graph for ${query} (key-node level ${currentKeyNodeLevel}) ...`);

    try {
      await ensureResources();
        const metrics = await waitForContainerSize();
        if ((container.clientWidth || 0) < 25 && metrics.width > 0) {
          container.style.width = `${metrics.width}px`;
        }
        if ((container.clientHeight || 0) < 25 && metrics.height > 0) {
          container.style.height = `${metrics.height}px`;
        }

      const response = await fetch(`api/graph.php?q=${encodeURIComponent(query)}&key_level=${currentKeyNodeLevel}`, {
        credentials: 'same-origin',
      });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const payload = await response.json();
      const data = buildGraphData(payload.elements || []);

      if (graph && typeof graph.destroy === 'function') {
        graph.destroy();
      }

      graph = new Graph({
        container,
        autoFit: 'center',
        data,
        node: {
          style: {
            size: (d) => d.size,
            fill: (d) => d.fillColor || TYPE_COLORS[d.nodeType] || '#94a3b8',
            stroke: (d) => d.strokeColor || TYPE_STROKES[d.nodeType] || '#111111',
            lineWidth: 2,
            labelText: (d) => {
              if (d.nodeType === 'TE' && teShouldShowLabel(d)) {
                return d.displayLabel || d.rawLabel || '';
              }
              if (secondaryShouldShowLabel(d)) {
                return secondaryLabelText(d);
              }
              return '';
            },
            labelPlacement: 'center',
            labelFill: '#111111',
            labelFontSize: (d) => {
              if (d.nodeType === 'TE' && teShouldShowLabel(d)) {
                return teLabelFontSize(d);
              }
              if (secondaryShouldShowLabel(d)) {
                return secondaryLabelFontSize(d);
              }
              return 10;
            },
            labelFontWeight: 700,
            labelStroke: '#ffffff',
            labelLineWidth: 3,
            labelLineJoin: 'round',
          },
        },
        edge: {
          style: {
            stroke: (edge) => {
              const source = resolveNode(edge.source, data.nodes);
              const target = resolveNode(edge.target, data.nodes);
              const alpha = edge.synthetic ? 0.62 : 0.5;
              return mixEdgeColor(source?.nodeType, target?.nodeType, alpha);
            },
            lineWidth: (edge) => (edge.synthetic ? 1.9 : 1.5),
          },
        },
        layout: {
          type: 'd3-force',
          link: {
            distance: (edge) => {
              const source = resolveNode(edge.source, data.nodes);
              const target = resolveNode(edge.target, data.nodes);
              if (!source || !target) return 80;
              if (source.nodeType === 'DiseaseClass' || target.nodeType === 'DiseaseClass') return 120;
              if (source.team === target.team) return 96;
              if (source.nodeType === 'Disease' || target.nodeType === 'Disease') return 300;
              return 220;
            },
            strength: (edge) => {
              const source = resolveNode(edge.source, data.nodes);
              const target = resolveNode(edge.target, data.nodes);
              if (!source || !target) return 0.1;
              if (source.nodeType === 'DiseaseClass' || target.nodeType === 'DiseaseClass') return 0.92;
              if (source.team === target.team) return 0.5;
              if (source.nodeType === 'Disease' || target.nodeType === 'Disease') return 0.06;
              return 0.12;
            },
          },
          manyBody: {
            strength: (node) => {
              const size = typeof node.size === 'number' ? node.size : 16;
              if (node.nodeType === 'DiseaseClass') return -(240 + size * 4.6);
              return -(170 + size * 3.8);
            },
          },
          collide: {
            radius: (node) => {
              if (node.nodeType === 'DiseaseClass') return node.size / 2 + 46;
              if (node.nodeType === 'TE') return node.size / 2 + 38;
              if (node.nodeType === 'Disease') return node.size / 2 + 34;
              return node.size / 2 + 30;
            },
            strength: 1,
            iterations: 16,
          },
        },
        behaviors: [
          {
            type: 'drag-element-force',
            trigger: [],
            enable: (event) => event.targetType === 'node',
          },
          'zoom-canvas',
          'drag-canvas',
        ],
      });

      await graph.render();
      graph.off?.('node:click');
      graph.on('node:click', (event) => {
        const nodeId = event?.target?.id;
        const node = data.nodes.find((item) => item.id === nodeId);
        if (!node) return;
        pushSelection(node);
        pushDetail(node.displayLabel || node.rawLabel, node.description);
        if (!fixedView && node.nodeType !== 'DiseaseClass' && node.queryLabel) {
          loadGraph(node.queryLabel);
        }
      });

      pushSelection(null);
      pushDetail('No node selected', 'Click a node to inspect its name and description.');
      pushStatus(`Loaded ${data.nodes.length} nodes and ${data.edges.length} edges for ${query} at key-node level ${currentKeyNodeLevel}.`);
      return payload;
    } catch (error) {
      pushStatus(`Failed: ${error && error.message ? error.message : 'unknown error'}`);
      console.error('G6 embed graph failed:', error);
      throw error;
    }
  }

  function resize() {
    const metrics = getContainerMetrics();
    if ((container.clientWidth || 0) < 25 && metrics.width > 0) {
      container.style.width = `${metrics.width}px`;
    }
    if ((container.clientHeight || 0) < 25 && metrics.height > 0) {
      container.style.height = `${metrics.height}px`;
    }
    if (graph && typeof graph.resize === 'function') {
      graph.resize();
    }
  }

  const bridge = {
    loadGraph(query) {
      return loadGraph(query);
    },
    setFixedView(next) {
      fixedView = !!next;
      params.set('fixed', fixedView ? '1' : '0');
      return Promise.resolve(fixedView);
    },
    setKeyNodeLevel(level) {
      currentKeyNodeLevel = Math.max(1, Math.min(10, Number(level) || 1));
      params.set('key_level', String(currentKeyNodeLevel));
      if (!currentQuery) return Promise.resolve();
      return loadGraph(currentQuery);
    },
    setLanguage(lang) {
      currentLang = lang === 'zh' ? 'zh' : 'en';
      params.set('lang', currentLang);
      if (!currentQuery) return Promise.resolve();
      return loadGraph(currentQuery);
    },
    resize() {
      resize();
      return Promise.resolve();
    },
    getCurrentQuery() {
      return currentQuery;
    },
    getFixedView() {
      return fixedView;
    },
    getKeyNodeLevel() {
      return currentKeyNodeLevel;
    },
  };

  window.__TEKG_G6_EMBED = bridge;
  window.addEventListener('resize', resize);

  ensureResources()
    .catch((error) => {
      console.warn('Failed to load English resources for G6 embed:', error);
    })
    .finally(async () => {
      pushReady();
      if (currentQuery) {
        try {
          await loadGraph(currentQuery);
        } catch (_error) {}
      }
    });
}());
