# Reference Audit

Audit date: 2026-08-01

## Coverage

- `references.bib` contains 22 stable BibTeX entries.
- The current manuscript cites 16 entries; six representative *Database*
  articles remain available for positioning and structural guidance.
- The cited reference list contains 16 DOI hyperlinks in both the generated
  bibliography data and the Word review derivative.
- BibTeX completed with zero warnings, and the LaTeX log contains no undefined
  citation report.

## Metadata Basis

The cited resource, expression, methods, and comparator records are mapped in
`evidence/literature_map.md`. Entries marked verified there were checked against
publisher, PubMed, or Crossref metadata during the literature stage. Stable
BibTeX keys preserve the claim-to-source mapping across LaTeX and Word.

## Remaining Checks

- Identify and verify the primary citation for the exact SRP013565 subset.
- Add citations for upstream RepeatMasker and RepBase release documentation once
  the local versions are known.
- Re-run field-level verification after the final bibliography is frozen,
  especially for author truncation, article identifiers, issue labels, and
  online-first versus volume year.
- Confirm that every comparison-table statement is supported by a paper or a
  dated live-resource check rather than by memory.

## Status

The current working bibliography is internally consistent and compiles. It is
not the final submission bibliography because provenance-dependent references
remain unresolved.
