# Taxonomy Canvas Graph Integration Design

## Goal

Add the existing Canvas taxonomy visualization as an optional classification
Graph without replacing the accepted G6 classification Tree or affecting the
ordinary Knowledge Graph and Co-expression workspaces.

## User Experience

- Classification mode defaults to `Tree` and keeps the current G6 tree.
- A `Tree | Graph` switch appears immediately to the left of the existing
  `All | RMSK + RepBase` source switch.
- `Graph` renders the Canvas taxonomy visualization inside the existing
  classification surface. It does not navigate to `taxonomy_canvas_demo.php`.
- In Tree or Graph classification mode, the toolbar contains only the two
  classification switches. Relation controls, export, fixed view, expand mode,
  Back, and Reset are hidden until an ordinary dynamic Knowledge Graph is open.
- Canvas class labels are always enabled. The demo's `Show class labels`,
  `Restart`, and `Fit` controls are not integrated.

## Architecture

- Keep `api/taxonomy.php?view=tree` as the only taxonomy runtime source.
- Extract the reusable Canvas renderer behavior from the isolated demo into a
  production-owned renderer under `assets/js/renderers/canvas-force/`.
- Give the Canvas renderer its own surface inside the Knowledge Graph iframe.
- Let `index-g6.bootstrap.js` remain the owner of classification mode, source
  selection, toolbar visibility, history, and transitions into ordinary graph
  queries.
- Do not reactivate the archived G6 large-force taxonomy renderer.

## Legend Contract

- Reuse the existing right-side Knowledge Graph legend container and visual
  language.
- Legend items represent taxonomy levels: Human TE, Class, Order,
  Superfamily, Family, Subfamily, and deeper levels when present.
- Clicking a legend row temporarily emphasizes nodes and edges for that level
  while dimming unrelated elements. Clicking the same row again clears focus.
- Checkboxes change pending level visibility; `Apply` commits visibility to the
  Canvas renderer without rebuilding data or restarting layout.
- An edge is visible only when both endpoint nodes are visible.
- Switching Tree/Graph or taxonomy source refreshes legend metadata and clears
  transient focus while preserving the selected source.

## State And Failure Behavior

- Default state is `Tree` plus the current default taxonomy source.
- Canvas data requests use the existing bounded taxonomy request behavior.
- Loading, empty, and error states settle through the existing graph loader and
  details surfaces; the standalone demo status panel is not integrated.
- Switching away from Canvas stops its animation loop. Returning reuses the
  current model and positions when the taxonomy source has not changed.

## Verification

- Static checks cover the new surface, switch, asset loading, toolbar-mode
  rules, legend bridge, and absence of archived large-force runtime loading.
- Browser checks cover Tree default, Tree/Graph switching, both taxonomy
  sources, always-on class labels, legend focus, Apply visibility, mode changes,
  and return to an ordinary Knowledge Graph.
- Existing Graph, loader, legend, navigation, Co-expression, taxonomy truth,
  and legacy-fallback checks must continue to pass.

## Non-Goals

- Replacing the current G6 classification Tree.
- Reusing one renderer instance for Canvas taxonomy and G6 evidence graphs.
- Reintroducing the archived G6 large-force taxonomy implementation.
- Defining the final click/double-click behavior for opening a TE dynamic graph;
  that interaction will remain disabled or unchanged until separately agreed.
