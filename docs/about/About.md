# TE-KG About Guide

This document is the editorial source for the public About page. It records the user-facing copy and the concrete capture requirements for future static and animated illustrations. Image instructions describe what must be visible; they do not require a specific capture tool.

## 1. About TE-KG

TE-KG is a human transposable element resource that connects classification, literature-derived relationships, representative sequence and genomic records, expression and co-expression contexts, downloadable data, and evidence-bounded natural-language assistance.

### What TE-KG is

- TE-KG brings complementary views of human transposable elements into one interface instead of treating each dataset as an isolated table.
- The resource combines a TE catalog, entity detail pages, literature-derived relationship graphs, classification views, expression and co-expression exploration, and downloadable data.
- Representative sequence and genomic records describe supported references or annotations; they do not represent every genomic copy of a TE.
- The central goal is to make TE-related information explorable while keeping source evidence and interpretation limits visible.

### What this guide covers

- The guide explains what each public page is designed to answer.
- It describes the main controls on each page and the order in which users should use them.
- It separates TE lookup, entity detail, graph and path exploration, expression and co-expression analysis, natural-language assistance, and data download workflows.
- It also explains the main evidence boundaries so users can distinguish database observations from biological conclusions.

### Data access routes

- Use Home for high-level live dataset composition.
- Use Browse to scan the TE catalog, then open Search for a selected entity's detailed record.
- Use Graph for knowledge relationships, TE classification, and co-expression networks; use Path Finder for a focused connection between two entities.
- Use Expression for abundance patterns across the supported expression datasets.
- Use Agent or DeepThink for evidence-grounded natural-language questions and follow-up questions within the current conversation.
- Use Download when you need the files currently made available through the site.

### Evidence principles

- Relation-level claims should be checked against supporting papers when available; an observed association does not by itself establish causation.
- A graph path shows connections stored in the database, not necessarily a biological pathway or mechanism.
- Co-expression indicates context-specific correlation and does not by itself demonstrate regulation.
- PMID, title, year, journal, IF, and JCR are descriptive evidence metadata, not interchangeable confidence scores; missing values should remain missing.
- Counts shown by the TE catalog, knowledge graph, taxonomy, and co-expression catalog use different units and should not be compared as if they measured the same population.

**Static image specification: `about-resource-overview.png`**

Create a clean route map with `TE-KG` at the center and eight labeled destinations around it: `Browse`, `Search`, `Path Finder`, `Graph`, `Expression`, `Agent & DeepThink`, `Download`, and `About`. Draw arrows from Browse to Search and from Agent & DeepThink to Graph, Path Finder, Expression, and Download to show verification routes. Use the following English callouts:

- `Scan and filter the TE catalog` pointing to Browse.
- `Review a selected entity in detail` pointing to Search.
- `Explore knowledge, classification, and co-expression networks` pointing to Graph.
- `Ask evidence-grounded questions, then verify the sources` pointing to Agent & DeepThink.
- `Download the files currently exposed through the site` pointing to Download.

Add a footer note inside the figure: `Associations, paths, expression, and co-expression represent different evidence types.`

## 2. Home Overview

Home is the orientation layer for TE-KG. It introduces the resource, summarizes the current database composition, and provides direct routes into the main public workflows.

### What the page contains

- The Overview area summarizes the purpose and scope of TE-KG.
- Dataset Status reports live read-only statistics from the current knowledge graph rather than fixed numbers in the page source.
- The donut charts separate entity composition, TE classification, and relation predicate composition.
- Quick Links provide direct entry points into the main lookup, graph, expression, download, and help workflows.

### How to read Dataset Status

- Entity Composition counts major knowledge-graph node classes once per stored node.
- TE Classification can switch classification level, so the chart can move from broad classes to more specific taxonomy levels.
- Relation Composition uses detailed predicate-level statistics, making frequent relation types visible without collapsing them into vague labels.
- If live statistics cannot load, the page shows a fallback instead of inventing or guessing values.

### Recommended workflow

