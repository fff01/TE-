# TE-KG Knowledge Graph Database Next Directions

Date: 2026-05-17

Scope: This note focuses on the database and knowledge graph layer of TE-KG. It does not plan the intelligent Q&A/agent subsystem.

## Current Baseline

- Runtime Neo4j target: `tekg3`.
- Runtime config entrypoint: `api/runtime_config.php`.
- TE taxonomy authority: `TE` node properties in Neo4j `tekg3`.
- Current Neo4j graph scale:
  - `TE`: 225
  - `Function`: 3683
  - `Paper`: 2308
  - `Gene`: 1280
  - `Protein`: 1089
  - `DiseaseCategory`: 767
  - `Disease`: 676
  - `RNA`: 588
  - `Mutation`: 377
  - `Pharmaceutical`: 293
- Current relationship scale:
  - `BIO_RELATION`: 12444
  - `HAS_SUBCATEGORY`: 744
  - `CLASSIFIED_AS`: 436
  - `SUBFAMILY_OF`: 72
- TE taxonomy state:
  - all 225 `TE` nodes have `taxonomy_group`
  - 192 `TE` nodes have `taxonomy_class`
  - 154 `TE` nodes are included in the homepage taxonomy chart
  - taxonomy groups: `standard=138`, `A=36`, `B=35`, `C=16`

## 1. Add Provenance As First-Class Data

The graph already has `Paper` nodes and `pmids` on `BIO_RELATION`, but relation-level evidence is still too compressed for a serious KG database.

Recommended direction:

- Create explicit evidence nodes or relation evidence records for each extracted claim.
- Store source sentence, extraction method, model/script version, PMID, section if available, and confidence.
- Keep curated relations separate from automatically extracted relations.
- Add a verification state such as `raw`, `reviewed`, `rejected`, `curated`.

Why this matters:

- Users can distinguish a strongly supported biological relation from a one-off extracted statement.
- Future data cleaning can target weak evidence instead of deleting whole entity nodes.
- It enables explainable ranking in graph search.

## 2. Normalize Relation Semantics

Most biological edges are currently stored under one relationship type, `BIO_RELATION`, with a `predicate` property. This is flexible, but weak for graph analytics and consistency checks.

Recommended direction:

- Define a controlled predicate vocabulary.
- Group predicates into families such as association, expression, regulation, insertion, classification, evidence reporting, and therapeutic interaction.
- Add validation rules for allowed source/target label pairs.
- Keep `BIO_RELATION` if needed for import flexibility, but validate `predicate` and endpoint labels.

Near-term checks:

- Count distinct `predicate` values.
- Detect predicates with only capitalization or spelling differences.
- Detect unlikely endpoint shapes, such as drug-to-drug or mutation-to-toxin, before deciding whether they are real or extraction noise.

## 3. Promote TE Taxonomy To A Managed Subgraph

The current taxonomy is stored as properties on `TE` nodes and as `SUBFAMILY_OF` edges between TE nodes. This is now stable enough for runtime, but not yet expressive enough for deeper taxonomy work.

Recommended direction:

- Add optional taxonomy concept nodes such as `TaxonomyClass`, `TaxonomyOrder`, `TaxonomySuperfamily`, and `TaxonomyFamily`.
- Keep denormalized `taxonomy_*` properties on `TE` for fast runtime display.
- Use taxonomy concept nodes for validation, browsing, and future cross-species extension.
- Record taxonomy source and merge decisions as data, not only as JSON report fields.

Priority:

- First keep the current Neo4j property model stable.
- Then add taxonomy concept nodes as an additive layer.

## 4. Link Expression Data Back Into The Graph

Expression data currently lives mainly in MySQL `tekg_expression` and files under `data/bulk_expression_web`. This is efficient for page queries, but the KG cannot reason over expression contexts directly.

Recommended direction:

