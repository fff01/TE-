# TE-KG Manuscript Argument Map

Last reviewed: 2026-07-31

## Scientific Tension

Human TE evidence is distributed across literature, repeat classification,
genomic annotations, reference sequences, and context-dependent expression
datasets. These sources answer different questions and use different units of
evidence. A useful resource must connect them without making a literature
association look causal, a consensus sequence look locus-specific, or a
co-expression edge look regulatory.

## Central Question

How can a human TE resource integrate heterogeneous evidence and provide
traceable, biologically bounded routes from TE identity to literature,
classification, genomic context, expression, and co-expression information?

## Provisional Thesis

TE-KG provides a provenance-aware human TE resource in which literature-derived
relations, classification, representative genomic and sequence records,
expression profiles, and context-specific co-expression networks can be
accessed through connected graph, table, path, visualization, and question-
answering workflows while retaining the interpretation boundary of each layer.

This thesis is provisional until the literature comparison, data-release plan,
and worked use cases are complete.

## Supporting Arguments

### A1. The resource has a traceable literature core

- Current evidence: 2,308 Paper nodes, 12,444 directed biological relations,
  and PMID-bearing relation records.
- Reader takeaway: graph statements can be traced to literature records rather
  than appearing as anonymous edges.
- Required caution: relation predicates inherit the limitations of extraction,
  curation, and source papers.

### A2. TE identity connects complementary evidence layers

- Current evidence: Neo4j taxonomy, RMSK-derived genomic records,
  RepBase-derived identity and sequence records, and separate expression and
  co-expression stores.
- Reader takeaway: users can move between complementary evidence types through
  a common TE-centered resource.
- Required caution: the sources are linked, not collapsed into one evidence
  type.

### A3. Expression and co-expression add biological context

- Current evidence: 1,158 expression samples across three context classes and
  approved TE/gene display networks derived with a fixed correlation and FDR
  standard.
- Reader takeaway: the resource supports context-specific inspection beyond a
  static literature graph.
- Required caution: context datasets are not directly interchangeable, and
  correlation is not causation.

### A4. Multiple access workflows support different research questions

- Current evidence: Browse, Path, Graph, Expression, Download, and Agent/
  DeepThink interfaces with dedicated APIs and tests.
- Reader takeaway: a user can start from a name, a relationship, a path, an
  expression profile, or a natural-language question.
- Required caution: implementation breadth is not itself scientific utility;
  the paper must show a small number of worked, evidence-traceable use cases.

## Candidate Worked Use Cases

1. **L1HS evidence walk:** move from classification and consensus-sequence
   identity to representative genomic location, disease-linked literature, and
   context-specific expression/co-expression evidence.
2. **Cross-source graph exploration:** use Path or Knowledge Graph to inspect a
   TE-to-disease or TE-to-gene connection and open its paper evidence without
   upgrading association to causality.
3. **Context contrast:** compare one TE's expression and approved
   co-expression neighborhood across normal tissue, normal primary cell, and
   cancer cell line contexts.
4. **Bounded intelligent retrieval:** ask a follow-up question whose answer
   combines two or more local evidence layers while keeping citations and
   missing evidence visible.

The final use cases must be selected only after each has a complete evidence
chain and a figure/table contract.

## Counterarguments and Responses

| Reviewer concern | Evidence-based response required |
| --- | --- |
| Existing TE databases already provide classification, sequences, or expression. | Show a verified feature/provenance comparison and define the specific integration gap TE-KG addresses. |
| LLM extraction may introduce errors. | Describe constraints, normalization, manual curation, provenance retention, and a reproducible validation audit; disclose remaining uncertainty. |
| Co-expression networks can be overinterpreted. | Report preprocessing, thresholds, multiple-testing correction, context separation, sensitivity evidence, and explicit correlation-only language. |
| The web application is a collection of pages rather than a scientific resource. | Organize the paper around evidence layers and worked research workflows, not interface screenshots. |
| The intelligent interface may hallucinate or obscure evidence. | Keep it secondary, report bounded tool access and known limitations, and avoid unsupported accuracy claims. |
| Local availability is insufficient for a database paper. | Provide a durable public URL, versioned data/code deposits, licences, and availability statements before submission. |

## Limitations That Must Remain Visible

- unresolved intermediate literature-screening arithmetic;
- incomplete provenance metadata for some upstream data releases;
- normal-tissue metadata mismatches;
- uncertain feature classification cases;
- representative rather than exhaustive locus and display-network outputs;
- evidence-extraction and literature-retrieval limitations;
- historical Agent/DeepThink evaluation that does not yet represent a current
  end-to-end benchmark;
- no claim of experimental validation for graph relations or co-expression
  modules.

## Final Move

The paper should conclude that TE-KG's contribution is not merely the number of
interfaces or the use of a knowledge graph. Its value lies in making several
human TE evidence layers jointly accessible while keeping provenance and
interpretation boundaries explicit. The conclusion must remain conditional on
verified comparative positioning and durable public availability.

