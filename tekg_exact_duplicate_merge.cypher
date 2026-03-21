// Generated exact duplicate merge cypher from current tekg database

// Merge Disease: acquired immunodeficiency syndrome (AIDS) -> Acquired immunodeficiency syndrome (AIDS)
MATCH (keep:Disease)
WHERE keep.name = "Acquired immunodeficiency syndrome (AIDS)"
MATCH (drop:Disease)
WHERE drop.name = "acquired immunodeficiency syndrome (AIDS)"
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

// Merge Disease: autoimmunity -> Autoimmunity
MATCH (keep:Disease)
WHERE keep.name = "Autoimmunity"
MATCH (drop:Disease)
WHERE drop.name = "autoimmunity"
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

// Merge Disease: carcinogenesis -> Carcinogenesis
MATCH (keep:Disease)
WHERE keep.name = "Carcinogenesis"
MATCH (drop:Disease)
WHERE drop.name = "carcinogenesis"
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

// Merge Disease: choroideremia -> Choroideremia
MATCH (keep:Disease)
WHERE keep.name = "Choroideremia"
MATCH (drop:Disease)
WHERE drop.name = "choroideremia"
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

// Merge Disease: Fanconi Anemia -> Fanconi anemia
MATCH (keep:Disease)
WHERE keep.name = "Fanconi anemia"
MATCH (drop:Disease)
WHERE drop.name = "Fanconi Anemia"
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

// Merge Disease: frontotemporal lobar degeneration -> Frontotemporal lobar degeneration
MATCH (keep:Disease)
WHERE keep.name = "Frontotemporal lobar degeneration"
MATCH (drop:Disease)
WHERE drop.name = "frontotemporal lobar degeneration"
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

// Merge Disease: hemimegalencephaly -> Hemimegalencephaly
MATCH (keep:Disease)
WHERE keep.name = "Hemimegalencephaly"
MATCH (drop:Disease)
WHERE drop.name = "hemimegalencephaly"
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

// Merge Disease: neurodegenerative conditions -> Neurodegenerative conditions
MATCH (keep:Disease)
WHERE keep.name = "Neurodegenerative conditions"
MATCH (drop:Disease)
WHERE drop.name = "neurodegenerative conditions"
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

// Merge Disease: neurodevelopmental diseases -> Neurodevelopmental diseases
MATCH (keep:Disease)
WHERE keep.name = "Neurodevelopmental diseases"
MATCH (drop:Disease)
WHERE drop.name = "neurodevelopmental diseases"
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

// Merge Disease: neuropsychiatric conditions -> Neuropsychiatric conditions
MATCH (keep:Disease)
WHERE keep.name = "Neuropsychiatric conditions"
MATCH (drop:Disease)
WHERE drop.name = "neuropsychiatric conditions"
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

// Merge Disease: normal aging -> Normal aging
MATCH (keep:Disease)
WHERE keep.name = "Normal aging"
MATCH (drop:Disease)
WHERE drop.name = "normal aging"
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

// Merge Disease: Oral squamous cell carcinoma -> Oral Squamous Cell Carcinoma
MATCH (keep:Disease)
WHERE keep.name = "Oral Squamous Cell Carcinoma"
MATCH (drop:Disease)
WHERE drop.name = "Oral squamous cell carcinoma"
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

// Merge Disease: primary effusion lymphoma -> Primary effusion lymphoma
MATCH (keep:Disease)
WHERE keep.name = "Primary effusion lymphoma"
MATCH (drop:Disease)
WHERE drop.name = "primary effusion lymphoma"
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

// Merge Disease: substance abuse disorders -> Substance abuse disorders
MATCH (keep:Disease)
WHERE keep.name = "Substance abuse disorders"
MATCH (drop:Disease)
WHERE drop.name = "substance abuse disorders"
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

// Merge Disease: α-1 antitrypsin deficiency -> α1-antitrypsin deficiency
MATCH (keep:Disease)
WHERE keep.name = "α1-antitrypsin deficiency"
MATCH (drop:Disease)
WHERE drop.name = "α-1 antitrypsin deficiency"
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

