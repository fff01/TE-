<?php
declare(strict_types=1);

require_once __DIR__ . '/site_i18n.php';

$pageTitle = 'TE-KG Academic Agent';
$activePage = 'agent';
$protoCurrentPath = '/TE-/agent.php';
$protoSubtitle = 'Traceable academic research assistant';

$ui = [
    'page_title' => 'Academic Agent',
    'start_title' => 'Start chatting with the academic agent',
    'message_label' => 'Message',
    'placeholder_agent' => 'Ask about TEs, disease mechanisms, papers, expression, or genomic loci...',
    'plugin_details' => 'Plugin Details',
    'no_tool_selected' => 'No tool selected',
    'inspector_hint' => 'Click a tool event inside the thinking trace to inspect query details, evidence, citations, and returned data.',
    'thinking_title' => 'Deep thinking',
    'thinking_running' => 'Running...',
    'thinking_done' => 'Done',
    'send_label' => 'Send message',
    'inspector_summary' => 'Summary',
    'inspector_evidence' => 'Evidence',
    'inspector_citations' => 'Citations',
    'inspector_data' => 'Returned Data',
    'inspector_errors' => 'Errors',
    'tool_status' => 'Status',
    'tool_latency' => 'Latency',
    'tool_query' => 'Query',
    'tool_empty_citations' => 'No citations were returned for this tool call.',
    'tool_empty_evidence' => 'No evidence items were returned for this tool call.',
    'tool_empty_data' => 'No result payload was returned.',
    'tool_empty_errors' => 'No plugin errors were reported.',
    'tool_open_hint' => 'Click to inspect details',
    'graph_button' => 'Knowledge Graph',
    'graph_popup_title' => 'Knowledge Graph View',
    'graph_popup_empty' => 'No graph subgraph is available for this tool call.',
];

$local = [];
if (is_file(__DIR__ . '/api/config.local.php')) {
    $loaded = require __DIR__ . '/api/config.local.php';
    if (is_array($loaded)) {
        $local = $loaded;
    }
}

$defaultAgentModel = trim((string)($local['deepseek_model'] ?? 'deepseek-chat'));

require __DIR__ . '/head.php';
?>
<link rel="stylesheet" href="/TE-/assets/css/pages/agent.css">
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://unpkg.com/@antv/g6@5/dist/g6.min.js"></script>

<section class="agent-app is-pristine" id="agentApp" data-mode="agent">
  <div class="agent-chat-shell">
    <div class="agent-chat-scroll" id="agentChatScroll">
      <div class="agent-conversation" id="agentConversation">
        <section class="agent-empty-state" id="agentEmptyState">
          <h1 class="agent-empty-title"><?= htmlspecialchars($ui['start_title'], ENT_QUOTES, 'UTF-8') ?></h1>
        </section>
      </div>
    </div>

    <form id="agentForm" class="agent-composer" autocomplete="off">
      <label class="agent-composer-label" for="agentQuestion"><?= htmlspecialchars($ui['message_label'], ENT_QUOTES, 'UTF-8') ?></label>
      <textarea
        id="agentQuestion"
        name="question"
        rows="1"
        class="agent-composer-input"
        placeholder="<?= htmlspecialchars($ui['placeholder_agent'], ENT_QUOTES, 'UTF-8') ?>"
      ></textarea>
      <div class="agent-composer-footer">
        <div class="agent-composer-hint" id="agentComposerHint"></div>
        <div class="agent-composer-actions">
          <span id="agentStatus" class="agent-status" aria-live="polite"></span>
          <button id="agentSubmit" class="agent-submit" type="submit" aria-label="<?= htmlspecialchars($ui['send_label'], ENT_QUOTES, 'UTF-8') ?>">
            <span aria-hidden="true">&#8593;</span>
          </button>
        </div>
      </div>
    </form>
  </div>

  <aside class="agent-inspector" id="agentInspector" aria-hidden="true">
    <div class="agent-inspector-head">
      <div class="agent-inspector-headcopy">
        <p class="agent-inspector-eyebrow"><?= htmlspecialchars($ui['plugin_details'], ENT_QUOTES, 'UTF-8') ?></p>
        <h3 id="agentInspectorTitle"><?= htmlspecialchars($ui['no_tool_selected'], ENT_QUOTES, 'UTF-8') ?></h3>
      </div>
      <button type="button" class="agent-inspector-close" id="agentInspectorClose" aria-label="Close details">&times;</button>
    </div>
    <div class="agent-inspector-body" id="agentInspectorBody">
      <div class="agent-inspector-placeholder">
        <?= htmlspecialchars($ui['inspector_hint'], ENT_QUOTES, 'UTF-8') ?>
      </div>
    </div>
  </aside>

  <section class="agent-graph-popup" id="agentGraphPopup" aria-hidden="true">
    <div class="agent-graph-popup-head" id="agentGraphPopupHandle">
      <div class="agent-graph-popup-headcopy">
        <p class="agent-graph-popup-eyebrow"><?= htmlspecialchars($ui['graph_button'], ENT_QUOTES, 'UTF-8') ?></p>
        <h3 id="agentGraphPopupTitle"><?= htmlspecialchars($ui['graph_popup_title'], ENT_QUOTES, 'UTF-8') ?></h3>
      </div>
      <button type="button" class="agent-graph-popup-close" id="agentGraphPopupClose" aria-label="Close graph view">&times;</button>
    </div>
    <div class="agent-graph-popup-body">
      <div class="agent-graph-popup-empty" id="agentGraphPopupEmpty"><?= htmlspecialchars($ui['graph_popup_empty'], ENT_QUOTES, 'UTF-8') ?></div>
      <div class="agent-graph-popup-canvas" id="agentGraphPopupCanvas"></div>
    </div>
  </section>
</section>

<script id="agent-page-config" type="application/json"><?= json_encode([
    'apiUrl' => '/TE-/api/agent.php',
    'streamApiUrl' => '/TE-/api/agent_stream.php',
    'defaultModel' => $defaultAgentModel,
    'defaultMode' => 'agent',
    'ui' => $ui,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script src="/TE-/assets/js/pages/agent.js"></script>
<?php require __DIR__ . '/foot.php'; ?>
