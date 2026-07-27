# TE-KG Intelligent QA Handoff

Last reviewed: 2026-07-14

Transition note: 2026-07-27. No Agent/DeepThink runtime code was changed during
the latest database-page and UI pass. Agent is a primary candidate for the next
conversation, but this subsystem has not been deeply re-audited as part of that
pass. Verify live endpoints, model configuration, prompt assets, plugin catalog,
and the checks listed in `api/README.md` before changing behavior.

This document is the current handoff for the TE-KG intelligent QA subsystem. It
covers DeepThink, Agent, plugin routing, LLM nodes, frontend event handling, and
maintenance risks.

## What This Subsystem Does

The intelligent QA subsystem answers TE-KG questions through two user-facing
modes on `agent.php`:

- DeepThink: a lighter assistant for direct, plugin-grounded answers.
- Agent: a heavier research assistant for multi-step evidence collection,
  integration, and report-style answers.

Both modes use local TE-KG evidence tools. Neither mode should invent graph
facts, PMIDs, URLs, expression results, loci, or sequence details.

## Current Runtime Shape

- DeepThink endpoint: `api/deep_think_stream.php`.
- Agent async run create: `api/agent_runs.php`.
- Agent async run status: `api/agent_run_status.php`.
- Agent execution path: `api/agent_run_execute.php` and worker/kickoff helpers.
- Main backend orchestrators:
  - `api/agent/orchestrator/DeepThinkService.php`
  - `api/agent/orchestrator/AcademicAgentService.php`
  - `api/agent/orchestrator/LlmClient.php`
  - `api/agent/orchestrator/EntityNormalizer.php`
- Main frontend: `assets/js/pages/agent.js`.

The old direct `api/agent_stream.php` still exists, but the current frontend path
uses async run creation and polling for Agent.

## DeepThink

DeepThink is a four-stage assistant:

```text
Understanding -> Planning -> Executing -> Writing
```

Current important behavior:

- The model for DeepThink stages is resolved in the service and currently uses
  `deepseek-v4-flash`.
- Planning selects only validated business plugins.
- Executing may call each planned business plugin at most once.
- Citation Resolver is post-processing and may run outside the business-plugin
  set.
- Literature Reading requires usable Literature Plugin results.
- Writing is the final answer source. If Writing fails, the failure must be
  exposed instead of hidden behind a rule-composed answer.

## Agent

Agent is a six-stage research assistant:

```text
Understanding -> Planning -> Collecting -> Executing -> Integrating -> Writing
```

Current important behavior:

- Agent uses deterministic normalization and planning helpers plus LLM node
  artifacts.
- Collecting decides whether more evidence is needed.
- Executing runs plugins and can review plugin output with an ExecutingReview
  LLM artifact.
- Integrating builds structured evidence materials such as evidence package,
  evidence walk, report plan, answer structure, and claim mapping.
- Writing runs a writing decision, draft generation, optional polishing, and
  integrity checks.

Agent is more expressive than DeepThink but slower and more failure-prone,
especially near Writing.

## Models And Config

Do not rely on old handoff text for model truth. Inspect the live local config
before debugging model behavior:

- `api/config.local.php`
- `api/agent/bootstrap.php`
- `api/agent/orchestrator/DeepThinkService.php`
- `api/agent/orchestrator/AcademicAgentService.php`

At the latest review, local config set Agent core/expert/narrator/writing style
models to `deepseek-v4-pro`, while DeepThink still used
`deepseek-v4-flash`. Timeouts were also much larger than older documents stated.

## Plugin System

Plugins are registered in `api/agent/plugin_registry.php`. The planner-facing
directory is `api/agent/plugins/PLUGIN_CATALOG.md`; maintainer notes are in
`api/agent/plugins/README.md`.

Registered plugins include:

- Entity Resolver
- Site Navigator Plugin
- Graph Plugin
- Graph Analytics Plugin
- Cypher Explorer Plugin
- Literature Plugin
- Literature Reading Plugin
- Tree Plugin
- Expression Plugin
- Genome Plugin
- Sequence Plugin
- Citation Resolver

Plugin results are bounded evidence. The writing layers must not turn retrieval
metadata into stronger claims than the evidence supports.

## Frontend Event Flow

The frontend receives streamed or polled events and renders thinking/status
state. Important event families include:

- `stage_state`
- `node_llm_result`
- `node_llm_error`
- `tool_selected`
- `tool_result`
- `reflection`
- `synthesizing`
- `answer`
- `error`
- `done`

The workflow UI should be driven by backend events, not by fake frontend
progress. Status labels such as `Understanding`, `Planning`, and `Writing`
remain English UI labels; generated thinking content follows the request
language.

## Known Risks

- Agent Writing is the main latency and failure risk because it consumes large
  evidence structures and may run draft, polish, and integrity checks.
- ExecutingReview improves traceability but adds LLM failure points.
- Agent may still over-handle simple lookup or navigation tasks if compact
  preflight is disabled.
- Literature retrieval can produce weak or irrelevant matches.
- Graph relationships describe local associations and relation labels, not
  automatic biological causality.
- Expression, genome, and sequence plugins have strict evidence boundaries that
  must be preserved in final answers.

## Recommended Next Maintenance Tasks

1. Keep `api/README.md` and `api/docs/` current after every Agent/DeepThink
   change.
2. Audit Agent Writing payload size and latency before changing model settings.
3. Expand semantic evaluation beyond artifact-presence checks.
4. Keep plugin catalog wording accurate enough for LLM planning.
5. Treat old `docs/architecture/*agent*` markdown as historical background, not
   runtime truth.