// Merge Function: Altered retrotransposon expression -> altered retrotransposon expression
MATCH (keep:Function)
WHERE keep.name = "altered retrotransposon expression"
MATCH (drop:Function)
WHERE drop.name = "Altered retrotransposon expression"
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

// Merge Function: Apoptosis -> apoptosis
MATCH (keep:Function)
WHERE keep.name = "apoptosis"
MATCH (drop:Function)
WHERE drop.name = "Apoptosis"
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

// Merge Function: Chimeric gene formation -> chimeric gene formation
MATCH (keep:Function)
WHERE keep.name = "chimeric gene formation"
MATCH (drop:Function)
WHERE drop.name = "Chimeric gene formation"
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

// Merge Function: cis preference -> Cis preference
MATCH (keep:Function)
WHERE keep.name = "Cis preference"
MATCH (drop:Function)
WHERE drop.name = "cis preference"
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

// Merge Function: Copy number variation -> copy number variation
MATCH (keep:Function)
WHERE keep.name = "copy number variation"
MATCH (drop:Function)
WHERE drop.name = "Copy number variation"
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

// Merge Function: down-regulation -> downregulation
MATCH (keep:Function)
WHERE keep.name = "downregulation"
MATCH (drop:Function)
WHERE drop.name = "down-regulation"
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

// Merge Function: Dysregulation of retrotransposon activity -> dysregulation of retrotransposon activity
MATCH (keep:Function)
WHERE keep.name = "dysregulation of retrotransposon activity"
MATCH (drop:Function)
WHERE drop.name = "Dysregulation of retrotransposon activity"
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

// Merge Function: Endonuclease activity -> endonuclease activity
MATCH (keep:Function)
WHERE keep.name = "endonuclease activity"
MATCH (drop:Function)
WHERE drop.name = "Endonuclease activity"
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

// Merge Function: Endonuclease-independent retrotransposition -> endonuclease-independent retrotransposition
MATCH (keep:Function)
WHERE keep.name = "endonuclease-independent retrotransposition"
MATCH (drop:Function)
WHERE drop.name = "Endonuclease-independent retrotransposition"
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

// Merge Function: Epigenetic changes -> epigenetic changes
MATCH (keep:Function)
WHERE keep.name = "epigenetic changes"
MATCH (drop:Function)
WHERE drop.name = "Epigenetic changes"
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

// Merge Function: Epigenetic drift -> epigenetic drift
MATCH (keep:Function)
WHERE keep.name = "epigenetic drift"
MATCH (drop:Function)
WHERE drop.name = "Epigenetic drift"
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

// Merge Function: Exon skipping -> exon-skipping
MATCH (keep:Function)
WHERE keep.name = "exon-skipping"
MATCH (drop:Function)
WHERE drop.name = "Exon skipping"
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

// Merge Function: Gene disruption -> gene disruption
MATCH (keep:Function)
WHERE keep.name = "gene disruption"
MATCH (drop:Function)
WHERE drop.name = "Gene disruption"
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

// Merge Function: Genome rearrangement -> genome rearrangement
MATCH (keep:Function)
WHERE keep.name = "genome rearrangement"
MATCH (drop:Function)
WHERE drop.name = "Genome rearrangement"
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

// Merge Function: Genome stability maintenance -> genome stability maintenance
MATCH (keep:Function)
WHERE keep.name = "genome stability maintenance"
MATCH (drop:Function)
WHERE drop.name = "Genome stability maintenance"
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

// Merge Function: Germline variation -> germline variation
MATCH (keep:Function)
WHERE keep.name = "germline variation"
MATCH (drop:Function)
WHERE drop.name = "Germline variation"
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

// Merge Function: Heritable retrotransposition -> heritable retrotransposition
MATCH (keep:Function)
WHERE keep.name = "heritable retrotransposition"
MATCH (drop:Function)
WHERE drop.name = "Heritable retrotransposition"
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

