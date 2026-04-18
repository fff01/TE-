import json
import re
from pathlib import Path


SCRIPT_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = SCRIPT_DIR.parent
SEED_FILE = PROJECT_ROOT / "data" / "processed" / "tekg2" / "tekg2_0413_seed.json"
DEMO_JS = PROJECT_ROOT / "assets" / "data" / "graph_demo_data.js"

TREE_SPECS = {
    "rmsk_repbase": {
        "tree_file": PROJECT_ROOT / "transposon_tree" / "tree_rmsk_repbase_4.18.txt",
        "label": "RMSK + Repbase Tree",
        "summary": "A cleaner canonical tree built from RMSK and Repbase names only.",
    },
    "all": {
        "tree_file": PROJECT_ROOT / "transposon_tree" / "tree_all_4.18_2.txt",
        "label": "All TE Tree",
        "summary": "An expanded tree that also includes database-only and less standardized TE names.",
    },
}

ROOT_LABEL = "TE - Human (Mobile element)"
LEGACY_ROOT_LABELS = {
    "Transposable Elements - Human",
    "Transposable Elements (Mobile element) - Human",
    "Mobile genetic element",
}
FLATTEN_ROOT_CHILD_LABELS = {
    "Transposable Elements (Mobile element) - Human",
}
ROOT_BUCKET_LABEL = "others"
ROOT_PRIMARY_LABELS = {
    ROOT_BUCKET_LABEL,
    "Class I: Retrotransposons",
    "Class II: DNA Transposons",
}
META_PREFIXES = ("Order:", "Superfamily:", "Family:")
DISPLAY_LABEL_OVERRIDES = {
    "TE": ROOT_LABEL,
    "L1": "LINE1",
    "Retrotransposons": "Class I: Retrotransposons",
    "DNA Transposons": "Class II: DNA Transposons",
}
QUERY_LABEL_OVERRIDES = {
    "L1": "LINE1",
}
X_STEP = 250
Y_STEP = 34
LEFT_MARGIN = -560


def norm(value: str) -> str:
    return " ".join(str(value or "").split()).strip()


def norm_key(value: str) -> str:
    return norm(value).casefold()


def count_depth(line: str) -> int:
    depth = 0
    index = 0
    while True:
        if line.startswith("\u2502   ", index) or line.startswith("    ", index):
            depth += 1
            index += 4
            continue
        if line.startswith("\u251c\u2500\u2500 ", index) or line.startswith("\u2514\u2500\u2500 ", index):
            depth += 1
            index += 4
        break
    return depth


def strip_prefix(line: str) -> str:
    index = 0
    while True:
        if line.startswith("\u2502   ", index) or line.startswith("    ", index):
            index += 4
            continue
        if line.startswith("\u251c\u2500\u2500 ", index) or line.startswith("\u2514\u2500\u2500 ", index):
            index += 4
        break
    return line[index:].strip()


def choose_canonical_name(content: str) -> tuple[str, str]:
    content = norm(content)
    if content == ROOT_LABEL or content in LEGACY_ROOT_LABELS:
        return "TE", content

    if re.match(r"^Class\s+\S+:", content):
        return re.sub(r"^Class\s+\S+:\s*", "", content).strip(), content

    for prefix in META_PREFIXES:
        if content.startswith(prefix):
            return content[len(prefix):].strip(), content

    alias_match = re.match(r"^(.*?)\s*\(([^()]*)\)\s*$", content)
    if alias_match:
        main = alias_match.group(1).strip()
        paren = alias_match.group(2).strip()
        if paren and re.fullmatch(r"[A-Za-z0-9_.+\-/ ]+", paren) and len(paren) < len(main):
            return paren, content
        return main, content

    return content, content


def is_meta_node(original_label: str) -> bool:
    original_label = norm(original_label)
    return (
        original_label == ROOT_LABEL
        or original_label in LEGACY_ROOT_LABELS
        or original_label.startswith(META_PREFIXES)
        or bool(re.match(r"^Class\s+\S+:", original_label))
        or original_label.startswith("Subclass ")
        or original_label.startswith("Subclade:")
        or original_label.startswith("Other ")
        or original_label in {"Internal Sequences", "LTR Sequences", "Non-HERV LTR"}
    )


