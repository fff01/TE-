from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]


def read(relative_path: str) -> str:
    return (ROOT / relative_path).read_text(encoding="utf-8")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


def main() -> None:
    preview = read("preview.php")
    iframe = read("assets/html/preview_graph.html")
    bootstrap = read("assets/js/renderers/g6/index-g6.bootstrap.js")

    require('id="toggle-taxonomy-display"' not in preview, "Switch Tree button is still rendered")

    retired_runtime_scripts = (
        "large-force-graph/large-force-graph-contract.js",
        "large-force-graph/large-force-graph-layout.js",
        "large-force-graph/large-force-graph-styles.js",
        "large-force-graph/large-force-graph-core.js",
        "large-force-graph/adapters/taxonomy-large-force-adapter.js",
        "taxonomy-dynamic-prototype.js",
    )
    for script in retired_runtime_scripts:
        require(script not in preview, f"preview.php still loads retired taxonomy graph script: {script}")
        require(script not in iframe, f"preview_graph.html still loads retired taxonomy graph script: {script}")

    require(
        "let currentTaxonomyDisplayMode = 'tree';" in bootstrap,
        "taxonomy display mode does not default to tree",
    )
    require(
        "async function renderCurrentTaxonomyView(options = {}) {\n    return renderDefaultTree(options);\n  }" in bootstrap,
        "taxonomy entrypoint can still route to taxonomy Graph",
    )
    require(
        "toggleTaxonomyDisplayMode().catch" not in bootstrap,
        "Switch Tree click handler is still registered",
    )

    print("PASS: taxonomy runtime exposes the classification tree only")


if __name__ == "__main__":
    main()
