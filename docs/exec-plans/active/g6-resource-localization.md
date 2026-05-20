# G6 Resource Localization

## 背景

Phase 2 browser smoke 已能打开 `preview.php?q=LINE1` 并收集页面证据，但当前失败证据显示浏览器无法加载外部 CDN 资源，例如 `@antv/g6` 和 `marked`。这会导致 G6 iframe 有尺寸但无 canvas/svg/children，看起来像白屏。

## 目标

- 让 G6 runtime 关键前端依赖可在本地 WAMP 环境稳定加载。
- Browser smoke 不再因为 CDN 被阻断而失败。
- 保留现有页面和 API 行为。

## 不做什么

- 本计划不修 Expand mode 的业务正确性。
- 本计划不重写 G6 runtime。
- 本计划不改变 Neo4j 或 MySQL 数据。

## 涉及文件范围

- `assets/html/preview_graph.html`
- `assets/js/renderers/g6/`
- 可能新增本地 vendor assets。
- `scripts/checks/check_g6_browser_smoke.py`

## 验收标准

- `preview.php?q=LINE1` 在 browser smoke 中能加载 G6 依赖。
- Smoke 输出不再包含 `ERR_NETWORK_ACCESS_DENIED` 的 G6 / marked CDN 错误。
- 图容器至少出现 canvas/svg/children，且 loader 不长期卡住。

## 验证命令

```powershell
python scripts/checks/check_g6_browser_smoke.py
```

## 执行记录

待执行。

## 残留风险

即使资源本地化后，Expand mode 仍需要单独的交互正确性计划。
