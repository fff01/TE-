# Statistical Reporting Audit

Audit date: 2026-08-01

## Scope

The manuscript is primarily a database resource description. Most reported
values are dated descriptive counts, not inferential experimental results. The
only central statistical analysis described in the current draft is the
context-specific TE-gene co-expression pipeline.

## Study Design Readout

- Response variables: TE and gene abundance features within three independently
  sourced expression contexts.
- Transformation: log2(count + 1).
- Association: Spearman rank correlation.
- Retention threshold: absolute correlation at least 0.4 and Benjamini-Hochberg
  FDR at most 0.05.
- Community detection: positive retained edges, Louvain seed 42, resolution
  1.8.
- Interpretation boundary: correlation, not regulation or causality.

## Major Issues

### P0: Reproducibility of the multiple-testing family

The manuscript does not yet define the exact set of TE-gene pairs tested in
each context, feature filtering rules, handling of constant or missing values,
or whether FDR correction was applied separately by context. These details are
needed to reproduce the retained network and evaluate the stated FDR threshold.

### P1: Sample and metadata inclusion

The three contexts are correctly kept separate, but the exact accession-to-row
manifest is not frozen. Duplicated normal-tissue identifiers and five unmatched
runs remain unresolved. The release must state which records entered each
correlation analysis and why any record was excluded.

### P1: Software environment

The implementation language, relevant package names and versions used for
correlation, FDR adjustment and Louvain detection are not yet stated in the
manuscript. The current repository code can supply these facts, but they should
be frozen with the released network.

## Reporting Strengths

- The draft does not combine the three contexts into a matched normal-cancer
  comparison.
- Presentation tiers are not described as statistical significance levels.
- The display graph is distinguished from the full offline network.
- No p-value, confidence interval, sample size, accuracy percentage, or causal
  claim has been invented.

## Required Methods Addition

Before submission, add one reproducible paragraph defining the analysed sample
set, tested feature-pair universe, missing and constant-value handling,
correction family, package and version information, and output version. Do not
add effect sizes or uncertainty intervals unless a new comparison analysis is
actually performed.

## Reviewer Risk

A reviewer may challenge the network as irreproducible until the comparison
family and sample manifest are frozen. This is a reporting and provenance gap;
the current supplied materials are insufficient to determine whether the
underlying computation itself is invalid.
