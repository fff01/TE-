import json
import re
from datetime import datetime, timezone
from pathlib import Path

import requests


ROOT = Path(__file__).resolve().parents[1]
CONFIG_PATH = ROOT / "api" / "config.local.php"
SEED_PATH = ROOT / "data" / "processed" / "tekg2" / "tekg2_seed.json"
REPORT_PATH = ROOT / "data" / "processed" / "tekg2" / "tekg21_attention_db_import_report.json"

GROUP_TO_LABEL = {
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

OPERATIONS = [
    {"drop_label": "TE", "drop_name": "Tip100", "keep_group": "transposons", "keep_name": "hAT-Tip100"},
    {"drop_label": "TE", "drop_name": "HSAT5", "keep_group": "genes", "keep_name": "HSAT5"},
    {
        "drop_label": "TE",
        "drop_name": "human alphoid repetitive DNA",
        "keep_group": "genes",
        "keep_name": "human alphoid repetitive DNA",
    },
    {"drop_label": "TE", "drop_name": "hY pseudogene", "keep_group": "genes", "keep_name": "hY pseudogene"},
    {
        "drop_label": "TE",
        "drop_name": "LINE-1 retrotransposition",
        "keep_group": "functions",
        "keep_name": "LINE-1 retrotransposition",
    },
    {
        "drop_label": "Mutation",
        "drop_name": "LINE-1 retrotransposition",
        "keep_group": "functions",
        "keep_name": "LINE-1 retrotransposition",
    },
    {"drop_label": "TE", "drop_name": "LR", "keep_group": "transposons", "keep_name": "LTR"},
    {"drop_label": "TE", "drop_name": "LSau", "keep_group": "genes", "keep_name": "LSau"},
    {
        "drop_label": "TE",
        "drop_name": "MIR insertion in POLG",
        "keep_group": "mutations",
        "keep_name": "MIR insertion in POLG",
    },
    {"drop_label": "TE", "drop_name": "SVA-A", "keep_group": "transposons", "keep_name": "SVA_A"},
    {"drop_label": "TE", "drop_name": "SVA-B", "keep_group": "transposons", "keep_name": "SVA_B"},
    {"drop_label": "TE", "drop_name": "SVA-C", "keep_group": "transposons", "keep_name": "SVA_C"},
    {"drop_label": "TE", "drop_name": "SVA-D", "keep_group": "transposons", "keep_name": "SVA_D"},
    {"drop_label": "TE", "drop_name": "SVA-E", "keep_group": "transposons", "keep_name": "SVA_E"},
    {"drop_label": "TE", "drop_name": "SVA-F", "keep_group": "transposons", "keep_name": "SVA_F"},
    {"drop_label": "RNA", "drop_name": "SVA-F", "keep_group": "rnas", "keep_name": "SVA_F"},
    {
        "drop_label": "TE",
        "drop_name": "Repetitive sequence",
        "keep_group": "genes",
        "keep_name": "Repetitive sequence",
    },
    {
        "drop_label": "TE",
        "drop_name": "Reverse transcriptase",
        "keep_group": "proteins",
        "keep_name": "Reverse transcriptase",
    },
    {
        "drop_label": "TE",
        "drop_name": "SLMO2 retroduplication",
        "keep_group": "functions",
        "keep_name": "SLMO2 retroduplication",
    },
]

PRE_POST_NAMES = [
    "SVA-A",
    "SVA_A",
    "Tip100",
    "hAT-Tip100",
    "LR",
    "LTR",
    "HSAT5",
    "human alphoid repetitive DNA",
    "hY pseudogene",
    "LSau",
    "MIR insertion in POLG",
    "Repetitive sequence",
    "Reverse transcriptase",
    "SLMO2 retroduplication",
    "LINE-1 retrotransposition",
]


MERGE_STATEMENT_TEMPLATE = """
MERGE (keep:%KEEP_LABEL% {name: $keep_name})
ON CREATE SET keep.description = $keep_description, keep.source_group = $keep_group
SET keep.description = CASE WHEN coalesce(keep.description, '') = '' THEN $keep_description ELSE keep.description END,
    keep.source_group = CASE WHEN coalesce(keep.source_group, '') = '' THEN $keep_group ELSE keep.source_group END
WITH keep
MATCH (drop:%DROP_LABEL% {name: $drop_name})
WITH keep, drop
WHERE elementId(keep) <> elementId(drop)
CALL {
  WITH keep, drop
  MATCH (src)-[r:BIO_RELATION]->(drop)
  MERGE (src)-[r2:BIO_RELATION {predicate: r.predicate}]->(keep)
  SET r2.pmids = reduce(acc = coalesce(r2.pmids, []), x IN coalesce(r.pmids, []) | CASE WHEN x IN acc THEN acc ELSE acc + x END),
      r2.description = CASE WHEN coalesce(r2.description, '') = '' THEN coalesce(r.description, '') ELSE r2.description END,
      r2.source_group = coalesce(r2.source_group, r.source_group)
  DELETE r
}
CALL {
  WITH keep, drop
  MATCH (drop)-[r:BIO_RELATION]->(dst)
  MERGE (keep)-[r2:BIO_RELATION {predicate: r.predicate}]->(dst)
  SET r2.pmids = reduce(acc = coalesce(r2.pmids, []), x IN coalesce(r.pmids, []) | CASE WHEN x IN acc THEN acc ELSE acc + x END),
      r2.description = CASE WHEN coalesce(r2.description, '') = '' THEN coalesce(r.description, '') ELSE r2.description END,
      r2.source_group = coalesce(r2.source_group, r.source_group)
  DELETE r
}
CALL {
  WITH keep, drop
  MATCH (src)-[r:EVIDENCE_RELATION]->(drop)
  MERGE (src)-[r2:EVIDENCE_RELATION {predicate: r.predicate}]->(keep)
  SET r2.pmids = reduce(acc = coalesce(r2.pmids, []), x IN coalesce(r.pmids, []) | CASE WHEN x IN acc THEN acc ELSE acc + x END),
      r2.description = CASE WHEN coalesce(r2.description, '') = '' THEN coalesce(r.description, '') ELSE r2.description END,
      r2.source_group = coalesce(r2.source_group, r.source_group)
  DELETE r
}
CALL {
  WITH keep, drop
  MATCH (drop)-[r:EVIDENCE_RELATION]->(dst)
  MERGE (keep)-[r2:EVIDENCE_RELATION {predicate: r.predicate}]->(dst)
  SET r2.pmids = reduce(acc = coalesce(r2.pmids, []), x IN coalesce(r.pmids, []) | CASE WHEN x IN acc THEN acc ELSE acc + x END),
      r2.description = CASE WHEN coalesce(r2.description, '') = '' THEN coalesce(r.description, '') ELSE r2.description END,
      r2.source_group = coalesce(r2.source_group, r.source_group)
  DELETE r
}
CALL {
  WITH keep, drop
  MATCH (src)-[r:SUBFAMILY_OF]->(drop)
  MERGE (src)-[:SUBFAMILY_OF]->(keep)
  DELETE r
}
CALL {
  WITH keep, drop
  MATCH (drop)-[r:SUBFAMILY_OF]->(dst)
  MERGE (keep)-[:SUBFAMILY_OF]->(dst)
  DELETE r
}
SET keep.description = CASE WHEN coalesce(keep.description, '') = '' THEN coalesce(drop.description, '') ELSE keep.description END,
    keep.source_group = CASE WHEN coalesce(keep.source_group, '') = '' THEN coalesce(drop.source_group, '') ELSE keep.source_group END
DETACH DELETE drop
"""


def load_config() -> dict[str, str]:
    text = CONFIG_PATH.read_text(encoding="utf-8")
    config: dict[str, str] = {}
    for key in ("neo4j_url", "neo4j_user", "neo4j_password"):
        match = re.search(rf"'{key}'\s*=>\s*'([^']*)'", text)
        if match:
            config[key] = match.group(1)
    return config


def run_statement(config: dict[str, str], statement: str, parameters: dict | None = None) -> dict:
    payload = {"statements": [{"statement": statement, "parameters": parameters or {}, "resultDataContents": ["row"]}]}
    response = requests.post(
        config["neo4j_url"],
        auth=(config["neo4j_user"], config["neo4j_password"]),
        json=payload,
        timeout=180,
    )
    response.raise_for_status()
    data = response.json()
    if data.get("errors"):
        raise RuntimeError(json.dumps(data["errors"], ensure_ascii=False))
    return data


def load_seed_metadata() -> dict[tuple[str, str], dict]:
    seed = json.loads(SEED_PATH.read_text(encoding="utf-8"))
    metadata = {}
    for group, items in (seed.get("nodes") or {}).items():
        for item in items or []:
            metadata[(group, str(item.get("name", "")).strip())] = {
                "description": str(item.get("description", "") or ""),
                "source_group": group,
            }
    return metadata


def fetch_snapshot(config: dict[str, str]) -> list[dict]:
    statement = """
MATCH (n)
WHERE n.name IN $names
RETURN n.name AS name, labels(n) AS labels, count(*) AS count
ORDER BY name, labels(n)
"""
    data = run_statement(config, statement, {"names": PRE_POST_NAMES})
    rows = data["results"][0]["data"]
    return [
        {"name": row["row"][0], "labels": row["row"][1], "count": row["row"][2]}
        for row in rows
    ]


def apply_operations(config: dict[str, str], seed_metadata: dict[tuple[str, str], dict]) -> list[dict]:
    executed = []
    for item in OPERATIONS:
        keep_group = item["keep_group"]
        keep_name = item["keep_name"]
        meta = seed_metadata.get((keep_group, keep_name), {})
        keep_label = GROUP_TO_LABEL[keep_group]
        statement = (
            MERGE_STATEMENT_TEMPLATE.replace("%KEEP_LABEL%", keep_label).replace("%DROP_LABEL%", item["drop_label"])
        )
        params = {
            "drop_name": item["drop_name"],
            "keep_name": keep_name,
            "keep_description": meta.get("description", ""),
            "keep_group": meta.get("source_group", keep_group),
        }
        run_statement(config, statement, params)
        executed.append(
            {
                "drop_label": item["drop_label"],
                "drop_name": item["drop_name"],
                "keep_label": keep_label,
                "keep_name": keep_name,
            }
        )
    return executed


def main() -> None:
    config = load_config()
    seed_metadata = load_seed_metadata()
    before = fetch_snapshot(config)
    executed = apply_operations(config, seed_metadata)
    after = fetch_snapshot(config)

    report = {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "database": config.get("neo4j_url", ""),
        "before": before,
        "executed": executed,
        "after": after,
    }
    REPORT_PATH.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(report, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
