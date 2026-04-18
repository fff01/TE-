<?php
$pageTitle = 'TE-KG Browse';
$activePage = 'browse';
$protoCurrentPath = '/TE-/browse.php';
$protoSubtitle = 'Browse TE classes and records in a structured catalog view';

function tekg_browse_normalize_label(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return str_replace(['_', '-'], ' ', $value);
}

function tekg_browse_extract_length(array $entry): ?int
{
    $summary = $entry['sequence_summary'] ?? null;
    if (is_array($summary)) {
        $headline = trim((string) ($summary['headline'] ?? ''));
        if ($headline !== '' && preg_match('/(\d+)\s*BP/i', $headline, $matches) === 1) {
            return (int) $matches[1];
        }
    }

    $sequence = preg_replace('/\s+/', '', (string) ($entry['sequence'] ?? '')) ?? '';
    return $sequence !== '' ? strlen($sequence) : null;
}

function tekg_browse_infer_lineage(array $entry): array
{
    $name = (string) ($entry['name'] ?? '');
    $description = mb_strtolower((string) ($entry['description'] ?? ''));
    $keywords = array_map(
        static fn ($keyword): string => mb_strtolower((string) $keyword),
        is_array($entry['keywords'] ?? null) ? $entry['keywords'] : []
    );
    $haystack = mb_strtolower($name . ' ' . $description . ' ' . implode(' ', $keywords));

    $className = 'Unclassified';
    $family = '';
    $subtype = '';

    if (
        str_contains($haystack, 'endogenous retrovirus')
        || str_contains($haystack, 'herv')
        || str_contains($haystack, ' erv')
        || str_contains($haystack, 'ltr')
    ) {
        $className = 'Retrotransposon';
        foreach ($entry['keywords'] ?? [] as $keyword) {
            if (preg_match('/^(ERV\d+|ERVL|ERVK|HERV[\w\-]+)$/i', (string) $keyword) === 1) {
                $family = (string) $keyword;
                break;
            }
        }
        $family = $family !== '' ? $family : 'ERV';
        $subtype = str_starts_with($name, 'LTR') ? 'LTR' : '';
    } elseif (
        str_contains($haystack, 'non-ltr retrotransposon')
        || str_contains($haystack, ' line ')
        || str_contains($haystack, 'l1 (line) family')
    ) {
        $className = 'Retrotransposon';
        $family = 'LINE';
        foreach (['CR1', 'L1', 'L2', 'RTE'] as $candidate) {
            if (str_contains($haystack, mb_strtolower($candidate))) {
                $subtype = $candidate;
                break;
            }
        }
    } elseif (str_contains($haystack, 'sine')) {
        $className = 'Retrotransposon';
        $family = 'SINE';
        if (str_contains($haystack, 'alu')) {
            $subtype = 'Alu';
        }
    } elseif (str_contains($haystack, 'dna transposon')) {
        $className = 'DNA Transposon';
        foreach (['hAT-Charlie', 'hAT', 'Mariner/Tc1', 'piggyBac', 'Merlin', 'Helitron'] as $candidate) {
            if (str_contains($haystack, mb_strtolower($candidate))) {
                $family = $candidate;
                break;
            }
        }
    }

    return [
        'className' => tekg_browse_normalize_label($className),
        'family' => tekg_browse_normalize_label($family),
        'subtype' => tekg_browse_normalize_label($subtype),
    ];
}

