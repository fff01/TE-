<?php
require_once __DIR__ . '/site_i18n.php';
$lang = site_lang();
$renderer = site_renderer();
$pageTitle = site_t(['zh' => '搜索 - TEKG', 'en' => 'Search - TEKG'], $lang);
$activePage = 'search';

function tekg_repbase_lookup(string $query): ?array
{
    $query = trim($query);
    if ($query === '') {
        return null;
    }

    $file = __DIR__ . '/data/processed/te_repbase_db_matched.json';
    if (!is_file($file)) {
        return null;
    }

    $payload = json_decode((string) file_get_contents($file), true);
    if (!is_array($payload)) {
        return null;
    }

    $clean = static function (string $value): string {
        $value = trim($value);
        $value = preg_replace('/<[^>]+>/', '', $value) ?? $value;
        $value = rtrim($value, ".;,");
        return preg_replace('/\s+/', ' ', $value) ?? $value;
    };
    $canonicalize = static function (string $value) use ($clean): string {
        return str_replace(['_', '-', ' '], '', mb_strtolower($clean($value)));
    };

    $strictKey = mb_strtolower($clean($query));
    $canonicalKey = $canonicalize($query);
    $entryId = $payload['name_index'][$strictKey] ?? $payload['canonical_index'][$canonicalKey] ?? null;
    if (!$entryId || empty($payload['entries']) || !is_array($payload['entries'])) {
        return null;
    }

    foreach ($payload['entries'] as $entry) {
        if (($entry['id'] ?? '') !== $entryId) {
            continue;
        }
        return [
            'matched' => $query,
            'id' => (string) ($entry['id'] ?? ''),
            'nm' => (string) ($entry['name'] ?? ''),
            'description' => (string) ($entry['description'] ?? ''),
            'keywords' => is_array($entry['keywords'] ?? null) ? implode('，', $entry['keywords']) : '',
            'species' => (string) ($entry['species'] ?? ''),
            'sequence_summary' => (string) (($entry['sequence_summary']['raw'] ?? '') ?: ''),
            'reference_count' => is_array($entry['references'] ?? null) ? count($entry['references']) : 0,
        ];
    }

    return null;
}

function tekg_request_scalar(array $source, string $key, string $default = ''): string
{
    if (!array_key_exists($key, $source)) {
        return $default;
    }
    $value = $source[$key];
    if (is_array($value)) {
        foreach ($value as $item) {
            if (is_scalar($item)) {
                return trim((string) $item);
            }
        }
        return $default;
    }
    if (!is_scalar($value)) {
        return $default;
    }
    return trim((string) $value);
}

$query = tekg_request_scalar($_GET, 'q', '');
$type = tekg_request_scalar($_GET, 'type', 'all');
$repbase = tekg_repbase_lookup($query);
$searchGraphSrc = $renderer === 'g6'
    ? 'index_g6.html?embed=search-result&lang=' . rawurlencode($lang) . ($query !== '' ? '&q=' . rawurlencode($query) : '')
    : '';
