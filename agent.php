<?php
declare(strict_types=1);

require_once __DIR__ . '/site_i18n.php';
$pageTitle = 'TE-KG Academic Agent';
$activePage = '';
$protoCurrentPath = '/TE-/agent.php';
$protoSubtitle = 'Traceable academic research assistant';
$ui = [
    'page_title' => site_t(['en' => 'Academic Agent', 'zh' => '学术智能体']),
    'start_title' => site_t(['en' => 'Start with Agent mode', 'zh' => '使用智能体模式开始对话']),
    'quick_mode' => site_t(['en' => 'Quick QA', 'zh' => '快速问答']),
    'agent_mode' => site_t(['en' => 'Agent', 'zh' => '智能体']),
    'message_label' => site_t(['en' => 'Message', 'zh' => '消息']),
    'placeholder_agent' => site_t([
        'en' => 'Message the academic agent about TEs, disease evidence, expression, trees, or genomic loci...',
        'zh' => '给智能体发送消息，询问 TE、疾病证据、表达、分类树或基因组位点...',
    ]),
    'placeholder_quick' => site_t([
        'en' => 'Quick QA is coming soon. Switch back to Agent mode to use the academic workflow.',
        'zh' => '快速问答稍后再做。请切回智能体模式使用当前学术工作流。',
    ]),
    'quick_mode_notice' => site_t([
        'en' => 'Quick QA is not available yet. Please switch back to Agent mode.',
        'zh' => '快速问答暂未接入，请切回智能体模式。',
    ]),
    'plugin_details' => site_t(['en' => 'Plugin Details', 'zh' => '插件详情']),
    'no_tool_selected' => site_t(['en' => 'No tool selected', 'zh' => '尚未选择工具']),
    'inspector_hint' => site_t([
        'en' => 'Click a tool event inside the thinking trace to inspect the exact query summary, evidence, citations, and returned data.',
        'zh' => '点击深度思考里的工具事件，可以在这里查看具体查询摘要、证据、引用和返回数据。',
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

<section class="agent-app is-pristine" id="agentApp" data-mode="agent">
  <div class="agent-chat-shell">
    <div class="agent-chat-topbar">
      <div class="agent-thread-head" id="agentThreadHead" hidden>
        <h2 class="agent-thread-title" id="agentThreadTitle"></h2>
        <p class="agent-thread-mode" id="agentThreadMode"><?= htmlspecialchars($ui['agent_mode'], ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </div>

    <div class="agent-chat-scroll" id="agentChatScroll">
      <div class="agent-conversation" id="agentConversation">
        <article class="agent-empty-state" id="agentEmptyState">
          <div class="agent-empty-mark">✦</div>
          <h3><?= htmlspecialchars($ui['start_title'], ENT_QUOTES, 'UTF-8') ?></h3>
          <div class="agent-mode-switch" id="agentModeSwitch" role="tablist" aria-label="<?= htmlspecialchars($ui['page_title'], ENT_QUOTES, 'UTF-8') ?>">
            <button type="button" class="agent-mode-button" data-mode="quick"><?= htmlspecialchars($ui['quick_mode'], ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" class="agent-mode-button is-active" data-mode="agent"><?= htmlspecialchars($ui['agent_mode'], ENT_QUOTES, 'UTF-8') ?></button>
          </div>
        </article>
      </div>
    </div>

    <form id="agentForm" class="agent-composer">
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
          <button id="agentSubmit" class="agent-submit" type="submit" aria-label="Send message">↑</button>
        </div>
      </div>
    </form>
  </div>

  <aside class="agent-inspector" id="agentInspector" aria-hidden="true">
    <div class="agent-inspector-head">
      <div>
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
    'defaultModel' => $defaultAgentModel,
    'ui' => $ui,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script src="/TE-/assets/js/pages/agent.js"></script>
<?php require __DIR__ . '/foot.php'; ?>
