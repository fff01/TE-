# TE-KG Architecture Index

本页是 TE-KG 架构文档目录。它用于快速定位当前事实、历史 handoff、专题设计和技术债。旧文档可能包含过时信息；处理任务时应优先确认 live code 和 `current_system.md`。

## 当前入口

- `../../ARCHITECTURE.md`：根目录短架构入口。
- `current_system.md`：当前系统概览，优先级高于旧 handoff。
- `graph_runtime.md`：G6 / TE-KG 图谱运行时结构。
- `data_sources.md`：当前数据源、路径和 canonical 规则。
- `database_contract.md`：Neo4j / API contract。
- `frontend_contract.md`：非 agent 前端页面和交互约束。
- `project_full_gpt55_handoff.md`：全项目历史 handoff，适合快速了解背景，但不要假设完全最新。
- `agent_gpt55_handoff.md`：智能问答 agent 子系统 handoff，仅在明确处理 agent 时作为主要输入。
- `g6-development-rules.md`：G6 图谱开发约束和经验。
- `te_taxonomy_runtime_canonical_2026-05-16.md`：TE taxonomy runtime 规则记录。

## 专题文档

- `targets.md`：前端与数据探索能力目标。
- `network_explorer_next_tasks.md`：Network / TE-KG 图谱后续任务。
- `kg_database_next_directions_2026-05-17.md`：知识图谱数据库后续方向。
- `project_core_risk_review_2026-05-16.md`：核心风险审查。
- `project_folder_simplification_2026-05-17.md`：项目文件夹精简建议。
- `php_frontend_extraction_plan.md`：PHP/frontend 拆分计划。
- `path_refactor_audit.md`：路径抽象与迁移审查。
- `sequence_repbase_plan.md`、`repbase_sequence_structure_plan.md`：Repbase sequence 相关计划。
- `jbrowse_recovery_plan.md`：JBrowse 恢复计划。

## 文件结构与历史记录

- `folder_structure_target.md`：目标目录结构。
- `folder_cleanup_step1_inventory.md`：文件夹清理盘点。
- `project-structure-audit.md`：结构审查。
- `agent_differentiation_direction.md`：agent 与非 agent 职责边界方向。
- `tekg_agent_development_guide.md`：agent 开发参考。

## 执行计划系统

- `../exec-plans/README.md`：执行计划格式和使用规则。
- `../exec-plans/active/`：正在执行或准备执行的计划。
- `../exec-plans/completed/`：已完成计划。
- `../exec-plans/tech-debt-tracker.md`：技术债跟踪。
- `../QUALITY_SCORE.md`：模块质量评分。
- `../RELIABILITY.md`：可靠性检查和失败诊断。
- `../design-docs/index.md`：未来设计文档入口。
- `../generated/README.md`：可生成系统快照入口。
- `../references/README.md`：参考资料索引。

## 使用规则

- 先读当前入口，再读专题文档。
- 任何旧文档都必须用 live code 验证。
- 非 agent 工作默认不修改 agent 子系统。
- 架构事实、禁区和长期决策应写回文档，不能只留在聊天记录里。
