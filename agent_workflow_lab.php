<?php
declare(strict_types=1);

$activePage = 'agent';
$pageTitle = 'Agent Workflow Lab';
$protoSubtitle = 'Developer workflow designer for the multi-node TE-KG agent';
$pageExtraStylesheets = ['/TE-/assets/css/pages/agent_workflow_lab.css'];
require __DIR__ . '/head.php';
?>
<section class="workflow-lab">
  <div class="workflow-lab__hero">
    <div>
      <h2 class="workflow-lab__title">Agent Workflow Lab</h2>
      <p class="workflow-lab__desc">Developer-only visualization for the proposed node workflow. Edit the JSON spec, drag nodes, and save the workflow design back to the backend spec file.</p>
      <p class="workflow-lab__meta">All node-to-node payloads are treated as JSON only. Entity normalization now lives inside Question Understanding, each expert has its own output variable, and the expert outputs also feed the Answer Structuring node before Answer Writer.</p>
    </div>
    <div class="workflow-lab__actions">
      <button type="button" id="workflowReload" class="workflow-lab__button">Reload Spec</button>
      <button type="button" id="workflowSave" class="workflow-lab__button workflow-lab__button--primary">Save Spec</button>
    </div>
  </div>

  <div class="workflow-lab__grid">
    <section class="workflow-lab__panel workflow-lab__panel--canvas">
      <div class="workflow-lab__panel-head">
        <h3>Visual Flow</h3>
        <p>Click a node to inspect details. Drag nodes to rearrange the draft workflow.</p>
      </div>
      <div class="workflow-lab__canvas-wrap">
        <svg id="workflowEdges" class="workflow-lab__edges" aria-hidden="true"></svg>
        <div id="workflowCanvas" class="workflow-lab__canvas"></div>
      </div>
    </section>

    <aside class="workflow-lab__sidebar">
      <section class="workflow-lab__panel">
        <div class="workflow-lab__panel-head">
          <h3>Node Detail</h3>
          <p>Selected node metadata, JSON inputs, outputs, and neighborhood links.</p>
        </div>
        <div id="workflowDetail" class="workflow-lab__detail">
          <p>Select a node in the canvas.</p>
        </div>
      </section>

      <section class="workflow-lab__panel">
        <div class="workflow-lab__panel-head">
          <h3>Routing Examples</h3>
          <p>Reference routes for relationship, graph analytics, literature, and mechanism questions.</p>
        </div>
        <div id="workflowRoutes" class="workflow-lab__routes"></div>
      </section>

      <section class="workflow-lab__panel">
        <div class="workflow-lab__panel-head">
          <h3>Current Node Contracts</h3>
          <p>Read-only backend JSON contracts exported by the current agent code.</p>
        </div>
        <pre id="workflowContracts" class="workflow-lab__pre"></pre>
      </section>
    </aside>
  </div>

  <section class="workflow-lab__panel workflow-lab__panel--editor">
    <div class="workflow-lab__panel-head">
      <h3>Workflow Spec JSON</h3>
      <p>Edit the JSON directly if you want full control over node metadata, positions, and edges.</p>
    </div>
    <textarea id="workflowEditor" class="workflow-lab__editor" spellcheck="false"></textarea>
    <div class="workflow-lab__editor-actions">
      <button type="button" id="workflowApply" class="workflow-lab__button">Apply From Editor</button>
      <span id="workflowStatus" class="workflow-lab__status">Idle</span>
    </div>
  </section>
</section>

<script src="/TE-/assets/js/pages/agent_workflow_lab.js"></script>
<?php require __DIR__ . '/foot.php'; ?>
