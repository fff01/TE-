import json
import re
from pathlib import Path


TREE_JSON = Path("data/processed/tree_te_lineage.json")
DEMO_JS = Path("graph_demo_data.js")


DISPLAY_LABEL_OVERRIDES = {
    "TE": "人类转座子",
    "L1": "LINE1",
}

QUERY_LABEL_OVERRIDES = {
    "L1": "LINE1",
}

X_STEP = 250
Y_STEP = 34
LEFT_MARGIN = -560


def should_skip_tree_node(node: dict) -> bool:
    name = str(node.get("name", "")).strip()
    original_label = str(node.get("original_label", "")).strip()
    if not name:
        return True
    lowered_name = name.casefold()
    lowered_original = original_label.casefold()
    if "未在文件中发现" in original_label:
        return True
    if "not found" in lowered_original:
        return True
    if lowered_name in {"dirs-like"}:
        return True
    return False


def build_positions(children_by_parent: dict[str, list[str]], root: str = "TE") -> dict[str, dict[str, float]]:
    positions: dict[str, dict[str, float]] = {}
    next_leaf_index = 0

    def visit(name: str, depth: int) -> float:
        nonlocal next_leaf_index
        children = children_by_parent.get(name, [])
        x = LEFT_MARGIN + depth * X_STEP
        if not children:
            y = next_leaf_index * Y_STEP
            next_leaf_index += 1
            positions[name] = {"x": x, "y": y}
            return y
        child_ys = [visit(child, depth + 1) for child in children]
        y = sum(child_ys) / len(child_ys)
        positions[name] = {"x": x, "y": y}
        return y

    visit(root, 0)
    if positions:
        ys = [pos["y"] for pos in positions.values()]
        center = (min(ys) + max(ys)) / 2
        for pos in positions.values():
            pos["y"] -= center
    return positions


def make_id(prefix: str, value: str) -> str:
    safe = re.sub(r"[^A-Za-z0-9_]+", "_", value).strip("_")
    return f"{prefix}_{safe}" if safe else prefix


def load_existing_demo() -> dict:
    text = DEMO_JS.read_text(encoding="utf-8", errors="replace")
    start = text.find("=")
    end = text.rfind(";")
    if start == -1 or end == -1:
        raise RuntimeError("graph_demo_data.js is not in the expected format.")
    return json.loads(text[start + 1:end].strip())


def build_elements(tree_payload: dict) -> list[dict]:
    nodes_by_name = {node["name"]: node for node in tree_payload["nodes"]}
    included_names = {
        node["name"]
        for node in tree_payload["nodes"]
        if not should_skip_tree_node(node)
    }
    children_by_parent: dict[str, list[str]] = {}
    for edge in tree_payload["edges"]:
        child = edge["child"]
        parent = edge["parent"]
        if child not in included_names or parent not in included_names:
            continue
        children_by_parent.setdefault(parent, []).append(child)

    for parent, children in children_by_parent.items():
        children.sort(key=lambda child_name: nodes_by_name[child_name]["line"])

    positions = build_positions(children_by_parent)

    elements: list[dict] = []
    for name in sorted(included_names, key=lambda item: nodes_by_name[item]["line"]):
        node = nodes_by_name.get(name)
        if not node:
            raise RuntimeError(f"Required TE node '{name}' was not found in tree_te_lineage.json")
        elements.append(
            {
                "position": positions.get(name, {"x": 0, "y": 0}),
                "data": {
                    "id": make_id("TE", name),
                    "label": DISPLAY_LABEL_OVERRIDES.get(name, name),
                    "query_label": QUERY_LABEL_OVERRIDES.get(name, name),
                    "type": "TE",
                    "description": node["description"],
                    "tree_original_label": node["original_label"],
                    "tree_reference": True,
                    "tree_depth": node["depth"],
                },
            }
        )

    for edge in tree_payload["edges"]:
        child = edge["child"]
        parent = edge["parent"]
        if child not in included_names or parent not in included_names:
            continue
        elements.append(
            {
                "data": {
                    "id": make_id("SUB", f"{parent}__{child}"),
                    "source": make_id("TE", parent),
                    "target": make_id("TE", child),
                    "relation": "SUBFAMILY_OF",
                    "tree_reference": True,
                }
            }
        )
    return elements


def main() -> None:
    tree_payload = json.loads(TREE_JSON.read_text(encoding="utf-8"))
    demo_payload = load_existing_demo()
    demo_payload["elements"] = build_elements(tree_payload)
    output = "window.GRAPH_DEMO_DATA = " + json.dumps(demo_payload, ensure_ascii=False, indent=2) + ";\n"
    DEMO_JS.write_text(output, encoding="utf-8")
    print(f"Updated {DEMO_JS} with {len(demo_payload['elements'])} tree demo elements.")


if __name__ == "__main__":
    main()
