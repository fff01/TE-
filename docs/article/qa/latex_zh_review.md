# Chinese LaTeX Review

Review date: 2026-08-01

## Scope

`drafts/main_zh.tex` is a complete Chinese review translation of the current
English working manuscript. It includes the abstract, all manuscript sections,
Table 1, declarations, citations, references, and every unresolved Draft note.
Resource names, identifiers, code fields, accessions, numerical values, and
bibliographic metadata remain unchanged so that the translation can be checked
against the English source.

The Chinese PDF is not a submission source. Its title page and running header
state that the English manuscript governs in case of disagreement. Author
Contributions remains omitted and was not inferred during translation.

## Build and Visual QA

- Build command: `latexmk -xelatex -interaction=nonstopmode -halt-on-error main_zh.tex`.
- Engine: XeLaTeX with `ctexart` and the Windows CJK font set.
- Citations: numeric, sorted, and compressed through the same BibTeX database
  used by the English manuscript.
- Initial output: ten pages.
- All ten pages were rendered to PNG and inspected at 1.8x scale.
- No missing Chinese glyphs, unresolved citations, clipped paragraphs,
  overlapping text, broken equations, or table overflow were observed.
- The title line break and Table 1 alignment were revised after the first
  visual inspection. A second render of all ten pages confirmed the corrected
  title, readable table wrapping, and stable page flow.

## Translation Boundary

This is a faithful review translation rather than a new language-polished
manuscript. It does not strengthen claims, fill provenance gaps, translate
reference titles, or replace red Draft notes with inferred facts.
