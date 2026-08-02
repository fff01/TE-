# TE-KG Data Source Register

Last reviewed: 2026-08-01

This register separates source identity from the local runtime destination. A
source is not manuscript-ready until its release/version, acquisition date,
licence, primary citation, transformation, and runtime role are documented.

| source | scientific role | local evidence | runtime destination | version/date status | licence status | manuscript status |
| --- | --- | --- | --- | --- | --- | --- |
| PubMed | Human TE literature discovery and paper metadata | `docs/method_english.docx`; 2,308 `Paper` nodes | Neo4j `tekg3` | Search reported through 2026-04-13; query recorded; intermediate counts inconsistent | NCBI terms still to record | Partial |
| RMSK/UCSC-style repeat table | Repeat names, classes, families, strands, and genomic coordinates | `data/rmsk.txt` | Neo4j graph and genome lookup assets | `AUTHOR_INPUT_NEEDED`: exact assembly/table release and acquisition date | `AUTHOR_INPUT_NEEDED` | Partial |
| RepBase-derived TE records | TE names, aliases, descriptions, classification, references, and consensus/reference sequences | `data/raw/TE_Repbase.txt` | Browse/sequence/taxonomy build products and runtime stores | `AUTHOR_INPUT_NEEDED`: exact RepBase release and acquisition date | Redistribution conditions must be confirmed | Partial |
| ICD-11 | Disease taxonomy reference | disease classification code/import history; architecture overview | Neo4j `DiseaseCategory` hierarchy | `AUTHOR_INPUT_NEEDED`: release identifier and access date | `AUTHOR_INPUT_NEEDED` | Partial |
| E-MTAB-1733 | Normal-tissue RNA-seq source component | normal-tissue matrix and metadata | MySQL expression summaries; co-expression inputs | Accession verified; local preprocessing release incomplete | Repository reuse terms to record | Partial |
| E-MTAB-2836 | Normal-tissue RNA-seq source component | normal-tissue matrix and metadata | MySQL expression summaries; co-expression inputs | Accession verified; local preprocessing release incomplete | Repository reuse terms to record | Partial |
| SRP013565 / GSE35585 / PRJNA30709 | ENCODE RNA-seq study from which the normal-primary-cell subset was derived | `normal_cell_line` matrix and metadata | MySQL expression summaries; co-expression inputs | Accession cross-links verified; exact included experiments and primary-cell selection must be enumerated | NCBI/GEO terms to record | Partial |
| PRJNA523380 | Cancer Cell Line Encyclopedia RNA-seq | cancer-cell-line matrix and metadata | MySQL expression summaries; co-expression inputs | Accession and CCLE association verified; exact selected 646 samples and preprocessing release must be recorded | NCBI SRA terms to record | Partial |
| HGNC/gene reference used by feature annotation | Gene-versus-TE feature classification | `data/coexpression/feature_annotation/`; annotation reports | co-expression analysis inputs | `AUTHOR_INPUT_NEEDED`: exact HGNC download/version | `AUTHOR_INPUT_NEEDED` | Missing |

## Verified Source Roles

### PubMed and the literature graph

The recorded search combined `DNA Transposable Elements` MeSH and TE-related
free text with a human restriction. The final retained set contains 2,308 papers,
which matches the current Neo4j Paper-node count. The inconsistent initial and
intermediate counts are not approved for manuscript use.

### RMSK and RepBase

RMSK-derived records answer where repeat occurrences are annotated in the human
genome. RepBase-derived records answer TE identity, classification, description,
alias, and representative-sequence questions. The manuscript must not describe
these as interchangeable sources.

### Expression accessions

The local matrices are derived resources rather than unmodified repository
downloads. The final Methods must therefore cite both the primary source
accessions/studies and the local preprocessing pipeline. The runtime identifier
`normal_cell_line` is retained for compatibility, but public biological wording
is `normal primary cell`.

## Primary Source Citations Identified

- RepBase update: Bao, Kojima and Kohany, *Mobile DNA* (2015),
  DOI `10.1186/s13100-015-0041-9`, PMID `26045719`.
- Human TE classification in RepBase: Kojima, *Mobile DNA* (2018),
  DOI `10.1186/s13100-017-0107-y`, PMID `29308093`.
- Human Protein Atlas tissue map associated with E-MTAB-2836: Uhlen et al.,
  *Science* (2015), DOI `10.1126/science.1260419`.
- Skin/tissue transcriptomics associated with E-MTAB-1733: Edqvist et al.,
  *Journal of Histochemistry & Cytochemistry* (2015),
  DOI `10.1369/0022155414562646`.
- CCLE source associated with PRJNA523380: Ghandi et al., *Nature* (2019),
  DOI `10.1038/s41586-019-1186-3`.

The SRP013565 subset still requires experiment-level reconciliation before a
single primary paper is assigned as its complete provenance.

## Blocking Provenance Gaps

1. exact RMSK assembly/table version, source URL, acquisition date, and licence;
2. exact RepBase release, source URL, acquisition date, and redistribution rule;
3. ICD-11 release and reuse statement;
4. complete expression preprocessing manifest, including reference genome,
   annotation release, software versions, normalization, and sample exclusion;
5. the exact SRP013565 experiments included as normal primary cells;
6. durable public release destinations and licences for TE-KG outputs.

