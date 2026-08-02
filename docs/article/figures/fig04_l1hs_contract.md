# Figure 4 Contract: Bounded L1HS Cross-Layer Use Case

## Five-Point Contract

- **Core conclusion:** a user can assemble complementary, source-traceable L1HS
  evidence in TE-KG without treating literature association, representative
  location, consensus/reference sequence, expression, and co-expression as the
  same type of finding.
- **Evidence chain:** entity resolution -> classification and sequence record ->
  representative genomic record -> expression profiles -> approved
  context-specific co-expression network -> PMID-linked literature relations.
- **Archetype:** asymmetric mixed-modality figure.
- **Backend:** resolve before quantitative plotting.
- **Journal/export:** every panel linked to a frozen source extract; PDF/SVG
  master and 300-dpi colour TIFF.

## Proposed Panels

| panel | evidence layer | required output | current status |
| --- | --- | --- | --- |
| A | identity/classification | exact TE identifier, class/order/family, and source role | pending frozen extract |
| B | sequence and location | clearly labelled consensus/reference sequence summary and representative location | pending frozen extract |
| C | expression | context-labelled L1HS profile with sample units and no cross-context inferential claim | pending source table |
| D | co-expression | one approved network or compact neighbourhood with correlation/FDR thresholds | runtime example exists; manuscript extract pending |
| E | graph/literature | one or two non-causal biomedical relations with verified PMID links | pending case selection |
| F | cited answer | short user-readable synthesis that distinguishes the five evidence types | writing-layer behavior implemented; final case pending |

## Pass Criteria

- Every displayed fact can be recovered through a recorded query or API call.
- Every PMID resolves to the cited paper and supports the adjacent relation.
- Expression and co-expression contexts are named precisely.
- The text says `associated with` or `correlated with` where appropriate.
- No internal flags, plugin names, or evidence-routing vocabulary appear.
- Missing layers are reported as missing rather than filled by general TE
  knowledge.

## Failure Rule

If a complete evidence chain cannot be frozen reproducibly, Figure 4 is reduced
to the supported layers or replaced by two narrower use cases. It must not be
kept by supplementing runtime gaps with model-generated biological prose.

