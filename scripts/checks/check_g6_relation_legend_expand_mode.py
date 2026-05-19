from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def assert_contains(path: str, needle: str) -> None:
    content = read(path)
    assert needle in content, f"{path} is missing {needle!r}"


def assert_not_contains(path: str, needle: str) -> None:
    content = read(path)
    assert needle not in content, f"{path} should not contain {needle!r}"


def main() -> None:
    assert_not_contains("preview.php", "key-node-level-control")
    assert_not_contains("preview.php", "Key-node level")
    assert_contains("preview.php", "toggle-expand-mode")
    assert_contains("preview.php", "graph-legend-mode-switch")
    assert_contains("preview.php", "graph-relation-min-pmids")

    assert_contains("assets/js/renderers/g6/index-g6.bootstrap.js", "relationLegendState")
    assert_contains("assets/js/renderers/g6/index-g6.bootstrap.js", "relationLegendKeyForEdge")
    assert_contains("assets/js/renderers/g6/index-g6.bootstrap.js", "getVisibleRelationPayload")
    assert_contains("assets/js/renderers/g6/index-g6.bootstrap.js", "relationMinPmids")
    assert_contains("assets/js/renderers/g6/index-g6.bootstrap.js", "expandModeEnabled")
    assert_contains("assets/js/renderers/g6/index-g6.bootstrap.js", "expandSelectedNode")
    assert_contains("assets/js/renderers/g6/index-g6.bootstrap.js", "mergeGraphElements")

    assert_contains("assets/js/renderers/g6/index-g6-shared.js", "visibleRelations")
    assert_contains("assets/js/renderers/g6/index-g6-shared.js", "relationLegendKeyForEdge")
    assert_contains("assets/js/renderers/g6/index-g6-shared.js", "minRelationPmids")
    assert_contains("assets/js/renderers/g6/index-g6-shared.js", "relationStyleForType")
    assert_contains("assets/js/renderers/g6/index-g6-shared.js", "relationLegendMeta")

    assert_contains("assets/css/pages/preview.css", "graph-legend-mode-switch")
    assert_contains("assets/css/pages/preview.css", "text-decoration-thickness: 2px")
    assert_not_contains("assets/css/pages/preview.css", ".graph-legend-tab.is-active {\n          border-radius")
    assert_contains("assets/css/pages/preview.css", "expand-mode")
    assert_contains("assets/css/pages/preview.css", "graph-relation-line")


if __name__ == "__main__":
    main()
