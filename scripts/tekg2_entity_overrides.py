from __future__ import annotations

from collections import Counter


ENTITY_OVERRIDES = {
    "tip100": {"canonical": "hAT-Tip100"},
    "hsat5": {"group": "genes"},
    "human alphoid repetitive dna": {"group": "genes"},
    "hy pseudogene": {"group": "genes"},
    "line-1 retrotransposition": {"group": "functions"},
    "lr": {"canonical": "LTR"},
    "lsau": {"group": "genes"},
    "mir insertion in polg": {"group": "mutations"},
    "sva-a": {"canonical": "SVA_A"},
    "sva-b": {"canonical": "SVA_B"},
    "sva-c": {"canonical": "SVA_C"},
    "sva-d": {"canonical": "SVA_D"},
    "sva-e": {"canonical": "SVA_E"},
    "sva-f": {"canonical": "SVA_F"},
    "repetitive sequence": {"group": "genes"},
    "reverse transcriptase": {"group": "proteins"},
    "slmo2 retroduplication": {"group": "functions"},
}


def norm(value: str) -> str:
    return " ".join(str(value or "").split()).strip()


def norm_key(value: str) -> str:
    return norm(value).casefold()


def apply_entity_override(name: str, group: str) -> tuple[str, str, bool]:
    current_name = norm(name)
    current_group = norm(group)
    override = ENTITY_OVERRIDES.get(norm_key(current_name))
    if not override:
        return current_name, current_group, False

    next_name = norm(override.get("canonical", current_name))
    next_group = norm(override.get("group", current_group))
    changed = next_name != current_name or next_group != current_group
    return next_name, next_group, changed


def apply_relation_endpoint_override(name: str) -> tuple[str, bool]:
    next_name, _group, changed = apply_entity_override(name, "")
    return next_name, changed


def dedupe_named_entities(items: list[dict], keep_disease_class: bool = False) -> list[dict]:
    deduped = []
    seen = {}
    for item in items or []:
        name = norm(item.get("name", ""))
        if not name:
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
            existing = deduped[seen[key]]
            if not existing.get("description") and payload.get("description"):
                existing["description"] = payload["description"]
            if keep_disease_class and not existing.get("disease_class") and payload.get("disease_class"):
                existing["disease_class"] = payload["disease_class"]
            continue

        seen[key] = len(deduped)
        deduped.append(payload)
    return deduped


def dedupe_relations(items: list[dict]) -> list[dict]:
    deduped = []
    seen = {}
    for item in items or []:
        source = norm(item.get("source", ""))
        relation = norm(item.get("relation", ""))
        target = norm(item.get("target", ""))
        if not source or not relation or not target:
            continue

        payload = {
            "source": source,
            "relation": relation,
            "target": target,
            "description": norm(item.get("description", "")),
        }
        key = (norm_key(source), norm_key(relation), norm_key(target))
        if key in seen:
            existing = deduped[seen[key]]
            if not existing.get("description") and payload.get("description"):
                existing["description"] = payload["description"]
            continue

        seen[key] = len(deduped)
        deduped.append(payload)
    return deduped


def apply_record_overrides(record: dict) -> tuple[dict, dict]:
    entities = record.get("entities") or {}
    rewritten = {bucket: [] for bucket in entities.keys()}
    stats = Counter()

    for bucket, items in entities.items():
        for item in items or []:
            original_name = norm(item.get("name", ""))
            if not original_name:
                continue

            next_name, next_group, changed = apply_entity_override(original_name, bucket)
            if next_group not in rewritten:
                rewritten[next_group] = []

            payload = {
                "name": next_name,
                "description": norm(item.get("description", "")),
            }
            if "disease_class" in item and norm(item.get("disease_class", "")):
                payload["disease_class"] = norm(item.get("disease_class", ""))

            rewritten[next_group].append(payload)
            if changed:
                stats["entity_override_count"] += 1
                if next_name != original_name:
                    stats["entity_rename_count"] += 1
                if next_group != norm(bucket):
                    stats["entity_regroup_count"] += 1

    for bucket, items in list(rewritten.items()):
        rewritten[bucket] = dedupe_named_entities(items, keep_disease_class=(bucket == "diseases"))

    relations = []
    for relation in record.get("relations") or []:
        original_source = norm(relation.get("source", ""))
        original_target = norm(relation.get("target", ""))
        next_source, source_changed = apply_relation_endpoint_override(original_source)
        next_target, target_changed = apply_relation_endpoint_override(original_target)
        payload = {
            "source": next_source,
            "relation": norm(relation.get("relation", "")),
            "target": next_target,
            "description": norm(relation.get("description", "")),
        }
        relations.append(payload)
        if source_changed or target_changed:
            stats["relation_endpoint_override_count"] += 1

    updated = {
        **record,
        "entities": rewritten,
        "relations": dedupe_relations(relations),
    }
    return updated, dict(stats)
