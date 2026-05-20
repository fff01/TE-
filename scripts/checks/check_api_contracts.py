from __future__ import annotations

from harness_lib import app_url, http_json, ok, require, run_check


def graph_elements_contract(payload: dict) -> None:
    elements = payload.get("elements")
    require(isinstance(elements, list) and len(elements) > 0, "api/graph.php returned empty or invalid elements")
    nodes = []
    edges = []
    for item in elements:
        data = item.get("data") if isinstance(item, dict) else None
        if not isinstance(data, dict):
            continue
        if data.get("source") and data.get("target"):
            edges.append(data)
        else:
            nodes.append(data)
    require(nodes, "api/graph.php elements contain no nodes")
    require(edges, "api/graph.php elements contain no edges")
    for node in nodes[:5]:
        require(node.get("id"), f"Graph node missing id: {node}")
        require(node.get("type"), f"Graph node missing type: {node}")
        require("label" in node or "rawLabel" in node, f"Graph node missing label/rawLabel: {node}")
    for edge in edges[:5]:
        require(edge.get("id"), f"Graph edge missing id: {edge}")
        require(edge.get("source"), f"Graph edge missing source: {edge}")
        require(edge.get("target"), f"Graph edge missing target: {edge}")
        require(edge.get("relationType") or edge.get("relation"), f"Graph edge missing relation metadata: {edge}")


def main() -> None:
    health = http_json(app_url("api/health.php"))
    require(health.get("ok") is True, "api/health.php ok must be true")
    require(health.get("neo4j_database") == "tekg3", f"api/health.php neo4j_database must be tekg3, got {health.get('neo4j_database')!r}")
    require(health.get("neo4j_reachable") is True, f"api/health.php neo4j_reachable must be true: {health.get('neo4j_message')}")
    ok("api/health.php contract passed")

    graph = http_json(app_url("api/graph.php?q=LINE1"))
    require(graph.get("ok") is True, f"api/graph.php?q=LINE1 ok must be true: {graph.get('error')}")
    require(isinstance(graph.get("anchor"), dict), "api/graph.php?q=LINE1 missing anchor object")
    graph_elements_contract(graph)
    ok("api/graph.php?q=LINE1 contract passed")

    tree = http_json(app_url("api/taxonomy.php?view=tree&source=rmsk_repbase"))
    require(tree.get("ok") is True, f"taxonomy file tree ok must be true: {tree.get('error')}")
    require(tree.get("root"), "taxonomy file tree missing root")
    require(isinstance(tree.get("nodes"), list) and tree["nodes"], "taxonomy file tree missing nodes")
    require(isinstance(tree.get("edges"), list), "taxonomy file tree missing edges list")
    ok("api/taxonomy.php file tree contract passed")

    items = http_json(app_url("api/taxonomy.php?names=L1HS,AluJb,SVA"))
    require(items.get("ok") is True, f"taxonomy items ok must be true: {items.get('error')}")
    require(items.get("source") == "tekg3", f"taxonomy items source must be tekg3, got {items.get('source')!r}")
    require(isinstance(items.get("items"), list) and items["items"], "taxonomy items missing items list")
    resolved = {str(item.get("name")) for item in items["items"] if isinstance(item, dict)}
    require(resolved, "taxonomy items did not resolve representative names")
    ok(f"api/taxonomy.php items contract passed: {', '.join(sorted(resolved))}")


if __name__ == "__main__":
    run_check(main)
