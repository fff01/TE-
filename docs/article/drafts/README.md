# TE-KG LaTeX Manuscript Draft

`main.tex` is the canonical manuscript source. The Word manuscript must be
generated from a content-locked version of this LaTeX project, not edited as an
independent scientific source.

## Build

Run from this directory:

```powershell
latexmk -pdf -interaction=nonstopmode -halt-on-error main.tex
```

The OUP 1.5 class and numeric bibliography style are copied from the official
CTAN bundle retained under `../submission/oup-authoring-template-1.5/`.

## Word Review Derivative

`word_source.tex` reuses the same section files and can be converted with
Pandoc. After conversion, run `../scripts/build_word_manuscript.py` to apply the
review layout, DOI hyperlinks, table geometry, draft warning, header, and page
field. The resulting DOCX is stored under `../submission/`. Scientific edits
must be made in the LaTeX section files and regenerated into Word.

## Working-Draft Markers

Red `Draft note` text marks facts that require author input or unrecovered
provenance. No file containing those markers is submission-ready. Author
Contributions is intentionally absent and must not be inferred.