// Merge Function: Homologous recombination -> homologous recombination
MATCH (keep:Function)
WHERE keep.name = "homologous recombination"
MATCH (drop:Function)
WHERE drop.name = "Homologous recombination"
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

// Merge Function: Innate immune system activation -> innate immune system activation
MATCH (keep:Function)
WHERE keep.name = "innate immune system activation"
MATCH (drop:Function)
WHERE drop.name = "Innate immune system activation"
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

// Merge Function: Insertion into synapse- and schizophrenia-related genes -> insertion into synapse- and schizophrenia-related genes
MATCH (keep:Function)
WHERE keep.name = "insertion into synapse- and schizophrenia-related genes"
MATCH (drop:Function)
WHERE drop.name = "Insertion into synapse- and schizophrenia-related genes"
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

// Merge Function: Insertion polymorphism -> insertion polymorphism
MATCH (keep:Function)
WHERE keep.name = "insertion polymorphism"
MATCH (drop:Function)
WHERE drop.name = "Insertion polymorphism"
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

// Merge Function: integration into replicating DNA -> Integration into replicating DNA
MATCH (keep:Function)
WHERE keep.name = "Integration into replicating DNA"
MATCH (drop:Function)
WHERE drop.name = "integration into replicating DNA"
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

// Merge Function: Interferon system activation -> interferon system activation
MATCH (keep:Function)
WHERE keep.name = "interferon system activation"
MATCH (drop:Function)
WHERE drop.name = "Interferon system activation"
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

// Merge Function: L1-ORF1p表达 -> L1ORF1p表达
MATCH (keep:Function)
WHERE keep.name = "L1ORF1p表达"
MATCH (drop:Function)
WHERE drop.name = "L1-ORF1p表达"
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

// Merge Function: LINE1 retrotransposition -> LINE-1 retrotransposition
MATCH (keep:Function)
WHERE keep.name = "LINE-1 retrotransposition"
MATCH (drop:Function)
WHERE drop.name = "LINE1 retrotransposition"
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

// Merge Function: Mobilization -> mobilization
MATCH (keep:Function)
WHERE keep.name = "mobilization"
MATCH (drop:Function)
WHERE drop.name = "Mobilization"
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

// Merge Function: Neddylation -> neddylation
MATCH (keep:Function)
WHERE keep.name = "neddylation"
MATCH (drop:Function)
WHERE drop.name = "Neddylation"
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

// Merge Function: Neuroinflammation -> neuroinflammation
MATCH (keep:Function)
WHERE keep.name = "neuroinflammation"
MATCH (drop:Function)
WHERE drop.name = "Neuroinflammation"
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

// Merge Function: Oncogenic β-catenin/Wnt signaling -> oncogenic β-catenin/Wnt signaling
MATCH (keep:Function)
WHERE keep.name = "oncogenic β-catenin/Wnt signaling"
MATCH (drop:Function)
WHERE drop.name = "Oncogenic β-catenin/Wnt signaling"
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

// Merge Function: Preintegration barrier -> preintegration barrier
MATCH (keep:Function)
WHERE keep.name = "preintegration barrier"
MATCH (drop:Function)
WHERE drop.name = "Preintegration barrier"
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

// Merge Function: Premature stop codon creation -> premature stop codon creation
MATCH (keep:Function)
WHERE keep.name = "premature stop codon creation"
MATCH (drop:Function)
WHERE drop.name = "Premature stop codon creation"
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

// Merge Function: Programmed cell death -> programmed cell death
MATCH (keep:Function)
WHERE keep.name = "programmed cell death"
MATCH (drop:Function)
WHERE drop.name = "Programmed cell death"
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

// Merge Function: promoter activity regulation of LINE-1 5'-UTR -> Promoter activity regulation of LINE-1 5'-UTR
MATCH (keep:Function)
WHERE keep.name = "Promoter activity regulation of LINE-1 5'-UTR"
MATCH (drop:Function)
WHERE drop.name = "promoter activity regulation of LINE-1 5'-UTR"
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

