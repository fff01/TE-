# TE-KG Manuscript Workflow Using the Nature Skills

## Status and Scope

This document is the operating procedure for preparing the TE-KG manuscript for
*Database: The Journal of Biological Databases and Curation*. It defines how the
existing nature skills should be used, what each stage must produce, and which
quality gate must pass before work moves forward.

This document does not contain manuscript claims and is not a substitute for the
project fact base. The manuscript-specific facts, evidence, argument, drafts,
figures, reviews, and submission files will be maintained separately under
`docs/article/`.

The remaining manuscript work is divided into five phases:

1. establish the six foundation files;
2. inventory the verified TE-KG evidence;
3. build the argument and figure plan;
4. draft and lock the manuscript section by section;
5. run manuscript QA and prepare the submission package.

## Governing Rules

1. *Database* author instructions override generic Nature-style guidance.
2. Current runtime data, verified code behavior, reproducible checks, and
   authoritative source records override historical project Markdown.
3. Evidence precedes prose, argument precedes sections, and section contracts
   precede paragraphs.
4. No skill may invent a count, version, data source, accession, citation,
   statistical result, URL, repository, biological interpretation, or system
   capability.
5. Missing author facts are recorded as `AUTHOR_INPUT_NEEDED`; they are not
   guessed or silently omitted.
6. Scientific content is reviewed before language polishing.
7. The manuscript must describe a scientific resource, not provide a
   page-by-page product tour.
8. Quantified scope, provenance, validation, and worked use cases replace vague
   adjectives such as "comprehensive," "accurate," "novel," or "intelligent."
9. Agent and DeepThink are evidence-access interfaces within TE-KG. They must
   not dominate the manuscript or be described as autonomous scientific
   reasoning systems.
10. Durable decisions and verified facts must be written to `docs/article/` so
    that a later maintainer does not depend on chat history.

## Skill Roles

| Skill | Role in this manuscript | When to use | Do not use it for |
|---|---|---|---|
| `researchwrite` | Workflow spine and state manager | Foundation files, evidence table, argument map, section contracts, content QA | One-shot full-paper generation |
| `nature-academic-search` | Literature discovery and metadata collection | TE databases, biological knowledge graphs, expression resources, LLM database interfaces, related work | Claiming support from title-only matches |
| `nature-reader` | Full-paper, figure-aware close reading | The small set of papers that directly shapes the argument, methods, comparisons, or figures | Replacing a source with a summary-only note |
| `nature-writing` | Evidence-bounded section drafting and initial submission materials | Drafting one contracted section at a time; later preparing the initial submission package | Revision correspondence after peer review |
| `nature-citation` | Strict claim-to-citation support from Nature/CNS families | Selected broad biological background claims where that journal scope is useful | Finding all TE database, software, data-source, or methods references |
| `nature-figure` | Figure contracts, plotting, assembly, export, and visual QA | Quantitative panels, architecture figures, multi-panel manuscript figures, graphical abstracts | Plotting before the claim and evidence chain are fixed |
| `nature-statistics` | Statistical and evaluation-reporting audit | Agent evaluation, expression or co-expression analyses, figure legends, uncertainty and sample reporting | Inventing analyses or treating technical measurements as independent samples |
| `nature-data` | Data/code access plan and Data Availability package | Repository selection, dataset-to-location mapping, identifiers, licences, FAIR metadata | Inventing accession numbers or relying on unsupported "available on request" wording |
| `nature-polishing` | Final scientific English and paragraph-level clarity | After a section's claims, evidence, structure, and citations are locked | Repairing weak evidence or changing the strength of a claim |
| `nature-reviewer` | Adversarial pre-submission stress test | Full-draft or major-section review after content integration | Acting as the final authority on fit for *Database* |
| `nature-ref-verifier` | Bibliographic integrity audit | Final DOI, author, title, year, volume, page, and journal checks | Literature discovery or scientific support assessment |
| `nature-response` | Post-decision revision correspondence | Reviewer responses, revision cover letters, redline packages, appeals when justified | Initial submission materials |

`nature-reader`, `nature-writing`, `nature-polishing`, and related router-based
skills must load only the fragments required for the current task. The internal
`nature-shared` support package is never invoked directly.

