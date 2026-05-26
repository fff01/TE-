#!/usr/bin/env python3
"""Regression tests for the DT vs Agent live eval local scorer."""

from __future__ import annotations

import importlib.util
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
SCORER_PATH = ROOT / "scripts" / "eval" / "run_dt_agent_live_eval.py"


def load_scorer():
    spec = importlib.util.spec_from_file_location("run_dt_agent_live_eval", SCORER_PATH)
    if spec is None or spec.loader is None:
        raise AssertionError(f"Could not load scorer module: {SCORER_PATH}")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def assert_equal(actual, expected, message: str) -> None:
    if actual != expected:
        raise AssertionError(f"{message}: expected {expected!r}, got {actual!r}")


def test_compact_agent_artifact_is_not_overkill() -> None:
    scorer = load_scorer()
    evaluation = scorer.score_locally(
        {"case_id": "P5A_B_001", "expected_best_mode": "deep_think"},
        {"ok": True, "answer": "compact DT answer", "used_plugins": [], "citations": [], "timings": {"total_ms": 10}},
        {
            "ok": True,
            "answer": "compact Agent answer",
            "used_plugins": ["task_complexity", "site_navigator", "reference_materials"],
            "citations": [],
            "timings": {"total_ms": 20},
            "errors": [],
        },
    )

    assert_equal(evaluation["agent_artifact_score"], 0.1, "compact preflight artifact score")
    assert_equal(evaluation["agent_overkill"], False, "compact preflight should not be overkill")


if __name__ == "__main__":
    test_compact_agent_artifact_is_not_overkill()
    print("DT Agent live eval scorer tests passed.")
