from __future__ import annotations

from harness_lib import app_url, ok, require, run_check


def main() -> None:
    try:
        from playwright.sync_api import Error as PlaywrightError
        from playwright.sync_api import sync_playwright
    except ImportError:
        raise AssertionError("Playwright is not installed")

    with sync_playwright() as p:
        try:
            browser = p.chromium.launch(headless=True)
        except PlaywrightError as exc:
            raise AssertionError(f"Unable to launch Chromium: {exc}") from exc

        page = browser.new_page(viewport={"width": 1440, "height": 980})
        try:
            page.goto(app_url("download.php"), wait_until="networkidle", timeout=30000)
            state = page.evaluate(
                """
                () => {
                    const cards = Array.from(document.querySelectorAll('.download-card'));
                    const categories = Array.from(document.querySelectorAll('[data-download-category]'));
                    const downloadLinks = Array.from(document.querySelectorAll('.download-card-action[href]'));
                    const paletteText = getComputedStyle(document.querySelector('.download-page-title')).color;
                    const greenish = Array.from(document.querySelectorAll('*')).some((el) => {
                        const style = getComputedStyle(el);
                        return ['color', 'backgroundColor', 'borderColor'].some((prop) => {
                            const value = style[prop];
                            return value === 'rgb(45, 95, 31)' || value === 'rgb(95, 143, 61)';
                        });
                    });
                    return {
                        title: document.querySelector('.download-page-title')?.textContent?.trim() || '',
                        summaryCards: document.querySelectorAll('.download-summary-card').length,
                        categoryFilters: categories.map((button) => button.textContent.trim()),
                        cardCount: cards.length,
                        availableBadges: document.querySelectorAll('.download-status.is-available').length,
                        sizeBadges: Array.from(document.querySelectorAll('.download-file-size')).filter((el) => el.textContent.trim() !== '').length,
                        downloadLinks: downloadLinks.length,
                        paletteText,
                        greenish,
                    };
                }
                """
            )

            require(state["title"] == "Download", f"unexpected page title: {state}")
            require(state["summaryCards"] >= 3, f"download summary cards missing: {state}")
            require(any(label.startswith("Expression") for label in state["categoryFilters"]), f"Expression category filter missing: {state}")
            require(any(label.startswith("Graph") for label in state["categoryFilters"]), f"Graph category filter missing: {state}")
            require(any(label.startswith("Taxonomy") for label in state["categoryFilters"]), f"Taxonomy category filter missing: {state}")
            require(state["cardCount"] >= 6, f"download cards missing: {state}")
            require(state["availableBadges"] >= 6, f"available status badges missing: {state}")
            require(state["sizeBadges"] >= 6, f"file size badges missing: {state}")
            require(state["downloadLinks"] >= 6, f"download action links missing: {state}")
            require(state["greenish"] is False, f"legacy green palette should not remain in Download UI: {state}")

            page.locator('[data-download-category="Graph"]').click()
            graph_state = page.evaluate(
                """
                () => Array.from(document.querySelectorAll('.download-card-category'))
                    .map((el) => el.textContent.trim())
                """
            )
            require(graph_state, "Graph filter should show at least one card")
            require(all(label == "Graph" for label in graph_state), f"Graph filter should show only Graph cards: {graph_state}")

            page.locator('[data-download-category="All"]').click()
            page.locator("#download-search").fill("taxonomy")
            search_count = page.locator(".download-card").count()
            require(search_count >= 1, "search should find taxonomy-related downloads")
        finally:
            browser.close()

    ok("Download catalog smoke passed")


if __name__ == "__main__":
    run_check(main)
