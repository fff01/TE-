<?php
declare(strict_types=1);

require_once __DIR__ . '/site_i18n.php';

$pageTitle = 'TE-KG Academic Agent';
$activePage = '';
$protoCurrentPath = '/TE-/agent.php';
$protoSubtitle = 'Traceable academic research assistant';

$ui = [
    'page_title' => site_t(['en' => 'Academic Agent', 'zh' => '学术智能体']),
    'start_title' => site_t(['en' => 'Choose a mode and start chatting', 'zh' => '选择模式并开始对话']),
    'quick_mode' => site_t(['en' => 'Quick QA', 'zh' => '快速问答']),
    'agent_mode' => site_t(['en' => 'Agent', 'zh' => '智能体']),
    'message_label' => site_t(['en' => 'Message', 'zh' => '消息']),
    'placeholder_agent' => site_t([
        'en' => 'Ask about TEs, disease mechanisms, papers, expression, or genomic loci...',
        'zh' => '询问 TE、疾病机制、文献、表达或基因组位点……',
    ]),
    'placeholder_quick' => site_t([
        'en' => 'Quick QA is coming soon. Switch back to Agent mode to use the academic workflow.',
        'zh' => '快速问答稍后接入，请切回智能体模式使用当前学术工作流。',
    ]),
    'quick_mode_notice' => site_t([
        'en' => 'Quick QA is not available yet. Please switch back to Agent mode.',
        'zh' => '快速问答暂未接入，请切回智能体模式。',
    ]),
    'plugin_details' => site_t(['en' => 'Plugin Details', 'zh' => '工具详情']),
    'no_tool_selected' => site_t(['en' => 'No tool selected', 'zh' => '尚未选择工具']),
    'inspector_hint' => site_t([
        'en' => 'Click a tool event inside the thinking trace to inspect query details, evidence, citations, and returned data.',
        'zh' => '点击思考过程中的工具事件，可在这里查看查询摘要、证据、引用和返回数据。',
    ]),
    'thinking_title' => site_t(['en' => 'Deep thinking', 'zh' => '深度思考']),
    'thinking_running' => site_t(['en' => 'Running...', 'zh' => '进行中…']),
    'thinking_done' => site_t(['en' => 'Done', 'zh' => '已完成']),
    'send_label' => site_t(['en' => 'Send message', 'zh' => '发送消息']),
    'inspector_summary' => site_t(['en' => 'Summary', 'zh' => '摘要']),
    'inspector_evidence' => site_t(['en' => 'Evidence', 'zh' => '证据']),
    'inspector_citations' => site_t(['en' => 'Citations', 'zh' => '引用']),
    'inspector_data' => site_t(['en' => 'Returned Data', 'zh' => '返回数据']),
    'inspector_errors' => site_t(['en' => 'Errors', 'zh' => '错误信息']),
    'tool_status' => site_t(['en' => 'Status', 'zh' => '状态']),
    'tool_latency' => site_t(['en' => 'Latency', 'zh' => '耗时']),
    'tool_query' => site_t(['en' => 'Query', 'zh' => '查询摘要']),
    'tool_empty_citations' => site_t([
        'en' => 'No citations were returned for this tool call.',
        'zh' => '本次工具调用没有返回引用。',
    ]),
    'tool_empty_evidence' => site_t([
        'en' => 'No evidence items were returned for this tool call.',
        'zh' => '本次工具调用没有返回证据条目。',
    ]),
    'tool_empty_data' => site_t([
        'en' => 'No result payload was returned.',
        'zh' => '本次工具调用没有返回结果数据。',
    ]),
    'tool_empty_errors' => site_t([
        'en' => 'No plugin errors were reported.',
        'zh' => '本次工具调用没有报告错误。',
    ]),
    'tool_open_hint' => site_t([
        'en' => 'Click to inspect details',
        'zh' => '点击查看详情',
    ]),
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
          <div class="agent-empty-mark" aria-hidden="true">✦</div>
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
            <span aria-hidden="true">↑</span>
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
      <button type="button" class="agent-inspector-close" id="agentInspectorClose" aria-label="Close details">×</button>
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
