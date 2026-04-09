import csv
import json
from collections import Counter
from pathlib import Path


ROOT = Path(__file__).resolve().parent.parent
INPUT_FILE = ROOT / "te_kg2_final_standardized_new.jsonl"
OUTPUT_DIR = ROOT / "data" / "processed" / "tekg2"
OUTPUT_JSONL = OUTPUT_DIR / "tekg2_unresolved_relations.jsonl"
OUTPUT_CSV = OUTPUT_DIR / "tekg2_unresolved_relations.csv"
OUTPUT_SUMMARY = OUTPUT_DIR / "tekg2_unresolved_relations_summary.json"

ENTITY_BUCKET_MAP = {
    "transposons": "transposons",
    "diseases": "diseases",
    "functions": "functions",
    "genes": "genes",
    "proteins": "proteins",
    "rnas": "rnas",
    "carbohydrates": "carbohydrates",
    "lipids": "lipids",
    "peptides": "peptides",
    "pharmaceuticals": "pharmaceuticals",
    "toxins": "toxins",
    "paper": "papers",
    "papers": "papers",
}

REPORT_RELATIONS = {"report", "reports", "reported", "??"}


def norm(value: str) -> str:
    return " ".join(str(value or "").split()).strip()


def norm_key(value: str) -> str:
    return norm(value).casefold()


def iter_jsonl(path: Path):
    with path.open("r", encoding="utf-8") as handle:
        for line_no, line in enumerate(handle, start=1):
            line = line.strip()
            if not line:
                continue
            yield line_no, json.loads(line)


def strip_paper_prefix(value: str) -> str:
    value = norm(value)
    if value.lower().startswith("paper:"):
        return norm(value.split(":", 1)[1])
    return value


def build_indexes(entities: dict) -> dict:
    indexes = {bucket: {} for bucket in set(ENTITY_BUCKET_MAP.values())}
    for raw_type, items in (entities or {}).items():
        bucket = ENTITY_BUCKET_MAP.get(raw_type)
        if not bucket:
            continue
        if bucket == "papers":
            for item in items or []:
                name = norm(item.get("name", ""))
                if name:
                    indexes["papers"][norm_key(name)] = name
            continue
        for item in items or []:
            name = norm(item.get("name", ""))
            if name:
                indexes[bucket][norm_key(name)] = name
    return indexes


def infer_endpoint(value: str, indexes: dict):
    raw = norm(value)
    if not raw:
        return None, None

    paper_name = strip_paper_prefix(raw)
    paper_key = norm_key(paper_name)
    if paper_key in indexes.get("papers", {}):
        return "papers", indexes["papers"][paper_key]

    raw_key = norm_key(raw)
    for bucket, bucket_index in indexes.items():
        if bucket == "papers":
            continue
        if raw_key in bucket_index:
            return bucket, bucket_index[raw_key]

    return None, raw


def main() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

    unresolved_rows = []
    missing_side_counter = Counter()
    relation_counter = Counter()
    source_counter = Counter()
    target_counter = Counter()

    for _, obj in iter_jsonl(INPUT_FILE):
        pmid = norm(obj.get("pmid", ""))
        entities = obj.get("entities") or {}
        indexes = build_indexes(entities)

        for rel in obj.get("relations") or []:
            relation = norm(rel.get("relation", ""))
            if norm_key(relation) in {x.casefold() for x in REPORT_RELATIONS}:
                continue

            source_bucket, source_name = infer_endpoint(rel.get("source", ""), indexes)
            target_bucket, target_name = infer_endpoint(rel.get("target", ""), indexes)
            if source_bucket and target_bucket and source_name and target_name:
                continue

            missing = []
            if not source_bucket or not source_name:
                missing.append("source")
            if not target_bucket or not target_name:
                missing.append("target")

            row = {
                "pmid": pmid,
                "source": norm(rel.get("source", "")),
                "relation": relation,
                "target": norm(rel.get("target", "")),
                "description": norm(rel.get("description", "")),
                "missing": missing,
                "source_bucket": source_bucket or "",
                "target_bucket": target_bucket or "",
            }
            unresolved_rows.append(row)

            missing_side_counter["+".join(missing)] += 1
            relation_counter[relation] += 1
            if "source" in missing:
                source_counter[row["source"]] += 1
            if "target" in missing:
                target_counter[row["target"]] += 1

    with OUTPUT_JSONL.open("w", encoding="utf-8") as handle:
        for row in unresolved_rows:
            handle.write(json.dumps(row, ensure_ascii=False) + "\n")

    with OUTPUT_CSV.open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.DictWriter(
            handle,
            fieldnames=[
                "pmid",
                "source",
                "relation",
                "target",
                "description",
                "missing",
                "source_bucket",
                "target_bucket",
            ],
        )
        writer.writeheader()
        for row in unresolved_rows:
            payload = dict(row)
            payload["missing"] = ",".join(row["missing"])
            writer.writerow(payload)

    summary = {
        "input_file": str(INPUT_FILE),
        "unresolved_relation_count": len(unresolved_rows),
        "missing_side_breakdown": dict(missing_side_counter),
        "top_relations": relation_counter.most_common(30),
        "top_missing_sources": source_counter.most_common(30),
        "top_missing_targets": target_counter.most_common(30),
    }
    OUTPUT_SUMMARY.write_text(json.dumps(summary, ensure_ascii=False, indent=2), encoding="utf-8")

    print(f"Wrote: {OUTPUT_JSONL}")
    print(f"Wrote: {OUTPUT_CSV}")
    print(f"Wrote: {OUTPUT_SUMMARY}")
    print(json.dumps(summary, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
