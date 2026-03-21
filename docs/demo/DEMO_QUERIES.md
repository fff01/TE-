# Demo Queries

This file organizes the most useful Neo4j queries for the current project stage.

## Current status

The graph already contains:

- `TE` nodes
- `Disease` nodes
- `Function` nodes
- `Paper` nodes
- `SUBFAMILY_OF` lineage edges
- `BIO_RELATION` core knowledge edges
- `EVIDENCE_RELATION` paper evidence edges

This is enough for demonstration, graph exploration, and the first version of a QA pipeline.

## Recommended presentation order

### 1. Show the LINE-1 lineage

Purpose:
Show that the graph stores biological hierarchy, not only flat entities.

Use query:
See `Demo 1` in [demo_queries.cypher](/d:/wamp64/www/TE-/docs/demo/demo_queries.cypher).

### 2. Show LINE-1 related mechanisms

Purpose:
Demonstrate that the graph captures mechanistic knowledge such as retrotransposition and DNA damage.

Use query:
See `Demo 2` in [demo_queries.cypher](/d:/wamp64/www/TE-/docs/demo/demo_queries.cypher).

### 3. Show LINE-1 related diseases

Purpose:
Demonstrate that the graph supports disease-oriented exploration.

Use query:
See `Demo 3` in [demo_queries.cypher](/d:/wamp64/www/TE-/docs/demo/demo_queries.cypher).

### 4. Show evidence support

Purpose:
Show that graph relations are linked to literature evidence.

Use queries:

- `Demo 4` for `LINE-1`
- `Demo 5` for `L1HS`
- `Demo 8` for one paper and its reported entities

### 5. Show a small neighborhood graph

Purpose:
This is the best query for front-end graph display because it returns a compact local subgraph.

Use query:
See `Demo 7` in [demo_queries.cypher](/d:/wamp64/www/TE-/docs/demo/demo_queries.cypher).

## Best fit for the next modules

### For visualization

Use:

- `Demo 1`
- `Demo 2`
- `Demo 3`
- `Demo 7`

These are the best starting points for Cytoscape.js or ECharts network rendering.

### For QA

Use:

- `Demo 2`
- `Demo 3`
- `Demo 4`
- `Demo 6`

These match question patterns such as:

- `LINE-1 参与哪些功能？`
- `LINE-1 和哪些疾病相关？`
- `哪些文献提到 L1HS？`
- `LINE-1 可能通过什么机制与疾病关联？`

## What is still not fully done

The project is not completely finished yet. The major remaining tasks are:

- refine name normalization rules
- improve or repair text descriptions with encoding issues
- connect Neo4j query results to the front-end page
- package a controlled question-to-query workflow for the QA module
- prepare screenshots and a stable live demo flow

## Practical conclusion

At this stage, the most valuable next move is not more raw importing.
It is to stabilize the demo queries and use them as the bridge to:

- web visualization
- QA retrieval
- defense presentation
