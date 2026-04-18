<?php
$pageTitle = 'TE-KG JBrowse';
$activePage = 'genomic';
$protoCurrentPath = '/TE-/jbrowse.php';
$protoSubtitle = 'Standalone genome browser for TE loci';
require __DIR__ . '/site_i18n.php';
require __DIR__ . '/path_config.php';
$isEmbedded = trim((string) ($_GET['embed'] ?? '')) !== '';

function jbrowse_primary_chr_order(): array
{
    $order = [];
    for ($i = 1; $i <= 22; $i++) {
        $order['chr' . $i] = $i;
    }
    $order['chrX'] = 23;
    $order['chrY'] = 24;
    return $order;
}

function jbrowse_read_json(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $content = file_get_contents($path);
    if ($content === false || $content === '') {
        return [];
    }
    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : [];
}

function jbrowse_sanitize_slug(string $value): string
{
    $slug = preg_replace('/[^A-Za-z0-9._-]+/', '_', $value);
    $slug = trim((string) $slug, '_');
    return $slug !== '' ? $slug : 'track';
}

function jbrowse_normalize_te(?string $te): string
{
    $te = trim((string) $te);
    return $te;
}

function jbrowse_project_relative_path(string $relativePath): string
{
    $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
    if ($normalized === '') {
        return 'data/JBrowse';
    }
    if (str_starts_with($normalized, 'data/JBrowse/')) {
        return $normalized;
    }
    if ($normalized === 'data/JBrowse') {
        return $normalized;
    }
    if (str_starts_with($normalized, 'JBrowse/')) {
        return 'data/' . $normalized;
    }
    if ($normalized === 'JBrowse') {
        return 'data/JBrowse';
    }
    $marker = '/JBrowse/';
    $markerPos = strpos($normalized, $marker);
    if ($markerPos !== false) {
        return 'data/JBrowse/' . substr($normalized, $markerPos + strlen($marker));
    }
    if (str_ends_with($normalized, '/JBrowse')) {
        return 'data/JBrowse';
    }
    return 'data/JBrowse/' . $normalized;
}

function jbrowse_project_fs_path(string $relativePath): string
{
    return tekg_fs_from_project_relative(jbrowse_project_relative_path($relativePath));
}

function jbrowse_project_url(string $relativePath): string
{
    return tekg_url_from_project_relative(jbrowse_project_relative_path($relativePath));
}

function jbrowse_load_hit_entry(string $te, array $hitManifest): ?array
{
    static $cache = [];
    if (array_key_exists($te, $cache)) {
        return $cache[$te];
    }

    $relativePath = $hitManifest[$te] ?? null;
    if (!is_string($relativePath) || $relativePath === '') {
        $cache[$te] = null;
        return null;
    }

    $absolutePath = jbrowse_project_fs_path($relativePath);
    if (!is_file($absolutePath)) {
        $cache[$te] = null;
        return null;
    }

    $decoded = jbrowse_read_json($absolutePath);
    $cache[$te] = is_array($decoded) ? $decoded : null;
    return $cache[$te];
}

