const { chromium } = require('playwright');

function closeEnough(a, b, tolerance = 1) {
  return Math.abs(a - b) <= tolerance;
}

function rectStable(before, after, label) {
  if (!before || !after) {
    throw new Error(`${label} missing rect`);
  }
  for (const key of ['left', 'top', 'width', 'height']) {
    if (!closeEnough(before[key], after[key])) {
      throw new Error(`${label} ${key} moved from ${before[key]} to ${after[key]}`);
    }
  }
}

async function sideState(page, rootSel, drawerSel, fabSel, headSel) {
  return page.evaluate(({ rootSel, drawerSel, fabSel, headSel }) => {
    const root = document.querySelector(rootSel);
    const drawer = document.querySelector(drawerSel);
    const fab = document.querySelector(fabSel);
    const head = document.querySelector(headSel);
    const ds = drawer ? getComputedStyle(drawer) : null;
    const fs = fab ? getComputedStyle(fab) : null;
    const hs = head ? getComputedStyle(head) : null;
    return {
      rootClass: root ? root.className : '',
      stageRect: document.querySelector('#previewStage')?.getBoundingClientRect().toJSON() || null,
      graphRect: document.querySelector('.preview-g6-surface-stack')?.getBoundingClientRect().toJSON() || null,
      drawerRect: drawer ? drawer.getBoundingClientRect().toJSON() : null,
      drawerOpacity: ds ? ds.opacity : '',
      drawerPosition: ds ? ds.position : '',
      fabRect: fab ? fab.getBoundingClientRect().toJSON() : null,
      fabOpacity: fs ? fs.opacity : '',
      fabPointerEvents: fs ? fs.pointerEvents : '',
      fabPosition: fs ? fs.position : '',
      headBackgroundColor: hs ? hs.backgroundColor : '',
      headBackgroundImage: hs ? hs.backgroundImage : '',
    };
  }, { rootSel, drawerSel, fabSel, headSel });
}

async function verifyClickDoesNotMove(page, selectors, label) {
  const before = await sideState(page, selectors.root, selectors.drawer, selectors.fab, selectors.head);
  await page.click(selectors.fab);
  await page.waitForTimeout(40);
  const during = await sideState(page, selectors.root, selectors.drawer, selectors.fab, selectors.head);
  await page.waitForTimeout(260);
  const after = await sideState(page, selectors.root, selectors.drawer, selectors.fab, selectors.head);

  rectStable(before.fabRect, during.fabRect, `${label} FAB during close`);
  rectStable(before.fabRect, after.fabRect, `${label} FAB after close`);
  rectStable(before.drawerRect, during.drawerRect, `${label} drawer during close`);
  rectStable(before.drawerRect, after.drawerRect, `${label} drawer after close`);

  if (after.rootClass.includes('is-open')) {
    throw new Error(`${label} did not close from FAB click`);
  }
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 980 }, deviceScaleFactor: 1 });

  await page.goto('http://127.0.0.1/TE-/browse.php', { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.evaluate(() => window.localStorage.clear());
  await page.reload({ waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForSelector('#sideDeepThinkToggle', { timeout: 15000 });
  await page.click('#sideDeepThinkToggle');
  await page.waitForTimeout(300);
  const browseOpen = await sideState(page, '#sideDeepThink', '#sideDeepThinkDrawer', '#sideDeepThinkToggle', '.side-dt-head');
  const expectedBrowseHeight = page.viewportSize().height - 48;
  if (!closeEnough(browseOpen.drawerRect.height, expectedBrowseHeight)) {
    throw new Error(`browse drawer should default to near full viewport height ${expectedBrowseHeight}, got ${browseOpen.drawerRect.height}`);
  }
  if (browseOpen.fabOpacity !== '1' || browseOpen.fabPointerEvents !== 'auto') {
    throw new Error('browse FAB must remain visible and clickable while open');
  }
  await verifyClickDoesNotMove(page, {
    root: '#sideDeepThink',
    drawer: '#sideDeepThinkDrawer',
    fab: '#sideDeepThinkToggle',
    head: '.side-dt-head',
  }, 'browse');

  await page.goto('http://127.0.0.1/TE-/preview.php?q=L1HS', { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForSelector('#qaOverlay', { timeout: 15000 });
  await page.waitForTimeout(700);
  const previewOpen = await sideState(page, '#qaOverlay', '#qaDrawer', '#qaFab', '.preview-deepthink-head');
  const expectedPreviewHeight = previewOpen.graphRect.height;
  if (!closeEnough(previewOpen.drawerRect.height, expectedPreviewHeight)) {
    throw new Error(`preview drawer should match graph surface height ${expectedPreviewHeight}, got ${previewOpen.drawerRect.height}`);
  }
  if (!closeEnough(previewOpen.drawerRect.top, previewOpen.graphRect.top)) {
    throw new Error(`preview drawer should start at graph surface top ${previewOpen.graphRect.top}, got ${previewOpen.drawerRect.top}`);
  }
  if (previewOpen.headBackgroundColor !== 'rgba(0, 0, 0, 0)' || previewOpen.headBackgroundImage !== 'none') {
    throw new Error(`preview header must not cover the drawer gradient; got ${previewOpen.headBackgroundColor} / ${previewOpen.headBackgroundImage}`);
  }
  await verifyClickDoesNotMove(page, {
    root: '#qaOverlay',
    drawer: '#qaDrawer',
    fab: '#qaFab',
    head: '.preview-deepthink-head',
  }, 'preview');

  const bridge = await page.evaluate(() => ({
    shell: Boolean(window.__TEKG_PREVIEW_SHELL),
    graphGoBack: Boolean(window.__TEKG_G6_BRIDGE && typeof window.__TEKG_G6_BRIDGE.goBack === 'function'),
    graphApplyAnswer: Boolean(window.__TEKG_G6_BRIDGE && typeof window.__TEKG_G6_BRIDGE.applyAnswerGraph === 'function'),
  }));
  if (!bridge.shell || !bridge.graphGoBack || !bridge.graphApplyAnswer) {
    throw new Error('preview graph bridge functions must remain available');
  }

  await browser.close();
  console.log('Side Deep Think browser contract checks passed.');
})().catch(async (error) => {
  console.error(error && error.stack ? error.stack : error);
  process.exit(1);
});
