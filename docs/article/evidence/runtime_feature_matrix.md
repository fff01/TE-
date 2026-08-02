# TE-KG Runtime Feature Matrix

Last reviewed: 2026-08-01

This matrix records manuscript-visible capabilities. It describes implemented
behavior, not scientific validation or usability performance.

| capability | user entrypoint | canonical data dependency | implemented behavior | main boundary | verification evidence |
| --- | --- | --- | --- | --- | --- |
| Home overview | `index.php` | Neo4j/API taxonomy and resource summaries | Presents the resource and entry routes | Overview graphics are summaries, not new truth sources | architecture and homepage checks |
| Browse | `browse.php`, `api/browse.php` | MySQL `tekg_catalog` | Versioned TE catalog, filtering, table browsing, and autocomplete | 276 Browse rows are not the 225 Neo4j TE nodes | live API response; Browse contract checks |
| Search/detail | `search.php`, detail templates | Neo4j plus sequence/genome assets | Entity detail, relations, evidence, classification, and linked views | Available fields vary by entity and source | current runtime code; stable reference surface |
| Path | `path_finder.php`, `api/path_finder.php` | Neo4j `tekg3` | Finds and presents graph connections between selected entities in table or graph form | A graph path is not automatically a biological pathway or mechanism | path contract/browser checks |
| Knowledge Graph | `preview.php`, `api/graph.php` | Neo4j `tekg3` | Entity-centered network exploration, relation evidence, legend focus, expansion, and export | Stored associations and relation predicates do not by themselves prove causality | graph API, G6 contract, and browser checks |
| TE classification Tree | `preview.php`, `api/taxonomy.php` | taxonomy tree response tied to current taxonomy contract | Hierarchical Tree view with source switching | Historical tree files are build/view inputs, not a second independent runtime taxonomy | taxonomy contract checks |
| TE classification Graph | `preview.php`; Canvas renderer | same taxonomy tree payload | Force-directed Canvas graph with Tree/Graph switch and interactive legend | Layout area and visual weight do not represent biological abundance | Canvas integration/browser checks |
| Expression | `expression.php`, `expression_detail.php` | MySQL summaries derived from `data/bulk_expression_web` | Search, filter, summarize, and plot TE/gene expression by context | Expression level is not network importance or regulation | expression API/browser checks |
| Co-expression | `preview.php`, `api/coexpression.php` | MySQL `coexpression_*` tables | TE- or Gene-centered context-specific graph, expression layer, legend focus, and CSV/PNG/SVG export | Correlation only; bounded display graph; unavailable contexts remain unavailable | 849-network parity record; representative browser checks |
| Agent | `agent.php`, asynchronous Agent APIs | evidence plugins over graph, literature, taxonomy, expression, genome, and sequence sources | Six-stage multi-step evidence collection and report-style writing | Slower and more failure-prone; evidence synthesis is not independent validation | Agent tests and evaluation register |
| DeepThink | `agent.php`, `api/deep_think_stream.php` | same bounded evidence-plugin ecosystem | Four-stage direct evidence-grounded response | Lighter workflow; writing and retrieval can still fail | DeepThink tests and evaluation register |
| Session follow-up | Agent and DeepThink on current page | bounded server memory plus page-held session identifier | Resolves references to recent validated entities for up to three successful turns | Reload/new tab does not restore prior conversation; prior answers are not scientific evidence | context contract, tests, browser record |
| Download | `download.php` | ten configured public files | Category filtering, search, pagination, and direct file links | Runtime download is not archival publication | download smoke check and source review |
| About/help | `about.php` | maintained explanatory assets | Provides task-oriented help and screenshots | Help content must remain synchronized with runtime | About smoke check |

## Interfaces to Emphasize in the Paper

The main paper should organize interfaces into scientific access workflows:

1. identify and classify a TE;
2. inspect literature-derived graph evidence and paths;
3. inspect representative genomic and sequence records;
4. compare expression contexts and co-expression neighborhoods;
5. retrieve a bounded cross-layer answer through Agent or DeepThink.

Home, About, toolbar details, and individual export controls belong in a short
implementation description or supplementary user guide unless they support a
specific scientific workflow.

## Claims the Feature Matrix Does Not Support

- that all interfaces are equally complete or scientifically important;
- that an implemented control is intuitive or usable without a user study;
- that a passing browser test validates biological content;
- that Agent or DeepThink answers are generally accurate;
- that the current local deployment is publicly durable;
- that every TE has every evidence layer.