function jbrowse_build_locus_from_params(array $representativeIndex, array $hitManifest = []): array
{
    $te = jbrowse_normalize_te($_GET['te'] ?? '');
    $chr = trim((string) ($_GET['chr'] ?? ''));
    $start = isset($_GET['start']) ? (int) $_GET['start'] : null;
    $end = isset($_GET['end']) ? (int) $_GET['end'] : null;

    $resolvedTeEntry = ($te !== '' && isset($representativeIndex[$te]) && is_array($representativeIndex[$te])) ? $representativeIndex[$te] : null;
    if ($te !== '' && $resolvedTeEntry) {
        $hitEntry = jbrowse_load_hit_entry($te, $hitManifest);
        if (is_array($hitEntry)) {
            $resolvedTeEntry = array_replace($resolvedTeEntry, $hitEntry);
        }
    }
    $representative = is_array($resolvedTeEntry['representative_locus'] ?? null) ? $resolvedTeEntry['representative_locus'] : null;

    if ($chr === '' && $representative) {
        $chr = (string) ($representative['chrom'] ?? '');
    }
    if ($start === null && $representative) {
        $start = (int) ($representative['start'] ?? 0);
    }
    if ($end === null && $representative) {
        $end = (int) ($representative['end'] ?? 0);
    }

    if ($chr === '' || $start === null || $end === null || $end <= $start) {
        $chr = 'chr1';
        $start = 231646101;
        $end = 231652225;
        if ($te === '') {
            $te = 'L1HS';
        }
        $resolvedTeEntry = ($te !== '' && isset($representativeIndex[$te]) && is_array($representativeIndex[$te])) ? $representativeIndex[$te] : null;
        if ($te !== '' && $resolvedTeEntry) {
            $hitEntry = jbrowse_load_hit_entry($te, $hitManifest);
            if (is_array($hitEntry)) {
                $resolvedTeEntry = array_replace($resolvedTeEntry, $hitEntry);
            }
        }
        $representative = is_array($resolvedTeEntry['representative_locus'] ?? null) ? $resolvedTeEntry['representative_locus'] : null;
    }

    $length = max(1, $end - $start);
    $padding = max(5000, (int) round($length * 2.5));
    $viewStart = max(0, $start - $padding);
    $viewEnd = $end + $padding;

    return [
        'te' => $te,
        'chr' => $chr,
        'start' => $start,
        'end' => $end,
        'view_start' => $viewStart,
        'view_end' => $viewEnd,
        'entry' => $resolvedTeEntry,
        'representative' => $representative,
    ];
}

function jbrowse_collect_repeat_rows(string $bedPath, string $chr, int $start, int $end, int $limit = 1200): array
{
    if (!is_file($bedPath)) {
        return [];
    }
    $rows = [];
    $handle = fopen($bedPath, 'rb');
    if ($handle === false) {
        return [];
    }

    while (($line = fgets($handle)) !== false) {
        $parts = explode("\t", rtrim($line, "\r\n"));
        if (count($parts) < 8) {
            continue;
        }
        $rowChr = $parts[0];
        if ($rowChr !== $chr) {
            continue;
        }
        $rowStart = (int) $parts[1];
        $rowEnd = (int) $parts[2];
        if ($rowEnd < $start) {
            continue;
        }
        if ($rowStart > $end) {
            break;
        }
        $rows[] = [
            'seqid' => $rowChr,
            'source' => 'RepeatMasker',
            'type' => 'repeat_region',
            'start' => $rowStart + 1,
            'end' => $rowEnd,
            'score' => '.',
            'strand' => ($parts[5] === '-' ? '-' : '+'),
            'phase' => '.',
            'attributes' => [
                'ID' => 'repeat_' . count($rows),
                'Name' => $parts[3],
                'te_name' => $parts[3],
                'class' => $parts[6],
                'family' => $parts[7],
            ],
        ];
        if (count($rows) >= $limit) {
            break;
        }
    }

    fclose($handle);
    return $rows;
}

function jbrowse_parse_gtf_attributes(string $attributeText): array
{
    $attributes = [];
    if (preg_match_all('/([A-Za-z0-9_]+) "([^"]*)"/', $attributeText, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $attributes[$match[1]] = $match[2];
        }
    }
    return $attributes;
}

