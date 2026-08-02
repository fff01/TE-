# Figure 1 Contract: Provenance-Aware TE-KG Architecture

## Five-Point Contract

- **Core conclusion:** TE-KG connects complementary human TE evidence layers
  while preserving their source roles and serving them through bounded access
  workflows.
- **Evidence chain:** source registers -> processing and provenance -> Neo4j or
  MySQL runtime destinations -> user-facing graph, expression, co-expression,
  download, and question-answering routes.
- **Archetype:** schematic-led composite.
- **Backend:** not applicable to contract drafting; resolve before production.
- **Journal/export:** one-column-readable labels, editable text, PDF/SVG master,
  600-dpi line-art TIFF, white background.

## Proposed Panels

| panel | content | evidence | status |
| --- | --- | --- | --- |
| A | PubMed, RepBase-derived, RMSK-derived, and three expression-source groups | data-source register | partial because upstream versions/licences are missing |
| B | literature filtering/extraction/normalization and expression/co-expression processing | method record and co-expression docs | partial |
| C | Neo4j `tekg3`, MySQL catalogue/expression/co-expression, PHP APIs, browser clients | current architecture and runtime checks | verified |
| D | provenance anchors: PMID on biological relations, source/accession labels, versioned runtime outputs | live queries and API responses | verified/partial by layer |

## Expected Reader Takeaway

The database is not a single blended evidence table. It is a TE-centred set of
linked evidence layers whose provenance and interpretation remain visible.

## Integrity Risks

- drawing RepBase consensus sequences as locus-specific sequences;
- implying that every RMSK locus or expression observation is in Neo4j;
- presenting Agent/DeepThink as an autonomous validation system;
- including unresolved source versions as if final.

## Production Inputs Still Needed

- exact RepeatMasker and RepBase source releases, dates, and licences;
- exact SRP013565 subset and citation;
- locked terminology for `normal primary cell` in manuscript-facing labels;
- dated release identifiers for public code and data.