For TE-KG, `nature-writing` and `nature-polishing` should normally use:

- `journal=generic`, because the target is *Database*, not a Nature journal;
- `paper_type=research`, with a methods- and resource-heavy outline derived
  from representative *Database* articles;
- `language=zh-to-en` when the source notes are Chinese, otherwise `language=en`.

## Workspace Contract

All manuscript materials remain under `docs/article/`.

```text
docs/article/
  README.md
  nature_skills_workflow.md
  database_journal_writing_examples.md
  00_scope.md
  01_research_canon.md
  02_evidence_table.md
  03_argument_map.md
  04_section_contracts.md
  05_style_guide.md
  sources/
  evidence/
  figures/
  tables/
  drafts/
  qa/
  submission/
```

Markdown files hold decisions, evidence mappings, reviews, and working notes.
Once the OUP template is installed, the canonical English manuscript prose
should live in LaTeX section files under `drafts/`, with stable BibTeX keys used
from the start. Chinese notes may remain in Markdown beside the relevant
section contract. The manuscript must be written and scientifically locked in
LaTeX first. A Word derivative is created only after the LaTeX content passes
its manuscript gates, then rendered and visually inspected with the `documents`
skill. Word-specific layout corrections must not change scientific claims.

## Phase 1: Establish the Six Foundation Files

### Objective

Create the controlled fact and argument environment required before manuscript
prose is drafted.

### Inputs

- official *Database* author instructions and OUP template requirements;
- `database_journal_writing_examples.md`;
- current TE-KG architecture and handoff documentation;
- current runtime configuration and verified system behavior;
- explicit author decisions about scope, claims, and exclusions.

### Skills

- Use `researchwrite` as the primary skill in compose mode.
- Use `nature-writing` only to assess manuscript structure or section purpose;
  do not draft prose in this phase.
- Use `nature-academic-search` only when a scope decision requires checking the
  external literature.

### Required Files

#### `00_scope.md`

Record the target journal, article type, intended readership, manuscript
boundary, principal contribution candidates, explicitly excluded claims, and
definition of submission readiness.

#### `01_research_canon.md`

Record only verified, durable facts: active databases, data sources and
versions, entity definitions, architecture, implemented capabilities, validated
evaluation results, known limitations, and approved terminology. Each fact must
carry a provenance pointer to code, data, a runtime check, or an authoritative
source.

#### `02_evidence_table.md`

Use one row per manuscript claim with these fields:

```text
claim_id | proposed_claim | claim_type | evidence_location | source_or_test |
citation_key | figure_or_table | strength | boundary | status
```

`status` is one of `verified`, `partial`, `missing`, or `rejected`. A rejected
claim remains visible so that it is not reintroduced later.

#### `03_argument_map.md`

Define the central problem, resource gap, TE-KG response, supporting evidence,
biological value, limitations, and conclusion. The map must distinguish the
database contribution from the implementation details.

#### `04_section_contracts.md`

For every planned section, record its purpose, required inputs, allowed claims,
forbidden claims, required figures/tables, citation needs, and validation gate.

#### `05_style_guide.md`

Fix terminology, capitalization, abbreviations, TE naming conventions, database
name, tense, citation conventions, user-facing wording, and prohibited internal
labels. It must state that development diagnostics and model-only vocabulary do
not appear in manuscript examples.

### Phase Gate

Phase 1 passes only when the six files agree on the manuscript boundary, use
the same terminology, contain no contradictory core claims, and expose all
missing author inputs. No Abstract, Introduction, or complete section draft is
allowed before this gate passes.

## Phase 2: Inventory Verified TE-KG Evidence

### Objective

Build the auditable evidence base for every quantitative, technical, and
biological statement the manuscript may make.

### Workstreams

#### A. Data provenance and database scope

Inventory every source name, version, licence, acquisition date, update method,
normalization rule, deduplication rule, and runtime destination. Record counts
for all node/entity types, relation types, TE classes, orders and families,
species, sequences, genomic locations, expression records, samples, tissues,
genes, diseases, pathways, and publications that are actually in scope.

#### B. Runtime and feature verification

