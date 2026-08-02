<?php
require_once __DIR__ . '/path_config.php';
$pageTitle = 'TE-KG About';
$activePage = 'about';
$protoCurrentPath = tekg_app_url('about.php');
$protoSubtitle = 'Detailed guide to the TE-KG interface and public workflows';
$aboutCssVersion = (int)@filemtime(tekg_assets_fs_path('css/pages/about.css'));
$aboutJsVersion = (int)@filemtime(tekg_assets_fs_path('js/pages/about.js'));
$pageExtraStylesheets = [
    tekg_assets_url('css/pages/about.css') . '?v=' . $aboutCssVersion,
];
require __DIR__ . '/head.php';

$aboutSections = [
    'resource' => [
        'nav' => 'Resource',
        'title' => 'About TE-KG',
        'summary' => 'TE-KG is a human transposable element resource that connects classification, literature-derived relationships, representative sequence and genomic records, expression and co-expression contexts, downloadable data, and evidence-bounded natural-language assistance.',
        'sections' => [
            [
                'heading' => 'What TE-KG is',
                'items' => [
                    'TE-KG brings complementary views of human transposable elements into one interface instead of treating each dataset as an isolated table.',
                    'The resource combines a TE catalog, entity detail pages, literature-derived relationship graphs, classification views, expression and co-expression exploration, and downloadable data.',
                    'Representative sequence and genomic records describe supported references or annotations; they do not represent every genomic copy of a TE.',
                    'The central goal is to make TE-related information explorable while keeping source evidence and interpretation limits visible.',
                ],
            ],
            [
                'heading' => 'What this guide covers',
                'items' => [
                    'The guide explains what each public page is designed to answer.',
                    'It describes the main controls on each page and the order in which users should use them.',
                    'It separates TE lookup, entity detail, graph and path exploration, expression and co-expression analysis, natural-language assistance, and data download workflows.',
                    'It also explains the main evidence boundaries so users can distinguish database observations from biological conclusions.',
                ],
            ],
            [
                'heading' => 'Data access routes',
                'items' => [
                    'Use Home for high-level live dataset composition.',
                    'Use Browse to scan the TE catalog, then open Search for a selected entity\'s detailed record.',
                    'Use Graph for knowledge relationships, TE classification, and co-expression networks; use Path Finder for a focused connection between two entities.',
                    'Use Expression for abundance patterns across the supported expression datasets.',
                    'Use Agent or DeepThink for evidence-grounded natural-language questions and follow-up questions within the current conversation.',
                    'Use Download when you need the files currently made available through the site.',
                ],
            ],
            [
                'heading' => 'Evidence principles',
                'items' => [
                    'Relation-level claims should be checked against supporting papers when available; an observed association does not by itself establish causation.',
                    'A graph path shows connections stored in the database, not necessarily a biological pathway or mechanism.',
                    'Co-expression indicates context-specific correlation and does not by itself demonstrate regulation.',
                    'PMID, title, year, journal, IF, and JCR are descriptive evidence metadata, not interchangeable confidence scores; missing values should remain missing.',
                    'Counts shown by the TE catalog, knowledge graph, taxonomy, and co-expression catalog use different units and should not be compared as if they measured the same population.',
                ],
            ],
        ],
    ],
    'home' => [
        'nav' => 'Home',
        'title' => 'Home Overview',
        'summary' => 'Home is the orientation layer for TE-KG. It introduces the resource, summarizes the current database composition, and provides direct routes into the main public workflows.',
        'sections' => [
            [
                'heading' => 'What the page contains',
                'items' => [
                    'The Overview area summarizes the purpose and scope of TE-KG.',
                    'Dataset Status reports live read-only statistics from the current knowledge graph rather than fixed numbers in the page source.',
                    'The donut charts separate entity composition, TE classification, and relation predicate composition.',
                    'Quick Links provide direct entry points into the main lookup, graph, expression, download, and help workflows.',
                ],
            ],
            [
                'heading' => 'How to read Dataset Status',
                'items' => [
                    'Entity Composition counts major knowledge-graph node classes once per stored node.',
                    'TE Classification can switch classification level, so the chart can move from broad classes to more specific taxonomy levels.',
                    'Relation Composition uses BIO_RELATION predicate-level statistics, making frequent relation types visible without collapsing them into vague labels.',
                    'If live statistics cannot load, the page shows a fallback instead of inventing or guessing values.',
                ],
            ],
            [
                'heading' => 'Recommended workflow',
                'items' => [
                    'Start here when you need a quick sense of what the database currently contains.',
                    'Move to Browse when you want to scan TE records, Search when you need a detailed entity record, or Path Finder when you want a focused connection.',
                    'Open Graph for visual knowledge, classification, or co-expression exploration, and use Agent when a question is easier to express in natural language.',
                ],
            ],
        ],
    ],
    'browse' => [
        'nav' => 'Browse',
        'title' => 'Browse',
        'summary' => 'Browse is the table-first TE catalog. It is designed for scanning and filtering TE records before opening a selected TE in the detailed Search view.',
        'sections' => [
            [
                'heading' => 'What the page is for',
                'items' => [
                    'Use Browse when you want a catalog-style view of TE records rather than a graph-first exploration.',
                    'The table presents TE name, class, family, subtype, and description for side-by-side comparison.',
                    'The catalog supports pagination so large result sets remain easy to scan.',
                    'Select a TE name from the table when you are ready to inspect its detailed Search record.',
                ],
            ],
            [
                'heading' => 'Using the selector',
                'items' => [
                    'Use the class, family, and subtype controls to narrow the catalog by TE classification.',
                    'Type a TE name or prefix in the search field, then choose a database-backed suggestion when one is available.',
                    'Combine text search with classification filters to reduce a broad result set.',
                    'Clear one or more conditions when no records match the current filter combination.',
                ],
            ],
            [
                'heading' => 'Data interpretation',
                'items' => [
                    'Browse is a TE catalog, so its row count is not the same as the number of TE nodes, taxonomy leaves, or co-expression features shown elsewhere.',
                    'Class, family, and subtype values describe the catalog lineage associated with each displayed record.',
                    'Browse is optimized for discovery and comparison; use Search, Graph, or Path Finder when detailed records or relation evidence matter.',
                ],
            ],
        ],
    ],
    'search' => [
        'nav' => 'Search',
        'title' => 'Search and Entity Detail',
        'summary' => 'Search opens a detailed record for a TE or another supported entity. Available panels depend on the selected entity and may include a summary, local graph, representative sequence, genome annotation, and genome browser.',
        'sections' => [
            [
                'heading' => 'Finding a record',
                'items' => [
                    'Search for a TE, disease, function, or PMID, or arrive from a linked TE name in Browse.',
                    'Choose a database-backed suggestion when several names are possible so the entity type remains clear.',
                    'The summary identifies the selected record and provides the metadata available for that entity type.',
                    'Not every panel appears for every entity; the page only shows detail views supported by the selected record.',
                ],
            ],
            [
                'heading' => 'Reading TE detail',
                'items' => [
                    'Use Local Graph to inspect nearby entities and relations without leaving the detail page.',
                    'Sequence displays a supported representative or consensus sequence and its available annotation, not every genomic TE copy.',
                    'Genome Annotation Distribution summarizes supported hits across the displayed assembly, while Genome Browser provides locus-level inspection.',
                    'Use the page navigation to move directly to an available panel in a long record.',
                ],
            ],
            [
                'heading' => 'Interpretation boundaries',
                'items' => [
                    'A missing panel means that the corresponding record is not available for the selected entity in the current resource.',
                    'Sequence and genome panels may come from different reference or annotation layers and should be interpreted using their displayed labels.',
                    'A local graph relation is a database-supported association; review its source evidence before treating it as a biological mechanism.',
                ],
            ],
        ],
    ],
    'pathfinder' => [
        'nav' => 'Path Finder',
        'title' => 'Path Finder',
        'summary' => 'Path Finder searches stored connections between two selected entities and presents each relation with reviewable evidence. It is useful when the question concerns a specific connection rather than a single record.',
        'sections' => [
            [
                'heading' => 'Search structure',
                'items' => [
                    'Each side of the search has a narrow category selector and a wider entity selector inside the same search box.',
                    'The entity dropdown is constrained by the selected category, so choosing TE gives TE names and choosing Disease gives disease names.',
                    'Select a suggestion for both endpoints before starting the search.',
                    'This structure avoids mixing categories in one uncontrolled autocomplete list.',
                ],
            ],
            [
                'heading' => 'Reading path results',
                'items' => [
                    'The path itself shows the sequence of entities and relations connecting the selected endpoints.',
                    'Relation labels should be interpreted at the detailed predicate level, such as activate or affect, when that detail is available.',
                    'Under each relation, evidence is shown as a table rather than a loose PMID list.',
                    'Evidence rows can include PMID, year, journal, IF, JCR, match type, and title.',
                    'A returned graph path is a chain of stored relationships; it is not automatically a biological pathway or mechanistic model.',
                ],
            ],
            [
                'heading' => 'Evidence checks',
                'items' => [
                    'Use PMID and title to identify the supporting publication.',
                    'Use journal, IF, and JCR as descriptive metadata, not as a replacement for reading the source.',
                    'Use match type to understand how publication and journal metadata were linked.',
                    'When a path has multiple relations, review each relation separately rather than assuming the whole path has uniform support.',
                ],
            ],
        ],
    ],
    'tekg' => [
        'nav' => 'Graph',
        'title' => 'Graph Workspace',
        'summary' => 'Graph provides three complementary visual workflows: literature-derived knowledge relationships, TE classification in Tree or force-directed Graph form, and a separate context-specific co-expression network.',
        'sections' => [
            [
                'heading' => 'Graph interaction',
                'items' => [
                    'Keep Knowledge Graph selected to search for an entity and load its visible relationship neighborhood.',
                    'Pan, zoom, and move nodes to inspect a dense network; use node actions to open entity-specific options and metadata.',
                    'Open relation evidence when you need the publications supporting a visible edge.',
                    'Use Export when you need an available snapshot or data representation of the current knowledge-graph view.',
                ],
            ],
            [
                'heading' => 'Legend and filters',
                'items' => [
                    'Entity legends distinguish TE, disease, function, paper, and other visible node types.',
                    'Relation legends expose predicate categories so a dense view can be narrowed to the relationships relevant to the question.',
                    'Clicking legend items temporarily changes what is emphasized or displayed in the current view; it does not change the stored data.',
                    'Always interpret a filtered graph together with the active legend state.',
                ],
            ],
            [
                'heading' => 'Classification Tree and Graph',
                'items' => [
                    'When a TE classification is displayed, use Tree for a stable hierarchical view or Graph for a force-directed view that can be rearranged interactively.',
                    'Use All or RMSK + RepBase to change the classification source scope shown by either display.',
                    'Tree and Graph are two layouts of the same classification data, not separate taxonomy sources.',
                    'Node spacing and occupied area in the force-directed Graph reflect layout behavior and hierarchy size, not biological abundance or prevalence.',
                ],
            ],
            [
                'heading' => 'Co-expression workspace',
                'items' => [
                    'Switch to Co-expression, choose a context and TE, then search to load a bounded TE-gene correlation neighborhood.',
                    'Use the legend to show or hide TE and Gene nodes, identify module hubs, and choose the visible edge scope.',
                    'Expression activity is a separate node-level layer for the selected context and does not encode correlation strength or causality.',
                    'Co-expression is context-specific association evidence; it does not by itself demonstrate regulation, mechanism, or a complete offline network.',
                ],
            ],
        ],
    ],
    'agent' => [
        'nav' => 'Agent',
        'title' => 'Agent and DeepThink',
        'summary' => 'Agent is the natural-language research surface. Agent mode gathers evidence through a structured multi-stage workflow, while DeepThink provides a shorter evidence-grounded reasoning flow for more direct questions.',
        'sections' => [
            [
                'heading' => 'Choosing a mode',
                'items' => [
                    'Use Agent for research reports or questions that may require evidence from several database areas, such as sequence, genomic location, expression, disease links, and literature.',
                    'Use DeepThink for a more direct question when a shorter reasoning and writing flow is sufficient.',
                    'Use clear TE names, disease terms, gene names, or PMIDs when possible; ask for clarification when an abbreviation or entity name is ambiguous.',
                    'The visible stage trace shows how the request progresses from understanding and planning to evidence collection and writing.',
                ],
            ],
            [
                'heading' => 'Questions and follow-ups',
                'items' => [
                    'Ask about TE classification, sequences, genomic records, expression, co-expression, graph relationships, diseases, genes, or literature evidence.',
                    'After the first answer, use a follow-up question such as “Which of these links has literature support?” without repeating the full topic.',
                    'Conversation context is retained only for the current browser conversation; reloading the page or starting a new session does not preserve it.',
                    'Open cited PMID links to review the corresponding PubMed records when literature evidence matters.',
                ],
            ],
            [
                'heading' => 'Reading the answer',
                'items' => [
                    'Treat the answer as a synthesis of the evidence retrieved for that request, not as independent experimental validation.',
                    'Verify important relations in Graph or Path Finder, expression patterns in Expression, and downloadable contents in Download.',
                    'A statement may be appropriately limited when the database lacks the requested evidence; absence from the retrieved result is not proof of biological absence.',
                    'Journal metrics are descriptive metadata rather than confidence scores, and association language should not be read as causal language.',
                ],
            ],
        ],
    ],
    'expression' => [
        'nav' => 'Expression',
        'title' => 'Expression',
        'summary' => 'Expression is the TE abundance lookup surface. It supports catalog-level filtering and TE detail views across the available normal tissue, normal cell line, and cancer cell line datasets.',
        'sections' => [
            [
                'heading' => 'Finding a TE',
                'items' => [
                    'Use Keyword to search for a TE, or combine dataset source, top-context text, and minimum global median filters to narrow the table.',
                    'Use Sort to order the catalog by the available summary measures, then select a TE row for its detail view.',
                    'The browse table summarizes the top normal tissue, normal cell line, and cancer cell line context when those datasets are available.',
                    'If a TE is not suggested or returned, verify that it is present in the current expression catalog before interpreting the absence.',
                ],
            ],
            [
                'heading' => 'Reading expression views',
                'items' => [
                    'Use the detail summary to confirm the available datasets and the selected median, mean, or maximum metric.',
                    'Read Normal Tissue, Normal Cell Line, and Cancer Cell Line as separate study contexts, not as a matched cohort comparison.',
                    'Use the plots to compare contexts within the displayed dataset and metric.',
                    'Keep expression abundance separate from knowledge-graph relations and co-expression correlations; they answer different questions.',
                ],
            ],
            [
                'heading' => 'Data notes',
                'items' => [
                    'Expression values should be interpreted within the displayed dataset, metric, and preprocessing context.',
                    'Differences across normal tissue, normal cell line, and cancer cell line views may reflect both biology and study design.',
                    'Download provides the expression matrices and metadata currently exposed through the site for independent inspection.',
                ],
            ],
        ],
    ],
    'download' => [
        'nav' => 'Download',
        'title' => 'Download',
        'summary' => 'Download lists the data files currently made available through TE-KG. The table helps users compare dataset name, filename, site usage, format, and a short description before downloading.',
        'sections' => [
            [
                'heading' => 'Table layout',
                'items' => [
                    'Dataset names identify each downloadable resource at a human-readable level.',
                    'File links point to the downloadable file path.',
                    'Used in explains which page or pipeline currently depends on that file.',
                    'Format tells whether the file is TSV, CSV, JSON, JSONL, TXT, or another exposed type.',
                ],
            ],
            [
                'heading' => 'Filtering downloads',
                'items' => [
                    'Use category filter buttons such as Expression, Graph, or Taxonomy to narrow the table.',
                    'Use Search to match dataset names, filenames, usage descriptions, formats, and row descriptions.',
                    'Expand a dataset row when you need a short explanation before downloading.',
                    'Clear the search text or return to All when you want to see the complete current download catalogue.',
                ],
            ],
            [
                'heading' => 'Catalog scope',
                'items' => [
                    'Files listed here correspond to visible TE-KG workflows or data that can be reviewed independently.',
                    'Internal intermediate outputs and archived working files are not included by default.',
                    'Availability on this page identifies a current site download; it is not by itself a versioned archival release.',
                    'For a formal release, use the accompanying stable identifier, version, checksum, and licence information when those fields are provided.',
                ],
            ],
        ],
    ],
    'about' => [
        'nav' => 'About',
        'title' => 'About',
        'summary' => 'About is the detailed guide to the TE-KG public interface. It explains what each page does, how to use it, and how the pages relate to one another.',
        'sections' => [
            [
                'heading' => 'How this guide is organized',
                'items' => [
                    'Use the left navigation to switch between page-specific guides.',
                    'Each section describes purpose, controls, data interpretation, and important boundaries.',
                    'The guide is written for users who need to decide where to start and how to verify what they find.',
                    'The text focuses on public interface behavior rather than internal implementation details.',
                ],
            ],
            [
                'heading' => 'Choosing the right workflow',
                'items' => [
                    'Use Browse for TE catalog lookup and Search for a selected entity\'s detailed record.',
                    'Use Path Finder for entity-to-entity connection questions.',
                    'Use Graph for knowledge relationships, TE classification, co-expression, and relation evidence inspection.',
                    'Use Expression for TE abundance patterns across supported expression datasets.',
                    'Use Download for the files currently available through the site.',
                    'Use Agent or DeepThink for natural-language synthesis, then verify important claims in the relevant evidence view.',
                ],
            ],
            [
                'heading' => 'Evidence-first reading',
                'items' => [
                    'Prefer views that expose source records when making relation-level claims.',
                    'Distinguish association, graph connection, expression abundance, and co-expression correlation before drawing a biological conclusion.',
                    'Do not interpret journal IF as confidence, and do not infer missing journal metrics.',
                    'When results differ between pages, first check the entity, dataset, context, metric, and evidence type shown by each page.',
                ],
            ],
        ],
    ],
];

