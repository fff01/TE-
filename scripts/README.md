# Scripts Layout

This directory stores project-side Python, JavaScript, and helper scripts for
building data assets, normalizing entities, importing data, and checking local
runtime consistency.

## Current Active Rules

- The active Neo4j runtime target is `tekg3`.
- The active TE taxonomy build/normalization entrypoint is
  `scripts/normalize/build_tekg3_from_tekg21.py`.
- `tekg2`, `0413`, old lineage builders, and old `graph_demo_data.js`
  generation chains are no longer active build entrypoints.
- Historical script categories that were removed should be recovered from Git
  history only when needed for archaeology, not restored as runtime build paths.

## Current Groups

- `build/`: parsers and asset preparers for JBrowse, expression, Dfam, Repbase,
  and related data assets.
- `normalize/`: `tekg3` construction, semantic normalization, disease
  classification, terminology backfill, and maintenance scripts.
- `export/`: export helpers such as entity-description translation output.
- `import/`: Cypher generation and import helpers.
- `checks/`: runtime consistency and regression checks.
- `plot/`: visualization and structure-figure helpers.
- Root support modules:
  - `path_helpers.py`
  - `semantic_aliases.py`
  - `disease_top_class.py`

## Common Checks

Run from the repository root:

```powershell
python scripts\checks\check_runtime_db_config.py
python scripts\checks\check_taxonomy_runtime_consistency.py
python scripts\checks\check_expression_paths.py
```

## Current Key Build Entrypoints

```powershell
python scripts\normalize\build_tekg3_from_tekg21.py
python scripts\build\prepare_expression_assets.py
python scripts\build\prepare_jbrowse_assets.py
python scripts\build\parse_dfam_embl.py
python scripts\build\parse_te_repbase.py
```

## Path Rules

- Python scripts should prefer `scripts/path_helpers.py`.
- PHP runtime paths should prefer `path_config.php`.
- Browser runtime paths should prefer `assets/js/tekg_paths.php`.

## Removed Legacy Chains

These categories are no longer active scripts:

- `tekg2` seed builders.
- `tekg2` import bundle generators.
- `0413` disease classification import scripts.
- Old `tree_te_lineage` generators.
- Old `assets/data/graph_demo_data.js` generators.
- Old `tekg2` unresolved-relation repair scripts.

For previous processing history, use Git history or historical documents instead
of reintroducing these scripts as runtime build entrypoints.
