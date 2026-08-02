# Word Working Draft Review

Review date: 2026-08-01

## Generation Evidence

- Canonical content source: `drafts/word_source.tex`, which inputs the same
  section files as `drafts/main.tex`.
- Conversion: Pandoc with citeproc, `submission/database.csl`, and
  `references.bib`.
- Post-processing: `scripts/build_word_manuscript.py`.
- Output: `submission/TE-KG_working_draft_v0.1.docx`.

## Structural Checks

- The DOCX ZIP package opens without an archive error.
- The document contains 94 paragraphs, 26 headings, one 10-row table, 16
  bibliography paragraphs, and one section.
- Two Word math objects preserve the log transformation and correlation
  threshold expressions.
- Sixteen external DOI hyperlinks are present.
- Eleven red runs comprise the working-draft warning and ten unresolved Draft
  notes.
- The four table grid widths are fixed at 1900, 850, 2750, and 3860 DXA.
- Author Contributions is absent, as required.
- Accessibility audit: zero high-, medium-, or low-severity findings.
- Style lint reported direct formatting associated with the deliberate title,
  bibliography, warning, and table treatment. Its four heading-like warnings
  are table header cells rather than missing document headings.

## Visual Verification Boundary

The packaged DOCX renderer could not run because this workstation has neither
LibreOffice `soffice` nor Microsoft Word installed. A browser-based proxy render
also failed because the installed Chromium GPU process terminated in headless
mode. Consequently, the Word file has passed structural and content QA but has
not passed page-by-page visual QA in a Word-compatible renderer.

The canonical LaTeX PDF was rendered and inspected on all six pages. That check
supports content and figure-flow review but does not substitute for the pending
DOCX visual check. Before the Word file is sent to a co-author or uploaded, open
it in Word or LibreOffice and check title-page spacing, Table 1 wrapping,
bibliography line breaks, headers, footers, and page numbers.

## Status

The DOCX is suitable as a review derivative. It is not submission-ready and is
not an independent scientific source.
