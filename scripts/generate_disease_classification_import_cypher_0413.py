import hashlib
import json
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
IMPORTS_DIR = ROOT / "imports"
PROCESSED_DIR = ROOT / "data" / "processed" / "tekg2"

MAPPING_JSON = PROCESSED_DIR / "disease_class_mapping_0413.json"

OUT_NODES = IMPORTS_DIR / "tekg2_0413_import_disease_category_nodes.cypher"
OUT_CATEGORY_EDGES = IMPORTS_DIR / "tekg2_0413_import_disease_category_edges.cypher"
OUT_DISEASE_EDGES = IMPORTS_DIR / "tekg2_0413_import_disease_to_category_edges.cypher"
OUT_REPORT = PROCESSED_DIR / "tekg2_0413_disease_classification_import_report.json"


def cypher_string(value):
    return json.dumps(value, ensure_ascii=False)


def make_id(prefix: str, parts: list[str]) -> str:
    key = " | ".join(parts)
    digest = hashlib.sha1(key.encode("utf-8")).hexdigest()[:12]
    return f"{prefix}_{digest}"


def render_unwind(rows: list[str]) -> str:
    return ",\n".join(rows)


def build_import_rows(mapping_payload: dict):
    category_nodes: dict[str, dict] = {}
    category_edges: dict[tuple[str, str], dict] = {}
    disease_edges: dict[tuple[str, str, str, int], dict] = {}

    mapping_rows = []
    mapping_rows.extend(mapping_payload.get("found_direct_mappings", []))
    mapping_rows.extend(mapping_payload.get("green_multiclass_mappings", []))

    for row in mapping_rows:
        disease_name = (row.get("target_jsonl_name") or "").strip()
        source_disease_name = (row.get("disease_name") or disease_name).strip()
        description = (row.get("suggested_jsonl_description") or row.get("workbook_description") or "").strip()
        mapping_type = (row.get("mapping_type") or "").strip()
        category_paths = row.get("category_paths") or []
        if not disease_name or not category_paths:
            continue

        for category_path in category_paths:
            path_parts_top_to_leaf = list(reversed([part.strip() for part in category_path if str(part).strip()]))
            if not path_parts_top_to_leaf:
                continue

            parent_id = ""
            for level, label in enumerate(path_parts_top_to_leaf, start=1):
                prefix_parts = path_parts_top_to_leaf[:level]
                node_id = make_id("dcat", prefix_parts)
                category_nodes[node_id] = {
                    "category_node_id": node_id,
                    "category_label": label,
                    "category_level": level,
                    "path_key": " | ".join(prefix_parts),
                    "top_category": path_parts_top_to_leaf[0],
                    "is_leaf": level == len(path_parts_top_to_leaf),
                }
                if parent_id:
                    category_edges[(parent_id, node_id)] = {
                        "parent_category_node_id": parent_id,
                        "child_category_node_id": node_id,
                    }
                parent_id = node_id

            leaf_id = parent_id
            leaf_label = path_parts_top_to_leaf[-1]
            disease_edges[(disease_name, leaf_id, mapping_type, len(path_parts_top_to_leaf))] = {
                "disease": disease_name,
                "source_disease_name": source_disease_name,
                "leaf_category_node_id": leaf_id,
                "leaf_category_label": leaf_label,
                "top_category": path_parts_top_to_leaf[0],
                "path_depth": len(path_parts_top_to_leaf),
                "mapping_type": mapping_type,
                "description": description,
            }

    return (
        sorted(category_nodes.values(), key=lambda r: (int(r["category_level"]), r["path_key"])),
        sorted(category_edges.values(), key=lambda r: (r["parent_category_node_id"], r["child_category_node_id"])),
        sorted(disease_edges.values(), key=lambda r: (r["disease"].casefold(), r["leaf_category_label"].casefold(), r["mapping_type"])),
    )