function about_anchor_slug(string $text): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));
    return $slug !== '' ? $slug : 'section';
}

function about_media_spec(string $sectionKey, string $heading): ?array
{
    $media = [
        'resource:What TE-KG is' => [
            'filename' => 'about-resource-overview.png',
            'type' => 'PNG',
            'alt' => 'TE-KG resource overview placeholder showing the major public interface routes.',
            'caption' => 'Resource overview placeholder for the core TE-KG entry points and connected evidence model.',
        ],
        'resource:Data access routes' => [
            'filename' => 'about-resource-data-routes.png',
            'type' => 'PNG',
            'alt' => 'TE-KG data access route placeholder connecting Home, Browse, Path Finder, TE-KG, Expression, and Download.',
            'caption' => 'Route map placeholder for deciding which public page to use for a given task.',
        ],
        'resource:Evidence principles' => [
            'filename' => 'about-resource-evidence-table.png',
            'type' => 'PNG',
            'alt' => 'Evidence table placeholder highlighting PMID, title, journal, IF, JCR, and match type fields.',
            'caption' => 'Evidence table placeholder for reviewing relation-level publication metadata.',
        ],
        'home:What the page contains' => [
            'filename' => 'about-home-overview.png',
            'type' => 'PNG',
            'alt' => 'Home page overview placeholder showing project summary, dataset status, and quick links.',
            'caption' => 'Home overview placeholder for the first-screen project orientation area.',
        ],
        'home:How to read Dataset Status' => [
            'filename' => 'about-home-dataset-status.png',
            'type' => 'PNG',
            'alt' => 'Home Dataset Status placeholder showing entity, TE classification, and relation composition charts.',
            'caption' => 'Dataset Status placeholder for the live Neo4j summary charts and classification controls.',
        ],
        'browse:What the page is for' => [
            'filename' => 'about-browse-main.png',
            'type' => 'PNG',
            'alt' => 'Browse page placeholder showing the main catalog lookup table and entity discovery surface.',
            'caption' => 'Browse main placeholder for table-first entity lookup and record comparison.',
        ],
        'browse:Using the selector' => [
            'filename' => 'about-browse-selector.gif',
            'type' => 'GIF',
            'alt' => 'Browse selector GIF placeholder showing category choice, typing, suggestion selection, and table update.',
            'caption' => 'Browse selector GIF placeholder for the database-driven entity selection workflow.',
        ],
        'pathfinder:Search structure' => [
            'filename' => 'about-pathfinder-search.gif',
            'type' => 'GIF',
            'alt' => 'Path Finder search GIF placeholder showing source and target category and entity selectors.',
            'caption' => 'Path Finder search GIF placeholder for composing a constrained two-entity query.',
        ],
        'pathfinder:Reading path results' => [
            'filename' => 'about-pathfinder-results.png',
            'type' => 'PNG',
            'alt' => 'Path Finder results placeholder showing entity and relation sequence after a path search.',
            'caption' => 'Path result placeholder for reading entity-to-entity connections and relation predicates.',
        ],
        'pathfinder:Evidence checks' => [
            'filename' => 'about-pathfinder-evidence.png',
            'type' => 'PNG',
            'alt' => 'Path Finder evidence placeholder showing relation-level publication rows and journal metadata.',
            'caption' => 'Path evidence placeholder for checking supporting publications relation by relation.',
        ],
        'tekg:Graph interaction' => [
            'filename' => 'about-tekg-graph.gif',
            'type' => 'GIF',
            'alt' => 'TE-KG graph GIF placeholder showing graph search, zoom, node action, and evidence opening.',
            'caption' => 'TE-KG graph GIF placeholder for the interactive G6 exploration workflow.',
        ],
        'tekg:Legend and filters' => [
            'filename' => 'about-tekg-legend.png',
            'type' => 'PNG',
            'alt' => 'TE-KG legend placeholder showing entity legends, relation legends, and filter controls.',
            'caption' => 'TE-KG legend placeholder for understanding visible graph categories and view filters.',
        ],
        'agent:What to ask' => [
            'filename' => 'about-agent-main.png',
            'type' => 'PNG',
            'alt' => 'Agent page placeholder showing the natural-language question input and guided answer surface.',
            'caption' => 'Agent main placeholder for asking navigation and interpretation questions.',
        ],
        'expression:Choosing a TE' => [
            'filename' => 'about-expression-main.png',
            'type' => 'PNG',
            'alt' => 'Expression page placeholder showing TE selection and expression summary views.',
            'caption' => 'Expression main placeholder for selecting a TE and reviewing expression context.',
        ],
        'download:Table layout' => [
            'filename' => 'about-download-main.png',
            'type' => 'PNG',
            'alt' => 'Download page placeholder showing public dataset rows, file links, usage, and format columns.',
            'caption' => 'Download main placeholder for comparing public TE-KG export files.',
        ],
        'download:Filtering downloads' => [
            'filename' => 'about-download-filter.gif',
            'type' => 'GIF',
            'alt' => 'Download filter GIF placeholder showing category filters, search text, row expansion, and clearing filters.',
            'caption' => 'Download filter GIF placeholder for narrowing and inspecting the public file catalog.',
        ],
    ];
    $key = $sectionKey . ':' . $heading;
    return $media[$key] ?? null;
}
?>
      <section class="about-shell">
        <div class="proto-container">
          <h1 class="page-title-hero">About</h1>

          <section class="about-panel">
            <div class="about-layout">
              <aside class="about-side">
                <label class="about-search" for="about-search-input">
                  <span>Search</span>
                  <input id="about-search-input" type="search" placeholder="Search this guide" autocomplete="off">
                </label>
                <div class="about-side-title">Contents</div>
                <nav class="about-nav" aria-label="About page sections">
