<?php
$pageTitle = '下载 - TEKG';
$activePage = 'download';

$sections = [
    [
        'title' => '原始数据',
        'desc' => '本部分提供知识图谱构建过程中使用的核心原始数据来源，包括文献抽取结果、转座子参考数据和谱系参考文本。',
        'items' => [
            [
                'name' => 'te_kg2.jsonl',
                'path' => 'data/raw/te_kg2.jsonl',
                'type' => 'JSONL',
                'desc' => '知识图谱构建所使用的主要结构化抽取源数据，包含转座元件、疾病、功能和文献信息。',
            ],
            [
                'name' => 'TE_Repbase.txt',
                'path' => 'data/raw/TE_Repbase.txt',
                'type' => 'TXT',
                'desc' => '从 Repbase 获取的人类转座子参考文件，可用于 TE 标准说明、别名与家族信息。',
            ],
            [
                'name' => 'tree.txt',
                'path' => 'data/raw/tree.txt',
                'type' => 'TXT',
                'desc' => '基于数据库文件生成的 TE 家族树状图参考文本，用于谱系结构整理。',
            ],
        ],
    ],
    [
        'title' => '处理后数据',
        'desc' => '面向图数据库构建与展示使用的标准化结果和结构化产物。',
        'items' => [
            [
                'name' => 'te_kg2_normalized_output.jsonl',
                'path' => 'data/processed/te_kg2_normalized_output.jsonl',
                'type' => 'JSONL',
                'desc' => '对 te_kg2 进行标准化和清洗后的结构化输出。',
            ],
            [
                'name' => 'te_kg2_graph_seed.json',
                'path' => 'data/processed/te_kg2_graph_seed.json',
                'type' => 'JSON',
                'desc' => '用于图数据库导入的图谱种子文件。',
            ],
            [
                'name' => 'tree_te_lineage.json',
                'path' => 'data/processed/tree_te_lineage.json',
                'type' => 'JSON',
                'desc' => '根据清洗后的 tree.txt 生成的 TE 谱系结构数据。',
            ],
            [
                'name' => 'tree_te_lineage.csv',
                'path' => 'data/processed/tree_te_lineage.csv',
                'type' => 'CSV',
                'desc' => 'TE 树状谱系的表格化导出版本，便于人工查看。',
            ],
            [
                'name' => 'te_kg2_normalization_report.json',
                'path' => 'data/processed/te_kg2_normalization_report.json',
                'type' => 'JSON',
                'desc' => 'te_kg2 标准化过程的统计报告。',
            ],
        ],
    ],
    [
        'title' => '术语表与标准化资源',
        'desc' => '面向中英映射、术语维护和界面展示使用的词表文件。',
        'items' => [
            [
                'name' => 'te_terminology.json',
                'path' => 'terminology/te_terminology.json',
                'type' => 'JSON',
                'desc' => '主术语表文件，包含节点名称与关系词的中英文映射。',
            ],
            [
                'name' => 'te_terminology.csv',
                'path' => 'terminology/te_terminology.csv',
                'type' => 'CSV',
                'desc' => '术语表的表格版本，便于人工维护与审阅。',
            ],
            [
                'name' => 'te_terminology_overrides.json',
                'path' => 'terminology/te_terminology_overrides.json',
                'type' => 'JSON',
                'desc' => '运行时优先覆盖的术语补丁文件，保存新增或修正过的映射。',
            ],
        ],
    ],
];

include __DIR__ . '/head.php';
?>
<section class="hero-card">
  <h2 class="page-title">下载</h2>
  <p class="page-desc">这里集中提供本数据库项目的主要原始数据、处理后数据与术语表资源，便于查阅、下载和复用。</p>
</section>

<section style="display:grid;gap:22px;">
  <?php foreach ($sections as $section): ?>
    <section class="content-card">
      <div style="margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid #e5edf7;">
        <h3 style="margin:0 0 8px;font-size:24px;"><?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?></h3>
        <p style="margin:0;color:#5e7288;line-height:1.7;"><?= htmlspecialchars($section['desc'], ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;">
        <?php foreach ($section['items'] as $item): ?>
          <article style="border:1px solid #dbe7f3;border-radius:18px;background:linear-gradient(180deg,#ffffff,#f8fbff);padding:18px;display:flex;flex-direction:column;gap:12px;min-height:210px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
              <strong style="font-size:18px;color:#19324d;"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></strong>
              <span style="display:inline-flex;align-items:center;justify-content:center;padding:6px 10px;border-radius:999px;background:#eef4ff;color:#2753b7;font-size:12px;font-weight:700;"><?= htmlspecialchars($item['type'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <p style="margin:0;color:#5e7288;line-height:1.75;flex:1;"><?= htmlspecialchars($item['desc'], ENT_QUOTES, 'UTF-8') ?></p>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
              <span style="font-size:13px;color:#7b8da3;word-break:break-all;"><?= htmlspecialchars($item['path'], ENT_QUOTES, 'UTF-8') ?></span>
              <a href="<?= htmlspecialchars($item['path'], ENT_QUOTES, 'UTF-8') ?>" download style="white-space:nowrap;padding:10px 16px;border-radius:14px;background:#2563eb;color:#fff;font-weight:700;">下载</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
</section>
<?php include __DIR__ . '/foot.php'; ?>
