# Co-expression Graph Dual-Mode Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: use the repository's
> harness-engineering workflow and execute this plan one task at a time. Do not
> begin a later task before the current task has fresh acceptance evidence and
> explicit user approval.

**Status:** Proposed plan; implementation has not started.

**Goal:** Add a `Knowledge Graph | Co-expression` mode control to the existing
`preview.php` Graph workspace so users can explore one TE-centered
co-expression network at a time without mixing correlation semantics into the
Neo4j evidence Graph.

**Architecture:** Keep `preview.php` as the shared workspace shell, but give
Knowledge Graph and Co-expression separate data sources, controllers, renderers,
legends, details, and lifecycle ownership. A thin PHP API reads only the
versioned offline co-expression display assets. A dedicated parent-page G6
renderer displays one small TE/context subgraph while the existing
tree/evidence-Graph runtime remains unchanged and restorable.

**Tech Stack:** PHP 8, browser JavaScript, AntV G6, versioned JSON/TSV offline
co-expression results, Python/Playwright harness checks.

---

### Architectural Decision: Shared Shell, Separate Surface

The Co-expression mode will not route its data through the existing evidence
Graph iframe runner. That runner's `renderElements()` currently owns Knowledge
Graph route synchronization, `dynamic` mode transitions, evidence inspect
cards, node actions, relation semantics, and parent-host callbacks. Adding a
domain flag to bypass all of those branches would increase the regression
surface in `index-g6-shared.js` and `index-g6-embed.js`.

Instead, `preview.php` keeps one visible Graph workspace but adds a dedicated
stacked `#g6-coexpression-surface` rendered directly by a small isolated G6
module. The existing tree surface and evidence iframe remain mounted but hidden
while Co-expression is active. This preserves Knowledge Graph state without
teaching its renderer about correlation semantics.

## 1. Product Contract

### 1.1 One Workspace, Two Semantic Modes

The visible Graph workspace gains a segmented control:

```text
[ Knowledge Graph | Co-expression ]
```

This is a workspace-mode selector, not an edge-layer toggle.

- `Knowledge Graph` retains the current taxonomy-tree and Neo4j evidence-Graph
  behavior.
- `Co-expression` displays one offline TE/context subgraph.
- The two modes must never merge nodes, edges, legends, filters, history, detail
  cards, exports, or scientific language.
- Knowledge Graph remains the default mode unless the URL explicitly requests
  Co-expression.

### 1.2 Co-expression Display Unit

One rendered network is identified by:

```text
analysis_version + TE + context
```

The initial supported version is:

```text
v1_abs0.4_fdr0.05_res1.8
```

Supported contexts:

```text
cancer_cell_line
normal_cell_line
normal_tissue
```

Current display assets contain:

- 285 searchable TE features;
- 849 valid TE/context JSON subgraphs;
- 7-26 nodes per subgraph;
- 11-100 edges per subgraph;
- 17 `core_case` rows;
- 287 `high_confidence` rows;
- 542 `searchable_all` rows;
- 3 `not_recommended_default` rows.

The six absent TE/context combinations are valid unavailable states, not API
errors:

- `CR1 / cancer_cell_line`
- `LTR30 / cancer_cell_line`
- `LTR7A / cancer_cell_line`
- `LTR7A / normal_tissue`
- `MER99 / normal_tissue`
- `SART1 / cancer_cell_line`

### 1.3 Default View

The first Co-expression activation defaults to:

```text
TE: L1HS
Context: cancer_cell_line
```

This is a high-confidence gene-rich case and is preferable to choosing an
arbitrary alphabetical TE.

### 1.4 Scientific Language

All UI text, API metadata, exports, tooltips, and detail cards must preserve:

```text
Co-expression indicates statistical association, not regulation or causation.
```

Prohibited unqualified terms include:

- regulates
- activates
- inhibits
- drives
- controls
- mechanism
- causal

Allowed terms include:

- co-expressed with
- positively correlated with
- expression-associated
- co-expression module
- expression context
- module genes are enriched for

### 1.5 Explicit Non-Goals

This plan does not:

- import co-expression results into Neo4j;
- add co-expression edges to `api/graph.php`;
- display the 2,100-2,300-node full context networks;
- display all 285 TE nodes at once;
- create a global module atlas;
- add negative edges to the display subgraphs;
- recompute correlations, modules, or enrichment;
- modify Agent/DeepThink prompts or graph actions;
- revive the archived taxonomy force-directed Graph;
- redesign ordinary Knowledge Graph Expand, legend, export, or Back behavior.

## 2. Locked Data and API Contract

### 2.1 Runtime Truth

Co-expression runtime truth is the approved versioned offline output:

