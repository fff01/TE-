
(function () {
  const initialElements = JSON.parse(JSON.stringify((window.GRAPH_DEMO_DATA && window.GRAPH_DEMO_DATA.elements) || []));
  const graphEl = document.getElementById('search-graph');
  const detailEl = document.getElementById('search-graph-detail');
  const resultEl = document.getElementById('search-best-match');
  const repbaseEl = document.getElementById('search-repbase');
  const resetBtn = document.getElementById('search-reset');
  const searchForm = document.getElementById('search-form');
  const queryInput = document.getElementById('search-query');
  let repbaseDataPromise = null;
  const colorMap = { TE: '#2563eb', Disease: '#ef4444', Function: '#10b981', Paper: '#f59e0b' };
  const typeMap = { TE: '转座元件', Disease: '疾病', Function: '功能/机制', Paper: '文献' };
  const relMap = {
    SUBFAMILY_OF: '包含',
    EVIDENCE_RELATION: '文献支持',
    '与…相关': '与…相关',
    '促进': '促进',
    '参与': '参与',
    '调控': '调控',
    '影响': '影响',
    '执行': '执行',
    '介导': '介导',
    '报道': '报道'
  };

  const cy = cytoscape({
    container: graphEl,
    elements: initialElements,
    wheelSensitivity: 0.2,
    style: [
      { selector: 'node', style: {
        'label': 'data(label)',
        'font-size': 12,
        'min-zoomed-font-size': 9,
        'text-valign': 'center',
        'text-halign': 'center',
        'background-color': function (ele) { return colorMap[ele.data('type')] || '#94a3b8'; },
        'color': '#0f172a',
        'text-outline-width': 3,
        'text-outline-color': '#fff',
        'width': 'label',
        'height': 'label',
        'padding': '12px',
        'text-wrap': 'wrap',
        'text-max-width': 170,
        'border-width': 2,
        'border-color': '#fff',
        'shape': 'round-rectangle'
      }},
      { selector: 'edge', style: {
        'width': 2.4,
        'line-color': '#4a6fe3',
        'target-arrow-color': '#4a6fe3',
        'target-arrow-shape': 'triangle',
        'curve-style': 'bezier',
        'label': function (ele) { return ele.data('relation') === 'EVIDENCE_RELATION' ? '' : (relMap[ele.data('relation')] || ele.data('relation') || ''); },
        'font-size': '10px',
        'color': '#334155',
        'text-background-color': 'rgba(255,255,255,0.92)',
        'text-background-opacity': 1,
        'text-background-padding': '2px',
        'text-rotation': 'autorotate'
      }},
      { selector: '.active-node', style: {
        'border-width': 5,
        'border-color': '#0f172a',
        'shadow-blur': 14,
        'shadow-color': '#2563eb',
        'shadow-opacity': 0.24
      }},
      { selector: '.active-edge', style: {
        'width': 4,
        'line-color': '#1d4ed8',
        'target-arrow-color': '#1d4ed8'
      }}
    ],
    layout: { name: 'preset', fit: true, padding: 50, animate: false }
  });

  function clearActive() {
    cy.nodes().removeClass('active-node');
    cy.edges().removeClass('active-edge');
  }

  function spreadNodesIfNeeded() {
    const nodes = cy.nodes().toArray();
    for (let iter = 0; iter < 8; iter += 1) {
      let moved = false;
      for (let i = 0; i < nodes.length; i += 1) {
        for (let j = i + 1; j < nodes.length; j += 1) {
          const a = nodes[i];
          const b = nodes[j];
          const boxA = a.boundingBox({ includeLabels: true, includeOverlays: false });
          const boxB = b.boundingBox({ includeLabels: true, includeOverlays: false });
          const overlapX = Math.min(boxA.x2, boxB.x2) - Math.max(boxA.x1, boxB.x1);
          const overlapY = Math.min(boxA.y2, boxB.y2) - Math.max(boxA.y1, boxB.y1);
          if (overlapX <= 0 || overlapY <= 0) continue;
          moved = true;
          let dx = b.position('x') - a.position('x');
          let dy = b.position('y') - a.position('y');
          if (dx === 0 && dy === 0) {
            dx = 1;
            dy = 1;
          }
          const len = Math.hypot(dx, dy) || 1;
          const ux = dx / len;
          const uy = dy / len;
          const shift = Math.min(overlapX, overlapY) / 2 + 20;
          a.position({ x: a.position('x') - ux * shift, y: a.position('y') - uy * shift });
          b.position({ x: b.position('x') + ux * shift, y: b.position('y') + uy * shift });
        }
      }
      if (!moved) break;
    }
  }

  function setGraphElements(elements, fitPadding) {
    const cloned = JSON.parse(JSON.stringify(elements || []));
    cy.elements().remove();
    cy.add(cloned);
    const usePreset = cloned
      .filter(function (item) {
        return item && item.data && !item.data.source;
      })
      .every(function (item) {
        return !!item.position
          && Number.isFinite(item.position.x)
          && Number.isFinite(item.position.y);
      });
    const layout = usePreset
      ? { name: 'preset', fit: true, padding: fitPadding || 60, animate: false }
      : {
          name: 'cose',
          fit: true,
          padding: fitPadding || 95,
          animate: false,
          nodeDimensionsIncludeLabels: true,
          componentSpacing: 180,
          idealEdgeLength: 220,
          edgeElasticity: 100,
          nodeRepulsion: 320000,
          gravity: 28,
          numIter: 1600,
          initialTemp: 220,
          coolingFactor: 0.94,
          minTemp: 1
        };
    const runner = cy.layout(layout);
    runner.on('layoutstop', function () {
      if (!usePreset) {
        spreadNodesIfNeeded();
        cy.fit(undefined, fitPadding || 95);
      }
    });
    runner.run();
  }

  function showNode(node) {
    detailEl.innerHTML = '<strong>' + node.data('label') + '</strong>（' + (typeMap[node.data('type')] || node.data('type') || '节点') + '）<br>' +
      (node.data('description') || '当前节点暂无补充说明。') +
      '<div style="margin-top:6px;color:#6b7f95;">连接数：' + node.degree() + '</div>';
  }

  function showEdge(edge) {
    const evidence = edge.data('evidence') || '';
    const pmids = Array.isArray(edge.data('pmids')) ? edge.data('pmids').filter(Boolean).join(', ') : '';
    const support = evidence || (pmids ? ('PMID: ' + pmids) : '当前未列出');
    detailEl.innerHTML = '<strong>' + edge.source().data('label') + '</strong> → ' + (relMap[edge.data('relation')] || edge.data('relation') || '关系') + ' → <strong>' + edge.target().data('label') + '</strong><br>证据：' + support;
  }

  function renderBestMatch(payload) {
    const anchor = payload.anchor;
    if (!anchor) {
      resultEl.innerHTML = '未找到与当前关键词匹配的实体。';
      return;
    }
    resultEl.innerHTML =
      '<div><strong>' + anchor.name + '</strong>（' + (typeMap[anchor.type] || anchor.type) + '）</div>' +
      '<div style="margin-top:8px;color:#5e7288;">当前搜索页以最佳命中实体为主结果展示。后续如需扩展，可在此页增加相关候选项列表。</div>' +
      (anchor.pmid ? '<div style="margin-top:8px;color:#5e7288;">PMID：' + anchor.pmid + '</div>' : '') +
      (Array.isArray(payload.matches) && payload.matches.length > 1
        ? '<div style="margin-top:10px;color:#5e7288;">相关候选：' + payload.matches.slice(1, 4).map(function (item) { return item.name; }).join('、') + '</div>'
        : '');
  }

  function cleanRepbaseLabel(value) {
    return String(value || '')
      .replace(/<[^>]+>/g, '')
      .trim()
      .replace(/[.;,]+$/g, '')
      .replace(/\s+/g, ' ');
  }

  function canonicalizeRepbaseLabel(value) {
    return cleanRepbaseLabel(value).toLowerCase().replace(/[_\-\s]/g, '');
  }

  async function loadRepbaseData() {
    if (!repbaseDataPromise) {
      repbaseDataPromise = fetch('data/processed/te_repbase_db_matched.json')
        .then(function (res) {
          if (!res.ok) {
            throw new Error('Repbase 数据加载失败');
          }
          return res.json();
        });
    }
    return repbaseDataPromise;
  }

  function renderRepbaseCard(repbase, matchedName) {
    return [
      '<div><strong>匹配名称：</strong>' + matchedName + '</div>',
      '<div><strong>Repbase ID：</strong>' + (repbase.id || '-') + '</div>',
      '<div><strong>标准名：</strong>' + (repbase.name || repbase.id || '-') + '</div>',
      '<div><strong>说明：</strong>' + (repbase.description || '暂无说明') + '</div>',
      '<div><strong>关键词：</strong>' + ((repbase.keywords && repbase.keywords.length) ? repbase.keywords.join('；') : '暂无关键词') + '</div>',
      '<div><strong>物种：</strong>' + (repbase.species || '暂无物种信息') + '</div>',
      '<div><strong>序列摘要：</strong>' + ((repbase.sequence_summary && repbase.sequence_summary.raw) ? repbase.sequence_summary.raw : '暂无序列摘要') + '</div>',
      '<div><strong>参考文献数：</strong>' + ((repbase.references && repbase.references.length) ? repbase.references.length : 0) + '</div>'
    ].join('');
  }

  async function updateRepbaseBlock(query, payload) {
    if (!repbaseEl) return;
    const anchor = payload && payload.anchor ? payload.anchor : null;
    const candidateNames = [];
    if (anchor && anchor.type === 'TE' && anchor.name) candidateNames.push(anchor.name);
    if (query) candidateNames.push(query);
    const uniqueNames = Array.from(new Set(candidateNames.filter(Boolean)));

    if (!uniqueNames.length) {
      repbaseEl.innerHTML = '该区块用于展示当前数据库 TE 能映射到的 Repbase 条目信息，包括标准名、说明、关键词、物种和序列摘要。';
      return;
    }

    try {
      const repbasePayload = await loadRepbaseData();
      const entries = repbasePayload.entries || [];
      const entryById = new Map(entries.map(function (entry) { return [entry.id, entry]; }));
      let matchedId = null;
      let matchedName = null;

      uniqueNames.some(function (name) {
        const strictKey = cleanRepbaseLabel(name).toLowerCase();
        const canonicalKey = canonicalizeRepbaseLabel(name);
        matchedId = (repbasePayload.name_index && repbasePayload.name_index[strictKey])
          || (repbasePayload.canonical_index && repbasePayload.canonical_index[canonicalKey])
          || null;
        if (matchedId) {
          matchedName = name;
          return true;
        }
        return false;
      });

      if (!matchedId || !entryById.has(matchedId)) {
        repbaseEl.innerHTML = '当前查询词或最佳命中 TE 暂未在已对齐的 Repbase 子集中命中。';
        return;
      }

      repbaseEl.innerHTML = renderRepbaseCard(entryById.get(matchedId), matchedName || matchedId);
    } catch (err) {
      repbaseEl.innerHTML = 'Repbase 参考区块加载失败：' + (err && err.message ? err.message : 'unknown error');
    }
  }

  async function runSearch(query) {
    if (!query) {
      resultEl.innerHTML = '输入关键词后，这里会显示最佳命中的实体详情。';
      setGraphElements(initialElements, 50);
      detailEl.innerHTML = '';
      updateRepbaseBlock('', null);
      return;
    }
    resultEl.innerHTML = '正在检索 <strong>' + query.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</strong> …';
    try {
      const response = await fetch('api/graph.php?q=' + encodeURIComponent(query));
      const payload = await response.json();
      if (!response.ok || !payload.ok) {
        throw new Error(payload.error || '搜索失败');
      }
      renderBestMatch(payload);
      if (payload.elements && payload.elements.length > 0) {
        setGraphElements(payload.elements, 60);
      } else {
        setGraphElements(initialElements, 50);
      }
      detailEl.innerHTML = '';
      updateRepbaseBlock(query, payload);
    } catch (err) {
      resultEl.innerHTML = '搜索失败：' + (err && err.message ? err.message : 'unknown error');
      repbaseEl.innerHTML = 'Repbase 参考区块暂不可用。';
    }
  }

  cy.on('tap', 'node', function (evt) {
    clearActive();
    evt.target.addClass('active-node');
    showNode(evt.target);
  });

  cy.on('tap', 'edge', function (evt) {
    clearActive();
    evt.target.addClass('active-edge');
    showEdge(evt.target);
  });

    cy.on('tap', function (evt) {
      if (evt.target === cy) {
        clearActive();
        detailEl.innerHTML = '';
      }
    });

  resetBtn.addEventListener('click', function () {
    queryInput.value = '';
    window.history.replaceState({}, '', 'search.php');
    resultEl.innerHTML = '输入关键词后，这里会显示最佳命中的实体详情。';
      if (repbaseEl) {
        repbaseEl.innerHTML = '该区块用于展示当前数据库 TE 能映射到的 Repbase 条目信息，包括标准名、说明、关键词、物种和序列摘要。';
      }
      setGraphElements(initialElements, 50);
      detailEl.innerHTML = '';
    });

  searchForm.addEventListener('submit', function (evt) {
    const query = queryInput.value.trim();
    if (!query) return;
    evt.preventDefault();
    const url = new URL(window.location.href);
    url.searchParams.set('q', query);
    const typeField = searchForm.querySelector('select[name="type"]');
    if (typeField) {
      url.searchParams.set('type', typeField.value || 'all');
    }
    window.history.replaceState({}, '', url.toString());
    runSearch(query);
  });

  setGraphElements(initialElements, 50);
  const initialQuery = queryInput.value.trim();
  if (initialQuery) {
    runSearch(initialQuery);
  }
}());
