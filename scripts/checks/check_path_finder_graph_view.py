from __future__ import annotations

from harness_lib import app_url, fail, ok, require, run_check


def main() -> None:
    try:
        from playwright.sync_api import Error as PlaywrightError
        from playwright.sync_api import sync_playwright
    except ImportError:
        fail("Playwright is not installed")

    with sync_playwright() as p:
        try:
            browser = p.chromium.launch(headless=True)
        except PlaywrightError as exc:
            fail(f"Unable to launch Chromium: {exc}")

        page = browser.new_page(viewport={"width": 1440, "height": 980})
        console_errors: list[str] = []
        graph_api_requests: list[str] = []
        page.on("console", lambda msg: console_errors.append(msg.text) if msg.type == "error" else None)
        page.on("request", lambda request: graph_api_requests.append(request.url) if "api/graph.php" in request.url else None)
        try:
            page.goto(app_url("path_finder.php"), wait_until="domcontentloaded", timeout=30000)
            page.locator("#pathSourceType").select_option("TE")
            page.locator("#pathSource").fill("AluJb")
            page.locator("#pathTargetType").select_option("Disease")
            page.locator("#pathTarget").fill("Cancer")
            page.locator("#pathMaxDepth").select_option("3")
            page.locator("#pathSubmit").click()
            page.locator("#pathResults .path-card").first.wait_for(timeout=60000)

            table_text = page.locator("#pathResults").text_content(timeout=10000) or ""
            page.locator("#pathGraphView").click()
            page.locator("#pathGraphPanel").wait_for(state="visible", timeout=10000)
            page.locator("#pathGraphSurface canvas").first.wait_for(timeout=60000)
            page.wait_for_function(
                "() => !document.querySelector('#pathGraphExport')?.disabled",
                timeout=60000,
            )
            page.wait_for_function(
                "() => !!window.__TEKG_PATH_FINDER_GRAPH_DEBUG?.getVisibleSubgraph()?.nodes?.length",
                timeout=60000,
            )
            inspected = page.evaluate("() => window.__TEKG_PATH_FINDER_GRAPH_DEBUG.inspectFirstNode()")
            require(inspected is True, "Path Finder graph debug bridge should inspect the first node")
            graph_debug_state = page.evaluate(
                """
                () => {
                  const subgraph = window.__TEKG_PATH_FINDER_GRAPH_DEBUG.getVisibleSubgraph();
                  const nodes = subgraph?.nodes || [];
                  const highlighted = nodes.filter((node) => node.endpointHighlight === true);
                  const minSize = nodes.reduce((value, node) => Math.min(value, Number(node.size || 0)), Infinity);
                  return {
                    nodeCount: nodes.length,
                    edgeCount: subgraph?.edges?.length || 0,
                    highlighted: highlighted.map((node) => ({ label: node.label, size: node.size })),
                    minSize: Number.isFinite(minSize) ? minSize : 0,
                  };
                }
                """
            )
            node_action_state = page.evaluate(
                """
                () => ({
                  jumpCount: document.querySelectorAll('#pathGraphSurface [data-node-action="jump"]').length,
                  expandCount: document.querySelectorAll('#pathGraphSurface [data-node-action="expand"]').length,
                  detailCount: document.querySelectorAll('#pathGraphSurface [data-node-action="details"]').length,
                })
                """
            )

            state = page.evaluate(
                """
                () => ({
                  tableHidden: document.querySelector('#pathResults')?.hidden === true,
                  graphHidden: document.querySelector('#pathGraphPanel')?.hidden === true,
                  showNamesText: document.querySelector('#pathGraphShowNames')?.textContent.trim() || '',
                  showNamesPressed: document.querySelector('#pathGraphShowNames')?.getAttribute('aria-pressed') || '',
                  showRelationsText: document.querySelector('#pathGraphShowRelations')?.textContent.trim() || '',
                  showRelationsPressed: document.querySelector('#pathGraphShowRelations')?.getAttribute('aria-pressed') || '',
                  exportDisabled: document.querySelector('#pathGraphExport')?.disabled === true,
                  canvasCount: document.querySelectorAll('#pathGraphSurface canvas').length,
                })
                """
            )
        finally:
            browser.close()

    require(not console_errors, "Path Finder graph console errors: " + " | ".join(console_errors[:5]))
    require(not graph_api_requests, "Path Finder graph view should not request graph.php: " + " | ".join(graph_api_requests[:5]))
    require("Relation type" not in table_text, "Path Finder table should not show raw relation type metadata")
    require("BIO_RELATION" not in table_text, "Path Finder table should not show raw BIO_RELATION labels")
    require(state["tableHidden"] is True, f"Table results should hide in graph mode: {state}")
    require(state["graphHidden"] is False, f"Graph panel should be visible in graph mode: {state}")
    require(state["showNamesPressed"] == "true" and "On" in state["showNamesText"], f"Show names should default on: {state}")
    require(state["showRelationsPressed"] == "true" and "On" in state["showRelationsText"], f"Show relations should default on: {state}")
    require(state["exportDisabled"] is False, f"Export should be enabled after graph render: {state}")
    require(state["canvasCount"] > 0, f"Graph surface should contain a G6 canvas: {state}")
    require(graph_debug_state["nodeCount"] > 0, f"Graph should expose visible nodes: {graph_debug_state}")
    require(graph_debug_state["edgeCount"] > 0, f"Graph should expose visible edges: {graph_debug_state}")
    require(len(graph_debug_state["highlighted"]) == 2, f"Source and target should be highlighted: {graph_debug_state}")
    require(all(node["size"] >= 104 for node in graph_debug_state["highlighted"]), f"Highlighted nodes should be enlarged: {graph_debug_state}")
    require(graph_debug_state["minSize"] >= 72, f"Path Finder graph nodes should use enlarged minimum sizes: {graph_debug_state}")
    require(node_action_state["jumpCount"] == 0, f"Path Finder node card should not show Jump: {node_action_state}")
    require(node_action_state["expandCount"] == 0, f"Path Finder node card should not show Expand: {node_action_state}")
    require(node_action_state["detailCount"] > 0, f"Path Finder node card should still show Details: {node_action_state}")
    ok("Path Finder graph view smoke passed")


if __name__ == "__main__":
    run_check(main)
