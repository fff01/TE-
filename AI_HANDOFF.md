# TE-KG AI Handoff

Last reviewed: 2026-07-14

This document gives the next AI maintainer a compact but actionable project
handoff. Use it together with `AGENTS.md`, `ARCHITECTURE.md`, and live code.

## What This Project Is

TE-KG is a local web database for transposable element knowledge exploration.
It combines root-level PHP pages, browser JavaScript, Neo4j graph data, MySQL
expression summaries, local data files, and an Agent/DeepThink question-answering
subsystem.

The project is not a single-page application. Runtime pages still live in the
repository root. The main user-facing pages are:

- `index.php`: homepage and dataset overview.
- `browse.php`: TE browse and entity lookup.
- `preview.php`: graph workspace using G6 and iframe-based graph rendering.
- `path_finder.php`: path search and path graph display.
- `expression.php` / `expression_detail.php`: expression data views.
- `agent.php`: Agent and DeepThink entrypoint.
- `download.php`: download/catalog page.

## Runtime State

- Neo4j runtime target: `tekg3`.
- Runtime configuration: `api/runtime_config.php`, `api/config.local.php`.
- PHP path helper: `path_config.php`.
- Browser path helper: `assets/js/tekg_paths.php`.
- Python path helper: `scripts/path_helpers.py`.
- TE taxonomy runtime truth: Neo4j/API via `api/taxonomy.php`.
- Expression runtime root: `data/bulk_expression_web`.

Do not restore old runtime fallbacks or old expression roots without a dedicated
migration plan and verification.

## Major Subsystems

### Graph Runtime

The main graph frontend is under `assets/js/renderers/g6/`. The current evidence
graph supports G6 rendering, relation legends, Expand/Jump workflows, evidence
inspection, export behavior, and Agent/DeepThink-driven graph actions.

Important files include:

- `assets/js/renderers/g6/index-g6-shared.js`
- `assets/js/renderers/g6/index-g6-runtime.js`
- `assets/js/renderers/g6/index-g6-embed.js`
- `assets/js/renderers/g6/index-g6.bootstrap.js`
- `assets/js/renderers/g6/default-tree-mindmap.js`
- `api/graph.php`
- `api/graph_service.php`

When changing G6 behavior, verify API payloads, iframe bridge behavior, loader
state, legend state, and browser rendering before changing visual logic.

### Taxonomy Graph Experiments

There are two in-progress graph-rendering experiments:

- `assets/js/renderers/g6/large-force-graph/`
  - G6-based lightweight large-graph experiment.
  - Connected to taxonomy Graph in an earlier attempt.
  - User feedback: it did not visually diverge enough from the old taxonomy G6
    graph and did not match the desired "space / star and planets" feel.

- `taxonomy_canvas_demo.php` and `assets/js/renderers/canvas-force/`
  - Isolated Canvas taxonomy demo.
  - It fetches `api/taxonomy.php?view=tree`.
  - Current direction: Order-level nodes act as stars, deeper TE nodes orbit
    their own star, labels are sparse by default, and hover/click reveals local
    neighborhoods.
  - This is a demo only. It is not production runtime unless explicitly
    integrated later.

Do not merge either experiment into production without a new execution plan,
browser screenshots, and checks proving the ordinary evidence Graph still works.

As of 2026-07-19, the force-directed All-TE classification Graph is archived and
paused. `preview.php` exposes the existing collapsible taxonomy tree only; the
Tree/Graph display switch was removed, and taxonomy large-force/prototype
scripts are no longer loaded by the preview runtime. The source remains as
inactive reference material. The detailed history, frozen files, evidence, and
resumption rules are recorded in
`docs/eval/runs/2026-07-19-all-te-classification-pre-dynamic-rebase/ARCHIVE_REPORT.md`.

### Co-expression Backend

Co-expression work is backend/offline-data oriented at the current stage.
Important result folders are under `data/coexpression/`; scripts are under
`scripts/coexpression/`.

The current main analysis standard is:

- Network filter: `abs0.4_fdr0.05`
- Module result: `v1_abs0.4_fdr0.05_res1.8`
- Display subgraphs: `selected_cases`, `high_confidence`, `all_te`
- Summary tables: `all_te_quality_summary.tsv`,
  `display_tier_recommendations.tsv`

Communication boundary:

- Co-expression is correlation only.
- Module enrichment explains the gene context of a module; it does not prove TE
  regulation or causality.
- `L1HS` is the primary case candidate. `HERVH-int`, `LTR5`, and `CR1` are
  backup or comparison candidates.

### Agent and DeepThink

The Agent/DeepThink subsystem lives under `api/agent/` and `agent.php`.
It is part of the next AI maintainer's scope. Non-agent tasks should not modify
it by default, but Agent/DeepThink tasks should be handled by the next AI after
reading the dedicated handoff and plugin catalog.

If a task explicitly involves Agent or DeepThink, read:

