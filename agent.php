<?php
declare(strict_types=1);

require_once __DIR__ . '/site_i18n.php';

$pageTitle = 'TE-KG Academic Agent';
$activePage = 'agent';
$protoCurrentPath = '/TE-/agent.php';
$protoSubtitle = 'Traceable academic research assistant';

$ui = [
    'page_title' => 'Academic Agent',
    'start_title' => 'Choose a mode and start chatting',
    'quick_mode' => 'Quick QA',
    'agent_mode' => 'Agent',
    'message_label' => 'Message',
    'placeholder_agent' => 'Ask about TEs, disease mechanisms, papers, expression, or genomic loci...',
    'placeholder_quick' => 'Quick QA is coming soon. Switch back to Agent mode to use the academic workflow.',
    'quick_mode_notice' => 'Quick QA is not available yet. Please switch back to Agent mode.',
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

<section class="agent-app is-pristine" id="agentApp" data-mode="agent">
  <div class="agent-chat-shell">
    <div class="agent-chat-scroll" id="agentChatScroll">
      <div class="agent-conversation" id="agentConversation">
        <section class="agent-empty-state" id="agentEmptyState">
          <div class="agent-empty-mark" aria-hidden="true">
            <svg viewBox="0 0 64 64" class="agent-empty-mark-icon">
              <defs>
                <linearGradient id="agentMarkGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                  <stop offset="0%" stop-color="#6d88ff"></stop>
                  <stop offset="100%" stop-color="#4e69ea"></stop>
                </linearGradient>
              </defs>
              <path d="M32 8c10.8 0 19.7 8.4 20.5 19 4.5 1.2 7.7 5.2 7.7 9.9 0 5.7-4.6 10.3-10.3 10.3h-2.1c-2 4.5-6.5 7.7-11.8 7.7-5 0-9.4-2.8-11.6-7a10 10 0 0 1-9.7-9.9c0-5.1 3.8-9.3 8.7-9.8C24.7 17 27.8 8 32 8Z" fill="url(#agentMarkGradient)"/>
              <path d="M24 29.5 32 35l8-5.5M24.8 38.2l-4.6 3.4m23-3.4 4.6 3.4M28 38.2v7.3m8-7.3v7.3" fill="none" stroke="#fff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>
              <circle cx="24.2" cy="28.6" r="2.1" fill="#fff"/>
              <circle cx="39.8" cy="28.6" r="2.1" fill="#fff"/>
            </svg>
          </div>
          <h1 class="agent-empty-title"><?= htmlspecialchars($ui['start_title'], ENT_QUOTES, 'UTF-8') ?></h1>
          <div class="agent-mode-switch" id="agentModeSwitch" role="tablist" aria-label="<?= htmlspecialchars($ui['page_title'], ENT_QUOTES, 'UTF-8') ?>">
            <button type="button" class="agent-mode-button" data-mode="quick"><?= htmlspecialchars($ui['quick_mode'], ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" class="agent-mode-button is-active" data-mode="agent"><?= htmlspecialchars($ui['agent_mode'], ENT_QUOTES, 'UTF-8') ?></button>
          </div>
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
