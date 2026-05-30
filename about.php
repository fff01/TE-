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
        'summary' => 'TE-KG is the interactive graph runtime. It exposes Neo4j tekg3 entities and BIO_RELATION edges through a G6-based visual interface with legends, filters, node actions, and evidence support.',
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
                    'Use category buttons such as Expression, Graph, or Taxonomy to narrow the table.',
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
?>
      <section class="about-shell">
        <div class="proto-container">
          <h1 class="page-title-hero">About</h1>
          <div class="page-crumbs">
            <a href="<?= htmlspecialchars(site_url_with_state(tekg_app_url('index.php'), $siteLang), ENT_QUOTES, 'UTF-8') ?>">Home</a>
            <span>/</span>
            <span>About</span>
          </div>

          <section class="about-panel">
            <div class="about-layout">
              <aside class="about-side">
                <nav class="about-nav" aria-label="About page sections">
<?php foreach ($aboutSections as $key => $section): ?>
                  <a href="#<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" data-pane="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" class="<?= $key === 'home' ? 'is-active' : '' ?>"><?= htmlspecialchars($section['nav'], ENT_QUOTES, 'UTF-8') ?></a>
<?php endforeach; ?>
                </nav>
              </aside>

              <div class="about-content">
<?php foreach ($aboutSections as $key => $section): ?>
                <section class="about-pane <?= $key === 'home' ? 'is-active' : '' ?>" id="pane-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                  <div class="about-block">
                    <div class="about-block-header">
                      <h4><?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?></h4>
                      <p><?= htmlspecialchars($section['summary'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>

                    <div class="about-detail-grid">
<?php foreach ($section['sections'] as $detail): ?>
                      <article class="about-detail-card">
                        <h5><?= htmlspecialchars($detail['heading'], ENT_QUOTES, 'UTF-8') ?></h5>
                        <ul>
<?php foreach ($detail['items'] as $item): ?>
                          <li><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></li>
<?php endforeach; ?>
                        </ul>
                      </article>
<?php endforeach; ?>
                    </div>
                  </div>
                </section>
<?php endforeach; ?>
              </div>
            </div>
          </section>
        </div>
      </section>

      <script src="<?= htmlspecialchars(tekg_assets_url('js/pages/about.js') . '?v=' . $aboutJsVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
    </main>
  </div>
</body>
</html>