- Start here when you need a quick sense of what the database currently contains.
- Move to Browse when you want to scan TE records, Search when you need a detailed entity record, or Path Finder when you want a focused connection.
- Open Graph for visual knowledge, classification, or co-expression exploration, and use Agent when a question is easier to express in natural language.

**Static image specification: `about-home-overview.png`**

Capture the Home page at desktop width with the page introduction, all Dataset Status charts, and Quick Links visible. Use numbered markers and these English labels:

- `Read the resource scope before choosing a workflow` on the overview text.
- `Check the current knowledge-graph composition` on Dataset Status.
- `Change the classification level shown in this chart` on the TE Classification control.
- `Compare detailed relationship predicates` on Relation Composition.
- `Open the workflow that matches your question` on Quick Links.

The annotation must not suggest that chart segment sizes across different charts use the same denominator.

## 3. Browse

Browse is the table-first TE catalog. It is designed for scanning and filtering TE records before opening a selected TE in the detailed Search view.

### What the page is for

- Use Browse when you want a catalog-style view of TE records rather than a graph-first exploration.
- The table presents TE name, class, family, subtype, and description for side-by-side comparison.
- The catalog supports pagination so large result sets remain easy to scan.
- Select a TE name from the table when you are ready to inspect its detailed Search record.

### Using the selector

- Use the class, family, and subtype controls to narrow the catalog by TE classification.
- Type a TE name or prefix in the search field, then choose a database-backed suggestion when one is available.
- Combine text search with classification filters to reduce a broad result set.
- Clear one or more conditions when no records match the current filter combination.

### Data interpretation

- Browse is a TE catalog, so its row count is not the same as the number of TE nodes, taxonomy leaves, or co-expression features shown elsewhere.
- Class, family, and subtype values describe the catalog lineage associated with each displayed record.
- Browse is optimized for discovery and comparison; use Search, Graph, or Path Finder when detailed records or relation evidence matter.

**Animated image specification: `about-browse-selector.gif`**

Record a 10-12 second desktop demonstration. Start on an unfiltered Browse table. Open the Class filter and select `Retrotransposons`, select `Non LTR Retrotransposons (LINEs)` under Family, type `L1HS` in the TE search field, choose the database suggestion, and show the filtered row count. Click the L1HS row and finish when its Search detail page is visibly loaded. Keep the pointer visible and pause for about one second after each result update. The animation must demonstrate the handoff from catalog discovery to entity detail; it must not imply that Browse searches diseases or functions.

## 4. Search and Entity Detail

Search opens a detailed record for a TE or another supported entity. Available panels depend on the selected entity and may include a summary, local graph, representative sequence, genome annotation, and genome browser.

### Finding a record

- Search for a TE, disease, function, or PMID, or arrive from a linked TE name in Browse.
- Choose a database-backed suggestion when several names are possible so the entity type remains clear.
- The summary identifies the selected record and provides the metadata available for that entity type.
- Not every panel appears for every entity; the page only shows detail views supported by the selected record.

### Reading TE detail

- Use Local Graph to inspect nearby entities and relations without leaving the detail page.
- Sequence displays a supported representative or consensus sequence and its available annotation, not every genomic TE copy.
- Genome Annotation Distribution summarizes supported hits across the displayed assembly, while Genome Browser provides locus-level inspection.
- Use the page navigation to move directly to an available panel in a long record.

### Interpretation boundaries

- A missing panel means that the corresponding record is not available for the selected entity in the current resource.
- Sequence and genome panels may come from different reference or annotation layers and should be interpreted using their displayed labels.
- A local graph relation is a database-supported association; review its source evidence before treating it as a biological mechanism.

**Static image specification: `about-search-l1hs-detail.png`**

Use a full-page or vertically stitched L1HS detail capture. Keep Summary, Local Graph, Sequence, Genome Annotation Distribution, and Genome Browser recognizable. Add these English callouts to the specified regions:

