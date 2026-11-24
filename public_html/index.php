<?php
require_once __DIR__ . '/_lib/site.php';
$thema = 'blauwdruk';
if (isset($_GET['thema']) && $_GET['thema'] !== 'blauwdruk') { require __DIR__ . '/404.php'; exit; }
cms_public_boot('home');
$content = cms_content();
$projecten = projecten_laden();
contact_verwerken();
documentkop('home', 'Immens Stucwerken | Sterk in strak stucwerk.', strip_tags(veld($content, 'home_intro')));
require __DIR__ . '/themas/blauwdruk.php';
projectdialogen();
documentslot();