// Merge Function: Reduced gene expression -> reduced gene expression
MATCH (keep:Function)
WHERE keep.name = "reduced gene expression"
MATCH (drop:Function)
WHERE drop.name = "Reduced gene expression"
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

// Merge Function: Regulation of gene expression -> regulation of gene expression
MATCH (keep:Function)
WHERE keep.name = "regulation of gene expression"
MATCH (drop:Function)
WHERE drop.name = "Regulation of gene expression"
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

// Merge Function: Regulation of retrotransposition -> regulation of retrotransposition
MATCH (keep:Function)
WHERE keep.name = "regulation of retrotransposition"
MATCH (drop:Function)
WHERE drop.name = "Regulation of retrotransposition"
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

// Merge Function: replication-coupled DNA repair -> replication-coupled DNA-repair
MATCH (keep:Function)
WHERE keep.name = "replication-coupled DNA-repair"
MATCH (drop:Function)
WHERE drop.name = "replication-coupled DNA repair"
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

// Merge Function: retrotransposon de-repression -> retrotransposon derepression
MATCH (keep:Function)
WHERE keep.name = "retrotransposon derepression"
MATCH (drop:Function)
WHERE drop.name = "retrotransposon de-repression"
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

// Merge Function: reverse transcriptase activity -> Reverse transcriptase activity
MATCH (keep:Function)
WHERE keep.name = "Reverse transcriptase activity"
MATCH (drop:Function)
WHERE drop.name = "reverse transcriptase activity"
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

// Merge Function: Reverse transcription -> reverse transcription
MATCH (keep:Function)
WHERE keep.name = "reverse transcription"
MATCH (drop:Function)
WHERE drop.name = "Reverse transcription"
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

// Merge Function: Somatic expression -> somatic expression
MATCH (keep:Function)
WHERE keep.name = "somatic expression"
MATCH (drop:Function)
WHERE drop.name = "Somatic expression"
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

// Merge Function: somatic L1 insertion -> Somatic L1 insertion
MATCH (keep:Function)
WHERE keep.name = "Somatic L1 insertion"
MATCH (drop:Function)
WHERE drop.name = "somatic L1 insertion"
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

// Merge Function: Somatic L1 retrotransposition -> somatic L1 retrotransposition
MATCH (keep:Function)
WHERE keep.name = "somatic L1 retrotransposition"
MATCH (drop:Function)
WHERE drop.name = "Somatic L1 retrotransposition"
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

// Merge Function: Somatic mosaicism -> somatic mosaicism
MATCH (keep:Function)
WHERE keep.name = "somatic mosaicism"
MATCH (drop:Function)
WHERE drop.name = "Somatic mosaicism"
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

// Merge Function: Stress granule formation -> stress granule formation
MATCH (keep:Function)
WHERE keep.name = "stress granule formation"
MATCH (drop:Function)
WHERE drop.name = "Stress granule formation"
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

// Merge Function: target-site primed reverse transcription -> Target-site primed reverse transcription
MATCH (keep:Function)
WHERE keep.name = "Target-site primed reverse transcription"
MATCH (drop:Function)
WHERE drop.name = "target-site primed reverse transcription"
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

// Merge Function: Transcriptional interference -> transcriptional interference
MATCH (keep:Function)
WHERE keep.name = "transcriptional interference"
MATCH (drop:Function)
WHERE drop.name = "Transcriptional interference"
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

// Merge Function: Transcriptional silencing -> transcriptional silencing
MATCH (keep:Function)
WHERE keep.name = "transcriptional silencing"
MATCH (drop:Function)
WHERE drop.name = "Transcriptional silencing"
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

// Merge Function: Tumorigenesis -> tumorigenesis
MATCH (keep:Function)
WHERE keep.name = "tumorigenesis"
MATCH (drop:Function)
WHERE drop.name = "Tumorigenesis"
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

// Merge Function: upregulation -> Upregulation
MATCH (keep:Function)
WHERE keep.name = "Upregulation"
MATCH (drop:Function)
WHERE drop.name = "upregulation"
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