function jbrowse_collect_refseq_rows(string $gtfPath, string $chr, int $start, int $end, int $limit = 4000): array
{
    if (!is_file($gtfPath)) {
        return [];
    }
    $rows = [];
    $handle = fopen($gtfPath, 'rb');
    if ($handle === false) {
        return [];
    }

    while (($line = fgets($handle)) !== false) {
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $parts = explode("\t", rtrim($line, "\r\n"));
        if (count($parts) < 9) {
            continue;
        }
        if ($parts[0] !== $chr) {
            continue;
        }
        $featureStart = (int) $parts[3];
        $featureEnd = (int) $parts[4];
        if ($featureEnd < $start + 1) {
            continue;
        }
        if ($featureStart > $end) {
            break;
        }

        $attrs = jbrowse_parse_gtf_attributes($parts[8]);
        $geneName = $attrs['gene_name'] ?? ($attrs['gene_id'] ?? ($attrs['transcript_id'] ?? 'feature'));
        $transcriptId = $attrs['transcript_id'] ?? null;
        $featureType = $parts[2];
        $featureId = $transcriptId
            ? ($featureType . '_' . $transcriptId . '_' . $featureStart . '_' . $featureEnd)
            : ($featureType . '_' . $geneName . '_' . $featureStart . '_' . $featureEnd);

        $gff3Attributes = [
            'ID' => $featureId,
            'Name' => $geneName,
            'gene_name' => $geneName,
        ];
        if (!empty($attrs['gene_id'])) {
            $gff3Attributes['gene_id'] = $attrs['gene_id'];
        }
        if ($transcriptId) {
            $gff3Attributes['transcript_id'] = $transcriptId;
        }

        $rows[] = [
            'seqid' => $parts[0],
            'source' => $parts[1] !== '' ? $parts[1] : 'NCBI_RefSeq',
            'type' => $featureType,
            'start' => $featureStart,
            'end' => $featureEnd,
            'score' => ($parts[5] !== '' ? $parts[5] : '.'),
            'strand' => ($parts[6] === '-' ? '-' : '+'),
            'phase' => ($parts[7] !== '' ? $parts[7] : '.'),
            'attributes' => $gff3Attributes,
        ];

        if (count($rows) >= $limit) {
            break;
        }
    }

    fclose($handle);
    return $rows;
}