- Keep MySQL as the runtime table store for large expression matrices and summary queries.
- Add graph-level summary nodes for important contexts, for example `ExpressionContext` or `Tissue`.
- Add edges from `TE` to context summary nodes for top expression, minimum expression, broad expression, or cancer-cell-line enrichment.
- Store only summary facts in Neo4j, not the full 5.4 million-row context table.

Good first graph facts:

- `TE` -> `ExpressionContext` for top median context by dataset.
- `TE` -> `ExpressionDataset` availability.
- high-level flags such as broadly expressed, tissue-specific, or cancer-cell-line enriched.

## 5. Add Data Quality Gates

The project now has useful checks for taxonomy and runtime DB config. This should become a standard data gate before importing or replacing `tekg3`.

Recommended checks:

- Node count and relationship count drift.
- Label/property coverage by entity type.
- Duplicate names beyond intended uniqueness constraints.
- Missing or malformed `pmids`.
- `BIO_RELATION` edges without `predicate`.
- `TE` nodes without required taxonomy fields.
- Homepage taxonomy count equals live Neo4j `homepage_chart_included = true`.
- MySQL expression tables have expected row counts and required quartile columns.

Suggested script direction:

- Add `scripts/checks/check_graph_database_integrity.py`.
- Keep `scripts/checks/check_taxonomy_runtime_consistency.py`.
- Keep `scripts/checks/check_runtime_db_config.py`.
- Add an expression table check using MySQL CLI or a PHP web check, because CLI PHP currently lacks `mysqli`.

## 6. Improve Search Ranking With Graph Signals

The graph already has enough structure for better ranking than simple name matching.

Recommended direction:

- Use degree, entity type, relation predicate, and evidence count as ranking features.
- Penalize generic functions that connect to too many entities unless the user explicitly asks broad questions.
- Boost entities with direct TE links, curated taxonomy, or expression summaries.
- Precompute lightweight graph metrics for repeated runtime use.

Useful graph metrics:

- TE degree by endpoint label.
- Predicate diversity per TE.
- Evidence count per TE relation.
- Disease and function neighborhood summaries.

## 7. Separate Runtime Graph From Build Graph

`tekg3` is the active runtime target. Build history still references `tekg2` and `tekg21`, which is normal for migration scripts, but the boundary should be explicit.

Recommended direction:

- Treat `tekg3` as the only runtime database.
- Keep older DB names only in migration/build/archive scripts.
- Add a generated database manifest after each build with:
  - source DB
  - target DB
  - import timestamp
  - node label counts
  - relationship type counts
  - taxonomy counts
  - homepage chart count
  - script commit/hash if available

## 8. Strengthen Schema Constraints

Current constraints cover names for most entity labels and PMID for `Paper`. This is a good base.

Recommended additions:

- Consider existence constraints where Neo4j edition supports them, especially `name` on core labels.
- Add constraints or checks for `DiseaseCategory.category_node_id`.
- Add validation for relationship properties such as `predicate` and `pmids`.
- Keep constraints generated by scripts rather than manually created in Neo4j Browser.

## 9. Treat Large Assets As A Data Product

Large files under `data/JBrowse`, `data/bulk_expression_web`, `data/dfam`, and `data/raw` are essential but too large to manage casually.

Recommended direction:

- Create a `data_manifest.json` or `docs/architecture/runtime_data_manifest.md`.
- For each large asset, record purpose, source, expected path, size, checksum, and generating script.
- Keep small canonical metadata in Git when feasible.
- Keep large raw/generated assets outside Git, but make their required status explicit.

## 10. Build A Public Database Contract

Before adding many more features, define what TE-KG promises as a database.

Recommended contract:

- Supported entity labels.
- Supported relation categories.
- Required properties per label.
- Taxonomy authority rule.
- Expression summary rule.
- Import/build order.
- Validation scripts that must pass before a database is considered usable.

This would make future UI, API, and data import work less fragile.
