<?php
require_once __DIR__ . '/site_i18n.php';

$siteLang = site_lang();
$siteRenderer = site_renderer();
$activePage = $activePage ?? 'home';
$pageTitle = $pageTitle ?? 'TE-KG';
$protoBasePath = '/TE-';
$protoCurrentPath = $protoCurrentPath ?? ($protoBasePath . '/index.php');
$protoSubtitle = $protoSubtitle ?? 'Transposable Elements Knowledge Graph';

$navItems = [
    'home' => ['label' => 'Home', 'href' => $protoBasePath . '/index.php'],
    'browse' => ['label' => 'Browse', 'href' => $protoBasePath . '/browse.php'],
    'preview' => ['label' => 'TE-KG', 'href' => $protoBasePath . '/preview.php'],
    'genomic' => ['label' => 'Genomic', 'href' => $protoBasePath . '/genomic.php'],
    'expression' => ['label' => 'Expression', 'href' => $protoBasePath . '/expression.php'],
    'epigenetics' => ['label' => 'Epigenetics', 'href' => $protoBasePath . '/epigenetics.php'],
    'download' => ['label' => 'Download', 'href' => $protoBasePath . '/download.php'],
    'about' => ['label' => 'About', 'href' => $protoBasePath . '/about.php'],
];

$currentQueryParams = $_GET;
unset($currentQueryParams['lang'], $currentQueryParams['renderer']);
$zhUrl = site_url_with_state($protoCurrentPath, 'zh', $siteRenderer, $currentQueryParams);
$enUrl = site_url_with_state($protoCurrentPath, 'en', $siteRenderer, $currentQueryParams);
?>
<!DOCTYPE html>
<html lang="<?= $siteLang === 'zh' ? 'zh-CN' : 'en' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    :root {
      --header-blue: #2f63b9;
      --header-blue-strong: #214b8d;
      --header-line: #d9e5f7;
      --header-text: #18345f;
      --header-muted: #61789f;
    }

    * { box-sizing: border-box; }

    html {
      scroll-behavior: smooth;
    }

    body {
      margin: 0;
      font-family: Inter, "Segoe UI", "Microsoft YaHei", sans-serif;
      color: var(--header-text);
      background: #ffffff;
    }

    a {
      color: inherit;
      text-decoration: none;
    }

    .proto-shell {
      min-height: 100vh;
    }

    .proto-header {
      position: sticky;
      top: 0;
      z-index: 100;
      background: var(--header-blue);
      border-bottom: 1px solid rgba(255,255,255,0.12);
      transition: background-color 0.22s ease, border-color 0.22s ease, box-shadow 0.22s ease;
    }

    .proto-header.is-scrolled {
      background: #ffffff;
      border-bottom-color: var(--header-line);
      box-shadow: 0 6px 18px rgba(19, 53, 102, 0.08);
    }

    .proto-header-inner {
      max-width: 1720px;
      margin: 0 auto;
      padding: 0 10px;
      width: min(100%, 1720px);
      min-height: 82px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
    }

    .proto-brand {
      display: flex;
      align-items: center;
      gap: 16px;
      min-width: 0;
    }

    .proto-brand-logo {
      width: 92px;
      height: 46px;
      object-fit: contain;
      background: transparent;
      padding: 0;
      border-radius: 0;
    }

    .proto-brand-title {
      margin: 0;
      font-size: 18px;
      font-weight: 800;
      color: #fff;
      transition: color 0.22s ease;
    }

    .proto-brand-subtitle {
      margin: 4px 0 0;
      font-size: 12px;
      color: rgba(255,255,255,0.76);
      transition: color 0.22s ease;
    }

    .proto-header.is-scrolled .proto-brand-title {
      color: var(--header-blue-strong);
    }

    .proto-header.is-scrolled .proto-brand-subtitle {
      color: var(--header-muted);
    }

    .proto-header-right {
      display: flex;
      align-items: center;
      gap: 16px;
      flex-wrap: nowrap;
      justify-content: flex-end;
      margin-left: auto;
    }

    .proto-nav {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-wrap: nowrap;
    }

    .proto-nav-link {
      padding: 10px 14px;
      font-size: 14px;
      font-weight: 700;
      color: rgba(255,255,255,0.94);
      transition: color 0.22s ease, opacity 0.22s ease;
    }

    .proto-nav-link.is-active {
      text-decoration: underline;
      text-underline-offset: 5px;
      text-decoration-thickness: 2px;
    }

    .proto-header.is-scrolled .proto-nav-link {
      color: var(--header-blue-strong);
    }

    .proto-control-group {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      flex: 0 0 auto;
    }

    .proto-control-group.is-hidden {
      display: none;
    }

    .proto-control {
      min-width: 84px;
      padding: 10px 16px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,0.22);
      color: rgba(255,255,255,0.94);
      font-size: 13px;
      font-weight: 700;
      text-align: center;
      transition: all 0.22s ease;
    }

    .proto-control.is-active {
      background: #ffffff;
      color: var(--header-blue-strong);
      border-color: #ffffff;
    }

    .proto-header.is-scrolled .proto-control {
      border-color: #d4e1f6;
      color: var(--header-blue-strong);
      background: #ffffff;
    }

    .proto-header.is-scrolled .proto-control.is-active {
      background: var(--header-blue);
      border-color: var(--header-blue);
      color: #ffffff;
    }

    .proto-main {
      display: block;
    }

    @media (max-width: 1120px) {
      .proto-header-inner {
        align-items: flex-start;
        padding-top: 14px;
        padding-bottom: 14px;
        min-height: auto;
        flex-direction: column;
      }

      .proto-header-right {
        width: 100%;
        justify-content: flex-start;
        flex-wrap: wrap;
      }

      .proto-nav {
        flex-wrap: wrap;
      }
    }

    @media (max-width: 680px) {
      .proto-header-inner {
        padding-left: 10px;
        padding-right: 10px;
      }

      .proto-brand {
        align-items: flex-start;
      }

      .proto-brand-logo {
        width: 84px;
        height: 42px;
      }
    }
  </style>
</head>
<body>
  <div class="proto-shell">
    <header class="proto-header" id="protoHeader">
      <div class="proto-header-inner">
        <div class="proto-brand">
          <img class="proto-brand-logo" src="/TE-/assets/img/brand/tekg-logo.png" alt="TE-KG logo">
          <div>
            <h1 class="proto-brand-title">Transposable Elements Knowledge Graph</h1>
            <p class="proto-brand-subtitle"><?= htmlspecialchars($protoSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        </div>

        <div class="proto-header-right">
          <nav class="proto-nav" aria-label="Primary">
            <?php foreach ($navItems as $key => $item): ?>
              <a class="proto-nav-link<?= $activePage === $key ? ' is-active' : '' ?>" href="<?= htmlspecialchars(site_url_with_state($item['href'], $siteLang, $siteRenderer), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
          </nav>

          <div class="proto-control-group is-hidden" aria-label="Language switch">
            <a class="proto-control<?= $siteLang === 'zh' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($zhUrl, ENT_QUOTES, 'UTF-8') ?>">中文</a>
            <a class="proto-control<?= $siteLang === 'en' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($enUrl, ENT_QUOTES, 'UTF-8') ?>">English</a>
          </div>

        </div>
      </div>
    </header>
    <main class="proto-main">
