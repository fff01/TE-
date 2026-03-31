# 交接提示词：TE 树默认视图问题

你现在是接手一个本地生物知识图谱项目的工程师，请基于以下上下文继续排障和实现，不要从零假设。

## 1. 我们在做什么数据库

我们在做的是一个 **转座元件（TE, Transposable Elements）知识图谱数据库**，主要用 **Neo4j** 存储和查询，前端提供图谱可视化和问答功能。

最初项目是围绕 **LINE-1** 文献做的局部知识图谱，后来扩展到更大范围的 TE 数据。当前数据库不只包含：

- `TE` 节点
- `Disease` 节点
- `Function` 节点
- `Paper` 节点

以及关系：

- `SUBFAMILY_OF`
- `BIO_RELATION`
- `EVIDENCE_RELATION`

现在又进一步引入了 `tree.txt`，目标是把 **TE 家族树** 本身导入数据库，并让默认演示图先展示 TE 树，再在点击具体 TE 后切换到更丰富的关系子图。

## 2. 我们数据库已有的功能是什么

当前项目已经具备这些能力：

- Neo4j 数据库已运行，且已有旧 LINE-1 数据和新 TE 扩库数据。
- 前端页面 `index_demo.html` 能显示图谱。
- 点击节点可通过 `api/graph.php` 拉取动态图子图。
- 右侧问答通过 `api/qa.php` 检索数据库，并调用本地 `llm_relay.py` 转发到 Qwen。
- 问答成功后，左侧会同步显示本次回答对应的局部子图。
- 边详情优先显示 `PMID`。
- 有固定视图模式，开启后点击节点只看详情不跳图。
- 前端已经接入术语表：
  - `terminology/te_terminology.json`
  - `terminology/te_terminology_overrides.json`
- 新数据 `te_kg2.jsonl` 已经过标准化并导入数据库。
- `tree.txt` 已被清洗成：
  - `tree_te_lineage.json`
  - `tree_te_lineage.csv`
  - `import_tree_te_lineage.cypher`

## 3. 我们待解决的问题是什么

当前最重要、尚未在浏览器里完全达到预期的问题是：

### 默认 demo 视图没有稳定呈现成理想的 TE 树

目标是：

- 默认页面打开时，只显示 **TE 节点与 TE 之间的树状关系**
- 不显示 `Disease / Function / Paper`
- 根节点是 **“人类转座子”**
- 默认只展示树的前四级
- 样式要接近 `tree.txt` 的层级结构
- 点击某个具体 TE（如 `LINE-1`、`ERV1`）后，再切换到 richer 的动态图，显示该 TE 与疾病、功能、文献等的关系

用户报告过的现象包括：

- 默认图里仍出现了非 TE 节点
- 节点被排成一条线
- 很多 TE 看起来都连向 `LINE-1`
- 默认情况下中心节点仍然像是 `LINE-1`，而不是“人类转座子”

另外，用户明确要求：

1. 忽略 `tree.txt` 中括号里的 `and subfamilies ...`
   - 例如 `L1M1 (and subfamilies ...)` 只保留 `L1M1`
2. 删除 `DIRS-like (未在文件中发现)`
3. 默认 demo 只展示前四级树
4. 点击某个 TE 后，才显示 richer 关系图

## 4. 为了解决目前的问题，最应该关注的文件是什么

最关键的文件是：

### 前端展示逻辑

- `D:\\wamp64\\www\\TE-\\index_demo.html`
  - 当前 demo 页面主文件
  - 默认图加载、树布局、点击节点行为、问答同步子图都在这里

- `D:\\wamp64\\www\\TE-\\graph_demo_data.js`
  - 当前默认 demo 的静态数据源

### TE 树生成逻辑

- `D:\\wamp64\\www\\TE-\\generate_tree_te_lineage.py`
  - 从 `tree.txt` 清洗出 TE 节点与树关系

- `D:\\wamp64\\www\\TE-\\tree_te_lineage.json`
  - 清洗后的树结构结果

- `D:\\wamp64\\www\\TE-\\import_tree_te_lineage.cypher`
  - TE 树导入 Neo4j 的 Cypher

- `D:\\wamp64\\www\\TE-\\generate_tree_demo_data.py`
  - 从树结构生成默认 demo 数据

### 后端动态图逻辑

- `D:\\wamp64\\www\\TE-\\api\\graph.php`
  - 点击节点后，前端会请求这里获取 richer 子图

### 术语表

- `D:\\wamp64\\www\\TE-\\terminology\\te_terminology.json`
- `D:\\wamp64\\www\\TE-\\terminology\\te_terminology_overrides.json`

## 5. 我们的工作环境是什么

- 操作系统：**Windows**
- Web 环境：**WampServer**
- 项目根目录：`D:\\wamp64\\www\\TE-`
- 前端访问地址：`http://localhost/TE-/index_demo.html`
- Neo4j 数据库：
  - HTTP: `http://127.0.0.1:7474/db/tekg/tx/commit`
  - 用户名：`neo4j`
  - 密码：`xjss9577`
- 本地 relay：
  - `http://127.0.0.1:18087/health`
- 问答模型通过：
  - `llm_relay.py`
  - 转发到 Qwen / DashScope

## 当前已知事实（很重要）

- `tree.txt` 实际是 UTF-8 可读的，PowerShell 里看起来乱码不代表文件坏了。
- `generate_tree_te_lineage.py` 已经收紧规则：
  - 会忽略 `and subfamilies ...`
  - 会删除 `DIRS-like`
  - 会避免 `SUBFAMILY_OF` 自环
- 当前重新生成后的树规模大致是：
  - `287` 个 TE 节点
  - `291` 条树关系
- `generate_tree_demo_data.py` 已经把默认 demo 限定到树的前四级，当前 demo 元素大约：
  - `177`
- `index_demo.html` 已经尝试切成树布局，并在默认图使用 `default-tree` 模式
- 但用户反馈浏览器中的实际呈现**仍然没有达到预期**

## 请你做什么

请你不要从零重做整个系统，而是：

1. 审查 `index_demo.html` 当前的默认 demo 加载逻辑
2. 审查 `graph_demo_data.js` 当前元素是否真的只有 TE 树前四级
3. 审查 `generate_tree_demo_data.py` 与 `generate_tree_te_lineage.py` 是否完全符合用户要求
4. 找出为什么浏览器实际呈现仍然不像理想的 TE 树
5. 给出最小、最稳的修复方案

重点不是继续扩功能，而是：

> **让默认首页稳定显示“前四级纯 TE 树”，点击 TE 后再进入 richer 子图。**
