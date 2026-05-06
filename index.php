<?php
$pageTitle = 'TE-KG Home';
$activePage = 'home';
$protoCurrentPath = '/TE-/index.php';
$protoSubtitle = 'A transposable-element knowledge graph for exploration and discovery';
require __DIR__ . '/head.php';

$seedPath = __DIR__ . '/data/processed/tekg2/tekg2_seed.json';
$seed = json_decode((string) file_get_contents($seedPath), true);
$nodeBuckets = $seed['nodes'] ?? [];

$datasetMeta = [
    [
        'key' => 'TE',
        'bucket' => 'transposons',
        'label' => 'TE',
        'gradient' => 'linear-gradient(180deg, #edf5ff 0%, #d9e9ff 100%)',
        'shadow' => '0 4px 14px rgba(30, 74, 142, 0.12)',
        'text' => '#214b8d',
        'samples' => ['LINE-1', 'L1HS', 'AluY', 'SVA_F'],
    ],
    [
        'key' => 'Disease',
        'bucket' => 'diseases',
        'label' => 'Disease',
        'gradient' => 'linear-gradient(180deg, #fff0f2 0%, #ffdbe3 100%)',
        'shadow' => '0 4px 14px rgba(200, 79, 98, 0.12)',
        'text' => '#b54d60',
        'samples' => ["Alzheimer's disease", 'breast cancer', 'lung cancer', 'Duchenne muscular dystrophy'],
    ],
    [
        'key' => 'Function',
        'bucket' => 'functions',
        'label' => 'Function',
        'gradient' => 'linear-gradient(180deg, #eefaf5 0%, #d8f0db 100%)',
        'shadow' => '0 4px 14px rgba(58, 126, 73, 0.12)',
        'text' => '#2f8b63',
        'samples' => ['retrotransposition', 'genomic instability', 'DNA repair', 'innate immune response'],
    ],
    [
        'key' => 'Gene',
        'bucket' => 'genes',
        'label' => 'Gene',
        'gradient' => 'linear-gradient(180deg, #f4f1ff 0%, #e0d6ff 100%)',
        'shadow' => '0 4px 14px rgba(102, 86, 216, 0.12)',
        'text' => '#6656d8',
        'samples' => ['CYBB', 'Dystrophin', 'SAMHD1', 'APOBEC3A'],
    ],
    [
        'key' => 'Protein',
        'bucket' => 'proteins',
        'label' => 'Protein',
        'gradient' => 'linear-gradient(180deg, #eefaf9 0%, #d5f0ec 100%)',
        'shadow' => '0 4px 14px rgba(45, 143, 135, 0.12)',
        'text' => '#2d8f87',
        'samples' => ['ORF1p', 'ORF2p', 'L1ORF1p', 'Reverse transcriptase'],
    ],
    [
        'key' => 'RNA',
        'bucket' => 'rnas',
        'label' => 'RNA',
        'gradient' => 'linear-gradient(180deg, #eef7ff 0%, #dcebff 100%)',
        'shadow' => '0 4px 14px rgba(61, 136, 219, 0.12)',
        'text' => '#3d88db',
        'samples' => ['LINE-1 RNA', 'LINE-1 mRNA', 'mRNA', 'RNA'],
    ],
    [
        'key' => 'Mutation',
        'bucket' => 'mutations',
        'label' => 'Mutation',
        'gradient' => 'linear-gradient(180deg, #fff5ea 0%, #ffe3c4 100%)',
        'shadow' => '0 4px 14px rgba(219, 124, 31, 0.12)',
        'text' => '#db7c1f',
        'samples' => ['P53 mutation', 'TREX1 mutation', 'ATM mutation', 'MECP2 mutation'],
    ],
    [
        'key' => 'Pharmaceutical',
        'bucket' => 'pharmaceuticals',
        'label' => 'Pharmaceutical',
        'gradient' => 'linear-gradient(180deg, #f5f2ff 0%, #e7ddff 100%)',
        'shadow' => '0 4px 14px rgba(122, 96, 212, 0.12)',
        'text' => '#7a60d4',
        'samples' => ['lamivudine', 'Melatonin', 'Ribavirin', 'Dexamethasone'],
    ],
    [
        'key' => 'Toxin',
        'bucket' => 'toxins',
        'label' => 'Toxin',
        'gradient' => 'linear-gradient(180deg, #fff4f1 0%, #f7d7cf 100%)',
        'shadow' => '0 4px 14px rgba(178, 93, 73, 0.12)',
        'text' => '#b25d49',
        'samples' => ['Benzo(a)pyrene', 'cadmium chloride', '1,3-Butadiene', 'TCDD'],
    ],
    [
        'key' => 'Lipid',
        'bucket' => 'lipids',
        'label' => 'Lipid',
        'gradient' => 'linear-gradient(180deg, #f4faeb 0%, #e1efc9 100%)',
        'shadow' => '0 4px 14px rgba(110, 162, 59, 0.12)',
        'text' => '#6ea23b',
        'samples' => ['arachidonic acid', 'palmitic acid', 'C15H31-IMeNMe3', 'C17H31-IMeNMe3'],
    ],
    [
        'key' => 'Peptide',
        'bucket' => 'peptides',
        'label' => 'Peptide',
        'gradient' => 'linear-gradient(180deg, #edf9f7 0%, #d2f1ec 100%)',
        'shadow' => '0 4px 14px rgba(36, 159, 151, 0.12)',
        'text' => '#249f97',
        'samples' => ['2A peptide', 'amyloid beta', 'beta-endorphin', 'peptide'],
    ],
    [
        'key' => 'Carbohydrate',
        'bucket' => 'carbohydrates',
        'label' => 'Carbohydrate',
        'gradient' => 'linear-gradient(180deg, #fdf9ea 0%, #f0e2ad 100%)',
        'shadow' => '0 4px 14px rgba(171, 139, 40, 0.12)',
        'text' => '#ab8b28',
        'samples' => ['Fasting glucose', 'Disialoganglioside GD2', 'cytidine diphosphate ribitol'],
    ],
    [
        'key' => 'Paper',
        'bucket' => 'papers',
        'label' => 'Paper',
        'gradient' => 'linear-gradient(180deg, #fff6eb 0%, #ffe3c5 100%)',
        'shadow' => '0 4px 14px rgba(183, 122, 22, 0.12)',
        'text' => '#b77a16',
        'samples' => ['PMID: 40600062', 'PMID: 41000934', 'PMID: 40707718', 'PMID: 41473303'],
    ],
];

