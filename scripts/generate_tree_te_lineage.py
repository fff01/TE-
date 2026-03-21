import json
import re
from pathlib import Path


TREE_FILE = Path("data/raw/tree.txt")
JSON_OUT = Path("data/processed/tree_te_lineage.json")
CSV_OUT = Path("data/processed/tree_te_lineage.csv")
CYPHER_OUT = Path("imports/import_tree_te_lineage.cypher")

ROOT_LABEL = "人类转座子"
ROOT_NAME = "TE"
SKIP_LABELS = {"DIRS-like"}


def count_depth(line: str) -> int:
    prefix = re.match(r"^[│├└─ ]*", line)
    raw = prefix.group(0) if prefix else ""
    return len(raw) // 4


def strip_prefix(line: str) -> str:
    return re.sub(r"^[│├└─ ]*", "", line).strip()


def is_ascii_token(text: str) -> bool:
    return bool(re.fullmatch(r"[A-Za-z0-9_.+\-/]+", text.strip()))


def is_subfamily_hint(content: str) -> bool:
    return bool(re.search(r"\(and subfamil(?:y|ies)\s+.+\)$", content, flags=re.I))


def choose_canonical_name(content: str) -> tuple[str, str]:
    content = content.strip()
    if content == ROOT_LABEL:
        return ROOT_NAME, content

    if is_subfamily_hint(content):
        main = re.sub(r"\s*\(and subfamil(?:y|ies)\s+.+\)$", "", content, flags=re.I).strip()
        return main, content

    alias_match = re.match(r"^(.*?)\s*\(([^()]*)\)\s*$", content)
    if alias_match:
        main = alias_match.group(1).strip()
        paren = alias_match.group(2).strip()
        subfamily_match = re.match(r"^([A-Za-z0-9_.+\-/]+)\s+subfamily$", paren, flags=re.I)
        if subfamily_match:
            return subfamily_match.group(1).strip(), content
        if is_ascii_token(paren) and len(paren) < len(main):
            return paren, content
        return main, content

    return content, content


def should_skip_node(name: str) -> bool:
    return name in SKIP_LABELS


def cypher_string(value: str) -> str:
    return json.dumps(value, ensure_ascii=False)


def build_description(name: str, original_label: str) -> str:
    if name == ROOT_NAME:
        return "TE root node imported from tree.txt lineage reference."
    return f"{name} lineage node imported from tree.txt. Original label: {original_label}"


def main() -> None:
    text = TREE_FILE.read_text(encoding="utf-8")
    lines = [line.rstrip("\r\n") for line in text.splitlines() if line.strip()]

    nodes: dict[str, dict] = {}
    edges: set[tuple[str, str]] = set()
    stack: dict[int, str] = {}

    for lineno, raw_line in enumerate(lines, start=1):
        depth = count_depth(raw_line)
        content = strip_prefix(raw_line)
        if not content:
            continue

        canonical_name, original_label = choose_canonical_name(content)
        if should_skip_node(canonical_name):
            continue

        if canonical_name not in nodes:
            nodes[canonical_name] = {
                "name": canonical_name,
                "original_label": original_label,
                "depth": depth,
                "line": lineno,
                "description": build_description(canonical_name, original_label),
            }

        if depth > 0 and (depth - 1) in stack:
            parent = stack[depth - 1]
            if not should_skip_node(parent) and canonical_name != parent:
                edges.add((canonical_name, parent))

        stack[depth] = canonical_name
        for higher in [key for key in list(stack.keys()) if key > depth]:
            del stack[higher]

    payload = {
        "root": ROOT_NAME,
        "root_label": ROOT_LABEL,
        "node_count": len(nodes),
        "edge_count": len(edges),
        "nodes": sorted(nodes.values(), key=lambda item: (item["depth"], item["name"].casefold())),
        "edges": [
            {"child": child, "parent": parent, "relation": "SUBFAMILY_OF"}
            for child, parent in sorted(edges, key=lambda item: (item[1].casefold(), item[0].casefold()))
        ],
    }

    JSON_OUT.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")

    csv_lines = ["child,parent,relation"]
    for child, parent in sorted(edges, key=lambda item: (item[1].casefold(), item[0].casefold())):
        csv_lines.append(f"{json.dumps(child, ensure_ascii=False)},{json.dumps(parent, ensure_ascii=False)},SUBFAMILY_OF")
    CSV_OUT.write_text("\n".join(csv_lines) + "\n", encoding="utf-8")

    cypher_blocks = [
        "// Generated from tree.txt. Imports full TE lineage tree into Neo4j.",
        "",
    ]
    for node in sorted(nodes.values(), key=lambda item: item["name"].casefold()):
        cypher_blocks.append(
            f"""MERGE (n:TE {{name: {cypher_string(node['name'])}}})
ON CREATE SET
  n.description = {cypher_string(node['description'])},
  n.tree_original_label = {cypher_string(node['original_label'])},
  n.tree_reference = true;"""
        )
    cypher_blocks.append("")
    for child, parent in sorted(edges, key=lambda item: (item[1].casefold(), item[0].casefold())):
        cypher_blocks.append(
            f"""MATCH (child:TE {{name: {cypher_string(child)}}})
MATCH (parent:TE {{name: {cypher_string(parent)}}})
MERGE (child)-[r:SUBFAMILY_OF]->(parent)
ON CREATE SET
  r.source = 'tree_reference',
  r.tree_reference = true;"""
        )

    CYPHER_OUT.write_text("\n\n".join(cypher_blocks) + "\n", encoding="utf-8")

    print(f"Wrote: {JSON_OUT}")
    print(f"Wrote: {CSV_OUT}")
    print(f"Wrote: {CYPHER_OUT}")
    print(f"Nodes: {len(nodes)}")
    print(f"Edges: {len(edges)}")


if __name__ == "__main__":
    main()
