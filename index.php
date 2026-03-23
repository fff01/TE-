<?php
require_once __DIR__ . '/site_i18n.php';
$lang = site_lang();
$pageTitle = site_t(['zh' => '首页 - TEKG', 'en' => 'Home - TEKG'], $lang);
$activePage = 'home';

function tekg_home_counts(): array
{
    $fallback = [
        'TE' => '—',
        'Disease' => '—',
        'Function' => '—',
        'Paper' => '—',
    ];

    $configPath = __DIR__ . '/api/config.local.php';
    if (!is_file($configPath)) {
        return $fallback;
    }

    $config = include $configPath;
    $url = $config['neo4j_url'] ?? '';
    $user = $config['neo4j_user'] ?? '';
    $password = $config['neo4j_password'] ?? '';
    if ($url === '' || $user === '') {
        return $fallback;
    }

    $payload = [
        'statements' => [[
            'statement' => "MATCH (n) RETURN sum(CASE WHEN 'TE' IN labels(n) THEN 1 ELSE 0 END) AS te_count, sum(CASE WHEN 'Disease' IN labels(n) THEN 1 ELSE 0 END) AS disease_count, sum(CASE WHEN 'Function' IN labels(n) THEN 1 ELSE 0 END) AS function_count, sum(CASE WHEN 'Paper' IN labels(n) THEN 1 ELSE 0 END) AS paper_count",
        ]],
    ];

    $ch = curl_init($url);
    if ($ch === false) {
        return $fallback;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_USERPWD => $user . ':' . $password,
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $status < 200 || $status >= 300 || !$response) {
        return $fallback;
    }

    $decoded = json_decode($response, true);
    $row = $decoded['results'][0]['data'][0]['row'] ?? null;
    if (!is_array($row) || count($row) < 4) {
        return $fallback;
    }

    return [
        'TE' => number_format((int) $row[0]),
        'Disease' => number_format((int) $row[1]),
        'Function' => number_format((int) $row[2]),
        'Paper' => number_format((int) $row[3]),
    ];
}

