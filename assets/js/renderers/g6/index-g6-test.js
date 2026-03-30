(function () {
  const G6Lib = window.G6;
  if (!G6Lib || !G6Lib.Graph) return;

  const { Graph } = G6Lib;
  const container = document.getElementById('container');
  const queryInput = document.getElementById('query-input');
  const loadBtn = document.getElementById('load-btn');
  const status = document.getElementById('status');
  const TYPE_COLORS = {
    TE: '#4e79ff',
    Disease: '#ff7a7a',
    Function: '#41b883',
    Paper: '#f2a93b',
  };
  const TYPE_STROKES = {
    TE: '#1f3f99',
    Disease: '#c84f62',
    Function: '#2f8b63',
    Paper: '#b77a16',
  };

  let graph = null;

  function setStatus(text) {
    if (status) status.textContent = text;
  }

  function getQuery() {
    const url = new URL(window.location.href);
    return (url.searchParams.get('q') || queryInput?.value || 'LINE1').trim() || 'LINE1';
  }

  function degreeToSize(degree) {
    const safeDegree = Math.max(1, Number(degree) || 1);
    return Math.max(10, Math.min(64, 10 + Math.log2(safeDegree + 1) * 7.5));
  }

  function fitLabelToCircle(text, diameter) {
    const raw = String(text || '').trim();
    if (!raw) return '';
    const maxChars = Math.max(2, Math.floor((Math.max(0, Number(diameter) || 0) - 14) / 8.5));
    if (raw.length <= maxChars) return raw;
    if (maxChars <= 3) return raw.slice(0, maxChars);
    return `${raw.slice(0, maxChars - 1)}…`;
  }

  function buildGraphData(elements) {
    const nodes = [];
    const edges = [];
    const allowedNodeIds = new Set();

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
      };
      nodes.push(node);
      allowedNodeIds.add(node.id);
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

    return { nodes, edges };
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
            labelText: (d) => ((d.databaseDegree || 0) > 10 ? fitLabelToCircle(d.rawLabel, d.size) : ''),
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
            stroke: 'rgba(78, 121, 255, 0.45)',
            lineWidth: 1.5,
          },
        },
        layout: {
          type: 'd3-force',
          collide: {
            radius: (d) => d.size / 2 + 10,
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
