import json
from pathlib import Path


INPUT_FILE = Path("neo4j_graph_seed.json")
OUTPUT_FILE = Path("graph_demo_data.js")


def build_index(seed):
    index = {}
    for group, items in seed["nodes"].items():
        for item in items:
            index[item["name"]] = {
                "type": {
                    "transposons": "TE",
                    "diseases": "Disease",
                    "functions": "Function",
                    "papers": "Paper",
                }[group],
                "description": item.get("description", ""),
            }
    return index


def node_id(name):
    return (
        name.replace(" ", "_")
        .replace("-", "_")
        .replace("'", "")
        .replace("(", "")
        .replace(")", "")
        .replace("/", "_")
        .replace(":", "_")
        .replace(".", "_")
        .replace(",", "_")
        .replace("<", "")
        .replace(">", "")
        .replace('"', "")
    )


def main():
    seed = json.loads(INPUT_FILE.read_text(encoding="utf-8"))
    node_index = build_index(seed)

    selected_nodes = {}
    selected_edges = []
    qa = {
        "line1_functions": [],
        "line1_diseases": [],
        "line1_papers": [],
        "l1hs_papers": [],
    }

    def add_node(name):
        if name in selected_nodes:
            return
        info = node_index.get(name, {"type": "Unknown", "description": ""})
        selected_nodes[name] = {
            "data": {
                "id": node_id(name),
                "label": name,
                "type": info["type"],
                "description": info["description"],
            }
        }

    def add_edge(source, target, relation, evidence=None):
        selected_edges.append(
            {
                "data": {
                    "id": f"{node_id(source)}__{relation}__{node_id(target)}",
                    "source": node_id(source),
                    "target": node_id(target),
                    "relation": relation,
                    "evidence": ", ".join(evidence or []),
                }
            }
        )

    add_node("LINE-1")
    add_node("L1HS")

    for rel in seed["lineage_relations"]:
        if rel["target"] == "LINE-1":
            add_node(rel["source"])
            add_edge(rel["source"], rel["target"], rel["relation"], [str(rel["copies"])])

    line1_function_edges = []
    line1_disease_edges = []
    line1_paper_edges = []
    l1hs_paper_edges = []

    for rel in seed["relations"]:
        if rel["source"] == "LINE-1" and node_index.get(rel["target"], {}).get("type") == "Function":
            line1_function_edges.append(rel)
        if rel["source"] == "LINE-1" and node_index.get(rel["target"], {}).get("type") == "Disease":
            line1_disease_edges.append(rel)
        if rel["source"] in node_index and node_index[rel["source"]]["type"] == "Paper" and rel["target"] == "LINE-1":
            line1_paper_edges.append(rel)
        if rel["source"] in node_index and node_index[rel["source"]]["type"] == "Paper" and rel["target"] == "L1HS":
            l1hs_paper_edges.append(rel)

    for rel in line1_function_edges[:12]:
        add_node(rel["target"])
        add_edge(rel["source"], rel["target"], rel["relation"], rel.get("pmids", []))
        qa["line1_functions"].append({"name": rel["target"], "predicate": rel["relation"], "pmids": rel.get("pmids", [])})

    for rel in line1_disease_edges[:8]:
        add_node(rel["target"])
        add_edge(rel["source"], rel["target"], rel["relation"], rel.get("pmids", []))
        qa["line1_diseases"].append({"name": rel["target"], "predicate": rel["relation"], "pmids": rel.get("pmids", [])})

    for rel in line1_paper_edges[:8]:
        add_node(rel["source"])
        add_edge(rel["source"], rel["target"], rel["relation"], rel.get("pmids", []))
        qa["line1_papers"].append({"name": rel["source"], "pmids": rel.get("pmids", [])})

    for rel in l1hs_paper_edges[:5]:
        add_node(rel["source"])
        add_edge(rel["source"], rel["target"], rel["relation"], rel.get("pmids", []))
        qa["l1hs_papers"].append({"name": rel["source"], "pmids": rel.get("pmids", [])})

    payload = {
        "elements": list(selected_nodes.values()) + selected_edges,
        "qa": qa,
    }

    OUTPUT_FILE.write_text(
        "window.GRAPH_DEMO_DATA = " + json.dumps(payload, ensure_ascii=False, indent=2) + ";\n",
        encoding="utf-8",
    )
    print(f"Wrote: {OUTPUT_FILE}")


if __name__ == "__main__":
    main()
