# TE-KG Project Folder Simplification Recommendations

Date: 2026-05-17

Scope: This document lists practical cleanup targets found by scanning the current project folder. It intentionally avoids download-link cleanup because that is easy to fix later.

## Current Shape

Top-level directories:

```text
.vscode
api
archive
assets
data
docs
imports
reference
scripts
templates
test
```

The repository has about 296 tracked/listed files from `rg --files`, while the local workspace also contains large ignored data assets under `data/`.

Large local assets are concentrated in:

- `data/JBrowse`
- `data/bulk_expression_web`
- `data/raw`
- `data/processed`
- `data/dfam`
- `reference/external_examples/g6-official/.git`
- `archive/processing_history`

## 1. Remove Generated Python Bytecode From Version Control

Observed tracked bytecode:

```text
scripts/__pycache__/path_helpers.cpython-313.pyc
scripts/checks/__pycache__/check_taxonomy_runtime_consistency.cpython-313.pyc
scripts/normalize/__pycache__/build_tekg3_from_tekg21.cpython-313.pyc
```

Risk:

- Every Python version or script run can create meaningless diffs.
- Reviews become noisier.

Recommended fix:

```powershell
git rm --cached scripts/__pycache__/path_helpers.cpython-313.pyc
git rm --cached scripts/checks/__pycache__/check_taxonomy_runtime_consistency.cpython-313.pyc
git rm --cached scripts/normalize/__pycache__/build_tekg3_from_tekg21.cpython-313.pyc
```

Then add to `.gitignore`:

```text
__pycache__/
*.pyc
```

## 2. Keep Runtime Config Centralized

Already improved in this pass:

- Added `api/runtime_config.php`.
- Updated `api/config.local.php.example` to `tekg3`.
- Removed runtime fallbacks to `tekg2` and `tekg21` from active API files.
- Added `scripts/checks/check_runtime_db_config.py`.

Next cleanup:

- Let non-agent runtime files use `api/runtime_config.php` for all shared runtime config where reasonable.
- Keep MySQL expression config separate only if it remains expression-specific.
- Do not reintroduce DB-name defaults inside page/API files.

## 3. Mark Old Build Scripts As Legacy Or Update Them

Still observed older references:

```text
scripts/build/generate_tree_demo_data.py
scripts/build/generate_tree_te_lineage.py
scripts/build/generate_tree_te_lineage_0413.py
scripts/export/finalize_te_234_taxonomy.py
scripts/build/build_tekg2_seed_from_standardized_new.py
scripts/normalize/fix_tekg2_unresolved_0413.py
scripts/normalize/extract_tekg2_unresolved_relations.py
```

Risk:

- A future maintainer may run an old generator and recreate stale files.
- Old `tekg2` and `4.18/4.27` taxonomy assumptions can drift back into runtime.

Recommended structure:

```text
scripts/
  active/
  legacy/
  checks/
  normalize/
  build/
```

Lower-risk first step:

- Keep paths unchanged for now.
- Add a header comment to legacy scripts.
- Add `scripts/checks/check_legacy_generator_inputs.py` to fail if a script is presented as active but references missing inputs.

## 4. Split Very Large Runtime Files Only After Behavior Is Covered

Largest active PHP files:

```text
api/qa.php                                      2293 lines
api/agent/orchestrator/AcademicAgentService.php 2241 lines
api/graph.php                                  1821 lines
api/agent/orchestrator/DeepThinkService.php    1439 lines
search.php                                     1115 lines
api/agent/bootstrap.php                         797 lines
api/expression_data.php                         541 lines
```

Largest active JS files:

```text
assets/js/renderers/g6/index-g6.bootstrap.js  1415 lines
assets/js/pages/agent.js                      1264 lines
assets/js/renderers/g6/index-g6-shared.js     1127 lines
assets/js/renderers/g6/dynamic-graph.js        883 lines
assets/js/renderers/g6/default-tree-mindmap.js 856 lines
assets/js/renderers/g6/default-tree.js         833 lines
```

Recommended order:

1. Leave `api/qa.php` and agent files to the agent-focused maintainer.
2. Split `api/graph.php` only after API smoke tests cover representative queries.
3. Split `search.php` by data source helper:
   - Repbase helper
   - Dfam helper
   - JBrowse helper
   - taxonomy helper
   - rendering/page assembly
4. Split G6 files by runtime contract, layout, interaction, and data loading.

