<?php
$pageTitle = 'TE-KG Browse Item';
$activePage = 'browse';
$protoCurrentPath = '/TE-/browse_item.php';
$protoSubtitle = 'Placeholder target for browse row navigation';
require __DIR__ . '/head.php';

$teName = trim((string)($_GET['te'] ?? ''));
if ($teName === '') {
    $teName = 'Unknown TE';
}
?>
      <style>
        .browse-item-shell {
          background: #f5f9ff;
          min-height: calc(100vh - 82px);
          padding: 34px 0 54px;
        }

        .proto-container {
          max-width: 1100px;
          margin: 0 auto;
          padding: 0 28px;
        }

        .browse-item-card {
          background: #ffffff;
          border: 1px solid #dbe7f8;
          border-radius: 14px;
          box-shadow: 0 10px 28px rgba(26, 60, 112, 0.08);
          padding: 28px 24px;
        }

        .browse-item-crumbs {
          display: flex;
          align-items: center;
          gap: 10px;
          margin-bottom: 20px;
          color: #70809a;
          font-size: 15px;
        }

        .browse-item-crumbs a {
          color: #2f63b9;
          font-weight: 600;
        }

        .browse-item-title {
          margin: 0 0 12px;
          font-size: 38px;
          line-height: 1.1;
          color: #163f86;
          font-weight: 800;
        }

        .browse-item-copy {
          margin: 0 0 18px;
          color: #5b7091;
          font-size: 15px;
          line-height: 1.9;
        }

        .browse-item-actions {
          display: flex;
          gap: 12px;
          flex-wrap: wrap;
        }

        .browse-item-link {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          min-height: 44px;
          padding: 0 18px;
          border-radius: 999px;
          border: 1px solid #d6e2f5;
          background: #ffffff;
          color: #2f63b9;
          font-size: 14px;
          font-weight: 700;
          text-decoration: none;
        }

        .browse-item-link.is-primary {
          background: #2f63b9;
          border-color: #2f63b9;
          color: #ffffff;
        }
      </style>

      <main class="proto-main">
        <section class="browse-item-shell">
          <div class="proto-container">
            <div class="browse-item-card">
              <div class="browse-item-crumbs">
                <a href="<?= htmlspecialchars(site_url_with_state('/TE-/index.php', $siteLang, $siteRenderer), ENT_QUOTES, 'UTF-8') ?>">Home</a>
                <span>/</span>
                <a href="<?= htmlspecialchars(site_url_with_state('/TE-/browse.php', $siteLang, $siteRenderer), ENT_QUOTES, 'UTF-8') ?>">Browse</a>
                <span>/</span>
                <span><?= htmlspecialchars($teName, ENT_QUOTES, 'UTF-8') ?></span>
              </div>

              <h1 class="browse-item-title"><?= htmlspecialchars($teName, ENT_QUOTES, 'UTF-8') ?></h1>
              <p class="browse-item-copy">This is a temporary placeholder destination for Browse row navigation. We can later redirect this entry to the final Search page, Preview page, or a dedicated TE detail page once the data source and target workflow are finalized.</p>

              <div class="browse-item-actions">
                <a class="browse-item-link is-primary" href="<?= htmlspecialchars(site_url_with_state('/TE-/browse.php', $siteLang, $siteRenderer), ENT_QUOTES, 'UTF-8') ?>">Back to Browse</a>
                <a class="browse-item-link" href="<?= htmlspecialchars(site_url_with_state('/TE-/search.php', $siteLang, $siteRenderer, ['q' => $teName]), ENT_QUOTES, 'UTF-8') ?>">Open in Search</a>
                <a class="browse-item-link" href="<?= htmlspecialchars(site_url_with_state('/TE-/preview.php', $siteLang, $siteRenderer), ENT_QUOTES, 'UTF-8') ?>">Open Preview</a>
              </div>
            </div>
          </div>
        </section>
      </main>
    </div>
  </body>
</html>
