<?php
$pageTitle = 'TE-KG Expression';
$activePage = 'expression';
$protoCurrentPath = '/TE-/expression.php';
$protoSubtitle = 'Expression-oriented TE exploration workflows';

require __DIR__ . '/api/expression_data.php';

function tekg_expression_page_int(string $key, int $default, int $min = 1, ?int $max = null): int
{
    $value = $_GET[$key] ?? null;
    if ($value === null || $value === '') {
        return $default;
    }
    if (!is_numeric((string)$value)) {
        return $default;
    }
    $parsed = (int)$value;
    if ($parsed < $min) {
        $parsed = $min;
    }
    if ($max !== null && $parsed > $max) {
        $parsed = $max;
    }
    return $parsed;
}

function tekg_expression_page_float_or_null(string $key): ?string
{
    $value = trim((string)($_GET[$key] ?? ''));
    if ($value === '' || !is_numeric($value)) {
        return null;
    }
    return (string)(float)$value;
}

function tekg_expression_page_build_url(array $overrides = [], ?string $hash = null): string
{
    global $siteLang, $siteRenderer, $valueMode;

    $params = [
        'keyword' => trim((string)($_GET['keyword'] ?? '')),
        'dataset_key' => trim((string)($_GET['dataset_key'] ?? '')),
        'top_context' => trim((string)($_GET['top_context'] ?? '')),
        'min_global_median' => trim((string)($_GET['min_global_median'] ?? '')),
        'sort' => trim((string)($_GET['sort'] ?? 'default')),
        'value_mode' => trim((string)($_GET['value_mode'] ?? $valueMode ?? 'median')),
        'page_size' => (string)tekg_expression_page_int('page_size', 10, 5, 100),
        'page' => (string)tekg_expression_page_int('page', 1, 1),
    ];

    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($params[$key]);
        } else {
            $params[$key] = (string)$value;
        }
    }

    $params = array_filter($params, static fn (string $value): bool => $value !== '');
    $params['lang'] = $siteLang;
    $params['renderer'] = $siteRenderer;

    $url = '/TE-/expression.php?' . http_build_query($params);
    if ($hash !== null && $hash !== '') {
        $url .= '#' . rawurlencode($hash);
    }
    return $url;
}

function tekg_expression_format_number($value, int $decimals = 2): string
{
    if ($value === null || $value === '') {
        return '-';
    }
    return number_format((float)$value, $decimals, '.', ',');
}

function tekg_expression_metric_label(string $valueMode): string
{
    return match (tekg_expression_normalize_browse_value_mode($valueMode)) {
        'max' => 'Max',
        'min' => 'Min',
        default => 'Median',
    };
}

function tekg_expression_row_context_name(array $row, string $datasetKey, string $valueMode): string
{
    $mode = tekg_expression_normalize_browse_value_mode($valueMode);
    $primary = $row[$datasetKey . '_top_context_' . $mode . '_full_name'] ?? null;
    if ($primary !== null && trim((string)$primary) !== '') {
        return trim((string)$primary);
    }

    $fallback = $row[$datasetKey . '_top_context_' . $mode] ?? null;
    if ($fallback !== null && trim((string)$fallback) !== '') {
        return trim((string)$fallback);
    }

    return '-';
}

function tekg_expression_row_context_value(array $row, string $datasetKey, string $valueMode)
{
    $mode = tekg_expression_normalize_browse_value_mode($valueMode);
    return $row[$datasetKey . '_top_context_' . $mode . '_value'] ?? null;
}

function tekg_expression_row_cv_payload(array $row): array
{
    $candidates = [
        'normal_tissue' => [
            'label' => 'Normal Tissue',
            'value' => $row['normal_tissue_top_context_median_value'] ?? null,
            'cv' => $row['normal_tissue_cv_of_median'] ?? null,
        ],
        'normal_cell_line' => [
            'label' => 'Normal Cell Line',
            'value' => $row['normal_cell_line_top_context_median_value'] ?? null,
            'cv' => $row['normal_cell_line_cv_of_median'] ?? null,
        ],
        'cancer_cell_line' => [
            'label' => 'Cancer Cell Line',
            'value' => $row['cancer_cell_line_top_context_median_value'] ?? null,
            'cv' => $row['cancer_cell_line_cv_of_median'] ?? null,
        ],
    ];

    uasort($candidates, static function (array $a, array $b): int {
        return ($b['value'] <=> $a['value']);
    });

    $best = reset($candidates);
    return is_array($best) ? $best : ['label' => '-', 'value' => null, 'cv' => null];
}

