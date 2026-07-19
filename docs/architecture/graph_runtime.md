# G6 Graph Runtime

This document records the current TE-KG graph runtime structure. It is a compact
maintenance map, not a complete code explanation.

## Current Entrypoints

- Page entrypoint: `preview.php`
- Graph API: `api/graph.php`
- Graph service: `api/graph_service.php`
- Iframe page: `assets/html/preview_graph.html`
- G6 runtime: `assets/js/renderers/g6/`

## Current Interaction Model

- `preview.php` owns the page shell, query bar, legends, top controls, and
  parent-page state.
- The iframe G6 runner owns actual graph rendering.
- Parent page and iframe communicate through a bridge.
- Legends support entity and relation modes and apply filters through an Apply
  action.
- `Fixed view`, `Expand mode`, `Show names`, and `Show labels` should use
  lightweight state updates when possible. They should not trigger full graph
  reloads unless necessary.

## Current Risks

- G6 browser smoke tests can expose blank canvases and stuck loader states.
- Local smoke environments may block external CDN resources such as `@antv/g6`
  and `marked`; prefer local assets where possible.
- Expand mode business correctness remains an independent technical debt item.
  Do not repair it blindly without browser evidence.
- Taxonomy large-graph rendering is currently experimental. Keep it isolated
  from ordinary evidence graph behavior until accepted.

## Taxonomy Runtime Decision

As of 2026-07-19, the force-directed All-TE classification Graph is archived and
paused. The preview taxonomy workflow always renders the existing collapsible
tree, the Tree/Graph display switch is removed, and large-force taxonomy scripts
are not loaded by the runtime. The experimental source is retained only for
reference. Restoring it requires a new plan and fresh visual, browser, and
ordinary-Graph regression acceptance.

## Related Checks

- `scripts/checks/check_g6_browser_smoke.py`
- `scripts/checks/check_g6_static_contract.py`
- `scripts/checks/check_g6_no_legacy_disease_node.py`
- `scripts/checks/check_g6_relation_legend_expand_mode.py`
- `scripts/checks/check_g6_legend_expand_tree_fixes.py`
