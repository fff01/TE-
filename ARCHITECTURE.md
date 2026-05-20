# TE-KG Architecture Entry

本文是 TE-KG 架构入口页，只保留地图和当前硬事实。详细说明请继续阅读 `docs/architecture/` 下的专题文档。

## 当前事实

- TE-KG 是本地 PHP + browser JS + Neo4j + MySQL 项目。
- 当前 Neo4j runtime database 是 `tekg3`。
- root runtime pages 当前仍保留在项目根目录，例如 `index.php`、`browse.php`、`preview.php`、`expression.php`。
- TE taxonomy runtime 以 Neo4j / `api/taxonomy.php` 为准。
- Expression runtime 数据根目录是 `data/bulk_expression_web`。
- 非 agent 任务默认不主要修改 `api/agent/`。

## 先读这些

1. `AGENTS.md`
2. `docs/architecture/index.md`
3. `docs/architecture/current_system.md`
4. `docs/architecture/graph_runtime.md`
5. `docs/architecture/data_sources.md`
6. `docs/architecture/database_contract.md`
7. `docs/architecture/frontend_contract.md`

## Harness 入口

- 执行计划：`docs/exec-plans/`
- 质量评分：`docs/QUALITY_SCORE.md`
- 可靠性说明：`docs/RELIABILITY.md`
- 可运行检查：`scripts/checks/`

旧 handoff 和专题记录可以帮助理解历史，但当前事实以本入口、`current_system.md` 和可运行检查为准。
