# Raw Data

This folder contains original input files used to build or enrich the graph.

Current examples:

- `te_kg2.jsonl`
  - New TE knowledge extraction data from teammates.
- `TE_Repbase.txt`
  - Repbase-derived human transposable element reference file.
- `TE_names.txt`
  - TE name list used by extraction scripts.
- `tree.txt`
  - TE lineage tree reference.
- `LINE1_pubmed_data.csv`
  - Source CSV for the original LINE1-focused pipeline.
- `output.jsonl`
  - Original LINE1 extraction output before normalization.

Guideline:

- Treat files here as source-of-truth inputs.
- If a script transforms a file, write the result into `../processed/`.
