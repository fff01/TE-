<?php
$pageTitle = '搜索 - TEKG';
$activePage = 'search';

function tekg_repbase_lookup(string $query): ?array
{
    $query = trim($query);
    if ($query === '') {
        return null;
    }

    $file = __DIR__ . '/data/raw/TE_Repbase.txt';
    if (!is_file($file)) {
        return null;
    }

    $text = (string) file_get_contents($file);
    if ($text === '') {
        return null;
    }

    $blocks = preg_split('/\n\/\/\s*\n/', str_replace("\r\n", "\n", $text));
    $queryLower = mb_strtolower($query);

    foreach ($blocks as $block) {
        $lines = array_filter(array_map('trim', explode("\n", $block)));
        if (empty($lines)) {
            continue;
        }

        $id = '';
        $nm = '';
        $de = '';
        $kw = [];
        foreach ($lines as $line) {
            if (str_starts_with($line, 'ID   ')) {
                $id = trim(substr($line, 5));
                $id = preg_replace('/\s+repbase;.*$/', '', $id) ?? $id;
            } elseif (str_starts_with($line, 'NM   ')) {
                $nm = trim(substr($line, 5));
            } elseif (str_starts_with($line, 'DE   ')) {
                $de = trim(substr($line, 5));
            } elseif (str_starts_with($line, 'KW   ')) {
                $kw[] = trim(substr($line, 5));
            }
        }

        $candidates = array_filter([$id, $nm]);
        foreach ($candidates as $candidate) {
            if (mb_strtolower($candidate) === $queryLower) {
                return [
                    'id' => $id,
                    'nm' => $nm,
                    'description' => $de,
                    'keywords' => implode(' ', $kw),
                    'matched' => $candidate,
                ];
            }
        }
    }

    return null;
}

$query = trim((string) ($_GET['q'] ?? ''));
$type = trim((string) ($_GET['type'] ?? 'all'));
$repbase = tekg_repbase_lookup($query);
include __DIR__ . '/head.php';
?>
<section class="hero-card">
  <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:end;justify-content:space-between;">
    <div>
      <h2 class="page-title">搜索</h2>
      <p class="page-desc">输入关键词后，页面会展示最佳命中实体、局部图谱，以及 TE 的 Repbase 参考信息区块。</p>
    </div>
    <form id="search-form" method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;min-width:min(100%,640px);">
      <select name="type" style="width:170px;min-height:50px;border:1px solid #d8e4f0;border-radius:14px;padding:0 14px;background:#f7faff;color:#28425f;font-size:15px;outline:none;">
        <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>所有数据类型</option>
        <option value="TE" <?= $type === 'TE' ? 'selected' : '' ?>>TE</option>
        <option value="Disease" <?= $type === 'Disease' ? 'selected' : '' ?>>Disease</option>
        <option value="Function" <?= $type === 'Function' ? 'selected' : '' ?>>Function</option>
        <option value="Paper" <?= $type === 'Paper' ? 'selected' : '' ?>>Paper</option>
      </select>
      <input id="search-query" type="text" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="输入 TE、疾病、功能或 PMID" style="flex:1;min-width:260px;min-height:50px;border:1px solid #d8e4f0;border-radius:14px;padding:0 16px;font-size:15px;outline:none;">
      <button type="submit" style="min-width:92px;min-height:50px;border:none;border-radius:14px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer;">搜索</button>
    </form>
  </div>
</section>

