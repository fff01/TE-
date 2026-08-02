# Figure 2 Contract: Database Content and Graph Schema

## Five-Point Contract

- **Core conclusion:** the current TE-KG snapshot links 225 TE nodes to a broad
  biomedical entity schema through 12,444 directed, PMID-bearing biological
  relations, alongside explicit taxonomy structures.
- **Evidence chain:** reproducible Neo4j label counts -> relationship-property
  audit -> schema grouping -> taxonomy summary.
- **Archetype:** asymmetric mixed-modality figure with one schema panel and
  subordinate quantitative panels.
- **Backend:** resolve before plotting.
- **Journal/export:** source CSV and query file retained; PDF/SVG master;
  colour-safe palette; 300-dpi colour TIFF.

## Proposed Panels

| panel | content | statistic or definition |
| --- | --- | --- |
| A | simplified TE-centred graph schema | 11 biomedical entity categories; Paper and taxonomy nodes shown separately |
| B | node counts by label | live `tekg3` snapshot dated 2026-07-31 |
| C | biological relation and provenance completeness | 12,444 directed `BIO_RELATION`; predicate, PMID list, and support-count fields present on all current relations |
| D | taxonomy summary | 225 TEs; 192 with taxonomy class; runtime summary definitions retained |

## Statistical Contract

These are database census counts, not samples from a population. No inferential
test, confidence interval, or significance language is appropriate. The query,
snapshot date, database name, direction semantics, and exclusions must be in
the legend or source-data note.

## Integrity Risks

- using the undirected traversal count of 24,748, which double-counts directed
  relationships;
- treating 276 Browse entries as the same unit as 225 Neo4j TE nodes;
- counting Paper or taxonomy labels among the 11 biomedical entity categories;
- making causal claims from literature predicates.

