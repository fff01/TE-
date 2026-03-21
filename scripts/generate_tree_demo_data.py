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

CURATED_TREE = {
    "TE": ["Retrotransposon", "DNA Transposon"],
    "Retrotransposon": ["Endogenous Retrovirus", "LINE", "SINE"],
    "DNA Transposon": ["hAT", "Mariner/Tc1", "piggyBac", "Merlin"],
    "Endogenous Retrovirus": ["ERV1", "ERV2", "ERV3"],
    "LINE": ["L1", "CR1"],
    "SINE": ["AmnSINE1_HS", "Alu", "SVA"],
}

POSITIONS = {
    "TE": {"x": 0, "y": 0},
    "Retrotransposon": {"x": -260, "y": 0},
    "DNA Transposon": {"x": 260, "y": 0},
    "Endogenous Retrovirus": {"x": -520, "y": -180},
    "LINE": {"x": -560, "y": 0},
    "SINE": {"x": -520, "y": 180},
    "hAT": {"x": 520, "y": -220},
    "Mariner/Tc1": {"x": 590, "y": -70},
    "piggyBac": {"x": 590, "y": 70},
    "Merlin": {"x": 520, "y": 220},
    "ERV1": {"x": -790, "y": -270},
    "ERV2": {"x": -850, "y": -160},
    "ERV3": {"x": -790, "y": -50},
    "L1": {"x": -840, "y": -45},
    "CR1": {"x": -840, "y": 45},
    "AmnSINE1_HS": {"x": -800, "y": 90},
    "Alu": {"x": -870, "y": 180},
    "SVA": {"x": -800, "y": 270},
}


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
    included_names = set(CURATED_TREE.keys())
    for children in CURATED_TREE.values():
        included_names.update(children)

    elements: list[dict] = []
    for name in sorted(included_names):
        node = nodes_by_name.get(name)
        if not node:
            raise RuntimeError(f"Required TE node '{name}' was not found in tree_te_lineage.json")
        elements.append(
            {
                "position": POSITIONS.get(name, {"x": 0, "y": 0}),
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

    for parent, children in CURATED_TREE.items():
        for child in children:
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
