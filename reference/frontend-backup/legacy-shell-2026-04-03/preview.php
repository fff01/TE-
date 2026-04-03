<?php
require_once __DIR__ . '/site_i18n.php';
$lang = site_lang();
$renderer = site_renderer();
$pageTitle = site_t(['zh' => '预览 - TEKG', 'en' => 'Preview - TEKG'], $lang);
$activePage = 'preview';
$previewSrc = $renderer === 'g6'
    ? 'index_g6.html?embed=preview&renderer=g6&lang=' . rawurlencode($lang)
    : 'index_demo.html?lang=' . rawurlencode($lang);
include __DIR__ . '/head.php';
?>
<style>
  .page-shell.preview-shell {
    max-width: none;
    padding: 8px 12px 12px;
  }
  .preview-stage {
    min-height: calc(100vh - 116px);
  }
  .preview-frame {
    width: 100%;
    min-height: calc(100vh - 132px);
    border: none;
    border-radius: 18px;
    background: #fff;
    box-shadow: var(--shadow);
  }
  @media (max-width: 860px) {
    .page-shell.preview-shell {
      padding: 6px 8px 10px;
    }
    .preview-frame {
      min-height: calc(100vh - 120px);
      border-radius: 14px;
    }
  }
</style>
</main>
<main class="page-shell preview-shell">
  <section class="preview-stage">
    <iframe
      id="preview-frame"
      class="preview-frame"
      src="<?= htmlspecialchars($previewSrc, ENT_QUOTES, 'UTF-8') ?>"
      title="<?= htmlspecialchars(site_t(['zh' => '知识图谱预览', 'en' => 'Knowledge graph preview'], $lang), ENT_QUOTES, 'UTF-8') ?>"
    ></iframe>
  </section>
</main>
<script>
(function () {
  const lang = <?= json_encode($lang, JSON_UNESCAPED_UNICODE) ?>;
  const renderer = <?= json_encode($renderer, JSON_UNESCAPED_UNICODE) ?>;
  const frame = document.getElementById('preview-frame');
  if (!frame) return;

  function hideInnerChrome(doc) {
    const header = doc.querySelector('header');
    const footer = doc.querySelector('footer');
    const langControl = doc.querySelector('.lang');
    if (header) header.style.display = 'none';
    if (footer) footer.style.display = 'none';
    if (langControl) langControl.style.display = 'none';
    doc.body.style.margin = '0';
    doc.body.style.minHeight = '100vh';
    doc.body.style.background = 'linear-gradient(180deg,#eef4fb,#f9fbfe)';
    const main = doc.querySelector('.main');
    if (main) {
      main.style.padding = '10px';
      main.style.minHeight = '100vh';
      main.style.height = '100vh';
      main.style.gap = '10px';
    }
    doc.documentElement.style.height = '100%';
    doc.body.style.height = '100%';
  }

  function stretchPanels(doc) {
    doc.querySelectorAll('.panel').forEach(function (panel) {
      panel.style.height = 'calc(100vh - 20px)';
      panel.style.minHeight = 'calc(100vh - 20px)';
    });
    const cy = doc.getElementById('cy');
    if (cy) {
      cy.style.minHeight = '0';
      cy.style.height = '100%';
      cy.style.flex = '1';
    }
  }

  function switchInnerLanguage(doc) {
    if (lang !== 'en') return;
    const enButton = doc.getElementById('lang-en');
    const zhButton = doc.getElementById('lang-zh');
    if (enButton && zhButton && !enButton.classList.contains('active')) {
      enButton.click();
    }
  }

  function restyleInnerPage() {
    const doc = frame.contentDocument;
    if (!doc) return;
    hideInnerChrome(doc);
    stretchPanels(doc);
    switchInnerLanguage(doc);
    const innerWin = frame.contentWindow;
    const innerCy = innerWin && innerWin.__TEKG_CY ? innerWin.__TEKG_CY : null;
    if (innerCy) {
      try {
        innerCy.resize();
        innerCy.fit(undefined, 55);
      } catch (_err) {}
    }
  }

  frame.addEventListener('load', function () {
    restyleInnerPage();
    setTimeout(restyleInnerPage, 300);
    setTimeout(restyleInnerPage, 900);
  });
}());
</script>
<?php include __DIR__ . '/foot.php'; ?>
