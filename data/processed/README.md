# Processed Data

This folder contains cleaned, normalized, or generated artifacts derived from raw data.

Current examples:

- `normalized_output.jsonl`
- `neo4j_graph_seed.json`
- `line1_subfamily_relations.csv`
- `te_kg2_normalized_output.jsonl`
- `te_kg2_graph_seed.json`
- `te_kg2_normalization_report.json`
- `tree_te_lineage.json`
- `tree_te_lineage.csv`
- `tekg_exact_duplicate_report.json`
- `tekg_semantic_standardization_report.json`

Guideline:

- Files here are safe to regenerate from scripts.
- These files are often used as the bridge between raw inputs and Neo4j import files.
