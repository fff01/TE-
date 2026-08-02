# TE-KG Manuscript Style Guide

Last reviewed: 2026-07-31

## Voice and Stance

- Write precise scientific English for a biological database audience.
- Prefer direct statements with an explicit evidence subject.
- Use a conservative, resource-paper stance: describe what the database stores,
  how it was produced, what a user can retrieve, and what the evidence does not
  establish.
- Prefer quantified statements to adjectives.
- Keep paragraphs focused on one argumentative job.

## Canonical Names and Capitalization

- Database name: `TE-KG`.
- General term: `transposable element (TE)` at first use; `TE` thereafter.
- Journal name: *Database: The Journal of Biological Databases and Curation* at
  first formal mention; *Database* thereafter.
- Resource names: `RepeatMasker` when discussing the software/resource;
  `RMSK` only for the project data/table convention after definition;
  `RepBase` in prose, even where legacy filenames use `Repbase`.
- Graph database: `Neo4j`; relational database: `MySQL`.
- Visualization library: `G6`, used only when implementation detail is needed.
- Assistant modes: `Agent` and `DeepThink`; do not alternate with unapproved
  spellings such as `Deep Think` in manuscript prose.
- Current database identifier: `tekg3` in technical text only.

## Biological Context Terms

- Public prose: `normal tissue`, `normal primary cell`, and
  `cancer cell line`.
- Runtime identifier `normal_cell_line` may appear in Methods or supplementary
  implementation notes only, with an explicit explanation that it stores the
  normal primary cell dataset.
- Use `TE/gene co-expression network`, not `gene regulatory network`.
- Use `co-expression module` or `expression-associated module`, not `pathway`
  unless a separate enrichment result and its limits are described.

## Evidence Terms

- `literature-derived relation`: a relation extracted and curated from retained
  literature evidence.
- `association`: use when evidence does not justify a causal verb.
- `representative genomic locus`: a displayed RMSK-derived occurrence, not all
  copies.
- `consensus/reference sequence`: a RepBase-derived representative record, not
  a locus-specific genomic sequence.
- `expression profile`: measured values available in the current expression
  dataset.
- `co-expression`: statistical association under the stated method and context.
- `knowledge-graph path`: a stored graph connection, not automatically a
  biological pathway.

## Required Boundary Language

- Literature relations inherit the limitations of their source papers,
  extraction schema, and curation.
- Co-expression does not establish regulation or causality.
- A missing or failed runtime result is not evidence of biological absence.
- Display catalogs and bounded subgraphs are not the complete upstream data.
- Intelligent answers synthesize retrieved evidence and are not independent
  experimental validation.

## Prohibited Manuscript Vocabulary

Do not expose internal or developer-facing terms in the manuscript narrative or
user-facing answer examples, including:

- evidence walk;
- evidence package;
- support flag;
- `keyword_derived`;
- `association_not_causality`;
- plugin result envelope;
- routing confidence;
- fallback chain;
- hard-stop condition;
- internal claim ID outside tables or working notes.

Translate such concepts into ordinary scientific language or omit them.

## Prohibited or Restricted Claims

- Avoid `comprehensive`, `complete`, `definitive`, `unique`, `first`, `novel`,
  `accurate`, `robust`, `powerful`, and `intelligent` unless a defined comparison
  directly supports the word.
- Avoid `proves`, `causes`, `regulates`, `drives`, or `mechanism` for graph and
  co-expression observations unless primary evidence justifies the verb.
- Do not call an implementation test a biological validation.
- Do not call a successful LLM response a correct scientific answer without a
  separate evidence-based assessment.

## Numbers and Snapshots

- Give each database count a snapshot date and definition.
- Use commas in counts of 1,000 or more.
- Use `n = 50` for sample sizes and define the sampling unit.
- Use `95% CI` after defining confidence interval.
- Report correlation as Spearman's `r` or `rho` consistently after the final
  statistics audit; do not alternate notation casually.
- Report thresholds as `abs(r) >= 0.4` and `FDR <= 0.05` in source files; the
  final LaTeX version may use mathematical notation.

## Tense

- Methods: past tense for completed data processing; present tense for stable
  resource behavior and definitions.
- Database content: present tense with an explicit release/snapshot date.
- Use cases: past tense for the executed example, present tense for what the
  current interface displays.
- Discussion: present tense for interpretation; future tense only for specific
  planned work.

## Citations

- Use stable BibTeX keys from the first draft.
- Cite primary data resources, methods, and original studies directly.
- Place citations immediately after the supported claim.
- A citation that supports only the topic is insufficient.
- The Abstract must not contain citations unless current journal instructions
  explicitly allow them.

## LaTeX and Word Order

1. Lock scientific content in section-level LaTeX files.
2. Compile and review the OUP-formatted LaTeX manuscript.
3. Generate the Word derivative only after LaTeX content is stable.
4. Apply Word-specific layout fixes without changing scientific claims.
5. Render the Word document to page images and inspect every page.

## Placeholders

- Use `AUTHOR_INPUT_NEEDED` only in working Markdown or LaTeX comments.
- Do not place unresolved placeholders in a submission PDF or final Word file.
- Author Contributions is intentionally deferred and should not be drafted from
  inference.

