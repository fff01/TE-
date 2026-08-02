# Data Availability Audit

Audit date: 2026-08-01

Target: *Database: The Journal of Biological Databases and Curation*

## Blocking Issues

The current Data Availability paragraph is intentionally incomplete. The local
web interface and Download page are not a stable public deposit. Submission
requires a free, login-free public resource URL, a maintenance commitment, and
durable dataset-to-location mapping. No repository, DOI, accession, licence, or
release identifier may be invented to fill these gaps.

## Dataset and Access Inventory

| Dataset or artifact | Intended route | Required action |
| --- | --- | --- |
| Neo4j literature graph and schema exports | Public repository plus web download | Freeze a release, record format and licence, and mint a stable identifier. |
| Taxonomy source and exported views | Public repository plus web download | Record upstream release rights, derived-file licence, and release checksum. |
| Processed expression matrices and metadata | Public repository | Map every local sample to its reused public accession and resolve duplicated or unmatched runs. |
| Co-expression networks, catalogue, and parameters | Public repository | Deposit full offline outputs, approved display subset, parameters, software versions, and provenance. |
| Literature query, retained metadata, relation table, and audit records | Mixed public and third-party-restricted | Release query strings, PMIDs, derived relations, and audit decisions; do not redistribute restricted full text. |
| Source code and deployment documentation | Versioned code archive | Create a tagged release, archive it durably, and record the software licence. |
| Figure source tables and verification snapshots | Supplement or public repository | Freeze the exact tables used for figures and Table 1. |

## Working Statement

> TE-KG will be freely available without login or registration at
> AUTHOR_INPUT_NEEDED_PUBLIC_URL. The version of the graph, taxonomy,
> expression, co-expression and figure-source datasets supporting this article
> will be deposited at AUTHOR_INPUT_NEEDED_REPOSITORY under
> AUTHOR_INPUT_NEEDED_IDENTIFIER. Source code for the corresponding release
> will be archived at AUTHOR_INPUT_NEEDED_CODE_ARCHIVE under
> AUTHOR_INPUT_NEEDED_IDENTIFIER. Reused expression datasets are available from
> their original repositories under the accessions listed in the release
> manifest. File-level provenance and licence information will be supplied with
> each deposited artifact.

This text is not ready to paste into a submission until every placeholder has a
verified value.

## FAIR and Metadata Gate

- Add a machine-readable release manifest with filenames, versions, checksums,
  formats, licences, provenance, and related manuscript figures or tables.
- Provide stable TE identifiers and document how TE names map across graph,
  taxonomy, expression, and co-expression files.
- Preserve the source accession and processing history for every expression
  sample.
- Record repository metadata: title, creators, description, version, release
  date, licence, related article, keywords, and contact route.
- Test that public files are reachable without authentication.

## Chinese Author Checklist

- 确认公开数据库网址以及至少两年的维护安排。
- 确认代码仓库、数据仓库、版本号、永久标识符和许可证。
- 冻结三个表达矩阵的样本对应表，并解决重复或缺失元数据。
- 明确哪些文献材料只能发布 PMID 和衍生关系，不能重新分发全文。
