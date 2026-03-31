# Scripts Layout

This folder stores project-side Python scripts that generate data, normalize entities,
or apply maintenance operations to the local Neo4j database.

## Current groups

- `normalize_*.py`
  - normalize raw source data into graph-ready JSON / JSONL
- `generate_*.py`
  - build demo assets, import bundles, and maintenance Cypher files
- `apply_*.py`
  - apply database-side cleanup or merge operations
- `semantic_aliases.py`
  - shared alias rules used by the normalization / standardization scripts

## How to run

Run scripts from the project root, for example:

```powershell
python scripts\generate_tree_demo_data.py
python scripts\generate_semantic_standardization_merge.py
```

The scripts still read and write project files relative to the repository root.

## Current raw/processed data preference

- Primary raw source: `data/raw/te_kg2.jsonl`
- Primary processed JSONL: `data/processed/te_kg2_normalized_output.jsonl`
- Primary graph seed: `data/processed/te_kg2_graph_seed.json`
- Legacy raw source retained for compatibility only: `data/archive/legacy/raw/output.jsonl`
- Legacy processed result retained for comparison only: `data/archive/legacy/processed/normalized_output.jsonl`
- Legacy graph seed retained for comparison only: `data/archive/legacy/processed/neo4j_graph_seed.json`

For new development, treat the `te_kg2` branch as the default pipeline.

## Legacy compatibility switches

- `scripts/apply_disease_classes.py`
  - Default behavior now updates only `data/raw/te_kg2.jsonl`
  - Use `--include-legacy-output-jsonl` only when you intentionally need to annotate `data/archive/legacy/raw/output.jsonl`
- `scripts/normalize_line1_graph.py`
  - This is now an explicit legacy pipeline
  - Use `--legacy-output-jsonl` only when you intentionally want to rebuild artifacts from `data/archive/legacy/raw/output.jsonl`
