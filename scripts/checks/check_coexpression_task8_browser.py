from __future__ import annotations

from pathlib import Path

from harness_lib import ROOT, app_url, fail, ok, require, run_check


CASES = [
    ("L1HS", "cancer_cell_line", 26, 100),
    ("L1HS", "normal_cell_line", 26, 100),
    ("L1HS", "normal_tissue", 26, 100),
    ("LTR5", "cancer_cell_line", 13, 36),
    ("MER11B", "cancer_cell_line", 15, 100),
    ("HERVH-int", "cancer_cell_line", 19, 100),
    ("CR1", "normal_tissue", 13, 77),
]


def main() -> None:
    try:
        from playwright.sync_api import Error as PlaywrightError
        from playwright.sync_api import sync_playwright
    except ImportError:
        fail("Playwright is not installed.")

    evidence_dir = ROOT / "docs" / "eval" / "runs" / "2026-07-25-coexpression-dual-mode" / "representative-cases"
    evidence_dir.mkdir(parents=True, exist_ok=True)

    with sync_playwright() as playwright:
        try:
            browser = playwright.chromium.launch(headless=True)
        except PlaywrightError as exc:
            fail(f"Unable to launch Chromium: {exc}")

        page = browser.new_page(viewport={"width": 1440, "height": 960})
        errors: list[str] = []
        page.on("pageerror", lambda error: errors.append(str(error)))
        page.on("console", lambda message: errors.append(message.text) if message.type == "error" else None)
        try:
            center_expression: dict[str, float] = {}
            page.goto(app_url("preview.php"), wait_until="domcontentloaded", timeout=30_000)
            page.wait_for_function(
                "() => window.__TEKG_COEXPRESSION_MODE && window.__TEKG_PREVIEW_WORKSPACE_MODE",
                timeout=15_000,
            )
            require(
                page.locator("#graph-expression-layer").count() == 0,
                "Knowledge Graph still exposes the retired Expression control.",
            )
            if page.locator("#qaOverlay.is-open").count():
                page.click("#qaFab")
                page.wait_for_function("() => !document.querySelector('#qaOverlay').classList.contains('is-open')")

            for te, context, nodes, edges in CASES:
                page.evaluate(
                    """([te, context]) => window.__TEKG_PREVIEW_WORKSPACE_MODE.setMode(
                      'coexpression',
                      { te, context }
                    )""",
                    [te, context],
                )
                page.wait_for_function(
                    """([te, context]) => {
                      const state = window.__TEKG_COEXPRESSION_MODE.getDiagnostics();
                      return state.state === 'ready'
                        && state.selection?.te === te
                        && state.selection?.context === context;
                    }""",
                    arg=[te, context],
                    timeout=30_000,
                )
                state = page.evaluate("() => window.__TEKG_COEXPRESSION_MODE.getDiagnostics()")
                require(
                    state["nodeCount"] == nodes and state["edgeCount"] == edges,
                    f"Unexpected {te}/{context} graph: {state}",
                )
                require(
                    state["expressionEnabled"] is True and state["expressionAvailableCount"] > 0,
                    f"Expression activity did not load for {te}/{context}: {state}",
                )
                graph = page.evaluate(
                    "() => document.querySelector('#coexpression-graph-frame').contentWindow.__TEKG_COEXPRESSION_EMBED.getVisibleSubgraph()"
                )
                ids = {node["id"] for node in graph["nodes"]}
                require(
                    all(edge["source"] in ids and edge["target"] in ids for edge in graph["edges"]),
                    f"Visible edges have missing endpoints for {te}/{context}.",
                )
                te_nodes = [node for node in graph["nodes"] if node["type"] == "TE"]
                gene_nodes = [node for node in graph["nodes"] if node["type"] == "Gene"]
                center_node = next(node for node in graph["nodes"] if node["id"] == te)
                partner_sizes = [
                    float(node["size"])
                    for node in graph["nodes"]
                    if node["id"] != te and node.get("size") is not None
                ]
                require(
                    partner_sizes and float(center_node["size"]) > max(partner_sizes),
                    f"Center TE is not the largest node for {te}/{context}: {center_node}",
                )
                require(
                    any(node["expression_value"] is not None for node in te_nodes),
                    f"No TE activity values reached the renderer for {te}/{context}.",
                )
                require(
                    all(node["expression_value"] is None for node in gene_nodes),
                    f"Gene nodes incorrectly received TE Expression values for {te}/{context}.",
                )
                if context == "cancer_cell_line" and te in {"LTR5", "L1HS", "HERVH-int"}:
                    center_expression[te] = float(center_node["expression_value"])
                if te in {"LTR5", "MER11B", "HERVH-int", "CR1"}:
                    page.screenshot(
                        path=str(evidence_dir / f"{te.replace('/', '_')}-{context}.png"),
                        full_page=True,
                    )

            require(
                center_expression["LTR5"] < center_expression["L1HS"] < center_expression["HERVH-int"],
                f"Representative center Expression ordering changed: {center_expression}",
            )

            page.evaluate(
                "() => window.__TEKG_COEXPRESSION_MODE.activate({te:'LTR5', context:'cancer_cell_line'})"
            )
            page.wait_for_function(
                "() => window.__TEKG_COEXPRESSION_MODE.getDiagnostics().state === 'ready'"
                " && window.__TEKG_COEXPRESSION_MODE.getDiagnostics().selection?.te === 'LTR5'",
                timeout=30_000,
            )
            inspected = page.evaluate(
                """() => {
                  const win = document.querySelector('#coexpression-graph-frame').contentWindow;
                  const embed = win.__TEKG_COEXPRESSION_EMBED;
                  const graph = embed.getVisibleSubgraph();
                  const edgeId = graph.edges[0]?.id || '';
                  const nodeOpened = embed.inspectNode('LTR5');
                  const nodeText = win.document.querySelector('.inspect-card')?.textContent || '';
                  const edgeOpened = edgeId ? embed.inspectEdge(edgeId) : false;
                  const edgeText = win.document.querySelector('.inspect-card')?.textContent || '';
                  return { nodeOpened, edgeOpened, nodeText, edgeText };
                }"""
            )
            require(
                inspected["nodeOpened"]
                and "Co-expression Node" in inspected["nodeText"]
                and "Expression Activity" in inspected["nodeText"],
                f"Center TE detail card is incomplete: {inspected}",
            )
            require(
                inspected["edgeOpened"]
                and "Spearman r" in inspected["edgeText"]
                and "FDR" in inspected["edgeText"],
                f"Co-expression edge detail card is incomplete: {inspected}",
            )

            page.evaluate(
                "() => window.__TEKG_COEXPRESSION_MODE.setViewOptions({showTE:true, showGene:false, edgeScope:'all'})"
            )
            page.wait_for_function(
                "() => window.__TEKG_COEXPRESSION_MODE.getDiagnostics().state === 'ready'",
                timeout=30_000,
            )
            te_only = page.evaluate(
                "() => document.querySelector('#coexpression-graph-frame').contentWindow.__TEKG_COEXPRESSION_EMBED.getVisibleSubgraph()"
            )
            require(
                all(node["type"] == "TE" for node in te_only["nodes"]),
                "Gene visibility filtering left Gene nodes visible.",
            )

            page.evaluate(
                "() => window.__TEKG_COEXPRESSION_MODE.setViewOptions({showTE:true, showGene:true, edgeScope:'center'})"
            )
            page.wait_for_function(
                "() => window.__TEKG_COEXPRESSION_MODE.getDiagnostics().state === 'ready'",
                timeout=30_000,
            )
            center_only = page.evaluate(
                "() => document.querySelector('#coexpression-graph-frame').contentWindow.__TEKG_COEXPRESSION_EMBED.getVisibleSubgraph()"
            )
            require(
                all(edge["source"] == "LTR5" or edge["target"] == "LTR5" for edge in center_only["edges"]),
                "Center-edge filtering retained internal edges.",
            )

            page.evaluate("() => window.__TEKG_COEXPRESSION_MODE.setExpressionEnabled(false)")
            page.wait_for_function(
                "() => window.__TEKG_COEXPRESSION_MODE.getDiagnostics().expressionEnabled === false",
                timeout=10_000,
            )
            expression_off = page.evaluate(
                "() => document.querySelector('#coexpression-graph-frame').contentWindow.__TEKG_COEXPRESSION_EMBED.getVisibleSubgraph()"
            )
            require(
                all(node["expression_context"] == "off" for node in expression_off["nodes"] if node["type"] == "TE"),
                "Expression Off left active TE activity state in the renderer.",
            )
            page.evaluate("() => window.__TEKG_COEXPRESSION_MODE.setExpressionEnabled(true)")
            page.wait_for_function(
                "() => window.__TEKG_COEXPRESSION_MODE.getDiagnostics().expressionEnabled === true",
                timeout=10_000,
            )

            page.evaluate(
                "() => window.__TEKG_COEXPRESSION_MODE.activate({te:'CR1', context:'cancer_cell_line'})"
            )
            page.wait_for_function(
                "() => window.__TEKG_COEXPRESSION_MODE.getDiagnostics().state === 'unavailable'",
                timeout=10_000,
            )
            unavailable = page.evaluate("() => window.__TEKG_COEXPRESSION_MODE.getDiagnostics()")
            require(
                unavailable["availableContexts"] == ["normal_cell_line", "normal_tissue"],
                f"CR1 unavailable recovery lost valid alternatives: {unavailable}",
            )

            require(not errors, f"Browser errors: {errors[:5]}")
            ok("Task 8 representative networks, Expression activity, filtering, and scientific isolation passed")
        finally:
            page.close()
            browser.close()


if __name__ == "__main__":
    run_check(main)