$classDatasetStats = [
    [
        'key' => 'class-i',
        'label' => 'Class I: Retrotransposons',
        'count' => 1140,
        'color' => '#4f86df',
        'description' => 'Retrotransposons',
    ],
    [
        'key' => 'class-ii',
        'label' => 'Class II: DNA Transposons',
        'count' => 440,
        'color' => '#b7d4ff',
        'description' => 'DNA Transposons',
    ],
];
$classDatasetTotal = array_sum(array_map(static fn(array $item): int => (int) $item['count'], $classDatasetStats));
$classDatasetStats = array_map(
    static function (array $item) use ($classDatasetTotal): array {
        $item['percentage'] = $classDatasetTotal > 0 ? ((float) $item['count'] / (float) $classDatasetTotal) * 100.0 : 0.0;
        return $item;
    },
    $classDatasetStats
);

$datasetItems = [];
foreach ($datasetMeta as $meta) {
    $bucket = $meta['bucket'];
    $count = count($nodeBuckets[$bucket] ?? []);
    $samples = $meta['samples'];
    if (empty($samples)) {
        $rawItems = $nodeBuckets[$bucket] ?? [];
        $samples = [];
        foreach ($rawItems as $rawItem) {
            $name = is_array($rawItem) ? ($rawItem['name'] ?? $rawItem['title'] ?? $rawItem['id'] ?? '') : (string) $rawItem;
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $samples[] = $name;
            if (count($samples) >= 4) {
                break;
            }
        }
    }
    $meta['count'] = $count;
    $meta['samples'] = $samples;
    $datasetItems[] = $meta;
}

