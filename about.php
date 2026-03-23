<?php
require_once __DIR__ . '/site_i18n.php';
$lang = site_lang();
$pageTitle = site_t(['zh' => '关于 - TEKG', 'en' => 'About - TEKG'], $lang);
$activePage = 'about';
include __DIR__ . '/head.php';
?>
<section class="hero-card">
  <h2 class="page-title"><?= htmlspecialchars(site_t(['zh' => '关于', 'en' => 'About'], $lang), ENT_QUOTES, 'UTF-8') ?></h2>
  <p class="page-desc">
    <?= htmlspecialchars(site_t([
      'zh' => 'TE-KG 是一个围绕转座元件构建的知识图谱数据库原型，用于组织 TE、疾病、功能机制与文献证据之间的结构化关系，并通过图谱浏览、实体检索与智能问答进行展示。',
      'en' => 'TE-KG is a prototype knowledge graph database centered on transposable elements. It organizes structured relationships among TEs, diseases, functions/mechanisms, and literature evidence through graph exploration, entity search, and QA.'
    ], $lang), ENT_QUOTES, 'UTF-8') ?>
  </p>
</section>

<section class="about-grid">
  <div class="about-stack">
    <div class="section-card">
      <h3><?= htmlspecialchars(site_t(['zh' => '项目定位', 'en' => 'Project Positioning'], $lang), ENT_QUOTES, 'UTF-8') ?></h3>
      <p><?= htmlspecialchars(site_t([
        'zh' => '本项目面向课程展示与数据库原型验证场景，目标是把分散在文献、参考资源和抽取结果中的 TE 相关信息组织成可查询、可解释、可视化的知识图谱系统。',
        'en' => 'This project serves as a course demo and database prototype. Its goal is to organize TE-related information scattered across literature, reference resources, and extraction results into a queryable, explainable, and visual knowledge graph system.'
      ], $lang), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="section-card">
      <h3><?= htmlspecialchars(site_t(['zh' => '建设思路', 'en' => 'Construction Approach'], $lang), ENT_QUOTES, 'UTF-8') ?></h3>
      <p><?= htmlspecialchars(site_t([
        'zh' => '系统从文献抽取与参考资源整理开始，对实体名称、关系词和描述进行标准化处理，再导入图数据库，最终通过多页面网站提供图谱预览、搜索、下载与问答能力。',
        'en' => 'The system starts from literature extraction and reference resource curation, standardizes entity names, relations, and descriptions, imports the results into a graph database, and finally exposes graph preview, search, download, and QA through a multi-page website.'
      ], $lang), ENT_QUOTES, 'UTF-8') ?></p>
      <ul>
        <li><?= htmlspecialchars(site_t(['zh' => '文本侧负责抽取 TE、疾病、功能和文献实体。', 'en' => 'The text side is responsible for extracting TE, disease, function, and literature entities.'], $lang), ENT_QUOTES, 'UTF-8') ?></li>
        <li><?= htmlspecialchars(site_t(['zh' => '图数据库负责组织层级关系、生物学关系与证据关系。', 'en' => 'The graph database organizes lineage, biological, and evidence relations.'], $lang), ENT_QUOTES, 'UTF-8') ?></li>
        <li><?= htmlspecialchars(site_t(['zh' => '网页端负责图谱展示、实体搜索、资源下载与答辩演示。', 'en' => 'The web layer supports graph display, entity search, data download, and presentation.'], $lang), ENT_QUOTES, 'UTF-8') ?></li>
      </ul>
    </div>

    <div class="section-card">
      <h3><?= htmlspecialchars(site_t(['zh' => '数据库结构', 'en' => 'Database Structure'], $lang), ENT_QUOTES, 'UTF-8') ?></h3>
      <p><?= htmlspecialchars(site_t([
        'zh' => '当前数据库以 TE、Disease、Function 和 Paper 四类核心节点为基础。系统同时维护 TE 层级关系、跨实体生物学关系以及文献证据关系，使图谱既能回答“谁和谁有关”，也能回答“为什么有关”。',
        'en' => 'The current database is built around four core node types: TE, Disease, Function, and Paper. It maintains TE lineage relations, cross-entity biological relations, and literature evidence relations, allowing the graph to answer both “what is connected” and “why it is connected.”'
      ], $lang), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="section-card">
      <h3><?= htmlspecialchars(site_t(['zh' => '可视化与问答', 'en' => 'Visualization and QA'], $lang), ENT_QUOTES, 'UTF-8') ?></h3>
      <p><?= htmlspecialchars(site_t([
        'zh' => '前端支持默认 TE 树、局部图谱展开、搜索驱动的局部关系图，以及结合图数据库检索结果的智能问答。问答模块优先基于本地知识图谱上下文回答，并可切换模型与回答策略。',
        'en' => 'The frontend supports a default TE tree, local graph expansion, search-driven local relationship views, and graph-grounded QA. The QA module prioritizes local graph context and can switch between response strategies and models.'
      ], $lang), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  </div>

  <div class="about-stack">
    <div class="section-card">
      <h3><?= htmlspecialchars(site_t(['zh' => '当前系统骨架', 'en' => 'Current System Skeleton'], $lang), ENT_QUOTES, 'UTF-8') ?></h3>
      <div class="mini-stat">
        <div class="mini-stat-row"><span><?= htmlspecialchars(site_t(['zh' => '核心节点', 'en' => 'Core nodes'], $lang), ENT_QUOTES, 'UTF-8') ?></span><strong>TE / Disease / Function / Paper</strong></div>
        <div class="mini-stat-row"><span><?= htmlspecialchars(site_t(['zh' => '核心关系', 'en' => 'Core relations'], $lang), ENT_QUOTES, 'UTF-8') ?></span><strong>SUBFAMILY_OF / BIO_RELATION / EVIDENCE_RELATION</strong></div>
        <div class="mini-stat-row"><span><?= htmlspecialchars(site_t(['zh' => '图数据库', 'en' => 'Graph database'], $lang), ENT_QUOTES, 'UTF-8') ?></span><strong>Neo4j</strong></div>
        <div class="mini-stat-row"><span><?= htmlspecialchars(site_t(['zh' => '网页环境', 'en' => 'Web environment'], $lang), ENT_QUOTES, 'UTF-8') ?></span><strong>WampServer + PHP</strong></div>
      </div>
    </div>

    <div class="section-card">
      <h3><?= htmlspecialchars(site_t(['zh' => '数据来源', 'en' => 'Data Sources'], $lang), ENT_QUOTES, 'UTF-8') ?></h3>
      <p><?= htmlspecialchars(site_t([
        'zh' => '当前系统主要整合了标准化后的 TE 文献抽取结果、扩展得到的 te_kg2 数据、来自 Repbase 的 TE 参考文件，以及用于谱系整理的 tree.txt。这些资源共同支撑图谱构建、条目搜索和 TE 说明展示。',
        'en' => 'The current system integrates normalized TE literature extraction results, the expanded te_kg2 dataset, TE reference records from Repbase, and tree.txt lineage resources. Together they support graph construction, search, and TE description display.'
      ], $lang), ENT_QUOTES, 'UTF-8') ?></p>
      <div class="note-box"><?= htmlspecialchars(site_t([
        'zh' => '在 TE 名称、谱系层级和说明性属性上，系统优先参考 Repbase；图谱中的实体关系和问答上下文则以当前 Neo4j 数据为主。',
        'en' => 'For TE names, lineage levels, and descriptive attributes, the system prioritizes Repbase. Graph relations and QA context are primarily based on the current Neo4j data.'
      ], $lang), ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <div class="section-card">
      <h3><?= htmlspecialchars(site_t(['zh' => '仍在完善的部分', 'en' => 'Ongoing Improvements'], $lang), ENT_QUOTES, 'UTF-8') ?></h3>
      <p><?= htmlspecialchars(site_t([
        'zh' => '当前系统已经具备可展示的主链路，但仍在持续优化 TE 标准化、中英文术语映射、Repbase 参考区块、搜索体验，以及大规模图谱展开时的性能表现。',
        'en' => 'The main workflow is already presentation-ready, while TE standardization, bilingual terminology mapping, the Repbase reference block, search experience, and large-graph performance are still being refined.'
      ], $lang), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="section-card">
      <h3><?= htmlspecialchars(site_t(['zh' => '团队协作', 'en' => 'Team Collaboration'], $lang), ENT_QUOTES, 'UTF-8') ?></h3>
      <p><?= htmlspecialchars(site_t([
        'zh' => '项目通过分工协作推进，包括文献抽取、数据标准化、图数据库建模、前端展示和问答联调等模块。当前网站承担的是系统整合与展示入口的角色，用于课程答辩和功能演示。',
        'en' => 'The project moves forward through collaboration across literature extraction, data standardization, graph modeling, frontend presentation, and QA integration. The current website serves as the integration and presentation layer for demonstration.'
      ], $lang), ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  </div>
</section>

<?php include __DIR__ . '/foot.php'; ?>
