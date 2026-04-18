import csv
import json
import re
from collections import Counter
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
SEED_FILE = ROOT / "data" / "processed" / "tekg2" / "tekg2_0413_seed.json"
PROCESSED_DIR = ROOT / "data" / "processed" / "tekg2"
IMPORTS_DIR = ROOT / "imports"

ROOT_LABEL = "Transposable Elements - Human"
META_PREFIXES = ("Order:", "Superfamily:", "Family:")

TREE_SPECS = {
    "rmsk_repbase": {
        "tree_file": ROOT / "transposon_tree" / "tree_rmsk_repbase_4.18.txt",
        "stem": "tekg2_0413_tree_rmsk_repbase",
        "legacy_default": True,
    },
    "all": {
        "tree_file": ROOT / "transposon_tree" / "tree_all_4.18.txt",
        "stem": "tekg2_0413_tree_all",
        "legacy_default": False,
    },
}


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
                "parent_original_label": parent["original_label"] if parent else None,
            }
            nodes.append(node)
            stack[depth] = node
            for higher in [key for key in list(stack.keys()) if key > depth]:
                del stack[higher]
    return nodes


def build_payloads(tree_path: Path, te_name_map: dict[str, str]) -> tuple[dict, dict, dict]:
    parsed_nodes = parse_tree_lines(tree_path)
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
        "source_tree": str(tree_path),
        "matched_te_count": len({edge["child"] for edge in edges.values()} | {edge["parent"] for edge in edges.values()}),
        "edge_count": len(edges),
        "edges": sorted(edges.values(), key=lambda item: (item["parent"].casefold(), item["child"].casefold())),
    }

    missing_name_counter = Counter(item["tree_name"] for item in missing_nodes)
    missing_payload = {
        "source_tree": str(tree_path),
        "missing_node_count": len(missing_nodes),
        "missing_unique_name_count": len(missing_name_counter),
        "missing_name_counts": [
            {"tree_name": name, "count": count}
            for name, count in sorted(missing_name_counter.items(), key=lambda item: (-item[1], item[0].casefold()))
        ],
        "missing_nodes": sorted(missing_nodes, key=lambda item: (item["tree_name"].casefold(), item["line"])),
    }

    report = {
        "source_files": {
            "tree_file": str(tree_path.relative_to(ROOT)),
            "seed_file": str(SEED_FILE.relative_to(ROOT)),
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
    return lineage_payload, missing_payload, report


def write_outputs(tree_key: str, spec: dict, lineage_payload: dict, missing_payload: dict, report: dict) -> dict:
    stem = spec["stem"]
    json_out = PROCESSED_DIR / f"{stem}_lineage.json"
    csv_out = PROCESSED_DIR / f"{stem}_lineage.csv"
    missing_json = PROCESSED_DIR / f"{stem}_missing_nodes.json"
    report_json = PROCESSED_DIR / f"{stem}_lineage_report.json"
    cypher_out = IMPORTS_DIR / f"import_{stem}_lineage.cypher"

    json_out.write_text(json.dumps(lineage_payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    with csv_out.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.writer(handle)
        writer.writerow(["child", "parent", "relation", "child_tree_label", "parent_tree_label"])
        for edge in lineage_payload["edges"]:
            writer.writerow([edge["child"], edge["parent"], "SUBFAMILY_OF", edge["child_tree_label"], edge["parent_tree_label"]])

    missing_json.write_text(json.dumps(missing_payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    cypher_blocks = [
        f"// Generated from {Path(lineage_payload['source_tree']).name}. Only links TE nodes that already exist in the graph.",
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
            f"MERGE (child)-[r:SUBFAMILY_OF {{tree_variant: '{tree_key}'}}]->(parent)",
            f"SET r.source = 'tree_0413_{tree_key}',",
            "    r.tree_reference = true,",
            "    r.child_tree_label = row.child_tree_label,",
            "    r.parent_tree_label = row.parent_tree_label;",
            "",
        ]
    )
    cypher_out.write_text("\n".join(cypher_blocks), encoding="utf-8")

    report["generated_files"] = {
        "lineage_json": str(json_out.relative_to(ROOT)),
        "lineage_csv": str(csv_out.relative_to(ROOT)),
        "missing_json": str(missing_json.relative_to(ROOT)),
        "lineage_cypher": str(cypher_out.relative_to(ROOT)),
    }
    report_json.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    # Preserve the old default output names for the clean canonical tree.
    if spec.get("legacy_default"):
        legacy_map = {
            PROCESSED_DIR / "tekg2_0413_tree_lineage.json": json_out,
            PROCESSED_DIR / "tekg2_0413_tree_lineage.csv": csv_out,
            PROCESSED_DIR / "tekg2_0413_tree_missing_nodes.json": missing_json,
            PROCESSED_DIR / "tekg2_0413_tree_lineage_report.json": report_json,
            IMPORTS_DIR / "tekg2_0413_import_tree_te_lineage.cypher": cypher_out,
        }
        for legacy_path, source_path in legacy_map.items():
            legacy_path.write_text(source_path.read_text(encoding="utf-8"), encoding="utf-8")

    return {
        "tree_key": tree_key,
        "tree_file": str(spec["tree_file"].relative_to(ROOT)),
        "lineage_json": str(json_out.relative_to(ROOT)),
        "lineage_csv": str(csv_out.relative_to(ROOT)),
        "missing_json": str(missing_json.relative_to(ROOT)),
        "report_json": str(report_json.relative_to(ROOT)),
        "lineage_cypher": str(cypher_out.relative_to(ROOT)),
        "counts": report["counts"],
    }


def main() -> None:
    PROCESSED_DIR.mkdir(parents=True, exist_ok=True)
    IMPORTS_DIR.mkdir(parents=True, exist_ok=True)

    te_name_map = load_te_names(SEED_FILE)
    manifest = {
        "seed_file": str(SEED_FILE.relative_to(ROOT)),
        "tree_variants": [],
    }

    for tree_key, spec in TREE_SPECS.items():
        lineage_payload, missing_payload, report = build_payloads(spec["tree_file"], te_name_map)
        manifest["tree_variants"].append(write_outputs(tree_key, spec, lineage_payload, missing_payload, report))

    manifest_path = PROCESSED_DIR / "tekg2_0413_tree_lineage_manifest.json"
    manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(manifest, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