- `Confirm the entity identity and available metadata` on Summary.
- `Inspect nearby database relationships` on Local Graph.
- `Representative or consensus sequence; not every genomic copy` on Sequence.
- `Review the assembly and total annotation hits` on the distribution metadata.
- `Inspect supported loci in genomic context` on Genome Browser.

Do not annotate an unavailable panel as if it appears for all entity types.

## 5. Path Finder

Path Finder searches stored connections between two selected entities and presents each relation with reviewable evidence. It is useful when the question concerns a specific connection rather than a single record.

### Search structure

- Each side of the search has a narrow category selector and a wider entity selector inside the same search box.
- The entity dropdown is constrained by the selected category.
- Select a suggestion for both endpoints before starting the search.
- This structure avoids mixing categories in one uncontrolled autocomplete list.

### Reading path results

- The path shows the sequence of entities and relations connecting the selected endpoints.
- Relation labels should be interpreted at the detailed predicate level when that detail is available.
- Under each relation, evidence is shown as a table rather than a loose PMID list.
- Evidence rows can include PMID, year, journal, IF, JCR, match type, and title.
- A returned graph path is a chain of stored relationships; it is not automatically a biological pathway or mechanistic model.

### Evidence checks

- Use PMID and title to identify the supporting publication.
- Use journal, IF, and JCR as descriptive metadata, not as a replacement for reading the source.
- Use match type to understand how publication and journal metadata were linked.
- When a path has multiple relations, review each relation separately rather than assuming the whole path has uniform support.

**Animated image specification: `about-pathfinder-search.gif`**

Record a complete two-endpoint search. Select `TE` and `L1HS` on the left, select a supported target category and entity on the right, run the search, then pause on the returned path. Expand or scroll to one relation evidence table and click a PMID link so the PubMed destination is apparent. End back on the evidence table if opening the link replaces the page. Include a small final overlay: `A stored graph path is not automatically a biological pathway.`

## 6. Graph Workspace

Graph provides three complementary visual workflows: literature-derived knowledge relationships, TE classification in Tree or force-directed Graph form, and a separate context-specific co-expression network.

### Graph interaction

- Keep Knowledge Graph selected to search for an entity and load its visible relationship neighborhood.
- Pan, zoom, and move nodes to inspect a dense network; use node actions to open entity-specific options and metadata.
- Open relation evidence when you need the publications supporting a visible edge.
- Use Export when you need an available snapshot or data representation of the current knowledge-graph view.

### Legend and filters

- Entity legends distinguish TE, disease, function, paper, and other visible node types.
- Relation legends expose predicate categories so a dense view can be narrowed to the relationships relevant to the question.
- Clicking legend items temporarily changes what is emphasized or displayed in the current view; it does not change the stored data.
- Always interpret a filtered graph together with the active legend state.

### Classification Tree and Graph

- When a TE classification is displayed, use Tree for a stable hierarchical view or Graph for a force-directed view that can be rearranged interactively.
- Use All or RMSK + RepBase to change the classification source scope shown by either display.
- Tree and Graph are two layouts of the same classification data, not separate taxonomy sources.
- Node spacing and occupied area in the force-directed Graph reflect layout behavior and hierarchy size, not biological abundance or prevalence.

### Co-expression workspace

- Switch to Co-expression, choose a context and TE, then search to load a bounded TE-gene correlation neighborhood.
- Use the legend to show or hide TE and Gene nodes, identify module hubs, and choose the visible edge scope.
- Expression activity is a separate node-level layer for the selected context and does not encode correlation strength or causality.
- Co-expression is context-specific association evidence; it does not by itself demonstrate regulation, mechanism, or a complete offline network.

**Static image specification: `about-graph-knowledge.png`**

Capture a loaded Knowledge Graph with the workspace switch, search box, entity legend, relation legend, one selected node, and a visible evidence control. Use these callouts:

- `Switch between knowledge and co-expression workflows` on the workspace tabs.
- `Search for an entity and load its neighborhood` on the search controls.
- `Click a legend item to change the current view temporarily` on the legend.
- `Open source evidence for a visible relationship` on the edge evidence control.
- `Move and filter the view without changing stored data` on the graph canvas.

