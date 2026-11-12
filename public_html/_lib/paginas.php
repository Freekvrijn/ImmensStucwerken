<?php
/** Paginazichtbaarheid voor vaste, door de LLM geregistreerde templates. */
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/velden.php';
require_once __DIR__ . '/cms.php';

function pagina_status_alles() { return load_json('paginastatus.json', array()); }

function pagina_is_actief($sleutel) {
    $register = site_pagina_register();
    if (!isset($register[$sleutel])) { return false; }
    if (!empty($register[$sleutel]['kern'])) { return true; }
    $status = pagina_status_alles();
    return !isset($status[$sleutel]['actief']) || !empty($status[$sleutel]['actief']);
}

function pagina_is_indexeerbaar($sleutel) {
    $status = pagina_status_alles();
    return empty($status[$sleutel]['noindex']);
}

function pagina_slug_voor_url($url) {
    foreach (site_pagina_register() as $sleutel => $pagina) {
        if ((string) ($pagina['url'] ?? '') === (string) $url) { return $sleutel; }
    }
    return '';
}

function pagina_toegang_bewaken($sleutel) {
    if (pagina_is_actief($sleutel)) { return; }
    if (function_exists('mag') && mag('content')) { return; }
    http_response_code(404);
    header('Cache-Control: no-store');
    exit('Deze pagina is niet beschikbaar.');
}

/** Genereert alleen na koppeling een sitemap voor actieve, indexeerbare pagina's. */
function sitemap_regenereren() {
    if (!defined('CMS_PUBLIEKE_SITE_AAN') || !CMS_PUBLIEKE_SITE_AAN || cms_site_url() === '') { return true; }
    $regels = array('<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');
    foreach (site_pagina_register() as $sleutel => $pagina) {
        if (!pagina_is_actief($sleutel) || !pagina_is_indexeerbaar($sleutel)) { continue; }
        $locatie = cms_site_url() . ltrim((string) ($pagina['url'] ?? ''), '/');
        $regels[] = '  <url><loc>' . htmlspecialchars($locatie, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc></url>';
    }
    $regels[] = '</urlset>';
    return @file_put_contents(dirname(__DIR__) . '/sitemap.xml', implode("\n", $regels) . "\n", LOCK_EX) !== false;
}
