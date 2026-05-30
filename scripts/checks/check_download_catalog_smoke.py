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
                    const headers = Array.from(document.querySelectorAll('.download-table thead th')).map((th) => th.textContent.trim());
                    const rows = Array.from(document.querySelectorAll('#download-table-body tr'));
                    const downloadLinks = Array.from(document.querySelectorAll('.file-link[href]'));
                    const greenish = Array.from(document.querySelectorAll('*')).some((el) => {
                        const style = getComputedStyle(el);
                        return ['color', 'backgroundColor', 'borderColor'].some((prop) => {
                            const value = style[prop];
                            return value === 'rgb(45, 95, 31)' || value === 'rgb(95, 143, 61)';
                        });
                    });
                    return {
                        title: document.querySelector('.download-page-title')?.textContent?.trim() || '',
                        panelTitle: document.querySelector('.download-panel h2')?.textContent?.trim() || '',
                        categoryFilters: Array.from(document.querySelectorAll('[data-download-category]')).map((button) => button.dataset.downloadCategory || ''),
                        summaryCards: document.querySelectorAll('.download-summary-card').length,
                        cards: document.querySelectorAll('.download-card').length,
                        hasTable: Boolean(document.querySelector('.download-table')),
                        hasSearch: Boolean(document.querySelector('#download-search')),
                        headers,
                        rowCount: rows.length,
                        statusMentions: headers.filter((header) => header === 'Status').length + document.querySelectorAll('.download-status').length,
                        categoryPills: document.querySelectorAll('.download-category-pill').length,
                        fileSizeBadges: document.querySelectorAll('.download-file-size').length,
                        downloadLinks: downloadLinks.length,
                        firstHref: downloadLinks[0]?.getAttribute('href') || '',
                        greenish,
                    };
                }
                """
            )

            require(state["title"] == "Download", f"unexpected page title: {state}")
            require(state["categoryFilters"] == ["All", "Expression", "Graph", "Taxonomy"], f"category filters missing or out of order: {state}")
            require(state["summaryCards"] == 0, f"summary cards should not be present: {state}")
            require(state["cards"] == 0, f"card catalog should not be present: {state}")
            require(state["hasTable"] is True, f"traditional table missing: {state}")
            require(state["hasSearch"] is True, f"early table search control missing: {state}")
            require(state["headers"] == ["Dataset", "File", "Used in", "Format"], f"traditional table headers mismatch: {state}")
            require(state["rowCount"] >= 10, f"download table rows missing: {state}")
            require(state["statusMentions"] == 0, f"Status column/badges should be removed: {state}")
            require(state["categoryPills"] == 0, f"card-era category pills should be removed: {state}")
            require(state["fileSizeBadges"] == 0, f"card-era file size badges should be removed: {state}")
            require(state["downloadLinks"] >= 10, f"download links missing: {state}")
            require("bulk_expression_web" in state["firstHref"], f"download paths should use current expression runtime root: {state}")
            require(state["greenish"] is False, f"legacy green palette should not remain in Download UI: {state}")

            page.click('[data-download-category="Graph"]')
            graph_state = page.evaluate(
                """
                () => ({
                    rowCount: document.querySelectorAll('#download-table-body tr').length,
                    bodyText: document.querySelector('#download-table-body')?.textContent || '',
                })
                """
            )
            require(graph_state["rowCount"] == 2, f"Graph filter should show the two graph datasets: {graph_state}")
            require("Graph seed" in graph_state["bodyText"], f"Graph filter should include graph seed: {graph_state}")

            page.click('[data-download-category="All"]')
            page.fill("#download-search", "taxonomy")
            page.wait_for_timeout(100)
            filtered = page.evaluate(
                """
                () => ({
                    rowCount: document.querySelectorAll('#download-table-body tr').length,
                    summary: document.querySelector('#download-summary')?.textContent?.trim() || '',
                })
                """
            )
            require(filtered["rowCount"] >= 2, f"search should filter taxonomy datasets: {filtered}")
            require("entries" in filtered["summary"], f"summary should remain in early table wording: {filtered}")

            page.click(".dataset-toggle")
            expanded = page.locator(".dataset-row.is-open").count()
            require(expanded == 1, f"dataset description toggle should open exactly one row, got {expanded}")
        finally:
            browser.close()

    ok("Download catalog smoke passed")


if __name__ == "__main__":
    run_check(main)
