# TE-KG Reliability

本文说明 TE-KG 当前可观测性和可靠性检查。目标是让 Codex 先拿证据，再判断问题在哪里。

## 核心检查

```powershell
python scripts/checks/check_neo4j_tekg3.py
python scripts/checks/check_api_contracts.py
python scripts/checks/check_g6_browser_smoke.py
python scripts/checks/check_g6_expand_mode_smoke.py
python scripts/checks/check_runtime_db_config.py
python scripts/checks/check_expression_paths.py
python scripts/checks/check_taxonomy_runtime_consistency.py
python scripts/checks/check_no_legacy_db_fallback.py
python scripts/checks/check_taxonomy_runtime_truth.py
python scripts/checks/check_docs_freshness.py
```

## 失败含义

- `check_neo4j_tekg3.py` 失败：优先检查 Neo4j 是否启动、`api/config.local.php` 是否指向 `tekg3`。
- `check_api_contracts.py` 失败：说明 PHP/WAMP/API payload 有问题，不要先修前端。
- `check_g6_browser_smoke.py` 失败：说明浏览器层或 G6 runtime 有证据异常；需要看 console、failed requests、loader 和 graph container。
- `check_g6_expand_mode_smoke.py` 失败：说明 `preview.php` Expand mode 在 parent/iframe bridge、局部追加、loader/canvas 状态或请求中止路径上可能回归；不要先改 API，先看 smoke 输出中的 canvas、nodes/edges、console 和 failed requests 证据。
- `check_runtime_db_config.py` 或 `check_no_legacy_db_fallback.py` 失败：说明旧 DB fallback 可能回流。
- `check_expression_paths.py` 失败：说明 expression runtime path 可能回到旧目录。
- `check_taxonomy_runtime_consistency.py` 或 `check_taxonomy_runtime_truth.py` 失败：说明 taxonomy canonical 规则可能被破坏。

## 当前已知可靠性风险

- Browser smoke 可能因外部 CDN 被阻断失败，尤其是 `@antv/g6` 和 `marked`。
- 这类失败不等于 Neo4j 或 API 失败；应优先按 `docs/exec-plans/active/g6-resource-localization.md` 处理。
- G6 resource loading blocker 已解除；`@antv/g6` 和 `marked` 现在由本地 vendor asset 提供，browser smoke 不应依赖外部 CDN。
- G6 legend loading blocker 已解除；`check_g6_browser_smoke.py` 会验证 legend 不停留在 `Loading legend...`。
- `L1HS` / `LINE-1` 初始进入图谱时 Preparing / Loading legend 卡住的问题已通过 render bridge timeout 缓解，避免大图 `graph.render()` 长时间不 resolve 时阻塞 parent loader。
- Expand mode 白屏 blocker 已解除，并由 `scripts/checks/check_g6_expand_mode_smoke.py` 覆盖保留中心图、非中心节点扩展、无 iframe blank / `ERR_ABORTED` 的基础回归路径。
- Expand mode 新增节点聚集问题已解除；`scripts/checks/check_g6_expand_layout_smoke.py` 覆盖增量追加后 `draw` / `layout` 路径和新增节点坐标分布。
- Expand mode same-label entity disambiguation 基础版已解决：Expand 请求会携带 `expand_node_id`、`expand_node_type`、`expand_query`，后端优先按 clicked node 精确扩展。`Disease:Aging` vs `Function:Aging` 由 `check_g6_expand_disambiguation_smoke.py` 和 `check_api_contracts.py` 覆盖。
- Same-label disambiguation 残留风险：当前 smoke 主要覆盖 Aging；更多同名跨类型实体样本仍需扩展。当前精确定位使用 Neo4j `elementId()`，适合本地单库 runtime，未来如需跨库稳定链接可能要引入稳定业务 id。
- Expanded node 的视觉 affordance / collapse、复杂多节点连续扩展、多类型展开质量仍需后续单独计划处理。
- G6 subgraph export v1 已完成：当前可见子图可导出 CSV，当前画布可导出 PNG，Export toolbar 已收敛为单按钮菜单，并由 `scripts/checks/check_g6_subgraph_export_smoke.py` 覆盖。SVG 仍是 disabled 的 `SVG Soon`，后续 v2 另行调研。

## 运行前提

- WAMP 由用户手动启动。
- Neo4j 由用户手动启动。
- 默认 base URL 是 `http://127.0.0.1/TE-`，可用 `TEKG_BASE_URL` 覆盖。
