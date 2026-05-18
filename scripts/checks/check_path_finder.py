from __future__ import annotations

from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def main() -> int:
    failures: list[str] = []

    required_files = [
        "path_finder.php",
        "api/path_finder.php",
        "api/path_finder_service.php",
        "assets/js/pages/path_finder.js",
        "assets/css/pages/path_finder.css",
    ]
    for relative in required_files:
        if not (ROOT / relative).is_file():
            failures.append(f"missing {relative}")

    head = text("head.php")
    nav_start = head.find("$navItems = [")
    nav_end = head.find("];", nav_start)
    nav = head[nav_start:nav_end]
    browse_pos = nav.find("'browse'")
    path_pos = nav.find("'path_finder'")
    preview_pos = nav.find("'preview'")
    if not (browse_pos != -1 and path_pos != -1 and preview_pos != -1 and browse_pos < path_pos < preview_pos):
        failures.append("Path Finder nav item is not between Browse and TE-KG")

    index = text("index.php")
    browse_quick = index.find("'Browse'")
    path_quick = index.find("'Path Finder'")
    preview_quick = index.find("'TE-KG'")
    if not (browse_quick != -1 and path_quick != -1 and preview_quick != -1 and browse_quick < path_quick < preview_quick):
        failures.append("Path Finder quick link is not between Browse and TE-KG")

    if (ROOT / "assets/js/pages/path_finder.js").is_file():
        js = text("assets/js/pages/path_finder.js")
        if "https://pubmed.ncbi.nlm.nih.gov/" not in js:
            failures.append("Path Finder JS does not build PubMed PMID links")
        if "compact-path-strip" not in js:
            failures.append("Path Finder JS does not render compact multi-hop path strips")
        if "path-mini-graph" in js or "g6" in js.lower():
            failures.append("Path Finder JS appears to use a graph view for direct relations")

    if (ROOT / "api/path_finder_service.php").is_file():
        service = text("api/path_finder_service.php")
        if "NONE(label IN labels(node) WHERE label = 'Paper')" not in service:
            failures.append("Path Finder service does not exclude Paper nodes from paths")
        if "BIO_RELATION*1.." not in service:
            failures.append("Path Finder service does not query BIO_RELATION paths")
        if "max(1, min(3" not in service:
            failures.append("Path Finder service does not clamp max_depth to 1..3")

    if failures:
        print("FAIL path finder check")
        for failure in failures:
            print(f"- {failure}")
        return 1

    print("PASS path finder check")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
