import csv
import json
import re
from collections import Counter
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
TREE_FILE = ROOT / "transposon_tree" / "tree.txt"
SEED_FILE = ROOT / "data" / "processed" / "tekg2" / "tekg2_0413_seed.json"
PROCESSED_DIR = ROOT / "data" / "processed" / "tekg2"
IMPORTS_DIR = ROOT / "imports"

JSON_OUT = PROCESSED_DIR / "tekg2_0413_tree_lineage.json"
CSV_OUT = PROCESSED_DIR / "tekg2_0413_tree_lineage.csv"
MISSING_JSON = PROCESSED_DIR / "tekg2_0413_tree_missing_nodes.json"
REPORT_JSON = PROCESSED_DIR / "tekg2_0413_tree_lineage_report.json"
CYPHER_OUT = IMPORTS_DIR / "tekg2_0413_import_tree_te_lineage.cypher"

ROOT_LABEL = "Transposable Elements - Human"
META_PREFIXES = ("Order:", "Superfamily:", "Family:")


def norm(value: str) -> str:
    return " ".join(str(value or "").split()).strip()


def norm_key(value: str) -> str:
    return norm(value).casefold()


def count_depth(line: str) -> int:
    prefix = re.match(r"^[\u2502\u251c\u2514\u2500 ]*", line)
    raw = prefix.group(0) if prefix else ""
    return raw.count("\u2502")


def strip_prefix(line: str) -> str:
    return re.sub(r"^[\u2502\u251c\u2514\u2500 ]*", "", line).strip()


def choose_canonical_name(content: str) -> tuple[str, str]:
    content = norm(content)
    if content == ROOT_LABEL:
        return "TE", content

    if re.match(r"^Class\s+\S+:", content):
        return re.sub(r"^Class\s+\S+:\s*", "", content).strip(), content

    for prefix in META_PREFIXES:
        if content.startswith(prefix):
            return content[len(prefix) :].strip(), content

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
        or original_label.startswith(META_PREFIXES)
        or bool(re.match(r"^Class\s+\S+:", original_label))
        or original_label.startswith("Subclass ")
        or original_label.startswith("Subclade:")
        or original_label.startswith("Other ")
        or original_label in {"Internal Sequences", "LTR Sequences", "Non-HERV LTR"}
    )


def cypher_string(value: str) -> str:
    return json.dumps(value, ensure_ascii=False)


def load_te_names(seed_path: Path) -> dict[str, str]:
    seed = json.loads(seed_path.read_text(encoding="utf-8"))
    transposons = seed.get("nodes", {}).get("transposons", [])
    return {norm_key(item.get("name", "")): norm(item.get("name", "")) for item in transposons if norm(item.get("name", ""))}


def parse_tree_lines(tree_path: Path):
    nodes = []
    stack: dict[int, dict] = {}

    with tree_path.open("r", encoding="utf-8") as handle:
        for line_no, raw_line in enumerate(handle, start=1):
            line = raw_line.rstrip("\r\n")
            if not line.strip():
                continue
            depth = count_depth(line)
            content = strip_prefix(line)
            if not content:
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
                "parent_original_label": parent["original_label"] if parent else None,
            }
            nodes.append(node)
            stack[depth] = node
            for higher in [key for key in list(stack.keys()) if key > depth]:
                del stack[higher]
    return nodes


