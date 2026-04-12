<?php
$pageTitle = 'TE-KG JBrowse';
$activePage = 'genomic';
$protoCurrentPath = '/TE-/jbrowse.php';
$protoSubtitle = 'Standalone genome browser for TE loci';
require __DIR__ . '/site_i18n.php';
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

    $absolutePath = __DIR__ . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
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
$siteRenderer = site_renderer();

$root = __DIR__;
$jbrowseDir = $root . '/new_data/JBrowse';
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

$repeatCacheRel = 'new_data/JBrowse/cache/repeats/' . $regionKey . '.gff3';
$refseqCacheRel = 'new_data/JBrowse/cache/refseq/' . $regionKey . '.gff3';
$repeatCacheAbs = $root . '/' . $repeatCacheRel;
$refseqCacheAbs = $root . '/' . $refseqCacheRel;
jbrowse_write_gff3_cache($repeatCacheAbs, $repeatsRows);
jbrowse_write_gff3_cache($refseqCacheAbs, $refseqRows);

$repeatTrackUrl = '/TE-/' . str_replace('\\', '/', $repeatCacheRel);
$refseqTrackUrl = '/TE-/' . str_replace('\\', '/', $refseqCacheRel);
$fastaUrl = '/TE-/new_data/JBrowse/hg38.fa';
$faiUrl = '/TE-/new_data/JBrowse/hg38.fa.fai';
$clinvarMainUrl = '/TE-/new_data/JBrowse/clinvarMain.bb';
$clinvarCnvUrl = '/TE-/new_data/JBrowse/clinvarCnv.bb';
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
      <style>
        .jbrowse-shell {
          background: #f5f9ff;
          min-height: calc(100vh - 82px);
          padding: 34px 0 56px;
        }

        .jbrowse-shell.is-embedded {
          background: transparent;
          min-height: auto;
          padding: 0;
        }

        .jbrowse-embed-body {
          margin: 0;
          background: transparent;
        }

        .jbrowse-container {
          max-width: 1560px;
          margin: 0 auto;
          padding: 0 28px;
        }

        .jbrowse-title {
          margin: 0 0 18px;
          font-size: 52px;
          font-weight: 700;
          color: #8a93a3;
          line-height: 1.1;
        }

        .jbrowse-crumbs {
          display: flex;
          align-items: center;
          gap: 10px;
          margin-bottom: 26px;
          font-size: 16px;
          color: #70809a;
        }

        .jbrowse-crumbs a {
          color: #2f63b9;
          font-weight: 500;
        }

        .jbrowse-topbar {
          display: flex;
          justify-content: space-between;
          gap: 20px;
          align-items: stretch;
          margin-bottom: 18px;
          flex-wrap: wrap;
        }

        .jbrowse-summary {
          background: #ffffff;
          border: 1px solid #dbe7f8;
          border-radius: 14px;
          box-shadow: 0 10px 28px rgba(26, 60, 112, 0.08);
          padding: 20px 22px;
          min-width: 360px;
          flex: 1 1 100%;
        }

        .jbrowse-summary h2 {
          margin: 0 0 12px;
          font-size: 24px;
          color: #193458;
          font-weight: 700;
        }

        .jbrowse-meta {
          display: grid;
          grid-template-columns: repeat(3, minmax(0, 1fr));
          gap: 12px 18px;
        }

        .jbrowse-meta-item {
          min-width: 0;
        }

        .jbrowse-meta-label {
          display: block;
          font-size: 12px;
          letter-spacing: 0.08em;
          text-transform: uppercase;
          color: #7a8baa;
          margin-bottom: 4px;
          font-weight: 700;
        }

        .jbrowse-meta-value {
          display: block;
          font-size: 17px;
          line-height: 1.45;
          color: #27466f;
          word-break: break-word;
        }

        .jbrowse-note {
          margin-top: 14px;
          font-size: 14px;
          line-height: 1.7;
          color: #6a7f9e;
        }

        .jbrowse-track-toolbar {
          margin-top: 18px;
          padding-top: 16px;
          border-top: 1px solid #e5edf9;
        }

        .jbrowse-control-row {
          display: flex;
          flex-wrap: wrap;
          align-items: flex-end;
          gap: 14px 18px;
        }

        .jbrowse-hit-picker {
          min-width: min(420px, 100%);
          flex: 1 1 420px;
        }

        .jbrowse-hit-picker-label {
          display: block;
          margin-bottom: 8px;
          font-size: 12px;
          letter-spacing: 0.08em;
          text-transform: uppercase;
          color: #7a8baa;
          font-weight: 700;
        }

        .jbrowse-hit-picker-select {
          width: 100%;
          min-height: 44px;
          padding: 10px 14px;
          border-radius: 12px;
          border: 1px solid #d7e3f7;
          background: #fbfdff;
          color: #27466f;
          font-size: 14px;
          line-height: 1.4;
        }

        .jbrowse-track-list {
          display: flex;
          flex-wrap: wrap;
          gap: 10px;
        }

        .jbrowse-track-item {
          display: inline-flex;
          align-items: center;
          gap: 10px;
          padding: 10px 14px;
          border-radius: 999px;
          background: #f5f8ff;
          border: 1px solid #e0e9f8;
        }

        .jbrowse-track-item input[type='checkbox'] {
          width: 18px;
          height: 18px;
          accent-color: #3d8f57;
          cursor: pointer;
          margin: 0;
          flex: 0 0 auto;
        }

        .jbrowse-track-dot {
          width: 14px;
          height: 14px;
          border-radius: 50%;
          flex: 0 0 auto;
          border: 2px solid rgba(18, 43, 86, 0.12);
        }

        .jbrowse-track-name {
          font-size: 15px;
          color: #26466f;
          font-weight: 700;
          white-space: nowrap;
        }

        .jbrowse-browser-stage {
          margin-top: 10px;
        }

        .jbrowse-browser-head {
          margin: 0 0 10px;
          padding: 0;
          background: transparent;
          border: 0;
        }

        .jbrowse-browser-head strong {
          display: block;
          font-size: 24px;
          color: #1c3f73;
          margin-bottom: 6px;
        }

        .jbrowse-browser-head span {
          display: block;
          font-size: 15px;
          color: #6881a7;
        }

        #jbrowse_linear_genome_view {
          height: 840px;
          background: transparent;
        }

        .jbrowse-loading {
          height: 840px;
          display: flex;
          align-items: center;
          justify-content: center;
          color: #587399;
          font-size: 18px;
          border-radius: 16px;
          background: rgba(255, 255, 255, 0.62);
          border: 1px solid rgba(219, 231, 248, 0.9);
          box-shadow: inset 0 1px 0 rgba(255,255,255,0.7);
        }
      </style>

      <section class="jbrowse-shell<?= $isEmbedded ? ' is-embedded' : '' ?>">
        <div class="jbrowse-container">
          <?php if (!$isEmbedded): ?>
          <h1 class="jbrowse-title">JBrowse</h1>
          <div class="jbrowse-crumbs">
            <a href="<?= htmlspecialchars(site_url_with_state('/TE-/index.php', $siteLang, $siteRenderer), ENT_QUOTES, 'UTF-8') ?>">Home</a>
            <span>/</span>
            <a href="<?= htmlspecialchars(site_url_with_state('/TE-/genomic.php', $siteLang, $siteRenderer), ENT_QUOTES, 'UTF-8') ?>">Genomic</a>
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
      <script>
        (function () {
          const meta = <?= json_encode($pageMeta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
          const { React, createRoot, createViewState, JBrowseLinearGenomeView } = JBrowseReactLinearGenomeView;
          const mount = document.getElementById('jbrowse_linear_genome_view');
          const controls = Array.from(document.querySelectorAll('#jbrowseTrackControls input[data-track-id]'));
          const hitSelect = document.getElementById('jbrowseHitSelect');
          const defaultLocEl = document.getElementById('jbrowseDefaultLoc');
          const repeatCountEl = document.getElementById('jbrowseRepeatCount');
          const refseqCountEl = document.getElementById('jbrowseRefseqCount');
          const root = createRoot(mount);
          let runtimeConfig = null;

          function getSelectedTrackIds() {
            return controls.filter(input => input.checked).map(input => input.dataset.trackId);
          }

          function getSelectedHitParams() {
            const option = hitSelect && hitSelect.selectedOptions.length ? hitSelect.selectedOptions[0] : null;
            return {
              chrom: option ? String(option.dataset.chrom || '') : String(meta.chr || ''),
              start: option ? String(option.dataset.start || '') : String(meta.start || ''),
              end: option ? String(option.dataset.end || '') : String(meta.end || ''),
            };
          }

          function buildConfigUrl() {
            const url = new URL(window.location.href);
            const hit = getSelectedHitParams();
            if (meta.te) {
              url.searchParams.set('te', meta.te);
            }
            if (hit.chrom) {
              url.searchParams.set('chr', hit.chrom);
            }
            if (hit.start) {
              url.searchParams.set('start', hit.start);
            }
            if (hit.end) {
              url.searchParams.set('end', hit.end);
            }
            url.searchParams.set('format', 'config');
            return url.toString();
          }

          function renderBrowser() {
            if (!runtimeConfig) {
              return;
            }
            const selectedTrackIds = getSelectedTrackIds();
            const trackConfigs = [
              {
                type: 'FeatureTrack',
                trackId: 'repeats_hg38',
                name: 'Repeats',
                assemblyNames: ['hg38'],
                category: ['Annotation'],
                adapter: {
                  type: 'Gff3Adapter',
                  gffLocation: { uri: runtimeConfig.repeatTrackUrl },
                },
              },
              {
                type: 'FeatureTrack',
                trackId: 'ncbi_refseq_window',
                name: 'NCBI RefSeq',
                assemblyNames: ['hg38'],
                category: ['Annotation'],
                adapter: {
                  type: 'Gff3Adapter',
                  gffLocation: { uri: runtimeConfig.refseqTrackUrl },
                },
              },
              {
                type: 'FeatureTrack',
                trackId: 'clinvar_variants',
                name: 'ClinVar variants',
                assemblyNames: ['hg38'],
                category: ['ClinVar'],
                adapter: {
                  type: 'BigBedAdapter',
                  uri: runtimeConfig.clinvarMainUrl,
                },
              },
              {
                type: 'FeatureTrack',
                trackId: 'clinvar_cnv',
                name: 'ClinVar CNV',
                assemblyNames: ['hg38'],
                category: ['ClinVar'],
                adapter: {
                  type: 'BigBedAdapter',
                  uri: runtimeConfig.clinvarCnvUrl,
                },
              },
            ];
            const selectedTracks = trackConfigs.filter(track => selectedTrackIds.includes(track.trackId));
            const state = new createViewState({
              assembly: {
                name: 'hg38',
                sequence: {
                  type: 'ReferenceSequenceTrack',
                  trackId: 'hg38_reference',
                  name: 'Reference sequence',
                  assemblyNames: ['hg38'],
                  adapter: {
                    type: 'IndexedFastaAdapter',
                    fastaLocation: { uri: runtimeConfig.fastaUrl },
                    faiLocation: { uri: runtimeConfig.faiUrl },
                  },
                },
              },
              tracks: selectedTracks,
              defaultSession: {
                name: runtimeConfig.pageMeta && runtimeConfig.pageMeta.te ? `JBrowse - ${runtimeConfig.pageMeta.te}` : 'JBrowse locus session',
                view: {
                  id: 'linearGenomeView',
                  type: 'LinearGenomeView',
                  init: {
                    assembly: 'hg38',
                    loc: runtimeConfig.pageMeta.defaultLoc,
                    tracks: selectedTrackIds,
                  },
                },
              },
            });

            root.render(React.createElement(JBrowseLinearGenomeView, { viewState: state }));
          }

          function applyConfig(config) {
            runtimeConfig = config;
            if (defaultLocEl && config.pageMeta) {
              defaultLocEl.textContent = String(config.pageMeta.defaultLoc ?? '-');
            }
            if (repeatCountEl && config.pageMeta) {
              repeatCountEl.textContent = String(config.pageMeta.repeatFeatureCount ?? '-');
            }
            if (refseqCountEl && config.pageMeta) {
              refseqCountEl.textContent = String(config.pageMeta.refseqFeatureCount ?? '-');
            }
            renderBrowser();
            window.requestAnimationFrame(renderBrowser);
            window.setTimeout(renderBrowser, 120);
          }

          function loadSelectedHit() {
            root.render(React.createElement('div', { className: 'jbrowse-loading' }, 'Loading selected genomic hit...'));
            fetch(buildConfigUrl(), { cache: 'no-store' })
              .then(function (response) {
                if (!response.ok) {
                  throw new Error('Failed to load JBrowse config');
                }
                return response.json();
              })
              .then(applyConfig)
              .catch(function (error) {
                console.error(error);
                mount.innerHTML = '<div class="jbrowse-loading">Genome browser is unavailable right now.</div>';
              });
          }

          controls.forEach(input => {
            input.addEventListener('change', renderBrowser);
          });
          if (hitSelect) {
            hitSelect.addEventListener('change', loadSelectedHit);
          }

          loadSelectedHit();
        })();
      </script>
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

