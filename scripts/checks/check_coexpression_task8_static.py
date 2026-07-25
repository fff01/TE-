from __future__ import annotations

from harness_lib import ROOT, ok, require, run_check


def read(relative_path: str) -> str:
    return (ROOT / relative_path).read_text(encoding="utf-8")


def read_plan() -> str:
    completed = ROOT / "docs/exec-plans/completed/coexpression-graph-dual-mode.md"
    path = completed if completed.exists() else ROOT / "docs/exec-plans/active/coexpression-graph-dual-mode.md"
    return path.read_text(encoding="utf-8")


def main() -> None:
    plan = read_plan()
    knowledge = read("templates/preview/knowledge_graph_workspace.php")
    coexpression = read("templates/preview/coexpression_workspace.php")
    mode = read("assets/js/pages/preview/coexpression-mode.js")
    embed = read("assets/js/renderers/g6/coexpression/coexpression-embed.js")
    adapter = read("assets/js/renderers/g6/coexpression/coexpression-dynamic-adapter.js")
    renderer = read("assets/js/renderers/g6/coexpression/coexpression-renderer.js")
    bootstrap = read("assets/js/renderers/g6/index-g6.bootstrap.js")

    require(
        'id="coexpression-expression-layer"' in coexpression
        and "Expression activity: On" in coexpression,
        "Co-expression is missing its Expression activity toolbar control.",
    )
    require(
        'id="graph-expression-layer"' not in knowledge
        and "graph-expression-layer" not in bootstrap
        and "graph_expression.php" not in bootstrap,
        "Knowledge Graph still exposes or requests the retired Expression overlay.",
    )
    require(
        'id="coexpression-legend"' in coexpression
        and 'id="coexpression-legend-apply"' in coexpression
        and 'id="coexpression-edge-scope"' in coexpression
        and 'id="coexpression-correlation-notice"' in coexpression,
        "Co-expression legend, Apply workflow, edge scope, or scientific notice is missing.",
    )
    require(
        "fetchExpressionSummaries" in mode
        and "expressionSummaryCache" in mode
        and "setExpressionEnabled" in mode
        and "setViewOptions" in mode,
        "Co-expression mode does not own Expression loading and view controls.",
    )
    require(
        "setExpressionOverlay" in embed and "setViewOptions" in embed,
        "The Co-expression iframe bridge is missing Expression or filter updates.",
    )
    require(
        "coexpressionModule" in adapter
        and "correlationToCenter" in adapter
        and "expressionActivity" in renderer
        and "renderCoexpressionNodeInspectCard" in renderer
        and "renderCoexpressionEdgeInspectCard" in renderer,
        "The adapter/renderer lacks Co-expression-specific detail and activity contracts.",
    )
    require(
        "Expression values provide activity context" in mode
        and "Correlation does not imply causation" in adapter,
        "The Expression/correlation scientific boundary is missing.",
    )
    runtime_copy = "\n".join((coexpression, mode, embed, adapter, renderer)).lower()
    for prohibited in (" regulates ", " activates ", " causes "):
        require(
            prohibited not in runtime_copy,
            f"Runtime Co-expression copy contains prohibited causal wording: {prohibited.strip()}",
        )
    for name in ("L1HS", "LTR5", "MER11B", "HERVH-int", "CR1"):
        require(name in plan, f"The representative acceptance plan is missing {name}.")

    ok("Task 8 static Expression, details, legend, and representative-case contracts passed")


if __name__ == "__main__":
    run_check(main)
