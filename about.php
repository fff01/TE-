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
        'summary' => 'TE-KG is a transposable-element-centered knowledge graph interface for exploring TE entities, diseases, molecular functions, literature evidence, expression contexts, and public data exports in one local database environment.',
        'sections' => [
            [
                'heading' => 'What TE-KG is',
                'items' => [
                    'TE-KG organizes transposable element knowledge as connected entities rather than as isolated tables.',
                    'The public interface combines catalog lookup, graph exploration, path search, expression inspection, and downloadable datasets.',
                    'The runtime graph target is Neo4j tekg3, and user-facing graph pages should be read as views over that runtime source.',
                    'The goal is to make TE-related relationships reviewable, especially when a relation depends on literature evidence.',
                ],
            ],
            [
                'heading' => 'What this guide covers',
                'items' => [
                    'The guide explains what each public page is designed to answer.',
                    'It describes the main controls on each page and the order in which users should use them.',
                    'It separates lookup, graph browsing, path evidence, expression analysis, and public file download workflows.',
                    'It also documents evidence-reading cautions so users do not overinterpret journal metrics or missing metadata.',
                ],
            ],
            [
                'heading' => 'Data access routes',
                'items' => [
                    'Use Home for high-level live dataset composition.',
                    'Use Browse for entity discovery and name lookup.',
                    'Use Path Finder and TE-KG when relation evidence matters.',
                    'Use Expression for TE expression contexts.',
                    'Use Download when you need the public files supporting visible workflows.',
                ],
            ],
            [
                'heading' => 'Evidence principles',
                'items' => [
                    'Relation-level claims should be checked against supporting papers when available.',
                    'PMID, title, year, journal, IF, JCR, and match type are evidence metadata fields, not interchangeable confidence scores.',
                    'IF must not be called confidence, and missing IF or JCR should not be guessed.',
                    'When a page appears inconsistent, check page context and data source before assuming a biological contradiction.',
                ],
            ],
        ],
    ],
    'home' => [
        'nav' => 'Home',
        'title' => 'Home Overview',
        'summary' => 'Home is the orientation layer for TE-KG. It introduces the project, shows the live scale of the Neo4j-backed dataset, and gives a compact route into the public database workflows.',
        'sections' => [
            [
                'heading' => 'What the page contains',
                'items' => [
                    'The Overview area summarizes the purpose of TE-KG and reserves the right-side image area for a future architecture diagram.',
                    'Dataset Status reports live read-only statistics from Neo4j tekg3 rather than fixed numbers in the page source.',
                    'The donut charts separate entity composition, TE classification, and relation predicate composition.',
                    'Quick Links provide direct entry points into Browse, Path Finder, TE-KG, Expression, Download, and this guide.',
                ],
            ],
            [
                'heading' => 'How to read Dataset Status',
                'items' => [
                    'Entity Composition counts major node classes once per Neo4j node.',
                    'TE Classification can switch classification level, so the chart can move from broad classes to more specific taxonomy levels.',
                    'Relation Composition uses BIO_RELATION predicate-level statistics, making frequent relation types visible without collapsing them into vague labels.',
                    'If live statistics cannot load, the page shows a fallback instead of inventing or guessing values.',
                ],
            ],
            [
                'heading' => 'Recommended workflow',
                'items' => [
                    'Start here when you need a quick sense of what the database currently contains.',
                    'Use the architecture placeholder as a reminder that Home is the project-level entry point, not the detailed graph runtime.',
                    'Move to Browse when you know an entity name, Path Finder when you want a connection, or TE-KG when you want a visual network.',
                ],
            ],
        ],
    ],
    'browse' => [
        'nav' => 'Browse',
        'title' => 'Browse',
        'summary' => 'Browse is the table-first lookup surface. It is designed for users who want to scan TE-KG entities directly before opening a graph, path search, or expression workflow.',
        'sections' => [
            [
                'heading' => 'What the page is for',
                'items' => [
                    'Use Browse when you want a catalog-style view rather than a graph-first exploration.',
                    'The page is suited for checking whether a TE, disease, function, or other supported entity is present.',
                    'The dropdown suggestions are database-driven, so users do not need to guess exact names from memory.',
                    'Browse is also a good starting point when you want to compare several records before choosing one for detailed review.',
                ],
            ],
            [
                'heading' => 'Using the selector',
                'items' => [
                    'Choose the entity category first when a category selector is available.',
                    'Type the beginning of a name to filter suggestions alphabetically within the selected category.',
                    'Pick a suggestion from the dropdown rather than relying on unchecked free text.',
                    'After selecting an entity, use the table or linked controls to move into detail, graph, or evidence workflows.',
                ],
            ],
            [
                'heading' => 'Data interpretation',
                'items' => [
                    'Entity labels and names should be treated as runtime database values from tekg3.',
                    'When a name appears in multiple contexts, use category and linked metadata to avoid confusing TE, disease, and function records.',
                    'Browse is optimized for discovery and lookup; evidence-level review should happen in TE-KG or Path Finder when relation support matters.',
                ],
            ],
        ],
    ],
    'pathfinder' => [
        'nav' => 'Path Finder',
        'title' => 'Path Finder',
        'summary' => 'Path Finder searches paths between two selected entities and presents relation-level support in a reviewable format. It is useful when the question is about connection rather than simple lookup.',
        'sections' => [
            [
                'heading' => 'Search structure',
                'items' => [
                    'Each side of the search has a narrow category selector and a wider entity selector inside the same search box.',
                    'The entity dropdown is constrained by the selected category, so choosing TE gives TE names and choosing Disease gives disease names.',
                    'The default state keeps the fields clean so users can start with a fresh pair of entities.',
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
                ],
            ],
            [
                'heading' => 'Evidence checks',
                'items' => [
                    'Use PMID and title to identify the supporting publication.',
                    'Use journal, IF, and JCR as descriptive metadata, not as a replacement for reading the source.',
                    'Use match type to understand how a paper was linked to journal metrics.',
                    'When a path has multiple relations, review each relation separately rather than assuming the whole path has uniform support.',
                ],
            ],
        ],
    ],
    'tekg' => [
        'nav' => 'TE-KG',
        'title' => 'TE-KG Graph',
        'summary' => 'TE-KG is the interactive G6 graph runtime. It exposes Neo4j tekg3 entities and BIO_RELATION edges through a G6-based visual interface with legends, filters, node actions, and evidence support.',
        'sections' => [
            [
                'heading' => 'Graph interaction',
                'items' => [
                    'Use search or built-in loading controls to open a graph neighborhood.',
                    'Pan and zoom to inspect the network without losing the high-level layout.',
                    'Use node action cards to inspect entity-specific options and metadata.',
                    'Open relation evidence controls when you need publication support for a visible edge.',
                ],
            ],
            [
                'heading' => 'Legend and filters',
                'items' => [
                    'Entity legends help distinguish TE, disease, function, paper, and other node types.',
                    'Relation legends expose predicate categories and make it easier to focus on a subset of relation types.',
                    'Expanded legend modes are intended for detailed review when the graph is dense.',
                    'Filter changes should be interpreted as view changes, not database writes.',
                ],
            ],
            [
                'heading' => 'Runtime boundaries',
                'items' => [
                    'The graph page reads from the current runtime target tekg3.',
                    'It should not be treated as a separate taxonomy truth source.',
                    'Iframe bridge state, loader state, and legend state are part of the graph experience and can affect what the user sees.',
                    'If visual graph content looks incomplete, compare the API payload and browser rendering before drawing biological conclusions.',
                ],
            ],
        ],
    ],
    'agent' => [
        'nav' => 'Agent',
        'title' => 'Agent',
        'summary' => 'Agent is the natural-language assistant surface. It is separate from the core table and graph pages and is intended to help users frame questions, navigate workflows, and interpret what to inspect next.',
        'sections' => [
            [
                'heading' => 'What to ask',
                'items' => [
                    'Ask questions about TE entities, diseases, functions, papers, or evidence patterns you want to explore.',
                    'Use Agent when you need help choosing between Browse, Path Finder, TE-KG, Expression, and Download.',
                    'Use clear entity names or PMIDs when possible so the answer can be grounded in database context.',
                    'For ambiguous names, ask the assistant to clarify candidate entities before continuing.',
                ],
            ],
            [
                'heading' => 'How to use answers',
                'items' => [
                    'Treat Agent as a guided navigation and interpretation layer.',
                    'For relation evidence, verify important claims in Path Finder or TE-KG evidence tables.',
                    'For dataset contents, verify public files on Download rather than relying only on a text answer.',
                    'For expression questions, use Expression after the assistant identifies the relevant TE name or context.',
                ],
            ],
            [
                'heading' => 'Important limits',
                'items' => [
                    'Agent should not replace direct evidence review when a claim depends on a specific paper.',
                    'Agent-facing pages are not the default place for ordinary UI or database changes.',
                    'If the answer references IF or JCR, remember these are journal metrics and should not be called confidence.',
                    'Missing journal metrics should remain missing; they should not be guessed.',
                ],
            ],
        ],
    ],
    'expression' => [
        'nav' => 'Expression',
        'title' => 'Expression',
        'summary' => 'Expression is the TE expression lookup surface. It helps users inspect supported bulk expression contexts after choosing a valid TE name from the database-driven selector.',
        'sections' => [
            [
                'heading' => 'Choosing a TE',
                'items' => [
                    'Use the TE dropdown to select a name from the current database-backed list.',
                    'Type a prefix to narrow the suggestions rather than manually entering a free-form label.',
                    'The selector pattern is shared with other TE lookup surfaces so names stay consistent across pages.',
                    'If a TE is not suggested, treat that as a signal to verify its current availability before continuing.',
                ],
            ],
            [
                'heading' => 'Reading expression views',
                'items' => [
                    'Use the summary area to confirm which dataset or context is currently being displayed.',
                    'Use plots to compare expression patterns across the supported cohorts or sample groups.',
                    'Open detail-level views when the summary is not enough for interpretation.',
                    'Keep expression evidence separate from graph relation evidence; they answer different questions.',
                ],
            ],
            [
                'heading' => 'Data notes',
                'items' => [
                    'The current runtime expression root is data/bulk_expression_web.',
                    'Expression files support the website views and can also be downloaded from the Download page when exposed publicly.',
                    'Expression values should be interpreted in the context of the displayed dataset and preprocessing path.',
                ],
            ],
        ],
    ],
    'download' => [
        'nav' => 'Download',
        'title' => 'Download',
        'summary' => 'Download exposes public files that support the visible TE-KG workflows. The page uses a traditional table so users can quickly compare dataset name, file, usage, and format.',
        'sections' => [
            [
                'heading' => 'Table layout',
                'items' => [
                    'Dataset names describe the public data export at a human-readable level.',
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
                    'Clear the search text or return to All when you want to see the full public catalog.',
                ],
            ],
            [
                'heading' => 'Catalog scope',
                'items' => [
                    'Files listed here should correspond to visible public TE-KG pages or reviewable runtime data.',
                    'Internal intermediate outputs and archived files are intentionally not treated as public catalog items by default.',
                    'A file appearing in Download means it is exposed for user access; it does not imply the page writes to Neo4j.',
                    'When a path changes, Download should be updated to avoid stale public links.',
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
                    'Use Browse for catalog lookup.',
                    'Use Path Finder for entity-to-entity connection questions.',
                    'Use TE-KG for visual graph exploration and relation evidence inspection.',
                    'Use Expression for TE expression patterns.',
                    'Use Download for public data exports.',
                    'Use Agent for guided natural-language help, then verify important claims in the relevant page.',
                ],
            ],
            [
                'heading' => 'Evidence-first reading',
                'items' => [
                    'Prefer pages that show source records when making relation-level claims.',
                    'Do not interpret journal IF as confidence.',
                    'Do not assume missing IF or JCR values; missing values should remain explicit.',
                    'When results differ between pages, check the runtime source, API payload, and page context before assuming a biological disagreement.',
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
