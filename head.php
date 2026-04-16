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
  <link rel="stylesheet" href="/TE-/assets/css/layout.css">
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
            <a class="proto-control<?= $siteLang === 'zh' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($zhUrl, ENT_QUOTES, 'UTF-8') ?>">涓枃</a>
            <a class="proto-control<?= $siteLang === 'en' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($enUrl, ENT_QUOTES, 'UTF-8') ?>">English</a>
          </div>

        </div>
      </div>
    </header>
    <main class="proto-main">

