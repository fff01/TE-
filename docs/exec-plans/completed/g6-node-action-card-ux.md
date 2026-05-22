# G6 Node Action Card UX

## Background

`g6-evidence-support-ux-v1` is completed and archived. The legacy detail area is hidden, and edge evidence tables now render inside the expanded edge card `PubMed` section.

Current G6 node interaction still relies on global `Fixed view` / `Expand mode` controls and implicit node-click behavior. This stage changes node click into an explicit action-card workflow.

## Goals

- Hide user-visible `Fixed view` and `Expand mode` controls.
- Keep the current graph fixed by default.
- Clicking a node opens a node action card and does not automatically jump or expand.
- Node action card exposes explicit `Jump`, `Expand`, and `Details` actions.
- Preserve edge evidence card / PubMed table / CSV behavior.

## Scope

- Only G6/preview front-end and checks.
- Do not modify Neo4j data.
- Do not change graph API contract unless a tiny compatibility fix is proven necessary.
- Do not modify taxonomy, agent, expression, evidence support API, metrics imports, or layout mode.
- Keep old internal state variables where needed for compatibility; prefer hiding UI over deleting state.

## Implementation Plan

1. Inspect current `Fixed view`, `Expand mode`, node click, inspect card, and detail card code paths.
2. Add/extend browser smoke for node action card:
   - global controls hidden;
   - node click opens action card;
   - node click alone does not jump or expand;
   - `Jump`, `Expand`, `Details` buttons exist and call real paths;
   - edge evidence table remains available.
3. Add explicit G6 runner handlers:
   - `jumpToNode(node)` reuses existing node-centered graph load path;
   - `expandNodeFromCard(node)` reuses existing `onNodeExpand` / same-label expand path;
   - `showNodeDetails(node)` expands the node card without changing graph query or elements.
4. Hide `Fixed view` / `Expand mode` toolbar controls through CSS/DOM state while leaving compatibility variables intact.
5. Update old expand-mode browser checks only where necessary so they validate the new card `Expand` behavior rather than the removed global mode UI.
6. Run required regression checks.

## Verification

Required:

```powershell
php -l preview.php
php -l api/graph.php
php -l api/graph_service.php
node --check assets/js/renderers/g6/index-g6.bootstrap.js
node --check assets/js/renderers/g6/index-g6-shared.js
node --check assets/js/renderers/g6/index-g6-embed.js
python scripts/checks/check_g6_browser_smoke.py
python scripts/checks/check_g6_expand_mode_smoke.py --query LINE-1
python scripts/checks/check_g6_expand_layout_smoke.py --query LINE-1
python scripts/checks/check_g6_expand_disambiguation_smoke.py
python scripts/checks/check_g6_evidence_support_ux.py
python scripts/checks/check_g6_node_action_card_ux.py
python scripts/checks/check_api_contracts.py
```

## Archive Rule

If all required checks pass, move this file to `docs/exec-plans/completed/g6-node-action-card-ux.md` and update `docs/RELIABILITY.md` plus `docs/exec-plans/tech-debt-tracker.md`.

If any check fails, keep this plan active and record the failing command, cause, completed items, and next step.

## Execution Result - 2026-05-22

Status: completed.

Changed files:

- `assets/css/pages/preview.css`
- `assets/js/renderers/g6/index-g6.bootstrap.js`
- `assets/js/renderers/g6/index-g6-shared.js`
- `assets/js/renderers/g6/index-g6-embed.js`
- `scripts/checks/check_g6_browser_smoke.py`
- `scripts/checks/check_g6_expand_disambiguation_smoke.py`
- `scripts/checks/check_g6_expand_layout_smoke.py`
- `scripts/checks/check_g6_expand_mode_smoke.py`
- `scripts/checks/check_g6_node_action_card_ux.py`
- `docs/RELIABILITY.md`
- `docs/exec-plans/tech-debt-tracker.md`

Implementation notes:

- `Fixed view` and `Expand mode` controls remain in the compatibility DOM but are hidden from users.
- Node click now opens the inspect/action card only. It no longer auto-jumps or auto-expands.
- Node action card buttons are `Jump`, `Expand`, and `Details`.
- `Jump` reuses the existing node-centered graph load path.
- `Expand` reuses the existing `hooks.onNodeExpand(node)` path and preserves same-label disambiguation parameters: `expand_node_id`, `expand_node_type`, and `expand_query`.
- `Details` expands the node card without changing query or graph element count.
- Diagnostic bridge methods `inspectNode(nodeId)` and `triggerNodeAction(nodeId, action)` were added for smoke checks and reuse the real card/action paths.
- Edge evidence card, PubMed table, and CSV behavior remain unchanged.
- Force layout remains the default; no layout mode was added.

Verification passed:

```powershell
php -l preview.php
php -l api/graph.php
php -l api/graph_service.php
node --check assets/js/renderers/g6/index-g6.bootstrap.js
node --check assets/js/renderers/g6/index-g6-shared.js
node --check assets/js/renderers/g6/index-g6-embed.js
python scripts/checks/check_g6_browser_smoke.py
python scripts/checks/check_g6_expand_mode_smoke.py --query LINE-1
python scripts/checks/check_g6_expand_layout_smoke.py --query LINE-1
python scripts/checks/check_g6_expand_disambiguation_smoke.py
python scripts/checks/check_g6_evidence_support_ux.py
python scripts/checks/check_g6_node_action_card_ux.py
python scripts/checks/check_api_contracts.py
```

Residual risks:

- `Fixed view` / `Expand mode` internals still exist for compatibility; future cleanup should be a separate plan after references are audited.
- Node action card smoke uses diagnostic bridge methods to make browser tests deterministic.
- Expand/collapse affordance and multi-step expand UX remain separate future work.
