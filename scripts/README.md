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