$counts = tekg_home_counts();
include __DIR__ . '/head.php';
?>
<section class="hero-card" style="background:#2f588f;color:#fff;border-color:#2f588f;box-shadow:none;">
  <div style="max-width:820px;margin:0 auto;text-align:center;">
    <h2 class="page-title" style="color:#fff;margin-bottom:14px;"><?= htmlspecialchars(site_t([
      'zh' => '浏览与检索转座元件知识图谱数据库',
      'en' => 'Explore and Search the Transposable Elements Knowledge Graph'
    ], $lang), ENT_QUOTES, 'UTF-8') ?></h2>
    <p class="page-desc" style="color:#dbe7fb;line-height:1.9;">
      <?= htmlspecialchars(site_t([
        'zh' => '本数据库用于组织转座元件（TE）、疾病、功能机制与文献证据之间的结构化关联，当前支持 TE 树预览、图谱浏览、智能问答、实体检索与数据下载。',
        'en' => 'This database organizes structured relationships among transposable elements (TEs), diseases, functions/mechanisms, and literature evidence. It currently supports TE tree preview, graph exploration, QA, entity search, and data download.'
      ], $lang), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <form action="search.php" method="GET" style="margin-top:28px;display:flex;max-width:860px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 16px 32px rgba(10,30,60,.22);">
      <select name="type" style="width:170px;border:none;border-right:1px solid #d9e5f3;padding:0 18px;background:#f7faff;color:#28425f;font-size:15px;outline:none;">
        <option value="all"><?= htmlspecialchars(site_t(['zh' => '所有数据类型', 'en' => 'All types'], $lang), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="TE">TE</option>
        <option value="Disease">Disease</option>
        <option value="Function">Function</option>
        <option value="Paper">Paper</option>
      </select>
      <input type="text" name="q" placeholder="<?= htmlspecialchars(site_t(['zh' => '输入标识符或关键词进行搜索...', 'en' => 'Search by identifier or keyword...'], $lang), ENT_QUOTES, 'UTF-8') ?>" style="flex:1;border:none;padding:0 18px;font-size:16px;min-height:74px;outline:none;color:#19324d;">
      <button type="submit" style="min-width:132px;border:none;background:#3b67f2;color:#fff;font-size:20px;font-weight:700;cursor:pointer;"><?= htmlspecialchars(site_t(['zh' => '搜索', 'en' => 'Search'], $lang), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
  </div>
</section>

<section style="display:grid;grid-template-columns:minmax(0,2fr) minmax(320px,.95fr);gap:24px;align-items:start;">
  <div class="content-card">
    <h3 style="margin:0 0 18px;font-size:24px;padding-bottom:12px;border-bottom:1px solid #e5edf7;">
      <a href="<?= htmlspecialchars(site_url_with_lang('preview.php', $lang), ENT_QUOTES, 'UTF-8') ?>" style="color:inherit;text-decoration:none;"><?= htmlspecialchars(site_t(['zh' => '知识图谱预览', 'en' => 'Knowledge Graph Preview'], $lang), ENT_QUOTES, 'UTF-8') ?></a>
    </h3>
    <div id="home-preview" style="height:520px;border:1px solid #d8e4f0;border-radius:18px;background:radial-gradient(circle at top,#ffffff,#edf4ff);"></div>
    <div id="home-preview-detail" style="margin-top:14px;padding:14px 16px;border-radius:16px;background:#f8fbff;border:1px solid #dbe7f3;color:#5e7288;line-height:1.7;min-height:74px;">
      <?= htmlspecialchars(site_t([
        'zh' => '点击节点或关系可查看详情；在首页预览中不会跳转到其他图谱页面。',
        'en' => 'Click nodes or edges to inspect details. The home preview does not jump to another graph page.'
      ], $lang), ENT_QUOTES, 'UTF-8') ?>
    </div>
  </div>

  <div style="display:grid;gap:22px;">
    <section class="content-card">
      <h3 style="margin:0 0 14px;font-size:22px;padding-bottom:12px;border-bottom:1px solid #e5edf7;"><?= htmlspecialchars(site_t(['zh' => '数据集状态', 'en' => 'Dataset Status'], $lang), ENT_QUOTES, 'UTF-8') ?></h3>
      <div style="display:grid;gap:14px;font-size:16px;">
        <div style="display:flex;justify-content:space-between;gap:12px;"><span style="color:#111827;"><?= htmlspecialchars(site_t(['zh' => 'TE 节点：', 'en' => 'TE nodes:'], $lang), ENT_QUOTES, 'UTF-8') ?></span><strong style="color:var(--te);"><?= htmlspecialchars((string) $counts['TE'], ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div style="display:flex;justify-content:space-between;gap:12px;"><span style="color:#111827;"><?= htmlspecialchars(site_t(['zh' => 'Disease 节点：', 'en' => 'Disease nodes:'], $lang), ENT_QUOTES, 'UTF-8') ?></span><strong style="color:var(--disease);"><?= htmlspecialchars((string) $counts['Disease'], ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div style="display:flex;justify-content:space-between;gap:12px;"><span style="color:#111827;"><?= htmlspecialchars(site_t(['zh' => 'Function 节点：', 'en' => 'Function nodes:'], $lang), ENT_QUOTES, 'UTF-8') ?></span><strong style="color:var(--function);"><?= htmlspecialchars((string) $counts['Function'], ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div style="display:flex;justify-content:space-between;gap:12px;"><span style="color:#111827;"><?= htmlspecialchars(site_t(['zh' => 'Paper 节点：', 'en' => 'Paper nodes:'], $lang), ENT_QUOTES, 'UTF-8') ?></span><strong style="color:var(--paper);"><?= htmlspecialchars((string) $counts['Paper'], ENT_QUOTES, 'UTF-8') ?></strong></div>
      </div>
    </section>

    <section class="content-card">
      <h3 style="margin:0 0 14px;font-size:22px;padding-bottom:12px;border-bottom:1px solid #e5edf7;"><?= htmlspecialchars(site_t(['zh' => '快速检索示例', 'en' => 'Quick Search Examples'], $lang), ENT_QUOTES, 'UTF-8') ?></h3>
      <p style="margin:0 0 14px;color:#5e7288;line-height:1.7;"><?= htmlspecialchars(site_t(['zh' => '每类提供一个典型入口，点击后将直接跳转到搜索页。', 'en' => 'Each category provides one representative entry that jumps directly to the search page.'], $lang), ENT_QUOTES, 'UTF-8') ?></p>
      <div style="display:flex;flex-wrap:wrap;gap:10px;">
        <a href="<?= htmlspecialchars(site_url_with_lang('search.php?q=LINE1', $lang), ENT_QUOTES, 'UTF-8') ?>" style="padding:8px 14px;border-radius:999px;background:var(--te-soft);color:var(--te);font-weight:600;">TE: LINE1</a>
        <a href="<?= htmlspecialchars(site_url_with_lang('search.php?q=Breast%20cancer', $lang), ENT_QUOTES, 'UTF-8') ?>" style="padding:8px 14px;border-radius:999px;background:var(--disease-soft);color:var(--disease);font-weight:600;">Disease: Breast cancer</a>
        <a href="<?= htmlspecialchars(site_url_with_lang('search.php?q=RNA%20polymerase%20III%20transcription', $lang), ENT_QUOTES, 'UTF-8') ?>" style="padding:8px 14px;border-radius:999px;background:var(--function-soft);color:var(--function);font-weight:600;">Function: RNA polymerase III transcription</a>
        <a href="<?= htmlspecialchars(site_url_with_lang('search.php?q=39100749', $lang), ENT_QUOTES, 'UTF-8') ?>" style="padding:8px 14px;border-radius:999px;background:var(--paper-soft);color:var(--paper);font-weight:600;">Paper: PMID 39100749</a>
      </div>
    </section>
  </div>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.25.0/cytoscape.min.js"></script>
<script src="graph_demo_data.js"></script>
<script>
  (function () {
    const lang = <?= json_encode($lang, JSON_UNESCAPED_UNICODE) ?>;
    const container = document.getElementById('home-preview');
    const detail = document.getElementById('home-preview-detail');
    const demoData = window.GRAPH_DEMO_DATA || { elements: [] };
    const elements = Array.isArray(demoData.elements) ? demoData.elements : [];

    const colorMap = { TE: '#2563eb', Disease: '#ef4444', Function: '#10b981', Paper: '#f59e0b' };
    const typeMap = {
      zh: { TE: '转座元件', Disease: '疾病', Function: '功能/机制', Paper: '文献' },
      en: { TE: 'TE', Disease: 'Disease', Function: 'Function/Mechanism', Paper: 'Paper' }
    };
    const relMap = { zh: { SUBFAMILY_OF: '包含', EVIDENCE_RELATION: '文献支持' }, en: { SUBFAMILY_OF: 'contains', EVIDENCE_RELATION: 'literature support' } };
    const detailText = {
      zh: '当前首页预览支持查看节点与关系，但不会跳转到动态图。',
      en: 'The home preview supports node and edge inspection, but does not jump to another graph page.'
    };
    const degreeLabel = { zh: '连接数：', en: 'Degree: ' };

    const cy = cytoscape({
      container,
      elements,
      wheelSensitivity: 0.2,
      style: [
        { selector: 'node', style: {
          'label': 'data(label)',
          'font-size': function (ele) {
            const d = ele.data('tree_depth');
            if (d === 0) return '20px';
            if (d === 1) return '17px';
            if (d === 2) return '14px';
            if (d === 3) return '12px';
            return '12px';
          },
          'min-zoomed-font-size': 9,
          'text-valign': 'center',
          'text-halign': 'center',
          'background-color': function (ele) { return colorMap[ele.data('type')] || '#94a3b8'; },
          'color': '#0f172a',
          'text-outline-width': 3,
          'text-outline-color': '#fff',
          'width': 'label',
          'height': 'label',
          'padding': function (ele) {
            const d = ele.data('tree_depth');
            if (d === 0) return '20px';
            if (d === 1) return '17px';
            if (d === 2) return '14px';
            if (d === 3) return '11px';
            return '13px';
          },
          'text-wrap': 'wrap',
          'text-max-width': 150,
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
          'label': function (ele) { return relMap[lang][ele.data('relation')] || ele.data('relation') || ''; },
          'font-size': '10px',
          'color': '#334155',
          'text-background-color': 'rgba(255,255,255,0.92)',
          'text-background-opacity': 1,
          'text-background-padding': '2px',
          'text-rotation': 'autorotate'
        }},
        { selector: '.active-node', style: { 'border-width': 5, 'border-color': '#0f172a', 'shadow-blur': 14, 'shadow-color': '#2563eb', 'shadow-opacity': 0.24 }},
        { selector: '.active-edge', style: { 'width': 4, 'line-color': '#1d4ed8', 'target-arrow-color': '#1d4ed8' }}
      ],
      layout: { name: 'preset', fit: true, padding: 40, animate: false }
    });

    function clearActive() { cy.nodes().removeClass('active-node'); cy.edges().removeClass('active-edge'); }
    function setDetail(html) { detail.innerHTML = html; }

    cy.on('tap', 'node', function (evt) {
      const node = evt.target;
      clearActive();
      node.addClass('active-node');
      setDetail('<strong>' + node.data('label') + '</strong>（' + (typeMap[lang][node.data('type')] || node.data('type') || 'node') + '）<br>' + detailText[lang] + '<div style="margin-top:6px;color:#6b7f95;">' + degreeLabel[lang] + node.degree() + '</div>');
    });

    cy.on('tap', 'edge', function (evt) {
      const edge = evt.target;
      clearActive();
      edge.addClass('active-edge');
      setDetail('<strong>' + edge.source().data('label') + '</strong> → ' + (relMap[lang][edge.data('relation')] || edge.data('relation') || 'relation') + ' → <strong>' + edge.target().data('label') + '</strong>');
    });

    cy.on('tap', function (evt) {
      if (evt.target === cy) {
        clearActive();
        setDetail(detailText[lang]);
      }
    });

    const root = cy.nodes().filter(function (node) { return node.data('tree_depth') === 0; })[0];
    if (root) {
      root.addClass('active-node');
      cy.center(root);
    }
  }());
</script>
<?php include __DIR__ . '/foot.php'; ?>
