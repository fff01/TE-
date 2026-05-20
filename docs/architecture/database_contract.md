# Database Contract

本文记录 TE-KG 当前数据库契约。它用于让 Codex 先验证数据库事实，再修改业务代码。

## Neo4j contract

- 当前 database name 必须解析为 `tekg3`。
- `RETURN 1` 必须可执行。
- `TE` 节点数必须大于 0。
- `BIO_RELATION` 关系数必须大于 0。
- 代表性 TE 至少能查到部分结果：`L1HS`、`AluJb`、`SVA`。

## API contract

- `api/health.php` 应返回 `ok=true`、`neo4j_database=tekg3`、`neo4j_reachable=true`。
- `api/graph.php?q=LINE1` 应返回 `ok=true`、`anchor` 和非空 `elements`。
- `api/taxonomy.php?view=tree&source=rmsk_repbase` 应返回 tree/root 数据。
- `api/taxonomy.php?names=L1HS,AluJb,SVA` 应返回 `source=tekg3` 且 items 可解析。

## 不允许的回归

- 活跃 runtime 不应 fallback 到 `tekg2` 或 `tekg21`。
- 缺失本地配置时应显式报 setup/config error，而不是静默切旧 DB。

## 相关检查

- `scripts/checks/check_neo4j_tekg3.py`
- `scripts/checks/check_api_contracts.py`
- `scripts/checks/check_runtime_db_config.py`
- `scripts/checks/check_no_legacy_db_fallback.py`
