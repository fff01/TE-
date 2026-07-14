# Intelligent QA Testing And Evaluation

This document lists the checks most useful for Agent and DeepThink maintenance.

## Static PHP Checks

```powershell
php -l api/deep_think_stream.php
php -l api/agent_runs.php
php -l api/agent_run_status.php
php -l api/agent_run_execute.php
php -l api/agent/orchestrator/DeepThinkService.php
php -l api/agent/orchestrator/AcademicAgentService.php
```

## Runtime Contract Tests

```powershell
php test/agent_model_config_test.php
php test/agent_timeout_config_test.php
php test/agent_six_stage_runtime_test.php
php test/agent_six_stage_llm_contract_test.php
php test/agent_executing_review_resilience_test.php
php test/agent_simple_preflight_gate_test.php
php test/deepthink_four_stage_runtime_test.php
php test/deepthink_four_stage_contract_test.php
php test/deepthink_relationship_synthesis_test.php
php test/deepthink_sequence_local_answer_test.php
php test/plugin_result_envelope_test.php
```

## Frontend Contract Checks

```powershell
node scripts/checks/check_agent_llm_event_frontend_contract.js
node scripts/checks/check_agent_workflow_default_state_guard.js
node scripts/checks/check_deepthink_frontend_state_contract.js
```

## Repository-Level Checks

```powershell
python scripts/checks/check_docs_freshness.py
python scripts/checks/check_api_contracts.py
python scripts/checks/check_neo4j_tekg3.py
```

`check_api_contracts.py` and `check_neo4j_tekg3.py` require the local services
to be running. Do not interpret their failures as Agent bugs until WAMP and
Neo4j state is confirmed.

## Live Evaluation

Live Agent/DeepThink evaluation records live under `docs/eval/runs/`. Use a new
run directory for each live run.

Example:

```powershell
python scripts/eval/run_dt_agent_live_eval.py --base-url http://127.0.0.1/TE- --cases docs/eval/dt_agent_golden_cases.jsonl --out-dir docs/eval/runs/<run-name> --timeout 2400 --poll-interval 2
```

Live evaluation needs:

- WAMP/PHP available at the chosen base URL.
- Neo4j running with the expected `tekg3` database.
- MySQL available when expression evidence is tested.
- LLM relay or DashScope configuration available.

## Interpreting Results

- A green static check proves syntax or contract markers only; it does not prove
  scientific answer quality.
- A successful plugin call proves retrieval succeeded, not that the final claim
  is biologically strong.
- Agent answer quality should be judged against evidence support, citation
  relevance, unsupported-claim avoidance, and language consistency.
- DeepThink should be judged for concise evidence-grounded answers and clear
  failure exposure.