def load_te_names(seed_path: Path) -> dict[str, str]:
    seed = json.loads(seed_path.read_text(encoding="utf-8"))
    transposons = seed.get("nodes", {}).get("transposons", [])
    return {norm_key(item.get("name", "")): norm(item.get("name", "")) for item in transposons if norm(item.get("name", ""))}


def parse_tree_lines(tree_path: Path):
    nodes = []
    stack: dict[int, dict] = {}

    raw_text = None
    for encoding in ("utf-8", "utf-8-sig", "gb18030"):
        try:
            candidate = tree_path.read_text(encoding=encoding)
        except UnicodeDecodeError:
            continue
        if "鈹" in candidate and encoding != "gb18030":
            continue
        raw_text = candidate
        break
    if raw_text is None:
        raw_text = tree_path.read_text(encoding="utf-8", errors="replace")

    for line_no, raw_line in enumerate(raw_text.splitlines(), start=1):
            line = raw_line.rstrip("\r\n")
            if not line.strip():
                continue
            depth = count_depth(line)
            content = strip_prefix(line)
            content = re.sub(r"^[\u2502\u251c\u2514\u2500]+", "", content).strip()
            if not content or re.fullmatch(r"[\u2502\u251c\u2514\u2500]+", content):
                continue

            canonical_name, original_label = choose_canonical_name(content)
            parent = stack.get(depth - 1)
            node = {
                "line": line_no,
                "depth": depth,
                "canonical_name": canonical_name,
                "original_label": original_label,
                "is_meta": is_meta_node(original_label),
                "parent_line": parent["line"] if parent else None,
                "parent_canonical_name": parent["canonical_name"] if parent else None,
            }
            nodes.append(node)
            stack[depth] = node
            for higher in [key for key in list(stack.keys()) if key > depth]:
                del stack[higher]

    root_line = None
    for node in nodes:
        if node["depth"] == 0:
            root_line = node["line"]
            break
    if root_line is not None:
        for node in nodes:
            if node["parent_line"] is None:
                continue
            if node["parent_line"] == root_line and norm(node["original_label"]) in FLATTEN_ROOT_CHILD_LABELS:
                flatten_line = node["line"]
                for child in nodes:
                    if child["parent_line"] == flatten_line:
                        child["parent_line"] = root_line
                        child["parent_canonical_name"] = "TE"
        root_bucket_line = next(
            (
                node["line"]
                for node in nodes
                if node["parent_line"] == root_line and norm(node["original_label"]) == ROOT_BUCKET_LABEL
            ),
            None,
        )
        if root_bucket_line is not None:
            for node in nodes:
                if node["parent_line"] != root_line:
                    continue
                label = norm(node["original_label"])
                if label in ROOT_PRIMARY_LABELS or label in FLATTEN_ROOT_CHILD_LABELS:
                    continue
                node["parent_line"] = root_bucket_line
                node["parent_canonical_name"] = ROOT_BUCKET_LABEL
    return nodes


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


