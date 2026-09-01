# TE-KG: a provenance-aware knowledge graph and multi-layer resource for human transposable elements

Author names to be supplied  
Department and institution to be supplied  
Corresponding author details to be supplied

Working draft v0.1, 1 August 2026

==*WORKING DRAFT - unresolved author and provenance fields are shown in red*==

# Abstract

Information on human transposable elements is distributed across repeat classifications, reference sequences, genomic annotations, expression datasets and biomedical literature. We developed TE-KG to connect these complementary data through a human TE-centred knowledge resource. The current knowledge graph contains 225 TE nodes, 2,308 paper nodes and 12,444 directed biological relations, each linked to its predicate and PubMed provenance. The expression collection comprises 1,158 samples from normal tissues, normal primary cells and cancer cell lines. Context-specific co-expression networks are searchable for 285 TEs and 499 genes. TE-KG combines browsing, graph and path exploration, expression visualization, data download and natural-language retrieval in a single web resource. This integration provides traceable access to classification, genomic, expression and literature evidence for human TE research.

**Database URL:** https://bis.zju.edu.cn/tekg/

**Keywords:** transposable elements; knowledge graph; biomedical literature; expression; co-expression; database

# Introduction

Transposable elements (TEs) are a major component of the human genome and are investigated through several complementary data types. Repeat-family resources describe TE classification and consensus sequences, genome annotations record genomic loci, expression datasets quantify transcription across biological contexts, and biomedical studies report relations between TEs and other entities. Together, these sources provide complementary views of human TE biology.

Established resources provide substantial depth within particular evidence types. RepBase curates repeat-family classifications and consensus sequences (1,2). HERVd, dbRIP, euL1db and TranspoGene provide specialized information on endogenous retroviruses, retrotransposon insertions and TE-related genomic annotations (3–6). More recent resources have focused on disease or expression evidence. HervD Atlas curates HERV-disease associations and exposes them through an interactive knowledge graph, CancerHERVdb concentrates on HERV expression in cancer, and TE-SCALE provides single-cell TE expression and TE-gene co-expression across human cancers (7–9). Together, these resources illustrate the breadth of TE data represented across specialized databases.

Despite this breadth, information for the same TE remains distributed across resources. Researchers must repeatedly reconcile names when moving from classification and sequence records to genomic locations, expression profiles, co-expression networks and supporting publications. A resource that links these layers through shared TE identifiers can make cross-layer exploration more direct and improve access to the underlying evidence.

Here we describe TE-KG, a human TE-centred resource that integrates literature-derived biomedical relations, taxonomy, representative sequence and genomic records, expression profiles and context-specific co-expression networks. TE-KG provides browsing, graph and path exploration, expression visualization, data download and natural-language access.

# Materials and methods

## Literature collection, extraction and normalization

PubMed was queried using the following combination of MeSH terms and free text: `("DNA Transposable Elements"[MeSH] OR "retrotransposon" OR "transposon" OR "retrotransposons" OR "transposons" OR "Retrotransposition" OR "transposition") AND ("humans"[MeSH Terms] OR "human" OR "homo sapiens")`. The search retrieved 34,788 articles published by 13 April 2026. Human TE names annotated in RMSK were combined with human TE identifiers and KW-line text from RepBase to form a screening whitelist. Screening titles and abstracts against this whitelist retained 5,362 articles. A second whitelist-based assessment of the language-model outputs produced a final corpus of 2,308 papers.

The retained text and metadata were processed with constrained DeepSeek-V3 prompts to identify TE-centred biomedical statements and their participating entities. Extracted entities were mapped to 11 categories: carbohydrate, disease, function, gene, lipid, mutation, peptide, pharmaceutical, protein, RNA and toxin. Relations were grouped into five semantic classes: causal, regulatory, interaction, association and genetic information flow. Each relation record retained a normalized predicate, one or more PubMed identifiers (PMIDs) and the number of supporting PMIDs. Entity names were matched using a Ratcliff-Obershelp similarity threshold of 80%, followed by manual synonym-to-canonical-name curation where automated resolution was insufficient.

Three manual audits examined 50 records from each of the initially excluded, secondarily excluded and integration-flagged sets. The corresponding correct-decision rates were 100%, 94% and 96%.

## TE classification, sequences and genomic records