$primaryDatasetItem = null;
$secondaryDatasetItems = [];
foreach ($datasetItems as $item) {
    if ($item['key'] === 'TE') {
        $primaryDatasetItem = $item;
        continue;
    }
    $secondaryDatasetItems[] = $item;
}
$secondarySplitIndex = (int) ceil(count($secondaryDatasetItems) / 2);
$leftDatasetItems = array_slice($secondaryDatasetItems, 0, $secondarySplitIndex);
$rightDatasetItems = array_slice($secondaryDatasetItems, $secondarySplitIndex);

$statusChartViews = [
    'root' => [
        'count' => $primaryDatasetItem['count'] ?? 0,
        'label' => $primaryDatasetItem['label'] ?? 'TE',
        'segments' => $classDatasetStats,
    ],
];

foreach ($statusChartViews as $viewKey => $view) {
    $viewTotal = (float) ($view['count'] ?? 0);
    $segments = $view['segments'] ?? [];
    $segments = array_map(
        static function (array $segment) use ($viewTotal): array {
            $segment['percentage'] = $viewTotal > 0 ? ((float) $segment['count'] / $viewTotal) * 100.0 : 0.0;
            return $segment;
        },
        $segments
    );
    $statusChartViews[$viewKey]['segments'] = $segments;
}

$overviewCopy = 'TE-KG is a comprehensive resource designed to support exploration of transposable elements, their associated diseases, molecular functions, and supporting literature in one integrated environment. This homepage highlights the overall scope of the resource, the public dataset scale, and direct paths into browsing, graph exploration, genomic, expression, epigenetics, download, and project information.';

$quickLinks = [
    ['title' => 'Home', 'href' => site_url_with_state('/TE-/index.php', $siteLang), 'icon' => 'home'],
    ['title' => 'Browse', 'href' => site_url_with_state('/TE-/browse.php', $siteLang), 'icon' => 'browse'],
    ['title' => 'TE-KG', 'href' => site_url_with_state('/TE-/preview.php', $siteLang), 'icon' => 'preview'],
    ['title' => 'Genomic', 'href' => site_url_with_state('/TE-/genomic.php', $siteLang), 'icon' => 'genomic'],
    ['title' => 'Expression', 'href' => site_url_with_state('/TE-/expression.php', $siteLang), 'icon' => 'expression'],
    ['title' => 'Epigenetics', 'href' => site_url_with_state('/TE-/epigenetics.php', $siteLang), 'icon' => 'epigenetics'],
    ['title' => 'Download', 'href' => site_url_with_state('/TE-/download.php', $siteLang), 'icon' => 'download'],
    ['title' => 'About', 'href' => site_url_with_state('/TE-/about.php', $siteLang), 'icon' => 'about'],
];