- `api/README.md`
- `api/docs/intelligent_qa_handoff.md`
- `api/docs/intelligent_qa_architecture.md`
- `api/agent/plugins/PLUGIN_CATALOG.md`
- `api/agent/plugins/README.md`
- `docs/eval/runs/`

Current broad direction:

- Agent and DeepThink expose visible thinking/status streams.
- Plugin descriptions are important and should remain visible to planning
  prompts.
- Writing must be model-generated where specified; deterministic fallback
  answers must not mask model-stage failures.

## Documentation Policy

- New project Markdown should be English.
- `AGENTS.md` should stay short and index-like.
- Detailed handoff material should be in this file or linked architecture docs.
- Execution plans belong in `docs/exec-plans/`.
- Evaluation evidence belongs in `docs/eval/runs/`.
- Runtime Chinese prompt assets under `api/prompts/zh*` and
  `api/prompts/fallback_zh*` are functional assets and should not be translated
  as ordinary documentation.
- External reference corpora under `reference/` should not be translated or
  cleaned as project documentation.
- Historical Chinese or mixed-language Markdown should be treated as
  low-priority archive/background material. It is not a blocker for the current
  handoff cleanup unless a future task depends on a specific file.

## Cleanup State

Completed in the 2026-07-14 cleanup pass:

- Rewrote `AGENTS.md` in English as a short AI entry and index.
- Rewrote `ARCHITECTURE.md` in English as the root architecture entry.
- Added this handoff document.
- Rewrote key current architecture docs in English:
  - `docs/architecture/current_system.md`
  - `docs/architecture/index.md`
  - `docs/architecture/data_sources.md`
  - `docs/architecture/database_contract.md`
  - `docs/architecture/frontend_contract.md`
  - `docs/architecture/graph_runtime.md`
- Rewrote `docs/exec-plans/README.md`, `scripts/README.md`,
  `docs/coexpression/README.md`, and `api/agent/plugins/README.md` in English.
- Added the isolated intelligent QA documentation set under `api/README.md` and
  `api/docs/`.
- Removed the zero-byte `api/agent/agent.md`.
- Removed temporary `tmp/` screenshot artifacts.

Known remaining work:

- Historical Chinese or mixed-language Markdown files still exist in
  `docs/architecture/`, `docs/design-docs/`, `docs/exec-plans/`,
  `docs/coexpression/`, and course/paper/proposal folders.
- Ignore these files for routine handoff cleanup. Do not bulk-delete them.
  Translate or summarize durable decisions into English only when a future task
  needs that specific history.

## Current Dirty Worktree Warning

At handoff cleanup time, the working tree included:

- Modified documentation entries and runtime graph experiment files.
- A modified user course PPTX artifact under `docs/ppt/`; do not revert or
  delete it without explicit user instruction.
- Untracked graph experiment files under `assets/js/renderers/canvas-force/`,
  `assets/js/renderers/g6/large-force-graph/`, `taxonomy_canvas_demo.php`, and
  `scripts/checks/check_large_force_graph_contract.js`.

Review diffs before cleanup or integration.

## Harness Engineering Rules

Use harness engineering for complex tasks:

- Main AI: coordinates, scopes, plans, delegates, integrates, and verifies.
- Explorer: read-only repository investigation.
- Worker: bounded implementation with explicit file ownership.
- Reviewer: bug, architecture, and regression-risk review.
- Verifier: command execution and evidence capture.

Subagent output should not remain only in chat. Convert useful results into
plans, documentation, tests, scripts, or durable handoff notes.

## Recommended Next Tasks

1. Decide what to do with the G6 `large-force-graph` experiment: keep, archive,
   or remove after comparing it with the Canvas demo.
2. Continue improving the Canvas taxonomy demo until the user accepts the visual
   model, then create a dedicated integration plan.
3. Keep co-expression frontend design separate from the evidence Graph until the
   display contract and visual renderer are settled.
4. Maintain Agent/DeepThink as part of the project when requested, using
   `api/README.md`, `api/docs/intelligent_qa_handoff.md`, and the plugin catalog
   before edits.
5. Re-run graph and taxonomy checks after any renderer integration.

## Minimum Verification Set

Use the relevant subset for the task:

```powershell
php -l preview.php
php -l api/graph.php
php -l api/graph_service.php
node --check assets/js/renderers/g6/index-g6.bootstrap.js
node --check assets/js/renderers/g6/index-g6-shared.js
node --check assets/js/renderers/g6/index-g6-embed.js
python scripts/checks/check_g6_static_contract.py
python scripts/checks/check_g6_browser_smoke.py
python scripts/checks/check_taxonomy_runtime_truth.py
python scripts/checks/check_no_legacy_db_fallback.py
```

If `check_taxonomy_runtime_truth.py` fails, inspect whether the failure is a
pre-existing `index.php` taxonomy-helper issue or caused by current changes.