TE taxonomy was based primarily on RepBase annotations obtained in EMBL format from the *Homo sapiens and ancestral (shared) repeats* collection (1,2). For TEs that were absent from RepBase or not fully classified there, RMSK classification was used as a secondary reference. The local RepBase-derived source also supplied TE identifiers and consensus or reference sequences, whereas genomic positions were obtained from RMSK annotations. These sequence and position records represent reference models and representative genomic loci rather than an exhaustive inventory of active, polymorphic or sample-specific insertions.

Taxonomy is served through the Neo4j-backed API. The RMSK + RepBase view presents the hierarchy of TEs classified using these two sources. The All view retains this hierarchy and adds TE names that are not covered by RMSK or RepBase, including some non-standard names represented in the knowledge graph.

## Expression datasets

The expression layer comprises three matrices, each containing 37,868 features. The normal-tissue matrix was derived from E-MTAB-1733 and E-MTAB-2836 and contains 205 samples (10,11); the normal-primary-cell matrix was derived from SRP013565 and contains 307 samples; and the cancer-cell-line matrix was derived from PRJNA523380 and contains 646 samples (12). Together, the matrices contain 1,158 samples.

## Context-specific co-expression networks

Co-expression was computed separately for normal tissues, normal primary cells and cancer cell lines. Abundance values were transformed as log<sub>2</sub>(*count* + 1), and TE-gene associations were measured using Spearman rank correlation. Candidate edges were retained when \|*r*\| ≥ 0.4 and the Benjamini-Hochberg false discovery rate (FDR) was at most 0.05 (13). Positive retained edges were used for Louvain community detection with random seed 42 and resolution 1.8 (14).

The complete network outputs were retained for data release and database import. For interactive visualization, each network view was limited to 50 nodes and 150 edges.

## Graph, relational storage and web application

The knowledge graph is stored in Neo4j, whereas MySQL stores the Browse catalogue, expression matrices and co-expression datasets. PHP application programming interfaces connect these databases to the web application. Browser-based components render tables, expression charts, taxonomy views and interactive knowledge and co-expression graphs.

## Agent and DeepThink

The natural-language interface provides two evidence-retrieval workflows. DeepThink uses four stages—understanding, planning, execution and writing—for direct questions. Agent adds evidence collection and integration to form a six-stage workflow for questions that require information from several data layers. Both workflows normalize entities, select the relevant TE-KG retrieval plugins and generate an answer from the returned records. Follow-up context is limited to the current browser session, and citations recovered from the evidence are presented as PubMed links in the final response.

# Results

## A literature-linked human TE graph with explicit provenance

The current Neo4j snapshot contains 225 TE nodes, 2,308 Paper nodes and 12,444 directed `BIO_RELATION` relationships connecting TEs to biomedical entities. Every biological relation has a normalized predicate, at least one PMID and a supporting-PMID count, allowing users to inspect the publications associated with an edge.

The biomedical schema contains 11 entity categories. The most numerous current labels are Function (3,683 nodes), Gene (1,280), Protein (1,089), Disease (676), RNA (588), Mutation (377) and Pharmaceutical (293). Smaller categories include Toxin (67), Lipid (26), Peptide (23) and Carbohydrate (12). DiseaseCategory (767), Paper (2,308) and taxonomy-related nodes are represented separately because they serve organizational or provenance roles rather than the 11 biomedical entity categories.

Table 1. Current TE-KG content snapshot.

| **Component**                     | **Count**  | **Record type**                                                                                         | **Data source**                         |
|-----------------------------------|------------|---------------------------------------------------------------------------------------------------------|-----------------------------------------|
| TE nodes                          | 225        | TE entities in the knowledge graph                                                                      | Neo4j                                   |
| Paper nodes                       | 2,308      | Publications represented in the knowledge graph                                                         | Neo4j                                   |
| Directed biological relations     | 12,444     | Stored `BIO_RELATION` relationships                                                                     | Neo4j                                   |
| Biomedical entity categories      | 11         | Normalized classes used for literature-derived entities                                                | Neo4j                                   |
| Browse catalogue entries          | 276        | Versioned TE catalogue records                                                                          | MySQL Browse catalogue                  |
| Taxonomy-classified TEs           | 192 of 225 | TE nodes with an assigned taxonomy class                                                                | Taxonomy API                            |
| Expression samples                | 1,158      | 205 normal tissue, 307 normal primary cell and 646 cancer cell line                                     | Three expression matrices               |
| Searchable co-expression entries  | 784        | 285 TE and 499 Gene entries                                                                             | Co-expression display catalogue         |
| Download files                    | 10         | Six expression, two graph and two taxonomy files                                                        | Download collection                     |

