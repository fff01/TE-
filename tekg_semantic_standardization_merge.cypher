// Generated semantic standardization merge cypher

// Merge Disease: FTD -> Frontotemporal dementia
MATCH (keep:Disease)
WHERE keep.name = "Frontotemporal dementia"
MATCH (drop:Disease)
WHERE drop.name = "FTD"
WITH keep, drop
WHERE id(keep) <> id(drop)
CALL {
  WITH keep, drop
  MATCH (src)-[r:BIO_RELATION]->(drop)
  MERGE (src)-[r2:BIO_RELATION {predicate: r.predicate}]->(keep)
  SET r2.pmids = reduce(acc = coalesce(r2.pmids, []), x IN coalesce(r.pmids, []) | CASE WHEN x IN acc THEN acc ELSE acc + x END),
      r2.source_group = coalesce(r2.source_group, r.source_group)
  DELETE r
}
CALL {
  WITH keep, drop
  MATCH (drop)-[r:BIO_RELATION]->(dst)
  MERGE (keep)-[r2:BIO_RELATION {predicate: r.predicate}]->(dst)
  SET r2.pmids = reduce(acc = coalesce(r2.pmids, []), x IN coalesce(r.pmids, []) | CASE WHEN x IN acc THEN acc ELSE acc + x END),
      r2.source_group = coalesce(r2.source_group, r.source_group)
  DELETE r
}
CALL {
  WITH keep, drop
  MATCH (src)-[r:EVIDENCE_RELATION]->(drop)
  MERGE (src)-[r2:EVIDENCE_RELATION {predicate: r.predicate}]->(keep)
  SET r2.pmids = reduce(acc = coalesce(r2.pmids, []), x IN coalesce(r.pmids, []) | CASE WHEN x IN acc THEN acc ELSE acc + x END),
      r2.source_group = coalesce(r2.source_group, r.source_group)
  DELETE r
}
SET keep.description = CASE WHEN coalesce(keep.description, '') = '' THEN drop.description ELSE keep.description END
DETACH DELETE drop;

// Merge Disease: HNSCC -> Head and neck squamous cell carcinoma
MATCH (keep:Disease)
WHERE keep.name = "Head and neck squamous cell carcinoma"
MATCH (drop:Disease)
WHERE drop.name = "HNSCC"
WITH keep, drop
WHERE id(keep) <> id(drop)
CALL {
  WITH keep, drop
  MATCH (src)-[r:BIO_RELATION]->(drop)
  MERGE (src)-[r2:BIO_RELATION {predicate: r.predicate}]->(keep)
  SET r2.pmids = reduce(acc = coalesce(r2.pmids, []), x IN coalesce(r.pmids, []) | CASE WHEN x IN acc THEN acc ELSE acc + x END),
      r2.source_group = coalesce(r2.source_group, r.source_group)
  DELETE r
}
CALL {
  WITH keep, drop
  MATCH (drop)-[r:BIO_RELATION]->(dst)
  MERGE (keep)-[r2:BIO_RELATION {predicate: r.predicate}]->(dst)
  SET r2.pmids = reduce(acc = coalesce(r2.pmids, []), x IN coalesce(r.pmids, []) | CASE WHEN x IN acc THEN acc ELSE acc + x END),
      r2.source_group = coalesce(r2.source_group, r.source_group)
  DELETE r
}
CALL {
  WITH keep, drop
  MATCH (src)-[r:EVIDENCE_RELATION]->(drop)
  MERGE (src)-[r2:EVIDENCE_RELATION {predicate: r.predicate}]->(keep)
  SET r2.pmids = reduce(acc = coalesce(r2.pmids, []), x IN coalesce(r.pmids, []) | CASE WHEN x IN acc THEN acc ELSE acc + x END),
      r2.source_group = coalesce(r2.source_group, r.source_group)
  DELETE r
}
SET keep.description = CASE WHEN coalesce(keep.description, '') = '' THEN drop.description ELSE keep.description END
DETACH DELETE drop;

// Merge Disease: LSCC -> lung squamous cell carcinoma
MATCH (keep:Disease)
WHERE keep.name = "lung squamous cell carcinoma"
MATCH (drop:Disease)
WHERE drop.name = "LSCC"
WITH keep, drop
WHERE id(keep) <> id(drop)
CALL {
  WITH keep, drop
  MATCH (src)-[r:BIO_RELATION]->(drop)
  MERGE (src)-[r2:BIO_RELATION {predicate: r.predicate}]->(keep)
  SET r2.pmids = reduce(acc = coalesce(r2.pmids, []), x IN coalesce(r.pmids, []) | CASE WHEN x IN acc THEN acc ELSE acc + x END),
      r2.source_group = coalesce(r2.source_group, r.source_group)
  DELETE r
}
CALL {
  WITH keep, drop
  MATCH (drop)-[r:BIO_RELATION]->(dst)
  MERGE (keep)-[r2:BIO_RELATION {predicate: r.predicate}]->(dst)
  SET r2.pmids = reduce(acc = coalesce(r2.pmids, []), x IN coalesce(r.pmids, []) | CASE WHEN x IN acc THEN acc ELSE acc + x END),
      r2.source_group = coalesce(r2.source_group, r.source_group)
  DELETE r
}
CALL {
  WITH keep, drop
  MATCH (src)-[r:EVIDENCE_RELATION]->(drop)
  MERGE (src)-[r2:EVIDENCE_RELATION {predicate: r.predicate}]->(keep)
  SET r2.pmids = reduce(acc = coalesce(r2.pmids, []), x IN coalesce(r.pmids, []) | CASE WHEN x IN acc THEN acc ELSE acc + x END),
      r2.source_group = coalesce(r2.source_group, r.source_group)
  DELETE r
}
SET keep.description = CASE WHEN coalesce(keep.description, '') = '' THEN drop.description ELSE keep.description END
DETACH DELETE drop;
