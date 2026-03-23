<?php
require_once __DIR__ . '/site_i18n.php';

$siteLang = site_lang();
if (!isset($pageTitle)) {
    $pageTitle = site_t(['zh' => 'TE 数据库', 'en' => 'TE Database'], $siteLang);
}
if (!isset($activePage)) {
    $activePage = '';
}

$navItems = [
    'home' => ['label' => site_t(['zh' => '首页', 'en' => 'Home'], $siteLang), 'href' => 'index.php'],
    'preview' => ['label' => site_t(['zh' => '预览', 'en' => 'Preview'], $siteLang), 'href' => 'preview.php'],
    'search' => ['label' => site_t(['zh' => '搜索', 'en' => 'Search'], $siteLang), 'href' => 'search.php'],
    'download' => ['label' => site_t(['zh' => '下载', 'en' => 'Download'], $siteLang), 'href' => 'download.php'],
    'about' => ['label' => site_t(['zh' => '关于', 'en' => 'About'], $siteLang), 'href' => 'about.php'],
];

$currentQueryParams = $_GET;
unset($currentQueryParams['lang']);
$baseQuery = http_build_query($currentQueryParams);
$currentPath = basename((string) ($_SERVER['PHP_SELF'] ?? 'index.php'));
$zhUrl = $currentPath . ($baseQuery !== '' ? '?' . $baseQuery . '&lang=zh' : '?lang=zh');
$enUrl = $currentPath . ($baseQuery !== '' ? '?' . $baseQuery . '&lang=en' : '?lang=en');
?>
<!DOCTYPE html>
<html lang="<?= $siteLang === 'en' ? 'en' : 'zh-CN' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    :root {
      --bg: #f5f8fc;
      --panel: #ffffff;
      --border: #d9e5f3;
      --text: #19324d;
      --muted: #5e7288;
      --primary: #2563eb;
      --primary-soft: #e8f0ff;
      --te: #2563eb;
      --te-soft: #e8f0ff;
      --disease: #ef4444;
      --disease-soft: #fee2e2;
      --function: #10b981;
      --function-soft: #d1fae5;
      --paper: #f59e0b;
      --paper-soft: #fef3c7;
      --shadow: 0 14px 32px rgba(15, 23, 42, 0.08);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: "Segoe UI", "Microsoft YaHei", sans-serif;
      background: linear-gradient(180deg, #eef4fb, #f8fbff);
      color: var(--text);
    }
    a { color: inherit; text-decoration: none; }
    .site-header {
      position: sticky;
      top: 0;
      z-index: 20;
      background: rgba(255,255,255,0.94);
      border-bottom: 1px solid var(--border);
      backdrop-filter: blur(10px);
    }
    .site-header-inner {
      max-width: none;
      margin: 0;
      padding: 18px 24px 18px 36px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .brand-mark {
      width: 86px;
      height: 64px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex: 0 0 auto;
      overflow: visible;
      background: transparent;
      border: none;
      border-radius: 0;
      box-shadow: none;
    }
    .brand-mark img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      display: block;
    }
    .brand-title {
      margin: 0;
      font-size: 24px;
      line-height: 1.08;
    }
    .brand-subtitle {
      margin: 4px 0 0;
      color: var(--muted);
      font-size: 13px;
    }
    .site-nav {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .site-nav a {
      padding: 10px 16px;
      border-radius: 999px;
      color: var(--muted);
      font-weight: 600;
    }
    .site-nav a.active {
      background: var(--primary);
      color: #fff;
      box-shadow: 0 8px 18px rgba(37, 99, 235, 0.22);
    }
    .lang-switch {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      margin-left: 8px;
      padding: 4px;
      border: 1px solid var(--border);
      border-radius: 999px;
      background: #f7faff;
    }
    .lang-switch a {
      padding: 8px 14px;
      border-radius: 999px;
      color: var(--muted);
      font-weight: 700;
      font-size: 14px;
    }
    .lang-switch a.active {
      background: var(--primary);
      color: #fff;
      box-shadow: 0 8px 18px rgba(37, 99, 235, 0.18);
    }
    .page-shell {
      max-width: 1280px;
      margin: 0 auto;
      padding: 28px 24px 48px 8px;
    }
    .hero-card, .content-card {
      background: var(--panel);
      border: 1px solid var(--border);
      border-radius: 24px;
      box-shadow: var(--shadow);
    }
    .hero-card {
      padding: 28px;
      margin-bottom: 24px;
    }
    .content-card {
      padding: 24px;
    }
    .page-title {
      margin: 0 0 8px;
      font-size: 28px;
    }
    .page-desc {
      margin: 0;
      color: var(--muted);
      line-height: 1.7;
    }
    .placeholder-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 18px;
    }
    .placeholder {
      min-height: 180px;
      border: 1px dashed #b8c8de;
      border-radius: 18px;
      background: linear-gradient(135deg, #f8fbff, #eef4fb);
      padding: 18px;
    }
    .placeholder h3 {
      margin: 0 0 8px;
      font-size: 18px;
    }
    .placeholder p {
      margin: 0;
      color: var(--muted);
      line-height: 1.7;
    }
    .about-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.25fr) minmax(300px, 0.95fr);
      gap: 20px;
      align-items: start;
    }
    .about-stack {
      display: grid;
      gap: 18px;
    }
    .section-card {
      background: linear-gradient(180deg, #fbfdff, #f5f9ff);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 22px 22px 20px;
    }
    .section-card h3 {
      margin: 0 0 10px;
      font-size: 20px;
    }
    .section-card p {
      margin: 0;
      color: var(--muted);
      line-height: 1.8;
    }
    .section-card ul {
      margin: 10px 0 0;
      padding-left: 18px;
      color: var(--muted);
      line-height: 1.8;
    }
    .mini-stat {
      display: grid;
      gap: 12px;
      margin-top: 8px;
    }
    .mini-stat-row {
      display: flex;
      justify-content: space-between;
      gap: 16px;
      padding: 10px 0;
      border-bottom: 1px solid #e7eef8;
      color: var(--muted);
    }
    .mini-stat-row:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }
    .mini-stat-row strong {
      color: var(--text);
    }
    .note-box {
      margin-top: 12px;
      padding: 14px 16px;
      border-radius: 16px;
      background: #eef5ff;
      color: var(--muted);
      line-height: 1.7;
      border: 1px solid #d7e5fb;
    }
    .site-footer {
      max-width: 1280px;
      margin: 0 auto 28px;
      padding: 0 24px 0 8px;
      color: var(--muted);
      font-size: 13px;
    }
    @media (max-width: 860px) {
      .site-header-inner { flex-direction: column; align-items: flex-start; }
      .site-nav { width: 100%; }
      .page-shell { padding-left: 8px; }
      .about-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <header class="site-header">
    <div class="site-header-inner">
      <div class="brand">
        <div class="brand-mark" aria-label="<?= htmlspecialchars(site_t(['zh' => '站点图标', 'en' => 'Site logo'], $siteLang), ENT_QUOTES, 'UTF-8') ?>">
          <img src="tekg-logo.png" alt="TE-KG logo">
        </div>
        <div>
          <h1 class="brand-title"><?= htmlspecialchars(site_t(['zh' => '转座元件知识图谱', 'en' => 'Transposable Elements Knowledge Graph'], $siteLang), ENT_QUOTES, 'UTF-8') ?></h1>
          <p class="brand-subtitle">Transposable Elements Knowledge Graph</p>
        </div>
      </div>
      <nav class="site-nav" aria-label="<?= htmlspecialchars(site_t(['zh' => '主导航', 'en' => 'Main navigation'], $siteLang), ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($navItems as $key => $item): ?>
          <a href="<?= htmlspecialchars(site_url_with_lang($item['href'], $siteLang), ENT_QUOTES, 'UTF-8') ?>" class="<?= $activePage === $key ? 'active' : '' ?>"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
        <div class="lang-switch" aria-label="<?= htmlspecialchars(site_t(['zh' => '语言切换', 'en' => 'Language switch'], $siteLang), ENT_QUOTES, 'UTF-8') ?>">
          <a href="<?= htmlspecialchars($zhUrl, ENT_QUOTES, 'UTF-8') ?>" class="<?= $siteLang === 'zh' ? 'active' : '' ?>">中文</a>
          <a href="<?= htmlspecialchars($enUrl, ENT_QUOTES, 'UTF-8') ?>" class="<?= $siteLang === 'en' ? 'active' : '' ?>">English</a>
        </div>
      </nav>
    </div>
  </header>
  <main class="page-shell">
