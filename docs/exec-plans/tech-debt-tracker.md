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

### G6 Subgraph export SVG v2

- 状态：v1 已完成，见 `completed/g6-subgraph-export.md`。
- 已完成：当前可见 nodes / edges CSV 导出、当前画布 PNG 导出、单按钮 Export 菜单收敛。
- 已覆盖：`scripts/checks/check_g6_subgraph_export_smoke.py` 验证 Export 菜单、CSV/PNG 导出触发、SVG disabled 状态。
- 残留风险：SVG export 未实现，当前 UI 保持 disabled 的 `SVG Soon`。下一步先执行 `active/g6-svg-export.md` 调研当前 G6 canvas runtime 是否存在可靠官方 SVG export API，以及手写 SVG 是否值得做。

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

### G6 SVG export v2 research result - 2026-05-21

- Status: research archived in `completed/g6-svg-export.md`; no runtime implementation in this phase.
- Current supported export surface: visible subgraph CSV and current canvas PNG from G6 subgraph export v1.
- Decision: keep SVG disabled as `SVG Soon` because current browser G6 runtime is canvas-based and does not expose reliable browser `toSVG`, `export`, or `exportToFile` APIs.
- Residual options: later build a simplified visible-subgraph SVG renderer, or evaluate a dedicated `@antv/g6-ssr` export path. Both require a separate plan and should not change CSV/PNG semantics.

### PubMed metadata enrichment and evidence support inputs - 2026-05-21

- Status: full PubMed metadata fetch completed for current Neo4j `tekg3` PMID inventory; visual edge encoding is not changed.
- Output: `data/processed/pubmed_metadata.jsonl` contains 2308 PubMed metadata records and can later be joined to graph relations by PMID.
- Supported fields: DOI, title, abstract, journal title/ISO abbreviation/ISSN, publication year/date/model/types, MeSH terms, keywords, authors, affiliations, grants, chemicals, references, source provider, fetched timestamp.
- Impact Factor is not a PubMed field. Future relation strength or evidence support work needs an external journal metric mapping keyed by ISSN/year; do not hardcode IF in prompts or ask an LLM to infer it.
- Debt: Impact Factor requires a future local mapping file such as `data/reference/journal_metrics.csv` with `issn,year,impact_factor,metric_source,metric_name`.

### Journal metric mapping v1 - 2026-05-22

- Status: v1 mapping completed and accepted for internal TE-KG use; no Neo4j import, G6 change, graph API change, taxonomy change, or agent change has been made.
- Source: local `reference/external_examples/impact_factor` package data, treated as 2025 data with `metric_source=impact_factor_package_2025` and `metric_name=Journal Impact Factor`.
- Provenance constraint: this is not a direct official JCR export, but the project has approved it as a trusted internal journal metric source.
- Outputs: `data/reference/journal_metrics.csv`, `data/processed/journal_metrics_mapping_report.json`, and `data/processed/pubmed_metadata_with_metrics.jsonl`.
- Coverage: journal match rate 86.59%; PMID match rate 88.26%; unmatched PMID count 271.
- Unmatched journals/PMIDs keep null metrics. Do not guess IF, do not use LLM-generated metrics, and do not force-match different ISSNs.
- Residual risk: `title_exact` fallback produced 22 matches. This is accepted for internal v1, but should be manually reviewed before publication or external-facing visual encoding.

### Neo4j journal metrics relation aggregation v1 - 2026-05-22

- Status: Paper enrichment and BIO_RELATION aggregation completed in Neo4j `tekg3`; graph API and G6 visual encoding are still unchanged.
- Paper enrichment: 2308 existing `Paper` nodes tagged `journal_metrics_v1_2026-05-22`; 2037 with metrics, 271 with null metrics.
- Relation aggregation: 12444 existing `BIO_RELATION` relationships tagged `relation_metrics_v1_2026-05-22`.
- Aggregation evidence: 14770 raw relation PMID references, 2270 unique relation PMIDs, 2270 joinable PMIDs, 2003 with metrics, 267 without metrics.
- Written relation fields include `support_pmid_count`, `support_metric_paper_count`, `support_metric_coverage`, `support_if_max`, `support_if_mean`, `support_if_median`, JCR quartile counts, journal count, publication year min/max, and `relation_metrics_import_tag`.
- Rollback is property-only via `scripts/rollback_relation_journal_metrics.py --import-tag relation_metrics_v1_2026-05-22 --write`; it must not delete nodes or relationships.
- Next debt: design graph API evidence-support read path and G6 visual encoding in a separate active plan. Do not call IF-derived fields confidence.

