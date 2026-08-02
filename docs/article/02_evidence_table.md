# TE-KG Manuscript Evidence Table

Last reviewed: 2026-07-31

Status values are `verified`, `partial`, `missing`, or `rejected`. A claim may
enter manuscript prose only when its status is `verified`, or when a `partial`
claim is written together with its recorded boundary.

| claim_id | proposed_claim | claim_type | evidence_location | source_or_test | citation_key | figure_or_table | strength | boundary | status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| C001 | TE-KG integrates literature relations, TE classification, representative loci and sequences, expression, and co-expression evidence. | resource scope | `docs/architecture/database_overview.md`; runtime entrypoints | architecture review | external citations pending | Fig. 1 | high | The layers remain distinct evidence types. | verified |
| C002 | The current knowledge graph contains 2,308 paper nodes. | quantitative | Neo4j `tekg3` | live read-only label-count query, 2026-07-31 | none | Table 1 | high | Runtime snapshot; record date and release. | verified |
| C003 | The current graph contains 225 TE nodes and 12,444 directed `BIO_RELATION` relationships. | quantitative | Neo4j `tekg3` | live read-only label/relation-count queries, 2026-07-31 | none | Fig. 2; Table 1 | high | Relationship direction/count semantics must be stated. | verified |
| C004 | The graph connects TEs to 11 supported biomedical entity categories. | data model | `docs/method_english.docx`; runtime label counts | schema and runtime audit | none | Fig. 2 | high | Paper and taxonomy category nodes are not part of the 11 biomedical categories. | verified |
| C005 | Every current biological relation retains PMID provenance and a predicate. | provenance | Neo4j relationship properties | live property-completeness query, 2026-07-31 | none | Fig. 1; Table 1 | high | Presence of a PMID does not prove that every phrasing is causal. | verified |
| C006 | The final retained literature corpus contains 2,308 papers. | quantitative | `docs/method_english.docx`; Neo4j Paper count | document/runtime agreement | none | Fig. 1 | high | Intermediate filtering counts are excluded. | verified |
| C007 | PubMed records were screened with TE whitelists and processed by constrained DeepSeek-V3 extraction followed by normalization and manual curation. | method | `docs/method_english.docx`; `docs/architecture/database_overview.md` | method record | PubMed/method citations pending | Fig. 1 | medium | Exact model version and reproducible environment remain missing. | partial |
| C008 | The reported three 50-record manual audits support the filtering workflow. | evaluation | `docs/method_english.docx` | archived method report | statistical method citations pending | Supplementary table | low | Sample lists, seed, denominators, and inconsistent stage counts must be recovered. | partial |
| C009 | RMSK-derived records provide genomic positions and RepBase-derived records provide identity, classification, and consensus/reference sequences. | provenance | `data/rmsk.txt`; `data/raw/TE_Repbase.txt`; architecture overview | source-file and schema review | RMSK/RepBase citations pending | Fig. 1; Table 2 | high | Upstream versions, dates, and licences are missing. | partial |
| C010 | The live taxonomy summary contains 225 TEs, including 192 with a taxonomy class. | quantitative | `api/taxonomy.php?view=summary` | live HTTP response, 2026-07-31 | none | Fig. 2 | high | Runtime snapshot only. | verified |
| C011 | Browse exposes a versioned 276-entry TE catalog. | quantitative | `api/browse.php` | live HTTP response, 2026-07-31 | none | Table 1 | high | Browse catalog count is not identical to the Neo4j TE-node count. | verified |
| C012 | Expression data cover normal tissue, normal primary cell, and cancer cell line contexts with 205, 307, and 646 samples. | quantitative | active matrices; `docs/coexpression/data_audit.md` | matrix audit | accession citations pending | Fig. 3; Table 2 | high | Existing runtime name `normal_cell_line` means normal primary cell. | verified |
| C013 | Expression matrices contain both TE/repeat and gene features. | data model | active matrices; data audit | feature-name audit | none | Fig. 3 | high | Do not describe them as gene-only matrices. | verified |
| C014 | Five normal-tissue matrix samples lack matching metadata and duplicate run identifiers are present. | limitation | `docs/coexpression/data_audit.md` | metadata audit | none | Supplementary table | high | Must be disclosed or resolved before downstream comparisons are generalized. | verified |
| C015 | Context-specific co-expression uses Spearman correlation with `abs(r) >= 0.4`, `FDR <= 0.05`, and Louvain resolution 1.8. | method | `docs/coexpression/README.md`; runtime catalog | code/output/runtime agreement | method citations pending | Fig. 4 | high | Parameter-selection evidence still needs a manuscript-facing sensitivity summary. | verified |
| C016 | Production co-expression serves approved MySQL-backed display networks separately from full offline networks. | architecture | repository/API/frontend | runtime architecture review | none | Fig. 1; Fig. 4 | high | Display catalogs are not full analysis networks. | verified |
| C017 | The live co-expression catalog contains 285 TE and 499 Gene entries across three contexts. | quantitative | `api/coexpression.php?action=catalog` | live HTTP response, 2026-07-31 | none | Table 1 | high | Counts describe approved searchable display entries. | verified |
| C018 | Co-expression edges indicate statistical association and do not establish regulation or causality. | interpretation | method design and runtime contract | method/contract review | correlation references pending | all relevant figures | high | Required wording boundary. | verified |
| C019 | TE-KG provides connected Browse, Path, Graph, Expression, Download, and intelligent question-answering workflows. | functionality | root pages, APIs, browser checks | runtime/source review | none | Fig. 3 | high | Scientific utility must be shown with worked use cases, not asserted from feature presence. | verified |
| C020 | Agent and DeepThink retrieve bounded local evidence through twelve plugin roles and support session-scoped follow-up questions. | functionality | intelligent QA handoff, plugin catalog, tests | contract/test review | none | Fig. 5 | high | The interface is not an autonomous reasoning or validation system. | verified |
| C021 | The historical 36-question evaluation demonstrates acceptable current answer quality. | evaluation | 2026-07-30 evaluation records | historical run | none | Table 3 | low | Later fixes changed some failures; the complete suite was not rerun as one current benchmark. | rejected |
| C022 | TE-KG is the first or most comprehensive integrated human TE database. | novelty | literature comparison not yet complete | none | missing | none | none | Requires systematic comparison against existing resources. | missing |
| C023 | TE-KG improves biological discovery or research efficiency. | utility | worked use cases not yet locked | none | missing | Fig. 4/5 candidate | none | Must be demonstrated with bounded examples or user evaluation. | missing |
| C024 | The data and code are openly and durably available. | availability | repository and licence plan missing | none | none | Data Availability | none | Local runtime and Download page do not establish archival availability. | missing |
| C025 | The manual literature audit achieved the reported confidence intervals. | statistics | `docs/method_english.docx` | source audit pending | methods citations pending | Supplementary table | low | Recompute after recovering the sampled decisions and exact denominators. | partial |

## Immediate Evidence Work

1. Recover and verify source versions, dates, licences, and primary citations.
2. Export a dated runtime count table from reproducible Neo4j/MySQL queries.
3. Reconcile the literature-screening arithmetic and manual-audit sample records.
4. Preserve the co-expression sensitivity tables and generation commands.
5. Build a current, bounded Agent/DeepThink evaluation table only if the
   intelligent interface will receive a quantitative manuscript claim.
6. Define durable code and data release locations before drafting Data
   Availability.

