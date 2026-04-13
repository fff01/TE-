import json
from datetime import datetime, timezone
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
INPUT_JSONL = ROOT / "data" / "processed" / "tekg2" / "tekg2_0413_clean.jsonl"
OUTPUT_JSONL = ROOT / "data" / "processed" / "tekg2" / "tekg2_0413_unresolved_fixed.jsonl"
OUTPUT_REPORT = ROOT / "data" / "processed" / "tekg2" / "tekg2_0413_unresolved_fixed_report.json"


def ensure_named_entity(record: dict, bucket: str, name: str, description: str) -> bool:
    entities = (record.setdefault("entities", {})).setdefault(bucket, [])
    for entity in entities:
        if (entity.get("name") or "").strip() == name:
            if not (entity.get("description") or "").strip():
                entity["description"] = description
                return True
            return False
    entities.append({"name": name, "description": description})
    return True


def main():
    updated_records = 0
    added_entities = 0
    removed_relations = 0
    action_log = []
    lines_out = []

    with INPUT_JSONL.open("r", encoding="utf-8") as handle:
        for line in handle:
            raw = line.strip()
            if not raw:
                continue
            record = json.loads(raw)
            pmid = str(record.get("pmid") or "")
            touched = False

            if pmid == "18952144":
                if ensure_named_entity(
                    record,
                    "functions",
                    "SVA insertion",
                    "SVA insertion refers to the insertion event of an SVA retrotransposon into a genomic locus.",
                ):
                    added_entities += 1
                    touched = True
                    action_log.append({"pmid": pmid, "action": "added_function", "name": "SVA insertion"})

            if pmid == "24062987":
                if ensure_named_entity(
                    record,
                    "genes",
                    "chromosomal DNA",
                    "Chromosomal DNA refers to genomic DNA packaged within chromosomes and used here as a gene-class placeholder for graph import alignment.",
                ):
                    added_entities += 1
                    touched = True
                    action_log.append({"pmid": pmid, "action": "added_gene", "name": "chromosomal DNA"})

            if pmid == "41952195":
                relations = record.get("relations") or []
                kept_relations = []
                for relation in relations:
                    if (
                        (relation.get("source") or "").strip() == "Sox2-binding TE"
                        and (relation.get("relation") or "").strip() == "involve in"
                        and (relation.get("target") or "").strip() == "Neuronal development"
                    ):
                        removed_relations += 1
                        touched = True
                        action_log.append(
                            {
                                "pmid": pmid,
                                "action": "removed_relation",
                                "relation": {
                                    "source": "Sox2-binding TE",
                                    "relation": "involve in",
                                    "target": "Neuronal development",
                                },
                            }
                        )
                        continue
                    kept_relations.append(relation)
                record["relations"] = kept_relations

            if pmid == "19294374":
                if ensure_named_entity(
                    record,
                    "proteins",
                    "Major Histocompatibility Complex",
                    "Major Histocompatibility Complex is a protein complex region central to antigen presentation and immune recognition.",
                ):
                    added_entities += 1
                    touched = True
                    action_log.append(
                        {"pmid": pmid, "action": "added_protein", "name": "Major Histocompatibility Complex"}
                    )

            if touched:
                updated_records += 1
            lines_out.append(json.dumps(record, ensure_ascii=False))

    OUTPUT_JSONL.write_text("\n".join(lines_out) + "\n", encoding="utf-8")
    report = {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "input_file": str(INPUT_JSONL),
        "output_file": str(OUTPUT_JSONL),
        "updated_records": updated_records,
        "added_entities": added_entities,
        "removed_relations": removed_relations,
        "actions": action_log,
    }
    OUTPUT_REPORT.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(report, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