<?php foreach ($aboutSections as $key => $section): ?>
                  <a class="about-nav-parent <?= $key === 'resource' ? 'is-active' : '' ?>" href="#section-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" data-pane="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($section['nav'], ENT_QUOTES, 'UTF-8') ?></a>
<?php foreach ($section['sections'] as $detailIndex => $detail): ?>
<?php $detailId = 'section-' . $key . '-' . about_anchor_slug($detail['heading']); ?>
                  <a class="about-nav-child" href="#<?= htmlspecialchars($detailId, ENT_QUOTES, 'UTF-8') ?>" data-pane="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" data-subsection="<?= htmlspecialchars($detailId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($detail['heading'], ENT_QUOTES, 'UTF-8') ?></a>
<?php endforeach; ?>
<?php endforeach; ?>
                </nav>
              </aside>

              <div class="about-content">
<?php $sectionIndex = 1; ?>
<?php foreach ($aboutSections as $key => $section): ?>
                <article class="about-doc-section" id="section-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" data-section="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                  <header class="about-doc-header">
                    <span><?= str_pad((string)$sectionIndex, 2, '0', STR_PAD_LEFT) ?></span>
                    <div>
                      <h3><?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                      <p><?= htmlspecialchars($section['summary'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                  </header>

                  <div class="about-detail-grid">
<?php foreach ($section['sections'] as $detailIndex => $detail): ?>
<?php $detailId = 'section-' . $key . '-' . about_anchor_slug($detail['heading']); ?>
<?php $media = about_media_spec($key, $detail['heading']); ?>
                    <section class="about-doc-subsection" id="<?= htmlspecialchars($detailId, ENT_QUOTES, 'UTF-8') ?>" data-subsection-title="<?= htmlspecialchars($detail['heading'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="about-detail-card">
                      <h4><?= htmlspecialchars($detail['heading'], ENT_QUOTES, 'UTF-8') ?></h4>
                      <ul>
<?php foreach ($detail['items'] as $item): ?>
                        <li><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></li>
<?php endforeach; ?>
                      </ul>
                    </div>
<?php if ($media !== null): ?>
<?php
    $mediaFsPath = tekg_assets_fs_path('img/about/' . $media['filename']);
    $mediaUrl = tekg_assets_url('img/about/' . $media['filename']);
    $mediaExists = is_file($mediaFsPath);
?>
                    <figure class="about-placeholder-media" data-media-filename="<?= htmlspecialchars($media['filename'], ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($media['filename'], ENT_QUOTES, 'UTF-8') ?> media">
<?php if ($mediaExists): ?>
                      <img class="about-media-image" src="<?= htmlspecialchars($mediaUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($media['alt'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy" decoding="async">
<?php else: ?>
                      <div class="about-placeholder-stage" data-media-type="<?= htmlspecialchars($media['type'], ENT_QUOTES, 'UTF-8') ?>">
                        <span class="about-placeholder-type"><?= htmlspecialchars($media['type'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="about-placeholder-file"><?= htmlspecialchars($media['filename'], ENT_QUOTES, 'UTF-8') ?></span>
                      </div>
<?php endif; ?>
                      <figcaption>
                        <?= htmlspecialchars($media['caption'], ENT_QUOTES, 'UTF-8') ?>
                      </figcaption>
                    </figure>
<?php endif; ?>
                    </section>
<?php endforeach; ?>
                  </div>
                </article>
<?php $sectionIndex += 1; ?>
<?php endforeach; ?>
              </div>
            </div>
          </section>
        </div>
      </section>
      <p class="about-no-results" hidden>No matching guide sections.</p>

      <script src="<?= htmlspecialchars(tekg_assets_url('js/pages/about.js') . '?v=' . $aboutJsVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php require __DIR__ . '/foot.php'; ?>
