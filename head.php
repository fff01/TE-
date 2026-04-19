<?php
require_once __DIR__ . '/site_i18n.php';

$siteLang = site_lang();
$activePage = $activePage ?? 'home';
$pageTitle = $pageTitle ?? 'TE-KG';
$protoBasePath = '/TE-';
$protoCurrentPath = $protoCurrentPath ?? ($protoBasePath . '/index.php');
$protoSubtitle = $protoSubtitle ?? 'Transposable Elements Knowledge Graph';
$pageExtraStylesheets = is_array($pageExtraStylesheets ?? null) ? $pageExtraStylesheets : [];

$navItems = [
    'home' => ['label' => 'Home', 'href' => $protoBasePath . '/index.php'],
    'browse' => ['label' => 'Browse', 'href' => $protoBasePath . '/browse.php'],
    'preview' => ['label' => 'TE-KG', 'href' => $protoBasePath . '/preview.php'],
    'agent' => ['label' => 'Agent', 'href' => $protoBasePath . '/agent.php'],
    'genomic' => ['label' => 'Genomic', 'href' => $protoBasePath . '/genomic.php'],
    'expression' => ['label' => 'Expression', 'href' => $protoBasePath . '/expression.php'],
    'epigenetics' => ['label' => 'Epigenetics', 'href' => $protoBasePath . '/epigenetics.php'],
    'download' => ['label' => 'Download', 'href' => $protoBasePath . '/download.php'],
    'about' => ['label' => 'About', 'href' => $protoBasePath . '/about.php'],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="/TE-/assets/css/layout.css">
  <?php foreach ($pageExtraStylesheets as $stylesheet): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars((string)$stylesheet, ENT_QUOTES, 'UTF-8') ?>">
  <?php endforeach; ?>
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
              <a class="proto-nav-link<?= $activePage === $key ? ' is-active' : '' ?>" href="<?= htmlspecialchars(site_url_with_state($item['href']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
          </nav>

        </div>
      </div>
    </header>
    <main class="proto-main">

