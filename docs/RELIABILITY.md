# TE-KG Reliability

本文说明 TE-KG 当前可观测性和可靠性检查。目标是让 Codex 先拿证据，再判断问题在哪里。

## 核心检查

```powershell
python scripts/checks/check_neo4j_tekg3.py
python scripts/checks/check_api_contracts.py
python scripts/checks/check_g6_browser_smoke.py
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
- `check_runtime_db_config.py` 或 `check_no_legacy_db_fallback.py` 失败：说明旧 DB fallback 可能回流。
- `check_expression_paths.py` 失败：说明 expression runtime path 可能回到旧目录。
- `check_taxonomy_runtime_consistency.py` 或 `check_taxonomy_runtime_truth.py` 失败：说明 taxonomy canonical 规则可能被破坏。

## 当前已知可靠性风险

- Browser smoke 可能因外部 CDN 被阻断失败，尤其是 `@antv/g6` 和 `marked`。
- 这类失败不等于 Neo4j 或 API 失败；应优先按 `docs/exec-plans/active/g6-resource-localization.md` 处理。
- Expand mode 的业务正确性仍需要单独计划和 browser smoke 用例。

## 运行前提

- WAMP 由用户手动启动。
- Neo4j 由用户手动启动。
- 默认 base URL 是 `http://127.0.0.1/TE-`，可用 `TEKG_BASE_URL` 覆盖。