```text
data/coexpression/display_subgraphs/v1_abs0.4_fdr0.05_res1.8/
```

Neo4j remains Knowledge Graph truth but is not Co-expression truth.

The API must never accept an arbitrary filesystem path. It resolves TE/context
only through the approved manifest and recommendation table.

### 2.2 Catalog Endpoint

Request:

```text
GET api/coexpression.php?action=catalog
```

Successful response:

```json
{
  "ok": true,
  "version": "v1_abs0.4_fdr0.05_res1.8",
  "method": "spearman",
  "thresholds": {
    "min_abs_correlation": 0.4,
    "max_fdr": 0.05,
    "module_resolution": 1.8,
    "positive_display_edges_only": true
  },
  "default_selection": {
    "te": "L1HS",
    "context": "cancer_cell_line"
  },
  "contexts": [
    {"id": "cancer_cell_line", "label": "Cancer cell line"},
    {"id": "normal_cell_line", "label": "Normal cell line"},
    {"id": "normal_tissue", "label": "Normal tissue"}
  ],
  "items": [
    {
      "te": "L1HS",
      "available_contexts": [
        "cancer_cell_line",
        "normal_cell_line",
        "normal_tissue"
      ],
      "best_tier": "core_case",
      "recommended_default": true
    }
  ],
  "interpretation_limit": "Co-expression is correlation, not causation or direct regulatory evidence."
}
```

Catalog rules:

- exactly one item per TE;
- items sorted case-insensitively by TE name;
- `available_contexts` derived from manifest files, not assumed;
- tier and recommendation derived from
  `display_tier_recommendations.tsv`;
- no absolute Windows path is returned;
- no graph JSON is opened while serving the catalog;
- the response must remain small enough for one initial request.

### 2.3 Network Endpoint

Request:

```text
GET api/coexpression.php?action=network&te=L1HS&context=cancer_cell_line
```

Successful response:

```json
{
  "ok": true,
  "version": "v1_abs0.4_fdr0.05_res1.8",
  "selection": {
    "te": "L1HS",
    "context": "cancer_cell_line",
    "available_contexts": [
      "cancer_cell_line",
      "normal_cell_line",
      "normal_tissue"
    ],
    "display_tier": "core_case",
    "quality_flag": "high",
    "recommended_default": true
  },
  "module": {
    "id": "cancer_cell_line_M002",
    "type": "gene-rich",
    "size": 428,
    "te_count": 3,
    "gene_count": 425,
    "confidence": "high",
    "candidate_label": "Steroid hormone biosynthesis / BP:fatty acid metabolic process / BP:skin development",
    "top_enriched_terms": []
  },
  "interpretation": {
    "statement_en": "",
    "statement_zh": "",
    "limit": "Co-expression is correlation, not causation or direct regulatory evidence."
  },
  "nodes": [],
  "edges": []
}
```

Network rules:

- return exactly one TE/context display JSON;
- preserve numeric `correlation`, `abs_correlation`, and `fdr`;
- preserve `feature_type`, `role`, `is_center`, and `is_module_hub`;
- all node IDs must be nonempty and unique;
- all edge endpoints must resolve to returned nodes;
- every edge must have `correlation > 0`;
- reject a payload with more than 50 nodes or 150 edges as an unexpected
  display-contract regression;
- do not return `selection.edge_source` or any filesystem path;
- encode all output with `JSON_UNESCAPED_UNICODE |
  JSON_UNESCAPED_SLASHES`.

### 2.4 Error Contract

```json
{
  "ok": false,
  "error": {
    "code": "network_unavailable",
    "message": "No display network is available for CR1 in cancer_cell_line.",
    "available_contexts": ["normal_cell_line", "normal_tissue"]
  }
}
```

Required statuses:

- `400 invalid_action`
- `400 invalid_context`
- `404 unknown_te`
- `404 network_unavailable`
- `405 method_not_allowed`
- `500 data_contract_error`

Error responses must not expose absolute paths, stack traces, or raw PHP
warnings.

## 3. Frontend State Contract

### 3.1 Parent Workspace State

Add one top-level state independent of the existing `currentMode`:

```javascript
workspaceMode = 'knowledge' | 'coexpression'
```

Do not rename or overload existing Knowledge Graph states such as `tree`,
`dynamic`, `query`, `answer`, and `disease_class_tree`.

Co-expression owns:

```javascript
{
  status: 'idle' | 'catalog-loading' | 'network-loading' | 'ready' | 'error',
  catalog: null,
  te: 'L1HS',
  context: 'cancer_cell_line',
  network: null,
  selectedElement: null,
  showInternalEdges: true,
  visibleNodeTypes: { TE: true, gene: true },
  requestEpoch: 0
}
```

