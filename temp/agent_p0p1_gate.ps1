$ErrorActionPreference = "Stop"

$commands = @(
    "php -l api\agent\bootstrap\evidence_support.php",
    "php -l api\agent\plugins\EntityResolverPlugin.php",
    "php -l api\agent\plugins\SequencePlugin.php",
    "php -l api\agent\plugins\GenomePlugin.php",
    "php -l api\agent\plugins\ExpressionPlugin.php",
    "php -l api\agent\plugins\TreePlugin.php",
    "php -l api\agent\plugins\SiteNavigatorPlugin.php",
    "php -l api\agent\plugins\GraphPlugin.php",
    "php -l api\agent\plugins\GraphAnalyticsPlugin.php",
    "php -l api\agent\plugins\CypherExplorerPlugin.php",
    "php -l api\agent\plugins\LiteraturePlugin.php",
    "php -l api\agent\plugins\LiteratureReadingPlugin.php",
    "php -l api\agent\plugins\CitationResolverPlugin.php",
    "php -l api\agent\contracts\EvidencePackage.php",
    "php -l api\agent\orchestrator\traits\AcademicAgentEvidenceTrait.php",
    "php -l api\agent\orchestrator\traits\AcademicAgentPluginResultTrait.php",
    "php test\agent_evidence_package_runtime_test.php",
    "php test\report_integrity_gate_test.php",
    "php test\agent_simple_preflight_gate_test.php",
    "php test\plugin_result_envelope_test.php",
    "php test\evidence_package_test.php",
    "php test\agent_plugin_context_accessor_test.php",
    "php test\agent_evidence_walk_runtime_test.php",
    "php test\agent_research_report_prompt_test.php",
    "php test\agent_research_report_planning_test.php",
    "php test\site_navigator_plugin_test.php",
    "php test\task_complexity_test.php",
    "php test\agent_six_stage_runtime_test.php",
    "node --check assets\js\pages\agent.js",
    "node scripts\checks\check_agent_llm_event_frontend_contract.js",
    "node scripts\checks\check_agent_workflow_default_state_guard.js"
)

foreach ($command in $commands) {
    Write-Host "== $command =="
    Invoke-Expression $command
    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
}
