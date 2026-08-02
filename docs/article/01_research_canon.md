# TE-KG Research Canon

Last reviewed: 2026-07-31

This file contains manuscript-relevant facts and boundaries that are supported
by current repository evidence or a reproducible runtime check. Writing must
not silently strengthen, weaken, or replace these facts.

## Canon Status Vocabulary

- `VERIFIED`: supported by current code, current data, or a successful runtime
  query recorded below.
- `PARTIAL`: usable only with the stated limitation.
- `UNRESOLVED`: excluded from manuscript claims until additional evidence is
  supplied.

## Resource Identity

- `VERIFIED`: TE-KG is a local PHP and browser-JavaScript application backed by
  Neo4j and MySQL. Current architecture: `docs/architecture/current_system.md`.
- `VERIFIED`: the active Neo4j database is `tekg3`; runtime configuration is
  resolved through `api/runtime_config.php` and `api/config.local.php`.
- `VERIFIED`: TE-KG integrates literature evidence, TE taxonomy, representative
  genomic annotations, reference sequences, expression summaries, and TE/gene
  co-expression results. Evidence synthesis: `docs/architecture/database_overview.md`.

## Literature-Derived Knowledge Graph

- `VERIFIED`: the current Neo4j `tekg3` runtime contains 2,308 `Paper` nodes and
  12,444 directed `BIO_RELATION` relationships. Provenance: live read-only
  Neo4j queries run on 2026-07-31 through `scripts/checks/harness_lib.py`.
- `VERIFIED`: every current `BIO_RELATION` has `predicate`, `pmids`, and
  `support_pmid_count` properties. The relationship property audit was run on
  2026-07-31 through the same read-only query helper.
- `VERIFIED`: the current graph contains the following biomedical entity-node
  counts: 3,683 Function, 1,280 Gene, 1,089 Protein, 676 Disease, 588 RNA,
  377 Mutation, 293 Pharmaceutical, 67 Toxin, 26 Lipid, 23 Peptide, and 12
  Carbohydrate nodes. These are runtime label counts, not counts of distinct
  biological concepts after an external ontology audit.
- `VERIFIED`: the final retained literature set contains 2,308 papers. This is
  also consistent with `docs/method_english.docx` and
  `docs/architecture/database_overview.md`.
- `PARTIAL`: literature was retrieved from PubMed with TE-focused MeSH and
  free-text terms plus a human restriction, and the method document reports
  coverage through 2026-04-13.
- `PARTIAL`: DeepSeek-V3 was used for constrained entity and relation extraction,
  followed by whitelist filtering, name normalization, and manual curation.
  The exact model endpoint/version and reproducible extraction environment must
  be recovered before final Methods text is locked.
- `UNRESOLVED`: intermediate retrieval and filtering counts in
  `docs/method_english.docx` do not reconcile. Only the final 2,308-paper count
  is approved for public manuscript use.
- `PARTIAL`: the method document reports three manual samples of 50 records and
  correct-decision rates of 100%, 94%, and 96%, with reported confidence
  intervals. The sampled record lists, random seed, and arithmetic definitions
  must be recovered before these values are used as formal validation results.

## Knowledge-Graph Content

- `VERIFIED`: a read-only runtime query on 2026-07-31 returned 225 `TE` nodes.
- `VERIFIED`: the current graph also contains 767 `DiseaseCategory` nodes and
  the classification relations `HAS_SUBCATEGORY` (744), `CLASSIFIED_AS` (436),
  and `SUBFAMILY_OF` (72).
- `VERIFIED`: graph relations are evidence records bounded by their source
  papers, predicates, and curation. They are not automatic proof of causality.
- `PARTIAL`: disease classification was designed with reference to ICD-11 and
  includes an `Others` branch. The exact ICD-11 release and reuse conditions are
  not yet recorded in the manuscript workspace.

## TE Identity, Classification, Genome, and Sequence

- `VERIFIED`: RMSK/UCSC-style data in `data/rmsk.txt` supply genomic coordinates,
  strand, repeat name, repeat class, and repeat family.
- `VERIFIED`: RepBase-derived data in `data/raw/TE_Repbase.txt` supply TE
  identity, descriptions, keywords, aliases, taxonomic information, and
  consensus/reference sequences.
- `VERIFIED`: RMSK genomic locations and RepBase consensus sequences are
  distinct evidence layers and must not be conflated.
- `VERIFIED`: a live taxonomy summary query on 2026-07-31 returned 225 TE nodes,
  192 with a taxonomy class, 188 standard leaves, and 154 entries included in
  the homepage chart. Runtime endpoint: `api/taxonomy.php?view=summary`.
- `VERIFIED`: the Browse catalog is a separate versioned MySQL catalog. A live
  query on 2026-07-31 returned version
  `browse-20260727T104133Z-7dc80d8143dd-35ff7f` with 276 rows.
- `UNRESOLVED`: exact upstream RMSK and RepBase release identifiers, acquisition
  dates, licences, and redistribution permissions must be confirmed.

## Expression Data

- `VERIFIED`: the active expression asset root is
  `data/bulk_expression_web/`.
- `VERIFIED`: expression is separated into normal tissue, normal primary cell,
  and cancer cell line biological contexts. The existing runtime identifier
  `normal_cell_line` refers to the normal primary cell dataset and must not be
  silently renamed in code.