### 3.2 Mode Lifecycle

Knowledge Graph -> Co-expression:

1. snapshot the current Knowledge Graph state through the existing state
   mechanism;
2. do not mutate evidence elements, relation filters, history, or iframe
   bridge state;
3. hide Knowledge Graph tree/dynamic surfaces;
4. show the dedicated Co-expression surface and controls;
5. load catalog once;
6. load only the selected TE/context network;
7. create exactly one Co-expression renderer.

Co-expression -> Knowledge Graph:

1. abort or invalidate any in-flight Co-expression request;
2. destroy the Co-expression renderer;
3. clear Co-expression tooltip and selection state;
4. hide Co-expression controls and surface;
5. restore Knowledge Graph surface, controls, legend, detail, and Back state;
6. do not refetch or redraw the Knowledge Graph unless its existing restore
   contract requires it.

Knowledge-only asynchronous entrypoints must check `workspaceMode` immediately
before rendering. A DeepThink answer graph received while Co-expression is
active must explicitly switch the workspace to Knowledge Graph before applying
the answer; it must never replace the Co-expression surface in place.

Rapid mode or context switches use a monotonically increasing request epoch.
Late responses must not replace the active mode or newer selection.

### 3.3 URL Contract

Supported Co-expression URL:

```text
preview.php?mode=coexpression&te=L1HS&context=cancer_cell_line
```

Rules:

- omitted `mode` preserves the current Knowledge Graph default;
- invalid TE falls back to L1HS with a visible nonfatal notice;
- unavailable context falls back to the first available context and explains
  why;
- browser Back/Forward restores workspace mode, TE, and context;
- existing `preview.php?q=LINE1` behavior remains unchanged.

## 4. File Ownership

### 4.1 New Runtime Files

- `api/coexpression.php`
  - HTTP method, action dispatch, status codes, JSON output.
- `api/coexpression_repository.php`
  - approved-root resolution, manifest/tier parsing, catalog construction,
    network loading, schema validation.
- `assets/js/renderers/g6/coexpression/coexpression-contract.js`
  - browser-side response normalization and invariant checks.
- `assets/js/renderers/g6/coexpression/coexpression-styles.js`
  - node/edge style functions and legend metadata only.
- `assets/js/renderers/g6/coexpression/coexpression-renderer.js`
  - one G6 instance, layout, local interactions, selection, export, destroy.
- `assets/js/pages/preview/coexpression-mode.js`
  - catalog/network requests, request epochs, cache, controls, details, and
    mode lifecycle.
- `test/coexpression_renderer_harness.html`
  - isolated browser host for renderer development and Canvas/performance
    checks; never linked from production navigation.

### 4.2 Modified Runtime Files

- `preview.php`
  - segmented mode control;
  - Co-expression TE/context controls;
  - dedicated `#g6-coexpression-surface`;
  - dedicated `#coexpression-inspect-card` overlay because the existing
    parent `#node-details` is intentionally hidden in the preview workspace;
  - script loading and cache version entries.
- `assets/css/pages/preview.css`
  - mode control, Co-expression controls, legend, empty/error state, responsive
    behavior.
- `assets/js/renderers/g6/index-g6.bootstrap.js`
  - only the minimal workspace-mode coordinator and Knowledge Graph
    snapshot/restore hooks.
- `assets/js/tekg_paths.php` only if the existing generic `apiUrl()` helper
  cannot resolve `coexpression.php`; do not modify it preemptively.

### 4.3 New Checks

- `scripts/checks/check_coexpression_data_contract.py`
- `scripts/checks/check_coexpression_api_contract.py`
- `scripts/checks/check_coexpression_frontend_static_contract.py`
- `scripts/checks/check_coexpression_graph_browser.py`
- `scripts/checks/measure_coexpression_graph_performance.py`

### 4.4 Documentation

- `docs/coexpression/frontend_contract.md`
- `docs/architecture/graph_runtime.md`
- `AI_HANDOFF.md`
- this execution plan
- `.gitignore`
  - add only the narrow exceptions needed to keep the accepted frontend
    contract and dated evaluation evidence visible to Git.

### 4.5 Forbidden Runtime Edits

Do not modify unless a verified blocker proves it necessary:

- `api/graph.php`
- `api/graph_service.php`
- `api/agent/`
- `assets/js/renderers/g6/index-g6-shared.js`
- `assets/js/renderers/g6/index-g6-embed.js`
- `assets/html/preview_graph.html`
- archived taxonomy large-force files
- co-expression analysis outputs and generation scripts

## 5. Reuse Boundary

### 5.1 Reuse Directly