def build_tree_variant_payload(tree_path: Path, te_name_map: dict[str, str], label: str, summary: str) -> dict:
    parsed_nodes = parse_tree_lines(tree_path)
    node_by_line = {node["line"]: node for node in parsed_nodes}
    root_line = next((node["line"] for node in parsed_nodes if node["depth"] == 0), None)

    included_lines = {node["line"] for node in parsed_nodes}
    node_records = {}
    children_by_parent: dict[str, list[str]] = {}
    root_id = ""

    skip_lines = {
        node["line"]
        for node in parsed_nodes
        if node["parent_line"] == root_line and norm(node["original_label"]) in FLATTEN_ROOT_CHILD_LABELS
    }

    for node in parsed_nodes:
        line_no = node["line"]
        if line_no in skip_lines:
            continue
        canonical_name = norm(node["canonical_name"])
        original_label = norm(node["original_label"])
        is_root = node["depth"] == 0
        if is_root:
            stable_name = "TE"
            display_label = ROOT_LABEL
            query_label = ""
            description = f"Tree root imported from {tree_path.name}."
            root_id = make_id("TREE", f"{tree_path.stem}_{line_no}_{stable_name}")
        elif node["is_meta"]:
            stable_name = canonical_name or original_label or f"line_{line_no}"
            display_label = DISPLAY_LABEL_OVERRIDES.get(stable_name, stable_name)
            query_label = ""
            description = f"Tree category node imported from {tree_path.name}. Original label: {original_label}"
        else:
            matched_name = te_name_map.get(norm_key(canonical_name))
            stable_name = matched_name or canonical_name or original_label or f"line_{line_no}"
            display_label = DISPLAY_LABEL_OVERRIDES.get(stable_name, stable_name)
            query_label = QUERY_LABEL_OVERRIDES.get(stable_name, stable_name) if matched_name else ""
            description = (
                f"{stable_name} lineage node imported from {tree_path.name}. Original label: {original_label}"
                if matched_name
                else f"Unmatched tree node from {tree_path.name}. Original label: {original_label}"
            )

        node_id = make_id("TREE", f"{tree_path.stem}_{line_no}_{stable_name}")
        node_records[line_no] = {
            "id": node_id,
            "line": line_no,
            "name": stable_name,
            "display_label": display_label,
            "query_label": query_label,
            "description": description,
            "original_label": original_label,
            "depth": node["depth"],
            "matched": bool(query_label),
            "is_meta": bool(node["is_meta"]),
        }

    edge_records = []
    for node in parsed_nodes:
        if node["parent_line"] is None:
            continue
        if node["line"] in skip_lines or node["parent_line"] in skip_lines:
            continue
        if node["line"] not in included_lines or node["parent_line"] not in included_lines:
            continue
        child = node_records[node["line"]]
        parent = node_records[node["parent_line"]]
        children_by_parent.setdefault(parent["id"], []).append(child["id"])
        edge_records.append({"child": child["id"], "parent": parent["id"]})

    for parent_id, child_ids in children_by_parent.items():
        child_ids.sort(key=lambda child_id: next(record["line"] for record in node_records.values() if record["id"] == child_id))

    positions = build_positions(children_by_parent, root=root_id or next(iter(node_records.values()))["id"])

    elements = []
    for record in sorted(node_records.values(), key=lambda item: item["line"]):
        elements.append(
            {
                "position": positions.get(record["id"], {"x": 0, "y": 0}),
                "data": {
                    "id": record["id"],
                    "label": record["display_label"],
                    "query_label": record["query_label"],
                    "type": "TE",
                    "description": record["description"],
                    "tree_original_label": record["original_label"],
                    "tree_reference": True,
                    "tree_depth": record["depth"],
                    "tree_matched": record["matched"],
                    "tree_is_meta": record["is_meta"],
                },
            }
        )

    for edge in edge_records:
        elements.append(
            {
                "data": {
                    "id": make_id("SUB", f"{edge['parent']}__{edge['child']}"),
                    "source": edge["parent"],
                    "target": edge["child"],
                    "relation": "SUBFAMILY_OF",
                    "tree_reference": True,
                }
            }
        )

    matched_node_count = sum(1 for record in node_records.values() if record["matched"])
    return {
        "label": label,
        "summary": summary,
        "source_tree": str(tree_path.relative_to(PROJECT_ROOT)),
        "counts": {
            "matched_nodes": matched_node_count,
            "total_tree_nodes": len(node_records),
            "lineage_edges": len(edge_records),
        },
        "elements": elements,
    }


def main() -> None:
    demo_payload = load_existing_demo()
    te_name_map = load_te_names(SEED_FILE)

    tree_variants = {}
    for tree_key, spec in TREE_SPECS.items():
        tree_variants[tree_key] = build_tree_variant_payload(
            spec["tree_file"],
            te_name_map,
            spec["label"],
            spec["summary"],
        )

    demo_payload["tree_variants"] = tree_variants
    demo_payload["tree_default_variant"] = "rmsk_repbase"
    demo_payload["elements"] = tree_variants["rmsk_repbase"]["elements"]

    output = "window.GRAPH_DEMO_DATA = " + json.dumps(demo_payload, ensure_ascii=False, indent=2) + ";\n"
    DEMO_JS.write_text(output, encoding="utf-8")
    print(f"Updated {DEMO_JS} with {len(tree_variants)} tree variants.")


if __name__ == "__main__":
    main()