For each manuscript-visible capability, record its current entry point, data
dependency, verification command or manual check, expected behavior, and known
boundary. Stable pages may be used as evidence without being refactored.

#### C. Evaluation evidence

Consolidate graph, co-expression, search, path, expression, Agent, and DeepThink
evaluation records. Distinguish fixed automated checks, human/user-facing
assessment, live-service tests, and anecdotal observations. Do not combine them
into one unsupported accuracy number.

#### D. Literature and comparison evidence

Use `nature-academic-search` to build focused groups for:

- TE databases and TE classification resources;
- biological databases and knowledge graphs;
- expression and co-expression resources;
- literature-linked or LLM-assisted biological database interfaces.

Use `nature-reader` only for papers that directly affect the manuscript's gap,
method, comparison table, evaluation design, or discussion. Store source-grounded
notes under `sources/` and preserve DOI, PMID, URL, and exact claim support.

Use `nature-citation` selectively for broad biological background statements
where a Nature/CNS-family source is desirable. It must not narrow the full
reference list to those journal families.

#### E. Data and statistical readiness

Use `nature-data` early to decide where processed data, downloadable tables,
code, and versioned releases will be made accessible. Use `nature-statistics`
to review the design and reporting of any quantitative comparison, evaluation
rate, expression analysis, or figure statistic before those numbers enter the
evidence table.

### Parallel Work

After the evidence-table schema and terminology are fixed, workstreams A-D may
run in parallel. Their outputs must be reconciled centrally before any count or
claim is marked `verified`. Repository planning and statistical review may also
run alongside the inventory, but their decisions must be reflected in the same
canon and evidence table.

### Outputs

- `evidence/data_source_register.md`
- `evidence/database_content_inventory.md`
- `evidence/runtime_feature_matrix.md`
- `evidence/evaluation_register.md`
- `evidence/literature_map.md`
- a maintained reference library with stable BibTeX keys
- updated `01_research_canon.md` and `02_evidence_table.md`

### Phase Gate

Phase 2 passes only when every central contribution has reproducible evidence,
every reported count has a defined source and date, every external comparison
has a verified citation, and known gaps are explicit. Claims that remain
`missing` cannot appear in the first draft.

## Phase 3: Build the Argument and Figure Plan

### Objective

Turn verified evidence into a coherent paper-level argument and a figure-first
presentation plan.

### Skills

- Use `researchwrite` to revise the argument map and section contracts.
- Use `nature-figure` to create a figure contract before plotting or assembling
  screenshots. Resolve and remember the Python/R backend when the first
  quantitative figure is built.
- Use `nature-statistics` for panels containing comparisons, rates, uncertainty,
  sample counts, or evaluation scores.
- Use `nature-writing` only for figure legends after the panel evidence and
  conclusion are fixed.

### Candidate Main Figures

1. data sources, processing, provenance, and overall TE-KG architecture;
2. graph schema and quantitative database composition;
3. principal scientific access workflows rather than a gallery of pages;
4. expression or co-expression use case;
5. graph exploration, path, or cross-source evidence use case;
6. bounded Agent/DeepThink evaluation and a user-readable example.

The final count may change. Each figure must earn its place by supporting one
manuscript-level conclusion.

### Figure Contract

Before production, every figure records:

```text
figure_id | conclusion | evidence_chain | data_source | panels | comparison |
statistics | expected_reader_takeaway | integrity_risks | export_formats
```

Screenshots must be cropped and annotated to support a scientific workflow.
They must not be used merely to prove that a page exists. Quantitative figures
must retain the source table and generation script.

### Outputs

- revised `03_argument_map.md`
- revised `04_section_contracts.md`
- `figures/figure_plan.md`
- one contract per proposed main or supplementary figure
- `tables/table_plan.md`
- a claim-to-figure/table map in `02_evidence_table.md`

### Phase Gate

Phase 3 passes only when the paper can be summarized as one defensible argument,
each main figure has a unique job, every panel maps to verified evidence, and no
section depends on a figure or analysis that has not been scoped.

## Phase 4: Draft and Lock the Manuscript Section by Section

### Objective

Produce an internally consistent English manuscript without allowing language
generation to outrun the evidence base.

### Drafting Order

