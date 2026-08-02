# TE-KG Manuscript Scope

Last reviewed: 2026-07-31

## Submission Target

- Journal: *Database: The Journal of Biological Databases and Curation*.
- Article type: database/resource research article, subject to confirmation in
  the journal submission system.
- Primary language: English.
- Primary authoring format: LaTeX using the supported OUP authoring template.
- Secondary deliverable: a Word version generated from the scientifically
  locked LaTeX manuscript and visually verified before delivery.
- Citation style: numbered references using the journal-supported OUP settings.

## Intended Readers

The manuscript is written for researchers who study transposable elements,
genome biology, disease associations, transcriptomics, biological databases,
and knowledge graphs. It should remain understandable to a biological database
user who is not a PHP, Neo4j, MySQL, G6, or large-language-model developer.

## Manuscript Boundary

The paper will present TE-KG as a human transposable-element resource that
connects five evidence layers:

1. literature-derived TE and biomedical entity relations;
2. TE classification and identity;
3. representative genomic annotations and reference sequences;
4. expression profiles in three biological context classes; and
5. context-specific TE/gene co-expression networks.

The paper will also describe the web interfaces that expose these layers and a
bounded intelligent question-answering interface that retrieves evidence from
them. The manuscript is a resource paper, not a page-by-page user manual and
not a claim that the intelligent interface performs autonomous scientific
reasoning.

## Principal Contribution Candidates

1. A literature-linked knowledge graph centered on human TEs and associated
   biomedical entities, with paper-level provenance retained in the graph.
2. Integration of classification, representative sequence, genomic location,
   expression, and literature evidence around TE identities while preserving
   the boundaries between those evidence types.
3. Context-specific TE/gene co-expression networks derived independently for
   normal tissue, normal primary cell, and cancer cell line datasets.
4. Connected search, browse, path, graph, expression, download, and evidence-
   bounded question-answering workflows for accessing the resource.

These are contribution candidates rather than novelty claims. Their novelty
relative to prior TE databases must be established through verified literature
comparison before the Introduction and Discussion are locked.

## Explicit Exclusions

- Do not claim that a literature-derived relation proves causality.
- Do not describe co-expression as regulation, activation, inhibition, or
  mechanism.
- Do not describe a representative RMSK locus as every genomic copy of a TE.
- Do not describe a RepBase consensus sequence as a copy-specific sequence.
- Do not claim complete coverage of human TEs, diseases, publications, loci,
  sequences, or expression contexts.
- Do not report unresolved intermediate PubMed filtering counts.
- Do not convert the historical 36-question Agent/DeepThink evaluation into an
  accuracy claim for the current system.
- Do not present implementation technology as the primary scientific
  contribution.
- Do not write the Author Contributions section until the authors provide the
  contribution assignments.

## Required Deliverables

### Foundation and evidence

- six researchwrite foundation files;
- data-source and database-content inventories;
- runtime-feature and evaluation registers;
- verified literature map and BibTeX library;
- argument, section, figure, and table contracts.

### Manuscript

- complete English LaTeX manuscript in the supported OUP format;
- numbered reference library;
- figure and table captions with evidence mappings;
- supplementary material when justified by the evidence inventory;
- Word version produced after LaTeX content is locked;
- cover letter and required initial-submission declarations.

## Submission Readiness

The first-submission manuscript is ready only when:

1. every central factual and quantitative claim maps to a verified evidence
   row;
2. all external citations support their adjacent claims and pass metadata
   verification;
3. data-source versions, licences, access dates, public release locations, and
   code/data availability statements are complete;
4. figures and tables are generated from retained source data or have a clear
   screenshot provenance trail;
5. all placeholders except the deliberately deferred Author Contributions
   section have been resolved;
6. the LaTeX source compiles cleanly in the supported OUP template;
7. the Word derivative has been rendered and visually inspected page by page;
8. Database-specific fit, scientific content, statistics, references, language,
   and submission packaging have passed their documented QA gates.

## Author Input Still Needed

- `AUTHOR_INPUT_NEEDED`: final title preference.
- `AUTHOR_INPUT_NEEDED`: complete author list, order, affiliations, ORCID IDs,
  and corresponding-author details.
- `AUTHOR_INPUT_NEEDED`: funding statement and grant identifiers.
- `AUTHOR_INPUT_NEEDED`: conflict-of-interest declaration.
- `AUTHOR_INPUT_NEEDED`: public production URL and access policy.
- `AUTHOR_INPUT_NEEDED`: durable code and data repository locations, licences,
  and release identifiers.
- `AUTHOR_INPUT_NEEDED`: exact versions and acquisition dates for RMSK,
  RepBase-derived, ICD-11, and expression source materials where not already
  encoded in the repository.
- `AUTHOR_INPUT_NEEDED`: Author Contributions, intentionally deferred by the
  author on 2026-07-31.

