# Mock Pre-submission Review

Review date: 2026-08-01

## Review Setup

- Input: complete six-page TE-KG working draft, evidence registers, figure and
  table contracts, and current declaration placeholders.
- Boundary: planned figures are not yet assembled, the public release is not
  deposited, and several upstream versions and author fields are unresolved.
- Shared claim: TE-KG integrates several human TE evidence layers around common
  identifiers while retaining layer-specific provenance and interpretation
  boundaries.

## Reviewer 1: Technical Soundness Emphasis

The manuscript is unusually careful about separating literature associations,
representative genomic records, expression measurements, and co-expression
statistics. Dated runtime counts and explicit rejection of doubled graph counts
are strengths. However, the literature extraction audit cannot yet be
reproduced, source releases and licences are missing, the expression sample
manifest is unresolved, and the co-expression comparison family is not fully
specified. These are major release-provenance gaps. The paper should not be
submitted until the archived query, audit decisions, sample manifest, network
parameters, and public deposits are frozen.

## Reviewer 2: Originality and Significance Emphasis

The strongest contribution is cross-layer traversal with visible evidence
boundaries, not the use of a knowledge graph or language model by itself. This
position is credible and appropriately avoids claims of being first, largest,
or comprehensive. The current draft still needs a verified comparison table
and completed L1HS example to show that integration produces a concrete user
benefit beyond placing several interfaces in one site. A second TE type would
substantially strengthen the generality argument.

## Reviewer 3: Readability and User Value Emphasis

The manuscript explains its interpretation limits clearly and avoids internal
Agent diagnostics in user-facing prose. Readers can distinguish graph,
taxonomy, expression, and co-expression record types. The text is nevertheless
count-heavy without the planned architecture, content, workflow, and use-case
figures. Those figures should carry the main narrative so that nonspecialists
can understand what question each layer answers. The intelligent interface is
correctly secondary and should remain so.

## Cross-review Synthesis

### Consensus Strengths

- Conservative, user-readable evidence semantics.
- Dated and explicitly defined resource counts.
- Honest positioning against specialist TE resources.
- Clear separation of interface capability from biological inference.

### Consensus Risks

- Public URL, maintenance plan, archival deposits, licences, and release
  manifests are missing.
- Literature audit and co-expression computation are not yet reproducible from
  the manuscript package.
- The principal L1HS example and manuscript figures remain contracts rather
  than results.
- The comparison table is not yet populated from source-verified evidence.

### Recommendation Posture

The draft has a defensible *Database* resource-paper core, but it is not ready
for submission. Resolving the release and provenance blockers, then completing
the figures and worked examples, is more important than further prose
polishing.

## Unsupported or Not Assessable

- A quantitative accuracy claim for literature extraction or Agent/DeepThink.
- Exhaustiveness of TE insertions, expression contexts, or literature coverage.
- Long-term public availability before a stable deployment and maintenance plan
  exist.
- Generality of the cross-layer workflow beyond L1HS until a second case is
  documented.