Use this order:

1. Data collection and integration
2. System architecture and implementation
3. Database content and web functionality
4. Evaluation and use cases
5. Discussion
6. Introduction
7. Conclusion
8. Abstract
9. Title

The Abstract and Introduction are deliberately late because their scope and
claims depend on the completed evidence and results narrative.

### Section Cycle

Each section follows the same controlled cycle:

1. open its section contract;
2. collect only the approved evidence rows and citations;
3. run `nature-writing` for that section with `journal=generic` and the correct
   language mode;
4. check every sentence against the evidence table;
5. use `nature-academic-search`, `nature-reader`, or `nature-citation` only for a
   specifically identified citation gap;
6. run scientific and structural review;
7. mark unresolved facts as `AUTHOR_INPUT_NEEDED` outside the manuscript prose;
8. lock the section's scientific content;
9. run `nature-polishing` for English clarity without changing claim strength;
10. recheck citations, numbers, terminology, and cross-section consistency.

One-shot full-manuscript generation is prohibited. Separate sections may be
drafted in parallel only after their contracts, evidence rows, terminology, and
shared numerical facts are locked. The Introduction, Discussion, Abstract, and
Title remain integration tasks and should not be independently generated in
parallel.

### Citation Practice

- Use stable BibTeX keys while drafting; apply numbered formatting through the
  OUP `unsrt` bibliography style at compile time.
- Cite original databases, primary methods, and authoritative data sources
  directly.
- Do not cite a review when the sentence makes a claim that requires the
  original study.
- Do not retain a citation that supports only the topic but not the sentence.
- Record support strength and claim boundaries in the evidence table.

### Outputs

- section-level LaTeX drafts under `drafts/`
- figure legends and table notes linked to their evidence contracts
- updated citation library
- section review notes under `qa/sections/`
- a manuscript integration log recording locked and reopened sections

### Phase Gate

Phase 4 passes when every section satisfies its contract, every central claim
maps to evidence, terminology and numbers are consistent across sections,
citations support the exact adjacent claims, and no developer-only vocabulary
or unsupported causal language remains.

## Phase 5: Manuscript QA and Submission Package

### Objective

Stress-test the complete manuscript, resolve reviewer-facing risks, validate all
references and availability statements, and prepare a clean OUP submission.

### QA Order

Run content checks before language and formatting checks:

1. **Research QA:** use the `researchwrite` paper gate to audit argument,
   evidence coverage, section contracts, contradictions, and unsupported claims.
2. **Database-specific fit review:** check the manuscript against the official
   *Database* instructions and the representative resource-paper patterns.
3. **Statistics review:** use `nature-statistics` on quantitative claims,
   evaluation tables, figures, legends, sample definitions, and uncertainty.
4. **Data/code review:** use `nature-data` to finalize dataset-to-location
   mapping, repository identifiers, licences, code access, and FAIR metadata.
5. **Adversarial review:** use `nature-reviewer` as a high-standard stress test,
   then interpret its broad-significance comments through the actual scope of
   *Database* rather than treating it as an editorial decision.
6. **Reference audit:** use `nature-ref-verifier` on the complete BibTeX library
   and correct all critical metadata mismatches.
7. **Final language pass:** use `nature-polishing` only after scientific changes
   are complete.
8. **Submission build:** compile with the supported OUP class, numeric citation
   configuration, final figures, supplementary files, and declarations.

### Initial Submission Materials

Use `nature-writing` in `task=submission-package` mode for the initial cover
letter, title page, highlights when required, reviewer suggestions when
requested, funding, conflict-of-interest language, and the submission readiness
audit. Journal-specific instructions remain authoritative. Author Contributions
is deliberately deferred until the authors provide the contribution assignments;
it must not be inferred from repository history.

Use `nature-response` only after an editor or reviewer decision has been
received. At that point, preserve the decision letter, map each comment to a
revision action and manuscript location, and do not claim changes that have not
been made.

### Outputs

- `qa/researchwrite_qa.md`
- `qa/database_fit_review.md`
- `qa/statistics_audit.md`
- `qa/data_availability_audit.md`
- `qa/mock_peer_review.md`
- `qa/reference_verification.md`
- `submission/` containing the compiled manuscript and required source files,
  figures, supplementary files, declarations, and initial-submission materials
