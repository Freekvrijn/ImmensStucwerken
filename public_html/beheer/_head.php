<?php
/** Gedeelde kop voor alle beheerpagina's. */
require_once __DIR__ . '/../_lib/cms.php';
if (cms_module_aan('sectie_indeling')) { require_once __DIR__ . '/../_lib/sectie_indeling.php'; }
$ingelogd = function_exists('is_logged_in') && is_logged_in();
$gebruiker = $ingelogd && function_exists('huidige_gebruiker') ? huidige_gebruiker() : null;
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(isset($pageTitle) ? $pageTitle : 'Beheer') ?> | <?= e(cms_site_naam()) ?> beheer</title>
<link rel="stylesheet" href="beheer.css?v=<?= (int) @filemtime(__DIR__ . '/beheer.css') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&amp;family=Montserrat:wght@400;700&amp;family=Roboto:wght@400;700&amp;family=Open+Sans:wght@400;700&amp;family=Lato:wght@400;700&amp;display=swap" rel="stylesheet">
</head>
<body>
<header class="admin-top">
  <a class="admin-brand" href="<?= $ingelogd ? 'index.php' : 'login.php' ?>"><?= e(cms_site_naam()) ?> · beheer</a>
  <?php if ($ingelogd && mag('settings') && cms_module_aan('onderhoud')):
      require_once __DIR__ . '/../_lib/onderhoud.php';
      if (onderhoud_actief()): ?>
  <a class="admin-onderhoud" href="index.php#onderhoud">Site in onderhoud</a>
  <?php endif; endif; ?>
  <?php if ($ingelogd): ?>
  <button class="admin-menu-toggle" type="button" aria-controls="adminMenu" aria-expanded="false" aria-label="Beheermenu openen"><span aria-hidden="true"></span></button>
  <nav class="admin-nav" id="adminMenu" aria-label="Beheermenu">
    <?php if (mag('dashboard')): ?><a href="index.php">Overzicht</a><?php endif; ?>
    <?php if (mag('settings')): ?><a href="offertes.php">Offertes</a><?php endif; ?>
    <?php if (mag('content')): ?>
      <?php if (cms_module_aan('paginas')): ?><a href="paginas.php">Pagina&rsquo;s</a><?php endif; ?>
      <?php if (cms_module_aan('collectie')): ?><a href="collectie.php"><?= e(CMS_COLLECTIE_LABEL) ?></a><?php endif; ?>
      <?php if (cms_module_aan('faq')): ?><a href="faq.php">FAQ</a><?php endif; ?>
    <?php endif; ?>
    <?php if (mag('seo') && cms_module_aan('seo')): ?><a href="seo.php">SEO</a><?php endif; ?>
    <details class="admin-more">
      <summary>Meer</summary>
      <div class="admin-subnav">
        <?php if (mag('content') && cms_module_aan('navigatie')): ?><a href="navigatie.php">Navigatie</a><?php endif; ?>
        <?php if (mag('media') && cms_module_aan('media')): ?><a href="media.php">Media</a><?php endif; ?>
        <?php if (mag('forms') && cms_module_aan('formulieren')): ?><a href="formulieren.php">Formulieren<?php
          require_once __DIR__ . '/../_lib/formulieren.php';
          $ongelezen = cms_inzendingen_nieuw();
          if ($ongelezen > 0): ?> <span class="teller"><?= (int) $ongelezen ?></span><?php endif; ?></a><?php endif; ?>
        <?php if (mag('content') && cms_module_aan('sectie_indeling') && function_exists('site_sectie_register') && site_sectie_register()): ?><a href="sectie-indeling.php">Pagina-indeling</a><?php endif; ?>
        <?php if (mag('settings') && cms_module_aan('redirects')): ?><a href="redirects.php">Redirects</a><?php endif; ?>
        <?php if (mag('settings')): ?><a href="instellingen.php">Site-instellingen</a><?php endif; ?>
        <?php if (mag('security') && cms_module_aan('toegang')): ?><a href="toegang.php">Toegang</a><?php endif; ?>
        <?php if (mag('users')): ?><a href="gebruikers.php">Gebruikers</a><?php endif; ?>
        <?php if (mag('backup')): ?><a href="versies.php">Versies &amp; back-up</a><?php endif; ?>
        <?php if (mag('settings') && cms_module_aan('onderhoud')): ?><a href="index.php#onderhoud">Onderhoudsmodus</a><?php endif; ?>
        <?php if (is_admin()): ?><a href="wachtwoord-instellen.php">Hoofdwachtwoord</a><?php endif; ?>
      </div>
    </details>
    <?php if (CMS_PUBLIEKE_SITE_AAN): ?><a href="../" target="_blank" rel="noopener">Bekijk site ↗</a><?php endif; ?>
    <a href="logout.php" class="logout">Uitloggen<?= is_array($gebruiker) && !empty($gebruiker['naam']) ? ' · ' . e($gebruiker['naam']) : '' ?></a>
  </nav>
  <?php endif; ?>
</header>
<main class="admin-main">