### G6 evidence support UX v1 - 2026-05-22

- Status: completed and archived in `completed/g6-evidence-support-ux-v1.md`.
- Graph API now exposes per-edge eager `evidence_records` without abstracts; fields are limited to PMID, PubMed URL, title, journal, year, journal metric value/source/year, JCR quartile, and match method.
- G6 edge width uses `support_pmid_count`; edge opacity uses `support_metric_coverage` with a visible lower bound, so zero-coverage edges remain visible.
- Edge click now renders an evidence table with PubMed links and supports selected-edge evidence CSV download for tables above 10 rows.
- Follow-up UI placement: evidence table now lives inside the expanded edge inspect card's `PubMed` section, with the CSV button on that section header; the lower detail area no longer duplicates the table.
- Follow-up layout: legacy lower detail is visually hidden again, and the PubMed evidence table uses fixed in-card column widths without horizontal scrolling; Journal/Title truncation keeps tooltip and CSV full values.
- Checks: `scripts/checks/check_graph_api_edge_evidence_records.py` and `scripts/checks/check_g6_evidence_support_ux.py`.
- Residual risk: eager payload size may need a lazy endpoint if future large graph queries regress browser/API performance; v1 does not add an explanatory legend for edge width/opacity.

### G6 node action card UX - 2026-05-22

- Status: completed and archived in `completed/g6-node-action-card-ux.md`.
- User-visible `Fixed view` and `Expand mode` controls are hidden; compatibility DOM/state is retained.
- Node click now opens a node action card only. It does not auto-jump and does not auto-expand.
- Node action buttons are explicit: `Jump`, `Expand`, and `Details`.
- `Jump` reuses existing node-centered graph loading; `Expand` reuses the same-label-safe expand path and still sends `expand_node_id`, `expand_node_type`, and `expand_query`; `Details` expands the node card without graph mutation.
- Follow-up UI polish completed: node Expand loader copy changed to `Expanding`, Jump closes the old card, Reset is no longer user-visible, `Show labels` was renamed to `Show relations`, toolbar off-state styling aligns with Export, evidence table Journal/Title widths were adjusted, and collision radius offsets increased slightly from `46/38/34/30` to `50/42/38/34`.
- Follow-up loader amendment completed: TE graph loading can render conservative retrotransposon- or DNA-transposon-inspired inline SVG/CSS loaders using the current TE color. Unknown/non-TE loading keeps the existing default animation. The loader is illustrative only and must not be treated as a strict mechanism diagram.
- Follow-up loader refinement completed: mechanism loaders are now larger (`420x220`), labels use black fill with white stroke, retro RNA/label/RT move as one group, RNA is semi-transparent TE color, target DNA slides in, and target copy labels use the current TE short label.
- Follow-up loader bugfix/refinement completed: `LINE1` now triggers retro loader during Search/graph loading, mechanism SVGs are enlarged to `560x300`, retro target DNA no longer slides, and DNA transposon no longer renders a duplicate inserted-copy TE.
- Follow-up loader biology/visual refinement completed: arrows were removed, target DNA now opens as left/right fixed gap segments, retro source DNA remains intact, DNA transposon donor DNA splits around the excised TE without slash cut marks, and DNA transposon TE movement is lift plus right/down transfer only.
- Follow-up loader root-cause refinement completed: mechanism loader type detection now handles taxonomy/category-level phrases and plural normalization (`Class I: Retrotransposons`, `Retrotransposons`, `Class II: DNA transposons`, `Tc1-Mariner`) without one-off hardcoding. Retro copy and DNA moving TE now align into target DNA gaps, DNA source donor segments initially connect to the TE and separate during movement, and target opening arcs no longer cover TE labels.
- Checks: `scripts/checks/check_g6_node_action_card_ux.py`, updated `check_g6_browser_smoke.py`, and updated expand smokes.
- Residual debt: fixed/expand internal variables are retained for compatibility and should only be removed after a separate reference audit. Expand/collapse affordance and multi-step expanded-node UX remain separate future work.
