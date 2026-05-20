# G6 Graph Runtime

本文记录 TE-KG 图谱运行时的当前结构。它不是完整代码说明，而是给 Codex 和人工维护者的最短可用地图。

## 当前入口

- 页面入口：`preview.php`
- 图谱 API：`api/graph.php`
- 图谱服务：`api/graph_service.php`
- iframe 页面：`assets/html/preview_graph.html`
- G6 runtime：`assets/js/renderers/g6/`

## 当前交互模型

- `preview.php` 负责页面壳、查询栏、图例、顶部开关和父页面状态。
- iframe 内 G6 runner 负责实际图谱渲染。
- 父页面和 iframe 之间通过 bridge 通信。
- 图例支持 entity / relation 两种模式，并通过 Apply 应用筛选。
- `Fixed view`、`Expand mode`、`Show names`、`Show labels` 应尽量走轻量状态更新，不应触发整图重载。

## 当前风险

- G6 browser smoke 已能暴露白屏和 loader 卡住证据。
- 当前本地 smoke 环境可能阻断 CDN 资源，例如 `@antv/g6` 和 `marked`。
- `Expand mode` 的业务正确性仍是独立技术债，不应在没有 browser evidence 的情况下继续盲修。

## 相关检查

- `scripts/checks/check_g6_browser_smoke.py`
- `scripts/checks/check_g6_static_contract.py`
- `scripts/checks/check_g6_no_legacy_disease_node.py`
- `scripts/checks/check_g6_relation_legend_expand_mode.py`
- `scripts/checks/check_g6_legend_expand_tree_fixes.py`
