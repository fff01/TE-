import json
from pathlib import Path


INPUT_FILE = Path("neo4j_graph_seed.json")
OUTPUT_FILE = Path("import_paper_relations.cypher")

LABEL_MAP = {
    "transposons": "TE",
    "diseases": "Disease",
    "functions": "Function",
    "papers": "Paper",
}


def cypher_string(value):
    return json.dumps(value, ensure_ascii=False)


def build_name_to_label(seed):
    mapping = {}
    for group, items in seed["nodes"].items():
        label = LABEL_MAP[group]
        for item in items:
            mapping[item["name"]] = label
    return mapping


def build_block(title, target_label, relations):
    lines = [f"// {title}", "UNWIND ["]
    payload_lines = []
    for rel in relations:
        payload_lines.append(
            "  {paper: "
            + cypher_string(rel["source"])
            + ", predicate: "
            + cypher_string(rel["relation"])
            + ", target: "
            + cypher_string(rel["target"])
            + ", pmids: "
            + cypher_string(rel.get("pmids", []))
            + "}"
        )
    lines.append(",\n".join(payload_lines))
    lines.append("] AS row")
    lines.append("MATCH (p:Paper {name: row.paper})")
    lines.append(f"MATCH (t:{target_label} {{name: row.target}})")
    lines.append("MERGE (p)-[r:EVIDENCE_RELATION {predicate: row.predicate}]->(t)")
    lines.append("SET r.pmids = row.pmids, r.source_group = 'paper_relation';")
    lines.append("")
    return "\n".join(lines)


def main():
    seed = json.loads(INPUT_FILE.read_text(encoding="utf-8"))
    name_to_label = build_name_to_label(seed)

    paper_to_te = []
    paper_to_disease = []
    paper_to_function = []

    for rel in seed["relations"]:
        source_label = name_to_label.get(rel["source"])
        target_label = name_to_label.get(rel["target"])
        if source_label != "Paper":
            continue
        if target_label == "TE":
            paper_to_te.append(rel)
        elif target_label == "Disease":
            paper_to_disease.append(rel)
        elif target_label == "Function":
            paper_to_function.append(rel)

    blocks = [
        "// Generated from neo4j_graph_seed.json",
        "// Import evidence relations from Paper nodes to entity nodes.",
        "",
        build_block("Import Paper -> TE relations", "TE", paper_to_te),
        build_block("Import Paper -> Disease relations", "Disease", paper_to_disease),
        build_block("Import Paper -> Function relations", "Function", paper_to_function),
    ]

    OUTPUT_FILE.write_text("\n".join(blocks), encoding="utf-8")
    print(f"Wrote: {OUTPUT_FILE}")
    print(f"Paper->TE: {len(paper_to_te)}")
    print(f"Paper->Disease: {len(paper_to_disease)}")
    print(f"Paper->Function: {len(paper_to_function)}")


if __name__ == "__main__":
    main()
