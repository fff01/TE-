# Phase 5C Semantic Evaluation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `subagent-driven-development` or `executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a semantic evaluation layer for Deep Think vs Agent that measures answer quality beyond deterministic artifact presence.

**Architecture:** Keep the existing Phase 5A/5B live runner as the transport and evidence capture layer. Add semantic scoring as a separate post-processing layer that can evaluate saved run artifacts without rerunning live APIs, then optionally attach semantic summaries to future live runs.

**Tech Stack:** Python eval scripts, JSONL run artifacts, PHP `ModeComparisonEvaluation` contract as deterministic baseline, TE-KG local WAMP endpoints for later live replay only.

---

## Background

Phase 5B showed that Deep Think is currently faster and more reliable across the seed set. Agent often produces longer research-style answers on complex prompts, but the current deterministic proxy mainly scores artifacts such as evidence package, evidence walk, report plan, citations, and answer presence.

Phase 5B.1 fixed boundary failures and canary regressions:

- `P5A_B_005`, `P5A_B_029`, and `P5A_B_001` now pass live canary checks.
- Compact Agent paths no longer fail on string confidence labels.
- Site Navigator route URLs tolerate benign Markdown punctuation and route fragment/base URL variants.
- The Python live scorer no longer counts compact lightweight Agent responses as overkill merely because more than two plugins ran.

Phase 5C should not change product routing first. It should measure whether Agent is semantically better on research tasks.

## Non-Goals

- Do not change Deep Think or Agent runtime routing in this plan.
- Do not change Neo4j, graph runtime, taxonomy, or expression runtime.
- Do not claim Agent superiority from artifact count alone.
- Do not require live LLM/API reruns for the first semantic scoring implementation.
- Do not replace the deterministic Phase 5A/5B scorer; semantic scoring should supplement it.

## Target Questions

- Are Agent claims supported by retrieved evidence?
- Are citations or route references relevant to the claims they support?
- Does Agent separate supported conclusions from hypotheses or missing evidence?
- Does Agent provide useful research synthesis on expected-Agent cases beyond Deep Think?
- Does Agent avoid unnecessary report-like output on expected-Deep Think cases?

## File Scope

Likely files:

- Modify: `scripts/eval/run_dt_agent_live_eval.py`
- Create: `scripts/eval/semantic_eval.py`
- Create: `test/dt_agent_semantic_eval_test.py`
- Modify or create docs under `docs/eval/`
- Update after completion: `docs/RELIABILITY.md`

Read-only context:

- `docs/eval/dt_agent_golden_cases.jsonl`
- `docs/eval/runs/phase5b_flash_full/case_results.jsonl`
- `docs/eval/runs/phase5b_flash_full/analysis.md`
- `api/agent/contracts/ModeComparisonEvaluation.php`
- `test/dt_agent_live_eval_score_test.py`

## Task 1: Define Semantic Score Schema

- [ ] Create `scripts/eval/semantic_eval.py`.
- [ ] Define a pure-Python function `score_semantic_proxy(case, dt_result, agent_result)` that returns:

```python
{
    "schema_version": "dt_agent_semantic_proxy.v1",
    "case_id": "P5A_B_011",
    "claim_support_score": 0.0,
    "citation_relevance_score": 0.0,
    "missing_evidence_score": 0.0,
    "research_usefulness_score": 0.0,
    "semantic_winner": "tie",
    "semantic_notes": []
}
```

- [ ] Keep v1 deterministic and transparent. Use saved answer text, citation counts, evidence artifacts, and explicit missing-evidence wording as proxy signals.
- [ ] Do not call external LLMs in Task 1.

Expected verification:

```powershell
python -m py_compile scripts\eval\semantic_eval.py
```

## Task 2: Add Fixture Tests

- [ ] Create `test/dt_agent_semantic_eval_test.py`.
- [ ] Add one fixture where Agent has evidence walk, citations, and explicit limitations, and should beat Deep Think on `expected_best_mode=agent`.
- [ ] Add one fixture where Agent has a long answer but no citation/evidence support, and should not win.
- [ ] Add one fixture where Deep Think expected case has compact Agent output, and semantic winner should be `deep_think` or `tie`.

Expected verification:

```powershell
python test\dt_agent_semantic_eval_test.py
```

## Task 3: Add Saved-Run Post-Processor

- [ ] Extend `scripts/eval/semantic_eval.py` with a CLI that reads an existing eval run directory.
- [ ] Input: `--run-dir docs\eval\runs\phase5b_flash_full`.
- [ ] Output:
  - `semantic_case_results.jsonl`
  - `semantic_summary.json`
  - `semantic_summary.md`
- [ ] Treat missing raw artifacts as explicit limitations in the summary.

Expected verification:

```powershell
python scripts\eval\semantic_eval.py --run-dir docs\eval\runs\phase5b_flash_full
```

## Task 4: Integrate Optional Semantic Scoring Into Live Runner

- [ ] Add an optional flag to `scripts/eval/run_dt_agent_live_eval.py`:

```powershell
--semantic-proxy
```

- [ ] When enabled, append semantic proxy fields into each case evaluation.
- [ ] Default behavior must remain unchanged when the flag is absent.
- [ ] Keep deterministic scorer output compatible with existing Phase 5B analysis.

Expected verification:

```powershell
python -m py_compile scripts\eval\run_dt_agent_live_eval.py scripts\eval\semantic_eval.py
python test\dt_agent_live_eval_score_test.py
python test\dt_agent_semantic_eval_test.py
```

## Task 5: Analyze Phase 5B Saved Run

- [ ] Run semantic post-processing on `docs/eval/runs/phase5b_flash_full`.
- [ ] Write a concise analysis file:

```text
docs/eval/runs/phase5b_flash_full/semantic_analysis.md
```

- [ ] Include:
  - Aggregate semantic winner counts.
  - Expected-Agent cases where Agent provides clear semantic value.
  - Expected-Agent cases where Agent artifact structure exists but semantic value is weak.
  - Expected-Deep Think cases where Agent remains unnecessary.
  - Known limitations of deterministic semantic proxy.

## Stop Conditions

Stop and report before implementation if:

- Existing run artifacts do not contain enough answer/evidence text to score saved runs.
- The scorer would need external LLM calls to produce meaningful v1 output.
- The live runner extraction layer drops required Agent artifacts from saved events.

## Final Verification Target

Before marking Phase 5C complete, run:

```powershell
python -m py_compile scripts\eval\run_dt_agent_live_eval.py scripts\eval\semantic_eval.py test\dt_agent_semantic_eval_test.py
python test\dt_agent_live_eval_score_test.py
python test\dt_agent_semantic_eval_test.py
python scripts\eval\semantic_eval.py --run-dir docs\eval\runs\phase5b_flash_full
```

Optional live verification should be a separate user-approved step because it may call local WAMP, Neo4j, and external LLM services.