## Classification views

The taxonomy summary contains 225 TE nodes, of which 192 have an assigned taxonomy class and 188 are standard leaves. Among the 225 TE nodes, 189 are assigned to the RMSK + RepBase taxonomy source and 36 to the supplementary All source. The All view combines these two groups.

The classification interface presents the same API-backed taxonomy data as either a hierarchical tree or a force-directed graph. Its interactive legend can hide or restore TE classes without changing the underlying result set.

## Expression and co-expression add three context classes

The three expression collections allow TE and gene abundance profiles to be examined separately in normal tissues, normal primary cells and cancer cell lines. The co-expression catalogue covers the same contexts and provides searchable networks for 285 TEs and 499 genes. All displayed edges meet the correlation and FDR thresholds defined in the Methods.

## Connected interfaces expose complementary evidence routes

TE-KG provides several entry routes suited to different questions. Browse combines TE catalogue filtering, built-in search and access to structured records for selected TEs. Knowledge Graph displays literature-linked biomedical relations and exposes PubMed evidence. Path searches for connections between selected entities. Expression shows context-specific abundance profiles, Co-expression displays searchable TE-gene correlation neighbourhoods, and Download provides the release files summarized in Table 1.

![alt text](<../../about/figs/TE-KG Data Architecture and Public Services.svg>)

These connected views allow a selected TE to be followed across classification, genomic records, expression profiles, co-expression neighbourhoods and supporting publications.

# Discussion

By organizing heterogeneous records around shared TE identities, TE-KG reduces the effort required to move between specialized evidence sources. Its principal contribution is a connected route from a TE record to complementary data and the publications supporting literature-derived relations.

This positioning is complementary to specialist resources. RepBase provides deeper repeat-family reference records (1,2). dbRIP and euL1db describe retrotransposon insertion records at a resolution not provided by TE-KG (4,5). HervD Atlas offers manually curated HERV-disease associations, and TE-SCALE provides single-cell cancer expression and co-expression beyond the bulk datasets described here (7,9). TE-KG is therefore most useful for questions that cross evidence types.

Predicates and PMIDs make literature-derived graph edges inspectable and allow users to return to the supporting publication. The corpus was generated through automated screening, constrained language-model extraction, normalization and manual curation. Retrieval, abbreviation resolution, entity normalization and relation extraction therefore remain potential sources of error. A reproducible audit with preserved sample identifiers will be needed to quantify extraction accuracy.

TE expression estimates are sensitive to repetitive sequences and quantification choices (15). The three expression contexts originate from different studies and were analysed as separate collections rather than as a matched normal-to-cancer experiment. Co-expression networks summarize thresholded pairwise correlations and do not establish regulatory interactions. The web interface displays selected neighbourhoods from the complete offline networks.

The natural-language interface provides a convenient route to records stored across TE-KG and supports follow-up questions within a session. Its answers depend on entity resolution, evidence retrieval and language-model synthesis, and may therefore be incomplete. Links to PubMed and the corresponding database views allow users to inspect the retrieved evidence directly.

# Conclusion

TE-KG provides a unified, traceable resource for investigating human TEs across classification, genomic, expression and literature evidence. By connecting these records through shared TE identities and retaining links to their underlying sources, the database supports both focused retrieval and cross-layer exploration.

# Data availability

TE-KG provides graph, taxonomy and expression files through its web interface. ==*\[Draft note: Replace this working statement with a stable public Database URL, versioned repository and archival deposit identifiers, file-level licences, release version, update policy and accession-to-sample manifests. The final resource must be freely accessible without login or registration.\]*==

# Supplementary data

==*\[Draft note: Supply the frozen source tables, query files, data manifests, literature-audit records, co-expression sensitivity outputs and detailed verification results described in the table plan.\]*==

# Funding

==*\[Draft note: Author input required, including funder names, grant numbers and recipient initials.\]*==

# Acknowledgements

==*\[Draft note: Author input required.\]*==

# Conflict of interest

The authors declare no conflict of interest.

# References