- `preview.php` workspace shell and surface stack;
- local AntV G6 dependency;
- loader behavior;
- detail-panel container and card visual language;
- existing path helper;
- local hover/selection update pattern;
- Canvas PNG export conventions;
- browser error capture, Canvas nonblank checks, and ordinary Graph smoke;
- renderer instance, request epoch, and destroy diagnostics concepts;
- bounded force cooling and idle-stability measurements.

### 5.2 Adapt, Do Not Copy Blindly

- Evidence Graph node click/detail logic becomes TE/Gene/edge-specific cards.
- Evidence Graph legend shell may host Co-expression legend content, but
  relation/PMID semantics are not reused.
- Existing autocomplete may be extended with a `coexpression-te` source only
  if the change is isolated and all existing sources retain their contracts.
- Existing CSV/PNG export UI may dispatch to the active mode, but each mode
  supplies its own export payload.

### 5.3 Do Not Reuse

- taxonomy adapter;
- Class/Order/Superfamily styling;
- deterministic taxonomy rings or star anchors;
- Neo4j Expand/Jump semantics;
- PMID evidence cards;
- disease/relation legend filters;
- classification edge styles;
- the 217/932-node taxonomy performance profile.

## 6. Visual and Interaction Contract

### 6.1 Layout

The Co-expression surface uses one bounded G6 `d3-force` layout because each
payload is small.

Required forces:

- link distance weighted within a bounded range by correlation;
- collision radius based on rendered node size;
- moderate many-body repulsion;
- weak center force;
- no taxonomy or module-star anchors;
- no permanently active simulation.

Acceptance:

- first interactive frame within 800 ms on the reference machine;
- layout stops within 1,000 ms;
- drag release cools within 800 ms;
- subsequent 500 ms idle window has no coordinate changes;
- one renderer and at most one active layout;
- no graph render/destroy on hover or selection.

### 6.2 Node Encoding

- center TE: largest node, unique dark outline, label always visible;
- other TE: circular node, TE color;
- Gene: distinct shape or clearly distinct color;
- module hub: stronger outline, not a fourth entity type;
- selected: one consistent selection ring;
- labels: center always; other labels on hover/selection; optional sparse labels
  for hubs only.

Do not encode module type as another competing node color. Module type belongs
in the summary/detail panel.

### 6.3 Edge Encoding

- all displayed edges are positive correlations;
- width maps monotonically to correlation within a restrained range;
- opacity maps monotonically to correlation;
- TE-TE, TE-Gene, and Gene-Gene may use restrained hue differences;
- center-neighbor edges are more visible than selected internal edges;
- no arrowheads;
- no causal verbs;
- no default edge labels.

### 6.4 Controls

Visible only in Co-expression mode:

- TE autocomplete/search;
- context segmented control;
- `Center edges / All selected edges` option;
- TE and Gene visibility checkboxes through an explicit Apply action;
- compact method/threshold summary;
- persistent correlation-only notice.

Hidden in Co-expression mode:

- Knowledge Graph entity-type selector;
- Expand mode;
- relation labels;
- Expression evidence layer;
- fixed Knowledge Graph view;
- taxonomy source;
- PMID relation controls.

### 6.5 Details

Center TE card:

- TE and context;
- module ID/type/size;
- TE and Gene counts;
- confidence;
- enriched terms when available;
- generated interpretation statement;
- correlation-only notice.

Gene/partner TE card:

- name and feature type;
- role;
- module hub state;
- correlation to center when present;
- no unsupported biological description.

Edge card:

- source and target;
- Spearman correlation;
- FDR;
- pair type;
- center/internal role;
- statistical-association notice.

All dynamic text must be escaped or assigned with `textContent`.

Co-expression details render in `#coexpression-inspect-card` inside the
Co-expression surface. Reuse the visual language of the iframe inspect card,
not its evidence-card HTML builder or PMID/node-action behavior.

## 7. Incremental Execution Tasks

Each task is one user-visible checkpoint. Default execution uses the main AI
only. At most one read-only Explorer may be used when a task has an unresolved
ownership question. Do not dispatch parallel Workers or Reviewers unless the
user explicitly approves them for that task.

### Task 1: Freeze Baseline and Validate Offline Display Data

**Files:**

- Create: `scripts/checks/check_coexpression_data_contract.py`
- Create: `docs/eval/runs/<date>-coexpression-dual-mode/baseline.json`
- Create: `docs/eval/runs/<date>-coexpression-dual-mode/README.md`
- Modify: `.gitignore` with only the dated evidence exception

**Steps:**

- [ ] Write a failing check for the approved version root, manifest, tier table,
      849 files, unique node IDs, endpoint integrity, positive correlations,
      and the 50-node/150-edge display caps.
