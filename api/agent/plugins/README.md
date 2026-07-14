# Agent Plugin Notes

This document describes the plugin layer under `api/agent/plugins/`. It is for
maintainers, not an end-user help page. For the planner-facing catalog, read
`PLUGIN_CATALOG.md`.

## Plugin Interface

All plugins implement `TekgAgentPluginInterface`:

```php
public function getName(): string;
public function run(array $context): array;
```

Plugins are registered through `api/agent/plugin_registry.php`. Registered names
must remain aligned with routing, planning queues, and frontend tool events, for
example `Graph Plugin` and `Sequence Plugin`.

Common `run($context)` inputs:

- `question`: original user question.
- `analysis`: intent, entities, alias chains, and requirement flags.
- `planning`: knowledge gaps, tool plan, and subtasks.
- `plugin_results`: previous plugin outputs.
- `config`: model, relay, PubMed, Neo4j, and runtime configuration.

Common output fields:

- `plugin_name`
- `status`: `ok`, `partial`, `empty`, or `error`
- `query_summary`
- `results`
- `display_label`, `display_summary`, `display_details`
- `result_counts`
- `evidence_items`
- `citations`
- `errors`
- `latency_ms`

Plugin output is not the final answer. Final answers are produced after evidence
packaging, evidence walking, report planning, writing/polishing, and integrity
checks.

## Evidence Item Contract

New plugins should prefer `tekg_agent_make_evidence_item()` instead of returning
bare strings. Core fields include:

- `source_plugin`
- `entity_scope`
- `claim`
- `support_strength`: `high`, `medium`, `low`, or `none`
- `raw_source_ref`
- `title`, `meta`, `body`

Useful extension fields:

- `evidence_type`
- `coverage_dimension`
- `subject`, `object`
- `provenance`
- `diagnostic`
- `citations`
- `quality_flags`

Failures, empty results, and citation-normalization diagnostics may enter
`evidence_items`, but they must use `support_strength=none` or quality flags so
the writing layer does not treat them as biological facts.

## Plugin Overview

| Plugin | Purpose | Main source | LLM use | Key risk |
|---|---|---|---|---|
| Entity Resolver | Normalize entities and aliases | Entity analysis | No | Missed entity recognition affects all downstream plugins |
| Site Navigator Plugin | Return internal page links | Site navigation map | No | Navigation links are not scientific evidence |
| Graph Plugin | Query local structured relations | Neo4j TE-KG | No | Graph associations are not automatically causal |
| Graph Analytics Plugin | Ranking/count/topology templates | Neo4j TE-KG | No | Template metrics are not biological strength |
| Cypher Explorer Plugin | Generate read-only exploratory Cypher | LLM + Neo4j | Yes | Schema assumptions and read-only validation |
| Literature Plugin | Local citations and PubMed search | Neo4j + PubMed | No | Weak or false-positive PubMed matches |
| Literature Reading Plugin | Cluster citation-level claims | Citations + LLM | Yes | Title/abstract synthesis can over-claim |
| Tree Plugin | TE/disease classification context | Taxonomy runtime | No | Classification context is not mechanism evidence |
| Expression Plugin | TE expression context | Expression runtime/MySQL | No | Runtime failure must not be read as no expression |
| Genome Plugin | Representative loci and JBrowse links | JBrowse hit JSON | No | Representative locus is not all loci |
| Sequence Plugin | Repbase-backed sequence records | Processed Repbase match | No | Structure hints are not strict classification |
| Citation Resolver | Normalize and deduplicate citations | Upstream citations | No | Does not verify claim support |

## Important Plugin Boundaries

- Site Navigator only provides page routes. It must not be used as research
  evidence.
- Graph Plugin returns local structured associations. Final writing must
  distinguish association, activation, hypomethylation, insertional mutagenesis,
  and other relation semantics.
- Literature Plugin retrieves candidate literature; it does not prove relevance
  or support by itself.
- Literature Reading Plugin should depend on valid Literature Plugin citations.
- Expression Plugin failures must be surfaced as retrieval/runtime failures, not
  biological absence.
- Genome Plugin outputs representative loci. Writing must not imply a complete
  catalog of all insertions unless the data support that.
- Citation Resolver formats citations. It does not perform claim-citation
  support auditing.

## Typical Execution Chains

Simple navigation:

```text
Entity Resolver -> Site Navigator Plugin
```

Simple sequence lookup:

```text
Entity Resolver -> Sequence Plugin
```

Mechanism or disease evidence:

```text
Entity Resolver -> Graph Plugin -> Literature Plugin -> Literature Reading Plugin -> Citation Resolver
```

Multi-dimensional research report:

```text
Entity Resolver
-> Literature Plugin
-> Literature Reading Plugin
-> Graph Plugin
-> Expression Plugin
-> Genome Plugin
-> Sequence Plugin
-> Citation Resolver
```

## Relation to the LLM Agent

Plugins are data tools, not LLM stages. They retrieve or structure evidence.
LLM stages perform understanding, planning, collection decisions, execution
review, integration, and writing.

The Agent may run an `ExecutingReview` LLM call after each plugin output. This
improves traceability but increases latency and failure points. Slowdowns often
come from plugin-adjacent LLM review rather than the plugin query itself.

## Current Improvement Priorities

1. Literature search needs stronger false-positive filtering.
2. Literature Reading needs explicit title-level vs abstract-level vs local
   graph-level evidence boundaries.
3. Graph `high` support must not be interpreted as strong causal evidence.
4. Expression runtime failures must be reported distinctly from no expression.
5. Genome representative loci must be used correctly by writing stages.
6. Sequence structure hints need stricter language to avoid LTR/LINE
   misclassification.
7. Citation Resolver should be paired with future claim-citation auditing.
