# TE-KG Manuscript Table Plan

Last reviewed: 2026-08-01

## Main Tables

| table | purpose | primary inputs | status |
| --- | --- | --- | --- |
| Table 1 | Dated database content snapshot with unit definitions | Neo4j label/relation queries, Browse API, taxonomy API, co-expression catalogue | counts verified; reproducible export file pending |
| Table 2 | Data sources, biological role, accession/release, processing, runtime destination, and limitation | data-source register and source manifests | partial because several versions/licences and one expression subset remain unresolved |
| Table 3 | Feature and evidence-layer comparison with representative TE resources | literature map and live-resource checks | provisional dimensions fixed; cell-level verification pending |

## Supplementary Tables

| table | purpose | status |
| --- | --- | --- |
| Table S1 | Full node-label counts and relation-property completeness | verified runtime snapshot; export pending |
| Table S2 | Expression matrices, sample counts, metadata matching, and exclusions | verified with disclosed normal-tissue mismatches; release manifest pending |
| Table S3 | Co-expression preprocessing, thresholds, feature annotation, context counts, and display tiers | partial; direct artifact audit needed for feature counts |
| Table S4 | Literature search, filtering, extraction, normalization, and manual audit record | blocked by inconsistent intermediate counts and missing sampled records |
| Table S5 | Runtime capabilities and verification evidence | verified/partial by feature |
| Table S6 | Agent/DeepThink evaluation cases and user-facing quality criteria | historical records only; quantitative current claim not approved |
| Table S7 | Downloadable artifacts, file descriptions, licences, and release identifiers | ten runtime files verified; public repository/licence fields missing |

## Table 1 Unit Contract

Table 1 must never place heterogeneous counts under a generic `records` label.
Approved row labels include:

- Neo4j TE nodes: 225;
- Neo4j Paper nodes: 2,308;
- directed Neo4j biological relations: 12,444;
- Browse catalogue entries: 276;
- taxonomy TEs with a class: 192 of 225;
- expression samples: 205 normal tissue, 307 normal primary cell, and 646
  cancer cell line;
- approved searchable co-expression catalogue entries: 285 TE and 499 Gene;
- currently exposed download files: 10.

Every row requires `definition`, `source/query`, `snapshot date`, and
`manuscript boundary` columns.

## Comparison Table Rules

- Use descriptive cells such as `human, L1HS-specific` or `PMID-linked
  literature relations`; avoid ticks without definitions.
- Do not total features or rank resources.
- Mark `not reported` separately from `absent`.
- Cite the publication supporting each row and record the date of any live-site
  check.
- State specialist depth explicitly where a comparator exceeds TE-KG, including
  insertion-polymorphism resolution and single-cell expression coverage.

