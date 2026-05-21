# TE-KG Tech Debt Tracker

本文记录跨任务技术债。它不替代具体执行计划；每个条目应在后续拆成 `active/` 下的独立计划。

## 高优先级

### G6 Expand mode same-label entity disambiguation

- 状态：基础版已解决，见 `completed/g6-expand-same-label-disambiguation.md`。
- 已完成：Expand 请求携带 `expand_node_id`、`expand_node_type`、`expand_query`；后端按 node id -> type+query -> old q fallback 解析。
- 已覆盖：`Disease:Aging` vs `Function:Aging`，包括 API direct contract 和 browser smoke。
- 残留风险：当前样本只覆盖 Aging；更多同名跨类型实体需要扩展 smoke。当前精确定位使用 Neo4j `elementId()`，未来跨库或重建数据库场景可能需要稳定业务 id。

### G6 Expand mode 后续交互质量

- 现状：白屏 blocker、loader blocker、legend loading、L1HS / LINE-1 初始加载卡住、增量新增节点聚集问题已解除。
- 风险：expanded node 仍缺少明确视觉 affordance / collapse；复杂多节点连续扩展、expand 后切换 legend/filter/view state 再继续 expand 的 smoke 覆盖不足。
- 下一步：拆分独立 active plan，先定义 expand/collapse 交互 contract 和多步浏览器 smoke，再做最小 runtime 改动。

### G6 Expand mode 白屏与加载态

- 状态：已完成阶段性修复。
- 现状：`preview.php` 中 Expand mode 白屏、loader 长期卡住、iframe request abort、增量新增节点无布局坐标等 blocker 已由 browser smoke 覆盖。
- 下一步：保留 smoke 作为回归门禁；后续只在新的 active plan 中处理同名实体歧义和交互质量。

### G6 局部扩展接口不稳定

- 现象：当前局部扩展逻辑仍处在探索阶段，尚未形成可靠 contract。
- 风险：前端可能混用整图重载、iframe bridge 和局部增量合并，导致状态失真。
- 下一步：为 graph expand 行为写 API contract 和前端状态 contract。

### Browser smoke harness 基础版

- 现状：Phase 2 新增 `scripts/checks/check_g6_browser_smoke.py`。
- 风险：基础版只验证页面不白屏、无 `ReferenceError`、图容器存在；还不验证 Expand mode 的业务正确性。当前还可能因 CDN 被阻断失败。
- 下一步：执行 `active/g6-resource-localization.md`，再扩展 browser smoke 覆盖 legend apply、Show labels、节点点击和局部扩展结果。

### API contract harness 基础版

- 现状：Phase 2 新增 `scripts/checks/check_api_contracts.py`。
- 风险：当前覆盖 health、graph、taxonomy；expression JSON contract 尚未设计。
- 下一步：为 expression 建立明确 JSON endpoint 或单独 runtime data contract。

## 中优先级

### 旧 DB fallback 回归风险

- 现象：仓库历史中仍有 `tekg2`、`tekg21` 相关文件。
- 风险：未来修改可能重新引入旧 runtime fallback。
- 下一步：继续维护 runtime DB 配置检查，明确旧 DB 只能出现在 legacy docs/imports 中。

### 旧 expression path 回归风险

- 现象：历史路径 `data/raw/new_data/bulk_expression_web` 曾经作为 fallback 出现。
- 风险：重建 expression assets 或 fallback 逻辑时可能再次指向不存在路径。
- 下一步：新增或维护 expression path 检查，确保 runtime root 是 `data/bulk_expression_web`。

### Taxonomy 多真相源回归风险

- 现象：历史上 taxonomy 曾由多个文件和 runtime 路径同时提供。
- 风险：页面、API、G6、agent 可能显示不一致分类。
- 现状：Phase 3 新增 `scripts/checks/check_taxonomy_runtime_truth.py`。
- 下一步：持续维护 Neo4j/API canonical 检查，避免旧文件重新成为 runtime truth source。

### Phase 3 结构约束检查

- 现状：新增 `check_no_legacy_db_fallback.py`、`check_taxonomy_runtime_truth.py`、`check_g6_static_contract.py`、`check_g6_no_legacy_disease_node.py`、`check_docs_freshness.py`、`check_repository_entropy.py`。
- 风险：前五个是 hard fail；`check_repository_entropy.py` 初版是 warning-only，避免把历史大文件和参考 checkout 立刻变成阻塞项。
- 下一步：等清理策略明确后，再逐步把部分 entropy warning 升级为 hard fail。

## 低优先级

### 历史文档新鲜度

- 现象：`docs/architecture/` 中部分文档是历史 handoff 或专题记录。
- 风险：Codex 可能误把旧状态当当前事实。
- 现状：Phase 3 新增 `scripts/checks/check_docs_freshness.py`。
- 下一步：继续在索引中标注 canonical / reference / legacy。
