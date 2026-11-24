<?php
// Render één publieke pagina naar stdout. Alleen voor de statische demo-export.
if (PHP_SAPI !== 'cli') { exit(1); }
define('IMMENS_LOCAL_PREVIEW', true);
$root = dirname(__DIR__) . '/public_html';
$route = $argv[1] ?? '';
$theme = $argv[2] ?? '';
$_GET = array();
$_POST = array();
$_COOKIE = array();
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/' . $route;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['DOCUMENT_ROOT'] = $root;
if ($route === 'offerte') {
    require $root . '/offerte.php';
} elseif ($route === 'privacy') {
    $_GET = array('p' => 'privacy');
    require $root . '/eigen-pagina.php';
} elseif ($route === 'beheer') {
    require $root . '/_lib/site.php';
    $thema = 'blauwdruk'; $content = cms_content();
    documentkop('home', 'Beheer | Immens Stucwerken');
    echo '<main class="breed sectie" id="inhoud"><h1>Dit is een ontwerpvoorbeeld.</h1><p style="margin:25px 0">Deze demo toont de website van Immens Stucwerken. Het beheer en het versturen van aanvragen zijn niet actief in deze demo. Het volledige CMS blijft bewaard voor de definitieve website.</p><a class="knop" href="/">Terug naar de startpagina</a></main>';
    documentslot();
} elseif ($route === '404') {
    require $root . '/404.php';
} else {
    if ($route !== '' && $route !== 'blauwdruk') { exit(1); }
    if ($route !== '') { $_GET['thema'] = $route; }
    require $root . '/index.php';
}
