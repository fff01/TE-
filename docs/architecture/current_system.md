# TE-KG Current System

This file records the current system state. It is the primary current-facts
entry for the Codex harness. Older handoffs and historical plans are useful
background, but they do not automatically represent live code.

## System Shape

- TE-KG is a local PHP + browser JavaScript + Neo4j + MySQL project.
- WAMP provides the PHP runtime.
- Root runtime pages still live in the repository root.
- The current Neo4j runtime target is `tekg3`.
- `api/runtime_config.php` and `api/config.local.php` provide runtime
  configuration.
- Path abstraction already exists:
  - PHP: `path_config.php`
  - Browser JavaScript: `assets/js/tekg_paths.php`
  - Python: `scripts/path_helpers.py`

## Root Pages

Main runtime pages include:

- `index.php`: homepage.
- `browse.php`: TE browse page.
- `preview.php`: TE-KG / G6 graph workspace.
- `path_finder.php`: entity path finder.
- `expression.php`, `expression_detail.php`: expression pages.
- `download.php`: download/catalog page.
- `agent.php`: Agent and DeepThink entrypoint.
- `about.php`: help/about page.

Do not move root runtime pages merely for cleanup.

## APIs

- Graph query entrypoint: `api/graph.php`.
- Graph core service: `api/graph_service.php`.
- Taxonomy query: `api/taxonomy.php`.
- Expression data: `api/expression_data.php`, `api/expression_repository.php`.
- Health / metrics: `api/health.php`, `api/te_metrics.php`.
- Agent APIs are under `api/agent/`; non-agent tasks should not modify them by
  default.

## Graph / G6

- `preview.php` is the main TE-KG graph page.
- G6 runtime files live under `assets/js/renderers/g6/`.
- High-value G6 risk areas include Expand mode, loader state, iframe bridge,
  legend filtering, browser smoke coverage, and relation evidence display.
- When fixing G6, inspect API payloads and frontend state before changing
  renderer logic.

## Taxonomy and Canvas Experiments

- TE taxonomy runtime rules are Neo4j/API-backed.
- Do not add a second taxonomy runtime truth source.
- A G6 `large-force-graph` experiment and an isolated Canvas taxonomy demo are
  present as graph-rendering experiments.
- The Canvas demo is isolated and should not be treated as production runtime
  until a dedicated integration plan is approved.

## Data and Taxonomy

- Taxonomy and terminology assets are under `data/taxonomy/` and
  `data/terminology/`.
- Homepage taxonomy/ring chart caches and reports may exist under
  `data/processed/`, but they do not replace live DB/API verification.

## Expression

- Current expression asset root is `data/bulk_expression_web`.
- Do not restore `data/raw/new_data/bulk_expression_web` as a runtime root.
- MySQL summary tables remain important dependencies for expression pages.

## Co-expression

- Co-expression scripts live under `scripts/coexpression/`.
- Co-expression outputs live under `data/coexpression/`.
- Current work is backend/offline-data oriented. Frontend integration should be
  planned separately.
- Co-expression results are correlation evidence only, not causal or regulatory
  proof.

## Scripts and Imports

- Python path helper: `scripts/path_helpers.py`.
- Build, check, migration, and analysis scripts live under `scripts/`.
- Neo4j import history lives under `imports/`; older `tekg2` materials are
  historical references and should not be used as runtime configuration.

## Agent Boundary

- `docs/architecture/agent_gpt55_handoff.md` is for tasks explicitly involving
  the Agent/DeepThink subsystem.
- The default harness serves the whole TE-KG project, especially non-agent
  pages, APIs, data paths, and graph runtime.
- Unless the task explicitly requires Agent work, do not treat `api/agent/` or
  `assets/js/pages/agent.js` as default edit targets.

## Harness Rule

Long-term facts must be written back to repository documentation. Complex tasks
belong in `docs/exec-plans/`. Verification rules should be turned into checks
under `scripts/checks/` when practical. Do not rely on chat history as the only
source of project memory.
