<?php
$pageTitle = '预览 - TEKG';
$activePage = 'preview';
include __DIR__ . '/head.php';
?>
<style>
  .page-shell {
    max-width: none;
    padding: 6px 10px 10px;
  }
  #preview-stage {
    min-height: calc(100vh - 94px);
  }
  #preview-frame {
    width: 100%;
    height: calc(100vh - 106px);
    min-height: calc(100vh - 106px);
    border: none;
    border-radius: 14px;
    background: #fff;
    display: block;
  }
</style>
<section id="preview-stage">
  <iframe
    id="preview-frame"
    src="index_demo.html"
    title="TEKG 图谱预览"
  ></iframe>
</section>
<script>
(function () {
  const frame = document.getElementById('preview-frame');

  function restyleInnerPage() {
    let doc;
    try {
      doc = frame.contentDocument || frame.contentWindow.document;
    } catch (_err) {
      return;
    }
    if (!doc) return;

    const innerHeader = doc.querySelector('body > header');
    if (innerHeader) {
      innerHeader.style.display = 'none';
    }

    const footer = doc.querySelector('body > footer');
    if (footer) {
      footer.style.display = 'none';
    }

    const body = doc.body;
    const html = doc.documentElement;
    if (html) {
      html.style.height = '100%';
      html.style.margin = '0';
    }
    if (body) {
      body.style.height = '100%';
      body.style.minHeight = '100%';
      body.style.padding = '0';
      body.style.margin = '0';
    }

    const main = doc.querySelector('.main');
    if (main) {
      main.style.padding = '8px';
      main.style.gap = '10px';
      main.style.minHeight = 'calc(100vh - 16px)';
      main.style.height = 'calc(100vh - 16px)';
      main.style.alignItems = 'stretch';
    }

    doc.querySelectorAll('.panel').forEach(function (panel) {
      panel.style.height = '100%';
      panel.style.minHeight = '0';
    });

    const cy = doc.getElementById('cy');
    if (cy) {
      cy.style.minHeight = '0';
      cy.style.height = '100%';
      cy.style.flex = '1';
    }

    const detail = doc.getElementById('node-details');
    if (detail) {
      detail.style.minHeight = '92px';
    }

    const graphHead = doc.querySelector('.panel .head');
    const lang = doc.querySelector('.lang');
    if (graphHead && lang && !lang.dataset.previewMoved) {
      lang.dataset.previewMoved = '1';
      lang.style.marginLeft = 'auto';
      lang.style.flexShrink = '0';
      graphHead.appendChild(lang);
    }

    const pageTitle = doc.getElementById('page-title');
    if (pageTitle) {
      pageTitle.textContent = '';
    }

    const pageBadge = doc.getElementById('page-badge');
    if (pageBadge) {
      pageBadge.style.display = 'none';
    }

    const brandIcon = doc.querySelector('body > header i');
    if (brandIcon) {
      brandIcon.style.display = 'none';
    }
  }

  frame.addEventListener('load', function () {
    restyleInnerPage();
    setTimeout(restyleInnerPage, 200);
    setTimeout(restyleInnerPage, 800);
  });
}());
</script>
<?php include __DIR__ . '/foot.php'; ?>
