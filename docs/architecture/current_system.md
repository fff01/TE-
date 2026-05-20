# TE-KG Current System

本文记录 TE-KG 当前系统状态。它是 Codex harness 的当前事实入口；旧 handoff 和历史计划只能作为背景，不能自动代表 live code。

## 系统形态

- TE-KG 当前是本地 PHP + browser JS + Neo4j + MySQL 项目。
- WAMP 提供 PHP runtime，root pages 仍位于项目根目录。
- 当前 Neo4j runtime target 是 `tekg3`。
- `api/runtime_config.php` 和 `api/config.local.php` 负责 runtime 配置。
- 路径抽象已经存在：PHP 使用 `path_config.php`，browser JS 使用 `assets/js/tekg_paths.php`，Python 使用 `scripts/path_helpers.py`。

## Root pages

主要页面包括：

- `index.php`：首页。
- `browse.php`：TE browse 页面。
- `preview.php`：TE-KG / G6 graph workspace。
- `path_finder.php`：实体路径查找页面。
- `expression.php`、`expression_detail.php`：表达数据页面。
- `genomic.php`、`epigenetics.php`、`download.php`：其他数据入口。
- `agent.php`：智能问答入口。

当前阶段不要为了整理结构而移动 root runtime pages。

## API

- 图谱查询入口：`api/graph.php`。
- 图谱核心服务：`api/graph_service.php`。
- taxonomy 查询：`api/taxonomy.php`。
- expression 数据：`api/expression_data.php`、`api/expression_repository.php`。
- health / metrics：`api/health.php`、`api/te_metrics.php`。
- agent 相关 API 位于 `api/agent/`，非 agent 任务不要默认改动。

## Graph / G6

- `preview.php` 是当前 TE-KG 图谱主页面。
- G6 runtime 文件位于 `assets/js/renderers/g6/`。
- 当前 G6 仍有高价值技术债：`Expand mode` 局部扩展、loader state、iframe bridge、legend filtering 需要 browser smoke harness 支撑。
- 修 G6 时应先确认 API payload 和前端状态，再改渲染逻辑。

## Data and taxonomy

- TE taxonomy runtime 规则以 Neo4j/API 为准。
- 不应新增多个 taxonomy runtime truth source。
- taxonomy 和 terminology 已迁移到 `data/taxonomy/` 和 `data/terminology/`。
- 首页 taxonomy / ring chart 相关缓存和报告位于 `data/processed/`，但不能替代 live DB 验证。

## Expression

- 当前 expression asset root 是 `data/bulk_expression_web`。
- 不要恢复旧路径 `data/raw/new_data/bulk_expression_web` 作为 runtime 根目录。
- MySQL summary tables 仍是 expression 页面运行的重要依赖。

## Scripts and imports

- Python path helper 位于 `scripts/path_helpers.py`。
- 构建、检查和迁移脚本位于 `scripts/`。
- Neo4j import 历史材料位于 `imports/`，其中旧 tekg2 相关文件默认视作历史参考，不应作为 runtime 配置来源。

## Agent boundary

- `docs/architecture/agent_gpt55_handoff.md` 面向专门处理智能问答 agent 的 AI。
- 本 harness 默认服务整个 TE-KG 项目，尤其是非 agent 页面、API、数据路径和图谱运行时。
- 除非任务明确要求 agent，不要把 `api/agent/`、`assets/js/pages/agent.js` 作为默认修改范围。

## Current harness rule

长期事实写入仓库文档；复杂任务写入 `docs/exec-plans/`；验证规则逐步沉淀到 `scripts/checks/`。不要依赖聊天记录作为唯一上下文。
