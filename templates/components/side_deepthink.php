<?php
declare(strict_types=1);

$sideDeepThinkConfig = [
    'deepThinkStreamApiUrl' => tekg_app_url('api/deep_think_stream.php'),
    'sessionStorageKey' => 'tekg-side-deepthink-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string)$activePage),
    'sourcePage' => (string)$activePage,
    'defaultModel' => 'deepseek-v4-flash',
    'graphBridge' => false,
    'showBack' => false,
];
?>
<div class="side-dt" id="sideDeepThink" aria-live="polite">
  <button class="side-dt-fab" id="sideDeepThinkToggle" type="button" aria-expanded="false" aria-controls="sideDeepThinkDrawer">
    <span>Deep Think</span>
  </button>
  <aside class="side-dt-drawer" id="sideDeepThinkDrawer" aria-label="Deep Think assistant">
    <header class="side-dt-head">
      <div>
        <h2>Deep Think</h2>
        <p id="sideDeepThinkStatus">Ready</p>
      </div>
      <button class="side-dt-close" id="sideDeepThinkClose" type="button" aria-label="Close Deep Think">Close</button>
    </header>
    <div class="side-dt-messages" id="sideDeepThinkMessages"></div>
    <form class="side-dt-form" id="sideDeepThinkForm">
      <textarea id="sideDeepThinkInput" rows="2" placeholder="Ask where to find TE sequence, genome annotation, expression, graph, or datasets."></textarea>
      <button id="sideDeepThinkSubmit" type="submit">Send</button>
    </form>
  </aside>
  <script type="application/json" id="side-deepthink-config"><?= json_encode($sideDeepThinkConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
</div>
