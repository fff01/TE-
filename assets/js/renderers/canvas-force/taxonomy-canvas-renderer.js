(function () {
  'use strict';

  const surface = document.getElementById('taxonomy-canvas-surface');
  const canvas = document.getElementById('taxonomy-canvas');
  const status = document.getElementById('taxonomy-canvas-status');
  const tooltip = document.getElementById('taxonomy-canvas-tooltip');
  if (!surface || !canvas) return;

  const ctx = canvas.getContext('2d');
  const TAU = Math.PI * 2;
  const STAR_DEPTH = 2;
  const LABELS = ['Human TE', 'Class', 'Order', 'Superfamily', 'Family', 'Subfamily'];
  const COLORS = ['#123d7a', '#1f66d1', '#2d8bdc', '#54a6db', '#86bfe8', '#b7d7f1', '#d4e6f7'];
  const state = {
    nodes: [], edges: [], byId: new Map(), adjacency: new Map(),
    visibleLevels: new Set(), focus: null, hover: null, selected: null,
    transform: { x: 0, y: 0, k: 1 },
    dragging: null, panning: false, pointer: null,
    frame: 0, running: false, paused: true, alpha: 1,
    source: '', requestEpoch: 0, requestController: null,
  };

  function keyForDepth(depth) { return `depth-${depth}`; }
  function levelLabel(depth) { return LABELS[depth] || `Level ${depth}`; }
  function levelColor(depth) { return COLORS[Math.min(depth, COLORS.length - 1)] || '#94a3b8'; }
  function nodeId(name, index) {
    const slug = String(name || '').replace(/[^A-Za-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
    return `taxonomy_${slug || 'node'}_${index}`;
  }
  function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, (character) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[character]));
  }
  function setStatus(value) {
    if (!status) return;
    status.textContent = String(value || '');
    status.hidden = !value;
  }

  function parse(payload) {
    const rawNodes = Array.isArray(payload?.nodes) ? payload.nodes : [];
    const rawEdges = Array.isArray(payload?.edges) ? payload.edges : [];
    const children = new Map();
    const parentByChild = new Map();
    const depthByName = new Map();
    rawNodes.forEach((raw) => {
      const name = String(raw?.name || '').trim();
      if (name) depthByName.set(name, Math.max(0, Number(raw?.depth) || 0));
    });
    rawEdges.forEach((raw) => {
      const parentName = String(raw?.parent || '').trim();
      const childName = String(raw?.child || '').trim();
      if (!parentName || !childName) return;
      if (!children.has(parentName)) children.set(parentName, []);
      children.get(parentName).push(childName);
      parentByChild.set(childName, parentName);
    });

    const rootChildren = children.get('TE')
      || children.get('Human TE')
      || children.get('Transposable Elements - Human')
      || children.get('Transposable Elements (Mobile element) - Human')
      || [];
    const branchIndex = new Map(rootChildren.map((name, index) => [name, index]));
    const starNames = rawNodes
      .map((raw) => String(raw?.name || '').trim())
      .filter((name) => (depthByName.get(name) || 0) === STAR_DEPTH);
    const starIndex = new Map(starNames.map((name, index) => [name, index]));

    function ancestorAtDepth(name, depth) {
      let current = name;
      const seen = new Set();
      while (current && !seen.has(current)) {
        seen.add(current);
        if (depthByName.get(current) === depth) return current;
        current = parentByChild.get(current);
      }
      return '';
    }

    function topBranch(name) {
      let current = name;
      const seen = new Set();
      while (current && !seen.has(current)) {
        seen.add(current);
        if (branchIndex.has(current)) return branchIndex.get(current);
        current = parentByChild.get(current);
      }
      return 0;
    }

    const nodes = rawNodes.map((raw, index) => {
      const name = String(raw?.name || '').trim();
      const depth = Math.max(0, Number(raw?.depth) || 0);
      const childCount = (children.get(name) || []).length;
      const starName = depth <= STAR_DEPTH ? name : ancestorAtDepth(name, STAR_DEPTH);
      const branch = starIndex.has(starName) ? starIndex.get(starName) : topBranch(name);
      const starTotal = Math.max(1, starNames.length || rootChildren.length || 6);
      const starAngle = branch / starTotal * TAU;
      const starRing = 290 + (branch % 4) * 105;
      const starX = Math.cos(starAngle) * starRing;
      const starY = Math.sin(starAngle) * starRing;
      const localAngle = (index * 2.399963229728653) % TAU + Math.random() * 0.3;
      const localRadius = depth <= STAR_DEPTH ? 0 : 34 + (depth - STAR_DEPTH) * 25 + Math.random() * 38;
      const size = depth === 0
        ? 18
        : depth === STAR_DEPTH
          ? Math.max(8, 12 + Math.min(7, Math.sqrt(childCount + 1)))
          : Math.max(1.8, 7.2 - (depth - STAR_DEPTH) * 0.85 + Math.min(2.2, Math.sqrt(childCount + 1) * 0.25));
      return {
        id: nodeId(name, index), name, depth, childCount, degree: 0, branch,
        starName, starId: '', starX, starY,
        x: starX + Math.cos(localAngle) * localRadius,
        y: starY + Math.sin(localAngle) * localRadius,
        vx: 0, vy: 0, radius: size,
      };
    }).filter((node) => node.name);
    const byName = new Map(nodes.map((node) => [node.name, node]));
    nodes.forEach((node) => {
      const star = byName.get(node.starName);
      node.starId = star ? star.id : node.id;
    });
    const edges = rawEdges.map((raw) => {
      const source = byName.get(String(raw?.parent || '').trim());
      const target = byName.get(String(raw?.child || '').trim());
      if (!source || !target) return null;
      source.degree += 1;
      target.degree += 1;
      return { source, target };
    }).filter(Boolean);
    nodes.forEach((node) => {
      node.radius = Math.max(2.5, node.radius + Math.min(5, Math.sqrt(node.degree) * 0.45));
    });
    return { nodes, edges };
  }

  function rebuildIndexes() {
    state.byId = new Map(state.nodes.map((node) => [node.id, node]));
    state.adjacency = new Map(state.nodes.map((node) => [node.id, new Set()]));
    state.edges.forEach((edge) => {
      state.adjacency.get(edge.source.id)?.add(edge.target.id);
      state.adjacency.get(edge.target.id)?.add(edge.source.id);
    });
  }
  function isVisible(node) { return state.visibleLevels.has(node.depth); }
  function isNearActive(node) {
    const active = state.selected || state.hover;
    return !active || node.id === active || state.adjacency.get(active)?.has(node.id);
  }
  function screenPoint(node) {
    return { x: node.x * state.transform.k + state.transform.x, y: node.y * state.transform.k + state.transform.y };
  }
  function worldPoint(x, y) {
    return { x: (x - state.transform.x) / state.transform.k, y: (y - state.transform.y) / state.transform.k };
  }

  function drawLabel(node, point) {
    const label = node.name;
    ctx.save();
    ctx.font = `${node.depth === 0 ? 700 : 600} ${node.depth === 0 ? 15 : 12}px "Segoe UI", sans-serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    const width = ctx.measureText(label).width + 10;
    const y = point.y + Math.max(3, node.radius * state.transform.k) + 6;
    ctx.fillStyle = 'rgba(255,255,255,.9)';
    ctx.fillRect(point.x - width / 2, y - 2, width, 18);
    ctx.fillStyle = node.depth === 0 ? '#123d7a' : '#1e293b';
    ctx.fillText(label, point.x, y);
    ctx.restore();
  }

  function draw() {
    const rect = surface.getBoundingClientRect();
    ctx.clearRect(0, 0, rect.width, rect.height);
    const active = state.selected || state.hover;
    state.edges.forEach((edge) => {
      if (!isVisible(edge.source) || !isVisible(edge.target)) return;
      const source = screenPoint(edge.source);
      const target = screenPoint(edge.target);
      const emphasized = active && (edge.source.id === active || edge.target.id === active);
      const inFocusedLevel = state.focus === null
        || keyForDepth(edge.source.depth) === state.focus
        || keyForDepth(edge.target.depth) === state.focus;
      ctx.beginPath();
      ctx.moveTo(source.x, source.y);
      ctx.lineTo(target.x, target.y);
      ctx.strokeStyle = emphasized
        ? 'rgba(37,99,235,.75)'
        : inFocusedLevel ? 'rgba(100,116,139,.18)' : 'rgba(100,116,139,.035)';
      ctx.lineWidth = emphasized ? 1.8 : 0.8;
      ctx.stroke();
    });
    state.nodes.forEach((node) => {
      if (!isVisible(node)) return;
      const point = screenPoint(node);
      const inFocusedLevel = state.focus === null || keyForDepth(node.depth) === state.focus;
      const near = isNearActive(node);
      const alpha = (inFocusedLevel && near) ? 1 : active || state.focus ? 0.16 : node.depth > 2 ? 0.62 : 0.92;
      ctx.globalAlpha = alpha;
      ctx.beginPath();
      ctx.arc(point.x, point.y, Math.max(1.5, node.radius * state.transform.k), 0, TAU);
      ctx.fillStyle = levelColor(node.depth);
      ctx.fill();
      ctx.globalAlpha = 1;
      if (node.depth <= 2 || node.id === active) drawLabel(node, point);
    });
  }

  function applyForces() {
    const alpha = state.alpha;
    const visible = state.nodes.filter((node) => isVisible(node));
    state.edges.forEach((edge) => {
      if (!isVisible(edge.source) || !isVisible(edge.target)) return;
      const dx = edge.target.x - edge.source.x;
      const dy = edge.target.y - edge.source.y;
      const dist = Math.max(1, Math.hypot(dx, dy));
      const sameGalaxy = edge.source.starId === edge.target.starId;
      const target = edge.target.depth <= STAR_DEPTH ? 135 : edge.target.depth >= 5 ? 16 : sameGalaxy ? 30 : 90;
      const strength = edge.target.depth > STAR_DEPTH ? 0.038 : 0.012;
      const force = (dist - target) * strength * alpha;
      const fx = dx / dist * force;
      const fy = dy / dist * force;
      if (edge.source !== state.dragging) {
        edge.source.vx += fx;
        edge.source.vy += fy;
      }
      if (edge.target !== state.dragging) {
        edge.target.vx -= fx;
        edge.target.vy -= fy;
      }
    });

    const stars = visible.filter((node) => node.depth <= STAR_DEPTH);
    for (let i = 0; i < stars.length; i += 1) {
      for (let j = i + 1; j < stars.length; j += 1) {
        const left = stars[i];
        const right = stars[j];
        const dx = left.x - right.x;
        const dy = left.y - right.y;
        const distanceSquared = Math.max(100, dx * dx + dy * dy);
        const distance = Math.sqrt(distanceSquared);
        const force = (left.depth === STAR_DEPTH && right.depth === STAR_DEPTH ? 9000 : 2600) / distanceSquared * alpha;
        if (left !== state.dragging) {
          left.vx += dx / distance * force;
          left.vy += dy / distance * force;
        }
        if (right !== state.dragging) {
          right.vx -= dx / distance * force;
          right.vy -= dy / distance * force;
        }
      }
    }

    const byGalaxy = new Map();
    visible.forEach((node) => {
      if (node.depth <= STAR_DEPTH) return;
      if (!byGalaxy.has(node.starId)) byGalaxy.set(node.starId, []);
      byGalaxy.get(node.starId).push(node);
    });
    byGalaxy.forEach((group) => {
      for (let i = 0; i < group.length; i += 1) {
        for (let j = i + 1; j < group.length; j += 1) {
          const left = group[i];
          const right = group[j];
          const dx = left.x - right.x;
          const dy = left.y - right.y;
          const distanceSquared = Math.max(12, dx * dx + dy * dy);
          if (distanceSquared > 3600) continue;
          const distance = Math.sqrt(distanceSquared);
          const force = 28 / distanceSquared * alpha;
          if (left !== state.dragging) {
            left.vx += dx / distance * force;
            left.vy += dy / distance * force;
          }
          if (right !== state.dragging) {
            right.vx -= dx / distance * force;
            right.vy -= dy / distance * force;
          }
        }
      }
    });

    visible.forEach((node) => {
      if (node === state.dragging) return;
      if (node.depth === STAR_DEPTH) {
        node.vx += (node.starX - node.x) * 0.018 * alpha;
        node.vy += (node.starY - node.y) * 0.018 * alpha;
      } else if (node.depth <= 1) {
        node.vx += -node.x * 0.018 * alpha;
        node.vy += -node.y * 0.018 * alpha;
      } else {
        const star = state.byId.get(node.starId);
        const anchorX = star ? star.x : node.starX;
        const anchorY = star ? star.y : node.starY;
        const dx = anchorX - node.x;
        const dy = anchorY - node.y;
        const distance = Math.max(1, Math.hypot(dx, dy));
        const preferred = 42 + (node.depth - STAR_DEPTH) * 24;
        const force = (distance - preferred) * 0.014 * alpha;
        node.vx += dx / distance * force;
        node.vy += dy / distance * force;
      }
      node.vx *= node.depth > STAR_DEPTH ? 0.82 : 0.88;
      node.vy *= node.depth > STAR_DEPTH ? 0.82 : 0.88;
      node.x += node.vx;
      node.y += node.vy;
    });
    state.alpha *= 0.985;
    if (state.alpha < 0.015) state.running = false;
  }

  function animate() {
    if (!state.running || state.paused) return;
    for (let index = 0; index < 2; index += 1) applyForces();
    draw();
    if (state.running) state.frame = requestAnimationFrame(animate);
  }

  function reheat(alpha = 1) {
    state.alpha = Math.max(state.alpha, alpha);
    state.paused = false;
    if (!state.running) {
      state.running = true;
      cancelAnimationFrame(state.frame);
      state.frame = requestAnimationFrame(animate);
    }
  }

  function fit() {
    const visible = state.nodes.filter(isVisible);
    if (!visible.length) return;
    const rect = surface.getBoundingClientRect();
    const xs = visible.map((node) => node.x);
    const ys = visible.map((node) => node.y);
    const minX = Math.min(...xs); const maxX = Math.max(...xs);
    const minY = Math.min(...ys); const maxY = Math.max(...ys);
    const scale = Math.max(0.14, Math.min(2, Math.min((rect.width - 80) / Math.max(1, maxX - minX), (rect.height - 80) / Math.max(1, maxY - minY))));
    state.transform = {
      k: scale,
      x: rect.width / 2 - (minX + maxX) / 2 * scale,
      y: rect.height / 2 - (minY + maxY) / 2 * scale,
    };
    draw();
  }

  function resize() {
    const rect = surface.getBoundingClientRect();
    const ratio = Math.max(1, Math.min(2, window.devicePixelRatio || 1));
    canvas.width = Math.max(1, Math.round(rect.width * ratio));
    canvas.height = Math.max(1, Math.round(rect.height * ratio));
    canvas.style.width = `${rect.width}px`;
    canvas.style.height = `${rect.height}px`;
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    draw();
  }

  function getLegendMeta() {
    const counts = new Map();
    state.nodes.forEach((node) => counts.set(node.depth, (counts.get(node.depth) || 0) + 1));
    return [...counts.entries()].sort((a, b) => a[0] - b[0]).map(([depth, count]) => ({
      key: keyForDepth(depth), depth, label: levelLabel(depth), color: levelColor(depth), count,
    }));
  }
  function applyLevelState(nextState) {
    if (nextState && typeof nextState === 'object') {
      Object.entries(nextState).forEach(([key, visible]) => {
        const match = /^depth-(\d+)$/.exec(key);
        if (!match) return;
        const depth = Number(match[1]);
        if (visible === false) state.visibleLevels.delete(depth);
        else state.visibleLevels.add(depth);
      });
    }
    state.hover = null;
    state.selected = null;
    draw();
    return Object.fromEntries(getDepths(state.nodes).map((depth) => [keyForDepth(depth), state.visibleLevels.has(depth)]));
  }
  function setLevelFocus(key) {
    state.focus = key && getLegendMeta().some((item) => item.key === key) ? key : null;
    draw();
    return state.focus;
  }
  function pause() {
    state.paused = true;
    state.running = false;
    cancelAnimationFrame(state.frame);
  }
  function resume() {
    state.paused = false;
    if (state.alpha >= 0.015) reheat(state.alpha);
    else draw();
  }

  async function render(options = {}) {
    const source = String(options.source || 'rmsk_repbase') === 'all' ? 'all' : 'rmsk_repbase';
    const epoch = ++state.requestEpoch;
    if (state.requestController) state.requestController.abort();
    const controller = new AbortController();
    state.requestController = controller;
    const timeout = window.setTimeout(() => controller.abort(), 6000);
    state.focus = null;
    setStatus('Loading taxonomy...');
    try {
      const url = window.__TEKG_PATHS.apiUrl(`taxonomy.php?view=tree&source=${encodeURIComponent(source)}`);
      const response = await fetch(url, { credentials: 'same-origin', signal: controller.signal });
      if (!response.ok) throw new Error(`Taxonomy API HTTP ${response.status}`);
      const payload = await response.json();
      if (epoch !== state.requestEpoch) return false;
      const parsed = parse(payload);
      if (!parsed.nodes.length) throw new Error('Taxonomy API returned no nodes');
      state.nodes = parsed.nodes;
      state.edges = parsed.edges;
      state.source = source;
      state.visibleLevels = new Set(getDepths(state.nodes));
      rebuildIndexes();
      resize();
      fit();
      state.alpha = 1;
      reheat(1);
      setStatus(`${state.nodes.length} nodes | ${state.edges.length} edges`);
      return true;
    } catch (error) {
      if (epoch !== state.requestEpoch) return false;
      const message = error?.name === 'AbortError' ? 'Taxonomy request timed out' : (error?.message || String(error));
      setStatus(`Failed: ${message}`);
      throw new Error(message);
    } finally {
      window.clearTimeout(timeout);
      if (state.requestController === controller) state.requestController = null;
    }
  }
  function getDepths(nodes) { return [...new Set(nodes.map((node) => node.depth))].sort((a, b) => a - b); }

  function hit(clientX, clientY) {
    const rect = canvas.getBoundingClientRect();
    let best = null; let distance = Infinity;
    state.nodes.forEach((node) => {
      if (!isVisible(node)) return;
      const point = screenPoint(node);
      const current = Math.hypot(point.x - (clientX - rect.left), point.y - (clientY - rect.top));
      if (current < Math.max(8, node.radius * state.transform.k + 5) && current < distance) { best = node; distance = current; }
    });
    return best;
  }
  function showTooltip(node, event) {
    if (!tooltip || !node) { if (tooltip) tooltip.hidden = true; return; }
    const rect = surface.getBoundingClientRect();
    tooltip.innerHTML = `<strong>${escapeHtml(node.name)}</strong><br>${escapeHtml(levelLabel(node.depth))}<br>Children: ${node.childCount}`;
    tooltip.style.left = `${Math.min(rect.width - 180, Math.max(6, event.clientX - rect.left + 12))}px`;
    tooltip.style.top = `${Math.min(rect.height - 70, Math.max(6, event.clientY - rect.top + 12))}px`;
    tooltip.hidden = false;
  }

  canvas.addEventListener('pointerdown', (event) => {
    const rect = canvas.getBoundingClientRect();
    const node = hit(event.clientX, event.clientY);
    state.dragging = node;
    state.panning = !node;
    state.pointer = { x: event.clientX - rect.left, y: event.clientY - rect.top };
    state.selected = node?.id || null;
    canvas.classList.add('is-dragging');
    canvas.setPointerCapture(event.pointerId);
    draw();
  });
  canvas.addEventListener('pointermove', (event) => {
    const rect = canvas.getBoundingClientRect();
    const point = { x: event.clientX - rect.left, y: event.clientY - rect.top };
    if (state.dragging) {
      Object.assign(state.dragging, worldPoint(point.x, point.y), { vx: 0, vy: 0 });
      reheat(0.18);
      draw(); return;
    }
    if (state.panning && state.pointer) {
      state.transform.x += point.x - state.pointer.x;
      state.transform.y += point.y - state.pointer.y;
      state.pointer = point;
      draw(); return;
    }
    const node = hit(event.clientX, event.clientY);
    state.hover = node?.id || null;
    showTooltip(node, event);
    draw();
  });
  function endPointer(event) {
    state.dragging = null; state.panning = false; state.pointer = null; state.selected = null;
    canvas.classList.remove('is-dragging');
    try { canvas.releasePointerCapture(event.pointerId); } catch (_error) {}
    reheat(0.12);
  }
  canvas.addEventListener('pointerup', endPointer);
  canvas.addEventListener('pointercancel', endPointer);
  canvas.addEventListener('pointerleave', () => { state.hover = null; showTooltip(null); draw(); });
  canvas.addEventListener('wheel', (event) => {
    event.preventDefault();
    const rect = canvas.getBoundingClientRect();
    const x = event.clientX - rect.left; const y = event.clientY - rect.top;
    const before = worldPoint(x, y);
    state.transform.k = Math.max(0.12, Math.min(5, state.transform.k * (event.deltaY < 0 ? 1.12 : 0.89)));
    state.transform.x = x - before.x * state.transform.k;
    state.transform.y = y - before.y * state.transform.k;
    draw();
  }, { passive: false });

  window.addEventListener('resize', resize);
  window.__TEKG_CANVAS_TAXONOMY = {
    render,
    applyLevelState,
    setLevelFocus,
    getLegendMeta,
    pause,
    resume,
    resize,
  };
}());
