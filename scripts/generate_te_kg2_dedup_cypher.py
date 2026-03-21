import json
from pathlib import Path

from normalize_te_kg2_graph import DISEASE_ALIASES, FUNCTION_ALIASES


OUTPUT_FILE = Path("imports/te_kg2_dedup_merge.cypher")


def cypher_string(value: str) -> str:
    return json.dumps(value, ensure_ascii=False)


def dedup_pairs(alias_map: dict[str, str]) -> list[tuple[str, str]]:
    pairs = []
    for alias, canonical in alias_map.items():
        if alias != canonical and canonical:
            pairs.append((alias, canonical))
    return sorted(set(pairs), key=lambda x: (x[1].casefold(), x[0].casefold()))


def build_merge_block(label: str, duplicate_name: str, canonical_name: str) -> str:
    return f"""// Merge {label}: {duplicate_name} -> {canonical_name}
MATCH (keep:{label})
WHERE toLower(keep.name) = toLower({cypher_string(canonical_name)})
MATCH (drop:{label})
WHERE toLower(drop.name) = toLower({cypher_string(duplicate_name)})
WITH keep, drop
WHERE id(keep) <> id(drop)
CALL {{
  WITH keep, drop
  MATCH (src)-[r:BIO_RELATION]->(drop)
  MERGE (src)-[r2:BIO_RELATION {{predicate: r.predicate}}]->(keep)
  SET r2.pmids = reduce(acc = coalesce(r2.pmids, []), x IN coalesce(r.pmids, []) | CASE WHEN x IN acc THEN acc ELSE acc + x END),
      r2.source_group = coalesce(r2.source_group, r.source_group)
  DELETE r
}}
CALL {{
  WITH keep, drop
  MATCH (drop)-[r:BIO_RELATION]->(dst)
  MERGE (keep)-[r2:BIO_RELATION {{predicate: r.predicate}}]->(dst)
  SET r2.pmids = reduce(acc = coalesce(r2.pmids, []), x IN coalesce(r.pmids, []) | CASE WHEN x IN acc THEN acc ELSE acc + x END),
      r2.source_group = coalesce(r2.source_group, r.source_group)
  DELETE r
}}
CALL {{
  WITH keep, drop
  MATCH (src)-[r:EVIDENCE_RELATION]->(drop)
  MERGE (src)-[r2:EVIDENCE_RELATION {{predicate: r.predicate}}]->(keep)
  SET r2.pmids = reduce(acc = coalesce(r2.pmids, []), x IN coalesce(r.pmids, []) | CASE WHEN x IN acc THEN acc ELSE acc + x END),
      r2.source_group = coalesce(r2.source_group, r.source_group)
  DELETE r
}}
SET keep.description = CASE WHEN coalesce(keep.description, '') = '' THEN drop.description ELSE keep.description END
DETACH DELETE drop;
"""


def main() -> None:
    blocks = ["// Generated duplicate-merge cypher for te_kg2 imported data", ""]
    for duplicate_name, canonical_name in dedup_pairs(DISEASE_ALIASES):
        blocks.append(build_merge_block("Disease", duplicate_name, canonical_name))
    for duplicate_name, canonical_name in dedup_pairs(FUNCTION_ALIASES):
        blocks.append(build_merge_block("Function", duplicate_name, canonical_name))
    OUTPUT_FILE.write_text("\n".join(blocks), encoding="utf-8")
    print(f"Wrote: {OUTPUT_FILE}")


if __name__ == "__main__":
    main()