- [ ] Run it before adding any normalization or exception logic and record the
      expected failure if the check exposes an undocumented field shape.
- [ ] Make only test/harness corrections; do not change data outputs.
- [ ] Record counts, node/edge min/max, missing contexts, file sizes, and sample
      L1HS metadata.
- [ ] Run the existing Knowledge Graph tree and `LINE1` browser smoke as the
      pre-integration baseline.

**Commands:**

```powershell
python scripts/checks/check_coexpression_data_contract.py
python scripts/checks/check_g6_te_tree_load_regression.py
python scripts/checks/check_g6_browser_smoke.py
```

**Acceptance:**

- all 849 files validate;
- no endpoint or duplicate-ID errors;
- the six unavailable combinations are explicitly recorded;
- ordinary Graph baseline is green;
- no runtime file is edited.

**Time box:** 20-30 minutes. Stop after evidence review.

### Task 2: Build the Filesystem Repository

**Files:**

- Create: `api/coexpression_repository.php`
- Create: `scripts/checks/check_coexpression_repository.php`

**Steps:**

- [ ] Write CLI repository tests first for catalog count, L1HS lookup, missing
      CR1 cancer context, unknown TE, path redaction, and payload invariants.
- [ ] Run the tests and confirm they fail because the repository does not exist.
- [ ] Implement an approved-root constant and canonical path containment check.
- [ ] Parse `all_te/manifest.json` for membership and file availability.
- [ ] Parse `display_tier_recommendations.tsv` with `fgetcsv(..., "\t")`.
- [ ] Build a 285-item catalog without opening 849 graph JSON files.
- [ ] Load and validate exactly one graph JSON for network requests.
- [ ] Strip `selection.edge_source` and any path-like metadata.
- [ ] Re-run repository tests and PHP syntax checks.

**Commands:**

```powershell
php -l api/coexpression_repository.php
php scripts/checks/check_coexpression_repository.php
```

**Acceptance:**

- catalog contains 285 unique TE items and 849 available combinations;
- network load returns L1HS cancer with valid nodes/edges;
- CR1 cancer reports unavailable with two available contexts;
- `../`, encoded separators, arbitrary path input, and unknown TE cannot escape
  the approved root;
- no runtime page changes.

**Time box:** 30-45 minutes. Stop after API-layer review.

### Task 3: Add the Read-Only HTTP API

**Files:**

- Create: `api/coexpression.php`
- Create: `scripts/checks/check_coexpression_api_contract.py`
- Modify: `scripts/checks/check_api_contracts.py` only to add stable happy-path
  coverage after the dedicated check passes

**Steps:**

- [ ] Write failing HTTP checks for catalog, L1HS network, missing context,
      invalid method/action/context, unknown TE, content type, and path leakage.
- [ ] Implement GET/OPTIONS handling following current API conventions.
- [ ] Dispatch only `catalog` and `network`.
- [ ] Convert repository exceptions to the locked status/error contract.
- [ ] Add `Cache-Control` appropriate for versioned local results while keeping
      errors uncached.
- [ ] Measure catalog and network response time over five local requests.

**Commands:**

```powershell
php -l api/coexpression.php
python scripts/checks/check_coexpression_api_contract.py
python scripts/checks/check_api_contracts.py
```

**Acceptance:**

- contract statuses and JSON shapes match Section 2;
- catalog median response <= 200 ms;
- network median response <= 150 ms;
- no warnings, stack traces, or absolute paths;
- existing API contracts remain green.

**Time box:** 30-45 minutes. Stop before frontend work.

### Task 4: Add Browser Data Contract and Adapter

**Files:**

- Create:
  `assets/js/renderers/g6/coexpression/coexpression-contract.js`
- Create:
  `scripts/checks/check_coexpression_frontend_static_contract.py`
- Create:
  `scripts/checks/check_coexpression_contract.js`

**Steps:**

- [ ] Write Node tests for catalog normalization, network normalization,
      duplicate IDs, missing endpoints, invalid correlation/FDR, unsafe counts,
      and unavailable-context fallback.
- [ ] Confirm the tests fail before the contract module exists.
- [ ] Implement pure normalization functions with no DOM or G6 dependency.
- [ ] Convert API nodes/edges into a stable internal graph model while retaining
      original scientific metadata under `data`.
- [ ] Generate deterministic edge IDs from source, target, and index without
      modifying node IDs.
- [ ] Expose diagnostics for input/output counts and rejected records.

**Commands:**

```powershell
node --check assets/js/renderers/g6/coexpression/coexpression-contract.js
node scripts/checks/check_coexpression_contract.js
python scripts/checks/check_coexpression_frontend_static_contract.py
```

**Acceptance:**

