# G6 Expand Mode White Screen Fix

状态：completed

日期：2026-05-21

## 背景

`preview.php?q=LINE1` 的 G6 Expand mode 曾在点击非中心节点后导致 iframe canvas 清空、图谱白屏，或扩展请求被浏览器中止后停留在异常状态。该问题与 API contract 本身无关，定位阶段确认直接 API 请求可以返回有效 payload。

## 根因

- Parent 侧 `expandSelectedNode()` 在 expand 期间调用 `ensureDynamicFrame(buildCurrentGraphRequest())`，可能重设 iframe `src`，从而中止正在进行的 `bridge.expandGraph()` 请求。
- iframe 侧 `expandGraph()` 合并了新增 graph data，但没有通过 G6 增量 API 将新增节点和边提交到当前 canvas。
- Parent 侧 expand 成功后又调用整图 `renderDynamicElementsFromCache()`，容易把局部扩展重新拉回整图重渲染路径。

## 修复范围

- `assets/js/renderers/g6/index-g6.bootstrap.js`
  - expand 时优先复用现有 `dynamicFrame`。
  - expand 成功后不再触发 parent 侧整图重渲染。
- `assets/js/renderers/g6/index-g6-shared.js`
  - `expandGraph()` 合并数据后使用 `graph.addNodeData()`、`graph.addEdgeData()` 和 `await graph.draw()` 局部追加。
- `scripts/checks/check_g6_expand_mode_smoke.py`
  - 新增 Expand mode browser smoke，覆盖初始 canvas、开启 Expand mode、点击非中心节点、canvas 保持、状态计数不异常清零、无 abort/blank 失败。
- `scripts/checks/check_g6_relation_legend_expand_mode.py`
  - 更新静态契约，要求 expand 路径保留 G6 增量追加 API。

## 非目标

- 未修改 `api/graph.php`。
- 未修改 `api/graph_service.php`。
- 未修改 `api/agent/`。
- 未修改 taxonomy runtime。
- 未修改 expression runtime。
- 未继续优化 expand 后布局质量。

## 验证

```powershell
python scripts/checks/check_g6_browser_smoke.py
python scripts/checks/check_g6_expand_mode_smoke.py
python scripts/checks/check_g6_static_contract.py
python scripts/checks/check_g6_relation_legend_expand_mode.py
python scripts/checks/check_g6_legend_expand_tree_fixes.py
python scripts/checks/check_g6_no_legacy_disease_node.py
python scripts/checks/check_api_contracts.py
node --check assets/js/renderers/g6/index-g6.bootstrap.js
node --check assets/js/renderers/g6/index-g6-shared.js
node --check assets/js/renderers/g6/index-g6-embed.js
```

以上检查在本轮收口验收中通过。

## 残留风险

- Expand 后布局质量仍需单独视觉 QA。
- 多 node type / 多 query 的展开质量仍需补充场景覆盖。
- 复杂交互序列，例如 expand 后切换 legend/filter/view state，再继续 expand，仍缺专项 smoke。
