from __future__ import annotations

import json
from typing import Any
from urllib.parse import quote

from harness_lib import app_url, fail, ok, require, run_check


def evidence(data: dict[str, Any]) -> str:
    return json.dumps(data, ensure_ascii=False, indent=2, sort_keys=True)


def counts(state: dict[str, Any] | None) -> dict[str, int]:
    elements = state.get("currentElements") if isinstance(state, dict) else []
    if not isinstance(elements, list):
        elements = []
    nodes = 0
    edges = 0
    for item in elements:
        data = item.get("data") if isinstance(item, dict) else None
        if not isinstance(data, dict):
            continue
        if data.get("source") and data.get("target"):
            edges += 1
        else:
            nodes += 1
    return {"nodes": nodes, "edges": edges, "elements": len(elements)}


def main() -> None:
    try:
        from playwright.sync_api import Error as PlaywrightError
        from playwright.sync_api import TimeoutError as PlaywrightTimeoutError
        from playwright.sync_api import sync_playwright
    except ImportError:
        fail(
            "Playwright is not installed. Run:\n"
            "  pip install -r requirements-dev.txt\n"
            "  python -m playwright install chromium"
        )

    query = "LINE-1"
    url = app_url(f"preview.php?q={quote(query)}")
    console_errors: list[str] = []
    page_errors: list[str] = []
    failed_requests: list[str] = []
    graph_requests: list[str] = []

    with sync_playwright() as p:
        try:
            browser = p.chromium.launch(headless=True)
        except PlaywrightError as exc:
            fail(f"Unable to launch Chromium. Run python -m playwright install chromium. Original error: {exc}")

        page = browser.new_page(viewport={"width": 1440, "height": 960})
        page.on("console", lambda msg: console_errors.append(msg.text) if msg.type == "error" else None)
        page.on("pageerror", lambda exc: page_errors.append(str(exc)))
        page.on("requestfailed", lambda request: failed_requests.append(f"{request.url} :: {request.failure}"))
        page.on("request", lambda request: graph_requests.append(request.url) if "api/graph.php" in request.url else None)

        try:
            page.goto(url, wait_until="domcontentloaded", timeout=30000)
            page.wait_for_selector("#g6-dynamic-surface iframe", timeout=30000)
            frame = page.frame_locator("#g6-dynamic-surface iframe")
            frame.locator("#container").wait_for(timeout=30000)
            page.wait_for_function(
                """() => {
                    const loader = document.querySelector('#graph-preloader');
                    const legend = document.querySelector('#graph-legend-list');
                    return loader
                        && legend
                        && loader.getAttribute('aria-hidden') === 'true'
                        && !legend.textContent.includes('Loading legend');
                }""",
                timeout=45000,
            )

            controls = page.evaluate(
                """() => {
                    const fixed = document.querySelector('#toggle-fixed-view');
                    const expand = document.querySelector('#toggle-expand-mode');
                    const detail = document.querySelector('#node-details');
                    const visible = (el) => !!el && !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
                    return {
                        fixedVisible: visible(fixed),
                        expandVisible: visible(expand),
                        detailVisible: visible(detail),
                    };
                }"""
            )
            require(controls["fixedVisible"] is False, "Fixed view user control must be hidden\n" + evidence(controls))
            require(controls["expandVisible"] is False, "Expand mode user control must be hidden\n" + evidence(controls))
            require(controls["detailVisible"] is False, "Legacy detail area must stay hidden\n" + evidence(controls))

            before = page.evaluate("() => window.__TEKG_G6_BRIDGE.getState()")
            before_counts = counts(before)
            node_probe = page.evaluate(
                """async () => {
                    const iframe = document.querySelector('#g6-dynamic-surface iframe');
                    const embed = iframe && iframe.contentWindow ? iframe.contentWindow.__TEKG_G6_EMBED : null;
                    if (!embed || typeof embed.getVisibleSubgraph !== 'function') {
                        return { error: 'missing embed bridge' };
                    }
                    const subgraph = await embed.getVisibleSubgraph();
                    const nodes = Array.isArray(subgraph && subgraph.nodes) ? subgraph.nodes : [];
                    const initial = String(subgraph.query || '').trim().toLowerCase();
                    const preferred = nodes.find((item) => String(item.rawLabel || item.label || '').trim() === 'L1HS');
                    const node = preferred || nodes.find((item) => {
                        const label = String(item.rawLabel || item.label || '').trim().toLowerCase();
                        return label && label !== initial && item.type !== 'Paper';
                    });
                    return { node, hasInspectNode: typeof embed.inspectNode === 'function', hasTriggerNodeAction: typeof embed.triggerNodeAction === 'function' };
                }"""
            )
            require(not node_probe.get("error"), "Unable to inspect node graph\n" + evidence(node_probe))
            require(node_probe.get("hasInspectNode") is True, "Diagnostic inspectNode bridge missing\n" + evidence(node_probe))
            require(node_probe.get("hasTriggerNodeAction") is True, "Diagnostic triggerNodeAction bridge missing\n" + evidence(node_probe))
            node = node_probe.get("node")
            require(isinstance(node, dict) and node.get("id"), "No non-center node available for action-card smoke\n" + evidence(node_probe))

            request_count_before_card = len(graph_requests)
            inspected = page.evaluate(
                """async (nodeId) => {
                    const iframe = document.querySelector('#g6-dynamic-surface iframe');
                    const embed = iframe && iframe.contentWindow ? iframe.contentWindow.__TEKG_G6_EMBED : null;
                    return embed.inspectNode(nodeId);
                }""",
                node["id"],
            )
            require(inspected is True, "inspectNode did not open node card\n" + evidence({"node": node, "inspected": inspected}))
            page.wait_for_timeout(600)
            after_node_click = page.evaluate("() => window.__TEKG_G6_BRIDGE.getState()")
            require(after_node_click.get("query") == query, "Node click/inspect changed center query\n" + evidence({"node": node, "state": after_node_click}))
            require(counts(after_node_click) == before_counts, "Node click/inspect changed graph element counts\n" + evidence({"before": before_counts, "after": counts(after_node_click)}))
            require(len(graph_requests) == request_count_before_card, "Node click/inspect issued graph request before explicit action\n" + evidence({"requests": graph_requests[request_count_before_card:]}))

            card = frame.locator(".inspect-card")
            card.wait_for(timeout=10000)
            card_state = card.evaluate(
                """el => ({
                    text: el.textContent || '',
                    buttons: [...el.querySelectorAll('button')].map((button) => button.textContent.trim()),
                })"""
            )
            for label in ["Jump", "Expand", "Details"]:
                require(label in card_state["buttons"], f"Node action card missing {label} button\n" + evidence(card_state))

            details_result = page.evaluate(
                """async (nodeId) => {
                    const iframe = document.querySelector('#g6-dynamic-surface iframe');
                    const embed = iframe && iframe.contentWindow ? iframe.contentWindow.__TEKG_G6_EMBED : null;
                    return embed.triggerNodeAction(nodeId, 'details');
                }""",
                node["id"],
            )
            require(details_result is True, "Details action failed\n" + evidence({"node": node, "result": details_result}))
            page.wait_for_timeout(500)
            details_state = page.evaluate("() => window.__TEKG_G6_BRIDGE.getState()")
            details_card = card.evaluate("el => ({ text: el.textContent || '', cls: el.className })")
            require("is-expanded" in details_card["cls"], "Details action did not expand node card\n" + evidence(details_card))
            require(details_state.get("query") == query, "Details action changed center query\n" + evidence(details_state))
            require(counts(details_state) == before_counts, "Details action changed graph elements\n" + evidence({"before": before_counts, "after": counts(details_state)}))

            expand_request_start = len(graph_requests)
            expanded = page.evaluate(
                """async (nodeId) => {
                    const iframe = document.querySelector('#g6-dynamic-surface iframe');
                    const embed = iframe && iframe.contentWindow ? iframe.contentWindow.__TEKG_G6_EMBED : null;
                    return embed.triggerNodeAction(nodeId, 'expand');
                }""",
                node["id"],
            )
            require(expanded is True, "Expand action failed\n" + evidence({"node": node, "result": expanded}))
            page.wait_for_function(
                """() => {
                    const loader = document.querySelector('#graph-preloader');
                    return loader && loader.getAttribute('aria-hidden') === 'true';
                }""",
                timeout=30000,
            )
            page.wait_for_timeout(1200)
            after_expand = page.evaluate("() => window.__TEKG_G6_BRIDGE.getState()")
            after_expand_counts = counts(after_expand)
            require(after_expand.get("query") == query, "Expand action changed center query\n" + evidence(after_expand))
            require(after_expand_counts["elements"] > before_counts["elements"], "Expand action did not add graph elements\n" + evidence({"before": before_counts, "after": after_expand_counts}))
            expand_requests = graph_requests[expand_request_start:]
            require(expand_requests, "Expand action did not issue graph API request")
            require(any("expand_node_id=" in item and "expand_node_type=" in item and "expand_query=" in item for item in expand_requests), "Expand action did not use same-label disambiguation params\n" + evidence({"requests": expand_requests}))

            jump_node_probe = page.evaluate(
                """async (currentNodeId) => {
                    const iframe = document.querySelector('#g6-dynamic-surface iframe');
                    const embed = iframe && iframe.contentWindow ? iframe.contentWindow.__TEKG_G6_EMBED : null;
                    const subgraph = await embed.getVisibleSubgraph();
                    const nodes = Array.isArray(subgraph && subgraph.nodes) ? subgraph.nodes : [];
                    const node = nodes.find((item) => item.id && item.id !== currentNodeId && item.type !== 'Paper' && (item.rawLabel || item.label));
                    return node || null;
                }""",
                node["id"],
            )
            require(isinstance(jump_node_probe, dict) and jump_node_probe.get("id"), "No jump node available after expand")
            jumped = page.evaluate(
                """async (nodeId) => {
                    const iframe = document.querySelector('#g6-dynamic-surface iframe');
                    const embed = iframe && iframe.contentWindow ? iframe.contentWindow.__TEKG_G6_EMBED : null;
                    await embed.inspectNode(nodeId);
                    return embed.triggerNodeAction(nodeId, 'jump');
                }""",
                jump_node_probe["id"],
            )
            require(jumped is True, "Jump action failed\n" + evidence({"node": jump_node_probe, "result": jumped}))
            page.wait_for_function(
                """() => {
                    const loader = document.querySelector('#graph-preloader');
                    return loader && loader.getAttribute('aria-hidden') === 'true';
                }""",
                timeout=45000,
            )
            page.wait_for_timeout(1000)
            after_jump = page.evaluate("() => window.__TEKG_G6_BRIDGE.getState()")
            expected_jump = str(jump_node_probe.get("rawLabel") or jump_node_probe.get("label") or "").strip()
            require(after_jump.get("query") == expected_jump, "Jump action did not change center query to target node\n" + evidence({"expected": expected_jump, "state": after_jump}))
            require(counts(after_jump)["elements"] > 0, "Jump action loaded empty graph\n" + evidence(after_jump))

            iframe_canvas = frame.locator("#container").evaluate(
                """el => ({ canvases: el.querySelectorAll('canvas').length, children: el.children.length })"""
            )
            loader = page.locator("#graph-preloader").evaluate(
                """el => ({ hidden: el.getAttribute('aria-hidden'), cls: el.className })"""
            )
            require(iframe_canvas["canvases"] > 0 or iframe_canvas["children"] > 0, "Node action card workflow blanked graph\n" + evidence(iframe_canvas))
            require(loader["hidden"] == "true" or "is-visible" not in str(loader["cls"]), "Loader stayed visible after node actions\n" + evidence(loader))
        except PlaywrightTimeoutError as exc:
            fail(f"G6 node action card UX smoke timed out: {exc}")
        except PlaywrightError as exc:
            fail(f"G6 node action card UX smoke failed: {exc}")
        finally:
            browser.close()

    require(not page_errors, "Page errors detected:\n" + "\n".join(page_errors[:10]))
    require(not [message for message in console_errors if "ReferenceError" in message], "ReferenceError detected:\n" + "\n".join(console_errors[:10]))
    require(not failed_requests, "Failed browser requests:\n" + "\n".join(failed_requests[:10]))
    ok(f"G6 node action card UX smoke passed for {url}")


if __name__ == "__main__":
    run_check(main)