- pure adapter tests pass;
- the adapter neither fetches data nor creates G6;
- 26-node/100-edge L1HS input remains 26/100;
- invalid endpoints fail closed rather than silently disappearing.

**Time box:** 25-35 minutes. Stop before rendering.

### Task 5: Build an Isolated Co-expression Renderer

**Files:**

- Create:
  `assets/js/renderers/g6/coexpression/coexpression-styles.js`
- Create:
  `assets/js/renderers/g6/coexpression/coexpression-renderer.js`
- Create: `test/coexpression_renderer_harness.html`
- Create: `scripts/checks/check_coexpression_renderer_contract.js`
- Create or extend:
  `scripts/checks/measure_coexpression_graph_performance.py`

**Steps:**

- [ ] Write failing lifecycle/style contracts before renderer code.
- [ ] Implement `createRenderer({container, data, callbacks})`.
- [ ] Implement one G6 instance with bounded `d3-force`, canvas drag, zoom,
      node drag, hover, selection, and destroy.
- [ ] Implement local node/incident-edge state changes only.
- [ ] Implement `setData`, `setVisibility`, `getGraph`, `getDiagnostics`,
      `getVisibleSubgraph`, `exportPng`, and `destroy`.
- [ ] Verify selection and hover cause zero render/layout/create/destroy calls.
- [ ] Verify drag owns at most one force lifecycle and stops within the bounds.
- [ ] Render L1HS cancer in an isolated test host and capture a screenshot.

**Commands:**

```powershell
node --check assets/js/renderers/g6/coexpression/coexpression-styles.js
node --check assets/js/renderers/g6/coexpression/coexpression-renderer.js
node scripts/checks/check_coexpression_renderer_contract.js
python scripts/checks/measure_coexpression_graph_performance.py --te L1HS --context cancer_cell_line
```

**Acceptance:**

- nonblank Canvas;
- exactly 26 nodes and 100 edges for the reference L1HS payload;
- exactly one renderer;
- layout/drag cooling gates pass;
- screenshot clearly distinguishes center TE, other TE, Gene, and hubs;
- ordinary Graph runtime has not been wired or modified.

**Time box:** 45-60 minutes. This is the only phase allowed a 60-minute cap.

### Task 6: Build the Co-expression Mode Controller and Controls

**Files:**

- Create: `assets/js/pages/preview/coexpression-mode.js`
- Modify: `preview.php`
- Modify: `assets/css/pages/preview.css`
- Modify: `assets/js/components/te-autocomplete.js` only if its source extension
  is proven smaller than a dedicated controller-owned autocomplete

**Steps:**

- [ ] Add hidden Co-expression controls and surface without adding the
      Knowledge/Co-expression mode switch yet.
- [ ] Add the catalog request and a bounded in-memory response cache with a
      maximum of six networks.
- [ ] Implement TE selection, context availability/disable state, loading,
      unavailable, error, and empty states.
- [ ] Implement request epochs and abort signals.
- [ ] Mount the isolated renderer into `#g6-coexpression-surface`.
- [ ] Add a temporary test-only activation hook under
      `window.__TEKG_COEXPRESSION_MODE`; do not expose a production button yet.
- [ ] Browser-test L1HS across all three contexts and CR1 unavailable cancer.

**Acceptance:**

- catalog requested once;
- one network JSON requested per uncached selection;
- no other graph JSON is loaded;
- stale responses cannot replace newer context/TE selections;
- one renderer is created per Co-expression activation; context switches use
  its `setData()` path without create/destroy;
- leaving Co-expression destroys that renderer exactly once;
- controls fit desktop and mobile without overlap;
- Knowledge Graph remains the only user-visible mode.

**Time box:** 35-45 minutes. Stop for a functional prototype review.

### Task 7: Integrate the Dual-Mode Workspace Switch

**Files:**

- Modify: `preview.php`
- Modify: `assets/css/pages/preview.css`
- Modify: `assets/js/renderers/g6/index-g6.bootstrap.js`
- Modify: `assets/js/pages/preview/coexpression-mode.js`
- Create/extend:
  `scripts/checks/check_coexpression_graph_browser.py`

**Steps:**

- [ ] Write a failing browser contract for Knowledge -> Co-expression ->
      Knowledge roundtrip before adding the control.
- [ ] Add the segmented mode control with `aria-pressed`/tab semantics.
- [ ] Add only a thin coordinator in `index-g6.bootstrap.js`.
- [ ] Hide and restore mode-specific surfaces and controls.
- [ ] Preserve the existing Knowledge Graph query, counts, legend, history, and
      Back availability across a roundtrip.