Avoid doing this as a visual redesign. The first refactor should preserve behavior.

## 5. Treat `archive` And `reference` As Non-Runtime

Observed issue:

- `archive/processing_history` contains large historical JSONL and Python files.
- `reference/external_examples/g6-official/.git` contains a nested external Git checkout.

Risk:

- Search output is noisy.
- Large historical files can be mistaken for active inputs.
- Nested `.git` directories confuse project scanning and backup behavior.

Recommended cleanup:

- Move heavy historical files outside the active workspace if they are not needed daily.
- Keep a short manifest that explains where archival data lives.
- Remove or compress nested external examples if they are only references.
- Prefix archive scripts with a clear legacy marker if they must stay.

## 6. Add A Runtime Data Manifest

Important runtime data is currently local and large. Some generated files are canonical runtime inputs; some are derived outputs; some are fallback caches.

Recommended manifest fields:

```json
{
  "path": "data/bulk_expression_web/processed/te_expression_context_stats.tsv",
  "role": "canonical runtime input",
  "owner": "expression",
  "generated_by": "scripts/build/prepare_expression_assets.py",
  "required_for": ["expression_detail.php"],
  "expected_size_bytes": 818036710
}
```

High-priority entries:

- `data/taxonomy/transposon_tree/tree_rmsk_repbase.txt`
- `data/taxonomy/transposon_tree/tree_all.txt`
- `data/processed/tekg3_taxonomy_standardization_report.json`
- `data/processed/tekg3_homepage_taxonomy.json`
- `data/bulk_expression_web/processed/*`
- `data/JBrowse/*`
- `data/processed/dfam/dfam_curated_catalog.json`

## 7. Remove Duplicate Or Suspicious Data Files

One obvious candidate:

```text
data/bulk_expression_web/cancer_cell_line/CCLE_TE_normalized_count copy.tsv
```

It is large and likely a manual duplicate. Do not delete it blindly; first compare size/checksum and confirm whether any script references it.

Suggested check:

```powershell
rg -n "CCLE_TE_normalized_count copy.tsv|CCLE_TE_normalized_count.tsv" .
```

If only the non-copy file is used, move the copy to archive or remove it after backup.

## 8. Make Docs Easier To Navigate

Current docs are useful but spread across:

```text
docs/architecture
docs/course
docs/demo
docs/latex
docs/notes
docs/setup
```

Recommended convention:

- `docs/architecture`: current architecture and active risk/task documents.
- `docs/setup`: local setup, config, runtime data restore.
- `docs/latex`: paper/report source only.
- `docs/notes`: temporary notes; periodically archive or merge.
- `docs/demo`: demo-specific scripts/pages.

Also add a short `docs/README.md` table that says which docs are current.

## 9. Keep Agent And Non-Agent Ownership Separate

The project handoff already says the main assistant should focus on non-agent work, while `agent_gpt55_handoff.md` is for a separate AI/person responsible for agent development.

Recommended boundary:

- Non-agent owner: root runtime pages, data paths, taxonomy API, graph API, expression pages, JBrowse, docs.
- Agent owner: `agent.php`, `assets/js/pages/agent.js`, `api/agent/*`, `api/qa.php` behavior.
- Shared contract: `api/runtime_config.php`, `api/taxonomy.php`, `api/graph.php`, and expression helper functions.

This prevents unrelated agent refactors from blocking database and frontend stabilization.

## 10. Suggested Cleanup Sequence

1. Remove tracked `.pyc` files and update `.gitignore`.
2. Add a runtime data manifest.
3. Mark stale generator scripts as legacy or update their inputs.
4. Decide whether `data/processed/tekg2/*` is active reference, migration input, or archive.
5. Review duplicate large files such as `CCLE_TE_normalized_count copy.tsv`.
6. Split `search.php` data helpers after smoke tests are stable.
7. Split `api/graph.php` into query, normalization, payload, and HTTP handling modules.
8. Keep agent refactors separate from non-agent database cleanup.

## Verification Commands To Keep

```powershell
python scripts/checks/check_runtime_db_config.py
python scripts/checks/check_taxonomy_runtime_consistency.py
python scripts/checks/check_expression_paths.py
php -l api/runtime_config.php
php -l api/graph.php
php -l api/health.php
php -l api/taxonomy_lib.php
php -l api/te_metrics.php
```
