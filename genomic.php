<?php
require_once __DIR__ . '/path_config.php';
$pageTitle = 'TE-KG Genomic';
$activePage = 'genomic';
$protoCurrentPath = tekg_app_url('genomic.php');
$protoSubtitle = 'Genomic views and loci-oriented TE exploration';
$pageExtraStylesheets = [
    tekg_assets_url('css/pages/genomic.css'),
];
require __DIR__ . '/head.php';
?>
      <section class="module-shell">
        <div class="proto-container">
          <h1 class="module-title">Genomic</h1>
          <div class="module-crumbs">
            <a href="<?= htmlspecialchars(site_url_with_state(tekg_app_url('index.php'), $siteLang), ENT_QUOTES, 'UTF-8') ?>">Home</a>
            <span>/</span>
            <span>Genomic</span>
          </div>

          <div class="module-panel">
            <h2>Genomic module placeholder</h2>
            <p>This entry point is now wired into the public navigation. It is reserved for genome-scale TE views such as locus summaries, annotation tracks, and chromosome-level distribution workflows.</p>
          </div>
        </div>
      </section>
<?php require __DIR__ . '/foot.php'; ?>