1\. Bao, W., Kojima, K.K. and Kohany, O. (2015) Repbase update, a database of repetitive elements in eukaryotic genomes. *Mobile DNA*, **6**, 11. [<u>doi:10.1186/s13100-015-0041-9</u>](https://doi.org/10.1186/s13100-015-0041-9)

2\. Kojima, K.K. (2018) Human transposable elements in repbase: Genomic footprints from fish to humans. *Mobile DNA*, **9**, 2. [<u>doi:10.1186/s13100-017-0107-y</u>](https://doi.org/10.1186/s13100-017-0107-y)

3\. Pačes, J., Pavlíček, A. and Pačes, V. (2002) HERVd: Database of human endogenous retroviruses. *Nucleic Acids Research*, **30**, 205–206. [<u>doi:10.1093/nar/30.1.205</u>](https://doi.org/10.1093/nar/30.1.205)

4\. Wang, J., Song, L., Grover, D., et al. (2006) dbRIP: A highly integrated database of retrotransposon insertion polymorphisms in humans. *Human Mutation*, **27**, 323–329. [<u>doi:10.1002/humu.20307</u>](https://doi.org/10.1002/humu.20307)

5\. Mir, A.A., Philippe, C. and Cristofari, G. (2015) euL1db: The european database of L1HS retrotransposon insertions in humans. *Nucleic Acids Research*, **43**, D43–D47. [<u>doi:10.1093/nar/gku1043</u>](https://doi.org/10.1093/nar/gku1043)

6\. Levy, A., Sela, N. and Ast, G. (2008) TranspoGene and microTranspoGene: Transposed elements influence on the transcriptome of seven vertebrates and invertebrates. *Nucleic Acids Research*, **36**, D47–D52. [<u>doi:10.1093/nar/gkm949</u>](https://doi.org/10.1093/nar/gkm949)

7\. Li, C., Qian, Q., Yan, C., et al. (2024) HervD Atlas: A curated knowledgebase of associations between human endogenous retroviruses and diseases. *Nucleic Acids Research*, **52**, D1315–D1326. [<u>doi:10.1093/nar/gkad904</u>](https://doi.org/10.1093/nar/gkad904)

8\. Stricker, E., Peckham-Gregory, E.C. and Scheurer, M.E. (2023) CancerHERVdb: Human endogenous retrovirus (HERV) expression database for human cancer accelerates studies of the retrovirome and predictions for HERV-based therapies. *Journal of Virology*, **97**, e00059–23. [<u>doi:10.1128/jvi.00059-23</u>](https://doi.org/10.1128/jvi.00059-23)

9\. Meng, X., Nie, Z., Wang, Q., et al. (2026) TE-SCALE: A comprehensive database for exploring transposable element expression across human cancers at single-cell resolution. *Nucleic Acids Research*, **54**, D1658–D1671. [<u>doi:10.1093/nar/gkaf1235</u>](https://doi.org/10.1093/nar/gkaf1235)

10\. Edqvist, P.-H.D., Fagerberg, L., Hallström, B.M., et al. (2015) Expression of human skin-specific genes defined by transcriptomics and antibody-based profiling. *Journal of Histochemistry and Cytochemistry*, **63**, 129–141. [<u>doi:10.1369/0022155414562646</u>](https://doi.org/10.1369/0022155414562646)

11\. Uhlén, M., Fagerberg, L., Hallström, B.M., et al. (2015) Tissue-based map of the human proteome. *Science*, **347**, 1260419. [<u>doi:10.1126/science.1260419</u>](https://doi.org/10.1126/science.1260419)

12\. Ghandi, M., Huang, F.W., Jané-Valbuena, J., et al. (2019) Next-generation characterization of the cancer cell line encyclopedia. *Nature*, **569**, 503–508. [<u>doi:10.1038/s41586-019-1186-3</u>](https://doi.org/10.1038/s41586-019-1186-3)

13\. Benjamini, Y. and Hochberg, Y. (1995) Controlling the false discovery rate: A practical and powerful approach to multiple testing. *Journal of the Royal Statistical Society: Series B*, **57**, 289–300. [<u>doi:10.1111/j.2517-6161.1995.tb02031.x</u>](https://doi.org/10.1111/j.2517-6161.1995.tb02031.x)

14\. Blondel, V.D., Guillaume, J.-L., Lambiotte, R., et al. (2008) Fast unfolding of communities in large networks. *Journal of Statistical Mechanics: Theory and Experiment*, **2008**, P10008. [<u>doi:10.1088/1742-5468/2008/10/P10008</u>](https://doi.org/10.1088/1742-5468/2008/10/P10008)

15\. Lanciano, S. and Cristofari, G. (2020) Measuring and interpreting transposable element expression. *Nature Reviews Genetics*, **21**, 721–736. [<u>doi:10.1038/s41576-020-0251-y</u>](https://doi.org/10.1038/s41576-020-0251-y)