$keyword = trim((string)($_GET['keyword'] ?? ''));
$datasetKey = tekg_expression_normalize_dataset_key((string)($_GET['dataset_key'] ?? ''));
$topContext = trim((string)($_GET['top_context'] ?? ''));
$minGlobalMedian = tekg_expression_page_float_or_null('min_global_median');
$sort = tekg_expression_normalize_sort((string)($_GET['sort'] ?? 'default'));
$valueMode = tekg_expression_normalize_browse_value_mode((string)($_GET['value_mode'] ?? 'median'));
$page = tekg_expression_page_int('page', 1, 1);
$pageSize = tekg_expression_page_int('page_size', 10, 5, 100);

$filters = [
    'keyword' => $keyword,
    'dataset_key' => $datasetKey,
    'top_context' => $topContext,
    'min_global_median' => $minGlobalMedian,
];

$options = [];
$browse = [
    'rows' => [],
    'pagination' => ['page' => 1, 'page_size' => $pageSize, 'total' => 0, 'total_pages' => 0],
    'filters' => $filters,
    'sort' => $sort,
];
$errorMessage = null;

try {
    $options = tekg_expression_fetch_filter_options();
    $browse = tekg_expression_fetch_browse_page($filters, $page, $pageSize, $sort);
    $browse['rows'] = tekg_expression_enrich_browse_rows($browse['rows']);
} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
}

