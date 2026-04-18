import argparse
import json
from collections import Counter
from pathlib import Path

from tekg2_entity_overrides import apply_record_overrides

ENTITY_GROUP_ALIASES = {
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

ENTITY_GROUP_ORDER = [
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


def norm(value) -> str:
    return " ".join(str(value or "").split()).strip()


def norm_key(value: str) -> str:
    return norm(value).casefold()


def clean_named_entities(items, keep_disease_class: bool = False):
    cleaned = []
    seen = {}
    dropped_missing_name = 0
    duplicates_merged = 0

    for item in items or []:
        name = norm(item.get("name", ""))
        if not name:
            dropped_missing_name += 1
            continue

        payload = {
            "name": name,
            "description": norm(item.get("description", "")),
        }
        if keep_disease_class:
            disease_class = norm(item.get("disease_class", ""))
            if disease_class:
                payload["disease_class"] = disease_class

        key = norm_key(name)
        if key in seen:
            duplicates_merged += 1
            existing = cleaned[seen[key]]
            if not existing.get("description") and payload.get("description"):
                existing["description"] = payload["description"]
            if keep_disease_class and not existing.get("disease_class") and payload.get("disease_class"):
                existing["disease_class"] = payload["disease_class"]
            continue

        seen[key] = len(cleaned)
        cleaned.append(payload)

    return cleaned, dropped_missing_name, duplicates_merged


def clean_papers(items):
    cleaned = []
    seen = {}
    dropped_missing_name = 0
    duplicates_merged = 0

    for item in items or []:
        title = norm(item.get("name", ""))
        if not title:
            dropped_missing_name += 1
            continue

        payload = {
            "name": title,
            "description": norm(item.get("description", "")),
        }

        key = norm_key(title)
        if key in seen:
            duplicates_merged += 1
            existing = cleaned[seen[key]]
            if not existing.get("description") and payload.get("description"):
                existing["description"] = payload["description"]
            continue

        seen[key] = len(cleaned)
        cleaned.append(payload)

    return cleaned, dropped_missing_name, duplicates_merged


def clean_relations(items):
    cleaned = []
    seen = {}
    dropped_missing_endpoint = 0
    duplicates_merged = 0

    for item in items or []:
        source = norm(item.get("source", ""))
        relation = norm(item.get("relation", ""))
        target = norm(item.get("target", ""))
        description = norm(item.get("description", ""))

        if not source or not relation or not target:
            dropped_missing_endpoint += 1
            continue

        payload = {
            "source": source,
            "relation": relation,
            "target": target,
            "description": description,
        }
        key = (
            norm_key(source),
            norm_key(relation),
            norm_key(target),
        )
        if key in seen:
            duplicates_merged += 1
            existing = cleaned[seen[key]]
            if not existing.get("description") and description:
                existing["description"] = description
            continue

        seen[key] = len(cleaned)
        cleaned.append(payload)

    return cleaned, dropped_missing_endpoint, duplicates_merged


def clean_record(record: dict):
    entities_raw = record.get("entities") or {}
    cleaned_entities = {group: [] for group in ENTITY_GROUP_ORDER}

    entity_stats = {
        "unknown_groups": [],
        "dropped_missing_name": 0,
        "duplicates_merged": 0,
    }

    for raw_group, items in entities_raw.items():
        canonical_group = ENTITY_GROUP_ALIASES.get(raw_group)
        if not canonical_group:
            entity_stats["unknown_groups"].append(raw_group)
            continue

        if canonical_group == "papers":
            cleaned_items, dropped_missing_name, duplicates_merged = clean_papers(items)
        else:
            cleaned_items, dropped_missing_name, duplicates_merged = clean_named_entities(
                items,
                keep_disease_class=(canonical_group == "diseases"),
            )

        cleaned_entities[canonical_group].extend(cleaned_items)
        entity_stats["dropped_missing_name"] += dropped_missing_name
        entity_stats["duplicates_merged"] += duplicates_merged

    # One more dedupe pass after alias collapse (paper + papers).
    deduped_entities = {}
    post_alias_duplicates = 0
    post_alias_missing = 0
    for group in ENTITY_GROUP_ORDER:
        items = cleaned_entities[group]
        if group == "papers":
            deduped, dropped_missing_name, duplicates_merged = clean_papers(items)
        else:
            deduped, dropped_missing_name, duplicates_merged = clean_named_entities(
                items,
                keep_disease_class=(group == "diseases"),
            )
        deduped_entities[group] = deduped
        post_alias_duplicates += duplicates_merged
        post_alias_missing += dropped_missing_name

    relations, dropped_missing_endpoint, relation_duplicates_merged = clean_relations(record.get("relations") or [])

    cleaned_record = {
        "pmid": norm(record.get("pmid", "")),
        "entities": deduped_entities,
        "relations": relations,
    }
    cleaned_record, override_stats = apply_record_overrides(cleaned_record)
    return cleaned_record, {
        "unknown_groups": entity_stats["unknown_groups"],
        "entity_dropped_missing_name": entity_stats["dropped_missing_name"] + post_alias_missing,
        "entity_duplicates_merged": entity_stats["duplicates_merged"] + post_alias_duplicates,
        "relation_dropped_missing_endpoint": dropped_missing_endpoint,
        "relation_duplicates_merged": relation_duplicates_merged,
        "entity_override_count": override_stats.get("entity_override_count", 0),
        "entity_rename_count": override_stats.get("entity_rename_count", 0),
        "entity_regroup_count": override_stats.get("entity_regroup_count", 0),
        "relation_endpoint_override_count": override_stats.get("relation_endpoint_override_count", 0),
    }


def clean_jsonl(input_file: Path, output_file: Path, report_file: Path):
    total_records = 0
    cleaned_records = 0
    parse_errors = []
    unknown_group_counter = Counter()
    entity_group_counts = Counter()
    relation_type_counter = Counter()
    dropped_missing_name = 0
    merged_entity_duplicates = 0
    dropped_bad_relations = 0
    merged_relation_duplicates = 0
    entity_override_count = 0
    entity_rename_count = 0
    entity_regroup_count = 0
    relation_endpoint_override_count = 0

    output_file.parent.mkdir(parents=True, exist_ok=True)
    with input_file.open("r", encoding="utf-8") as src, output_file.open("w", encoding="utf-8") as dst:
        for line_no, raw_line in enumerate(src, start=1):
            raw = raw_line.strip()
            if not raw:
                continue
            total_records += 1
            try:
                record = json.loads(raw)
            except Exception as exc:
                parse_errors.append({
                    "line": line_no,
                    "error": str(exc),
                    "preview": raw[:240],
                })
                continue

            cleaned_record, stats = clean_record(record)
            cleaned_records += 1
            dropped_missing_name += stats["entity_dropped_missing_name"]
            merged_entity_duplicates += stats["entity_duplicates_merged"]
            dropped_bad_relations += stats["relation_dropped_missing_endpoint"]
            merged_relation_duplicates += stats["relation_duplicates_merged"]
            entity_override_count += stats["entity_override_count"]
            entity_rename_count += stats["entity_rename_count"]
            entity_regroup_count += stats["entity_regroup_count"]
            relation_endpoint_override_count += stats["relation_endpoint_override_count"]

            for group in stats["unknown_groups"]:
                unknown_group_counter[group] += 1
            for group, items in cleaned_record["entities"].items():
                if items:
                    entity_group_counts[group] += len(items)
            for rel in cleaned_record["relations"]:
                relation_type_counter[rel["relation"]] += 1

            dst.write(json.dumps(cleaned_record, ensure_ascii=False) + "\n")

    report = {
        "input_file": str(input_file),
        "output_file": str(output_file),
        "records_in_input": total_records,
        "records_written": cleaned_records,
        "parse_error_count": len(parse_errors),
        "parse_errors": parse_errors[:20],
        "entity_group_counts": dict(entity_group_counts),
        "relation_type_counts_top20": relation_type_counter.most_common(20),
        "dropped_entity_items_missing_name": dropped_missing_name,
        "merged_entity_duplicates": merged_entity_duplicates,
        "entity_override_count": entity_override_count,
        "entity_rename_count": entity_rename_count,
        "entity_regroup_count": entity_regroup_count,
        "dropped_relations_missing_endpoint": dropped_bad_relations,
        "merged_relation_duplicates": merged_relation_duplicates,
        "relation_endpoint_override_count": relation_endpoint_override_count,
        "unknown_entity_groups": dict(unknown_group_counter),
    }
    report_file.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return report


def main():
    parser = argparse.ArgumentParser(description="Clean TEKG2 standardized jsonl into a stable graph-ready baseline.")
    parser.add_argument("input_file", type=Path)
    parser.add_argument("output_file", type=Path)
    parser.add_argument("report_file", type=Path)
    args = parser.parse_args()

    report = clean_jsonl(args.input_file, args.output_file, args.report_file)
    print(json.dumps(report, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
