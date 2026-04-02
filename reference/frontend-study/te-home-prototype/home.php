<?php
$pageTitle = 'TE-KG Home Prototype';
$activePage = 'home';
$protoCurrentPath = '/TE-/reference/frontend-study/te-home-prototype/home.php';
$protoSubtitle = 'Prototype home page aligned to the ncPlantDB home structure';
require __DIR__ . '/head.php';

$seedPath = dirname(__DIR__, 3) . '/data/processed/te_kg2_graph_seed.json';
$seed = json_decode((string) file_get_contents($seedPath), true);
$nodeBuckets = $seed['nodes'] ?? [];
$datasetCounts = [
    'TE' => count($nodeBuckets['transposons'] ?? []),
    'Disease' => count($nodeBuckets['diseases'] ?? []),
    'Function' => count($nodeBuckets['functions'] ?? []),
    'Paper' => count($nodeBuckets['papers'] ?? []),
];

$caseStudies = [
    'TE' => ['LINE1', 'L1HS', 'Alu', 'HERV-K'],
    'Disease' => ["Alzheimer's disease", 'breast cancer', 'lung cancer', 'Frontotemporal dementia'],
    'Function' => ['retrotransposition', 'genomic instability', 'innate immune response', 'DNA damage'],
    'Paper' => ['PMID: 40600062', 'PMID: 41000934', 'PMID: 40707718', 'PMID: 41473303'],
];

$overviewCopy = 'TE-KG is a comprehensive resource designed to support exploration of transposable elements, their associated diseases, molecular functions, and supporting literature in one integrated environment. The current prototype focuses on a clearer home-page entry experience, highlighting the overall scope of the resource, the present dataset scale, and direct paths into graph preview, search, download, and project information.';

$quickLinks = [
    ['title' => 'Preview', 'href' => site_url_with_state('/TE-/reference/frontend-study/te-home-prototype/preview.php', $siteLang, $siteRenderer), 'icon' => 'preview'],
    ['title' => 'Search', 'href' => site_url_with_state('/TE-/reference/frontend-study/te-home-prototype/search.php', $siteLang, $siteRenderer), 'icon' => 'search'],
    ['title' => 'Download', 'href' => site_url_with_state('/TE-/reference/frontend-study/te-home-prototype/download.php', $siteLang, $siteRenderer), 'icon' => 'download'],
    ['title' => 'About', 'href' => site_url_with_state('/TE-/reference/frontend-study/te-home-prototype/about.php', $siteLang, $siteRenderer), 'icon' => 'about'],
];

$treeEmbedUrl = $siteRenderer === 'g6'
    ? site_url_with_state('/TE-/index_g6.html', $siteLang, 'g6', ['embed' => 'home-preview'])
    : site_url_with_state('/TE-/index_demo.html', $siteLang, 'cytoscape', ['embed' => 'home-preview']);