require __DIR__ . '/head.php';
?>
      <style>
        .expression-shell {
          background: #f7f9fc;
          min-height: calc(100vh - 82px);
          padding: 28px 0 48px;
        }

        .proto-container {
          max-width: 1480px;
          margin: 0 auto;
          padding: 0 28px;
        }

        .expression-page-title {
          margin: 0 0 14px;
          font-size: 46px;
          font-weight: 700;
          color: #8a93a3;
          line-height: 1.08;
        }

        .expression-intro {
          max-width: 1220px;
          margin: 0 0 22px;
          color: #5f6f86;
          font-size: 15px;
          line-height: 1.85;
        }

        .expression-crumbs {
          display: flex;
          align-items: center;
          gap: 10px;
          margin-bottom: 34px;
          font-size: 16px;
          color: #70809a;
        }

        .expression-crumbs a {
          color: #2f63b9;
          font-weight: 500;
        }

        .expression-layout {
          display: grid;
          grid-template-columns: 1fr;
          gap: 18px;
        }

        .expression-panel,
        .expression-results {
          background: #ffffff;
          border: 1px solid #dfe6ef;
          border-radius: 10px;
          box-shadow: 0 4px 14px rgba(34, 56, 92, 0.05);
        }

        .expression-panel {
          padding: 24px 24px 20px;
        }

        .expression-panel h3 {
          margin: 0 0 18px;
          font-size: 22px;
          font-weight: 700;
          color: #1b3558;
        }

        .expression-filter-grid {
          display: grid;
          grid-template-columns: minmax(250px, 1.35fr) repeat(4, minmax(160px, 1fr)) auto;
          gap: 18px;
          align-items: end;
        }

        .expression-filter-group {
          display: grid;
          gap: 10px;
          margin: 0;
        }

        .expression-filter-label {
          font-size: 12px;
          font-weight: 700;
          color: #617089;
          letter-spacing: 0.04em;
          text-transform: uppercase;
        }

        .expression-filter-input,
        .expression-filter-select,
        .expression-page-size-select,
        .expression-page-jump-input {
          width: 100%;
          min-height: 46px;
          border: 1px solid #d8e0ea;
          border-radius: 8px;
          background: #ffffff;
          display: flex;
          align-items: center;
          padding: 0 14px;
          color: #49627f;
          font-size: 14px;
          outline: none;
          transition: border-color 0.2s ease, box-shadow 0.2s ease;
          box-sizing: border-box;
        }

        .expression-filter-input:focus,
        .expression-filter-select:focus,
        .expression-page-size-select:focus,
        .expression-page-jump-input:focus {
          border-color: #79a6ea;
          box-shadow: 0 0 0 4px rgba(92, 143, 219, 0.14);
        }

        .expression-filter-actions {
          display: flex;
          gap: 10px;
          align-items: center;
          justify-content: flex-end;
          min-height: 46px;
        }

        .expression-filter-btn {
          min-height: 40px;
          padding: 0 16px;
          border: 1px solid #d6e2f5;
          border-radius: 999px;
          background: #ffffff;
          color: #31588f;
          font-size: 13px;
          font-weight: 700;
          cursor: pointer;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          line-height: 1;
          text-decoration: none;
          box-sizing: border-box;
        }

        .expression-filter-btn.is-primary {
          background: #2f63b9;
          border-color: #2f63b9;
          color: #ffffff;
        }

        .expression-results {
          padding: 18px 18px 16px;
        }

        .expression-table-wrap {
          overflow: auto;
          border: 1px solid #dfe6ef;
          border-radius: 10px;
        }

        .expression-table {
          width: 100%;
          border-collapse: collapse;
          min-width: 1120px;
          background: #ffffff;
          table-layout: fixed;
        }

        .expression-table th,
        .expression-table td {
          padding: 12px 14px;
          text-align: left;
          border-bottom: 1px solid #ebeff5;
          font-size: 14px;
          vertical-align: top;
          overflow: hidden;
        }

        .expression-table th {
          background: #f3f6fa;
          color: #53657e;
          font-size: 12px;
          letter-spacing: 0.02em;
          font-weight: 800;
        }

        .expression-table tr:last-child td {
          border-bottom: none;
        }

        .expression-table tbody tr:hover {
          background: #f8fbff;
        }

        .expression-te-name {
          color: #214b8d;
          font-weight: 700;
          text-decoration: none;
          font-size: 16px;
        }

        .expression-context-cell,
        .expression-cv-cell,
        .expression-name-cell {
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }

        .expression-context-stack {
          display: grid;
          gap: 4px;
        }

        .expression-context-stack strong {
          color: #1f4678;
          font-weight: 700;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }

        .expression-context-stack small {
          color: #7a8ba5;
          font-size: 12px;
        }

        .expression-empty,
        .expression-error {
          display: block;
          padding: 34px 18px;
          text-align: center;
          color: #6f83a3;
          font-size: 14px;
          line-height: 1.8;
        }

        .expression-error {
          color: #8e4b4b;
          background: #fff8f7;
          border: 1px solid #f1d4d0;
          border-radius: 10px;
          text-align: left;
          padding: 20px 18px;
        }

        .expression-pagination {
          display: flex;
          align-items: center;
          justify-content: flex-end;
          gap: 24px;
          margin-top: 16px;
          flex-wrap: wrap;
        }

        .expression-page-size,
        .expression-page-jump,
        .expression-value-mode {
          display: inline-flex;
          align-items: center;
          gap: 14px;
          color: #445a79;
          font-size: 15px;
        }

        .expression-page-size-label,
        .expression-page-jump-label,
        .expression-value-mode-label {
          white-space: nowrap;
          font-weight: 500;
        }

        .expression-page-size-select {
          width: 118px;
          font-size: 15px;
          font-weight: 600;
          color: #2a436a;
          appearance: auto;
        }

        .expression-page-jump-input {
          width: 86px;
          font-size: 15px;
          color: #2a436a;
        }

        .expression-page-status {
          font-size: 15px;
          color: #243b61;
          min-width: 140px;
          text-align: center;
        }

        .expression-page-actions {
          display: flex;
          align-items: center;
          gap: 12px;
        }

        .expression-page-btn {
          width: 40px;
          height: 40px;
          border: none;
          background: transparent;
          color: #70819a;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          padding: 0;
          text-decoration: none;
        }

        .expression-page-btn svg {
          width: 24px;
          height: 24px;
          stroke: currentColor;
          stroke-width: 2.4;
          fill: none;
          stroke-linecap: round;
          stroke-linejoin: round;
        }

        .expression-page-btn:hover:not(.is-disabled) {
          color: #2f63b9;
        }

        .expression-page-btn.is-disabled {
          opacity: 0.35;
          cursor: not-allowed;
          pointer-events: none;
        }

        .expression-value-mode-group {
          display: inline-flex;
          align-items: center;
          gap: 8px;
          flex-wrap: wrap;
        }

        .expression-value-mode-btn {
          min-height: 36px;
          padding: 0 12px;
          border: 1px solid #d6e2f5;
          border-radius: 999px;
          background: #ffffff;
          color: #31588f;
          font-size: 13px;
          font-weight: 700;
          text-decoration: none;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          line-height: 1;
          box-sizing: border-box;
        }

        .expression-value-mode-btn.is-active {
          background: #eef4ff;
          border-color: #8fb3eb;
          color: #214b8d;
        }

        @media (max-width: 1180px) {
          .expression-filter-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
          }

          .expression-filter-actions {
            justify-content: flex-start;
          }
        }

        @media (max-width: 760px) {
          .proto-container {
            padding: 0 16px;
          }

          .expression-filter-grid {
            grid-template-columns: 1fr;
          }

          .expression-pagination {
            justify-content: flex-start;
          }
        }
      </style>

      <section class="expression-shell">
        <div class="proto-container">
          <h1 class="expression-page-title">Expression</h1>
          <p class="expression-intro">This browse view is now backed by the MySQL expression summary tables. It lets us shortlist TE records by expression context before we wire in the dedicated Expression detail page.</p>
          <div class="expression-crumbs">
            <a href="<?= htmlspecialchars(site_url_with_state('/TE-/index.php', $siteLang, $siteRenderer), ENT_QUOTES, 'UTF-8') ?>">Home</a>
            <span>/</span>
            <span>Expression</span>
          </div>

          <div class="expression-layout">
            <form class="expression-panel" method="get" action="<?= htmlspecialchars('/TE-/expression.php', ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="lang" value="<?= htmlspecialchars($siteLang, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="renderer" value="<?= htmlspecialchars($siteRenderer, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="value_mode" value="<?= htmlspecialchars($valueMode, ENT_QUOTES, 'UTF-8') ?>">
              <h3>Filters</h3>
              <div class="expression-filter-grid">
                <div class="expression-filter-group">
                  <div class="expression-filter-label">Keyword</div>
                  <input class="expression-filter-input" type="text" name="keyword" value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search a TE such as L1HS or AluY">
                </div>

                <div class="expression-filter-group">
                  <div class="expression-filter-label">Dataset Source</div>
                  <select class="expression-filter-select" name="dataset_key">
                    <option value="">All datasets</option>
                    <?php foreach (($options['datasets'] ?? []) as $dataset): ?>
                      <?php $optionKey = (string)($dataset['dataset_key'] ?? ''); ?>
                      <option value="<?= htmlspecialchars($optionKey, ENT_QUOTES, 'UTF-8') ?>" <?= $datasetKey === $optionKey ? 'selected' : '' ?>><?= htmlspecialchars((string)($dataset['dataset_label'] ?? $optionKey), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="expression-filter-group">
                  <div class="expression-filter-label">Top Context Contains</div>
                  <input class="expression-filter-input" type="text" name="top_context" value="<?= htmlspecialchars($topContext, ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. skin, ESCC, lung">
                </div>

                <div class="expression-filter-group">
                  <div class="expression-filter-label">Min Global Median</div>
                  <input class="expression-filter-input" type="number" step="0.01" min="0" name="min_global_median" value="<?= htmlspecialchars((string)($minGlobalMedian ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="0.00">
                </div>

                <div class="expression-filter-group">
                  <div class="expression-filter-label">Sort</div>
                  <select class="expression-filter-select" name="sort">
                    <?php foreach (($options['sort_options'] ?? []) as $option): ?>
                      <?php $sortKey = (string)($option['key'] ?? 'default'); ?>
                      <option value="<?= htmlspecialchars($sortKey, ENT_QUOTES, 'UTF-8') ?>" <?= $sort === $sortKey ? 'selected' : '' ?>><?= htmlspecialchars((string)($option['label'] ?? $sortKey), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="expression-filter-actions">
                  <button class="expression-filter-btn is-primary" type="submit">Apply</button>
                  <a class="expression-filter-btn" href="<?= htmlspecialchars(site_url_with_state('/TE-/expression.php', $siteLang, $siteRenderer), ENT_QUOTES, 'UTF-8') ?>">Reset</a>
                </div>
              </div>
            </form>

            <section class="expression-results" id="expressionResults">
              <?php if ($errorMessage !== null): ?>
                <div class="expression-error">Expression browse query failed: <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
              <?php elseif (empty($browse['rows'])): ?>
                <div class="expression-empty">No TE matched the current filters. Try clearing the keyword or relaxing the minimum global median threshold.</div>
              <?php else: ?>
                <div class="expression-table-wrap">
                  <table class="expression-table">
                    <colgroup>
                      <col style="width: 18%">
                      <col style="width: 24%">
                      <col style="width: 24%">
                      <col style="width: 24%">
                      <col style="width: 10%">
                    </colgroup>
                    <thead>
                      <tr>
                        <th>TE Name</th>
                        <th>Top Normal Tissue</th>
                        <th>Top Normal Cell Line</th>
                        <th>Top Cancer Type</th>
                        <th>CV</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($browse['rows'] as $row): ?>
                        <?php $cvPayload = tekg_expression_row_cv_payload($row); ?>
                        <?php $detailUrl = site_url_with_state('/TE-/expression_detail.php', $siteLang, $siteRenderer, ['te' => (string)$row['te_name'], 'metric' => 'median', 'sort' => 'default']); ?>
                        <tr>
                          <td class="expression-name-cell"><a class="expression-te-name" href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$row['te_name'], ENT_QUOTES, 'UTF-8') ?></a></td>
                          <td class="expression-context-cell">
                            <div class="expression-context-stack">
                              <strong><?= htmlspecialchars(tekg_expression_row_context_name($row, 'normal_tissue', $valueMode), ENT_QUOTES, 'UTF-8') ?></strong>
                              <small><?= htmlspecialchars(tekg_expression_metric_label($valueMode) . ' ' . tekg_expression_format_number(tekg_expression_row_context_value($row, 'normal_tissue', $valueMode)), ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                          </td>
                          <td class="expression-context-cell">
                            <div class="expression-context-stack">
                              <strong><?= htmlspecialchars(tekg_expression_row_context_name($row, 'normal_cell_line', $valueMode), ENT_QUOTES, 'UTF-8') ?></strong>
                              <small><?= htmlspecialchars(tekg_expression_metric_label($valueMode) . ' ' . tekg_expression_format_number(tekg_expression_row_context_value($row, 'normal_cell_line', $valueMode)), ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                          </td>
                          <td class="expression-context-cell">
                            <div class="expression-context-stack">
                              <strong><?= htmlspecialchars(tekg_expression_row_context_name($row, 'cancer_cell_line', $valueMode), ENT_QUOTES, 'UTF-8') ?></strong>
                              <small><?= htmlspecialchars(tekg_expression_metric_label($valueMode) . ' ' . tekg_expression_format_number(tekg_expression_row_context_value($row, 'cancer_cell_line', $valueMode)), ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                          </td>
                          <td class="expression-cv-cell">
                            <div class="expression-context-stack">
                              <strong><?= htmlspecialchars(tekg_expression_format_number($cvPayload['cv'] ?? null), ENT_QUOTES, 'UTF-8') ?></strong>
                              <small><?= htmlspecialchars((string)($cvPayload['label'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <?php $currentPage = (int)($browse['pagination']['page'] ?? 1); ?>
                <?php $totalPages = max(1, (int)($browse['pagination']['total_pages'] ?? 1)); ?>
                <?php $totalRows = (int)($browse['pagination']['total'] ?? 0); ?>
                <?php $from = $totalRows === 0 ? 0 : (($currentPage - 1) * $pageSize) + 1; ?>
                <?php $to = min($currentPage * $pageSize, $totalRows); ?>

                <div class="expression-pagination">
                  <form method="get" action="<?= htmlspecialchars('/TE-/expression.php#expressionResults', ENT_QUOTES, 'UTF-8') ?>" class="expression-page-size">
                    <input type="hidden" name="lang" value="<?= htmlspecialchars($siteLang, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="renderer" value="<?= htmlspecialchars($siteRenderer, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="keyword" value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="dataset_key" value="<?= htmlspecialchars((string)($datasetKey ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="top_context" value="<?= htmlspecialchars($topContext, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="min_global_median" value="<?= htmlspecialchars((string)($minGlobalMedian ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="value_mode" value="<?= htmlspecialchars($valueMode, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="page" value="1">
                    <span class="expression-page-size-label">Items Per Page:</span>
                    <select class="expression-page-size-select" name="page_size" onchange="this.form.submit()">
                      <?php foreach ([10, 20, 50, 100] as $sizeOption): ?>
                        <option value="<?= $sizeOption ?>" <?= $pageSize === $sizeOption ? 'selected' : '' ?>><?= $sizeOption ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>

                  <div class="expression-page-status"><?= $from ?> - <?= $to ?> of <?= number_format($totalRows) ?></div>

                  <form method="get" action="<?= htmlspecialchars('/TE-/expression.php#expressionResults', ENT_QUOTES, 'UTF-8') ?>" class="expression-page-jump">
                    <input type="hidden" name="lang" value="<?= htmlspecialchars($siteLang, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="renderer" value="<?= htmlspecialchars($siteRenderer, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="keyword" value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="dataset_key" value="<?= htmlspecialchars((string)($datasetKey ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="top_context" value="<?= htmlspecialchars($topContext, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="min_global_median" value="<?= htmlspecialchars((string)($minGlobalMedian ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="page_size" value="<?= $pageSize ?>">
                    <input type="hidden" name="value_mode" value="<?= htmlspecialchars($valueMode, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="expression-page-jump-label">Page</span>
                    <input class="expression-page-jump-input" name="page" type="number" min="1" step="1" value="<?= $currentPage ?>">
                  </form>

                  <div class="expression-value-mode">
                    <span class="expression-value-mode-label">Value</span>
                    <div class="expression-value-mode-group">
                      <?php foreach (['median' => 'Median', 'max' => 'Max', 'min' => 'Min'] as $modeKey => $modeLabel): ?>
                        <a class="expression-value-mode-btn <?= $valueMode === $modeKey ? 'is-active' : '' ?>" href="<?= htmlspecialchars(tekg_expression_page_build_url(['value_mode' => $modeKey, 'page' => 1], 'expressionResults'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($modeLabel, ENT_QUOTES, 'UTF-8') ?></a>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <div class="expression-page-actions">
                    <a class="expression-page-btn <?= $currentPage <= 1 ? 'is-disabled' : '' ?>" href="<?= htmlspecialchars(tekg_expression_page_build_url(['page' => max(1, $currentPage - 1)], 'expressionResults'), ENT_QUOTES, 'UTF-8') ?>" aria-label="Previous page">
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 6l-6 6 6 6"></path></svg>
                    </a>
                    <a class="expression-page-btn <?= $currentPage >= $totalPages ? 'is-disabled' : '' ?>" href="<?= htmlspecialchars(tekg_expression_page_build_url(['page' => min($totalPages, $currentPage + 1)], 'expressionResults'), ENT_QUOTES, 'UTF-8') ?>" aria-label="Next page">
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"></path></svg>
                    </a>
                  </div>
                </div>
              <?php endif; ?>
            </section>
          </div>
        </div>
      </section>
      <script>
        (function () {
          const resultsId = 'expressionResults';
          const resultsRoot = document.getElementById(resultsId);
          if (!resultsRoot) return;

          async function refreshExpressionResults(url) {
            const currentScrollY = window.scrollY;
            try {
              const response = await fetch(url, {
                headers: { 'X-Requested-With': 'fetch' },
                credentials: 'same-origin'
              });
              if (!response.ok) throw new Error(`HTTP ${response.status}`);
              const html = await response.text();
              const parser = new DOMParser();
              const doc = parser.parseFromString(html, 'text/html');
              const nextResults = doc.getElementById(resultsId);
              if (!nextResults) throw new Error('Expression results fragment was not found in the response.');
              const liveResults = document.getElementById(resultsId);
              if (!liveResults) return;
              liveResults.outerHTML = nextResults.outerHTML;
              window.history.pushState({ expressionUrl: url }, '', url);
              window.scrollTo(0, currentScrollY);
            } catch (error) {
              console.error('Expression results refresh failed:', error);
              window.location.href = url;
            }
          }

          document.addEventListener('click', function (event) {
            const target = event.target.closest('.expression-page-btn, .expression-value-mode-btn');
            if (!target) return;
            if (!(target instanceof HTMLAnchorElement)) return;
            if (target.classList.contains('is-disabled')) {
              event.preventDefault();
              return;
            }
            event.preventDefault();
            refreshExpressionResults(target.href);
          });

          document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (!form.closest('#' + resultsId)) return;
            event.preventDefault();
            const url = new URL(form.action || window.location.href, window.location.origin);
            const formData = new FormData(form);
            url.search = '';
            for (const [key, value] of formData.entries()) {
              if (typeof value === 'string' && value !== '') {
                url.searchParams.set(key, value);
              }
            }
            refreshExpressionResults(url.toString());
          });

          document.addEventListener('keydown', function (event) {
            const target = event.target;
            if (!(target instanceof HTMLInputElement)) return;
            if (!target.classList.contains('expression-page-jump-input')) return;
            if (event.key !== 'Enter') return;
            const form = target.form;
            if (!form) return;
            event.preventDefault();
            if (typeof form.requestSubmit === 'function') {
              form.requestSubmit();
            } else {
              form.submit();
            }
          });

          window.addEventListener('popstate', function () {
            refreshExpressionResults(window.location.href);
          });
        })();
      </script>
<?php require __DIR__ . '/foot.php'; ?>