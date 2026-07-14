# Data Sources

This document records current runtime data sources. Its main purpose is to
prevent old paths, old databases, and old taxonomy truth sources from returning
to active runtime.

## Neo4j

- Active runtime database: `tekg3`
- Local configuration entry: `api/config.local.php`
- Shared runtime reader: `api/runtime_config.php`
- Main consumers: `api/graph.php`, `api/taxonomy.php`, `api/health.php`,
  `api/te_metrics.php`

## TE Taxonomy

- Canonical runtime source: Neo4j `TE` node taxonomy properties exposed through
  `api/taxonomy.php`.
- Homepage taxonomy and ring chart data should prefer PHP-side Neo4j/API access.
- Historical taxonomy files may be references or build inputs, but they must not
  become a second runtime truth source.

Important files:

- `data/taxonomy/transposon_tree/tree_rmsk_repbase.txt`
- `data/taxonomy/transposon_tree/tree_all.txt`
- `data/processed/tekg3_taxonomy_standardization_report.json`
- `data/processed/tekg3_homepage_taxonomy.json`

## Expression

- Active runtime root: `data/bulk_expression_web`
- Do not restore old root: `data/raw/new_data/bulk_expression_web`
- MySQL summary tables are the primary runtime source for expression pages.
  TSV fallback paths must also point to the active root.

## Genome / JBrowse

- JBrowse runtime data lives under `data/JBrowse/`.
- `jbrowse.php` is the active entrypoint.

## Related Checks

- `scripts/checks/check_no_legacy_db_fallback.py`
- `scripts/checks/check_taxonomy_runtime_truth.py`
- `scripts/checks/check_expression_paths.py`
- `scripts/checks/check_taxonomy_runtime_consistency.py`
