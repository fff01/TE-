# Frontend Contract

本文记录非 agent 前端的当前约束。目标是让页面改造保持一致，不产生新的视觉系统和运行时分叉。

## 页面结构

- root runtime pages 当前保留在项目根目录。
- 典型页面包括 `index.php`、`browse.php`、`preview.php`、`expression.php`、`path_finder.php`。
- 新页面优先复用现有 `head.php`、`proto-container`、panel、table、button、filter 结构。

## 图谱页面

- `preview.php` 是 TE-KG / Network Explorer 的当前入口。
- G6 图例筛选通过 Apply 应用，不应每次勾选立即重绘。
- `Show labels` 默认关闭。
- `Fixed view` 和 `Expand mode` 是状态按钮，不应把图谱重新加载成白屏。

## 首页

- 首页 taxonomy / ring chart 是数据入口，不是单纯装饰。
- 首页统计应优先来自轻量 API 或缓存，避免页面加载时做重查询。

## Browse / Expression

- Browse 当前仍是 TE-oriented，不扩展为全实体搜索页。
- Expression 当前数据根目录是 `data/bulk_expression_web`。
- Expression 与 Graph 联动可以通过 URL 参数和 overlay API 逐步实现。

## 相关检查

- `scripts/checks/check_docs_freshness.py`
- `scripts/checks/check_g6_static_contract.py`
- `scripts/checks/check_path_finder.py`
