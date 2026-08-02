# TE-KG Manuscript Figure Plan

Last reviewed: 2026-08-01

The main figures are organized around the paper's argument rather than the
website's page structure. Four main figures are proposed. Figure 4 remains
conditional on completing a reproducible L1HS evidence chain.

| figure | unique job | archetype | evidence status | contract |
| --- | --- | --- | --- | --- |
| Figure 1 | Show how heterogeneous sources remain traceable through ingestion, storage, and access. | schematic-led composite | partial | `fig01_architecture_contract.md` |
| Figure 2 | Define the graph schema and quantify the current resource snapshot without conflating different catalogues. | asymmetric mixed-modality figure | verified for current counts | `fig02_content_contract.md` |
| Figure 3 | Demonstrate connected scientific access routes from a TE name, relation, path, expression profile, or question. | schematic-led composite | partial | `fig03_workflows_contract.md` |
| Figure 4 | Demonstrate one bounded L1HS cross-layer use case and preserve the boundary between association, location, sequence, expression, and co-expression evidence. | asymmetric mixed-modality figure | incomplete | `fig04_l1hs_contract.md` |

## Supplementary Figure Candidates

- Supplementary Figure S1: literature acquisition, constrained extraction,
  normalization, and curation detail, conditional on resolving intermediate
  counts and validation records.
- Supplementary Figure S2: co-expression preprocessing, threshold scan, module
  construction, and display-subgraph selection.
- Supplementary Figure S3: representative screenshots for Download and
  session-scoped Agent/DeepThink follow-up behavior.
- Supplementary Figure S4: taxonomy Tree/Graph presentation and class legend,
  only if it supports a specific classification workflow in the final text.

## Shared Integrity Rules

- Every count carries a snapshot date and definition.
- Neo4j TE counts, Browse catalogue counts, taxonomy counts, and co-expression
  searchable entries are displayed as different units.
- Co-expression edges are labelled as correlations, never regulatory links.
- Graph literature relations retain PMID access and do not imply causality.
- Representative genomic locations and consensus/reference sequences are not
  described as exhaustive copy-level records.
- Screenshots show a scientific action and outcome, not a decorative gallery.
- Source tables, commands, and crop/annotation decisions are retained beside
  final figure assets.
- Final export targets are editable PDF/SVG plus 600-dpi line art or 300-dpi
  colour/greyscale TIFF, consistent with current *Database* instructions.

## Production Dependency

No quantitative plotting starts until the `nature-figure` backend preference
is explicitly resolved. Contracts and source tables may be prepared before
that decision. The chosen backend must then be used exclusively for plotting,
preview, export, and visual QA.