def build_category_nodes(rows: list[dict]) -> str:
    payload = []
    for row in rows:
        payload.append(
            "  {category_node_id: "
            + cypher_string(row["category_node_id"])
            + ", category_label: "
            + cypher_string(row["category_label"])
            + ", category_level: "
            + str(int(row["category_level"]))
            + ", path_key: "
            + cypher_string(row["path_key"])
            + ", top_category: "
            + cypher_string(row["top_category"])
            + ", is_leaf: "
            + ("true" if row["is_leaf"] else "false")
            + "}"
        )
    return "\n".join(
        [
            f"// Generated from {MAPPING_JSON.name}",
            "UNWIND [",
            render_unwind(payload),
            "] AS row",
            "MERGE (n:DiseaseCategory {category_node_id: row.category_node_id})",
            "SET n.name = row.category_label,",
            "    n.category_label = row.category_label,",
            "    n.category_level = row.category_level,",
            "    n.path_key = row.path_key,",
            "    n.top_category = row.top_category,",
            "    n.is_leaf = row.is_leaf,",
            "    n.source_group = \"tekg2_0413_disease_classification\";",
            "",
        ]
    )


def build_category_edges(rows: list[dict]) -> str:
    payload = []
    for row in rows:
        payload.append(
            "  {parent_category_node_id: "
            + cypher_string(row["parent_category_node_id"])
            + ", child_category_node_id: "
            + cypher_string(row["child_category_node_id"])
            + "}"
        )
    return "\n".join(
        [
            f"// Generated from {MAPPING_JSON.name}",
            "UNWIND [",
            render_unwind(payload),
            "] AS row",
            "MATCH (parent:DiseaseCategory {category_node_id: row.parent_category_node_id})",
            "MATCH (child:DiseaseCategory {category_node_id: row.child_category_node_id})",
            "MERGE (parent)-[r:HAS_SUBCATEGORY]->(child)",
            "SET r.source_group = \"tekg2_0413_disease_classification\";",
            "",
        ]
    )


def build_disease_edges(rows: list[dict]) -> str:
    payload = []
    for row in rows:
        payload.append(
            "  {disease: "
            + cypher_string(row["disease"])
            + ", source_disease_name: "
            + cypher_string(row["source_disease_name"])
            + ", leaf_category_node_id: "
            + cypher_string(row["leaf_category_node_id"])
            + ", leaf_category_label: "
            + cypher_string(row["leaf_category_label"])
            + ", top_category: "
            + cypher_string(row["top_category"])
            + ", path_depth: "
            + str(int(row["path_depth"]))
            + ", mapping_type: "
            + cypher_string(row["mapping_type"])
            + ", description: "
            + cypher_string(row["description"])
            + "}"
        )
    return "\n".join(
        [
            f"// Generated from {MAPPING_JSON.name}",
            "UNWIND [",
            render_unwind(payload),
            "] AS row",
            "MATCH (d:Disease {name: row.disease})",
            "MATCH (c:DiseaseCategory {category_node_id: row.leaf_category_node_id})",
            "MERGE (d)-[r:CLASSIFIED_AS]->(c)",
            "SET r.leaf_category_label = row.leaf_category_label,",
            "    r.top_category = row.top_category,",
            "    r.path_depth = row.path_depth,",
            "    r.mapping_type = row.mapping_type,",
            "    r.source_disease_name = row.source_disease_name,",
            "    r.source_group = \"tekg2_0413_disease_classification\";",
            "",
        ]
    )


def main() -> None:
    IMPORTS_DIR.mkdir(parents=True, exist_ok=True)
    mapping_payload = json.loads(MAPPING_JSON.read_text(encoding="utf-8"))
    category_nodes, category_edges, disease_edges = build_import_rows(mapping_payload)

    OUT_NODES.write_text(build_category_nodes(category_nodes), encoding="utf-8")
    OUT_CATEGORY_EDGES.write_text(build_category_edges(category_edges), encoding="utf-8")
    OUT_DISEASE_EDGES.write_text(build_disease_edges(disease_edges), encoding="utf-8")

    report = {
        "source_files": {
            "mapping_json": str(MAPPING_JSON.relative_to(ROOT)),
        },
        "generated_files": {
            "category_nodes_cypher": str(OUT_NODES.relative_to(ROOT)),
            "category_edges_cypher": str(OUT_CATEGORY_EDGES.relative_to(ROOT)),
            "disease_edges_cypher": str(OUT_DISEASE_EDGES.relative_to(ROOT)),
        },
        "counts": {
            "found_direct_mappings": len(mapping_payload.get("found_direct_mappings", [])),
            "green_multiclass_mappings": len(mapping_payload.get("green_multiclass_mappings", [])),
            "category_nodes_payload": len(category_nodes),
            "category_edges_payload": len(category_edges),
            "disease_edges_payload": len(disease_edges),
        },
    }
    OUT_REPORT.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(report, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