?>
      <style>
        .hero-area {
          background: #f4f9ff;
          padding: 54px 0 70px;
        }

        .proto-container {
          max-width: 1320px;
          margin: 0 auto;
          padding: 0 28px;
        }

        .hero-row {
          display: grid;
          grid-template-columns: minmax(0, 1fr) minmax(560px, 1fr);
          gap: 34px;
          align-items: center;
        }

        .hero-content h1 {
          margin: 0 0 20px;
          font-size: 58px;
          line-height: 1.08;
          color: #163f86;
          font-weight: 800;
        }

        .hero-content p {
          margin: 0;
          color: #526d96;
          font-size: 15px;
          line-height: 2.0;
          text-align: justify;
        }

        .hero-content .learn-more {
          margin-top: 20px;
          color: #2f63b9;
          font-size: 15px;
          font-weight: 600;
        }

        .hero-figure {
          background: #fff;
          border-radius: 8px;
          box-shadow: 0 6px 24px rgba(34, 68, 120, 0.12);
          padding: 0;
          overflow: hidden;
        }

        .hero-figure-frame {
          min-height: 520px;
          padding: 0;
          border: none;
          border-radius: 0;
          background: transparent;
        }

        .figure-canvas {
          min-height: 520px;
          padding: 0;
          border: none;
          border-radius: 0;
          background: transparent;
        }

        .tree-frame {
          width: 100%;
          height: 100%;
          min-height: 520px;
          border-radius: 8px;
          overflow: hidden;
          background: #ffffff;
          border: none;
        }

        .tree-frame iframe {
          width: 100%;
          height: 100%;
          min-height: 520px;
          border: 0;
          display: block;
        }

        .status-section {
          padding: 54px 0 26px;
          background: #fff;
        }

        .section-title {
          text-align: center;
          margin-bottom: 46px;
        }

        .section-title h3 {
          margin: 0;
          font-size: 15px;
          font-weight: 700;
          text-transform: uppercase;
          color: #214b8d;
          letter-spacing: 0.04em;
        }

        .status-grid {
          display: grid;
          grid-template-columns: repeat(4, minmax(0, 1fr));
          gap: 24px;
          align-items: start;
        }

        .status-item {
          text-align: center;
        }

        .status-trigger {
          border: 0;
          background: transparent;
          padding: 0;
          width: 100%;
          cursor: pointer;
          color: inherit;
        }

        .status-badge {
          width: 188px;
          height: 188px;
          margin: 0 auto 14px;
          border-radius: 50%;
          background: linear-gradient(180deg, #edf5ff 0%, #d9e9ff 100%);
          border: 8px solid #f8fbff;
          box-shadow: 0 4px 14px rgba(30, 74, 142, 0.12);
          display: grid;
          align-content: center;
          justify-items: center;
          gap: 8px;
        }

        .status-item[data-status-item="TE"] .status-badge {
          background: linear-gradient(180deg, #edf5ff 0%, #d9e9ff 100%);
          box-shadow: 0 4px 14px rgba(30, 74, 142, 0.12);
        }

        .status-item[data-status-item="Disease"] .status-badge {
          background: linear-gradient(180deg, #ffeef2 0%, #ffd9e5 100%);
          box-shadow: 0 4px 14px rgba(170, 61, 103, 0.12);
        }

        .status-item[data-status-item="Function"] .status-badge {
          background: linear-gradient(180deg, #eef9ef 0%, #d8f0db 100%);
          box-shadow: 0 4px 14px rgba(58, 126, 73, 0.12);
        }

        .status-item[data-status-item="Paper"] .status-badge {
          background: linear-gradient(180deg, #fff4e8 0%, #ffe2c3 100%);
          box-shadow: 0 4px 14px rgba(176, 116, 46, 0.12);
        }

        .status-count {
          font-size: 38px;
          line-height: 1;
          font-weight: 800;
          color: #214b8d;
        }

        .status-name {
          font-size: 18px;
          font-weight: 700;
          color: #214b8d;
        }

        .status-panel {
          max-height: 0;
          overflow: hidden;
          transition: max-height 0.26s ease;
          margin-top: 18px;
        }

        .status-item.is-open .status-panel {
          max-height: 260px;
        }

        .status-panel-inner {
          background: #f6faff;
          border: 1px solid #d9e6fb;
          border-radius: 10px;
          padding: 14px;
          text-align: left;
        }

        .status-panel-inner h4 {
          margin: 0 0 10px;
          font-size: 14px;
          color: #214b8d;
          font-weight: 700;
        }

        .status-panel-list {
          display: grid;
          gap: 8px;
        }

        .status-panel-link {
          display: block;
          background: #fff;
          border: 1px solid #d9e6fb;
          border-radius: 8px;
          padding: 10px 12px;
          color: #355f9e;
          font-size: 14px;
          font-weight: 600;
          line-height: 1.4;
        }

        .links-section {
          padding: 34px 0 70px;
          background: #fff;
        }

        .link-grid {
          display: grid;
          grid-template-columns: repeat(4, minmax(0, 1fr));
          gap: 18px;
        }

        .link-card {
          background: #fff;
          border: 2px solid #d7e2f3;
          border-radius: 10px;
          padding: 22px 18px 18px;
          min-height: 248px;
          box-shadow: none;
          text-align: center;
        }

        .link-card:hover {
          border-color: #aac2e8;
        }

        .link-card-icon {
          width: 112px;
          height: 112px;
          margin: 0 auto 16px;
          color: #27558f;
        }

        .link-card-icon svg {
          width: 100%;
          height: 100%;
          display: block;
        }

        .link-card h4 {
          margin: 0;
          color: #214b8d;
          font-size: 22px;
          font-weight: 700;
        }

        @media (max-width: 1200px) {
          .hero-row {
            grid-template-columns: 1fr;
          }
        }

        @media (max-width: 992px) {
          .status-grid,
          .link-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
          }

          .hero-content h1 {
            font-size: 44px;
          }
        }

        @media (max-width: 680px) {
          .proto-container {
            padding: 0 18px;
          }

          .status-grid,
          .link-grid {
            grid-template-columns: 1fr;
          }

          .hero-content h1 {
            font-size: 34px;
          }

          .status-badge {
            width: 160px;
            height: 160px;
          }
        }
      </style>

      <section class="hero-area">
        <div class="proto-container">
          <div class="hero-row">
            <div class="hero-content">
              <h1>Overview</h1>
              <p><?= htmlspecialchars($overviewCopy, ENT_QUOTES, 'UTF-8') ?></p>
              <a class="learn-more" href="<?= htmlspecialchars(site_url_with_state('/TE-/reference/frontend-study/te-home-prototype/about.php', $siteLang, $siteRenderer), ENT_QUOTES, 'UTF-8') ?>">Learn More...</a>
            </div>
            <div class="hero-figure">
              <div class="hero-figure-frame">
              <div class="figure-canvas">
                <div class="tree-frame">
                  <iframe src="<?= htmlspecialchars($treeEmbedUrl, ENT_QUOTES, 'UTF-8') ?>" title="TE classification tree" loading="lazy"></iframe>
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
          <div class="status-grid">
            <?php foreach ($datasetCounts as $key => $count): ?>
              <div class="status-item" data-status-item="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                <button class="status-trigger" type="button" data-status-trigger="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" aria-expanded="false">
                  <div class="status-badge">
                    <div class="status-count"><?= number_format($count) ?></div>
                    <div class="status-name"><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?></div>
                  </div>
                </button>
                <div class="status-panel">
                  <div class="status-panel-inner">
                    <h4><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?> case studies</h4>
                    <div class="status-panel-list">
                      <?php foreach ($caseStudies[$key] as $item): ?>
                        <a class="status-panel-link" href="javascript:void(0)"><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></a>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
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
                  <?php if ($item['icon'] === 'preview'): ?>
                    <svg viewBox="0 0 64 64" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" d="M10 14h44v36H10z"/><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" d="M18 24h28M18 32h18M18 40h22"/><circle cx="47" cy="37" r="8" fill="none" stroke="currentColor" stroke-width="3.2"/><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" d="m53 43 5 5"/></svg>
                  <?php elseif ($item['icon'] === 'search'): ?>
                    <svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="28" cy="28" r="14" fill="none" stroke="currentColor" stroke-width="3.4"/><path fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" d="m39 39 13 13"/><path fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" d="M22 28h12M28 22v12"/></svg>
                  <?php elseif ($item['icon'] === 'download'): ?>
                    <svg viewBox="0 0 64 64" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linejoin="round" d="M16 50h32a6 6 0 0 0 6-6V20l-10-10H16a6 6 0 0 0-6 6v28a6 6 0 0 0 6 6Z"/><path fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round" d="M32 24v16"/><path fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round" d="m25 34 7 7 7-7"/></svg>
                  <?php else: ?>
                    <svg viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="20" r="10" fill="none" stroke="currentColor" stroke-width="3.2"/><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" d="M18 50c2-9 8-14 14-14s12 5 14 14"/><path fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" d="M16 18h-6M54 18h-6M32 6V0"/></svg>
                  <?php endif; ?>
                </div>
                <h4><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></h4>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <script>
        (() => {
          const header = document.getElementById('protoHeader');
          const triggers = Array.from(document.querySelectorAll('[data-status-trigger]'));
          const items = Array.from(document.querySelectorAll('[data-status-item]'));

          function syncHeader() {
            if (window.scrollY > 12) {
              header.classList.add('is-scrolled');
            } else {
              header.classList.remove('is-scrolled');
            }
          }

          function toggleItem(name) {
            items.forEach((item) => {
              const isTarget = item.dataset.statusItem === name;
              const shouldOpen = isTarget && !item.classList.contains('is-open');
              item.classList.toggle('is-open', shouldOpen);
            });
            triggers.forEach((trigger) => {
              const expanded = trigger.dataset.statusTrigger === name && !trigger.closest('[data-status-item]').classList.contains('is-open') ? 'true' : 'false';
              trigger.setAttribute('aria-expanded', expanded);
            });
          }

          triggers.forEach((trigger) => {
            trigger.addEventListener('click', () => {
              const item = trigger.closest('[data-status-item]');
              const wasOpen = item.classList.contains('is-open');
              items.forEach((entry) => entry.classList.remove('is-open'));
              triggers.forEach((entry) => entry.setAttribute('aria-expanded', 'false'));
              if (!wasOpen) {
                item.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
              }
            });
          });

          window.addEventListener('scroll', syncHeader, { passive: true });
          syncHeader();
        })();
      </script>
    </main>
  </div>
</body>
</html>