- `VERIFIED`: source accessions recorded for the three contexts are
  `E-MTAB-1733` and `E-MTAB-2836` for normal tissue, `SRP013565` for normal
  primary cells, and `PRJNA523380` for cancer cell lines.
- `VERIFIED`: audited matrix dimensions are 37,868 x 206 for normal tissue,
  37,868 x 308 for normal primary cells, and 37,868 x 647 for cancer cell lines.
  The first column is the feature identifier, leaving 205, 307, and 646
  expression samples, respectively. Provenance:
  `docs/coexpression/data_audit.md` and the active matrix files.
- `VERIFIED`: the matrices contain mixed gene and TE/repeat features and must
  not be described as gene-only matrices.
- `PARTIAL`: normal-tissue metadata contain duplicate run identifiers, and five
  matrix samples lack matching metadata (`ERR579126`, `ERR579137`, `ERR579144`,
  `ERR579145`, and `ERR579154`).
- `UNRESOLVED`: source-study citations, preprocessing details, units, and any
  batch-handling rules require source-level verification before final Methods.

## Co-expression

- `VERIFIED`: contexts are analyzed separately after
  `log2(normalized_count + 1)` transformation.
- `VERIFIED`: the active analysis uses Spearman correlation, an edge threshold
  of `abs(r) >= 0.4` and `FDR <= 0.05`, positive edges for module detection,
  NetworkX Louvain communities, random seed 42, and resolution 1.8.
- `VERIFIED`: the active version is `v1_abs0.4_fdr0.05_res1.8`.
- `VERIFIED`: production display networks are served from MySQL
  `coexpression_*` tables through `api/coexpression.php`; offline files under
  `data/coexpression/` are provenance and importer inputs rather than browser
  runtime sources.
- `VERIFIED`: a live catalog query on 2026-07-31 returned 285 TE entries and 499
  Gene entries across three contexts. This is a display-catalog count, not the
  number of all features in the source matrices.
- `VERIFIED`: the offline display recommendation table contains 849 TE-context
  rows: 17 `core_case`, 287 `high_confidence`, 542 `searchable_all`, and 3
  `not_recommended_default` rows. Provenance:
  `docs/coexpression/display_tier_recommendations.md`.
- `VERIFIED`: browser display graphs are capped at 50 nodes and 150 edges.
- `VERIFIED`: co-expression indicates statistical association only. Modules
  are network communities and are not validated pathways or regulatory units.
- `PARTIAL`: parameter scans support the selected display standard, but a final
  manuscript-facing sensitivity table and retained source tables still need to
  be assembled.

## User-Facing Services

- `VERIFIED`: the resource currently exposes Home, Browse, Search/detail, Path,
  Graph, Expression, Agent/DeepThink, Download, and About interfaces.
- `VERIFIED`: Graph contains separate Knowledge Graph and Co-expression modes.
  Knowledge Graph and Co-expression use separate APIs, state, renderers, and
  interpretation rules.
- `VERIFIED`: taxonomy can be viewed as a Tree or a force-directed Canvas Graph
  while retaining the current taxonomy data contract.
- `VERIFIED`: Path reports graph connections. A path is not automatically a
  biological pathway or mechanism.
- `VERIFIED`: Download currently exposes ten public files: six expression files,
  two graph files, and two taxonomy files. Provenance: `download.php`.

## Agent and DeepThink

- `VERIFIED`: DeepThink follows four visible stages: Understanding, Planning,
  Executing, and Writing.
- `VERIFIED`: Agent follows six visible stages: Understanding, Planning,
  Collecting, Executing, Integrating, and Writing.
- `VERIFIED`: the registry exposes twelve plugin roles covering entity
  resolution, navigation, graph retrieval and analytics, Cypher exploration,
  literature retrieval and reading, taxonomy, expression, genome, sequence,
  and citation normalization.
- `VERIFIED`: both modes support bounded multi-turn references within the
  current browser-page session. A reload or new tab does not recover the prior
  session identifier.
- `VERIFIED`: intelligent answers are bounded by retrieved evidence and must
  not invent graph facts, PMIDs, URLs, expression values, genomic loci, or
  sequence details.
- `PARTIAL`: the historical 36-question evaluation comprised 34 English and 2
  Chinese questions and recorded 16 pass, 10 partial, and 10 fail judgments.
  Several scoped failures were subsequently fixed, so those totals describe the
  historical run, not current production quality.
- `UNRESOLVED`: the remaining disease-qualified literature-retrieval weakness
  and the Sequence structure-hint parser issue must be resolved or disclosed
  before a manuscript claims broad answer reliability.

## Technology Boundary

- `VERIFIED`: Neo4j stores the evidence graph and canonical TE taxonomy; MySQL
  stores the Browse catalog, expression summaries, and co-expression display
  data; PHP APIs mediate browser access.
- `VERIFIED`: browser interfaces use JavaScript visualization components,
  including G6 for several graph surfaces and Canvas for taxonomy Graph mode.
- `PARTIAL`: implementation versions and deployment requirements have not yet
  been consolidated into a reproducible environment table.

## Prohibited Claim Transformations

- association -> causation;
- correlation -> regulation;
- representative locus -> exhaustive insertion catalogue;
- consensus sequence -> sequence of every genomic copy;
- runtime display catalog -> complete upstream dataset;
- successful tool call -> scientifically correct answer;
- historical evaluation rate -> current accuracy;
- implemented feature -> demonstrated scientific utility.