**Animated image specification: `about-graph-classification.gif`**

Begin with a classification Tree visible. Switch from `Tree` to `Graph`, wait for the force-directed layout to settle, drag one TE node so surrounding nodes respond, click one class legend item to hide it temporarily, restore it, then switch from `RMSK + RepBase` to `All`. End by returning to Tree. Keep the Tree/Graph and source-scope controls visible throughout. The animation should make clear that both layouts show the same classification data and that graph area is not an abundance scale.

**Animated image specification: `about-graph-coexpression.gif`**

Switch from Knowledge Graph to Co-expression. Select a context, choose a TE, and run Search. After the network loads, toggle Gene visibility off and on, point to the module-hub legend, change the edge scope, toggle `Expression activity`, and export a PNG or open the export menu. Finish with the correlation notice visible. Add a final overlay: `Correlation and expression activity do not imply causation.`

## 7. Agent and DeepThink

Agent is the natural-language research surface. Agent mode gathers evidence through a structured multi-stage workflow, while DeepThink provides a shorter evidence-grounded reasoning flow for more direct questions.

### Choosing a mode

- Use Agent for research reports or questions that may require evidence from several database areas, such as sequence, genomic location, expression, disease links, and literature.
- Use DeepThink for a more direct question when a shorter reasoning and writing flow is sufficient.
- Use clear TE names, disease terms, gene names, or PMIDs when possible; ask for clarification when an abbreviation or entity name is ambiguous.
- The visible stage trace shows how the request progresses from understanding and planning to evidence collection and writing.

### Questions and follow-ups

- Ask about TE classification, sequences, genomic records, expression, co-expression, graph relationships, diseases, genes, or literature evidence.
- After the first answer, use a follow-up question without repeating the full topic.
- Conversation context is retained only for the current browser conversation; reloading the page or starting a new session does not preserve it.
- Open cited PMID links to review the corresponding PubMed records when literature evidence matters.

### Reading the answer

- Treat the answer as a synthesis of the evidence retrieved for that request, not as independent experimental validation.
- Verify important relations in Graph or Path Finder, expression patterns in Expression, and downloadable contents in Download.
- A statement may be appropriately limited when the database lacks the requested evidence; absence from the retrieved result is not proof of biological absence.
- Journal metrics are descriptive metadata rather than confidence scores, and association language should not be read as causal language.

**Animated image specification: `about-agent-follow-up.gif`**

Start on the empty Agent page. Select Agent mode and ask: `Summarize the evidence available for L1HS, including disease links and literature.` Show the stage trace progressing and the completed answer with at least one clickable PMID. Then ask: `Which of those links has the strongest direct literature support?` without repeating L1HS. Show that the follow-up retains the current conversation context and finishes normally. Finally, point to one PMID link. The animation must avoid exposing raw plugin names, internal flags, JSON, or developer diagnostics.

**Static image specification: `about-agent-modes.png`**

Capture the empty state with both mode controls and short task templates visible. Add two callouts:

- `Use Agent for multi-source research questions` on Agent.
- `Use DeepThink for a shorter evidence-grounded response` on Deep Think.

Add a small note below the composer: `Follow-up context lasts only for the current browser conversation.`

## 8. Expression

Expression is the TE abundance lookup surface. It supports catalog-level filtering and TE detail views across the available normal tissue, normal cell line, and cancer cell line datasets.

### Finding a TE

- Use Keyword to search for a TE, or combine dataset source, top-context text, and minimum global median filters to narrow the table.
- Use Sort to order the catalog by the available summary measures, then select a TE row for its detail view.
- The browse table summarizes the top normal tissue, normal cell line, and cancer cell line context when those datasets are available.
- If a TE is not suggested or returned, verify that it is present in the current expression catalog before interpreting the absence.

### Reading expression views

- Use the detail summary to confirm the available datasets and the selected median, mean, or maximum metric.
- Read Normal Tissue, Normal Cell Line, and Cancer Cell Line as separate study contexts, not as a matched cohort comparison.
- Use the plots to compare contexts within the displayed dataset and metric.
- Keep expression abundance separate from knowledge-graph relations and co-expression correlations; they answer different questions.