function jbrowse_write_gff3_cache(string $cachePath, array $rows): void
{
    if (is_file($cachePath)) {
        return;
    }
    $dir = dirname($cachePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $handle = fopen($cachePath, 'wb');
    if ($handle === false) {
        return;
    }
    fwrite($handle, "##gff-version 3\n");
    foreach ($rows as $row) {
        $attrs = [];
        foreach (($row['attributes'] ?? []) as $key => $value) {
            $attrs[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }
        $attrText = implode(';', $attrs);
        $line = implode("\t", [
            $row['seqid'],
            $row['source'],
            $row['type'],
            (string) $row['start'],
            (string) $row['end'],
            (string) $row['score'],
            (string) $row['strand'],
            (string) $row['phase'],
            $attrText,
        ]);
        fwrite($handle, $line . "\n");
    }
    fclose($handle);
}

$siteLang = site_lang();
$root = __DIR__;
$jbrowseDir = TEKG_JBROWSE_FS_DIR;
$repeatsDir = $jbrowseDir . '/repeats';
$representativeIndex = jbrowse_read_json($repeatsDir . '/te_representative_index.json');
$hitManifest = jbrowse_read_json($repeatsDir . '/te_hits_manifest.json');
$locus = jbrowse_build_locus_from_params($representativeIndex, is_array($hitManifest) ? $hitManifest : []);

$regionKey = implode('__', [
    jbrowse_sanitize_slug($locus['te'] !== '' ? $locus['te'] : 'region'),
    $locus['chr'],
    $locus['view_start'],
    $locus['view_end'],
]);

$repeatsRows = jbrowse_collect_repeat_rows($repeatsDir . '/hg38.rmsk.repeats.bed', $locus['chr'], $locus['view_start'], $locus['view_end']);
$refseqRows = jbrowse_collect_refseq_rows($jbrowseDir . '/hg38.ncbiRefSeq.gtf/hg38.ncbiRefSeq.gtf', $locus['chr'], $locus['view_start'], $locus['view_end']);

$repeatCacheRel = jbrowse_project_relative_path('cache/repeats/' . $regionKey . '.gff3');
$refseqCacheRel = jbrowse_project_relative_path('cache/refseq/' . $regionKey . '.gff3');
$repeatCacheAbs = jbrowse_project_fs_path($repeatCacheRel);
$refseqCacheAbs = jbrowse_project_fs_path($refseqCacheRel);
jbrowse_write_gff3_cache($repeatCacheAbs, $repeatsRows);
jbrowse_write_gff3_cache($refseqCacheAbs, $refseqRows);

$repeatTrackUrl = jbrowse_project_url($repeatCacheRel);
$refseqTrackUrl = jbrowse_project_url($refseqCacheRel);
$fastaUrl = tekg_jbrowse_url('hg38.fa');
$faiUrl = tekg_jbrowse_url('hg38.fa.fai');
$clinvarMainUrl = tekg_jbrowse_url('clinvarMain.bb');
$clinvarCnvUrl = tekg_jbrowse_url('clinvarCnv.bb');
$defaultLoc = sprintf(
    '%s:%s..%s',
    $locus['chr'],
    number_format($locus['view_start'] + 1),
    number_format($locus['view_end'])
);

$defaultTracks = [
    'repeats_hg38',
    'ncbi_refseq_window',
    'clinvar_variants',
    'clinvar_cnv',
];
$pageMeta = [
    'te' => $locus['te'],
    'chr' => $locus['chr'],
    'start' => $locus['start'],
    'end' => $locus['end'],
    'viewStart' => $locus['view_start'],
    'viewEnd' => $locus['view_end'],
    'defaultLoc' => $defaultLoc,
    'totalHits' => (int) ($locus['entry']['total_hits'] ?? 0),
    'selectionRule' => (string) ($locus['entry']['selection_rule'] ?? ''),
    'sampledHits' => (int) ($locus['entry']['count_sampled'] ?? 0),
    'repeatFeatureCount' => count($repeatsRows),
    'refseqFeatureCount' => count($refseqRows),
];

if (trim((string) ($_GET['format'] ?? '')) === 'config') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'pageMeta' => $pageMeta,
        'defaultTracks' => $defaultTracks,
        'fastaUrl' => $fastaUrl,
        'faiUrl' => $faiUrl,
        'repeatTrackUrl' => $repeatTrackUrl,
        'refseqTrackUrl' => $refseqTrackUrl,
        'clinvarMainUrl' => $clinvarMainUrl,
        'clinvarCnvUrl' => $clinvarCnvUrl,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($isEmbedded) {
    ?>
<!doctype html>
<html lang="<?= htmlspecialchars($siteLang, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body class="jbrowse-embed-body">
<?php
} else {
    require __DIR__ . '/head.php';
}
?>
      <link rel="stylesheet" href="/TE-/assets/css/pages/jbrowse.css">

      <section class="jbrowse-shell<?= $isEmbedded ? ' is-embedded' : '' ?>">
        <div class="jbrowse-container">
          <?php if (!$isEmbedded): ?>
          <h1 class="jbrowse-title">JBrowse</h1>
          <div class="jbrowse-crumbs">
            <a href="<?= htmlspecialchars(site_url_with_state('/TE-/index.php', $siteLang), ENT_QUOTES, 'UTF-8') ?>">Home</a>
            <span>/</span>
            <a href="<?= htmlspecialchars(site_url_with_state('/TE-/genomic.php', $siteLang), ENT_QUOTES, 'UTF-8') ?>">Genomic</a>
            <span>/</span>
            <span>JBrowse</span>
          </div>
          <?php endif; ?>

          <div class="jbrowse-topbar">
            <div class="jbrowse-summary">
              <h2>Genome browser session</h2>
              <div class="jbrowse-meta">
                <div class="jbrowse-meta-item">
                  <span class="jbrowse-meta-label">TE</span>
                  <span class="jbrowse-meta-value"><?= htmlspecialchars($pageMeta['te'] !== '' ? $pageMeta['te'] : 'Custom locus', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="jbrowse-meta-item">
                  <span class="jbrowse-meta-label">Representative locus</span>
                  <span class="jbrowse-meta-value"><?= htmlspecialchars($pageMeta['chr'] . ':' . number_format($pageMeta['start'] + 1) . '-' . number_format($pageMeta['end']), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="jbrowse-meta-item">
                  <span class="jbrowse-meta-label">Initial browser window</span>
                  <span class="jbrowse-meta-value" id="jbrowseDefaultLoc"><?= htmlspecialchars($pageMeta['defaultLoc'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="jbrowse-meta-item">
                  <span class="jbrowse-meta-label">Total genomic hits</span>
                  <span class="jbrowse-meta-value"><?= htmlspecialchars((string) $pageMeta['totalHits'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="jbrowse-meta-item">
                  <span class="jbrowse-meta-label">Repeat features in window</span>
                  <span class="jbrowse-meta-value" id="jbrowseRepeatCount"><?= htmlspecialchars((string) $pageMeta['repeatFeatureCount'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="jbrowse-meta-item">
                  <span class="jbrowse-meta-label">RefSeq features in window</span>
                  <span class="jbrowse-meta-value" id="jbrowseRefseqCount"><?= htmlspecialchars((string) $pageMeta['refseqFeatureCount'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
              </div>
              <div class="jbrowse-track-toolbar">
                <div class="jbrowse-control-row">
                  <div class="jbrowse-hit-picker">
                    <label class="jbrowse-hit-picker-label" for="jbrowseHitSelect">Genomic hit</label>
                    <select id="jbrowseHitSelect" class="jbrowse-hit-picker-select">
                      <?php
                        $jbrowseRepresentative = is_array($locus['representative'] ?? null) ? $locus['representative'] : [];
                        foreach ((($locus['entry']['sample_hits'] ?? []) ?: []) as $hitIndex => $hit):
                          if (!is_array($hit)) {
                            continue;
                          }
                          $hitChrom = trim((string) ($hit['chrom'] ?? ''));
                          $hitStart = (int) ($hit['start'] ?? -1);
                          $hitEnd = (int) ($hit['end'] ?? -1);
                          if ($hitChrom === '' || $hitStart < 0 || $hitEnd <= $hitStart) {
                            continue;
                          }
                          $isSelectedHit = $hitChrom === (string) ($jbrowseRepresentative['chrom'] ?? '')
                            && $hitStart === (int) ($jbrowseRepresentative['start'] ?? -2)
                            && $hitEnd === (int) ($jbrowseRepresentative['end'] ?? -3);
                          $hitLabel = sprintf(
                            '%s:%s-%s | %s | len %s bp | score %s',
                            $hitChrom,
                            number_format($hitStart + 1),
                            number_format($hitEnd),
                            (((string) ($hit['strand'] ?? '+')) === '-') ? 'reverse strand' : 'forward strand',
                            number_format(max(1, (int) ($hit['length'] ?? ($hitEnd - $hitStart)))),
                            number_format((int) ($hit['score'] ?? 0))
                          );
                      ?>
                      <option value="<?= (int) $hitIndex ?>"
                              data-chrom="<?= htmlspecialchars($hitChrom, ENT_QUOTES, 'UTF-8') ?>"
                              data-start="<?= (int) $hitStart ?>"
                              data-end="<?= (int) $hitEnd ?>"
                              <?= $isSelectedHit ? 'selected' : '' ?>><?= htmlspecialchars($hitLabel, ENT_QUOTES, 'UTF-8') ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="jbrowse-track-list" id="jbrowseTrackControls">
                    <label class="jbrowse-track-item">
                      <input type="checkbox" data-track-id="repeats_hg38" checked>
                      <span class="jbrowse-track-dot" style="background:#d8a11a"></span>
                      <span class="jbrowse-track-name">Repeats</span>
                    </label>
                    <label class="jbrowse-track-item">
                      <input type="checkbox" data-track-id="ncbi_refseq_window" checked>
                      <span class="jbrowse-track-dot" style="background:#5fa1da"></span>
                      <span class="jbrowse-track-name">NCBI RefSeq</span>
                    </label>
                    <label class="jbrowse-track-item">
                      <input type="checkbox" data-track-id="clinvar_variants" checked>
                      <span class="jbrowse-track-dot" style="background:#73b36b"></span>
                      <span class="jbrowse-track-name">ClinVar variants</span>
                    </label>
                    <label class="jbrowse-track-item">
                      <input type="checkbox" data-track-id="clinvar_cnv" checked>
                      <span class="jbrowse-track-dot" style="background:#cc7f9f"></span>
                      <span class="jbrowse-track-name">ClinVar CNV</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="jbrowse-browser-stage">
            <div id="jbrowse_linear_genome_view">
              <div class="jbrowse-loading">Preparing standalone JBrowse session...</div>
            </div>
          </div>
        </div>
      </section>

      <script src="https://unpkg.com/@jbrowse/react-linear-genome-view2@3.5.0/dist/react-linear-genome-view.umd.production.min.js" crossorigin></script>
      <script id="jbrowse-page-meta" type="application/json"><?= json_encode($pageMeta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
      <script src="/TE-/assets/js/pages/jbrowse.js"></script>
<?php
if ($isEmbedded) {
    ?>
</body>
</html>
<?php
} else {
    require __DIR__ . '/foot.php';
}
?>
