(function () {
  const G6Lib = window.G6;
  if (!G6Lib || !G6Lib.Graph) return;

  const { Graph } = G6Lib;
  const container = document.getElementById('container');
  const queryInput = document.getElementById('query-input');
  const loadBtn = document.getElementById('load-btn');
  const status = document.getElementById('status');
  const detail = document.getElementById('detail');

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

  function setStatus(text) {
    if (status) status.textContent = text;
  }

  function escapeHtml(text) {
    return String(text || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function setDetail(title, description) {
    if (!detail) return;
    detail.innerHTML = `<strong>${escapeHtml(title)}</strong>${escapeHtml(description || 'No description.')}`;
  }

  function getQuery() {
    const url = new URL(window.location.href);
    return (url.searchParams.get('q') || queryInput?.value || 'LINE1').trim() || 'LINE1';
  }

  function degreeToSize(degree) {
    const safeDegree = Math.max(1, Number(degree) || 1);
    return Math.max(10, Math.min(64, 10 + Math.log2(safeDegree + 1) * 7.5));
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
    const value = String(hex || '').replace('#', '');
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

  function buildGraphData(elements) {
    const nodes = [];
    const edges = [];
    const allowedNodeIds = new Set();
    const diseaseCounts = new Map();
    const diseaseMembers = new Map();

    for (const item of elements || []) {
      const data = item && item.data ? item.data : null;
      if (!data) continue;
      if (data.source && data.target) continue;
      if ((data.type || 'TE') === 'Paper') continue;

      const node = {
        id: data.id,
        size: degreeToSize(data.degree),
        nodeType: data.type || 'TE',
        rawLabel: data.label || data.id,
        databaseDegree: Math.max(0, Number(data.degree) || 0),
        description: String(data.description || ''),
        diseaseClass: String(data.disease_class || ''),
        team: buildTeam(data),
      };

      nodes.push(node);
      allowedNodeIds.add(node.id);

      if (node.nodeType === 'Disease') {
        const diseaseClass = node.diseaseClass || 'Disease';
        diseaseCounts.set(diseaseClass, (diseaseCounts.get(diseaseClass) || 0) + 1);
        if (!diseaseMembers.has(diseaseClass)) diseaseMembers.set(diseaseClass, []);
        diseaseMembers.get(diseaseClass).push(node.id);
      }
    }

    for (const [diseaseClass, count] of diseaseCounts.entries()) {
      const classNodeId = `disease-class::${diseaseClass}`;
      const classNode = {
        id: classNodeId,
        size: Math.max(76, Math.min(118, 76 + Math.log2(count + 1) * 18)),
        nodeType: 'DiseaseClass',
        rawLabel: diseaseClass,
        databaseDegree: count,
        description: `Disease class node for ${diseaseClass}. Connected to ${count} disease node${count === 1 ? '' : 's'} in the current graph.`,
        diseaseClass,
        team: `Disease::${diseaseClass}`,
      };
      nodes.push(classNode);
      allowedNodeIds.add(classNodeId);
    }

    for (const item of elements || []) {
      const data = item && item.data ? item.data : null;
      if (!data || !data.source || !data.target) continue;
      if (!allowedNodeIds.has(data.source) || !allowedNodeIds.has(data.target)) continue;
      edges.push({
        source: data.source,
        target: data.target,
      });
    }

    for (const [diseaseClass, memberIds] of diseaseMembers.entries()) {
      const classNodeId = `disease-class::${diseaseClass}`;
      for (const memberId of memberIds) {
        edges.push({
          source: classNodeId,
          target: memberId,
          synthetic: true,
        });
      }
    }

    return { nodes, edges };
  }

  function resolveNode(edgeSide, nodes) {
    if (edgeSide && typeof edgeSide === 'object') return edgeSide;
    return nodes.find((node) => node.id === edgeSide) || null;
  }

  async function loadGraph() {
    const query = getQuery();
    if (queryInput) queryInput.value = query;
    setStatus(`Loading one-hop graph for ${query} ...`);

    try {
      const response = await fetch(`api/graph.php?q=${encodeURIComponent(query)}&key_level=1`, {
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
            fill: (d) => TYPE_COLORS[d.nodeType] || '#94a3b8',
            stroke: (d) => TYPE_STROKES[d.nodeType] || '#111111',
            lineWidth: 2,
            labelText: (d) =>
              d.nodeType === 'TE' && (d.databaseDegree || 0) > 10
                  ? fitLabelToCircle(d.rawLabel, d.size)
                  : '',
            labelPlacement: 'center',
            labelFill: '#111111',
            labelFontSize: 16,
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
              if (source.nodeType === 'DiseaseClass' || target.nodeType === 'DiseaseClass') return 56;
              if (source.team === target.team) return 48;
              if (source.nodeType === 'Disease' || target.nodeType === 'Disease') return 170;
              return 110;
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
              if (node.nodeType === 'DiseaseClass') return -(90 + size * 2.4);
              return -(55 + size * 1.8);
            },
          },
          collide: {
            radius: (node) => node.size / 2 + 10,
            strength: 1,
            iterations: 6,
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
        setDetail(node.rawLabel, node.description);
      });
      setDetail('No node selected', 'Click a node to inspect its name and description.');
      setStatus(`Loaded ${data.nodes.length} nodes and ${data.edges.length} edges for ${query}.`);
    } catch (error) {
      setStatus(`Failed: ${error && error.message ? error.message : 'unknown error'}`);
      console.error('G6 test graph failed:', error);
    }
  }

  if (loadBtn) {
    loadBtn.addEventListener('click', loadGraph);
  }

  if (queryInput) {
    queryInput.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') loadGraph();
    });
  }

  loadGraph();
}());
