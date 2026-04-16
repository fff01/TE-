<?php
$pageTitle = 'TE-KG Genomic';
$activePage = 'genomic';
$protoCurrentPath = '/TE-/genomic.php';
$protoSubtitle = 'Genomic views and loci-oriented TE exploration';
require __DIR__ . '/head.php';
?>
      <link rel="stylesheet" href="/TE-/assets/css/pages/genomic.css">

      <section class="module-shell">
        <div class="proto-container">
          <h1 class="module-title">Genomic</h1>
          <div class="module-crumbs">
            <a href="<?= htmlspecialchars(site_url_with_state('/TE-/index.php', $siteLang, $siteRenderer), ENT_QUOTES, 'UTF-8') ?>">Home</a>
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
