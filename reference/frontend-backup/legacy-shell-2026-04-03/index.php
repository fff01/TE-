<?php
require_once __DIR__ . '/site_i18n.php';
$lang = site_lang();
$renderer = site_renderer();
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
$homePreviewSrc = $renderer === 'g6'
    ? 'index_g6.html?embed=home-preview&lang=' . rawurlencode($lang)
    : 'index_demo.html?embed=home-preview&lang=' . rawurlencode($lang);
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
      <a href="<?= htmlspecialchars(site_url_with_state('preview.php', $lang, $renderer), ENT_QUOTES, 'UTF-8') ?>" style="color:inherit;text-decoration:none;"><?= htmlspecialchars(site_t(['zh' => '知识图谱预览', 'en' => 'Knowledge Graph Preview'], $lang), ENT_QUOTES, 'UTF-8') ?></a>
    </h3>
    <iframe
      id="home-preview-frame"
      src="<?= htmlspecialchars($homePreviewSrc, ENT_QUOTES, 'UTF-8') ?>"
      title="<?= htmlspecialchars(site_t(['zh' => '首页知识图谱预览', 'en' => 'Home knowledge graph preview'], $lang), ENT_QUOTES, 'UTF-8') ?>"
      style="width:100%;height:520px;border:1px solid #d8e4f0;border-radius:18px;background:radial-gradient(circle at top,#ffffff,#edf4ff);box-shadow:inset 0 1px 0 rgba(255,255,255,.72);"
    ></iframe>
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
        <a href="<?= htmlspecialchars(site_url_with_state('search.php', $lang, $renderer, ['q' => 'LINE1']), ENT_QUOTES, 'UTF-8') ?>" style="padding:8px 14px;border-radius:999px;background:var(--te-soft);color:var(--te);font-weight:600;">TE: LINE1</a>
        <a href="<?= htmlspecialchars(site_url_with_state('search.php', $lang, $renderer, ['q' => 'Breast cancer']), ENT_QUOTES, 'UTF-8') ?>" style="padding:8px 14px;border-radius:999px;background:var(--disease-soft);color:var(--disease);font-weight:600;">Disease: Breast cancer</a>
        <a href="<?= htmlspecialchars(site_url_with_state('search.php', $lang, $renderer, ['q' => 'RNA polymerase III transcription']), ENT_QUOTES, 'UTF-8') ?>" style="padding:8px 14px;border-radius:999px;background:var(--function-soft);color:var(--function);font-weight:600;">Function: RNA polymerase III transcription</a>
        <a href="<?= htmlspecialchars(site_url_with_state('search.php', $lang, $renderer, ['q' => '39100749']), ENT_QUOTES, 'UTF-8') ?>" style="padding:8px 14px;border-radius:999px;background:var(--paper-soft);color:var(--paper);font-weight:600;">Paper: PMID 39100749</a>
      </div>
    </section>
  </div>
</section>

<script>
  (function () {
    const renderer = <?= json_encode($renderer, JSON_UNESCAPED_UNICODE) ?>;
    const frame = document.getElementById('home-preview-frame');
    if (!frame) return;

    if (renderer === 'g6') return;

    function restyleHomePreview() {
      const doc = frame.contentDocument;
      if (!doc) return;
      const innerWin = frame.contentWindow;
      const innerCy = innerWin && innerWin.__TEKG_CY ? innerWin.__TEKG_CY : null;
      const header = doc.querySelector('header');
      const footer = doc.querySelector('footer');
      const langControl = doc.querySelector('.lang');
      const rightPanel = doc.querySelector('.main > .panel:last-child');
      const graphPanel = doc.querySelector('.main > .panel:first-child');
      const graphHead = graphPanel ? graphPanel.querySelector('.head') : null;
      const graphTools = graphPanel ? graphPanel.querySelector('.toolbar') : null;
      const graphDetail = doc.getElementById('node-details');

      if (header) header.style.display = 'none';
      if (footer) footer.style.display = 'none';
      if (langControl) langControl.style.display = 'none';
      if (rightPanel) rightPanel.style.display = 'none';
      if (graphTools) graphTools.style.display = 'none';
      if (graphHead) graphHead.style.display = 'none';
      if (graphDetail) {
        graphDetail.style.display = 'block';
        graphDetail.style.marginTop = '10px';
      }

      doc.documentElement.style.height = '100%';
      doc.body.style.height = '100%';
      doc.body.style.margin = '0';
      doc.body.style.background = 'transparent';

      const main = doc.querySelector('.main');
      if (main) {
        main.style.display = 'block';
        main.style.height = '100%';
        main.style.minHeight = '100%';
        main.style.padding = '0';
        main.style.gap = '0';
      }

      if (graphPanel) {
        graphPanel.style.height = '100%';
        graphPanel.style.minHeight = '100%';
        graphPanel.style.border = 'none';
        graphPanel.style.borderRadius = '18px';
        graphPanel.style.boxShadow = 'none';
      }

      const cyEl = doc.getElementById('cy');
      if (cyEl) {
        cyEl.style.height = '100%';
        cyEl.style.minHeight = '100%';
        cyEl.style.flex = '1';
      }

      if (innerCy) {
        try {
          innerCy.resize();
          innerCy.fit(undefined, 45);
        } catch (_err) {}
      }
    }

    frame.addEventListener('load', function () {
      restyleHomePreview();
      setTimeout(restyleHomePreview, 250);
      setTimeout(restyleHomePreview, 800);
    });
  }());
</script>
<?php include __DIR__ . '/foot.php'; ?>