$homeGraphQuery = 'LINE1';
$treeEmbedUrl = site_url_with_state('/TE-/index_g6.html', $siteLang, null, [
    'embed' => 'home-preview',
    'q' => $homeGraphQuery,
    'type' => 'TE',
]);
?>
      <link rel="stylesheet" href="/TE-/assets/css/pages/index.css">

      <section class="hero-area">
        <div class="proto-container">
          <div class="hero-row">
            <div class="hero-content">
              <h1>Overview</h1>
              <p><?= htmlspecialchars($overviewCopy, ENT_QUOTES, 'UTF-8') ?></p>
              <a class="learn-more" href="<?= htmlspecialchars(site_url_with_state('/TE-/about.php', $siteLang), ENT_QUOTES, 'UTF-8') ?>">Learn More...</a>
            </div>
            <div class="hero-figure">
              <div class="hero-figure-frame">
              <div class="figure-canvas">
                <div class="tree-frame">
                  <iframe src="<?= htmlspecialchars($treeEmbedUrl, ENT_QUOTES, 'UTF-8') ?>" title="LINE1 dynamic graph preview" loading="lazy"></iframe>
                </div>
              </div>
            </div>
          </div>
          </div>
        </div>
      </section>

      <section class="status-section">
        <div class="proto-container">
          <div class="section-title">
            <h3>Dataset Status</h3>
          </div>
          <div class="status-layout">
            <?php if ($primaryDatasetItem !== null): ?>
              <div class="status-orbit">
                <div class="status-cluster status-cluster--left">
                  <?php foreach ($leftDatasetItems as $item): ?>
                    <div class="status-item status-item--orbit" data-status-item="<?= htmlspecialchars($item['key'], ENT_QUOTES, 'UTF-8') ?>" style="--status-gradient: <?= htmlspecialchars($item['gradient'], ENT_QUOTES, 'UTF-8') ?>; --status-shadow: <?= htmlspecialchars($item['shadow'], ENT_QUOTES, 'UTF-8') ?>; --status-text: <?= htmlspecialchars($item['text'], ENT_QUOTES, 'UTF-8') ?>;">
                      <button class="status-trigger" type="button" data-status-trigger="<?= htmlspecialchars($item['key'], ENT_QUOTES, 'UTF-8') ?>" aria-expanded="false">
                        <div class="status-badge">
                          <div class="status-count"><?= number_format($item['count']) ?></div>
                          <div class="status-name"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                      </button>
                      <div class="status-panel">
                        <div class="status-panel-inner">
                          <h4><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?> examples</h4>
                          <div class="status-panel-list">
                            <?php foreach ($item['samples'] as $sample): ?>
                              <a class="status-panel-link" href="javascript:void(0)"><?= htmlspecialchars($sample, ENT_QUOTES, 'UTF-8') ?></a>
                            <?php endforeach; ?>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>

                <div class="status-item status-item--primary" data-status-item="<?= htmlspecialchars($primaryDatasetItem['key'], ENT_QUOTES, 'UTF-8') ?>" style="--status-gradient: <?= htmlspecialchars($primaryDatasetItem['gradient'], ENT_QUOTES, 'UTF-8') ?>; --status-shadow: <?= htmlspecialchars($primaryDatasetItem['shadow'], ENT_QUOTES, 'UTF-8') ?>; --status-text: <?= htmlspecialchars($primaryDatasetItem['text'], ENT_QUOTES, 'UTF-8') ?>;">
                  <button class="status-trigger" type="button" data-status-trigger="<?= htmlspecialchars($primaryDatasetItem['key'], ENT_QUOTES, 'UTF-8') ?>" aria-expanded="false">
                    <div class="status-badge status-badge--ring">
                      <svg
                        class="status-ring-chart"
                        viewBox="0 0 360 360"
                        role="img"
                        aria-label="TE class distribution ring chart"
                        data-chart='<?= htmlspecialchars(json_encode($statusChartViews, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
                        data-chart-view="root"
                      ></svg>
                      <div class="status-badge-center">
                        <div class="status-count" data-ring-count><?= number_format($primaryDatasetItem['count']) ?></div>
                        <div class="status-name" data-ring-label><?= htmlspecialchars($primaryDatasetItem['label'], ENT_QUOTES, 'UTF-8') ?></div>
                      </div>
                    </div>
                  </button>
                  <div class="status-panel">
                    <div class="status-panel-inner">
                      <h4><?= htmlspecialchars($primaryDatasetItem['label'], ENT_QUOTES, 'UTF-8') ?> examples</h4>
                      <div class="status-panel-list">
                        <?php foreach ($primaryDatasetItem['samples'] as $sample): ?>
                          <a class="status-panel-link" href="javascript:void(0)"><?= htmlspecialchars($sample, ENT_QUOTES, 'UTF-8') ?></a>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="status-cluster status-cluster--right">
                  <?php foreach ($rightDatasetItems as $item): ?>
                    <div class="status-item status-item--orbit" data-status-item="<?= htmlspecialchars($item['key'], ENT_QUOTES, 'UTF-8') ?>" style="--status-gradient: <?= htmlspecialchars($item['gradient'], ENT_QUOTES, 'UTF-8') ?>; --status-shadow: <?= htmlspecialchars($item['shadow'], ENT_QUOTES, 'UTF-8') ?>; --status-text: <?= htmlspecialchars($item['text'], ENT_QUOTES, 'UTF-8') ?>;">
                      <button class="status-trigger" type="button" data-status-trigger="<?= htmlspecialchars($item['key'], ENT_QUOTES, 'UTF-8') ?>" aria-expanded="false">
                        <div class="status-badge">
                          <div class="status-count"><?= number_format($item['count']) ?></div>
                          <div class="status-name"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                      </button>
                      <div class="status-panel">
                        <div class="status-panel-inner">
                          <h4><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?> examples</h4>
                          <div class="status-panel-list">
                            <?php foreach ($item['samples'] as $sample): ?>
                              <a class="status-panel-link" href="javascript:void(0)"><?= htmlspecialchars($sample, ENT_QUOTES, 'UTF-8') ?></a>
                            <?php endforeach; ?>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>

                <div class="status-tooltip" id="statusTooltip" hidden>
                  <div class="status-tooltip-title"></div>
                  <div class="status-tooltip-meta"></div>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="links-section">
        <div class="proto-container">
          <div class="section-title">
            <h3>Quick Links</h3>
          </div>
          <div class="link-grid">
            <?php foreach ($quickLinks as $item): ?>
              <a class="link-card" href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="link-card-icon">
                  <?php if ($item['icon'] === 'home'): ?>
                    <svg viewBox="0 0 64 64" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" d="M10 30 32 12l22 18"/><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linejoin="round" d="M16 28v24h32V28"/><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linejoin="round" d="M27 52V36h10v16"/></svg>
                  <?php elseif ($item['icon'] === 'preview'): ?>
                    <svg viewBox="0 0 64 64" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" d="M10 14h44v36H10z"/><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" d="M18 24h28M18 32h18M18 40h22"/><circle cx="47" cy="37" r="8" fill="none" stroke="currentColor" stroke-width="3.2"/><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" d="m53 43 5 5"/></svg>
                  <?php elseif ($item['icon'] === 'browse'): ?>
                    <svg viewBox="0 0 64 64" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linejoin="round" d="M12 14h40v36H12z"/><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" d="M20 24h24M20 32h24M20 40h18"/><circle cx="48" cy="40" r="4" fill="currentColor"/></svg>
                  <?php elseif ($item['icon'] === 'genomic'): ?>
                    <svg viewBox="0 0 64 64" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" d="M20 14c8 0 8 8 16 8s8-8 16-8"/><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" d="M20 50c8 0 8-8 16-8s8 8 16 8"/><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" d="M20 14v36M52 14v36"/><path fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" d="M24 22h8M32 30h8M24 38h8"/></svg>
                  <?php elseif ($item['icon'] === 'expression'): ?>
                    <svg viewBox="0 0 64 64" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" d="M14 48h36"/><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" d="M18 48V30M30 48V20M42 48V12"/><circle cx="18" cy="30" r="3" fill="currentColor"/><circle cx="30" cy="20" r="3" fill="currentColor"/><circle cx="42" cy="12" r="3" fill="currentColor"/></svg>
                  <?php elseif ($item['icon'] === 'epigenetics'): ?>
                    <svg viewBox="0 0 64 64" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" d="M18 18h28v28H18z"/><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" d="M18 26h28M26 18v28"/><circle cx="40" cy="26" r="4" fill="none" stroke="currentColor" stroke-width="3"/><path fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" d="M40 10v6M54 26h-6"/></svg>
                  <?php elseif ($item['icon'] === 'download'): ?>
                    <svg viewBox="0 0 64 64" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linejoin="round" d="M16 50h32a6 6 0 0 0 6-6V20l-10-10H16a6 6 0 0 0-6 6v28a6 6 0 0 0 6 6Z"/><path fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round" d="M32 24v16"/><path fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round" d="m25 34 7 7 7-7"/></svg>
                  <?php elseif ($item['icon'] === 'about'): ?>
                    <svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="20" r="10" fill="none" stroke="currentColor" stroke-width="3.2"/><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" d="M18 50c2-9 8-14 14-14s12 5 14 14"/><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" d="M16 18h-6M54 18h-6M32 6V0"/></svg>
                  <?php else: ?>
                    <svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="18" fill="none" stroke="currentColor" stroke-width="3.2"/><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" d="M32 24v10"/><circle cx="32" cy="40" r="2.6" fill="currentColor"/></svg>
                  <?php endif; ?>
                </div>
                <h4><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></h4>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <script src="/TE-/assets/js/pages/index.js"></script>
    </main>
  </div>
</body>
</html>