include __DIR__ . '/head.php';
?>
<section class="hero-card">
  <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:end;justify-content:space-between;">
    <div>
      <h2 class="page-title"><?= htmlspecialchars(site_t(['zh' => '搜索', 'en' => 'Search'], $lang), ENT_QUOTES, 'UTF-8') ?></h2>
      <p class="page-desc"><?= htmlspecialchars(site_t([
        'zh' => '输入关键词后，页面会展示最佳命中实体、局部图谱，以及面向 TE 的 Repbase 参考区块。',
        'en' => 'After entering a query, the page shows the best-matched entity, a local graph, and a Repbase reference block for TE entries.'
      ], $lang), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <form id="search-form" method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;min-width:min(100%,640px);">
      <select name="type" style="width:170px;min-height:50px;border:1px solid #d8e4f0;border-radius:14px;padding:0 14px;background:#f7faff;color:#28425f;font-size:15px;outline:none;">
        <option value="all" <?= $type === 'all' ? 'selected' : '' ?>><?= htmlspecialchars(site_t(['zh' => '所有数据类型', 'en' => 'All types'], $lang), ENT_QUOTES, 'UTF-8') ?></option>
        <option value="TE" <?= $type === 'TE' ? 'selected' : '' ?>>TE</option>
        <option value="Disease" <?= $type === 'Disease' ? 'selected' : '' ?>>Disease</option>
        <option value="Function" <?= $type === 'Function' ? 'selected' : '' ?>>Function</option>
        <option value="Paper" <?= $type === 'Paper' ? 'selected' : '' ?>>Paper</option>
      </select>
      <input id="search-query" type="text" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= htmlspecialchars(site_t(['zh' => '输入 TE、疾病、功能或 PMID', 'en' => 'Enter a TE, disease, function, or PMID'], $lang), ENT_QUOTES, 'UTF-8') ?>" style="flex:1;min-width:260px;min-height:50px;border:1px solid #d8e4f0;border-radius:14px;padding:0 16px;font-size:15px;outline:none;">
      <button type="submit" style="min-width:92px;min-height:50px;border:none;border-radius:14px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer;"><?= htmlspecialchars(site_t(['zh' => '搜索', 'en' => 'Search'], $lang), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
  </div>
</section>

<section style="display:grid;grid-template-columns:minmax(340px,.92fr) minmax(0,1.35fr);gap:22px;align-items:start;">
  <div style="display:grid;gap:22px;">
    <section class="content-card">
      <h3 style="margin:0 0 14px;font-size:22px;padding-bottom:12px;border-bottom:1px solid #e5edf7;"><?= htmlspecialchars(site_t(['zh' => '最佳命中', 'en' => 'Best Match'], $lang), ENT_QUOTES, 'UTF-8') ?></h3>
      <div id="search-best-match" style="line-height:1.8;color:#5e7288;min-height:120px;">
        <?php if ($query === ''): ?>
          <?= htmlspecialchars(site_t(['zh' => '输入关键词后，这里会显示最佳命中的实体详情。', 'en' => 'Enter a query to display the best-matched entity here.'], $lang), ENT_QUOTES, 'UTF-8') ?>
        <?php else: ?>
          <?= htmlspecialchars(site_t(['zh' => '正在搜索', 'en' => 'Searching for'], $lang), ENT_QUOTES, 'UTF-8') ?> <strong><?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?></strong> ...
        <?php endif; ?>
      </div>
    </section>

    <section class="content-card">
      <h3 style="margin:0 0 14px;font-size:22px;padding-bottom:12px;border-bottom:1px solid #e5edf7;"><?= htmlspecialchars(site_t(['zh' => 'Repbase 参考区块', 'en' => 'Repbase Reference'], $lang), ENT_QUOTES, 'UTF-8') ?></h3>
      <div id="search-repbase" style="line-height:1.8;color:#5e7288;min-height:140px;">
        <?php if ($repbase !== null): ?>
          <div><strong><?= htmlspecialchars(site_t(['zh' => '匹配名称：', 'en' => 'Matched name: '], $lang), ENT_QUOTES, 'UTF-8') ?></strong><?= htmlspecialchars($repbase['matched'], ENT_QUOTES, 'UTF-8') ?></div>
          <div><strong>Repbase ID：</strong><?= htmlspecialchars($repbase['id'] ?: '-', ENT_QUOTES, 'UTF-8') ?></div>
          <div><strong><?= htmlspecialchars(site_t(['zh' => '标准名：', 'en' => 'Canonical name: '], $lang), ENT_QUOTES, 'UTF-8') ?></strong><?= htmlspecialchars($repbase['nm'] ?: $repbase['id'] ?: '-', ENT_QUOTES, 'UTF-8') ?></div>
          <div><strong><?= htmlspecialchars(site_t(['zh' => '说明：', 'en' => 'Description: '], $lang), ENT_QUOTES, 'UTF-8') ?></strong><?= htmlspecialchars($repbase['description'] ?: site_t(['zh' => '暂无说明', 'en' => 'No description'], $lang), ENT_QUOTES, 'UTF-8') ?></div>
          <div><strong><?= htmlspecialchars(site_t(['zh' => '关键词：', 'en' => 'Keywords: '], $lang), ENT_QUOTES, 'UTF-8') ?></strong><?= htmlspecialchars($repbase['keywords'] ?: site_t(['zh' => '暂无关键词', 'en' => 'No keywords'], $lang), ENT_QUOTES, 'UTF-8') ?></div>
          <div><strong><?= htmlspecialchars(site_t(['zh' => '物种：', 'en' => 'Species: '], $lang), ENT_QUOTES, 'UTF-8') ?></strong><?= htmlspecialchars($repbase['species'] ?: site_t(['zh' => '暂无物种信息', 'en' => 'No species information'], $lang), ENT_QUOTES, 'UTF-8') ?></div>
          <div><strong><?= htmlspecialchars(site_t(['zh' => '序列摘要：', 'en' => 'Sequence summary: '], $lang), ENT_QUOTES, 'UTF-8') ?></strong><?= htmlspecialchars($repbase['sequence_summary'] ?: site_t(['zh' => '暂无序列摘要', 'en' => 'No sequence summary'], $lang), ENT_QUOTES, 'UTF-8') ?></div>
          <div><strong><?= htmlspecialchars(site_t(['zh' => '参考文献数：', 'en' => 'Reference count: '], $lang), ENT_QUOTES, 'UTF-8') ?></strong><?= htmlspecialchars((string) ($repbase['reference_count'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
        <?php elseif ($query !== ''): ?>
          <?= htmlspecialchars(site_t([
            'zh' => '当前查询词暂未在已对齐的 Repbase 子集中命中。如果最佳命中为 TE，页面会优先尝试按最佳命中名称继续匹配。',
            'en' => 'The current query is not found in the aligned Repbase subset. If the best match is a TE, the page will try again using the best-matched TE name.'
          ], $lang), ENT_QUOTES, 'UTF-8') ?>
        <?php else: ?>
          <?= htmlspecialchars(site_t([
            'zh' => '该区块用于展示当前数据库 TE 能映射到的 Repbase 条目信息，包括标准名、说明、关键词、物种和序列摘要。',
            'en' => 'This block shows Repbase information for TE entries aligned to the current database, including canonical name, description, keywords, species, and sequence summary.'
          ], $lang), ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <section class="content-card" style="display:flex;flex-direction:column;gap:14px;min-height:720px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;">
      <h3 style="margin:0;font-size:22px;"><?= htmlspecialchars(site_t(['zh' => '局部图谱', 'en' => 'Local Graph'], $lang), ENT_QUOTES, 'UTF-8') ?></h3>
      <button id="search-reset" type="button" style="border:none;border-radius:14px;background:#eef4ff;color:#2753b7;padding:10px 16px;font-weight:700;cursor:pointer;"><?= htmlspecialchars(site_t(['zh' => '重置图谱', 'en' => 'Reset Graph'], $lang), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
    <?php if ($renderer === 'g6'): ?>
      <iframe
        id="search-g6-frame"
        src="<?= htmlspecialchars($searchGraphSrc, ENT_QUOTES, 'UTF-8') ?>"
        title="<?= htmlspecialchars(site_t(['zh' => '搜索图谱（G6）', 'en' => 'Search graph (G6)'], $lang), ENT_QUOTES, 'UTF-8') ?>"
        style="flex:1;min-height:640px;border:1px solid #d8e4f0;border-radius:18px;background:radial-gradient(circle at top,#ffffff,#edf4ff);"
      ></iframe>
    <?php else: ?>
      <iframe
        id="search-cyt-frame"
        src="<?= htmlspecialchars(site_url_with_state('index_demo.html', $lang, 'cytoscape', ['embed' => 'search-result']), ENT_QUOTES, 'UTF-8') ?>"
        title="<?= htmlspecialchars(site_t(['zh' => '搜索图谱（Cytoscape）', 'en' => 'Search graph (Cytoscape)'], $lang), ENT_QUOTES, 'UTF-8') ?>"
        style="flex:1;min-height:640px;border:1px solid #d8e4f0;border-radius:18px;background:radial-gradient(circle at top,#ffffff,#edf4ff);"
      ></iframe>
    <?php endif; ?>
  </section>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.25.0/cytoscape.min.js"></script>
<script src="graph_demo_data.js"></script>
<script>
(function () {
  const lang = <?= json_encode($lang, JSON_UNESCAPED_UNICODE) ?>;
  const renderer = <?= json_encode($renderer, JSON_UNESCAPED_UNICODE) ?>;
  const texts = {
    zh: {
      te: '转座元件', disease: '疾病', function: '功能/机制', paper: '文献',
      relation: '关系', evidence: '证据：', emptyNode: '当前节点暂无补充说明。',
      searching: '正在搜索', best: '当前搜索页以最佳命中实体为主结果展示。后续如需扩展，可在此页增加相关候选项列表。',
      pmid: 'PMID：', candidates: '相关候选：', noMatch: '未找到与当前关键词匹配的实体。',
      prompt: '输入关键词后，这里会显示最佳命中的实体详情。',
      repbaseDefault: '该区块用于展示当前数据库 TE 能映射到的 Repbase 条目信息，包括标准名、说明、关键词、物种和序列摘要。',
      repbaseMissing: '当前查询词或最佳命中 TE 暂未在已对齐的 Repbase 子集中命中。',
      repbaseError: 'Repbase 参考区块加载失败：',
      repbaseUnavailable: 'Repbase 参考区块暂不可用。',
      noDescription: '暂无说明', noKeywords: '暂无关键词', noSpecies: '暂无物种信息', noSequence: '暂无序列摘要',
      matchName: '匹配名称：', canonicalName: '标准名：', description: '说明：', keywords: '关键词：', species: '物种：', sequenceSummary: '序列摘要：', referenceCount: '参考文献数：',
      graphDetailEmpty: '', resetState: '输入关键词后，这里会显示最佳命中的实体详情。', searchFailed: '搜索失败：', degree: '连接数：'
    },
    en: {
      te: 'TE', disease: 'Disease', function: 'Function/Mechanism', paper: 'Paper',
      relation: 'relation', evidence: 'Evidence: ', emptyNode: 'No additional description is available for this node.',
      searching: 'Searching for', best: 'This page presents the best-matched entity as the primary result. Related candidates can be added here later if needed.',
      pmid: 'PMID: ', candidates: 'Related candidates: ', noMatch: 'No entity matched the current query.',
      prompt: 'Enter a query to display the best-matched entity here.',
      repbaseDefault: 'This block shows Repbase information for TE entries aligned to the current database, including canonical name, description, keywords, species, and sequence summary.',
      repbaseMissing: 'The current query or best-matched TE is not found in the aligned Repbase subset.',
      repbaseError: 'Failed to load Repbase reference: ',
      repbaseUnavailable: 'Repbase reference is temporarily unavailable.',
      noDescription: 'No description', noKeywords: 'No keywords', noSpecies: 'No species information', noSequence: 'No sequence summary',
      matchName: 'Matched name: ', canonicalName: 'Canonical name: ', description: 'Description: ', keywords: 'Keywords: ', species: 'Species: ', sequenceSummary: 'Sequence summary: ', referenceCount: 'Reference count: ',
      graphDetailEmpty: '', resetState: 'Enter a query to display the best-matched entity here.', searchFailed: 'Search failed: ', degree: 'Degree: '
    }
  }[lang];

  if (renderer === 'g6') {
    const resultEl = document.getElementById('search-best-match');
    const repbaseEl = document.getElementById('search-repbase');
    const resetBtn = document.getElementById('search-reset');
    const searchForm = document.getElementById('search-form');
    const queryInput = document.getElementById('search-query');
    const frame = document.getElementById('search-g6-frame');
    let repbaseDataPromise = null;
    const typeField = searchForm ? searchForm.querySelector('select[name="type"]') : null;

    function renderBestMatch(payload) {
      const anchor = payload.anchor;
      if (!anchor) {
        resultEl.innerHTML = texts.noMatch;
        return;
      }
      resultEl.innerHTML = '<div><strong>' + anchor.name + '</strong>（' + (anchor.type || 'node') + '）</div>'
        + '<div style="margin-top:8px;color:#5e7288;">' + texts.best + '</div>'
        + (anchor.pmid ? '<div style="margin-top:8px;color:#5e7288;">' + texts.pmid + anchor.pmid + '</div>' : '')
        + (Array.isArray(payload.matches) && payload.matches.length > 1
          ? '<div style="margin-top:10px;color:#5e7288;">' + texts.candidates + payload.matches.slice(1, 4).map(function (item) { return item.name; }).join('、') + '</div>'
          : '');
    }

    function cleanRepbaseLabel(value) {
      return String(value || '').replace(/<[^>]+>/g, '').trim().replace(/[.;,]+$/g, '').replace(/\s+/g, ' ');
    }

    function canonicalizeRepbaseLabel(value) {
      return cleanRepbaseLabel(value).toLowerCase().replace(/[_\-\s]/g, '');
    }

    async function loadRepbaseData() {
      if (!repbaseDataPromise) {
        repbaseDataPromise = fetch('data/processed/te_repbase_db_matched.json').then(function (res) {
          if (!res.ok) throw new Error('Repbase data load failed');
          return res.json();
        });
      }
      return repbaseDataPromise;
    }

    function renderRepbaseCard(repbase, matchedName) {
      return [
        '<div><strong>' + texts.matchName + '</strong>' + matchedName + '</div>',
        '<div><strong>Repbase ID: </strong>' + (repbase.id || '-') + '</div>',
        '<div><strong>' + texts.canonicalName + '</strong>' + (repbase.name || repbase.id || '-') + '</div>',
        '<div><strong>' + texts.description + '</strong>' + (repbase.description || texts.noDescription) + '</div>',
        '<div><strong>' + texts.keywords + '</strong>' + ((repbase.keywords && repbase.keywords.length) ? repbase.keywords.join(', ') : texts.noKeywords) + '</div>',
        '<div><strong>' + texts.species + '</strong>' + (repbase.species || texts.noSpecies) + '</div>',
        '<div><strong>' + texts.sequenceSummary + '</strong>' + ((repbase.sequence_summary && repbase.sequence_summary.raw) ? repbase.sequence_summary.raw : texts.noSequence) + '</div>',
        '<div><strong>' + texts.referenceCount + '</strong>' + ((repbase.references && repbase.references.length) ? repbase.references.length : 0) + '</div>'
      ].join('');
    }

    async function updateRepbaseBlock(query, payload) {
      if (!repbaseEl) return;
      const anchor = payload && payload.anchor ? payload.anchor : null;
      const candidateNames = [];
      if (anchor && anchor.type === 'TE') {
        if (anchor.standard_name) candidateNames.push(anchor.standard_name);
        if (anchor.name) candidateNames.push(anchor.name);
      }
      if (query) candidateNames.push(query);
      const uniqueNames = Array.from(new Set(candidateNames.filter(Boolean)));
      if (!uniqueNames.length) {
        repbaseEl.innerHTML = texts.repbaseDefault;
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
          matchedId = (repbasePayload.name_index && repbasePayload.name_index[strictKey]) || (repbasePayload.canonical_index && repbasePayload.canonical_index[canonicalKey]) || null;
          if (matchedId) {
            matchedName = name;
            return true;
          }
          return false;
        });
        if (!matchedId || !entryById.has(matchedId)) {
          repbaseEl.innerHTML = texts.repbaseMissing;
          return;
        }
        repbaseEl.innerHTML = renderRepbaseCard(entryById.get(matchedId), matchedName || matchedId);
      } catch (err) {
        repbaseEl.innerHTML = texts.repbaseError + (err && err.message ? err.message : 'unknown error');
      }
    }

    function setG6Frame(query) {
      if (!frame) return;
      const url = new URL('index_g6.html', window.location.href);
      url.searchParams.set('embed', 'search-result');
      url.searchParams.set('lang', lang);
      if (query) {
        url.searchParams.set('q', query);
      }
      frame.src = url.toString();
    }

    async function runSearch(query) {
      if (!query) {
        resultEl.innerHTML = texts.prompt;
        repbaseEl.innerHTML = texts.repbaseDefault;
        setG6Frame('');
        return;
      }
      resultEl.innerHTML = texts.searching + ' <strong>' + query.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</strong> ...';
      try {
        const searchUrl = new URL('api/graph.php', window.location.href);
        searchUrl.searchParams.set('q', query);
        if (typeField && typeField.value !== 'all') {
          searchUrl.searchParams.set('type', typeField.value);
        }
        searchUrl.searchParams.set('lang', lang);
        const response = await fetch(searchUrl.toString(), { cache: 'no-store' });
        const payload = await response.json();
        if (!response.ok || !payload || payload.ok === false) {
          throw new Error((payload && payload.error) || 'search failed');
        }
        renderBestMatch(payload);
        await updateRepbaseBlock(query, payload);
        setG6Frame(query);
      } catch (err) {
        resultEl.innerHTML = texts.searchFailed + (err && err.message ? err.message : 'unknown error');
        repbaseEl.innerHTML = texts.repbaseUnavailable;
      }
    }

    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        queryInput.value = '';
        if (typeField) typeField.value = 'all';
        window.history.replaceState({}, '', <?= json_encode(site_url_with_state('search.php', $lang, $renderer), JSON_UNESCAPED_UNICODE) ?>);
        runSearch('');
      });
    }

    if (searchForm) {
      searchForm.addEventListener('submit', function (evt) {
        evt.preventDefault();
        const query = queryInput.value.trim();
        const url = new URL(window.location.href);
        if (query) {
          url.searchParams.set('q', query);
        } else {
          url.searchParams.delete('q');
        }
        if (typeField) url.searchParams.set('type', typeField.value || 'all');
        url.searchParams.set('lang', lang);
        url.searchParams.set('renderer', renderer);
        window.history.replaceState({}, '', url.toString());
        runSearch(query);
      });
    }

    runSearch(queryInput.value.trim());
    return;
  }

  if (renderer === 'cytoscape') {
    const resultEl = document.getElementById('search-best-match');
    const repbaseEl = document.getElementById('search-repbase');
    const resetBtn = document.getElementById('search-reset');
    const searchForm = document.getElementById('search-form');
    const queryInput = document.getElementById('search-query');
    const frame = document.getElementById('search-cyt-frame');
    let repbaseDataPromise = null;
    const typeField = searchForm ? searchForm.querySelector('select[name="type"]') : null;

    function renderBestMatch(payload) {
      const anchor = payload.anchor;
      if (!anchor) {
        resultEl.innerHTML = texts.noMatch;
        return;
      }
      resultEl.innerHTML = '<div><strong>' + anchor.name + '</strong>（' + (anchor.type || 'node') + '）</div>'
        + '<div style="margin-top:8px;color:#5e7288;">' + texts.best + '</div>'
        + (anchor.pmid ? '<div style="margin-top:8px;color:#5e7288;">' + texts.pmid + anchor.pmid + '</div>' : '')
        + (Array.isArray(payload.matches) && payload.matches.length > 1
          ? '<div style="margin-top:10px;color:#5e7288;">' + texts.candidates + payload.matches.slice(1, 4).map(function (item) { return item.name; }).join('、') + '</div>'
          : '');
    }

    function cleanRepbaseLabel(value) {
      return String(value || '').replace(/<[^>]+>/g, '').trim().replace(/[.;,]+$/g, '').replace(/\s+/g, ' ');
    }

    function canonicalizeRepbaseLabel(value) {
      return cleanRepbaseLabel(value).toLowerCase().replace(/[_\-\s]/g, '');
    }

    async function loadRepbaseData() {
      if (!repbaseDataPromise) {
        repbaseDataPromise = fetch('data/processed/te_repbase_db_matched.json').then(function (res) {
          if (!res.ok) throw new Error('Repbase data load failed');
          return res.json();
        });
      }
      return repbaseDataPromise;
    }

    function renderRepbaseCard(repbase, matchedName) {
      return [
        '<div><strong>' + texts.matchName + '</strong>' + matchedName + '</div>',
        '<div><strong>Repbase ID: </strong>' + (repbase.id || '-') + '</div>',
        '<div><strong>' + texts.canonicalName + '</strong>' + (repbase.name || repbase.id || '-') + '</div>',
        '<div><strong>' + texts.description + '</strong>' + (repbase.description || texts.noDescription) + '</div>',
        '<div><strong>' + texts.keywords + '</strong>' + ((repbase.keywords && repbase.keywords.length) ? repbase.keywords.join(', ') : texts.noKeywords) + '</div>',
        '<div><strong>' + texts.species + '</strong>' + (repbase.species || texts.noSpecies) + '</div>',
        '<div><strong>' + texts.sequenceSummary + '</strong>' + ((repbase.sequence_summary && repbase.sequence_summary.raw) ? repbase.sequence_summary.raw : texts.noSequence) + '</div>',
        '<div><strong>' + texts.referenceCount + '</strong>' + ((repbase.references && repbase.references.length) ? repbase.references.length : 0) + '</div>'
      ].join('');
    }

    async function updateRepbaseBlock(query, payload) {
      if (!repbaseEl) return;
      const anchor = payload && payload.anchor ? payload.anchor : null;
      const candidateNames = [];
      if (anchor && anchor.type === 'TE') {
        if (anchor.standard_name) candidateNames.push(anchor.standard_name);
        if (anchor.name) candidateNames.push(anchor.name);
      }
      if (query) candidateNames.push(query);
      const uniqueNames = Array.from(new Set(candidateNames.filter(Boolean)));
      if (!uniqueNames.length) {
        repbaseEl.innerHTML = texts.repbaseDefault;
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
          matchedId = (repbasePayload.name_index && repbasePayload.name_index[strictKey]) || (repbasePayload.canonical_index && repbasePayload.canonical_index[canonicalKey]) || null;
          if (matchedId) {
            matchedName = name;
            return true;
          }
          return false;
        });
        if (!matchedId || !entryById.has(matchedId)) {
          repbaseEl.innerHTML = texts.repbaseMissing;
          return;
        }
        repbaseEl.innerHTML = renderRepbaseCard(entryById.get(matchedId), matchedName || matchedId);
      } catch (err) {
        repbaseEl.innerHTML = texts.repbaseError + (err && err.message ? err.message : 'unknown error');
      }
    }

    function restyleCytFrame() {
      const doc = frame && frame.contentDocument;
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
      if (graphHead) graphHead.style.display = 'none';
      if (graphTools) graphTools.style.display = 'none';
      if (graphDetail) graphDetail.style.display = 'none';

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
          innerCy.fit(undefined, 55);
        } catch (_err) {}
      }
    }

    function ensureFrameReady() {
      return new Promise((resolve) => {
        if (!frame) {
          resolve();
          return;
        }
        const complete = () => {
          restyleCytFrame();
          resolve();
        };
        try {
          const win = frame.contentWindow;
          if (win && typeof win.__TEKG_LOAD_DYNAMIC_GRAPH === 'function' && win.__TEKG_CY) {
            complete();
            return;
          }
        } catch (_err) {}
        frame.addEventListener('load', complete, { once: true });
      });
    }

    async function setCytFrameToDefault() {
      if (!frame) return;
      const url = new URL('index_demo.html', window.location.href);
      url.searchParams.set('embed', 'search-result');
      url.searchParams.set('lang', lang);
      frame.src = url.toString();
      await ensureFrameReady();
    }

    async function setCytFrameToQuery(query) {
      if (!frame) return;
      await ensureFrameReady();
      const innerWin = frame.contentWindow;
      if (innerWin && typeof innerWin.__TEKG_LOAD_DYNAMIC_GRAPH === 'function') {
        await innerWin.__TEKG_LOAD_DYNAMIC_GRAPH(query);
        restyleCytFrame();
      }
    }

    async function runSearch(query) {
      if (!query) {
        resultEl.innerHTML = texts.prompt;
        repbaseEl.innerHTML = texts.repbaseDefault;
        await setCytFrameToDefault();
        return;
      }
      resultEl.innerHTML = texts.searching + ' <strong>' + query.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</strong> ...';
      try {
        const searchUrl = new URL('api/graph.php', window.location.href);
        searchUrl.searchParams.set('q', query);
        if (typeField && typeField.value !== 'all') {
          searchUrl.searchParams.set('type', typeField.value);
        }
        searchUrl.searchParams.set('lang', lang);
        const response = await fetch(searchUrl.toString(), { cache: 'no-store' });
        const payload = await response.json();
        if (!response.ok || !payload || payload.ok === false) {
          throw new Error((payload && payload.error) || 'search failed');
        }
        renderBestMatch(payload);
        await updateRepbaseBlock(query, payload);
        await setCytFrameToQuery(query);
      } catch (err) {
        resultEl.innerHTML = texts.searchFailed + (err && err.message ? err.message : 'unknown error');
        repbaseEl.innerHTML = texts.repbaseUnavailable;
      }
    }

    if (frame) {
      frame.addEventListener('load', function () {
        restyleCytFrame();
      });
    }

    if (resetBtn) {
      resetBtn.addEventListener('click', async function () {
        queryInput.value = '';
        if (typeField) typeField.value = 'all';
        window.history.replaceState({}, '', <?= json_encode(site_url_with_state('search.php', $lang, $renderer), JSON_UNESCAPED_UNICODE) ?>);
        await runSearch('');
      });
    }

    if (searchForm) {
      searchForm.addEventListener('submit', async function (evt) {
        evt.preventDefault();
        const query = queryInput.value.trim();
        const url = new URL(window.location.href);
        if (query) {
          url.searchParams.set('q', query);
        } else {
          url.searchParams.delete('q');
        }
        if (typeField) url.searchParams.set('type', typeField.value || 'all');
        url.searchParams.set('lang', lang);
        url.searchParams.set('renderer', renderer);
        window.history.replaceState({}, '', url.toString());
        await runSearch(query);
      });
    }

    runSearch(queryInput.value.trim());
    return;
  }

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
  const typeMap = { TE: texts.te, Disease: texts.disease, Function: texts.function, Paper: texts.paper };
  const relMap = { SUBFAMILY_OF: lang === 'en' ? 'contains' : '包含', EVIDENCE_RELATION: lang === 'en' ? 'literature support' : '文献支持', '与…相关': lang === 'en' ? 'associated with' : '与…相关', '促进': lang === 'en' ? 'promotes' : '促进', '参与': lang === 'en' ? 'participates in' : '参与', '调控': lang === 'en' ? 'regulates' : '调控', '影响': lang === 'en' ? 'affects' : '影响', '执行': lang === 'en' ? 'performs' : '执行', '介导': lang === 'en' ? 'mediates' : '介导', '报道': lang === 'en' ? 'reports' : '报道' };

  const cy = cytoscape({
    container: graphEl,
    elements: initialElements,
    wheelSensitivity: 0.2,
    style: [
      { selector: 'node', style: { 'label': 'data(label)', 'font-size': 12, 'min-zoomed-font-size': 9, 'text-valign': 'center', 'text-halign': 'center', 'background-color': function (ele) { return colorMap[ele.data('type')] || '#94a3b8'; }, 'color': '#0f172a', 'text-outline-width': 3, 'text-outline-color': '#fff', 'width': 'label', 'height': 'label', 'padding': '12px', 'text-wrap': 'wrap', 'text-max-width': 170, 'border-width': 2, 'border-color': '#fff', 'shape': 'round-rectangle' }},
      { selector: 'edge', style: { 'width': 2.4, 'line-color': '#4a6fe3', 'target-arrow-color': '#4a6fe3', 'target-arrow-shape': 'triangle', 'curve-style': 'bezier', 'label': function (ele) { return ele.data('relation') === 'EVIDENCE_RELATION' ? '' : (relMap[ele.data('relation')] || ele.data('relation') || ''); }, 'font-size': '10px', 'color': '#334155', 'text-background-color': 'rgba(255,255,255,0.92)', 'text-background-opacity': 1, 'text-background-padding': '2px', 'text-rotation': 'autorotate' }},
      { selector: '.active-node', style: { 'border-width': 5, 'border-color': '#0f172a', 'shadow-blur': 14, 'shadow-color': '#2563eb', 'shadow-opacity': 0.24 }},
      { selector: '.active-edge', style: { 'width': 4, 'line-color': '#1d4ed8', 'target-arrow-color': '#1d4ed8' }}
    ],
    layout: { name: 'preset', fit: true, padding: 50, animate: false }
  });

  function clearActive() { cy.nodes().removeClass('active-node'); cy.edges().removeClass('active-edge'); }
  function spreadNodesIfNeeded() {
    const nodes = cy.nodes().toArray();
    for (let iter = 0; iter < 8; iter += 1) {
      let moved = false;
      for (let i = 0; i < nodes.length; i += 1) {
        for (let j = i + 1; j < nodes.length; j += 1) {
          const a = nodes[i], b = nodes[j];
          const boxA = a.boundingBox({ includeLabels: true, includeOverlays: false });
          const boxB = b.boundingBox({ includeLabels: true, includeOverlays: false });
          const overlapX = Math.min(boxA.x2, boxB.x2) - Math.max(boxA.x1, boxB.x1);
          const overlapY = Math.min(boxA.y2, boxB.y2) - Math.max(boxA.y1, boxB.y1);
          if (overlapX <= 0 || overlapY <= 0) continue;
          moved = true;
          let dx = b.position('x') - a.position('x');
          let dy = b.position('y') - a.position('y');
          if (dx === 0 && dy === 0) { dx = 1; dy = 1; }
          const len = Math.hypot(dx, dy) || 1;
          const shift = Math.min(overlapX, overlapY) / 2 + 20;
          a.position({ x: a.position('x') - (dx / len) * shift, y: a.position('y') - (dy / len) * shift });
          b.position({ x: b.position('x') + (dx / len) * shift, y: b.position('y') + (dy / len) * shift });
        }
      }
      if (!moved) break;
    }
  }

  function setGraphElements(elements, fitPadding) {
    const cloned = JSON.parse(JSON.stringify(elements || []));
    cy.elements().remove();
    cy.add(cloned);
    const usePreset = cloned.filter(function (item) { return item && item.data && !item.data.source; }).every(function (item) { return !!item.position && Number.isFinite(item.position.x) && Number.isFinite(item.position.y); });
    const layout = usePreset ? { name: 'preset', fit: true, padding: fitPadding || 60, animate: false } : { name: 'cose', fit: true, padding: fitPadding || 95, animate: false, nodeDimensionsIncludeLabels: true, componentSpacing: 180, idealEdgeLength: 220, edgeElasticity: 100, nodeRepulsion: 320000, gravity: 28, numIter: 1600, initialTemp: 220, coolingFactor: 0.94, minTemp: 1 };
    const runner = cy.layout(layout);
    runner.on('layoutstop', function () { if (!usePreset) { spreadNodesIfNeeded(); cy.fit(undefined, fitPadding || 95); } });
    runner.run();
  }

  function showNode(node) {
    detailEl.innerHTML = '<strong>' + node.data('label') + '</strong>（' + (typeMap[node.data('type')] || node.data('type') || 'node') + '）<br>' + (node.data('description') || texts.emptyNode) + '<div style="margin-top:6px;color:#6b7f95;">' + texts.degree + node.degree() + '</div>';
  }
  function showEdge(edge) {
    const evidence = edge.data('evidence') || '';
    const pmids = Array.isArray(edge.data('pmids')) ? edge.data('pmids').filter(Boolean).join(', ') : '';
    const support = evidence || (pmids ? ('PMID: ' + pmids) : (lang === 'en' ? 'Not listed' : '当前未列出'));
    detailEl.innerHTML = '<strong>' + edge.source().data('label') + '</strong> → ' + (relMap[edge.data('relation')] || edge.data('relation') || texts.relation) + ' → <strong>' + edge.target().data('label') + '</strong><br>' + texts.evidence + support;
  }
  function renderBestMatch(payload) {
    const anchor = payload.anchor;
    if (!anchor) { resultEl.innerHTML = texts.noMatch; return; }
    resultEl.innerHTML = '<div><strong>' + anchor.name + '</strong>（' + (typeMap[anchor.type] || anchor.type) + '）</div>' + '<div style="margin-top:8px;color:#5e7288;">' + texts.best + '</div>' + (anchor.pmid ? '<div style="margin-top:8px;color:#5e7288;">' + texts.pmid + anchor.pmid + '</div>' : '') + (Array.isArray(payload.matches) && payload.matches.length > 1 ? '<div style="margin-top:10px;color:#5e7288;">' + texts.candidates + payload.matches.slice(1, 4).map(function (item) { return item.name; }).join('、') + '</div>' : '');
  }
  function cleanRepbaseLabel(value) { return String(value || '').replace(/<[^>]+>/g, '').trim().replace(/[.;,]+$/g, '').replace(/\s+/g, ' '); }
  function canonicalizeRepbaseLabel(value) { return cleanRepbaseLabel(value).toLowerCase().replace(/[_\-\s]/g, ''); }
  async function loadRepbaseData() {
    if (!repbaseDataPromise) {
      repbaseDataPromise = fetch('data/processed/te_repbase_db_matched.json').then(function (res) {
        if (!res.ok) throw new Error('Repbase data load failed');
        return res.json();
      });
    }
    return repbaseDataPromise;
  }
  function renderRepbaseCard(repbase, matchedName) {
    return [
      '<div><strong>' + texts.matchName + '</strong>' + matchedName + '</div>',
      '<div><strong>Repbase ID：</strong>' + (repbase.id || '-') + '</div>',
      '<div><strong>' + texts.canonicalName + '</strong>' + (repbase.name || repbase.id || '-') + '</div>',
      '<div><strong>' + texts.description + '</strong>' + (repbase.description || texts.noDescription) + '</div>',
      '<div><strong>' + texts.keywords + '</strong>' + ((repbase.keywords && repbase.keywords.length) ? repbase.keywords.join('，') : texts.noKeywords) + '</div>',
      '<div><strong>' + texts.species + '</strong>' + (repbase.species || texts.noSpecies) + '</div>',
      '<div><strong>' + texts.sequenceSummary + '</strong>' + ((repbase.sequence_summary && repbase.sequence_summary.raw) ? repbase.sequence_summary.raw : texts.noSequence) + '</div>',
      '<div><strong>' + texts.referenceCount + '</strong>' + ((repbase.references && repbase.references.length) ? repbase.references.length : 0) + '</div>'
    ].join('');
  }
  async function updateRepbaseBlock(query, payload) {
    if (!repbaseEl) return;
    const anchor = payload && payload.anchor ? payload.anchor : null;
    const candidateNames = [];
    
    // 增加 anchor.standard_name 判断
    if (anchor && anchor.type === 'TE') {
      if (anchor.standard_name) candidateNames.push(anchor.standard_name);
      if (anchor.name) candidateNames.push(anchor.name);
    }
    
    if (query) candidateNames.push(query);
    const uniqueNames = Array.from(new Set(candidateNames.filter(Boolean)));
    if (!uniqueNames.length) { repbaseEl.innerHTML = texts.repbaseDefault; return; }
    try {
      const repbasePayload = await loadRepbaseData();
      const entries = repbasePayload.entries || [];
      const entryById = new Map(entries.map(function (entry) { return [entry.id, entry]; }));
      let matchedId = null; let matchedName = null;
      uniqueNames.some(function (name) {
        const strictKey = cleanRepbaseLabel(name).toLowerCase();
        const canonicalKey = canonicalizeRepbaseLabel(name);
        matchedId = (repbasePayload.name_index && repbasePayload.name_index[strictKey]) || (repbasePayload.canonical_index && repbasePayload.canonical_index[canonicalKey]) || null;
        if (matchedId) { matchedName = name; return true; }
        return false;
      });
      if (!matchedId || !entryById.has(matchedId)) { repbaseEl.innerHTML = texts.repbaseMissing; return; }
      repbaseEl.innerHTML = renderRepbaseCard(entryById.get(matchedId), matchedName || matchedId);
    } catch (err) {
      repbaseEl.innerHTML = texts.repbaseError + (err && err.message ? err.message : 'unknown error');
    }
  }
  async function runSearch(query) {
    if (!query) {
      resultEl.innerHTML = texts.prompt;
      setGraphElements(initialElements, 50);
      detailEl.innerHTML = texts.graphDetailEmpty;
      updateRepbaseBlock('', null);
      return;
    }
    resultEl.innerHTML = texts.searching + ' <strong>' + query.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</strong> ...';
    try {
      // 构建完整的带有 q、type 和 lang 参数的请求 URL
      const searchUrl = new URL('api/graph.php', window.location.origin + window.location.pathname);
      searchUrl.searchParams.set('q', query);

      const typeField = document.querySelector('select[name="type"]');
      if (typeField && typeField.value !== 'all') {
        searchUrl.searchParams.set('type', typeField.value);
      }
      
      if (lang) {
        searchUrl.searchParams.set('lang', lang);
      }

      const response = await fetch(searchUrl.toString());
      const payload = await response.json();
      
      if (!response.ok || !payload.ok) throw new Error(payload.error || 'search failed');
      renderBestMatch(payload);
      if (payload.elements && payload.elements.length > 0) setGraphElements(payload.elements, 60); else setGraphElements(initialElements, 50);
      detailEl.innerHTML = texts.graphDetailEmpty;
      updateRepbaseBlock(query, payload);
    } catch (err) {
      resultEl.innerHTML = texts.searchFailed + (err && err.message ? err.message : 'unknown error');
      repbaseEl.innerHTML = texts.repbaseUnavailable;
    }
  }
  cy.on('tap', 'node', function (evt) { clearActive(); evt.target.addClass('active-node'); showNode(evt.target); });
  cy.on('tap', 'edge', function (evt) { clearActive(); evt.target.addClass('active-edge'); showEdge(evt.target); });
  cy.on('tap', function (evt) { if (evt.target === cy) { clearActive(); detailEl.innerHTML = texts.graphDetailEmpty; } });
  resetBtn.addEventListener('click', function () {
    queryInput.value = '';
    window.history.replaceState({}, '', <?= json_encode(site_url_with_lang('search.php', $lang) ?? 'search.php', JSON_UNESCAPED_UNICODE) ?>);
    resultEl.innerHTML = texts.resetState;
    if (repbaseEl) repbaseEl.innerHTML = texts.repbaseDefault;
    setGraphElements(initialElements, 50);
    detailEl.innerHTML = texts.graphDetailEmpty;
  });
  searchForm.addEventListener('submit', function (evt) {
    const query = queryInput.value.trim();
    if (!query) return;
    evt.preventDefault();
    const url = new URL(window.location.href);
    url.searchParams.set('q', query);
    const typeField = searchForm.querySelector('select[name="type"]');
    if (typeField) url.searchParams.set('type', typeField.value || 'all');
    url.searchParams.set('lang', lang);
    window.history.replaceState({}, '', url.toString());
    runSearch(query);
  });
  setGraphElements(initialElements, 50);
  const initialQuery = queryInput.value.trim();
  if (initialQuery) runSearch(initialQuery);
}());
</script>
<?php include __DIR__ . '/foot.php'; ?>
