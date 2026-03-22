<?php
$pageTitle = '关于 - TE Database';
$activePage = 'about';
include __DIR__ . '/head.php';
?>
<section class="hero-card">
  <h2 class="page-title">关于</h2>
  <p class="page-desc">TEKG 是一个围绕转座元件构建的知识图谱数据库原型。我们希望把分散在文献、谱系参考和结构化抽取结果中的信息组织起来，让用户能够通过图谱浏览、关键词搜索与智能问答三种方式理解转座元件与疾病、功能及文献证据之间的联系。</p>
</section>

<section class="about-grid">
  <div class="about-stack">
    <div class="section-card">
      <h3>项目定位</h3>
      <p>本项目面向课程展示场景，围绕转座元件（Transposable Elements, TE）构建一个可查询、可视化、可问答的知识图谱数据库。系统从公开文献和参考资源中抽取结构化信息，再将其组织为图数据库，并通过网页端提供统一的浏览入口。</p>
    </div>

    <div class="section-card">
      <h3>建设思路</h3>
      <p>数据库建设分为几个连续阶段：先从文献与 TE 参考资源中整理数据，再对实体名称和关系进行标准化处理，随后导入 Neo4j 构建知识图谱，最后通过前端页面完成图谱展示、搜索和问答交互。当前系统已经形成了从原始数据到网页可视化的完整链路。</p>
      <ul>
        <li>文本侧负责抽取 TE、Disease、Function、Paper 等核心实体。</li>
        <li>图数据库侧负责表达 TE 谱系关系、生物学关系和文献证据关系。</li>
        <li>网页端负责图谱浏览、搜索、下载与多页导航展示。</li>
        <li>问答模块负责在本地知识库基础上生成带证据的回答。</li>
      </ul>
    </div>

    <div class="section-card">
      <h3>数据库结构</h3>
      <p>当前数据库以四类核心节点为基础：TE、Disease、Function 和 Paper。它们分别承载转座元件实体、疾病实体、生物学机制实体以及文献证据。关系层主要由 TE 内部谱系关系、实体间生物学关系以及文献证据关系构成，使图谱既能表达“谁和谁有关”，也能表达“为什么有关”。</p>
    </div>

    <div class="section-card">
      <h3>可视化与问答</h3>
      <p>网页端目前支持默认 TE 树视图、局部图谱展开、固定视图、搜索与多页展示。问答模块则采用本地知识库检索优先的策略：系统先从 Neo4j 中取回相关节点与关系，再将这些检索结果交给大模型整理成结构化回答，并尽量附带 PMID 或文献线索。</p>
    </div>
  </div>

  <div class="about-stack">
    <div class="section-card">
      <h3>当前系统骨架</h3>
      <div class="mini-stat">
        <div class="mini-stat-row"><span>核心节点</span><strong>TE / Disease / Function / Paper</strong></div>
        <div class="mini-stat-row"><span>核心关系</span><strong>SUBFAMILY_OF / BIO_RELATION / EVIDENCE_RELATION</strong></div>
        <div class="mini-stat-row"><span>图数据库</span><strong>Neo4j</strong></div>
        <div class="mini-stat-row"><span>网页环境</span><strong>WampServer + PHP</strong></div>
      </div>
    </div>

    <div class="section-card">
      <h3>数据来源</h3>
      <p>当前系统的重要数据来源包括：标准化后的 TE 文献抽取结果、扩展得到的 <code>te_kg2</code> 数据、<code>TE_Repbase</code> 提供的人类转座子参考文件，以及用于谱系结构整理的 <code>tree.txt</code>。这些资源共同支撑了 TE 树展示、关系查询和搜索页的 TE 说明能力。</p>
      <div class="note-box">在 TE 相关名称、谱系层级和说明性属性上，系统后续会优先参考 Repbase；图谱中的局部关系和问答检索仍以当前 Neo4j 数据为主。</div>
    </div>

    <div class="section-card">
      <h3>当前仍在完善的部分</h3>
      <p>目前系统已经具备可展示的完整主链路，但仍在持续优化 TE 实体标准化、中英文术语映射、Repbase 属性接入、搜索页条目展示，以及更大规模数据导入后的布局与性能表现。</p>
    </div>

    <div class="section-card">
      <h3>团队协作</h3>
      <p>项目由小组协作完成：文献获取与抽取、图数据库建模、网页前端、多语言术语整理和智能问答联调分别由不同成员分工推进。当前网站页面承担的是最终展示与系统串联的角色，用于课程答辩和项目讲解。</p>
    </div>
  </div>
</section>

<?php include __DIR__ . '/foot.php'; ?>