function tekg_browse_load_rows(): array
{
    $repbaseFile = __DIR__ . '/data/processed/te_repbase_db_matched.json';
    $lineageFile = __DIR__ . '/data/processed/tree_te_lineage.json';
    if (!is_file($repbaseFile)) {
        return [];
    }

    $repbase = json_decode((string) file_get_contents($repbaseFile), true);
    if (!is_array($repbase) || !is_array($repbase['entries'] ?? null)) {
        return [];
    }

    $lineage = is_file($lineageFile)
        ? json_decode((string) file_get_contents($lineageFile), true)
        : null;

    $parentMap = [];
    foreach (($lineage['edges'] ?? []) as $edge) {
        $child = (string) ($edge['child'] ?? '');
        $parent = (string) ($edge['parent'] ?? '');
        if ($child !== '' && $parent !== '') {
            $parentMap[$child] = $parent;
        }
    }

    $pathCache = [];
    $pathToRoot = static function (string $name) use (&$pathCache, $parentMap): array {
        if (isset($pathCache[$name])) {
            return $pathCache[$name];
        }
        $path = [];
        $cursor = $name;
        $seen = [];
        while ($cursor !== '' && !isset($seen[$cursor])) {
            $seen[$cursor] = true;
            $path[] = $cursor;
            $cursor = $parentMap[$cursor] ?? '';
        }
        $pathCache[$name] = array_reverse($path);
        return $pathCache[$name];
    };

    $rows = [];
    foreach ($repbase['entries'] as $entry) {
        $name = trim((string) ($entry['name'] ?? $entry['id'] ?? ''));
        if ($name === '') {
            continue;
        }

        $path = $pathToRoot($name);
        $ancestors = $path;
        if (!empty($ancestors) && $ancestors[0] === 'TE') {
            array_shift($ancestors);
        }
        if (!empty($ancestors) && end($ancestors) === $name) {
            array_pop($ancestors);
        }

        $inferred = tekg_browse_infer_lineage($entry);
        $className = $ancestors[0] ?? $inferred['className'];
        $family = $ancestors[1] ?? $inferred['family'];
        $subtype = count($ancestors) > 2 ? (string) end($ancestors) : $inferred['subtype'];

        $rows[] = [
            'name' => $name,
            'className' => tekg_browse_normalize_label($className !== '' ? $className : 'Unclassified'),
            'family' => tekg_browse_normalize_label($family),
            'subtype' => tekg_browse_normalize_label($subtype),
            'description' => trim((string) ($entry['description'] ?? '')),
            'lengthBp' => tekg_browse_extract_length($entry),
            'referenceCount' => is_array($entry['references'] ?? null) ? count($entry['references']) : 0,
            'keywords' => is_array($entry['keywords'] ?? null) ? array_values(array_filter(array_map('strval', $entry['keywords']))) : [],
        ];
    }

    usort(
        $rows,
        static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name'])
    );

    return $rows;
}

require __DIR__ . '/head.php';
$browseSearchUrl = site_url_with_state('/TE-/search.php', $siteLang);
$browseRows = tekg_browse_load_rows();
?>
      <link rel="stylesheet" href="/TE-/assets/css/pages/browse.css">

      <main class="proto-main">
        <section class="browse-shell">
          <div class="proto-container">
            <h1 class="browse-page-title">Browse</h1>
            <p class="browse-intro">This browse view is designed as a lightweight catalog-style entry point inspired by Dfam. It prioritizes scanning, filtering, and shortlisting TE records in a clean table layout before users move into deeper search or graph exploration.</p>
            <div class="browse-crumbs">
              <a href="<?= htmlspecialchars(site_url_with_state('/TE-/index.php', $siteLang), ENT_QUOTES, 'UTF-8') ?>">Home</a>
              <span>/</span>
              <span>Browse</span>
            </div>

            <div class="browse-layout">
              <?php require __DIR__ . '/templates/components/browse_filters.php'; ?>

              <section class="browse-results">
                <div class="browse-table-wrap">
                  <table class="browse-table">
                    <colgroup>
                      <col style="width: 18%">
                      <col style="width: 14%">
                      <col style="width: 14%">
                      <col style="width: 14%">
                      <col style="width: 30%">
                      <col style="width: 10%">
                    </colgroup>
                    <thead>
                      <tr>
                        <th>TE Name</th>
                        <th>Class</th>
                        <th>Family</th>
                        <th>Subtype</th>
                        <th>Description</th>
                        <th>Length</th>
                      </tr>
                    </thead>
                    <tbody id="browseTableBody"></tbody>
                  </table>
                </div>
                <div class="browse-empty" id="browseEmpty">No TE records match the current search and filter combination. Try clearing one or more conditions.</div>

                <?php require __DIR__ . '/templates/components/browse_pagination.php'; ?>

                <p class="browse-note">This browse catalog now uses the aligned Repbase-backed TE dataset and current lineage reference. It shows formal catalog pagination and hands TE clicks off to Search for detailed inspection.</p>
              </section>
            </div>
          </div>
        </section>
      </main>
    </div>
    <script id="browse-page-data" type="application/json"><?= json_encode(['browseSearchBase' => $browseSearchUrl, 'browseRows' => $browseRows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script src="/TE-/assets/js/pages/browse.js"></script>
  </body>
</html>