- [ ] Invalidate stale Co-expression work when leaving the mode.
- [ ] Guard late Knowledge Graph loads, legend applies, Expression overlay
      updates, and QA answer-graph injection from overwriting an active
      Co-expression surface.
- [ ] Make a DeepThink answer graph explicitly switch to Knowledge mode before
      applying its graph action.
- [ ] Repeat the roundtrip five times and assert one active renderer/surface,
      no duplicate handlers, and no errors.

**Acceptance:**

- one visible workspace and one selected mode;
- Knowledge Graph remains default;
- direct Co-expression activation works;
- LINE1 evidence Graph returns with the same query and visible counts after a
  mode roundtrip;
- taxonomy tree returns as a tree when it was the prior Knowledge state;
- no co-expression semantics appear in Knowledge detail/legend/export;
- no Knowledge semantics appear in Co-expression.

**Time box:** 35-45 minutes. Stop after the user tests the switch.

### Task 8: Complete Details, Legend, Filters, and Scientific Copy

**Files:**

- Modify: `assets/js/pages/preview/coexpression-mode.js`
- Modify:
  `assets/js/renderers/g6/coexpression/coexpression-renderer.js`
- Modify:
  `assets/js/renderers/g6/coexpression/coexpression-styles.js`
- Modify: `assets/css/pages/preview.css`
- Modify: `scripts/checks/check_coexpression_graph_browser.py`

**Steps:**

- [ ] Add center TE, partner node, and edge detail-card contracts.
- [ ] Add TE/Gene/hub and edge-role legend.
- [ ] Add explicit Apply-based node-type visibility.
- [ ] Add Center edges / All selected edges control.
- [ ] Add tooltip content for node identity and edge r/FDR.
- [ ] Add the persistent correlation-only notice.
- [ ] Add a prohibited-language static scan for runtime Co-expression copy.
- [ ] Test L1HS high, L1HS low/not-interpretable, and CR1 context availability.

**Acceptance:**

- all details derive from returned fields;
- text is escaped;
- FDR and correlation remain numeric and correctly formatted;
- hidden-node filtering cannot leave visible edges with missing endpoints;
- gene-rich, TE-rich, low-confidence, and not-interpretable states are visibly
  distinguishable without implying causation;
- no mode contamination.

**Time box:** 35-45 minutes.

### Task 9: URL State, Export, Cache, Responsive, and Failure Recovery

**Files:**

- Modify: `preview.php`
- Modify: `assets/js/pages/preview/coexpression-mode.js`
- Modify:
  `assets/js/renderers/g6/coexpression/coexpression-renderer.js`
- Modify: `assets/css/pages/preview.css`
- Modify: `scripts/checks/check_coexpression_graph_browser.py`

**Steps:**

- [ ] Add failing URL/Back/Forward tests.
- [ ] Parse and normalize `mode`, `te`, and `context`.
- [ ] Push history only for real user mode/selection changes.
- [ ] Restore state on `popstate` without duplicate requests.
- [ ] Add mode-specific CSV export with nodes and edges plus version/threshold
      metadata.
- [ ] Route PNG export to the active renderer.
- [ ] Verify six-entry cache eviction and no stale response reuse across
      versions.
- [ ] Add retry for network failures without losing the selected TE/context.
- [ ] Verify 1440x960, 1024x768, 768x900, and 390x844 viewports.

**Acceptance:**

- direct URL opens the requested network;
- Back/Forward restores both modes correctly;
- CSV contains correlation/FDR and the interpretation limit;
- PNG is nonblank;
- no absolute path appears in exports;
- mobile controls wrap without text overlap or Canvas collapse;
- failed requests recover without a page reload.

**Time box:** 40-50 minutes.

### Task 10: Full Verification, Review, and Durable Handoff

**Files:**

- Modify: `docs/coexpression/frontend_contract.md`
- Modify: `docs/architecture/graph_runtime.md`
- Modify: `AI_HANDOFF.md`
- Modify: this plan
- Create: final evidence JSON and screenshots under the dated evaluation folder

**Steps:**

- [ ] Main AI reviews the complete diff against this plan.
- [ ] Run every new data/API/static/browser/performance check.
- [ ] Run ordinary Knowledge Graph API, tree, browser, legend, Expand, export,
      Back, and docs checks.
- [ ] Inspect desktop and mobile screenshots rather than trusting DOM counts.
- [ ] Record timings, requests, counts, renderer lifecycle, and residual risks.
- [ ] Record any pre-existing unrelated failure separately.
- [ ] Move the plan to `docs/exec-plans/completed/` only after every required
      criterion is accepted.

**Commands:**