- a Word derivative generated from the locked LaTeX manuscript and verified by
  rendering and inspecting every page

### Phase Gate

Phase 5 passes only when all critical QA findings are resolved, remaining
limitations are disclosed, references are verified, all data/code routes are
real and accessible, the LaTeX package compiles without manuscript-blocking
errors, and the submission checklist matches the current journal instructions.
The Word derivative must also pass the `documents` render-and-inspect gate.

## Quality Gates Summary

| Gate | Required evidence | Work blocked until it passes |
|---|---|---|
| G1 Foundation | Six consistent foundation files | Evidence claims and prose drafting |
| G2 Evidence | Verified counts, provenance, tests, literature, limitations | Argument lock and main-figure production |
| G3 Argument and figures | Coherent argument; contracted figures and tables | Section drafting |
| G4 Section lock | Claim-evidence consistency; citations; terminology | Whole-manuscript polishing and review |
| G5 Submission | QA resolved; references and availability verified; clean build | Journal submission |

## Stop and Rework Rules

- Stop drafting when a central count, source version, licence, or runtime claim
  cannot be verified.
- Remove or weaken a claim when its evidence does not support the proposed
  strength; do not add explanation merely to preserve the stronger wording.
- Reopen the argument map when a missing result affects more than one section.
- Reopen a section contract when a new figure or analysis changes its purpose.
- Repeat language polishing only after the new scientific content is locked.
- Limit repeated QA-without-new-evidence cycles. After three unsuccessful
  revision rounds on the same issue, identify the missing evidence or narrow the
  claim instead of continuing stylistic rewrites.
- Do not treat service timeouts, unavailable external links, or anecdotal manual
  checks as positive evidence.
- Do not mark the manuscript submission-ready while required author facts,
  repository identifiers, licences, or access routes remain unresolved.

## Recommended Invocation Patterns

The skills can be triggered by natural-language requests. Explicitly naming the
skill and phase reduces ambiguity.

```text
Use researchwrite in compose mode to create the six TE-KG foundation files.
Do not draft manuscript prose. Record unsupported facts as AUTHOR_INPUT_NEEDED.
```

```text
Use nature-academic-search to identify primary TE database and biological
knowledge-graph papers that support the gap and comparison table. Verify support
from abstracts or full text rather than titles alone.
```

```text
Use nature-reader to produce a source-grounded full-paper reading record for
this benchmark paper, preserving figure and table positions and exact anchors.
```

```text
Use nature-writing with task=manuscript, paper_type=research,
section=methods, journal=generic, and language=zh-to-en. Draft only from the
approved section contract and evidence rows.
```

```text
Use nature-figure with the saved plotting backend. First write the figure
contract, then generate and visually verify submission-grade PDF, SVG, and TIFF
outputs from the recorded source data.
```

```text
Use nature-statistics to audit the Agent evaluation table and associated figure
legend. Check the unit of analysis, denominators, uncertainty, and claim strength.
```

```text
Use nature-data to inventory every manuscript dataset and code artifact, choose
real access routes, and draft a Database-ready Data Availability statement.
```

```text
Use nature-polishing with journal=generic only after this section is
scientifically locked. Improve English and flow without strengthening claims or
changing numbers and citations.
```

```text
Use nature-reviewer to perform a bounded three-reviewer pre-submission stress
test. Then separate general high-impact concerns from requirements that matter
specifically for Database.
```

```text
Use nature-ref-verifier to audit the final BibTeX library field by field and
produce a correction report before submission.
```

## Change Control

- Update this workflow only when the manuscript process or skill boundary
  changes.
- Update `01_research_canon.md` when a durable TE-KG fact changes.
- Update `02_evidence_table.md` when evidence or claim strength changes.
- Update the section contract before changing a locked section's scientific
  purpose.
- Preserve user-approved decisions and existing manuscript work; never replace
  them with a skill's default style without review.
- Record the date and evidence source for all quantitative updates.

This workflow is complete when it can be followed by another maintainer using
only the repository artifacts, the current journal instructions, and the
available skills, without relying on undocumented chat context.