def main() -> None:
    PROCESSED_DIR.mkdir(parents=True, exist_ok=True)
    IMPORTS_DIR.mkdir(parents=True, exist_ok=True)

    te_name_map = load_te_names(SEED_FILE)
    parsed_nodes = parse_tree_lines(TREE_FILE)

    node_by_line = {node["line"]: node for node in parsed_nodes}
    matched_lines = set()
    missing_nodes = []
    meta_counter = Counter()

    for node in parsed_nodes:
        if node["is_meta"]:
            meta_counter[node["original_label"].split(":", 1)[0] if ":" in node["original_label"] else "root"] += 1
            continue
        key = norm_key(node["canonical_name"])
        if key in te_name_map:
            matched_lines.add(node["line"])
        else:
            missing_nodes.append(
                {
                    "line": node["line"],
                    "tree_name": node["canonical_name"],
                    "original_label": node["original_label"],
                    "depth": node["depth"],
                    "parent_canonical_name": node["parent_canonical_name"],
                    "parent_original_label": node["parent_original_label"],
                }
            )

    edges = {}
    for node in parsed_nodes:
        if node["line"] not in matched_lines:
            continue
        child_name = te_name_map[norm_key(node["canonical_name"])]
        current_parent = node
        while current_parent.get("parent_line") is not None:
            parent_line = current_parent["parent_line"]
            if parent_line is None:
                break
            parent_node = node_by_line[parent_line]
            if parent_node["line"] in matched_lines:
                parent_name = te_name_map[norm_key(parent_node["canonical_name"])]
                if child_name != parent_name:
                    edges[(child_name, parent_name)] = {
                        "child": child_name,
                        "parent": parent_name,
                        "child_tree_label": node["original_label"],
                        "parent_tree_label": parent_node["original_label"],
                    }
                break
            current_parent = parent_node

    lineage_payload = {
        "source_tree": str(TREE_FILE),
        "matched_te_count": len({edge["child"] for edge in edges.values()} | {edge["parent"] for edge in edges.values()}),
        "edge_count": len(edges),
        "edges": sorted(edges.values(), key=lambda item: (item["parent"].casefold(), item["child"].casefold())),
    }
    JSON_OUT.write_text(json.dumps(lineage_payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    with CSV_OUT.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.writer(handle)
        writer.writerow(["child", "parent", "relation", "child_tree_label", "parent_tree_label"])
        for edge in lineage_payload["edges"]:
            writer.writerow([edge["child"], edge["parent"], "SUBFAMILY_OF", edge["child_tree_label"], edge["parent_tree_label"]])

    missing_name_counter = Counter(item["tree_name"] for item in missing_nodes)
    missing_payload = {
        "source_tree": str(TREE_FILE),
        "missing_node_count": len(missing_nodes),
        "missing_unique_name_count": len(missing_name_counter),
        "missing_name_counts": [
            {"tree_name": name, "count": count}
            for name, count in sorted(missing_name_counter.items(), key=lambda item: (-item[1], item[0].casefold()))
        ],
        "missing_nodes": sorted(missing_nodes, key=lambda item: (item["tree_name"].casefold(), item["line"])),
    }
    MISSING_JSON.write_text(json.dumps(missing_payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    cypher_blocks = [
        f"// Generated from {TREE_FILE.name}. Only links TE nodes that already exist in the graph.",
        "// Missing tree nodes are reported separately and are intentionally not created here.",
        "",
        "UNWIND [",
    ]
    payload_rows = []
    for edge in lineage_payload["edges"]:
        payload_rows.append(
            "  {child: "
            + cypher_string(edge["child"])
            + ", parent: "
            + cypher_string(edge["parent"])
            + ", child_tree_label: "
            + cypher_string(edge["child_tree_label"])
            + ", parent_tree_label: "
            + cypher_string(edge["parent_tree_label"])
            + "}"
        )
    cypher_blocks.append(",\n".join(payload_rows))
    cypher_blocks.extend(
        [
            "] AS row",
            "MATCH (child:TE {name: row.child})",
            "MATCH (parent:TE {name: row.parent})",
            "MERGE (child)-[r:SUBFAMILY_OF]->(parent)",
            "SET r.source = 'tree_0413_reference',",
            "    r.tree_reference = true,",
            "    r.child_tree_label = row.child_tree_label,",
            "    r.parent_tree_label = row.parent_tree_label;",
            "",
        ]
    )
    CYPHER_OUT.write_text("\n".join(cypher_blocks), encoding="utf-8")

    report = {
        "source_files": {
            "tree_file": str(TREE_FILE.relative_to(ROOT)),
            "seed_file": str(SEED_FILE.relative_to(ROOT)),
        },
        "generated_files": {
            "lineage_json": str(JSON_OUT.relative_to(ROOT)),
            "lineage_csv": str(CSV_OUT.relative_to(ROOT)),
            "missing_json": str(MISSING_JSON.relative_to(ROOT)),
            "lineage_cypher": str(CYPHER_OUT.relative_to(ROOT)),
        },
        "counts": {
            "seed_te_count": len(te_name_map),
            "parsed_tree_lines": len(parsed_nodes),
            "matched_tree_node_lines": len(matched_lines),
            "missing_tree_node_lines": len(missing_nodes),
            "missing_tree_node_unique_names": len(missing_name_counter),
            "lineage_edges": len(edges),
        },
        "meta_node_summary": dict(meta_counter),
    }
    REPORT_JSON.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(report, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
