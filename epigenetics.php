<?php
$pageTitle = 'TE-KG Epigenetics';
$activePage = 'epigenetics';
$protoCurrentPath = '/TE-/epigenetics.php';
$protoSubtitle = 'Epigenetic annotations and regulatory TE signals';
require __DIR__ . '/head.php';
?>
      <link rel="stylesheet" href="/TE-/assets/css/pages/epigenetics.css">

      <section class="module-shell">
        <div class="proto-container">
          <h1 class="module-title">Epigenetics</h1>
          <div class="module-crumbs">
            <a href="<?= htmlspecialchars(site_url_with_state('/TE-/index.php', $siteLang), ENT_QUOTES, 'UTF-8') ?>">Home</a>
            <span>/</span>
            <span>Epigenetics</span>
          </div>

          <div class="module-panel">
            <h2>Epigenetics module placeholder</h2>
            <p>This entry point is now wired into the public navigation. It is reserved for methylation, chromatin-state, and other epigenetic annotations linked to transposable elements.</p>
          </div>
        </div>
      </section>
<?php require __DIR__ . '/foot.php'; ?>
