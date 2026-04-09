import json
from collections import Counter
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
INPUT_FILE = ROOT / "data_update_fix" / "te_kg2_final_standardized_new_standardized_fix.jsonl"
OUTPUT_DIR = ROOT / "data" / "processed" / "tekg2"
SEED_JSON = OUTPUT_DIR / "tekg2_seed.json"
REPORT_JSON = OUTPUT_DIR / "tekg2_seed_report.json"

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
    "mutations": "mutations",
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


def add_named_entity(bucket: dict, item: dict, entity_type: str):
    name = norm(item.get("name", ""))
    if not name:
        return
    key = norm_key(name)
    payload = {"name": name, "description": norm(item.get("description", ""))}
    if entity_type == "diseases":
        disease_class = norm(item.get("disease_class", ""))
        if disease_class:
            payload["disease_class"] = disease_class
    if key not in bucket:
        bucket[key] = payload
        return
    existing = bucket[key]
    if not existing.get("description") and payload.get("description"):
        existing["description"] = payload["description"]
    if entity_type == "diseases" and not existing.get("disease_class") and payload.get("disease_class"):
        existing["disease_class"] = payload["disease_class"]


def add_paper_entity(bucket: dict, pmid: str, paper_items: list[dict]):
    pmid = norm(pmid)
    if not pmid:
        return
    title = ""
    description = ""
    if paper_items:
        title = norm(paper_items[0].get("name", ""))
        description = norm(paper_items[0].get("description", ""))
    if not title:
        title = f"PMID:{pmid}"
    payload = {"pmid": pmid, "name": title, "description": description}
    if pmid not in bucket:
        bucket[pmid] = payload
        return
    existing = bucket[pmid]
    if existing.get("name", "").startswith("PMID:") and title and not title.startswith("PMID:"):
        existing["name"] = title
    if not existing.get("description") and description:
        existing["description"] = description


def build_record_indexes(entities: dict) -> dict:
    indexes = {bucket: {} for bucket in set(ENTITY_BUCKET_MAP.values())}
    for raw_type, items in (entities or {}).items():
        bucket_name = ENTITY_BUCKET_MAP.get(raw_type)
        if not bucket_name:
            continue
        if bucket_name == "papers":
            for item in items or []:
                title = norm(item.get("name", ""))
                if title:
                    indexes["papers"][norm_key(title)] = title
            continue
        for item in items or []:
            name = norm(item.get("name", ""))
            if name:
                indexes[bucket_name][norm_key(name)] = name
    return indexes


def strip_paper_prefix(value: str) -> str:
    value = norm(value)
    if value.lower().startswith("paper:"):
        return norm(value.split(":", 1)[1])
    return value


def infer_endpoint(endpoint: str, indexes: dict):
    raw = norm(endpoint)
    if not raw:
        return None, None
    paper_name = strip_paper_prefix(raw)
    if norm_key(paper_name) in indexes.get("papers", {}):
        return "papers", indexes["papers"][norm_key(paper_name)]
    for bucket_name, bucket_index in indexes.items():
        if bucket_name == "papers":
            continue
        if norm_key(raw) in bucket_index:
            return bucket_name, bucket_index[norm_key(raw)]
    return None, raw


def build_seed(input_file: Path):
    node_buckets = {bucket: {} for bucket in set(ENTITY_BUCKET_MAP.values())}
    relation_buckets = {}
    relation_counter = Counter()
    skipped_report = 0
    unresolved = []
    unresolved_count = 0
    malformed_relation_count = 0
    total_records = 0

    for _line_no, obj in iter_jsonl(input_file):
        total_records += 1
        entities = obj.get("entities") or {}
        pmid = norm(obj.get("pmid", ""))

        for raw_type, items in entities.items():
            bucket_name = ENTITY_BUCKET_MAP.get(raw_type)
            if not bucket_name:
                continue
            if bucket_name == "papers":
                add_paper_entity(node_buckets["papers"], pmid, items or [])
                continue
            for item in items or []:
                add_named_entity(node_buckets[bucket_name], item, bucket_name)

        indexes = build_record_indexes(entities)
        if pmid and pmid not in node_buckets["papers"]:
            add_paper_entity(node_buckets["papers"], pmid, [])
            indexes = build_record_indexes(entities)

        for rel in (obj.get("relations") or []):
            relation_name = norm(rel.get("relation", ""))
            source_raw = norm(rel.get("source", ""))
            target_raw = norm(rel.get("target", ""))
            if norm_key(relation_name) in {x.casefold() for x in REPORT_RELATIONS}:
                skipped_report += 1
                continue
            if not source_raw or not target_raw:
                malformed_relation_count += 1
                continue
            source_bucket, source_name = infer_endpoint(source_raw, indexes)
            target_bucket, target_name = infer_endpoint(target_raw, indexes)
            if not source_bucket or not target_bucket or not source_name or not target_name:
                unresolved_count += 1
                if len(unresolved) < 25:
                    unresolved.append({
                        "pmid": pmid,
                        "source": source_raw,
                        "relation": relation_name,
                        "target": target_raw,
                        "source_bucket": source_bucket,
                        "target_bucket": target_bucket,
                    })
                continue
            key = (source_bucket, norm_key(source_name), norm_key(relation_name), target_bucket, norm_key(target_name))
            if key not in relation_buckets:
                relation_buckets[key] = {
                    "source": source_name,
                    "source_group": source_bucket,
                    "relation": relation_name,
                    "target": target_name,
                    "target_group": target_bucket,
                    "description": norm(rel.get("description", "")),
                    "pmids": [],
                }
            payload = relation_buckets[key]
            if pmid:
                payload["pmids"].append(pmid)
            if not payload.get("description") and rel.get("description"):
                payload["description"] = norm(rel.get("description", ""))
            relation_counter[relation_name] += 1

    seed = {
        "nodes": {
            bucket: sorted(bucket_values.values(), key=lambda x: (x.get("name", "").casefold(), x.get("pmid", "")))
            for bucket, bucket_values in node_buckets.items()
        },
        "relations": sorted(
            (
                {
                    **payload,
                    "pmids": sorted({pmid for pmid in payload["pmids"] if pmid}),
                }
                for payload in relation_buckets.values()
            ),
            key=lambda x: (
                x["source_group"],
                x["source"].casefold(),
                x["relation"].casefold(),
                x["target_group"],
                x["target"].casefold(),
            ),
        ),
        "lineage_relations": [],
    }
    report = {
        "input_file": str(input_file),
        "records": total_records,
        "node_counts": {bucket: len(items) for bucket, items in seed["nodes"].items()},
        "relation_count": len(seed["relations"]),
        "top_relations": relation_counter.most_common(30),
        "skipped_report_relations": skipped_report,
        "unresolved_relation_count": unresolved_count,
        "malformed_relation_count": malformed_relation_count,
        "unresolved_relation_samples": unresolved,
    }
    return seed, report


def main():
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    seed, report = build_seed(INPUT_FILE)
    SEED_JSON.write_text(json.dumps(seed, ensure_ascii=False, indent=2), encoding="utf-8")
    REPORT_JSON.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"Wrote: {SEED_JSON}")
    print(f"Wrote: {REPORT_JSON}")
    print(json.dumps({
        "records": report["records"],
        "relation_count": report["relation_count"],
        "skipped_report_relations": report["skipped_report_relations"],
        "node_counts": report["node_counts"],
    }, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
