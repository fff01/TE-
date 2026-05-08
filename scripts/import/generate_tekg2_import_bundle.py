import json
import sys
from pathlib import Path

LABEL_MAP = {
    "transposons": "TE",
    "diseases": "Disease",
    "functions": "Function",
    "genes": "Gene",
    "proteins": "Protein",
    "rnas": "RNA",
    "carbohydrates": "Carbohydrate",
    "lipids": "Lipid",
    "peptides": "Peptide",
    "pharmaceuticals": "Pharmaceutical",
    "toxins": "Toxin",
    "mutations": "Mutation",
    "papers": "Paper",
}

NODE_GROUP_ORDER = [
    "transposons",
    "diseases",
    "functions",
    "genes",
    "proteins",
    "rnas",
    "carbohydrates",
    "lipids",
    "peptides",
    "pharmaceuticals",
    "toxins",
    "mutations",
    "papers",
]


def cypher_string(value):
    return json.dumps(value, ensure_ascii=False)


def build_nodes_block(group_name: str, label: str, items: list[dict]) -> str:
    lines = [f"// Import {group_name}", "UNWIND ["]
    payload_lines = []
    if group_name == "papers":
        for item in items:
            payload_lines.append(
                "  {pmid: "
                + cypher_string(item.get("pmid", ""))
                + ", name: "
                + cypher_string(item.get("name", ""))
                + ", description: "
                + cypher_string(item.get("description", ""))
                + "}"
            )
        lines.append(",\n".join(payload_lines))
        lines.append(f"] AS row\nMERGE (n:{label} {{pmid: row.pmid}})")
        lines.append(
            "SET n.name = row.name, n.description = row.description, "
            + f"n.source_group = {cypher_string(group_name)};"
        )
    else:
        for item in items:
            payload = (
                "  {name: "
                + cypher_string(item.get("name", ""))
                + ", description: "
                + cypher_string(item.get("description", ""))
            )
            if group_name == "diseases":
                payload += ", disease_class: " + cypher_string(item.get("disease_class", ""))
            payload += "}"
            payload_lines.append(payload)
        lines.append(",\n".join(payload_lines))
        lines.append(f"] AS row\nMERGE (n:{label} {{name: row.name}})")
        lines.append("SET n.description = row.description, " + f"n.source_group = {cypher_string(group_name)}")
        if group_name == "diseases":
            lines[-1] += ", n.disease_class = row.disease_class;"
        else:
            lines[-1] += ";"
    lines.append("")
    return "\n".join(lines)


def build_relation_block(title: str, source_label: str, target_label: str, relations: list[dict]) -> str:
    lines = [f"// {title}", "UNWIND ["]
    payload_lines = []
    for rel in relations:
        payload_lines.append(
            "  {source: "
            + cypher_string(rel["source"])
            + ", predicate: "
            + cypher_string(rel["relation"])
            + ", target: "
            + cypher_string(rel["target"])
            + ", pmids: "
            + cypher_string(rel.get("pmids", []))
            + ", description: "
            + cypher_string(rel.get("description", ""))
            + "}"
        )
    lines.append(",\n".join(payload_lines))
    lines.append("] AS row")
    if source_label == "Paper":
        lines.append(f"MATCH (s:{source_label} {{pmid: row.source}})")
    else:
        lines.append(f"MATCH (s:{source_label} {{name: row.source}})")
    if target_label == "Paper":
        lines.append(f"MATCH (t:{target_label} {{pmid: row.target}})")
    else:
        lines.append(f"MATCH (t:{target_label} {{name: row.target}})")
    lines.append("MERGE (s)-[r:BIO_RELATION {predicate: row.predicate}]->(t)")
    lines.append("SET r.pmids = row.pmids, r.description = row.description, r.source_group = 'tekg2_core_relation';")
    lines.append("")
    return "\n".join(lines)


def main():
    if len(sys.argv) < 3:
        raise SystemExit("Usage: python generate_tekg2_import_bundle.py <seed.json> <output_prefix>")

    input_file = Path(sys.argv[1])
    prefix = Path(sys.argv[2])
    seed = json.loads(input_file.read_text(encoding="utf-8"))

    nodes_output = prefix.with_name(prefix.name + "_graph_nodes.cypher")
    core_output = prefix.with_name(prefix.name + "_core_relations.cypher")

    node_blocks = [
        f"// Generated from {input_file.name}",
        "// Import tekg2 entity nodes before importing relations.",
        "",
    ]
    for group_name in NODE_GROUP_ORDER:
        items = seed.get("nodes", {}).get(group_name, [])
        if group_name not in LABEL_MAP:
            continue
        node_blocks.append(build_nodes_block(group_name, LABEL_MAP[group_name], items))
    nodes_output.write_text("\n".join(node_blocks), encoding="utf-8")

    relation_groups = {}
    for rel in seed.get("relations", []):
        source_group = rel.get("source_group")
        target_group = rel.get("target_group")
        if source_group not in LABEL_MAP or target_group not in LABEL_MAP:
            continue
        relation_groups.setdefault((source_group, target_group), []).append(rel)

    core_blocks = [
        f"// Generated from {input_file.name}",
        "// Import tekg2 non-report core relations.",
        "// Relationship type is kept stable as BIO_RELATION; original verb is stored in r.predicate.",
        "",
    ]
    for (source_group, target_group), relations in sorted(relation_groups.items()):
        title = f"Import {LABEL_MAP[source_group]} -> {LABEL_MAP[target_group]} relations"
        core_blocks.append(build_relation_block(title, LABEL_MAP[source_group], LABEL_MAP[target_group], relations))
    core_output.write_text("\n".join(core_blocks), encoding="utf-8")

    print(f"Wrote: {nodes_output}")
    print(f"Wrote: {core_output}")
    print(f"Node groups: {len([g for g in NODE_GROUP_ORDER if seed.get('nodes', {}).get(g)])}")
    print(f"Relation groups: {len(relation_groups)}")


if __name__ == "__main__":
    main()
