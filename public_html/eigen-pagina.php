<?php
require_once __DIR__ . '/_lib/site.php';
$thema = 'blauwdruk';
$slug = is_string($_GET['p'] ?? null) ? $_GET['p'] : '';
$content = cms_content();
if ($slug === 'privacy') {
    cms_public_boot('privacy');
    $pagina = array('titel'=>strip_tags(veld($content,'privacy_titel')),'intro'=>'','html'=>'<p>' . tekst('privacy_tekst') . '</p>', 'afbeelding'=>'');
    $sleutel = 'privacy';
} else {
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || !($pagina = cms_eigen_pagina($slug))) { require __DIR__ . '/404.php'; exit; }
    $sleutel = eigen_register_sleutel($slug);
    cms_public_boot($sleutel);
}
documentkop($sleutel, $pagina['seo_titel'] ?? ($pagina['titel'] . ' | Immens Stucwerken'), $pagina['omschrijving'] ?? '');
sitekop();
?>
<main class="eigen-pagina breed sectie" id="inhoud"><a class="knop" href="/">Terug naar Immens</a><h1><?= e($pagina['titel']) ?></h1>
<?php if(!empty($pagina['intro'])): ?><p class="intro"><?= e($pagina['intro']) ?></p><?php endif; ?>
<?php if(!empty($pagina['afbeelding']) && fotopad($pagina['afbeelding'])): ?><img class="eigen-foto" src="<?= e(fotopad($pagina['afbeelding'])) ?>" alt="<?= e($pagina['afbeelding_alt'] ?? '') ?>"><?php endif; ?>
<?php if($slug === 'privacy'): ?><div class="eigen-inhoud"><?= $pagina['html'] ?><?php if(CMS_MODULE_GEOIP && function_exists('geoip_attributie_html')): ?><p><?= geoip_attributie_html() ?></p><?php endif; ?></div><?php else: foreach(cms_eigen_secties($pagina) as $sectie): if($sectie === 'inhoud'): ?>
<div class="eigen-inhoud"><?= eigen_html_filteren($pagina['html']) ?></div><?php tekstrijen_toon('eigen-' . $slug); ?>
<?php endif; endforeach; endif; ?>
</main>
<?php sitevoet(); documentslot(); ?>
