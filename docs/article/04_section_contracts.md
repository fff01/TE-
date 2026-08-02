# TE-KG Manuscript Section Contracts

Last reviewed: 2026-07-31

Each section may be drafted only from approved rows in
`02_evidence_table.md`. `AUTHOR_INPUT_NEEDED` markers belong in working files,
not in submission prose.

## Title

- Purpose: name the resource and its literal category.
- Inputs: locked central thesis and target-journal fit.
- Allowed claims: human TE database/resource, knowledge graph, integrated
  evidence layers.
- Forbidden claims: first, unique, comprehensive, definitive, intelligent
  discovery platform unless separately verified.
- Gate: every title term is defined and supported by the manuscript.

## Abstract

- Purpose: state the problem, resource, verified content, principal workflows,
  bounded evaluation/use cases, availability, and conclusion.
- Inputs: all locked sections, final counts, final availability statement.
- Allowed claims: only claims already verified in the main text.
- Forbidden claims: citations, new numbers, new limitations, and inflated
  impact language.
- Gate: standalone, internally consistent, and compliant with current
  *Database* abstract requirements.

## Introduction

- Purpose: establish the biological/database problem, summarize related
  resources, define the unresolved integration gap, and state TE-KG's specific
  response.
- Inputs: verified literature map, comparison table, argument map.
- Allowed claims: well-supported TE biology context, documented limitations of
  fragmented evidence access, evidence-backed contribution statement.
- Forbidden claims: literature laundry lists, feature marketing, unsupported
  novelty, implementation details.
- Required items: citations to primary TE resources and representative
  biological database/knowledge-graph work.
- Gate: the gap follows from the cited comparison rather than from assertion.

## Data Collection and Integration

- Purpose: describe each source, its role, provenance, filtering, extraction,
  normalization, taxonomy integration, and versioning.
- Inputs: data-source register, source versions/licences, literature pipeline
  records, database-content inventory.
- Allowed claims: verified source roles, final paper count, entity schema,
  curation steps, separate evidence layers.
- Forbidden claims: unresolved intermediate counts, unverified model version,
  hidden manual decisions, conflated RMSK/RepBase roles.
- Required items: Fig. 1 architecture/provenance; Table 1 content snapshot;
  source citations.
- Gate: another group can understand what was collected, transformed, retained,
  and excluded, even if proprietary source redistribution is restricted.

## Expression and Co-expression Processing

- Purpose: describe matrices, sample contexts, preprocessing, feature
  annotation, correlation testing, FDR control, module detection, sensitivity
  checks, and display-subgraph selection.
- Inputs: data audit, method implementation, parameter scans, retained result
  tables, statistics review.
- Allowed claims: context-specific correlation and module results.
- Forbidden claims: regulation, causality, pathway validation, or direct
  cross-context equivalence.
- Required items: matrix/sample table; method-flow panel; sensitivity or
  quality summary; accession citations.
- Gate: samples, transformations, filters, correlation method, multiplicity
  correction, random seed, module parameters, and display caps are reproducible.

## System Architecture and Implementation

- Purpose: explain how the evidence layers are stored and served reliably.
- Inputs: current architecture, API/runtime matrix, deployment dependencies,
  version table.
- Allowed claims: Neo4j/MySQL responsibility split, PHP API mediation,
  client-side visualization architecture, bounded intelligent retrieval.
- Forbidden claims: technology as novelty, obsolete database names, raw files
  as runtime truth, exhaustive implementation narration.
- Required items: compact architecture panel or supplementary implementation
  table.
- Gate: all named components correspond to current runtime code.

## Database Content and Web Functionality

- Purpose: quantify the resource and explain its principal scientific access
  workflows.
- Inputs: dated database inventory, runtime-feature matrix, screenshots or
  workflow figures with contracts.
- Allowed claims: verified counts and demonstrated functions.
- Forbidden claims: page-by-page tour, unsupported usability claims, treating
  Browse count as Neo4j count, or treating display catalogs as full datasets.
- Required items: Fig. 2 content/schema; Fig. 3 workflow; Table 1 snapshot.
- Gate: every count has source, query, date, and definition; every workflow
  answers a research question.

## Evaluation and Use Cases

- Purpose: demonstrate traceable use of the resource and report bounded quality
  checks.
- Inputs: complete use-case evidence chains, automated checks, manual review
  records, current evaluation register.
- Allowed claims: case-specific retrieval, graph/path evidence, expression or
  co-expression observations, explicitly scoped QA outcomes.
- Forbidden claims: current accuracy from historical Agent totals, biological
  discovery without external validation, or absence claims from failed queries.
- Required items: at least two worked scientific workflows; one quantitative or
  auditable validation table; optional bounded Agent/DeepThink example.
- Gate: a reader can reproduce each route from input to evidence and understand
  its limitations.

## Discussion

- Purpose: interpret the resource contribution relative to existing databases,
  explain strengths and boundaries, and define realistic future work.
- Inputs: verified comparison, complete results/use cases, limitations.
- Allowed claims: evidence-backed integration value and explicit limitations.
- Forbidden claims: new results, generic future-work filler, acceptance or
  impact predictions, causal interpretation of correlations.
- Gate: every stated strength is paired with evidence and every material
  limitation has a consequence or mitigation.

## Conclusion

- Purpose: answer the central question in a short, bounded statement.
- Inputs: locked argument map and Discussion.
- Allowed claims: demonstrated resource contribution.
- Forbidden claims: new counts, new citations, new promises, or unsupported
  superlatives.
- Gate: no statement exceeds the evidence table.

## Data Availability

- Purpose: map each data/code asset to a durable location, identifier, licence,
  and access condition.
- Inputs: nature-data audit and final repository plan.
- Allowed claims: only active, tested public locations.
- Forbidden claims: `available on request` when a suitable repository exists;
  local filesystem paths as public availability.
- Gate: every manuscript dataset and script has an accessible destination or a
  justified restriction.

## Code Availability

- Purpose: identify the release corresponding to the manuscript.
- Inputs: tagged repository release, licence, dependency and deployment notes.
- Gate: a public reader can locate the exact submitted version.

## Declarations

- Funding, conflict of interest, acknowledgements, author list, affiliations,
  ORCID IDs, and corresponding-author details require author input.
- Author Contributions is deliberately deferred and must remain absent from the
  first working draft until the author supplies assignments.