<section style="display:grid;grid-template-columns:minmax(340px,.92fr) minmax(0,1.35fr);gap:22px;align-items:start;">
  <div style="display:grid;gap:22px;">
    <section class="content-card">
      <h3 style="margin:0 0 14px;font-size:22px;padding-bottom:12px;border-bottom:1px solid #e5edf7;">最佳命中</h3>
      <div id="search-best-match" style="line-height:1.8;color:#5e7288;min-height:120px;">
        <?php if ($query === ''): ?>
          输入关键词后，这里会显示最佳命中的实体详情。
        <?php else: ?>
          正在检索 <strong><?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?></strong> …
        <?php endif; ?>
      </div>
    </section>

    <section class="content-card">
      <h3 style="margin:0 0 14px;font-size:22px;padding-bottom:12px;border-bottom:1px solid #e5edf7;">Repbase 参考区块</h3>
      <div id="search-repbase" style="line-height:1.8;color:#5e7288;min-height:140px;">
        <?php if ($repbase !== null): ?>
          <div><strong>匹配名称：</strong><?= htmlspecialchars($repbase['matched'], ENT_QUOTES, 'UTF-8') ?></div>
          <div><strong>Repbase ID：</strong><?= htmlspecialchars($repbase['id'] ?: '-', ENT_QUOTES, 'UTF-8') ?></div>
          <div><strong>标准名：</strong><?= htmlspecialchars($repbase['nm'] ?: $repbase['id'] ?: '-', ENT_QUOTES, 'UTF-8') ?></div>
          <div><strong>说明：</strong><?= htmlspecialchars($repbase['description'] ?: '暂无说明', ENT_QUOTES, 'UTF-8') ?></div>
          <div><strong>关键词：</strong><?= htmlspecialchars($repbase['keywords'] ?: '暂无关键词', ENT_QUOTES, 'UTF-8') ?></div>
        <?php elseif ($query !== ''): ?>
          当前查询词暂未在 Repbase 文件中命中。若最佳命中为 TE，后续可继续增强这里的自动匹配逻辑。
        <?php else: ?>
          该区块预留给 TE 节点。搜索到 TE 时，后续优先展示 Repbase 的标准说明。
        <?php endif; ?>
      </div>
    </section>
  </div>

  <section class="content-card" style="display:flex;flex-direction:column;gap:14px;min-height:720px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;">
      <h3 style="margin:0;font-size:22px;">局部图谱</h3>
      <button id="search-reset" type="button" style="border:none;border-radius:14px;background:#eef4ff;color:#2753b7;padding:10px 16px;font-weight:700;cursor:pointer;">重置图谱</button>
    </div>
    <div id="search-graph" style="flex:1;min-height:580px;border:1px solid #d8e4f0;border-radius:18px;background:radial-gradient(circle at top,#ffffff,#edf4ff);"></div>
    <div id="search-graph-detail" style="padding:14px 16px;border-radius:16px;background:#f8fbff;border:1px solid #dbe7f3;color:#5e7288;line-height:1.7;min-height:84px;"></div>
  </section>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.25.0/cytoscape.min.js"></script>
<script src="graph_demo_data.js"></script>
<script>
(function () {
  const initialElements = JSON.parse(JSON.stringify((window.GRAPH_DEMO_DATA && window.GRAPH_DEMO_DATA.elements) || []));
  const graphEl = document.getElementById('search-graph');
  const detailEl = document.getElementById('search-graph-detail');
  const resultEl = document.getElementById('search-best-match');
  const repbaseEl = document.getElementById('search-repbase');
  const resetBtn = document.getElementById('search-reset');
  const searchForm = document.getElementById('search-form');
  const queryInput = document.getElementById('search-query');
  const colorMap = { TE: '#2563eb', Disease: '#ef4444', Function: '#10b981', Paper: '#f59e0b' };
  const typeMap = { TE: '转座元件', Disease: '疾病', Function: '功能/机制', Paper: '文献' };
  const relMap = {
    SUBFAMILY_OF: '包含亚家族',
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

  async function runSearch(query) {
    if (!query) {
      resultEl.innerHTML = '输入关键词后，这里会显示最佳命中的实体详情。';
      setGraphElements(initialElements, 50);
      detailEl.innerHTML = '';
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
    } catch (err) {
      resultEl.innerHTML = '搜索失败：' + (err && err.message ? err.message : 'unknown error');
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
        repbaseEl.innerHTML = '该区块预留给 TE 节点。搜索到 TE 时，后续优先展示 Repbase 的标准说明。';
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
</script>
<?php include __DIR__ . '/foot.php'; ?>