### Data notes

- Expression values should be interpreted within the displayed dataset, metric, and preprocessing context.
- Differences across normal tissue, normal cell line, and cancer cell line views may reflect both biology and study design.
- Download provides the expression matrices and metadata currently exposed through the site for independent inspection.

**Static image specification: `about-expression-detail.png`**

Capture one TE detail page with Summary and all three available dataset sections visible in a stitched vertical image. Add these callouts:

- `Choose Median, Mean, or Max before comparing values` on the metric control.
- `Confirm which datasets are available for this TE` on Summary.
- `Compare contexts within the Normal Tissue dataset` on the first plot.
- `Normal Cell Line is a separate study context` on the second plot.
- `Cancer Cell Line is not a matched cohort comparison` on the third plot.

Include a footer annotation: `Interpret values within the displayed dataset and preprocessing context.`

## 9. Download

Download lists the data files currently made available through TE-KG. The table helps users compare dataset name, filename, site usage, format, and a short description before downloading.

### Table layout

- Dataset names identify each downloadable resource at a human-readable level.
- File links point to the downloadable file path.
- Used in explains which page or pipeline currently depends on that file.
- Format identifies the exposed file type.

### Filtering downloads

- Use category filter buttons such as Expression, Graph, or Taxonomy to narrow the table.
- Use Search to match dataset names, filenames, usage descriptions, formats, and row descriptions.
- Expand a dataset row when you need a short explanation before downloading.
- Clear the search text or return to All when you want to see the complete current download catalogue.

### Catalogue scope

- Files listed here correspond to visible TE-KG workflows or data that can be reviewed independently.
- Internal intermediate outputs and archived working files are not included by default.
- Availability on this page identifies a current site download; it is not by itself a versioned archival release.
- For a formal release, use the accompanying stable identifier, version, checksum, and licence information when those fields are provided.

**Animated image specification: `about-download-filter.gif`**

Start with All selected. Click `Expression`, type part of an expression filename in Search, expand the matching row to show its description, and click the filename link without completing a disruptive browser download. Clear the search, select `Taxonomy`, and return to All. Keep the category counts, Dataset, File, Used in, and Format columns visible. End with a brief note: `A current site download is not automatically a versioned archival release.`

## 10. About

About is the detailed guide to the TE-KG public interface. It explains what each page does, how to use it, and how the pages relate to one another.

### How this guide is organized

- Use the left navigation to switch between page-specific guides.
- Each section describes purpose, controls, data interpretation, and important boundaries.
- The guide is written for users who need to decide where to start and how to verify what they find.
- The text focuses on public interface behavior rather than internal implementation details.

### Choosing the right workflow

- Use Browse for TE catalog lookup and Search for a selected entity's detailed record.
- Use Path Finder for entity-to-entity connection questions.
- Use Graph for knowledge relationships, TE classification, co-expression, and relation evidence inspection.
- Use Expression for TE abundance patterns across supported expression datasets.
- Use Download for the files currently available through the site.
- Use Agent or DeepThink for natural-language synthesis, then verify important claims in the relevant evidence view.

### Evidence-first reading

- Prefer views that expose source records when making relation-level claims.
- Distinguish association, graph connection, expression abundance, and co-expression correlation before drawing a biological conclusion.
- Do not interpret journal IF as confidence, and do not infer missing journal metrics.
- When results differ between pages, first check the entity, dataset, context, metric, and evidence type shown by each page.

**Static image specification: `about-guide-navigation.png`**

Capture the About page with the left navigation, Search field, one selected parent section, its child headings, and matching content visible. Use these callouts:

- `Search across all guide text` on the guide search field.
- `Choose a page guide` on the parent navigation.
- `Jump to a specific task or interpretation note` on the child navigation.
- `Read purpose, workflow, and evidence boundaries together` on the content area.

The image should teach navigation only and should not repeat annotations already used for the individual product pages.