```powershell
php -l preview.php
php -l api/coexpression.php
php -l api/coexpression_repository.php
node --check assets/js/renderers/g6/coexpression/coexpression-contract.js
node --check assets/js/renderers/g6/coexpression/coexpression-styles.js
node --check assets/js/renderers/g6/coexpression/coexpression-renderer.js
node --check assets/js/pages/preview/coexpression-mode.js
node --check assets/js/renderers/g6/index-g6.bootstrap.js
python scripts/checks/check_coexpression_data_contract.py
php scripts/checks/check_coexpression_repository.php
python scripts/checks/check_coexpression_api_contract.py
node scripts/checks/check_coexpression_contract.js
node scripts/checks/check_coexpression_renderer_contract.js
python scripts/checks/check_coexpression_frontend_static_contract.py
python scripts/checks/check_coexpression_graph_browser.py
python scripts/checks/measure_coexpression_graph_performance.py
python scripts/checks/check_api_contracts.py
python scripts/checks/check_g6_te_tree_load_regression.py
python scripts/checks/check_g6_browser_smoke.py
python scripts/checks/check_g6_inspect_card.py
python scripts/checks/check_g6_subgraph_export_smoke.py
python scripts/checks/check_g6_relation_legend_expand_mode.py
python scripts/checks/check_g6_legend_expand_tree_fixes.py
python scripts/checks/check_taxonomy_tree_only_runtime.py
python scripts/checks/check_no_legacy_db_fallback.py
python scripts/checks/check_taxonomy_runtime_truth.py
python scripts/checks/check_docs_freshness.py
```

**Acceptance:**

- all new contracts pass;
- ordinary Knowledge Graph contracts pass;
- no unexpected network request loads the full analysis edge TSVs or more than
  one display JSON;
- user accepts the visual and interaction result;
- no unresolved high/medium review finding;
- durable docs describe the actual integrated runtime.

**Time box:** 30-45 minutes after implementation is otherwise complete.

## 8. Global Performance Gates

Reference-machine budgets:

- catalog median HTTP <= 200 ms;
- network median HTTP <= 150 ms;
- mode activation to interactive Canvas <= 800 ms;
- context switch to interactive Canvas <= 600 ms after catalog load;
- pointer hover/leave <= 50 ms;
- selection detail update <= 50 ms;
- drag release cooling <= 800 ms;
- 500 ms post-cooling window with zero coordinate/tick changes;
- zero long task over 500 ms;
- exactly one Co-expression renderer while active;
- zero Co-expression renderers while Knowledge Graph is active;
- no more than one catalog request per page lifecycle;
- no more than one network request per uncached TE/context selection.

These budgets may be revised only with measured evidence and an execution-log
entry. They must not be relaxed merely to make a failing check green.

## 9. Global Stop Conditions

Stop the active task and return to the last accepted checkpoint if any occurs:

- ordinary Knowledge Graph becomes blank or loses elements;
- taxonomy tree no longer loads or expands;
- mode switch mixes nodes, edges, legends, details, history, or exports;
- more than one active Co-expression renderer or force simulation;
- a stale request replaces a newer TE/context;
- any API response or export exposes an absolute filesystem path;
- arbitrary path traversal reaches a file;
- edge endpoints are missing after filters;
- the browser loads a full analysis TSV or multiple graph JSON files;
- permanent force/tick activity remains after cooling;
- a user-visible statement implies regulation or causation;
- DeepThink/Agent behavior changes without a separate approved task;
- mobile controls overlap or collapse the graph surface;
- console errors, page errors, unhandled rejections, or stuck loaders appear.

Never use `git reset --hard`, never revert unrelated user changes, and never
repair an unrelated Graph issue merely because it appears during this plan.

## 10. Execution Governance

- Execute one task per conversation unless the user explicitly combines tasks.
- Start every task with `git status --short` and relevant diffs.
- Default to no subagents.
- A task may use one read-only Explorer for a narrow unresolved question.
- Do not use a Worker or Reviewer without explicit user approval for that task.
- The main AI owns all integration and verification decisions.
- No automatic commits.
- Record every accepted task in the execution log below.
- Do not describe a task as complete without fresh command evidence.

## 11. Execution Log

- 2026-07-19: Plan requested after confirming that current frontend-ready
  co-expression assets are small per-TE/per-context subgraphs rather than one
  all-TE graph. The current data layer has 285 TE features, 849 valid display
  JSON files, and six explicitly unavailable context combinations.
- 2026-07-19: Architecture direction locked to one `preview.php` workspace with
  separate Knowledge Graph and Co-expression modes. No runtime implementation
  was started while writing this plan.
- 2026-07-19: `check_taxonomy_runtime_truth.py` currently has the previously
  recorded unrelated `index.php` homepage-helper failure. Future verification
  must report its actual result but must not repair `index.php` inside this
  Co-expression plan.
