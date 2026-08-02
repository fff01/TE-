# Writing a TE-KG Resource Article for *Database*

## Purpose

This note records the initial writing model for a TE-KG manuscript targeting
*Database: The Journal of Biological Databases and Curation*. It is based on the
journal's official instructions and a small set of representative articles from
the journal itself. It should guide the outline, figures, evidence inventory,
and first manuscript draft.

## Official Format

*Database* permits LaTeX submissions, although its instructions state that Word
is preferred. The journal is supported by the current OUP general LaTeX template.
The OUP support list specifies numbered citations and the following layout:

```latex
\documentclass[unnumsec,webpdf,modern,large]{oup-authoring-template}
\bibliographystyle{unsrt}
```

Official resources:

- Journal instructions: https://academic.oup.com/database/pages/instructions_for_authors
- OUP general template: https://www.overleaf.com/latex/templates/oup-general-template/ybpypwncdxyb
- CTAN package: https://ctan.org/pkg/oup-authoring-template

## Representative Articles

### Direct TE database precedents

1. **DPTEdb, an integrative database of transposable elements in dioecious
   plants** (2016)
   - https://doi.org/10.1093/database/baw078
   - Main structure: Introduction; Construction and Content of the Database;
     Results; Discussion; Conclusion; Accessibility.
   - Useful precedent for data sources, TE identification and classification,
     database contents, browse/search/download functions, and analysis tools.

2. **FishTEDB: a collective database of transposable elements identified in the
   complete genomes of fish** (2018)
   - https://doi.org/10.1093/database/bax106
   - Main structure: Introduction; Materials and methods; Results and discussion.
   - Useful precedent for a TE resource built from multiple identification
     approaches, explicit data counts, implementation details, and user tools.

### Integrated biological database precedents

3. **TransAtlasDB: an integrated database connecting expression data, metadata
   and variants** (2018)
   - https://doi.org/10.1093/database/bay014
   - Main structure: Introduction; System architecture; Data types; Database
     structure; Package toolkit; Future developments; Conclusion.
   - Useful precedent for explaining heterogeneous data storage and a PHP-based
     interface without forcing the paper into a conventional IMRaD structure.

4. **BuffExDb: web-based tissue-specific gene expression resource for breeding
   and conservation programmes in Bubalus bubalis** (2025)
   - https://doi.org/10.1093/database/baae128
   - Main structure: Introduction; Materials and methods; Results and discussion;
     Conclusion; Data availability.
   - Useful precedent for expression-data provenance, processing, quantitative
     database coverage, web visualization, and comparison with existing resources.

### Knowledge graph and AI precedents

5. **The TOXIN knowledge graph: supporting animal-free risk assessment of
   cosmetics** (2025)
   - https://doi.org/10.1093/database/baae121
   - Main structure: Introduction; Methodology; Results and discussion; Related
     work; Future directions; Conclusion; Data Availability.
   - Useful precedent for presenting a knowledge graph as a scientific resource:
     source integration, semantic model, provenance, interface, and a concrete
     domain use case are treated as one argument.

6. **LitSumm: large language models for literature summarization of noncoding
   RNAs** (2025)
   - https://doi.org/10.1093/database/baaf006
   - Main structure: Introduction; Materials and methods; Results; Discussion;
     Conclusion; Data availability.
   - Useful precedent for the Agent and DeepThink component because it reports
     prompt/checking logic, a fixed evaluation set, human-facing failure modes,
     reference accuracy, limitations, and reproducible code/data access.

## Common Writing Pattern

The representative papers vary structurally, but their argument usually follows
the same sequence:

1. Establish a biological or curation problem.
2. Show why current databases or workflows do not solve it adequately.
3. Define the new resource and its scope.
4. Document data sources, normalization, processing, and provenance.
5. Quantify the database rather than relying on adjectives such as
   "comprehensive" or "user-friendly."
6. Demonstrate important user workflows with concrete examples.
7. Explain what new biological questions or comparisons the resource enables.
8. State limitations, update plans, access routes, and data/code availability.

The abstract commonly compresses this into: problem and gap, resource, data
scale, main capabilities, biological value, and a database URL.

## Implications for TE-KG

### Recommended article structure

1. **Abstract**
   - Unmet need in fragmented TE sequence, taxonomy, genomic, expression,
     disease, and literature resources.
   - TE-KG's integrated graph-backed resource and web interface.
   - Quantified data scope and principal capabilities.
   - One or two validated use cases and the database URL.

2. **Introduction**
   - Biological importance and heterogeneity of transposable elements.
   - Existing TE databases and their strengths.
   - The remaining integration and evidence-navigation gap.
   - TE-KG's specific contributions, without claiming that every feature is novel.

3. **Data collection and integration**
   - Source databases, versions, licenses, and acquisition dates.
   - TE identifier and taxonomy normalization.
   - Sequence, genomic location, expression, disease/gene, pathway, and
     literature evidence processing.
   - Entity and relation provenance, deduplication, and quality control.

4. **System architecture and implementation**
   - Neo4j and MySQL responsibilities and the browser/PHP service layer.
   - Graph schema and principal node/relation types.
   - Reproducible import and update workflow.
   - Access, download, and runtime availability.

5. **Database content and web functionality**
   - Quantitative content summary.
   - Search, browse, entity preview, taxonomy, graph exploration, path finding,
     expression, co-expression, and downloads.
   - Agent and DeepThink as evidence-access interfaces, not as an unsupported
     claim of autonomous scientific reasoning.

6. **Evaluation and use cases**
   - Data coverage and integrity checks.
   - Representative TE queries spanning common TE classes.
   - A graph or co-expression use case that produces a biologically meaningful
     observation.
   - Agent/DeepThink evaluation focused on factual support, citation integrity,
     plugin coverage, and user-readable answers.

7. **Discussion**
   - What integration and graph navigation add beyond individual source sites.
   - Comparison with existing TE databases.
   - Current limitations, including source coverage and association-versus-
     causality boundaries.
   - Maintenance and update strategy.

8. **Conclusion**

9. **Data availability, code availability, funding, author contributions, and
   conflict of interest**

### Writing cautions

- Do not organize the main text as a page-by-page product tour.
- Do not describe a function as valuable without a data count, comparison,
  validation result, or worked scientific example.
- Do not let the knowledge graph become only an implementation detail; explain
  which cross-source questions it makes easier to answer.
- Do not let Agent/DeepThink dominate the manuscript. It is one access layer over
  the curated evidence and should be evaluated proportionally.
- Avoid unqualified terms such as "comprehensive," "accurate," "intelligent,"
  and "novel." Replace them with measurable scope and bounded claims.
- Keep internal software vocabulary and diagnostic labels out of user-facing
  examples and manuscript prose.

## Recommended Next Evidence Inventory

Before drafting prose, collect the following in a single auditable table:

- source name, version, license, acquisition date, and update method;
- counts for every node/entity type and relation type;
- TE taxonomy coverage by major class/order/family;
- species, tissues, samples, genomic records, expression records, publications,
  disease links, genes, and pathways;
- deduplication and normalization rules;
- validation checks and known missing coverage;
- stable database URL, source-code availability, and downloadable datasets;
- one candidate result or use case for each main manuscript figure.

The evidence inventory should be completed before writing the Abstract or making
strong novelty claims.
