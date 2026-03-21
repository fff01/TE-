// Demo 1: LINE-1 subfamily lineage
MATCH (sub:TE)-[r:SUBFAMILY_OF]->(line1:TE {name: 'LINE-1'})
RETURN sub, r, line1
ORDER BY sub.name;

// Demo 2: Functions linked to LINE-1
MATCH (te:TE {name: 'LINE-1'})-[r:BIO_RELATION]->(f:Function)
RETURN te, r, f
ORDER BY r.predicate, f.name
LIMIT 80;

// Demo 3: Diseases linked to LINE-1
MATCH (te:TE {name: 'LINE-1'})-[r:BIO_RELATION]->(d:Disease)
RETURN te, r, d
ORDER BY r.predicate, d.name
LIMIT 80;

// Demo 4: Papers that report LINE-1
MATCH (p:Paper)-[r:EVIDENCE_RELATION]->(te:TE {name: 'LINE-1'})
RETURN p, r, te
ORDER BY p.name
LIMIT 50;

// Demo 5: Papers that report L1HS
MATCH (p:Paper)-[r:EVIDENCE_RELATION]->(te:TE {name: 'L1HS'})
RETURN p, r, te
ORDER BY p.name
LIMIT 50;

// Demo 6: Path from LINE-1 to Disease through Function
MATCH path = (te:TE {name: 'LINE-1'})-[r1:BIO_RELATION]->(f:Function)-[r2]->(d:Disease)
RETURN path
LIMIT 40;

// Demo 7: LINE-1 neighborhood for visualization
MATCH (te:TE {name: 'LINE-1'})
OPTIONAL MATCH (sub:TE)-[rs:SUBFAMILY_OF]->(te)
OPTIONAL MATCH (te)-[rb:BIO_RELATION]->(other)
RETURN te, sub, rs, rb, other
LIMIT 100;

// Demo 8: One paper and the entities it reports
MATCH (p:Paper)-[r:EVIDENCE_RELATION]->(entity)
WHERE p.name = "A 3' Poly(A) Tract Is Required for LINE-1 Retrotransposition."
RETURN p, r, entity;
