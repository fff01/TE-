# Data Sources

本文记录当前运行时数据来源，重点是避免旧路径、旧 DB、旧 taxonomy truth source 回流。

## Neo4j

- 当前运行 DB：`tekg3`
- 配置入口：`api/config.local.php`
- 共享读取入口：`api/runtime_config.php`
- 主要消费方：`api/graph.php`、`api/taxonomy.php`、`api/health.php`、`api/te_metrics.php`

## TE taxonomy

- 当前 canonical runtime source：Neo4j `TE` 节点 taxonomy 属性和 `api/taxonomy.php`。
- 首页 taxonomy / ring chart 应优先从 PHP 端查询 Neo4j。
- 历史文件可以作为 reference 或构建输入，但不应重新成为 runtime truth source。

关键文件：

- `data/taxonomy/transposon_tree/tree_rmsk_repbase.txt`
- `data/taxonomy/transposon_tree/tree_all.txt`
- `data/processed/tekg3_taxonomy_standardization_report.json`
- `data/processed/tekg3_homepage_taxonomy.json`

## Expression

- 当前 runtime root：`data/bulk_expression_web`
- 不应恢复旧路径：`data/raw/new_data/bulk_expression_web`
- MySQL summary table 是 Expression 页面主要运行来源；TSV fallback 也必须指向当前 root。

## Genome / JBrowse

- JBrowse runtime 数据位于 `data/JBrowse/`。
- `jbrowse.php` 是当前入口。

## 相关检查

- `scripts/checks/check_no_legacy_db_fallback.py`
- `scripts/checks/check_taxonomy_runtime_truth.py`
- `scripts/checks/check_expression_paths.py`
- `scripts/checks/check_taxonomy_runtime_consistency.py`
