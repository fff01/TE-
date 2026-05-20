# TE-KG Codex Entry

本文件是 Codex 进入 TE-KG 仓库时的短入口页。它不是百科全书；需要细节时按链接继续阅读。

## 项目定位

- TE-KG 是本地 PHP + browser JS + Neo4j + MySQL 项目。
- 当前运行页面仍位于项目根目录，例如 `index.php`、`browse.php`、`preview.php`、`expression.php`。
- 当前 Neo4j runtime target 是 `tekg3`，本地配置来自 `api/config.local.php` 和 `api/runtime_config.php`。
- 非 agent 主任务应主要覆盖普通数据库页面、API、数据路径、G6 图谱和前端体验；不要把 agent 子系统当成默认修改范围。

## 先读这些

1. `docs/architecture/index.md`
2. `docs/architecture/current_system.md`
3. `ARCHITECTURE.md`
4. `docs/architecture/project_full_gpt55_handoff.md`
5. 如任务明确涉及智能问答，再读 `docs/architecture/agent_gpt55_handoff.md`

## 核心入口

- 页面：`index.php`、`browse.php`、`preview.php`、`expression.php`、`expression_detail.php`、`path_finder.php`
- 图谱 API：`api/graph.php`、`api/graph_service.php`
- 图谱前端：`assets/js/renderers/g6/`
- taxonomy API：`api/taxonomy.php`
- expression API：`api/expression_data.php`、`api/expression_repository.php`
- 路径配置：`path_config.php`、`assets/js/tekg_paths.php`、`scripts/path_helpers.py`

## 当前硬约束

- 不要把 runtime fallback 改回 `tekg2` 或 `tekg21`。
- 不要新增第二套 TE taxonomy runtime truth source；当前 taxonomy runtime 规则以 Neo4j/API 为准。
- 不要把 `data/raw/new_data/bulk_expression_web` 重新作为 expression runtime 根目录；当前根目录是 `data/bulk_expression_web`。
- 不要在没有验证的情况下重构 root runtime pages。
- 修 G6 时先确认 API payload、iframe bridge、loader state、legend state，再改渲染逻辑。

## 常用检查

```powershell
php -l preview.php
php -l api/graph.php
php -l api/graph_service.php
node --check assets/js/renderers/g6/index-g6.bootstrap.js
node --check assets/js/renderers/g6/index-g6-shared.js
node --check assets/js/renderers/g6/index-g6-embed.js
python scripts/checks/check_runtime_db_config.py
python scripts/checks/check_neo4j_tekg3.py
python scripts/checks/check_api_contracts.py
python scripts/checks/check_g6_browser_smoke.py
python scripts/checks/check_no_legacy_db_fallback.py
python scripts/checks/check_taxonomy_runtime_truth.py
python scripts/checks/check_g6_static_contract.py
python scripts/checks/check_g6_no_legacy_disease_node.py
python scripts/checks/check_docs_freshness.py
python scripts/checks/check_g6_relation_legend_expand_mode.py
python scripts/checks/check_g6_legend_expand_tree_fixes.py
```

## 工作方式

- 复杂任务先在 `docs/exec-plans/active/` 建执行计划。
- 完成后将计划移到 `docs/exec-plans/completed/`，并记录验证结果。
- 发现结构性问题时，更新 `docs/exec-plans/tech-debt-tracker.md`。
- 不把聊天记录当长期事实；长期事实必须写回仓库文档。
- 大改按三角色自查：`Implementer` 做实现，`Reviewer` 找 bug/架构风险，`Verifier` 跑命令并记录证据。
