<?php
require_once __DIR__ . '/_lib/site.php';
$thema = thema_voor();
if (isset($_GET['thema']) && $thema === '') { http_response_code(404); require __DIR__ . '/404.php'; exit; }
cms_public_boot($thema ?: 'home');
$content = cms_content();
$projecten = projecten_laden();
contact_verwerken();
documentkop($thema ?: 'home', 'Immens Stucwerken' . ($thema ? ' | ' . ontwerpen()[$thema]['naam'] : ' | Vier ontwerprichtingen'), strip_tags(veld($content, 'home_intro')));
if ($thema === '') {
    require __DIR__ . '/themas/keuze.php';
} else {
    previewbalk();
    // Een vaste whitelist; nooit een willekeurig invoerpad includen.
    $templates = array('atelier'=>'atelier.php','blauwdruk'=>'blauwdruk.php','galerie'=>'galerie.php','werkplaats'=>'werkplaats.php');
    require __DIR__ . '/themas/' . $templates[$thema];
    projectdialogen();
}
documentslot();
