<?php
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
require_once __DIR__ . '/../_lib/cms.php';
require_once __DIR__ . '/../_lib/velden.php';
auth_boot();
require_login('dashboard');

$paginaAantal = count(site_pagina_register());
$mediaAantal = count(beschikbare_afbeeldingen());
$inzendingen = load_json('inzendingen.json', array());
$nieuwAantal = count(array_filter($inzendingen, function ($item) { return ($item['status'] ?? 'nieuw') === 'nieuw'; }));
$onderhoud = null;
$onderhoudMelding = null;
if (mag('settings') && cms_module_aan('onderhoud')) {
    require_once __DIR__ . '/../_lib/onderhoud.php';
    $onderhoud = onderhoud_stand();
    $onderhoudMelding = $_SESSION['onderhoud_melding'] ?? null;
    unset($_SESSION['onderhoud_melding']);
}
$pageTitle = 'Overzicht';
require __DIR__ . '/_head.php';
?>
<h1>Beheer-overzicht</h1>
<p class="lead">Welkom. Dit CMS staat los klaar en kan door een LLM aan een bestaande of nieuwe PHP-site worden gekoppeld.</p>

<?php if ($onderhoudMelding): ?><p class="msg <?= !empty($onderhoudMelding['ok']) ? 'ok' : 'err' ?>"><?= e($onderhoudMelding['tekst']) ?></p><?php endif; ?>
<?php if ($onderhoud): ?>
<section class="onderhoud-schakelaar<?= $onderhoud['actief'] ? ' is-aan' : '' ?>" id="onderhoud">
  <div>
    <h2><?= $onderhoud['actief'] ? 'De website staat in onderhoud' : 'De website staat online' ?></h2>
    <p><?= $onderhoud['actief'] ? 'Bezoekers zien de onderhoudspagina. Ingelogde beheerders kunnen de website wel bekijken.' : 'De publieke website is voor iedereen toegankelijk.' ?>
    <?php if ($onderhoud['gewijzigd'] !== ''): ?><span class="onderhoud-schakelaar__wanneer">Laatst gewijzigd op <?= e(date('d-m-Y H:i', strtotime($onderhoud['gewijzigd']))) ?><?= $onderhoud['door'] !== '' ? ' door ' . e($onderhoud['door']) : '' ?>.</span><?php endif; ?></p>
  </div>
  <form method="post" action="onderhoudsmodus.php" data-confirm="<?= $onderhoud['actief'] ? 'De website openzetten voor iedereen?' : 'De onderhoudsmodus aanzetten?' ?>">
    <?= csrf_field() ?><input type="hidden" name="onderhoud" value="<?= $onderhoud['actief'] ? 'uit' : 'aan' ?>">
    <button class="btn<?= $onderhoud['actief'] ? '' : ' ghost' ?>" type="submit"><?= $onderhoud['actief'] ? 'Website openzetten' : 'Onderhoudsmodus aanzetten' ?></button>
  </form>
</section>
<?php endif; ?>

<?php if (!CMS_PUBLIEKE_SITE_AAN): ?>
<div class="info-blok">
  <strong>Nog niet gekoppeld</strong>
  <p>Het beheer werkt zelfstandig. De openbare website en haar templates worden tijdens de integratie aangesloten via <code>_lib/public.php</code> en <code>_lib/velden.php</code>.</p>
</div>
<?php endif; ?>

<h2 class="dashboard-group">Website-inhoud</h2>
<div class="cards cards--dashboard">
  <?php if (mag('content') && cms_module_aan('paginas')): ?>
  <a class="card card--hoofd" href="paginas.php"><h2>Pagina&rsquo;s</h2><p>Beheer inhoud, extra pagina&rsquo;s, zichtbaarheid en indexering voor <?= $paginaAantal ?> pagina&rsquo;s.</p></a>
  <?php endif; ?>
  <?php if (mag('content') && cms_module_aan('collectie')): ?>
  <a class="card" href="collectie.php"><h2><?= e(CMS_COLLECTIE_LABEL) ?></h2><p>Voeg onbeperkt <?= e(CMS_COLLECTIE_ITEM_LABEL) ?>s met tekst, categorie en afbeelding toe.</p></a>
  <?php endif; ?>
  <?php if (mag('content') && cms_module_aan('faq')): ?>
  <a class="card" href="faq.php"><h2>Veelgestelde vragen</h2><p>Voeg vragen toe, wijzig de volgorde of verwijder ze.</p></a>
  <?php endif; ?>
  <?php if (mag('seo') && cms_module_aan('seo')): ?>
  <a class="card" href="seo.php"><h2>SEO</h2><p>Beheer titel, omschrijving, canonical, social-afbeelding en indexering per pagina.</p></a>
  <?php endif; ?>
  <?php if (mag('media') && cms_module_aan('media')): ?>
  <a class="card" href="media.php"><h2>Media</h2><p><?= $mediaAantal ?> bestand(en) in de centrale beeldbank.</p></a>
  <?php endif; ?>
  <?php if (mag('content') && cms_module_aan('navigatie')): ?>
  <a class="card" href="navigatie.php"><h2>Navigatie</h2><p>Beheer hoofdmenu en footermenu zonder de templates te wijzigen.</p></a>
  <?php endif; ?>
  <?php if (mag('content') && cms_module_aan('sectie_indeling') && function_exists('site_sectie_register') && site_sectie_register()): ?>
  <a class="card" href="sectie-indeling.php"><h2>Pagina-indeling</h2><p>Zet geregistreerde siteonderdelen aan of uit en bepaal hun volgorde.</p></a>
  <?php endif; ?>
</div>

<h2 class="dashboard-group">Instellingen &amp; beheer</h2>
<div class="cards cards--dashboard">
  <?php if (mag('forms') && cms_module_aan('formulieren')): ?>
  <a class="card" href="formulieren.php"><h2>Formulieren</h2><p><?= $nieuwAantal ?> nieuwe inzending(en). Beheer ook ontvangers en formulierstatus.</p></a>
  <?php endif; ?>
  <?php if (mag('settings') && cms_module_aan('redirects')): ?>
  <a class="card" href="redirects.php"><h2>Redirects</h2><p>Beheer permanente en tijdelijke doorverwijzingen.</p></a>
  <?php endif; ?>
  <?php if (mag('settings')): ?>
  <a class="card" href="instellingen.php"><h2>Site-instellingen</h2><p>Stel sitenaam, URL, contactgegevens en sociale links centraal in.</p></a>
  <?php endif; ?>
  <?php if (mag('users')): ?>
  <a class="card" href="gebruikers.php"><h2>Gebruikers</h2><p>Start met de hoofdbeheerder en voeg later beheerders, redacteuren of SEO-accounts toe.</p></a>
  <?php endif; ?>
  <?php if (mag('security') && cms_module_aan('toegang')): ?>
  <a class="card" href="toegang.php"><h2>Toegang &amp; bezoekers</h2><p>Bekijk recente bezoeken en blokkeer IP-adressen of landen.</p></a>
  <?php endif; ?>
  <?php if (mag('backup')): ?>
  <a class="card" href="versies.php"><h2>Versies &amp; back-up</h2><p>Zet eerdere inhoud terug of download een volledige JSON-back-up.</p></a>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_foot.php'; ?>
