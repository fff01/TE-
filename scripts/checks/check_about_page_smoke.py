from __future__ import annotations

from harness_lib import app_url, fail, ok, require, run_check


def main() -> None:
    try:
        from playwright.sync_api import Error as PlaywrightError
        from playwright.sync_api import sync_playwright
    except ImportError:
        fail("Playwright is not installed")

    with sync_playwright() as p:
        try:
            browser = p.chromium.launch(headless=True)
        except PlaywrightError as exc:
            fail(f"Unable to launch Chromium: {exc}")

        page = browser.new_page(viewport={"width": 1440, "height": 960})
        try:
            page.goto(app_url("about.php"), wait_until="networkidle", timeout=30000)
            state = page.evaluate(
                """
                () => ({
                    title: document.querySelector('.page-title-hero')?.textContent?.trim() || '',
                    nav: Array.from(document.querySelectorAll('.about-nav a')).map((link) => link.textContent.trim()),
                    activePane: document.querySelector('.about-pane.is-active')?.id || '',
                    openLinks: document.querySelectorAll('.about-open-link[href]').length,
                    detailCards: document.querySelectorAll('.about-detail-card').length,
                    pageText: document.body.textContent,
                })
                """
            )
            expected_nav = ["Home", "Browse", "Path Finder", "TE-KG", "Agent", "Expression", "Download", "About"]
            require(state["title"] == "About", f"unexpected About title: {state}")
            require(state["nav"] == expected_nav, f"About navigation mismatch: {state}")
            require(state["activePane"] == "pane-home", f"Home pane should be active by default: {state}")
            require(state["openLinks"] == 0, f"Open page links should be removed: {state}")
            require(state["detailCards"] >= 24, f"About should provide detailed guide cards for every page: {state}")
            require("Best for" not in state["pageText"], f"Best for text should be removed: {state}")
            require("Open page" not in state["pageText"], f"Open page text should be removed: {state}")
            for phrase in (
                "What the page contains",
                "Recommended workflow",
                "Dataset Status",
                "category selector",
                "evidence tables",
                "interactive G6 graph",
                "natural-language assistant",
                "category filter",
                "Do not interpret journal IF as confidence",
                "data/bulk_expression_web",
            ):
                require(phrase in state["pageText"], f"About guide text missing phrase {phrase!r}: {state}")

            page.click('[data-pane="pathfinder"]')
            path_state = page.evaluate(
                """
                () => ({
                    hash: window.location.hash,
                    activePane: document.querySelector('.about-pane.is-active')?.id || '',
                    activeNav: document.querySelector('.about-nav a.is-active')?.dataset.pane || '',
                })
                """
            )
            require(path_state["hash"] == "#pathfinder", f"Path Finder hash should update: {path_state}")
            require(path_state["activePane"] == "pane-pathfinder", f"Path Finder pane should activate: {path_state}")
            require(path_state["activeNav"] == "pathfinder", f"Path Finder nav should activate: {path_state}")
        finally:
            browser.close()

    ok("About page smoke passed")


if __name__ == "__main__":
    run_check(main)
